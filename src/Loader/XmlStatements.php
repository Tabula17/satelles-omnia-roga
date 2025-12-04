<?php

namespace Tabula17\Satelles\Omnia\Roga\Loader;

use Psr\Log\LoggerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use Tabula17\Satelles\Omnia\Roga\Exception\ExceptionDefinitions;
use Tabula17\Satelles\Omnia\Roga\Exception\InvalidArgumentException;
use Tabula17\Satelles\Omnia\Roga\LoaderInterface;
use Tabula17\Satelles\Omnia\Roga\LoaderStorageInterface;
use Tabula17\Satelles\Omnia\Roga\StatementBuilder;
use Tabula17\Satelles\Utilis\Cache\CacheManagerInterface;

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
        string                                  $baseDir,
        private(set) readonly ?CacheManagerInterface $cacheManager = null,
        private readonly ?LoggerInterface       $logger = null)
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
        return array_map(fn($file) => str_replace([$this->baseDir . DIRECTORY_SEPARATOR, '.xml'], '', $file->getPathname()), iterator_to_array($xmlFiles));
    }

    public function getLoader(): LoaderInterface
    {
        return new XmlFile($this->baseDir, $this->cacheManager, $this->logger);

    }

    public function clearCache(): void
    {
        $this->cacheManager?->clear();
    }

    /**
     * @throws \JsonException
     */
    public function getStatementInfo(string $name): array
    {
        $builder = new StatementBuilder(
            statementName: $name,
            loader: $this->getLoader(),
            reload: true
        );
        return [
            'cfg' => $name,
            'params' => [
                'required' => $builder->getRequiredParams(),
                'optional' => $builder->getOptionalParams(),
                'bindings' => $builder->getBindings()
            ],
            'metadata' => $builder->getMetadata() ?? [],
            'cached' => $this->cacheManager?->has($name) ?? false
        ];
    }
}