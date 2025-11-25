<?php

namespace Tabula17\Satelles\Omnia\Roga\Descriptor;

use Tabula17\Satelles\Utilis\Config\AbstractDescriptor;
class SubqueryDescriptor extends AbstractDescriptor
{
    protected(set) SelectDescriptor $descriptor {
        set(array|AbstractDescriptor $value) {
            if (is_array($value)) {
                $this->descriptor = new SelectDescriptor($value);
            } elseif ($value instanceof SelectDescriptor) {
                $this->descriptor = $value;
            }
        }
    }
    protected(set) array $arguments;
}