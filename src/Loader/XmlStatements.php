<?php

namespace Tabula17\Satelles\Omnia\Roga\Loader;

use Psr\Log\LoggerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use Tabula17\Satelles\Omnia\Roga\Collection\StatementCollection;
use Tabula17\Satelles\Omnia\Roga\Exception\ConfigException;
use Tabula17\Satelles\Omnia\Roga\Exception\ExceptionDefinitions;
use Tabula17\Satelles\Omnia\Roga\Exception\InvalidArgumentException;
use Tabula17\Satelles\Omnia\Roga\LoaderInterface;
use Tabula17\Satelles\Omnia\Roga\LoaderStorageInterface;
use Tabula17\Satelles\Omnia\Roga\StatementBuilder;
use Tabula17\Satelles\Utilis\Cache\CacheManagerInterface;

/**
 * Class XmlStatements
 *
 * Implements the LoaderStorageInterface to manage XML statements within a specified directory.
 * This class provides functionality to list, load, cache, and clear cache for XML-based statements.
 */
class XmlStatements implements LoaderStorageInterface
{
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

    public function __construct(
        string                            $baseDir,
        private(set) readonly ?CacheManagerInterface $cacheManager = null,
        private readonly ?LoggerInterface $logger = null)
    {
        $this->baseDir = $baseDir;
        $this->logger?->info("XML statements directory: $baseDir");
    }

    public function listAvailableStatements(): array
    {

        $directoryIterator = new RecursiveDirectoryIterator($this->baseDir);
        $recursiveIterator = new RecursiveIteratorIterator($directoryIterator);

// Filter for files ending with .xml (case-insensitive)
        $xmlFiles = new RegexIterator(
            $recursiveIterator,
            '/\.xml$/i',
            RegexIterator::MATCH
        );
        return array_values(array_map(fn($file) => str_replace([$this->baseDir . DIRECTORY_SEPARATOR, '.xml'], '', $file->getPathname()), iterator_to_array($xmlFiles)));
    }

    public function getLoader(bool $withCache = false): LoaderInterface
    {
        return new XmlFile(
            baseDir: $this->baseDir,
            cacheManager: $withCache ? $this->cacheManager : null,
            logger: $this->logger);

    }

    public function clearCache(): void
    {
        if (!$this->cacheManager) {
            $this->logger?->warning("No cache manager available, skipping cache clear");
            return;
        }
        $this->cacheManager?->clear();
    }

    public function clearStatementCache(string $name): void
    {
        if (!$this->cacheManager) {
            $this->logger?->warning("No cache manager available, skipping cache clear for $name");
            return;
        }
        if (!$this->cacheManager->has($name)) {
            $this->logger?->warning("No cache found for $name");
            return;
        }
        $this->logger?->info("Clearing cache for $name");
        $this->cacheManager->delete($name);
    }

    /**
     * @throws \JsonException|ConfigException
     */
    public function getStatementInfo(string $name): array
    {
        $loader = $this->getLoader();
        $builder = new StatementBuilder(
            statementName: $name,
            loader: $loader,
            reload: true
        );
        $descriptors = [];
        foreach (StatementCollection::$metadataVariantKeywords as $variant) {
            $collection = $loader->getStatementCollection($name, true);
            $variants = $collection?->availableVariantsByMetadata($variant);
            foreach ($variants as $variantValue) {
                $this->logger?->debug("Processing variant $variant " . var_export($variantValue, true));
                //foreach ($variantValue as $value) {
                $desc = $builder->loadStatementBy($variant, $variantValue);
                if ($desc instanceof StatementBuilder) {
                    $descriptors[] = [
                        'type' => (string)$desc->getStatementType(),
                        'variantMember' => $variant,
                        'metadata' => $desc->getMetadata() ?? [],
                        'params' => [
                            'required' => $desc->getRequiredParams(),
                            'optional' => $desc->getOptionalParams()
                        ],

                    ];
                }
                //}
            }

        }

        return [
            'cfg' => $name,
            'variants' => $descriptors,
            'cached' => $this->cacheManager?->has($name) ?? false
        ];
    }

    public function compareFromCache(string $name): bool
    {
        if (!$this->cacheManager) {
            $this->logger?->warning("No cache manager available, skipping cache comparison");
            return false;
        }
        $loader = $this->getLoader();
        $loaderWithCache = $this->getLoader(true);
        return $loader->getStatementDescriptors($name, true) === $loaderWithCache->getStatementDescriptors($name, false);
    }
}