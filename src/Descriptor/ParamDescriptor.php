<?php

namespace Tabula17\Satelles\Omnia\Roga\Descriptor;
/**
 * Represents a descriptor for a parameter, extending the functionality provided by ConditionDescriptor.
 *
 * This class provides properties to define and configure parameters, including their name, type,
 * binding behavior, and additional settings for handling empty or non-empty values. It also includes
 * options for template-based parameterization and subqueriesForPath handling.
 *
 * Properties in this class can be set with constraints or type conversions to ensure consistent usage.
 */
final class ParamDescriptor extends ConditionDescriptor
{
    protected(set) string $name;
    protected(set) string $type;
    protected(set) bool $onempty = false {
        set(bool|int $value) {
            $this->onempty = (bool)$value;
            if (isset($this->onnotempty) && $this->onnotempty === $this->onempty) {
                $this->onnotempty = !$this->onempty;
            }
        }
    }
    protected(set) bool $onnotempty = false {
        set(bool|int $value) {
            $this->onnotempty = (bool)$value;
            if (isset($this->onempty) && $this->onempty === $this->onnotempty) {
                $this->onempty = !$this->onnotempty;
            }
        }
    }
    protected(set) bool $writeFilter = false {
        set(bool|int $value) {
            $this->writeFilter = (bool)$value;
        }
    }
    protected(set) ?string $paramName;
    protected(set) ?string $paramTemplate;
    protected(set) ?bool $nullonempty {
        set(bool|int|null $value) {
            $this->nullonempty = (bool)$value;
        }
    }
    protected(set) bool $bindable = true {
        set(bool|int $value) {
            $this->bindable = (bool)$value;
        }
    }
    protected(set) bool $subqueryAsArgument = false {
        set(bool|int $value) {
            $this->subqueryAsArgument = (bool)$value;
        }
    }
}