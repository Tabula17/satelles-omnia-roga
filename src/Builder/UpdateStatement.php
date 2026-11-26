<?php

namespace Tabula17\Satelles\Omnia\Roga\Builder;

use Tabula17\Satelles\Omnia\Roga\Builder\Expr\Andx;
use Tabula17\Satelles\Omnia\Roga\Builder\Expr\Arguments;
use Tabula17\Satelles\Omnia\Roga\Builder\Expr\From;
use Tabula17\Satelles\Omnia\Roga\Builder\Expr\Orx;
use Tabula17\Satelles\Omnia\Roga\Descriptor\MetadataDescriptor;
use Tabula17\Satelles\Omnia\Roga\Descriptor\TableDescriptor;
use Tabula17\Satelles\Omnia\Roga\Descriptor\UpdateDescriptor;
use Tabula17\Satelles\Omnia\Roga\Exception\ConfigException;
use Tabula17\Satelles\Omnia\Roga\Exception\ExceptionDefinitions;
use Tabula17\Satelles\Omnia\Roga\Exception\NotAllowedException;
use Tabula17\Satelles\Omnia\Roga\Exception\ValueRequiredException;

class UpdateStatement implements StatementProcessorInterface
{
    use FormatTrait;
    use BindingsTrait;
    use CollisionTrait;
    private(set) array $values = [];
    private array $filters = [];
    private(set) array $ors = [];
    private(set) array $conditions = [];
    private Arguments $body;
    private(set) Andx $where;
    private(set) Andx $having;
    private(set) From $table;
    private(set) array $params = [];
    private array $bindings = [];
    private array $columns = [];

    //public bool $prettyPrint = false;
    private(set) string $statementId;

    /**
     * @throws ConfigException
     */
    public function __construct(
        private readonly UpdateDescriptor $statementParts,
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

    private function getQuote(): QuotesEnum
    {
        $quote = QuotesEnum::NONE;
        $metadata = $this->statementParts->metadata ?? null;
        if ($metadata?->quoteIdentifier) {
            $quote = $metadata->quoteType ?? QuotesEnum::DOUBLE_QUOTE;
        }
        return $quote;
    }
    private function setTable(): self
    {

        if (isset($this->statementParts['table'])) {
            $table = $this->statementParts['table'];
            if ($table instanceof TableDescriptor) {
                //echo var_export($table, true), PHP_EOL;
                $table = $table->name;
            }
            $this->table = new From(is_array($table) ? implode('.', $table) : $table);
        }
        if (isset($this->statementParts['to'])) {
            $this->table = new From(
                table: $this->statementParts['to']['table'] ?? $this->statementParts['to']['name']
            );
        }
        return $this;
    }

    /**
     * @throws ConfigException
     */
    private function setColumns(): void
    {
        $quote = $this->getQuote();
        //echo 'SET COLUMNS -:: ', var_export($this->statementParts['into']['columns'], true), PHP_EOL;
        if (isset($this->statementParts['to']['columns'])) {
            foreach ($this->statementParts['to']['columns'] as $column) {
                $column = new Column($column, $this->expression, $quote);
                $column->process();
                if (isset($column->params) && count($column->params) > 0) {
                    foreach ($column->params as $param) {
                        $param->setColumnExpression($column->columnName);
                        if ($param->required && $param->defaultValue !== null && !isset($this->values[$param->placeholder])) {
                            $this->setValue($param->placeholder, $param->defaultValue);
                        }
                        if (($param->writeFilter || $param->sqlexpression !== 'eq') && !isset($this->filters[$param->placeholder])) {
                            $this->filters[$param->placeholder] = $param;
                        } else {
                            $this->params[$param->placeholder] = $param;
                        }
                    }
                }
                if (isset($column->conditions) && count($column->conditions) > 0) {
                    foreach ($column->conditions as $condition) {
                        $condition->setColumnExpression($column->columnName);
                        if ($condition instanceof Param && $condition->required && $condition->defaultValue !== null && !isset($this->values[$condition->placeholder])) {
                            $this->setValue($condition->placeholder, $condition->defaultValue);
                        }
                        if ($condition instanceof Param && !isset($this->filters[$condition->placeholder])) {
                            $this->filters[$condition->placeholder] = $condition;
                        }
                        if ($condition instanceof Condition && !in_array($condition, $this->conditions, true)) {
                            $this->conditions[] = $condition;
                        }
                    }
                }
                $this->columns[] = $column;
            }
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

    private function processFilters(): UpdateStatement
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
                if (!isset($this->filters[$placeholder])) {
                    continue;
                }
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

    private function processWhere(): void
    {
        $collisions = [];
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
            $rendered = (string)$condition;
            if ($rendered !== '') {
                $scope = $condition->groupCondition ? 'HAVING' : 'WHERE';
                $this->assertNoCollision(
                    $collisions,
                    $this->collisionGroup(
                        $condition->collides,
                        $scope,
                        (string)$condition->getColumnExpression()
                    ),
                    $rendered,
                    $scope
                );
                if ($condition->groupCondition) {
                    if (!$this->having->partExists($rendered)) {
                        $this->having->add($rendered);
                    }
                } else if (!$this->where->partExists($rendered)) {
                    $this->where->add($rendered);
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
                    $rendered = (string)$c;
                    if ($rendered !== '') {
                        $scope = $c->groupCondition ? 'HAVING' : 'WHERE';
                        $this->assertNoCollision(
                            $collisions,
                            $this->collisionGroup(
                                $c->collides,
                                $scope,
                                (string)$c->getColumnExpression()
                            ),
                            $rendered,
                            $scope
                        );
                        if ($c->groupCondition) {
                            if (!$hor->partExists($rendered)) {
                                $hor->add($rendered);
                            }
                        } else if (!$or->partExists($rendered)) {
                            $or->add($rendered);
                        }
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
    }

    private function processParam($param, $colExpression): void
    {
        if (!isset($this->params[$param->placeholder])) {
            $this->params[$param->placeholder] = $param;
        }
        $this->processCondition($param, $colExpression);
    }

    private function processCondition($condition, $colExpression): void
    {
        $condition->setColumnExpression($colExpression);
        if (empty((string)$condition)) {
            return;
        }
        if (isset($condition->subquery) && $condition->subquery instanceof SelectStatement) {
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

    public function getBindings(): array
    {
        return $this->bindings;
    }
    public function getParams(): array
    {
        return array_merge($this->params, $this->filters);
    }

    public function setValues(array $values): UpdateStatement
    {
        foreach ($values as $placeholder => $value) {
            $this->setValue($placeholder, $value);
        }
        return $this;
    }

    public function setValue(string $placeholder, mixed $value): UpdateStatement
    {
        $placeholder = $this->normalizePlaceholder($placeholder);
        $this->values[$placeholder] = $value;
        if (isset($this->params[$placeholder])) {
            $this->params[$placeholder]->setValue($value);
            $this->registerBinding($placeholder, $this->params[$placeholder]);
            if (isset($this->params[$placeholder]->subquery) && $this->params[$placeholder]->subquery instanceof SelectStatement) {
                $this->params[$placeholder]->subquery->setValues($this->values);
                $this->params[$placeholder]->subquery->prettyPrint = $this->prettyPrint && $this->prettyPrintDeep;
                $this->elevateSubqueryParams($this->params[$placeholder]->subquery);
            }
        }
        if (isset($this->filters[$placeholder])) {
            $this->filters[$placeholder]->setValue($value);
            if ($this->filters[$placeholder]->bindable) {
                $this->bindings[$placeholder] = $this->filters[$placeholder]->getValue();
            }
            if (isset($this->filters[$placeholder]->subquery) && $this->filters[$placeholder]->subquery instanceof SelectStatement) {
                $this->filters[$placeholder]->subquery->setValues($this->values);
                $this->filters[$placeholder]->subquery->prettyPrint = $this->prettyPrint && $this->prettyPrintDeep;
                $this->elevateSubqueryParams($this->filters[$placeholder]->subquery);
            }
        }
        return $this;
    }

    public function getValue(string $placeholder): mixed
    {
        return $this->values[$this->normalizePlaceholder($placeholder)] ?? null;
    }

    public function removeValue(string $placeholder): UpdateStatement
    {
        $placeholder = $this->normalizePlaceholder($placeholder);
        unset($this->values[$placeholder]);
        $this->removeExpandedBindings($placeholder);
        return $this;
    }

    public function getValues(): array
    {
        return $this->values;
    }

    public function hasValue(string $placeholder): bool
    {
        return isset($this->values[$this->normalizePlaceholder($placeholder)]);
    }
    public function process(): StatementProcessorInterface
    {
        // process() runs on every render; rebuild SET to avoid duplicated assignments.
        $this->body->clear();
        foreach ($this->params as $argument) {
            $hasValue = isset($this->values[$argument->placeholder]) || isset($argument->defaultValue);
            if (!$hasValue && $argument->required === true) {
                $this->setValue($argument->placeholder, null);
            }
            if ($hasValue) {
                $this->setValue($argument->placeholder, $this->values[$argument->placeholder] ?? $argument->defaultValue);
                $this->body->add($argument);
            }
        }
        $this->processFilters()->processWhere();
        return $this;
    }

    /**
     * @throws NotAllowedException
     * @throws ValueRequiredException
     */
    public function __toString(): string
    {
        $quote = $this->getQuote();
        $this->process();
        $parts = [];
        $parts[] = Keywords::UPDATE->value . " " . $this->quoteIdentifier($this->table, $quote) ;
        $parts[] = Keywords::SET->value;
        if ($this->body->count() === 0) {
            throw new ValueRequiredException(ExceptionDefinitions::ARGUMENTS_LIST_EMPTY->value);
        }
        $this->body->prettyPrint = $this->prettyPrint;
        $parts[] = $this->body;
        if ($this->where->count() > 0) {
            $this->where->prettyPrint = $this->prettyPrint;
            $parts[] = Keywords::WHERE->value;
            $parts[] = $this->where;
        } else {
            throw new NotAllowedException(sprintf(ExceptionDefinitions::STATEMENT_WITHOUT_WHERE->value, 'UPDATE'));
        }
        $char = $this->prettyPrint ? "\n" : ' ';
        return implode($char, $parts);
    }


    public function getParam(string $placeholder): ?Param
    {
        return $this->params[$placeholder] ?? $this->filters[$placeholder] ?? null;
    }
    public function getMetadata(): ?MetadataDescriptor
    {
        return $this->statementParts->metadata ?? null;
    }
}