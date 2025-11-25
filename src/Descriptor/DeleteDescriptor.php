<?php

namespace Tabula17\Satelles\Omnia\Roga\Descriptor;
use Tabula17\Satelles\Utilis\Config\AbstractDescriptor;

class DeleteDescriptor extends StatementDescriptor
{
    protected(set) TableDescriptor $from {
        set(array|TableDescriptor $value) {
            if (is_array($value)) {
                $this->from = new TableDescriptor($value);
            } elseif ($value instanceof TableDescriptor) {
                $this->from = $value;
            }
        }
    }
}