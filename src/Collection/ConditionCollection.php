<?php

namespace Tabula17\Satelles\Omnia\Roga\Collection;

use Tabula17\Satelles\Omnia\Roga\Descriptor\ConditionDescriptor;
use Tabula17\Satelles\Omnia\Roga\Descriptor\ParamDescriptor;
use Tabula17\Satelles\Utilis\Collection\GenericCollection;

class ConditionCollection extends GenericCollection
{
    public function __construct(ParamDescriptor|ConditionDescriptor ...$descriptor)
    {
        $this->values = $descriptor;
    }
}