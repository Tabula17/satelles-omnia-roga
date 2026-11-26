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
    protected(set) bool|string|null $collides = null {
        set(bool|int|string|null $value) {
            if ($value === null || $value === false || $value === '' || $value === 0 || $value === '0') {
                $this->collides = null;
                return;
            }
            if ($value === true || $value === 1 || $value === '1') {
                $this->collides = true;
                return;
            }
            $normalized = strtolower(trim((string)$value));
            if (in_array($normalized, ['true', 'yes', 'si', '*'], true)) {
                $this->collides = true;
                return;
            }
            if (in_array($normalized, ['false', 'no'], true)) {
                $this->collides = null;
                return;
            }
            $this->collides = trim((string)$value);
        }
    }
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
    protected(set) ?string $description;
}