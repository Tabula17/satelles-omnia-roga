<?php

namespace Tabula17\Satelles\Omnia\Roga\Builder;

use Tabula17\Satelles\Omnia\Roga\Descriptor\ConditionDescriptor;
use Tabula17\Satelles\Omnia\Roga\Exception\ExceptionDefinitions;
use Tabula17\Satelles\Omnia\Roga\Exception\InvalidArgumentException;
use Tabula17\Satelles\Omnia\Roga\Exception\ValueRequiredException;

class Condition
{
    private(set) string $columnExpression;
    private(set) bool $needColumnExpression;
    private(set) ?int $combined;
    /**
     * @var mixed|string
     */
    private(set) string $sqlexpression;
    /**
     * @var mixed|null
     */
    /**
     * @var array|mixed
     */
    private mixed $arguments;
    /**
     * @var int|mixed
     */
    private(set) mixed $groupCondition;
    private(set) string $conditionId;
    private ?SelectStatement $subquery;

    /**
     */
    public function __construct(
        private readonly ConditionDescriptor $descriptor,
        private readonly Expression          $expression,
        public string                        $quote_identifier = '"',
        public string                        $pre_procedure_variable = "" // @ en SQLServer
    )
    {
        if (isset($descriptor['columnName'])) {
            //EN casos de JOIN puede venir como tableAlias + columnName
            $this->arguments = [];
            $joinedColumn = [];
            if (isset($descriptor['tableAlias'])) {
                $joinedColumn[] = $descriptor['tableAlias'];
            }
            $joinedColumn[] = $descriptor['columnName'];
            $this->arguments[] = implode('.', $joinedColumn);
        } else {
            $this->arguments = $descriptor['arguments'] ?? [];
        }
        $this->sqlexpression = $this->descriptor['sqlexpression'] ?? 'eq';
        $this->needColumnExpression = isset($this->descriptor['usecolexpression']) && (bool)$this->descriptor['usecolexpression'] === true;
        $this->combined = $this->descriptor['combined'] ?? null;
        $this->groupCondition = $this->descriptor['having'] ?? 0;
        //$this::class;
        $this->conditionId = uniqid(strtolower(basename(str_replace('\\', '/', $this::class))) . '::', false);
        if (isset($descriptor['subquery'])) {
            $this->subquery = new SelectStatement($descriptor['subquery']['descriptor'], $this->expression);
            $this->subquery->setValues($descriptor['subquery']['arguments'] ?? []);
        }

    }

    public function setColumnExpression(string $columnExpression): Condition
    {
        $this->columnExpression = $columnExpression;
        return $this;
    }

    public function getColumnExpression(): string
    {
        return $this->columnExpression;
    }

    /**
     * @throws ValueRequiredException
     * @throws InvalidArgumentException
     */
    public function __toString(): string
    {

        if (!isset($this->columnExpression)) {
            throw new ValueRequiredException(ExceptionDefinitions::CONDITION_WITHOUT_COLUMN_NAME->value);
        }
        $arguments = $this->arguments ?? [];
        array_unshift($arguments, $this->columnExpression);
        if (isset($this->subquery)) {
            $arguments[] = str_replace(':colname', $this->columnExpression, '(' . $this->subquery . ')');
        }
        if (empty($arguments)) {
            throw new InvalidArgumentException(ExceptionDefinitions::CONDITION_WITHOUT_ARGUMENTS->value);
        }
        return call_user_func_array(
            [$this->expression, $this->sqlexpression],
            $arguments
        );
    }

}