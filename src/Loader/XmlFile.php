<?php

namespace Tabula17\Satelles\Omnia\Roga\Loader;

use Psr\Log\LoggerInterface;
use Tabula17\Satelles\Omnia\Roga\Collection\StatementCollection;
use Tabula17\Satelles\Omnia\Roga\Descriptor\Descriptors;
use Tabula17\Satelles\Omnia\Roga\Exception\ExceptionDefinitions;
use Tabula17\Satelles\Omnia\Roga\Exception\InvalidArgumentException;
use Tabula17\Satelles\Omnia\Roga\LoaderInterface;
use Tabula17\Satelles\Utilis\Array\ArrayUtilities;
use Tabula17\Satelles\Utilis\Cache\CacheManagerInterface;

/**
 * Represents an XML file loader that processes and transforms XML queries into readable arrays.
 * Implements the `LoaderInterface` to ensure compatibility with other loaders.
 */
class XmlFile implements LoaderInterface
{
    //  private(set) ?CacheManagerInterface $cacheManager;
    private(set) string $baseDir {
        set {
            if (!realpath($value) || !is_dir($value)) {
                throw new InvalidArgumentException(sprintf(ExceptionDefinitions::DIRECTORY_NOT_FOUND->value, $value));
            }
            if (str_ends_with($value, DIRECTORY_SEPARATOR)) {
                $value = substr($value, 0, -1);
            }
            $this->baseDir = realpath($value);
        }
    }
    public array $variantsMembers = [
        'client',
        'clients',
        'variant',
        'variants',
        'allowed',
    ];

    /**
     */
    public function __construct(string $baseDir, private(set) readonly ?CacheManagerInterface $cacheManager = null, private readonly ?LoggerInterface $logger = null)
    {
        $this->baseDir = $baseDir;
    }

    /**
     * Sanitizes the path to ensure it is valid and returns the sanitized path.
     * @param string $path
     * @return string
     */
    private function sanitizePath(string $path): string
    {
        $end = '';
        if (str_ends_with($path, '.xml')) {
            $path = substr($path, 0, -4);
            $end = '.xml';
        }
        $path = str_replace(['\\', '/', '.', '|'], DIRECTORY_SEPARATOR, $path);
        $pathArr = explode(DIRECTORY_SEPARATOR, $path);

        $pathArr = array_filter($pathArr, static fn($value) => !empty($value));
        array_unshift($pathArr, $this->baseDir);
        return implode(DIRECTORY_SEPARATOR, $pathArr) . $end;
    }

    /**
     * Loads the XML file and returns the parsed array.
     * @param string $name
     * @param array $subqueries
     * @param string|null $parent
     * @return array
     */
    private function loadXml(string $name, array &$subqueries = [], ?string $parent = null): array
    {
        if (!str_ends_with($name, '.xml')) {
            $name .= '.xml';
        }
        $this->logger?->info("Loading XML file: $name from directory '$this->baseDir'");

        $array = ArrayUtilities::simpleXmlToArray(simplexml_load_string(file_get_contents($this->sanitizePath($name))));
        if (isset($array['statement']) && !array_is_list($array['statement'])) {
            $array['statement'] = [$array['statement']];
        }
        $list = $this->checkForSubQueries($this->checkArrayListsInChild($array['statement']), $parent, $subqueries);

        return [
            'statements' => $list,
            'subqueries' => $subqueries,
        ];
    }

    /**
     * Checks for subqueries and loads them recursively.
     * @param array $collection
     * @param string|int|null $parent
     * @param array|null $subqueries
     * @return array
     */
    private function checkForSubQueries(array $collection, null|string|int $parent = null, ?array &$subqueries = null): array
    {
        $newList = [];
        foreach ($collection as $key => $value) {
            $this->logger?->debug("Checking for subquery in $key");
            if ($parent !== null && $parent !== 'statement') {
                $keys = explode('.', $parent);
                if (($k = array_search('statement', $keys)) !== false) {
                    unset($keys[$k]);
                }
                $keys[] = $key;
                $keyPath = implode('.', $keys);

            }
            if (is_array($value)) {
                if (isset($subqueries) && (strtolower($key) === 'subquery' || strtolower($key) === 'union')) {
                    $path = ($keyPath ?? $key); // en la definicion de array con subquery anidado se suma el 'descriptor
                    $this->logger?->debug("Found subquery in $key. Path => $path");
                    $newList[$key] = $path;
                    $pathParts = explode('.', $path);
                    if (is_numeric($pathParts[0])) {
                        unset($pathParts[0]);
                    }
                    $sq = false;
                    $unsetNext = false;
                    foreach ($pathParts as $k => $part) {
                        if ($sq === false) {
                            $sq = $part === 'subquery';
                        }
                        if ($unsetNext) {
                            unset($pathParts[$k]);
                        }
                        $unsetNext = $sq && $part === 'descriptor';
                    }
                    $sqPath = implode('.', $pathParts);
                    if (!isset($subqueries[$sqPath])) {
                        $subqueries[$sqPath] = [];
                    }
                    if (!isset($value['path'])) {
                        $newList[$key] = $this->checkForSubQueries($value, $keyPath ?? $key, $subqueries);
                        continue;
                    }
                    if (!isset($subqueries[$sqPath][$value['path']])) {
                        $subquery = $this->loadXml($value['path'], $subqueries, ($path ? "$path." : '') . 'descriptor');
                        if (isset($value['arguments'])) {
                            $subquery['arguments'] = $value['arguments'];
                        }
                        unset($subquery['subqueries']);
                        $subqueries[$sqPath][$value['path']] = $subquery;
                    }
                    $newList[$key] = $value;
                } else {
                    $newList[$key] = $this->checkForSubQueries($value, $keyPath ?? $key, $subqueries);
                }
            } else {
                $newList[$key] = $value;
            }
        }
        return $newList;
    }

    /**
     * Checks for arrays that are not lists and converts them to lists.
     * @param array $collection
     * @param string|null $parent
     * @return array
     */
    private function checkArrayListsInChild(array $collection, ?string $parent = null): array
    {
        $newList = [];
        foreach ($collection as $key => $value) {
            if (is_array($value)) {
                if (count($collection) === 1 && $parent && substr($parent, 0, -1) === $key) {
                    if (array_is_list($value)) {
                        $this->logger?->debug("Found redundant array list in $key. Merge with parent ($parent).");
                        foreach ($value as $v) {
                            $newList[] = is_array($v) ? $this->checkArrayListsInChild($v, $key) : $v;
                        }
                    } else {
                        $newList[] = $this->checkArrayListsInChild($value, $key);
                    }
                } else {
                    $newList[$key] = $this->checkArrayListsInChild($value, $key);
                }
            } else if ($parent && substr($parent, 0, -1) === $key) {
                $newList[] = $value;
            } else {
                $newList[$key] = $value;
            }
        }
        return $newList;
    }

    /**
     * Returns an array of statement descriptors based on the specified XML file.
     * @param string $name
     * @param bool $forceReload
     * @return array|null
     */
    public function getStatementDescriptors(string $name, bool $forceReload = false): ?array
    {
        $this->logger?->info("Loading statement descriptors for $name");
        if ($forceReload || !$this->cacheManager || !$this->cacheManager?->has($name)) {
            $descriptors = [];
            ['statements' => $statements, 'subqueries' => $subqueries] = $this->loadXml($name);

            foreach ($statements as $statement) {
                $subsDetectedByKey = ArrayUtilities::getArrayPathsByKey($statement, 'subquery');
                $descriptorClass = Descriptors::fromName($statement['type'] ?? 'select');
                $variants = array_intersect_key($statement['metadata'], array_flip($this->variantsMembers));
                $subqueriesForPath = [];
                if (!empty($subsDetectedByKey)) {
                    foreach ($subsDetectedByKey as $subqueryPath) {
                        $detected = ArrayUtilities::getArrayValueByPath($statement, $subqueryPath);
                        if (isset($detected['path'], $subqueries[$subqueryPath][$detected['path']])) {
                            if (!isset($subqueriesForPath[$subqueryPath])) {
                                $subqueriesForPath[$subqueryPath] = [];
                            }
                            if (!isset($subqueriesForPath[$subqueryPath][$detected['path']])) {
                                $subqueriesForPath[$subqueryPath][$detected['path']] = $subqueries[$subqueryPath][$detected['path']];
                            }
                        }
                    }
                }

                foreach ($variants as $member => $variant) {
                    $this->logger?->debug("Processing variant $member " . var_export($variant, true));
                    foreach ($variant as $allowed) {
                        $descriptor = $statement;
                        ArrayUtilities::setArrayValueByPath($descriptor, 'metadata.' . $member, $allowed);

                        foreach ($subqueries as $path => $subDescriptors) {

                            $currentPaths = ArrayUtilities::getArrayPathsByKey($descriptor, 'subquery', []);

                            if (ArrayUtilities::pathExisits($descriptor, $path)) {
                                $subqueryBase = ArrayUtilities::getArrayValueByPath($descriptor, $path);
                                if (isset($subqueryBase['path'])) {
                                    $subqueryDescriptors = $subDescriptors[$subqueryBase['path']] ?? [];
                                    if (!empty($subqueryDescriptors)) {
                                        foreach ($subqueryDescriptors['statements'] as $sqKey => $sqDescriptor) {
                                            $allowedForSubquery = ArrayUtilities::getArrayValueByPath($sqDescriptor, 'metadata.' . $member);
                                            if ((isset($allowedForSubquery) && in_array($allowed, $allowedForSubquery)) || $allowed === '*') {
                                                ArrayUtilities::setArrayValueByPath($sqDescriptor, 'metadata.' . $member, $allowed);
                                                $subqueryDescriptor = ['descriptor' => $sqDescriptor];
                                                if (isset($subqueryDescriptors['arguments'])) {
                                                    $subqueryDescriptor['arguments'] = $subqueryDescriptors['arguments'];
                                                }
                                                ArrayUtilities::setArrayValueByPath($descriptor, $path, $subqueryDescriptor);
                                                break;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        $checkOrphanPaths = ArrayUtilities::getArrayPathsByKey($descriptor, 'path', []);
                        if (empty($checkOrphanPaths)) {
                            $descriptors[] = new $descriptorClass($descriptor);// $descriptor;
                        }
                    }
                }
            }
            $this->cacheManager?->set($name, $descriptors);
            $this->logger?->debug("Statement descriptors for $name loaded and refreshed successfully.");
            return $descriptors;
        }
        $this->logger?->debug("Statement descriptors for $name already loaded from cache.");
        return $this->cacheManager->get($name);

    }

    /**
     * Retrieves a statement collection for the given name, optionally forcing a reload.
     *
     * @param string $name The name of the statement collection to retrieve.
     * @param bool $forceReload Whether to force reloading the statement collection.
     * @return StatementCollection|null The retrieved statement collection or null if not found.
     */
    public function getStatementCollection(string $name, bool $forceReload = false): ?StatementCollection
    {
        return new StatementCollection(...$this->getStatementDescriptors($name, $forceReload));
    }

}