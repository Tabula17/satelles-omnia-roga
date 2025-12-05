<?php

namespace Tabula17\Satelles\Omnia\Roga\Descriptor;

use Tabula17\Satelles\Omnia\Roga\Collection\ConditionCollection;
use Tabula17\Satelles\Omnia\Roga\Collection\JoinConditionCollection;
use Tabula17\Satelles\Omnia\Roga\Collection\ParamCollection;
use Tabula17\Satelles\Utilis\Config\AbstractDescriptor;

/**
 * Represents a column descriptor in a database query or data structure.
 *
 * This class is used to define and handle the properties and behaviors of a database column,
 * including its name, type, alias, visibility, and specific behaviors such as handling
 * subqueries, parameters, conditions, and order definitions.
 *
 * Properties are protected and can only be modified within the class and via specific set methods.
 * Several properties support accepting different input types such as arrays, which are then
 * processed and instantiated as specific objects.
 */
final class ColumnDescriptor extends AbstractDescriptor
{
    protected(set) string $name;
    protected(set) ?string $type;
    protected(set) ?string $alias;
    protected(set) ?int $visible;
    protected(set) ?string $sqlexpression;
    protected(set) ?array $arguments;
    protected(set) ?int $excludecolname;
    protected(set) ?string $template;
    protected(set) SubqueryDescriptor $subquery {
        set(array|SubqueryDescriptor $value) {
            if (is_array($value)) {
                $this->subquery = new SubqueryDescriptor($value);
            } elseif ($value instanceof SubqueryDescriptor) {
                $this->subquery = $value;
            }
        }
    }
    protected(set) ?int $literal;
    protected(set) ?bool $quoteliteral;
    protected(set) ParamCollection $params {
        set(array|ParamCollection $value) {
            if (is_array($value)) {
                $params = [];
                foreach ($value as $param) {
                    if ($param instanceof ParamDescriptor) {
                        $params[] = $param;
                    } else {
                        $params[] = new ParamDescriptor($param);
                    }
                }
                $this->params = new ParamCollection(...$params);
            } elseif ($value instanceof ParamCollection) {
                $this->params = $value;
            }
        }
    }
    protected(set) ConditionCollection $conditions {
        set(array|ConditionCollection $value) {
            if (is_array($value)) {
                $params = [];
                foreach ($value as $param) {
                    if ($param instanceof ConditionDescriptor) {
                        $params[] = $param;
                    } else {
                        try {
                            $params[] = isset($param['name']) ? new ParamDescriptor($param) : new ConditionDescriptor($param);
                        }catch (\Throwable $exception){
                            echo $exception->getMessage(), PHP_EOL;
                            //echo var_export($param, true), PHP_EOL;
                        }
                    }
                }
                $this->conditions = new ConditionCollection(...$params);
            } elseif ($value instanceof ConditionCollection) {
                $this->conditions = $value;
            }
        }
    }
    protected(set) int $grouped;
    protected(set) JoinConditionCollection $joinConditions {
        set(array|JoinConditionCollection|null $value) {
            if (is_array($value)) {
                $params = [];
                foreach ($value as $param) {
                    if ($param instanceof ConditionDescriptor) {
                        $params[] = $param;
                    } else {
                        $params[] = isset($param['name']) ? new ParamDescriptor($param) : new ConditionDescriptor($param);
                    }
                }
                $this->joinConditions = new JoinConditionCollection(...$params);
            } elseif ($value instanceof JoinConditionCollection) {
                $this->joinConditions = $value;
            }
        }
    }
    protected(set) OrderDescriptor $order {
        set(array|OrderDescriptor $value) {
            if (is_array($value)) {
                $this->order = new OrderDescriptor($value);
            } elseif ($value instanceof OrderDescriptor) {
                $this->order = $value;
            }
        }
    }
    protected(set) string $tableAlias;

}