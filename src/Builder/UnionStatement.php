<?php

namespace Tabula17\Satelles\Omnia\Roga\Builder;

use Tabula17\Satelles\Omnia\Roga\Descriptor\UnionDescriptor;

class UnionStatement implements StatementProcessorInterface
{
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
        foreach ($this->statementParts->unions as $k => $part) {
            //echo 'UNION PART -> ' , var_export($part, true), "\n";
            $this->parts[] = new SelectStatement($part, $this->expression, "u$k");
        }
        $params = [];
        foreach ($this->parts as $part) {
            // echo 'UNION PART ->params', $part->statementId, var_export($part->getParams(), true), "\n";
            //$this->params = array_merge($this->params, $part->getParams());
            $params[] = $part->getParams();
        }
        $this->params = array_merge(...$params);
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
        if (!str_starts_with($placeholder, ':')) {
            $placeholder = ':' . $placeholder;
        }
        $this->values[$placeholder] = $value;
        if (isset($this->params[$placeholder])) {
            $this->params[$placeholder]->setValue($value);
            if ($this->params[$placeholder]->bindable) {
                $this->bindings[$placeholder] = $this->params[$placeholder]->getValue();
            }
        }
        foreach ($this->parts as $select) {
            $select->setValue($placeholder, $value);
        }
        return $this;
    }

    public function getValue(string $placeholder): mixed
    {
        return $this->values[$placeholder] ?? null;
    }

    public function removeValue(string $placeholder): UnionStatement
    {
        unset($this->values[$placeholder], $this->bindings[$placeholder]);
        foreach ($this->parts as $select) {
            $select->removeValue($placeholder);
        }
        return $this;
    }

    public function process(): UnionStatement
    {
        /**
         * @var SelectStatement $select ;
         */
        foreach ($this->parts as $select) {
            $select->prettyPrint = $this->prettyPrint && $this->prettyPrintDeep;
        }
        return $this;
    }

    public function getParam(string $placeholder): ?Param
    {
        return $this->params[$placeholder];
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
        return isset($this->values[$placeholder]);
    }
}