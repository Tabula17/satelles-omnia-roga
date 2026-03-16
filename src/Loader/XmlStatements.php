<?php

namespace Tabula17\Satelles\Omnia\Roga\Loader;

use JsonException;
use Psr\Log\LoggerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use Tabula17\Satelles\Omnia\Roga\Collection\StatementCollection;
use Tabula17\Satelles\Omnia\Roga\Descriptor\MetadataDescriptor;
use Tabula17\Satelles\Omnia\Roga\Exception\ConfigException;
use Tabula17\Satelles\Omnia\Roga\Exception\ExceptionDefinitions;
use Tabula17\Satelles\Omnia\Roga\Exception\InvalidArgumentException;
use Tabula17\Satelles\Omnia\Roga\LoaderInterface;
use Tabula17\Satelles\Omnia\Roga\LoaderStorageInterface;
use Tabula17\Satelles\Omnia\Roga\StatementBuilder;
use Tabula17\Satelles\Utilis\Cache\CacheManagerInterface;
use Tabula17\Satelles\Utilis\Exception\RuntimeException;

/**
 * Class XmlStatements
 *
 * Implements the LoaderStorageInterface to manage XML statements within a specified directory.
 * This class provides functionality to list, load, cache, and clear cache for XML-based statements.
 */
class XmlStatements implements LoaderStorageInterface
{
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
     * @throws JsonException|ConfigException|RuntimeException
     */
    public function getStatementInfo(string $name, bool $forceReload = true): array
    {
        $loader = $this->getLoader(!$forceReload && $this->cacheManager?->has($name) ?? false);
        $builder = new StatementBuilder(
            statementName: $name,
            loader: $loader,
            reload: $forceReload,
            logger: $this->logger
        );
        $descriptors = [];

        $this->logger?->debug("Getting statement info for $name -> ". var_export($builder->getAllDescriptorsInfo(), true));
        $statementsInfo = $builder->getAllDescriptorsInfo();

        foreach ($statementsInfo as $statementInfo) {
            $identifier = array_intersect_key($statementInfo, array_flip(MetadataDescriptor::getIdentifiedBy()));
            $this->logger?->debug("🍄 Processing identifiers --> ".var_export($identifier, true));
            foreach ($identifier as $member => $value) {
                $desc = $builder->loadStatementBy($member, $value, $statementInfo['version'] ?? null, $statementInfo[MetadataDescriptor::getVariantMember()] ?? null);
                $descriptors[] = [
                    'type' => $desc->getStatementType(),
                    'memberIdentifier' => $member,
                    'memberVariant' => $statementInfo[MetadataDescriptor::getVariantMember()] ?? null,
                    'metadata' => $desc->getMetadata() ?? [],
                    'params' => [
                        'required' => $desc->getRequiredParams(),
                        'optional' => $desc->getOptionalParams()
                    ],
                ];

            }
        }
/*
        foreach (MetadataDescriptor::getIdentifiedBy() as $statementIdentifier) {
            $collection = $loader->getStatementCollection($name, true);
            $this->logger?->debug("🍄 Processing variant --> ".var_export($statementIdentifier, true));
            $statementsValues = $collection?->getMetadataMemberValues($statementIdentifier);//todo: check if this is correct, sometimes $variant is array??
            foreach ($statementsValues as $statementValue) {
                $this->logger?->debug("Processing variant $statementIdentifier " . var_export($statementValue, true));

                //foreach ($variantValue as $value) {
                $desc = $builder->loadStatementBy($statementIdentifier, $statementValue);
                $descriptors[] = [
                    'type' => $desc->getStatementType(),
                    'variantMember' => $statementIdentifier,
                    'metadata' => $desc->getMetadata() ?? [],
                    'params' => [
                        'required' => $desc->getRequiredParams(),
                        'optional' => $desc->getOptionalParams()
                    ],
                ];
            }

        }*/

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
        $loaderWithCache = $this->getLoader(true);
        $loader = $this->getLoader();
        return $loader->getStatementDescriptors($name, true) === $loaderWithCache->getStatementDescriptors($name, false);
    }
}