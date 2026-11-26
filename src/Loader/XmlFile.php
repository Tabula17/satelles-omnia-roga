<?php

namespace Tabula17\Satelles\Omnia\Roga\Loader;

use Psr\Log\LoggerInterface;
use Tabula17\Satelles\Omnia\Roga\Collection\StatementCollection;
use Tabula17\Satelles\Omnia\Roga\Descriptor\DescriptorClasses;
use Tabula17\Satelles\Omnia\Roga\Descriptor\MetadataDescriptor;
use Tabula17\Satelles\Omnia\Roga\Exception\ConfigException;
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
        /**
         * @throws InvalidArgumentException
         */
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

    /** @var array<string, true> Canonical paths currently being parsed, used to detect cycles. */
    private array $loadingStack = [];

    /**
     */
    public function __construct(string $baseDir, private(set) readonly ?CacheManagerInterface $cacheManager = null, private readonly ?LoggerInterface $logger = null)
    {
        $this->baseDir = $baseDir;
    }

    /**
     * Resolves a logical statement name to a canonical, baseDir-confined file path.
     * Dots inside file names are preserved; traversal and absolute paths are rejected.
     *
     * @throws InvalidArgumentException|ConfigException
     */
    private function resolvePath(string $name): string
    {
        $path = str_replace(['\\', '|'], '/', $name);
        if (strcasecmp(substr($path, -4), '.xml') === 0) {
            $path = substr($path, 0, -4);
        }
        if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:/', $path) === 1) {
            throw new InvalidArgumentException(sprintf(ExceptionDefinitions::XML_FILE_OUTSIDE_BASE_DIR->value, $name, $this->baseDir));
        }
        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                throw new InvalidArgumentException(sprintf(ExceptionDefinitions::XML_FILE_OUTSIDE_BASE_DIR->value, $name, $this->baseDir));
            }
            $segments[] = $segment;
        }
        $candidate = $this->baseDir . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $segments) . '.xml';
        $resolved = realpath($candidate);
        if ($resolved === false || !is_file($resolved)) {
            throw new InvalidArgumentException(sprintf(ExceptionDefinitions::XML_FILE_NOT_FOUND->value, $candidate));
        }
        $base = realpath($this->baseDir);
        if ($base === false || !str_starts_with($resolved, $base . DIRECTORY_SEPARATOR)) {
            throw new InvalidArgumentException(sprintf(ExceptionDefinitions::XML_FILE_OUTSIDE_BASE_DIR->value, $name, $this->baseDir));
        }
        return $resolved;
    }

    /**
     * Cache keys must include baseDir, canonical path and file mtime: a bare name
     * collides between tenants sharing a cache backend and goes stale after edits.
     */
    private function cacheKey(string $name): string
    {
        $path = $this->resolvePath($name);
        return 'roga:' . hash('xxh128', $path . '|' . (filemtime($path) ?: 0) . '|v1');
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
        $path = $this->resolvePath($name);
        $this->logger?->info("Loading XML file: $name from directory '$this->baseDir'");
        if (isset($this->loadingStack[$path])) {
            throw new ConfigException(sprintf(ExceptionDefinitions::CIRCULAR_SUBQUERY->value, $path));
        }
        $this->loadingStack[$path] = true;
        try {
            $contents = @file_get_contents($path);
            if ($contents === false) {
                throw new InvalidArgumentException(sprintf(ExceptionDefinitions::XML_FILE_NOT_FOUND->value, $path));
            }
            $previous = libxml_use_internal_errors(true);
            $xml = simplexml_load_string($contents, 'SimpleXMLElement', LIBXML_NONET);
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            if ($xml === false) {
                throw new ConfigException(sprintf(ExceptionDefinitions::INVALID_XML->value, $path));
            }
            $array = ArrayUtilities::simpleXmlToArray($xml);
            if (!is_array($array) || !isset($array['statement'])) {
                throw new ConfigException(sprintf(ExceptionDefinitions::INVALID_STATEMENT_STRUCTURE->value, $path));
            }
            $statements = $array['statement'];
            if (!is_array($statements)) {
                throw new ConfigException(sprintf(ExceptionDefinitions::INVALID_STATEMENT_STRUCTURE->value, $path));
            }
            if (!array_is_list($statements)) {
                $statements = [$statements];
            }
            $list = $this->checkForSubQueries($this->checkArrayListsInChild($statements), $parent, $subqueries);

            return [
                'statements' => $list,
                'subqueries' => $subqueries,
            ];
        } finally {
            unset($this->loadingStack[$path]);
        }
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
            //$this->logger?->debug("Checking for subquery in $key");
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
        $childCount = count(array_filter(
            $collection,
            static fn(string $key): bool => $key !== '@attributes',
            ARRAY_FILTER_USE_KEY
        ));
        foreach ($collection as $key => $value) {
            if (is_array($value)) {
                if ($childCount === 1 && $parent && substr($parent, 0, -1) === $key) {
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
    private static function metadataValues(mixed $value): array
    {
        return array_map('strval', is_array($value) ? array_values($value) : [$value]);
    }

    /** ArrayUtilities::getArrayValueByPath() returns null for scalar leaves, so read metadata directly. */
    private static function subqueryMetadataValue(array $descriptor, string $member): mixed
    {
        $metadata = $descriptor['metadata'] ?? null;
        return is_array($metadata) ? ($metadata[$member] ?? null) : null;
    }

    /**
     * Only unresolved subquery/union references should invalidate a descriptor.
     */
    private function unresolvedSubqueryPaths(array $descriptor): array
    {
        $paths = [];
        foreach (ArrayUtilities::getArrayPathsByKey($descriptor, 'path', []) as $path) {
            $segments = explode('.', (string)$path);
            array_pop($segments);
            if (in_array('subquery', $segments, true) || in_array('union', $segments, true)) {
                $paths[] = $path;
            }
        }
        return $paths;
    }

    public function getStatementDescriptors(string $name, bool $forceReload = false): ?array
    {
        $this->logger?->info("Loading statement descriptors for $name");
        $cacheKey = $this->cacheKey($name);
        if (!$forceReload && $this->cacheManager !== null && $this->cacheManager->has($cacheKey)) {
            $cached = $this->cacheManager->get($cacheKey);
            if (is_array($cached) && array_is_list($cached)) {
                return $cached;
            }
        }

        $descriptors = [];
        ['statements' => $statements, 'subqueries' => $subqueries] = $this->loadXml($name);

        foreach ($statements as $statement) {
            $descriptorClass = DescriptorClasses::fromValue($statement['type'] ?? 'select')->value;
            $metadata = is_array($statement['metadata'] ?? null) ? $statement['metadata'] : [];
            $statementIdentifiers = array_intersect_key($metadata, array_flip(MetadataDescriptor::getIdentifiedBy()));
            if ($statementIdentifiers === []) {
                // <allowed> is optional; such a definition applies to every tenant.
                $statementIdentifiers = ['allowed' => '*'];
            }

            foreach ($statementIdentifiers as $member => $identifier) {
                $this->logger?->debug("Processing statements identified by $member " . var_export($identifier, true));
                foreach ((is_array($identifier) ? $identifier : [$identifier]) as $allowed) {
                    $descriptor = $statement;
                    ArrayUtilities::setArrayValueByPath($descriptor, 'metadata.' . $member, $allowed);

                    foreach ($subqueries as $path => $subDescriptorsByPath) {
                        if (!ArrayUtilities::pathExisits($descriptor, $path)) {
                            continue;
                        }
                        $subqueryBase = ArrayUtilities::getArrayValueByPath($descriptor, $path);
                        if (!is_array($subqueryBase) || !isset($subqueryBase['path'])) {
                            continue;
                        }
                        $subqueryDescriptors = $subDescriptorsByPath[$subqueryBase['path']] ?? null;
                        if ($subqueryDescriptors === null || empty($subqueryDescriptors['statements'])) {
                            continue;
                        }
                        $chosen = null;
                        if ((string)$allowed === '*') {
                            $chosen = $subqueryDescriptors['statements'][0];
                        } else {
                            // Exact tenant match wins; '*' is only a fallback.
                            foreach ([[false], [true]] as $wildcardPass) {
                                foreach ($subqueryDescriptors['statements'] as $candidate) {
                                    $values = self::metadataValues(
                                        self::subqueryMetadataValue($candidate, $member) ?? []
                                    );
                                    $matched = $wildcardPass[0]
                                        ? in_array('*', $values, true)
                                        : in_array((string)$allowed, $values, true);
                                    if ($matched) {
                                        $chosen = $candidate;
                                        break 2;
                                    }
                                }
                            }
                        }
                        if ($chosen === null) {
                            continue;
                        }
                        ArrayUtilities::setArrayValueByPath($chosen, 'metadata.' . $member, $allowed);
                        $subqueryDescriptor = ['descriptor' => $chosen];
                        if (isset($subqueryDescriptors['arguments'])) {
                            $subqueryDescriptor['arguments'] = $subqueryDescriptors['arguments'];
                        }
                        ArrayUtilities::setArrayValueByPath($descriptor, $path, $subqueryDescriptor);
                    }

                    $unresolved = $this->unresolvedSubqueryPaths($descriptor);
                    if ($unresolved !== []) {
                        // Keep the historical behaviour (skip the variant) but make it visible.
                        $this->logger?->error(sprintf(
                            ExceptionDefinitions::UNRESOLVED_SUBQUERY->value,
                            $name,
                            implode(', ', $unresolved)
                        ));
                        continue;
                    }
                    $descriptors[] = new $descriptorClass($descriptor);
                }
            }
        }

        $this->cacheManager?->set($cacheKey, $descriptors);
        $this->logger?->debug("Statement descriptors for $name loaded and refreshed successfully.");
        return $descriptors;
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
        $descriptors = $this->getStatementDescriptors($name, $forceReload);
        return $descriptors === null ? null : new StatementCollection(...$descriptors);
    }

}