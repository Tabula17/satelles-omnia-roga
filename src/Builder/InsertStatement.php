<?php

namespace Tabula17\Satelles\Omnia\Roga\Builder;

use Tabula17\Satelles\Omnia\Roga\Builder\Expr\Arguments;
use Tabula17\Satelles\Omnia\Roga\Builder\Expr\From;
use Tabula17\Satelles\Omnia\Roga\Descriptor\InsertDescriptor;
use Tabula17\Satelles\Omnia\Roga\Descriptor\TableDescriptor;
use Tabula17\Satelles\Omnia\Roga\Exception\ConfigException;
use Tabula17\Satelles\Omnia\Roga\Exception\ExceptionDefinitions;

class InsertStatement implements StatementProcessorInterface
{

    private(set) array $values = [];
    private array $arguments = [];
    private Arguments $columns;
    private Arguments $placeholders;
    private(set) From $table;
    private(set) array $params = [];
    private(set) array $bindings = [];
    private(set) ?SelectStatement $select;
    private(set) string $statementId;

    /**
     * @throws ConfigException
     */
    public function __construct(
        private readonly InsertDescriptor $statementParts,
        private readonly Expression       $expression = new Expression(),
        public bool                       $prettyPrint = false,
        public bool                       $prettyPrintDeep = false
    )
    {
        $this->statementId = uniqid('insert::', false);
        $this->columns = new Arguments();
        $this->placeholders = new Arguments();
        $this->columns->surrounded = true;
        $this->placeholders->surrounded = true;
        $this->setTable()->setSelect()->setColumns()->setArguments();
    }

    private function setTable(): InsertStatement
    {
        if (isset($this->statementParts['table'])) {
            $table = $this->statementParts['table'];
            if ($table instanceof TableDescriptor) {
                //echo var_export($table, true), PHP_EOL;
                $table = $table->name;
            }
            $this->table = new From(is_array($table) ? implode('.', $table) : $table);
        }
        if (isset($this->statementParts['into'])) {
            $this->table = new From(
                table: $this->statementParts['into']['table'] ?? $this->statementParts['into']['name']
            );
        }
        return $this;
    }

    private function setColumns(): self
    {
        //echo 'SET COLUMNS -:: ', var_export($this->statementParts['into']['columns'], true), PHP_EOL;
        if (isset($this->statementParts['into']['columns'])) {
            foreach ($this->statementParts['into']['columns'] as $column) {
                //echo 'PARAM ', var_export($column, true), PHP_EOL;
                if (isset($column->params) && $column->params->count() > 0) {
                    foreach ($column->params as $param) {

                        $argument = new Param(
                            descriptor: $param,
                            expression: $this->expression
                        );
                        $argument->setColumnExpression($column->name);
                        $this->params[$argument->placeholder] = $argument;
                    }
                } else if (isset($this->select) && $this->select instanceof SelectStatement) {
                    $this->columns->add($column->name);
                }
                /*
                $param = new Param(
                    descriptor: $column,
                    expression: $this->expression
                );
                $this->params[$param->placeholder] = $param;*/
            }
        }
        return $this;
    }

    /**
     * @throws ConfigException
     */
    private function setArguments(): void
    {
        if (isset($this->statementParts['arguments'])) {
            foreach ($this->statementParts['arguments'] as $argument) {
                $param = new Param(
                    descriptor: $argument,
                    expression: $this->expression
                );
                $this->params[$param->placeholder] = $param;
            }
        }
    }

    private function setSelect(): self
    {
        if (isset($this->statementParts['select'])) {
            $this->select = new SelectStatement($this->statementParts['select']);
        }
        return $this;
    }

    public function getRequiredParams(): array
    {
        $params = [];
        foreach ($this->params as $param) {
            if ($param->required === true) {
                $params[] = $param;
            }
        }
        return $params;
    }

    public function getOptionalParams(): array
    {
        $params = [];
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

    public function setValues(array $values): InsertStatement
    {
        foreach ($values as $placeholder => $value) {
            $this->setValue($placeholder, $value);
        }
        return $this;
    }

    public function setValue(string $placeholder, mixed $value): InsertStatement
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
        return $this;
    }

    public function getValue(string $placeholder): mixed
    {
        return $this->values[$placeholder] ?? null;
    }

    public function removeValue(string $placeholder): InsertStatement
    {
        unset($this->values[$placeholder], $this->bindings[$placeholder]);
        return $this;
    }

    public function getValues(): array
    {
        return $this->values;
    }

    public function hasValue(string $placeholder): bool
    {
        return isset($this->values[$placeholder]);
    }
    public function process(): InsertStatement
    {
        foreach ($this->params as $param) {
            // if (isset($this->values[$argument->placeholder]) || $argument->required === true) {
            //$this->setValue($argument->placeholder, $this->values[$argument->placeholder] ?? $argument->defaultValue);
            if ($param->isValid()) {
                //echo 'PARAM valid -> ', $param->paramName, ' -> ', $param->placeholder, ' = ', $param->getValue(), PHP_EOL;
                $this->columns->add($param->columnExpression);
                $this->placeholders->add($param->placeholder);
            }
        }
        if (isset($this->select)) {
            $this->select->setValues($this->values);
            $this->select->prettyPrint = $this->prettyPrint && $this->prettyPrintDeep;
            $this->select->process();
        }
        return $this;
    }

    public function __toString()
    {
        $this->process();
        $prettyChar = $this->prettyPrint ? "\n" : ' ';
        $this->columns->prettyPrint = $this->prettyPrint;
        $this->placeholders->prettyPrint = $this->prettyPrint;

        $insert = [Keywords::INSERT->value . ' ' . Keywords::INTO->value. ' ' . $this->table, $this->columns];
        if (!empty($this->params)) {
            $insert[] = Keywords::VALUES->value;
            $insert[] = $this->placeholders;
        } else if (isset($this->select)) {
            if($this->columns->count()!==$this->select->select->count()){
                throw new ConfigException(ExceptionDefinitions::INSERT_COLUMNS_AND_SELECT_COUNT_MISMATCH->value);
            }
            $insert[] = $this->select;
        } else {
            throw new ConfigException(ExceptionDefinitions::INSERT_WHITEOUT_BODY->value);
        }
        return implode($prettyChar, $insert);

        // return Keywords::INSERT->value . ' ' . Keywords::INTO->value . ' ' . $prettyChar . $this->table . $this->columns . $prettyChar . ' ' . Keywords::VALUES->value . ' ' . $this->placeholders;
    }

    public function getParam(string $placeholder): ?Param
    {
        return $this->params[$placeholder];
    }

    public function getBindings(): array
    {
        return $this->bindings;
    }
}