<?php

namespace Tabula17\Satelles\Omnia\Roga\Descriptor;
use Tabula17\Satelles\Utilis\Config\AbstractDescriptor;

class ConditionDescriptor extends AbstractDescriptor
{
    protected(set) mixed $defaultValue;
    protected(set) ?string $sqlexpression;
    protected(set) bool $required = false;
    protected(set) bool $nullable = false;
    protected(set) string $format;
    protected(set) ?array $arguments;
    protected(set) bool $usecolexpression = false;
    protected(set) ?bool $having;
    protected(set) ?int $combined;
    protected(set) ?string $tableAlias;
    protected(set) ?string $columnName;
    protected(set) SubqueryDescriptor $subquery {
        set(array|SubqueryDescriptor $value) {
            if (is_array($value)) {
                $this->subquery = new SubqueryDescriptor($value);
            } elseif ($value instanceof SubqueryDescriptor) {
                $this->subquery = $value;
            }
        }
    }
}