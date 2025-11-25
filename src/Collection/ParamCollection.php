<?php

namespace Tabula17\Satelles\Omnia\Roga\Collection;

use Tabula17\Satelles\Utilis\Collection\GenericCollection;
use Tabula17\Satelles\Omnia\Roga\Descriptor\ParamDescriptor;

class ParamCollection extends GenericCollection
{

    public function __construct(ParamDescriptor ...$descriptor)
    {
        $this->values = $descriptor;
    }
}