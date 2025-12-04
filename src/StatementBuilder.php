<?php

namespace Tabula17\Satelles\Omnia\Roga;

use Psr\Log\LoggerInterface;
use Tabula17\Satelles\Omnia\Roga\Builder\DataTypes;
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
use Tabula17\Satelles\Utilis\Exception\RuntimeException;

class StatementBuilder
{

    private StatementCollection $collection;
    private ?StatementDescriptor $descriptor = null;
    private StatementProcessorInterface $processor;

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
    public function __construct(private readonly string $statementName, public LoaderInterface $loader, bool $reload = false/*, private ?LoggerInterface $logger = null*/)
    {
        $this->collection = $this->loader->getStatementCollection($statementName, $reload);
    }

    public function getDescriptorBy(string $member, mixed $value): ?Descriptor\StatementDescriptor
    {
        if (!isset($this->descriptor)) {
            $this->descriptor = $this->collection->getDescriptorByMetadata($member, $value);
        }
        return $this->descriptor;
    }

    public function getAllDescriptors()
    {
        return $this->collection->toArray();
    }

    /**
     * Retrieves a statement based on the specified metadata member and value.
     *
     * @param string $member The name of the metadata member to search for.
     * @param mixed $value The corresponding value of the metadata member.
     *
     * @return StatementProcessorInterface|null Returns the respective statement object
     *         (SelectStatement, InsertStatement, UpdateStatement, DeleteStatement, or ExecuteStatement)
     *         based on the descriptor type, or null if no matching descriptor is found.
     * @throws ConfigException
     */
    public function loadStatementBy(string $member, mixed $value): self
    {
        $this->descriptor = $this->collection->getDescriptorByMetadata($member, $value);
        $expression = new Expression();
        $statement = null;
        if ($this->descriptor instanceof SelectDescriptor) {
            $statement = new SelectStatement(
                selectParts: $this->descriptor,
                expression: $expression,
                prettyPrint: false,
                prettyPrintDeep: false
            );
        }
        if ($this->descriptor instanceof UnionDescriptor) {
            $statement = new UnionStatement(
                statementParts: $this->descriptor,
                expression: $expression,
                prettyPrint: false,
                prettyPrintDeep: false
            );
        }
        if ($this->descriptor instanceof InsertDescriptor) {
            $statement = new InsertStatement(
                statementParts: $this->descriptor,
                expression: $expression,
                prettyPrint: false,
                prettyPrintDeep: false
            );
        }
        if ($this->descriptor instanceof UpdateDescriptor) {
            $statement = new UpdateStatement(
                statementParts: $this->descriptor,
                expression: $expression,
                prettyPrint: false,
                prettyPrintDeep: false
            );
        }
        if ($this->descriptor instanceof DeleteDescriptor) {
            $statement = new DeleteStatement(
                statementParts: $this->descriptor,
                expression: $expression,
                prettyPrint: false,
                prettyPrintDeep: false
            );
        }
        if ($this->descriptor instanceof ExecuteDescriptor) {
            $statement = new ExecuteStatement(
                statementParts: $this->descriptor,
                expression: $expression,
            );
        }
        if (!isset($statement)) {
            throw new RuntimeException(sprintf(ExceptionDefinitions::STATEMENT_NOT_FOUND_FOR_VARIANT->value, $value, $this->statementName ?? ''));
        }
        $this->processor = $statement;
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

    public function setValues(array $params): void
    {
        $this->processor?->setValues($params);
    }

    public function setValue(string $placeholder, mixed $value): void
    {
        $this->processor?->setValue($placeholder, $value);
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
        if (isset($this->processor)) {
            $this->processor->prettyPrint = true;
            return $this->processor?->__toString();
        }
        return null;
    }

    public function removeValue(string $placeholder): void
    {
        $this->processor?->removeValue($placeholder);
    }

    public function getValues()
    {
        return $this->processor->values;
    }
    public function getStatementType(): string
    {
        return $this->descriptor->type;
    }
}