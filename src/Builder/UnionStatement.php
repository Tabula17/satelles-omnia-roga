<?php

namespace Tabula17\Satelles\Omnia\Roga\Builder;

use Tabula17\Satelles\Omnia\Roga\Descriptor\MetadataDescriptor;
use Tabula17\Satelles\Omnia\Roga\Descriptor\UnionDescriptor;

class UnionStatement implements StatementProcessorInterface
{
    use BindingsTrait;

    private(set) string $unionType;
    private array $parts = [];
    private(set) string $statementId;
    private(set) array $params = [];
    private(set) array $values = [];
    private(set) array $bindings = [];

    public function __construct(
        private readonly UnionDescriptor $statementParts,
        private readonly Expression      $expression = new Expression(),
        public bool                      $prettyPrint = false,
        public bool                      $prettyPrintDeep = false
    )
    {
        $this->unionType = $this->statementParts->unionAll ? Keywords::UNION_ALL->value : Keywords::UNION->value;
        $this->statementId = uniqid(strtolower(str_replace(' ', '', $this->unionType)) . '::', false);
        //echo 'UNION * -> ' , var_export($this->statementParts , true), "\n";
        $quote = $this->getQuote();
        foreach ($this->statementParts->unions as $k => $part) {
            //echo 'UNION PART -> ' , var_export($part, true), "\n";
            $statement = new SelectStatement($part, $this->expression, "u$k");
            if(!$statement->getMetadata()){
                $statement->overrideMetadata($this->statementParts->metadata);
            }else{
                $statement->getMetadata()->set('quoteIdentifier', $quote !== QuotesEnum::NONE);
                $statement->getMetadata()->set('quoteType', $quote);
            }

            $this->parts[] =  $statement;
        }
        // The same placeholder may appear in several UNION branches. Keep one
        // Param per placeholder, but never let a later optional branch hide a
        // required parameter declared in an earlier branch.
        foreach ($this->parts as $part) {
            foreach ($part->getParams() as $placeholder => $param) {
                if (!isset($this->params[$placeholder]) ||
                    ($param->required && !$this->params[$placeholder]->required)) {
                    $this->params[$placeholder] = $param;
                }
            }
        }
    }

    private function getQuote(): QuotesEnum
    {
        $quote = QuotesEnum::NONE;
        $metadata = $this->statementParts->metadata ?? null;
        if ($metadata?->quoteIdentifier) {
            $quote = $metadata->quoteType ?? QuotesEnum::DOUBLE_QUOTE;
        }
        return $quote;
    }


    public function getRequiredParams(): array
    {
        $params = [];
        /**
         * var Param $param;
         */
        foreach ($this->params as $param) {
            if ($param->required) {
                $params[] = $param;
            }
        }
        return $params;
    }

    public function getOptionalParams(): array
    {
        $params = [];
        /**
         * var Param $param;
         */
        foreach ($this->params as $param) {
            if (!$param->required) {
                $params[] = $param;
            }
        }
        return $params;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function getBindings(): array
    {
        return $this->bindings;
    }

    public function setValues(array $values): UnionStatement
    {
        foreach ($values as $placeholder => $value) {
            $this->setValue($placeholder, $value);
        }

        return $this;
    }

    public function setValue(string $placeholder, mixed $value): UnionStatement
    {
        $placeholder = $this->normalizePlaceholder($placeholder);
        $this->values[$placeholder] = $value;
        if (isset($this->params[$placeholder])) {
            $this->params[$placeholder]->setValue($value);
            $this->registerBinding($placeholder, $this->params[$placeholder]);
        }
        foreach ($this->parts as $select) {
            $select->setValue($placeholder, $value);
        }
        return $this;
    }

    public function getValue(string $placeholder): mixed
    {
        return $this->values[$this->normalizePlaceholder($placeholder)] ?? null;
    }

    public function removeValue(string $placeholder): UnionStatement
    {
        $placeholder = $this->normalizePlaceholder($placeholder);
        unset($this->values[$placeholder]);
        $this->removeExpandedBindings($placeholder);
        foreach ($this->parts as $select) {
            $select->removeValue($placeholder);
        }
        return $this;
    }

    public function process(): UnionStatement
    {
        $quote = $this->getQuote();
        /**
         * @var SelectStatement $select ;
         */
        foreach ($this->parts as $select) {
            $metadata = $select->getMetadata();
            if ($metadata === null) {
                $select->overrideMetadata([
                    'quoteIdentifier' => $quote !== QuotesEnum::NONE,
                    'quoteType' => $quote,
                ]);
            } else {
                $metadata->set('quoteIdentifier', $quote !== QuotesEnum::NONE);
                $metadata->set('quoteType', $quote);
            }
            $select->prettyPrint = $this->prettyPrint && $this->prettyPrintDeep;
        }
        return $this;
    }

    public function getParam(string $placeholder): ?Param
    {
        return $this->params[$this->normalizePlaceholder($placeholder)] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        $this->process();
        $parts = [];
        foreach ($this->parts as $part) {
            $parts[] = (string)$part;
        }
        $char = $this->prettyPrint ? "\n" : " ";
        return implode("$char$this->unionType$char", $parts);
    }

    public function getValues(): array
    {
        return $this->values;
    }

    public function hasValue(string $placeholder): bool
    {
        return isset($this->values[$this->normalizePlaceholder($placeholder)]);
    }

    public function getMetadata(): ?MetadataDescriptor
    {
        return $this->statementParts->metadata ?? null;
    }
}