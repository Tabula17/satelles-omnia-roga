<?php

namespace Tabula17\Satelles\Omnia\Roga\Descriptor;

use Tabula17\Satelles\Omnia\Roga\Collection\ParamCollection;

class UpdateDescriptor extends StatementDescriptor
{

    protected(set) TableDescriptor $to {
        set(array|TableDescriptor $value) {
            if (is_array($value)) {
                $this->to = new TableDescriptor($value);
            } elseif ($value instanceof TableDescriptor) {
                $this->to = $value;
            }
        }
    }
    protected(set) ?StatementDescriptor $select {
        set(array|StatementDescriptor|null $value) {
            if ($value instanceof SelectDescriptor) {
                $this->select = $value;
            } else if (is_array($value) && isset($value['subquery'])) {
                $this->select = new SelectDescriptor($value['subquery']['descriptor']);
            } else {
                $this->select = new SelectDescriptor($value);
            }
        }
    }

}