<?php

namespace Tabula17\Satelles\Omnia\Roga\Builder;

use Tabula17\Satelles\Omnia\Roga\Builder\Expr\Andx;
use Tabula17\Satelles\Omnia\Roga\Builder\Expr\Arguments;
use Tabula17\Satelles\Omnia\Roga\Builder\Expr\From;
use Tabula17\Satelles\Omnia\Roga\Builder\Expr\Orx;
use Tabula17\Satelles\Omnia\Roga\Descriptor\DeleteDescriptor;
use Tabula17\Satelles\Omnia\Roga\Descriptor\TableDescriptor;
use Tabula17\Satelles\Omnia\Roga\Exception\ConfigException;
use Tabula17\Satelles\Omnia\Roga\Exception\ExceptionDefinitions;
use Tabula17\Satelles\Omnia\Roga\Exception\NotAllowedException;

class DeleteStatement implements StatementProcessorInterface
{


    private(set) array $values = [];
    private(set) array $ors = [];
    private(set) array $conditions = [];
    private Arguments $body;
    private(set) Andx $where;
    private(set) Andx $having;
    private(set) From $table;
    private(set) array $params = [];
    private array $columns = [];
    private array $bindings = [];
    private(set) string $statementId;

    /**
     * @throws ConfigException
     */
    public function __construct(
        private readonly DeleteDescriptor $statementParts,
        private readonly Expression       $expression = new Expression(),
        public bool                       $prettyPrint = false,
        public bool                       $prettyPrintDeep = false
    )
    {
        $this->statementId = uniqid(strtolower(basename(str_replace('\\', '/', $this::class))) . '::', false);
        $this->setTable()->setColumns();
        $this->body = new Arguments();
        $this->where = new Andx();
        $this->having = new Andx();

    }

    private function setTable(): DeleteStatement
    {

        if (isset($this->statementParts['table'])) {
            $table = $this->statementParts['table'];
            if ($table instanceof TableDescriptor) {
                //echo var_export($table, true), PHP_EOL;
                $table = $table->name;
            }
            $this->table = new From(is_array($table) ? implode('.', $table) : $table);
        }
        if (isset($this->statementParts['from'])) {
            $this->table = new From(
                table: $this->statementParts['from']['table'] ?? $this->statementParts['from']['name']
            );
        }
        return $this;
    }

    private function setColumns(): self
    {
        //echo 'SET COLUMNS -:: ', var_export($this->statementParts['into']['columns'], true), PHP_EOL;
        if (isset($this->statementParts['from']['columns'])) {
            foreach ($this->statementParts['from']['columns'] as $column) {
                $column = new Column($column, $this->expression);
                $column->process();
                if (isset($column->params) && count($column->params) > 0) {
                    foreach ($column->params as $param) {
                        $param->setColumnExpression($column->columnName);
                        if ($param->required && $param->defaultValue !== null && !isset($this->values[$param->placeholder])) {
                            $this->setValue($param->placeholder, $param->defaultValue);
                        }
                        $this->params[$param->placeholder] = $param;
                    }
                }
                if (isset($column->conditions) && count($column->conditions) > 0) {
                    foreach ($column->conditions as $condition) {
                        $condition->setColumnExpression($column->columnName);
                        if ($condition instanceof Param && $condition->required && $condition->defaultValue !== null && !isset($this->values[$condition->placeholder])) {
                            $this->setValue($condition->placeholder, $condition->defaultValue);
                        }
                        if ($condition instanceof Param && !isset($this->params[$condition->placeholder])) {
                            $this->params[$condition->placeholder] = $condition;
                        }
                        if ($condition instanceof Condition && !in_array($condition, $this->conditions, true)) {
                            $this->conditions[] = $condition;
                        }
                    }
                }
                $this->columns[] = $column;
            }
        }
        return $this;
    }

    private function processFilters(): DeleteStatement
    {
        foreach ($this->columns as $column) {
            if (isset($column->subquery) && $column->subquery instanceof SelectStatement) {
                $column->subquery->setValues($this->values);
                //$this->subqueries[$column->subqueriesForPath->statementId] = $column->subqueriesForPath;
                // SQ-> process!!
                $this->elevateSubqueryParams($column->subquery);
            }
            $column->process();
            $colExpression = $column->columnExpression;
            $colName = $column->columnName;
            foreach ($column->params as $placeholder => $param) {
                if (is_array($param)) {
                    if (!isset($this->params[$placeholder])) {
                        $this->params[$placeholder] = $param;
                    }
                    continue;
                }
                $this->processCondition($param, $param->needColumnExpression ? $colExpression : $colName);
            }
            foreach ($column->conditions as $condition) {
                // echo 'CONDITION -:: ', var_export($condition, true), PHP_EOL;
                $this->processCondition($condition, $condition->needColumnExpression ? $colExpression : $colName);
            }

        }
        return $this;
    }

    private function processWhere(): DeleteStatement
    {
        foreach ($this->conditions as $condition) {
            if ($condition instanceof Param) {
                if (empty($this->values[$condition->placeholder]) && $condition->onnotempty) {
                    continue;
                }
                if (!empty($this->values[$condition->placeholder]) && $condition->onempty) {
                    continue;
                }
                if (isset($this->values[$condition->placeholder])) {
                    $condition->setValue($this->values[$condition->placeholder]);
                } else if (!$condition->required) {
                    continue;
                } else if ($condition->defaultValue !== null) {
                    $this->setValue($condition->placeholder, $condition->defaultValue);
                }
            }
            if (!empty((string)$condition)) {
                if ($condition->groupCondition) {
                    if (!$this->having->partExists((string)$condition)) {
                        $this->having->add((string)$condition);
                    }
                } else if (!$this->where->partExists((string)$condition)) {
                    $this->where->add((string)$condition);
                }
            }
        }
        foreach ($this->ors as $condition) {
            $or = new Orx();
            $hor = new Orx();
            if (is_array($condition)) {
                foreach ($condition as $c) {
                    if ($c instanceof Param) {
                        if (isset($this->values[$c->placeholder])) {
                            $c->setValue($this->values[$c->placeholder]);
                        } else if (!$c->required) {
                            continue;
                        } else if ($c->defaultValue !== null) {
                            $this->values[$c->placeholder] = $c->defaultValue;
                        }
                    }
                    if ($c->groupCondition) {
                        if (!$hor->partExists((string)$c)) {
                            $hor->add((string)$c);
                        }
                    } else if (!$or->partExists((string)$c)) {
                        $or->add((string)$c);
                    }
                }
            }
            if (($or->count() > 0) && !$this->where->partExists($or)) {
                $this->where->add($or);
            }
            if (($hor->count() > 0) && !$this->having->partExists($hor)) {
                $this->having->add($hor);
            }
        }
        return $this;
    }

    private function processCondition($condition, $colExpression): void
    {
        $condition->setColumnExpression($colExpression);
        if (empty((string)$condition)) {
            return;
        }
        if (isset($condition->subquery) && $condition->subquery instanceof self) {
            $this->elevateSubqueryParams($condition->subquery);
        }
        if ($condition->combined !== null) {
            if (!isset($this->ors[$condition->combined]) || !is_array($this->ors[$condition->combined])) {
                $this->ors[$condition->combined] = [];
            }
            if (!in_array($condition, $this->ors[$condition->combined], true)) {
                $this->ors[$condition->combined][] = $condition;
            }
        } else if (!in_array($condition, $this->conditions, true)) {
            $this->conditions[] = $condition;
        }
    }

    private function elevateSubqueryParams(SelectStatement $table): void
    {
        foreach ($table->params as $placeholder => $param) {
            if (!isset($this->params[$placeholder])) {
                $this->params[$placeholder] = $param;
            }
        }
    }

    public function getRequiredParams(): array
    {
        $params = [];
        foreach ($this->getParams() as $param) {
            if ($param->required) {
                $params[] = $param;
            }
        }
        return $params;
    }

    public function getOptionalParams(): array
    {
        $params = [];
        foreach ($this->getParams() as $param) {
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

    public function getParam(string $placeholder): ?Paraminterface
    {
        return $this->params[$placeholder] ?? null;
    }

    public function setValues(array $values): StatementProcessorInterface
    {
        foreach ($values as $placeholder => $value) {
            $this->setValue($placeholder, $value);
        }
        return $this;
    }

    public function setValue(string $placeholder, mixed $value): StatementProcessorInterface
    {
        $this->values[$placeholder] = $value;
        if (isset($this->params[$placeholder])) {
            $this->params[$placeholder]->setValue($value);
            if ($this->params[$placeholder]->bindable) {
                $this->bindings[$placeholder] = $this->params[$placeholder]->getValue();
            }
            if (isset($this->params[$placeholder]->subquery) && $this->params[$placeholder]->subquery instanceof self) {
                $this->params[$placeholder]->subquery->setValues($this->values);
                $this->params[$placeholder]->subquery->prettyPrint = $this->prettyPrint && $this->prettyPrintDeep;
                $this->elevateSubqueryParams($this->params[$placeholder]->subquery);
            }
        }
        return $this;
    }

    public function getValue(string $placeholder): mixed
    {
        return $this->values[$placeholder] ?? null;
    }

    public function removeValue(string $placeholder): StatementProcessorInterface
    {
        unset($this->values[$placeholder], $this->bindings[$placeholder]);
        return $this;
    }

    public function process(): StatementProcessorInterface
    {
        $this->processFilters()->processWhere();
        return $this;
    }

    public function getValues(): array
    {
        return $this->values;
    }
    public function getBindings(): array
    {
        return $this->bindings;
    }
    public function hasValue(string $placeholder): bool
    {
        return isset($this->values[$placeholder]);
    }
    public function __toString()
    {
        $this->process();
        $parts = [];
        $parts[] = Keywords::DELETE->value . " " . Keywords::FROM->value . " " . $this->table;
        if ($this->where->count() > 0) {
            $this->where->prettyPrint = $this->prettyPrint;
            $parts[] = Keywords::WHERE->value;
            $parts[] = $this->where;
        } else {
            throw new NotAllowedException(sprintf(ExceptionDefinitions::STATEMENT_WITHOUT_WHERE->value, 'DELETE'));
        }
        if ($this->having->count() > 0) {
            $this->having->prettyPrint = $this->prettyPrint && $this->prettyPrintDeep;
            $parts[] = Keywords::HAVING->value;
            $parts[] = $this->having;
        }

        $char = $this->prettyPrint ? "\n" : ' ';
        return implode($char, $parts);
    }
}