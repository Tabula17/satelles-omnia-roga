<?php

namespace Tabula17\Satelles\Omnia\Roga;

use Psr\Log\LoggerInterface;
use Tabula17\Satelles\Omnia\Roga\Builder\Column;
use Tabula17\Satelles\Omnia\Roga\Builder\DeleteStatement;
use Tabula17\Satelles\Omnia\Roga\Builder\ExecuteStatement;
use Tabula17\Satelles\Omnia\Roga\Builder\Expression;
use Tabula17\Satelles\Omnia\Roga\Builder\InsertStatement;
use Tabula17\Satelles\Omnia\Roga\Builder\Param;
use Tabula17\Satelles\Omnia\Roga\Builder\ParamTypes;
use Tabula17\Satelles\Omnia\Roga\Builder\SelectStatement;
use Tabula17\Satelles\Omnia\Roga\Builder\StatementProcessorInterface;
use Tabula17\Satelles\Omnia\Roga\Builder\UnionStatement;
use Tabula17\Satelles\Omnia\Roga\Builder\UpdateStatement;
use Tabula17\Satelles\Omnia\Roga\Collection\StatementCollection;
use Tabula17\Satelles\Omnia\Roga\Descriptor\DeleteDescriptor;
use Tabula17\Satelles\Omnia\Roga\Descriptor\ExecuteDescriptor;
use Tabula17\Satelles\Omnia\Roga\Descriptor\InsertDescriptor;
use Tabula17\Satelles\Omnia\Roga\Descriptor\SelectDescriptor;
use Tabula17\Satelles\Omnia\Roga\Descriptor\StatementDescriptor;
use Tabula17\Satelles\Omnia\Roga\Descriptor\UnionDescriptor;
use Tabula17\Satelles\Omnia\Roga\Descriptor\UpdateDescriptor;
use Tabula17\Satelles\Omnia\Roga\Exception\ConfigException;
use Tabula17\Satelles\Omnia\Roga\Exception\ExceptionDefinitions;
use Tabula17\Satelles\Utilis\Collection\DataModelCollection;
use Tabula17\Satelles\Utilis\Data\DataTypes;
use Tabula17\Satelles\Utilis\Data\ModelDescriptor;
use Tabula17\Satelles\Utilis\Exception\RuntimeException;

class StatementBuilder
{

    private StatementCollection $collection;
    private ?StatementDescriptor $descriptor = null;
    private ?string $descriptorCacheKey = null;
    private ?StatementProcessorInterface $processor = null;

    /**
     * Constructor method for initializing the object with the provided statement name, reload flag, and loader instance.
     *
     * @param string $statementName Name of the statement to be loaded.
     * @param bool $reload Optional. Whether to reload the statement collection. Default is false.
     * @param LoaderInterface $loader The loader instance used to retrieve the statement collection.
     *
     * @return void
     * @throws \JsonException
     */
    public function __construct(
        private readonly string $statementName,
        public LoaderInterface $loader,
        bool $reload = false,
        private readonly ?LoggerInterface $logger = null)
    {
        $this->collection = $this->loader->getStatementCollection($statementName, $reload);
    }

    private static function stringify(mixed $value): string
    {
        if (is_array($value)) {
            return '[' . implode(', ', array_map(static fn($item) => self::stringify($item), $value)) . ']';
        }
        return is_scalar($value) || $value === null ? (string)$value : get_debug_type($value);
    }

    public function getDescriptorBy(string $member, mixed $value, string $version = 'latest', ?string $variant = 'default'): ?Descriptor\StatementDescriptor
    {
        $cacheKey = implode('|', [$member, self::stringify($value), $version, self::stringify($variant)]);
        if ($this->descriptorCacheKey !== $cacheKey) {
            $this->logger?->debug(sprintf(
                '%s: StatementBuilder::getDescriptorBy(%s, %s, %s, %s)',
                $this->statementName,
                $member,
                self::stringify($value),
                $version,
                self::stringify($variant)
            ));
            $this->descriptor = $this->collection
                ->getDescriptorsByMetadata($member, $value)
                ?->getDescriptorsByVersion($version)
                ?->getVariant($variant ?? 'default');
            $this->descriptorCacheKey = $cacheKey;
        }
        return $this->descriptor;
    }

    public function getAllDescriptors(): array
    {
        return $this->collection->toArray();
    }

    public function getAllDescriptorsInfo(): array
    {
        $info = [];
        foreach ($this->collection as $descriptor) {
            $info[] = array_filter($descriptor->metadata->toArray());
        }
        return $info;
    }

    /**
     * Retrieves a statement based on the specified metadata member and value.
     *
     * @param string $member The name of the metadata member to search for.
     * @param mixed $value The corresponding value of the metadata member.
     *
     * @return StatementBuilder Returns the respective statement object
     *         (SelectStatement, InsertStatement, UpdateStatement, DeleteStatement, or ExecuteStatement)
     *         based on the descriptor type, or null if no matching descriptor is found.
     * @throws ConfigException
     * @throws RuntimeException
     */
    public function loadStatementBy(string $member, mixed $value, string $version = 'latest', ?string $variant = 'default'): self
    {
        $this->descriptor = null;
        $this->descriptorCacheKey = null;
        $this->processor = null;
        $this->logger?->debug(sprintf(
            'StatementBuilder::loadStatementBy(%s, %s, %s, %s)',
            $member,
            self::stringify($value),
            $version,
            self::stringify($variant)
        ));
        $descriptor = $this->getDescriptorBy($member, $value, $version, $variant);
        if (!$descriptor instanceof StatementDescriptor) {
            throw new RuntimeException(sprintf(
                ExceptionDefinitions::STATEMENT_NOT_FOUND_FOR_VARIANT->value,
                self::stringify($value),
                $this->statementName
            ));
        }
        if (isset($descriptor) && $descriptor instanceof Descriptor\StatementDescriptor) {
            $expression = new Expression();
            $statement = null;
            if ($descriptor instanceof SelectDescriptor) {
                $statement = new SelectStatement(
                    statementParts: $descriptor,
                    expression: $expression,
                    prettyPrint: false,
                    prettyPrintDeep: false
                );
            }
            if ($descriptor instanceof UnionDescriptor) {
                $statement = new UnionStatement(
                    statementParts: $descriptor,
                    expression: $expression,
                    prettyPrint: false,
                    prettyPrintDeep: false
                );
            }
            if ($descriptor instanceof InsertDescriptor) {
                $statement = new InsertStatement(
                    statementParts: $descriptor,
                    expression: $expression,
                    prettyPrint: false,
                    prettyPrintDeep: false
                );
            }
            if ($descriptor instanceof UpdateDescriptor) {
                $statement = new UpdateStatement(
                    statementParts: $descriptor,
                    expression: $expression,
                    prettyPrint: false,
                    prettyPrintDeep: false
                );
            }
            if ($descriptor instanceof DeleteDescriptor) {
                $statement = new DeleteStatement(
                    statementParts: $descriptor,
                    expression: $expression,
                    prettyPrint: false,
                    prettyPrintDeep: false
                );
            }
            if ($descriptor instanceof ExecuteDescriptor) {
                $statement = new ExecuteStatement(
                    statementParts: $descriptor,
                    expression: $expression,
                );
            }
            if (!isset($statement)) {
                throw new RuntimeException(sprintf(ExceptionDefinitions::STATEMENT_NOT_FOUND_FOR_VARIANT->value, $value, $this->statementName ?? ''));
            }
            $this->processor = $statement;
        }
        return $this;
    }

    public function getMetadata(): array
    {
        return $this->descriptor?->metadata->toArray() ?? [];
    }

    public function getMetadataValue(string $key): mixed
    {
        return $this->descriptor?->metadata->get($key);
    }

    public function getRequiredParams(): ?array
    {
        return $this->processor?->getRequiredParams();
    }

    public function getOptionalParams(): ?array
    {
        return $this->processor?->getOptionalParams();
    }

    public function getParams(): ?array
    {
        return $this->processor?->getParams();
    }

    public function getParam(string $placeholder): ?Param
    {
        return $this->processor?->getParam($placeholder);
    }

    public function getParamType(string $placeholder): int
    {
        $type = $this->processor?->getParam($placeholder)?->getType();
        return ParamTypes::fromName($type) ?? ParamTypes::STR->value;
    }

    public function setValues(array $params): self
    {
        $this->processor?->setValues($params);
        return $this;
    }

    public function setValue(string $placeholder, mixed $value): self
    {
        $this->processor?->setValue($placeholder, $value);
        return $this;
    }

    public function getValue(string $placeholder): mixed
    {
        return $this->processor?->getValue($placeholder);
    }

    public function getBindings(): array
    {
        return $this->processor?->bindings ?? [];
    }

    public function getStatement(): ?string
    {
        return $this->processor?->__toString();
    }

    public function getPrettyStatement(): ?string
    {
        if (!isset($this->processor)) {
            return null;
        }
        $previous = $this->processor->prettyPrint ?? false;
        $this->processor->prettyPrint = true;
        try {
            return $this->processor->__toString();
        } finally {
            $this->processor->prettyPrint = $previous;
        }
    }

    public function removeValue(string $placeholder): self
    {
        $this->processor?->removeValue($placeholder);
        return $this;
    }

    public function getValues(): array
    {
        return $this->processor?->values ?? [];
    }

    /**
     * Retrieves the type of the statement from the descriptor.
     *
     * @return Descriptor\DescriptorClasses The type of the statement.
     */
    public function getStatementType(): Descriptor\DescriptorClasses
    {
        return $this->descriptor?->type ?? throw new RuntimeException('No statement has been loaded.');
    }

    public function getModel(): DataModelCollection
    {
        $model = new DataModelCollection();
        if (!isset($this->processor)) {
            return $model;
        }
        if ($this->processor instanceof SelectStatement || $this->processor instanceof UnionStatement) {
            $this->processor->process();
            foreach ($this->processor->columns as $column) {
                /**
                 * @var Column $column
                 */
               //var_dump($column);
                $model->set($column->modelName(), new ModelDescriptor(
                    title: $column->modelName(),
                    description: $column->description??$column->columnName,
                    dataTypes: $column->type ?? DataTypes::STR,
                ));
            }
        }
        return $model;
    }

}