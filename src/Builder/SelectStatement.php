<?php

namespace Tabula17\Satelles\Omnia\Roga\Builder;

use Tabula17\Satelles\Omnia\Roga\Builder\Expr\Andx;
use Tabula17\Satelles\Omnia\Roga\Builder\Expr\From;
use Tabula17\Satelles\Omnia\Roga\Builder\Expr\GroupBy;
use Tabula17\Satelles\Omnia\Roga\Builder\Expr\OrderBy;
use Tabula17\Satelles\Omnia\Roga\Builder\Expr\Orx;
use Tabula17\Satelles\Omnia\Roga\Builder\Expr\Select;
use Tabula17\Satelles\Omnia\Roga\Builder\Expr\Join;
use Tabula17\Satelles\Omnia\Roga\Collection\ColumnCollection;
use Tabula17\Satelles\Omnia\Roga\Descriptor\ColumnDescriptor;
use Tabula17\Satelles\Omnia\Roga\Descriptor\MetadataDescriptor;
use Tabula17\Satelles\Omnia\Roga\Descriptor\SelectDescriptor;
use Tabula17\Satelles\Omnia\Roga\Exception\ConfigException;
use Tabula17\Satelles\Utilis\Config\AbstractDescriptor;

class SelectStatement implements StatementProcessorInterface
{
    use FormatTrait;
    use BindingsTrait;
    use CollisionTrait;

    private(set) array $modifiersStart = [];
    private(set) array $modifiersEnd = [];
    private(set) array $columns = [];
    private(set) array $selects = [];
    private(set) array $joinsParts = [];
    private(set) array $ors = [];
    private(set) array $conditions = [];
    private(set) array $havings = [];
    private(set) array $groupBys = [];
    private(set) array $orderBys = [];
    private(set) array $params = [];
    private(set) Select $select;
    private(set) From $from;
    private(set) array $joins = [];
    private(set) Andx $where;
    private(set) Andx $having;
    private(set) GroupBy $groupBy;
    private(set) OrderBy $orderBy;
    private(set) array $values = [];
    private int $countAlias = 0;
    //public bool $prettyPrint = false;
    //public bool $prettyPrintDeep = false;
    private(set) string $statementId;
    private(set) array $bindings = [];

    //private array $subqueries = [];
    private bool $initialized = false;

    public function __construct(
        private readonly SelectDescriptor $statementParts,
        private readonly Expression       $expression = new Expression(),
        private readonly string           $baseAlias = 'a',
        public bool                       $prettyPrint = false,
        public bool                       $prettyPrintDeep = false
    )
    {
        $this->select = new Select();
        $this->where = new Andx();
        $this->having = new Andx();
        $this->groupBy = new GroupBy();
        $this->orderBy = new OrderBy();

        $this->statementId = uniqid('select::', false);
    }

    private function init(): void
    {
        if ($this->initialized) {
            return;
        }
        $this->initialized = true;
        $this->setFrom()->setJoins();
    }

    private function randAlias(): string
    {
        return $this->baseAlias . $this->countAlias++;
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

    private function setFrom(): self
    {
        $quote = $this->getQuote();
        $applyQuote = $quote !== QuotesEnum::NONE;

        if (isset($this->statementParts['from'])) {
            $alias = $this->statementParts['from']['alias'] ?? $this->randAlias();
            $isSubquery = isset($this->statementParts['from']['subquery']);
            if ($isSubquery) {
                $subQDescriptor = $this->statementParts['from']['subquery']['descriptor'] ?? $this->statementParts['from']['subquery'];
                $table = new SelectStatement(
                    $subQDescriptor,
                    $this->expression,
                );
                $table->statementParts->metadata->set('quoteIdentifier', $applyQuote);
                $table->statementParts->metadata->set('quoteType', $quote);
                $table->setValues($this->statementParts['from']['subquery']['arguments'] ?? []);
                $table->prettyPrint = $this->prettyPrint && $this->prettyPrintDeep;
                //$this->subqueries[$table->statementId] = $table;
                // SQ-> process!!
                $this->elevateSubqueryParams($table);
            } else {
                $table = $this->statementParts['from']['table'] ?? $this->statementParts['from']['name'];
                $table = $this->quoteIdentifier($table, $quote);
            }

            $this->from = new From(
                table: $table,
                alias: $this->quoteIdentifier($alias, $quote),
                isSubquery: $isSubquery
            );
            if (isset($this->statementParts['from']['columns'])) {
                $this->setColumns($this->statementParts['from']['columns'], $alias);
            }
        }
        if (isset($this->statementParts['distinct'])) {
            $this->modifiersStart['distinct'] = $this->statementParts['distinct'];
        }
        if (isset($this->statementParts['all'])) {
            $this->modifiersStart['all'] = $this->statementParts['all'];
        }
        if (isset($this->statementParts['top']) && $this->statementParts['top'] > 0) {
            $this->modifiersStart['top'] = $this->statementParts['top'];
        }
        if (isset($this->statementParts['forUpdate'])) {
            $this->modifiersEnd['forUpdate'] = $this->statementParts['forUpdate'];
        }
        if (isset($this->statementParts['lockInShareMode'])) {
            $this->modifiersEnd['lockInShareMode'] = $this->statementParts['lockInShareMode'];
        }
        if (isset($this->statementParts['forShare'])) {
            $this->modifiersEnd['forShare'] = $this->statementParts['forShare'];
        }
        if (isset($this->statementParts['forNoKeyUpdate'])) {
            $this->modifiersEnd['forNoKeyUpdate'] = $this->statementParts['forNoKeyUpdate'];
        }
        if (isset($this->statementParts['forKeyShare'])) {
            $this->modifiersEnd['forKeyShare'] = $this->statementParts['forKeyShare'];
        }
        if (isset($this->statementParts['forKeyNoWait'])) {
            $this->modifiersEnd['forKeyNoWait'] = $this->statementParts['forKeyNoWait'];
        }
        if (isset($this->statementParts['skipLocked'])) {
            $this->modifiersEnd['skipLocked'] = $this->statementParts['skipLocked'];
        }

        return $this;
    }

    private function elevateSubqueryParams(SelectStatement $table): void
    {
        foreach ($table->params as $placeholder => $param) {
            if (!isset($this->params[$placeholder])) {
                $this->params[$placeholder] = $param;
            }
        }
    }

    private function setJoins(): SelectStatement
    {
        if (isset($this->statementParts['joins'])) {
            foreach ($this->statementParts['joins'] as $join) {
                $alias = $this->join($join);
                if (isset($join['columns'])) {
                    $this->setColumns($join['columns'], $alias);
                }
            }
        }
        return $this;
    }

    private function join($join): string
    {
        $quote = $this->getQuote();
        $applyQuote = $quote !== QuotesEnum::NONE;
        if (isset($join['subquery']) && (is_array($join['subquery']) || $join['subquery']['descriptor'] instanceof SelectDescriptor)) {
            $table = new self($join['subquery']['descriptor'], $this->expression);
            $table->statementParts->metadata->set('quoteIdentifier', $applyQuote);
            $table->statementParts->metadata->set('quoteType', $quote);
            $table->setValues($join['subquery']['arguments'] ?? []);

            //$this->subqueries[$table->statementId] = $table;
            // SQ-> process!!
            $table->prettyPrint = $this->prettyPrint && $this->prettyPrintDeep;
            $this->elevateSubqueryParams($table);
            $alias = $join['alias'] ?? $this->randAlias();
        } else {
            $joinTable = new From($join['table'] ?? $join['name'], $join['alias'] ?? $this->randAlias());
            $table = $joinTable->table;
            $alias = $joinTable->alias;
        }

        $this->joinsParts[$alias] = [
            'table' => $table,
            'alias' => $alias,
            'type' => $join['type'] ?? 'INNER',
            'subquery' => isset($join['subquery']),
            'conditionType' => $join['conditionType'] ?? 'on'
        ];
        return $alias;
    }

    /**
     * Sets the columns for the current object instance.
     *
     * @param ColumnCollection $columns An array of columns to be processed and added. Each column should be an associative array, potentially containing a 'subqueriesForPath' key or other column metadata.
     * @param string|null $tableAlias An optional table alias to associate with the columns, used when no 'subqueriesForPath' is present in a column definition.
     * @return void
     * @throws ConfigException
     */
    private function setColumns(ColumnCollection $columns, ?string $tableAlias): void
    {

        $quote = $this->getQuote();
        //$this->columns = $columns;
        foreach ($columns as $columnConfig) {
            /* if (isset($columnConfig['subqueriesForPath'])) {
                 $columnConfig['subqueriesForPath'] = new self($columnConfig['subqueriesForPath'], $this->expression);
             }*/
            $columnConfig['tableAlias'] = $tableAlias;
            $column = new Column(
                descriptor: $columnConfig instanceof AbstractDescriptor ? $columnConfig : new ColumnDescriptor($columnConfig),
                expression: $this->expression,
                quote_identifier: $quote
            );
            $this->columns[$column->columnId] = $column; //$this->processColumn($colExpression);

            foreach ($column->params as $param) {
                if (!isset($this->params[$param->placeholder])) {
                    $this->params[$param->placeholder] = $param;
                    if ($param->required && $param->defaultValue !== null) {
                        $this->setValue($param->placeholder, $param->defaultValue);
                    }
                }
            }
            foreach ($column->joinParams as $param) {
                if (!isset($this->params[$param->placeholder])) {
                    $this->params[$param->placeholder] = $param;
                    if ($param->required && $param->defaultValue !== null) {
                        $this->setValue($param->placeholder, $param->defaultValue);
                    }
                }
            }
        }
    }

    public function getRequiredParams(): array
    {
        $this->init();
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
        $this->init();
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
        $this->init();
        return $this->params;
    }

    public function getBindings(): array
    {
        return $this->bindings;
    }

    public function setValues(array $values): SelectStatement
    {
        $this->init();
        foreach ($values as $placeholder => $value) {
            if (!str_starts_with($placeholder, ':')) {
                $placeholder = ':' . $placeholder;
            }
            $this->setValue($placeholder, $value);
        }

        return $this;
    }

    public function setValue(string $placeholder, mixed $value): SelectStatement
    {
        $this->init();
        $quote = $this->getQuote();
        $applyQuote = $quote !== QuotesEnum::NONE;
        if (!str_starts_with($placeholder, ':')) {
            $placeholder = ':' . $placeholder;
        }
        if (isset($this->params[$placeholder])) {
            $this->values[$placeholder] = $value;
            $this->params[$placeholder]->setValue($value);
            $this->registerBinding($placeholder, $this->params[$placeholder]);
            if (isset($this->params[$placeholder]->subquery) && $this->params[$placeholder]->subquery instanceof self) {
                $this->params[$placeholder]->subquery->statementParts->metadata->set('quoteIdentifier', $applyQuote);
                $this->params[$placeholder]->subquery->statementParts->metadata->set('quoteType', $quote);
                $this->params[$placeholder]->subquery->setValues($this->values);
                $this->params[$placeholder]->subquery->prettyPrint = $this->prettyPrint && $this->prettyPrintDeep;
                $this->elevateSubqueryParams($this->params[$placeholder]->subquery);
            }
        }
        return $this;
    }

    public function getValue(string $placeholder): mixed
    {
        $this->init();
        return $this->values[$this->normalizePlaceholder($placeholder)] ?? null;
    }

    public function removeValue(string $placeholder): SelectStatement
    {
        $this->init();
        $placeholder = $this->normalizePlaceholder($placeholder);
        unset($this->values[$placeholder]);
        $this->removeExpandedBindings($placeholder);
        return $this;
    }

    public function getValues(): array
    {
        $this->init();
        return $this->values;
    }

    public function hasValue(string $placeholder): bool
    {
        $this->init();
        return isset($this->values[$this->normalizePlaceholder($placeholder)]);
    }

    /**
     * @throws ConfigException
     */
    public function process(): SelectStatement
    {
        $this->init();
        $this->selects = [];
        $this->joins = [];
        $this->ors = [];
        $this->conditions = [];
        $this->havings = [];
        $this->groupBys = [];
        $this->orderBys = [];

        foreach ($this->columns as $column) {
            $this->processColumn($column);
        }

        $this->processSelect()
            ->processJoins()
            ->processWhere()
            ->processGroupBy()
            ->processOrderBy();
        return $this;
    }

    private function processSelect(): SelectStatement
    {
        if (count($this->selects) === 0) {
            $this->select->add('*');
        } else {
            $map = [];
            foreach ($this->selects as $select) {
                $map[$select instanceof Column ? $select->columnId : $select] = $select;
            }
            $this->select->clear();
            $this->select->addMultiple(array_values($map));
        }
        return $this;
    }

    /**
     * @param Column $column
     * @return void
     * @throws ConfigException
     */
    private function processColumn(Column $column): void
    {
        $quote = $this->getQuote();
        $applyQuote = $quote !== QuotesEnum::NONE;
        if (isset($column->subquery) && $column->subquery instanceof self) {
            $column->subquery->statementParts->metadata->set('quoteIdentifier', $applyQuote);
            $column->subquery->statementParts->metadata->set('quoteType', $quote);
            $column->subquery->setValues($this->values);
            //$this->subqueries[$column->subqueriesForPath->statementId] = $column->subqueriesForPath;
            // SQ-> process!!
            $this->elevateSubqueryParams($column->subquery);
        }
        $column->process();
        $colExpression = $column->columnExpression;
        $colName = $column->columnName;
        if (!empty($column->visible)) { // Si la columna es visible la agregamos al select
            // $select->add((string)$column);
            $this->selects[$column->columnId] = $column;
        }
        if ($column->grouped) {
            //$this->groupBy->add($colExpression);
            $this->groupBys[] = $colExpression;
        }
        if ($column->order) {
            // en el GROUP BY es importante el orden de las columnas
            $pos = $column->order['position'];
            if (isset($this->orderBys[$pos])) {
                array_splice($this->orderBys, $pos, 0, [[$colExpression, $column->order['direction'] ?? 'ASC']]);
            } else {
                $this->orderBys[$pos] = [$colExpression, $column->order['direction'] ?? 'ASC'];
            }
        }
        foreach ($column->params as $placeholder => $param) {
            /* if (is_array($param)) {
                 if (!isset($this->params[$placeholder])) {
                     $this->params[$placeholder] = $param;
                 }
                 continue;
             }*/
            $this->processCondition($param, $param->needColumnExpression ? $colExpression : $colName);
        }
        foreach ($column->conditions as $condition) {
            $this->processCondition($condition, $condition->needColumnExpression ? $colExpression : $colName);
        }
        $processJoinsConditions = count($column->joinConditions) > 0;
        $processJoinsParams = count($column->joinParams) > 0;
        if ($processJoinsConditions) {
            if (!isset($this->joinsParts[$column->tableAlias]['joinConditions']) || !is_array($this->joinsParts[$column->tableAlias]['joinConditions'])) {
                $this->joinsParts[$column->tableAlias]['joinConditions'] = [];
            }
            foreach ($column->joinConditions as $condition) {
                $condition->setColumnExpression($colExpression);
                $this->joinsParts[$column->tableAlias]['joinConditions'][] = $condition;
            }
        }
        if ($processJoinsParams) {
            if (!isset($this->joinsParts[$column->tableAlias]['joinParams']) || !is_array($this->joinsParts[$column->tableAlias]['joinParams'])) {
                $this->joinsParts[$column->tableAlias]['joinParams'] = [];
            }
            foreach ($column->joinParams as $condition) {
                $condition->setColumnExpression($colExpression);
                $this->joinsParts[$column->tableAlias]['joinParams'][] = $condition;
            }
        }
    }

    private function processCondition($condition, $colExpression): void
    {
        $condition->setColumnExpression($colExpression);
        if (empty((string)$condition)) {
            return;
        }
        if (isset($condition->subquery) && $condition->subquery instanceof self) {
            $quote = $this->getQuote();
            $condition->subquery->statementParts->metadata->set('quoteIdentifier', $quote !== QuotesEnum::NONE);
            $condition->subquery->statementParts->metadata->set('quoteType', $quote);
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

    private function processJoinConditions(): void
    {
        $procCondition = function ($condition, &$ors, &$conditions) {
            // $condition->setColumnExpression($colExpression);
            if ($condition->combined !== null) {
                if (!isset($ors[$condition->combined]) || !is_array($ors[$condition->combined])) {
                    $ors[$condition->combined] = [];
                }
                if (!in_array($condition, $this->ors[$condition->combined], true)) {
                    $ors[$condition->combined][] = $condition;
                }
            } else if (!in_array($condition, $this->conditions, true)) {
                $conditions[] = $condition;
            }
        };
        foreach ($this->joinsParts as $joinAlias => $joinDescriptor) {
            $ands = new Andx();
            $ors = [];
            $conditions = [];
            if (isset($joinDescriptor['joinConditions'])) {
                while (count($joinDescriptor['joinConditions']) > 0) {
                    $procCondition(array_shift($joinDescriptor['joinConditions']), $ors, $conditions);
                }
                /*  foreach ($joinDescriptor['joinConditions'] as $condition) {
                     $procCondition($condition, $ors, $conditions);
                 }*/
                if (count($conditions) > 0) {
                    $ands->addMultiple($conditions);
                    $conditions = [];
                }
                if (count($ors) > 0) {
                    foreach ($ors as $orMember) {
                        $or = new Orx();
                        $or->addMultiple($orMember);
                        $ands->add($or);
                    }
                    $ors = [];
                }
            }
            if (isset($joinDescriptor['joinParams'])) {
                foreach ($joinDescriptor['joinParams'] as $condition) {
                    $procCondition($condition, $ors, $conditions);
                }
                if (count($conditions) > 0) {
                    foreach ($conditions as $condition) {
                        if ($condition instanceof Param) {
                            if (isset($this->values[$condition->placeholder])) {
                                $condition->setValue($this->values[$condition->placeholder]);
                            }
                            if (isset($this->values[$condition->placeholder])) {
                                $condition->setValue($this->values[$condition->placeholder]);
                            }
                            $ands->add($condition);
                        }
                    }
                }
                if (count($ors) > 0) {
                    foreach ($ors as $orMember) {
                        $or = new Orx();
                        foreach ($orMember as $condition) {
                            if ($condition instanceof Param && isset($this->values[$condition->placeholder])) {
                                $condition->setValue($this->values[$condition->placeholder]);
                                $or->add($condition);
                            }
                        }
                        if ($or->count() > 0) {
                            $ands->add($or);
                        }
                    }
                }
            }
            $this->joinsParts[$joinAlias]['conditions'] = $ands;

        }
    }

    private function processJoins(): SelectStatement
    {

        $quote = $this->getQuote();
        $this->processJoinConditions();
        foreach ($this->joinsParts as $joinDescriptor) {
            if ($joinDescriptor['subquery'] && $joinDescriptor['table'] instanceof self) {
                $joinDescriptor['table']->setValues($this->values);
                $joinDescriptor['table']->prettyPrint = $this->prettyPrint && $this->prettyPrintDeep;

                //$this->subqueries[$joinDescriptor['table']->statementId] = $joinDescriptor['table'];
                // SQ-> process!!
                $this->elevateSubqueryParams($joinDescriptor['table']);
                $joinDescriptor['table'] = '(' . $joinDescriptor['table'] . ')';
            }

            $this->joins[] = new Join(
                joinType: $joinDescriptor['type'],
                join: $joinDescriptor['table'],
                alias: $joinDescriptor['alias'],
                conditionType: $joinDescriptor['conditionType'],
                condition: $joinDescriptor['conditions'],
                quote_identifier: $quote
            );
        }
        return $this;
    }

    private function processWhere(): SelectStatement
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
        return $this;
    }

    private function processGroupBy(): self
    {
        if (count($this->groupBys) > 0) {
            //echo 'GROUP BYS: ', implode(', ', $this->groupBys), "\n";
            foreach ($this->groupBys as $groupBy) {
                if (!$this->groupBy->partExists($groupBy)) {
                    //echo 'Adding group by: ', $groupBy, "\n";
                    $this->groupBy->add($groupBy);
                }
            }
        }
        return $this;
    }

    private function processOrderBy(): void
    {
        if (count($this->orderBys) > 0) {
            foreach ($this->orderBys as $order) {
                if (is_array($order) && !$this->orderBy->partExists(...$order)) {
                    $this->orderBy->add(...$order);
                }
            }
        }
    }

    /**
     * @throws ConfigException
     */
    public function __toString(): string
    {
        $this->process();
        $this->select->prettyPrint = $this->prettyPrint;
        $prettyChar = $this->prettyPrint ? "\n" : '';
        $modifiersStart = [];
        foreach ($this->modifiersStart as $modifier => $value) {
            if ((bool)$value === true) {
                //    echo 'Adding modifier start: ', $modifier, ' ', $value, "\n";
                $mod = Keywords::fromName($modifier)->value;
                if (!is_bool($value)) {
                    $mod .= ' ' . $value;
                }
                $modifiersStart[] = $mod;
            }
        }
        $modifiersEnd = [];
        foreach ($this->modifiersEnd as $modifier => $value) {
            if ((bool)$value === true) {
                //     echo 'Adding modifier end: ', $modifier, ' ', $value, "\n";
                $mod = Keywords::fromName($modifier)->value;
                if (!is_bool($value)) {
                    $mod .= ' ' . $value;
                }
                $modifiersEnd[] = $mod;
            }
        }
        /*if($this->from->isSubquery){
            $this->from->table->prettyPrint = $this->prettyPrint && $this->prettyPrintDeep;
        }*/
        //    echo 'MODIFIERS START: ' . implode(', ', $modifiersStart) . "\n";
        $stringParts = [Keywords::SELECT->value . $prettyChar, ...$modifiersStart, $this->select, $prettyChar . Keywords::FROM->value, $this->from];


        foreach ($this->joins as $join) {
            //$join->prettyPrint = $this->prettyPrint;
            $stringParts[] = $prettyChar . $join;
        }
        if ($this->where->count() > 0) {
            $this->where->prettyPrint = $this->prettyPrint;
            $stringParts[] = $prettyChar . Keywords::WHERE->value;
            $stringParts[] = $this->where;
            //echo 'WHERE: ', $this->where, PHP_EOL;
        }
        if ($this->groupBy->count() > 0) {
            $this->groupBy->prettyPrint = $this->prettyPrint;
            $stringParts[] = $prettyChar . Keywords::GROUP_BY->value;
            $stringParts[] = $this->groupBy;
            //echo 'GROUP BY: ', $this->groupBy, "\n";
        }
        if ($this->having->count() > 0) {
            $this->having->prettyPrint = $this->prettyPrint;
            $stringParts[] = $prettyChar . Keywords::HAVING->value;
            $stringParts[] = $this->having;
        }
        if ($this->orderBy->count() > 0) {
            $this->orderBy->prettyPrint = $this->prettyPrint;
            $stringParts[] = $prettyChar . Keywords::ORDER_BY->value;
            $stringParts[] = $this->orderBy;
        }
        $stringParts = array_merge($stringParts, $modifiersEnd);
        // echo 'STRING PARTS: ', var_export($stringParts, true), "\n";
        return implode(' ', $stringParts);//. ' ' . $this->groupBy . ' ' . $this->orderBy . ' ' . $this->having;;
    }

    public function getParam(string $placeholder): ?Param
    {
        return $this->params[$this->normalizePlaceholder($placeholder)] ?? null;
    }

    public function getMetadata(): ?MetadataDescriptor
    {
        return $this->statementParts->metadata ?? null;
    }
    public function overrideMetadata(MetadataDescriptor|array $metadata): void
    {
        $this->statementParts->set('metadata', $metadata);
    }
}