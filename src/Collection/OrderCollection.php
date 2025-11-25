<?php

namespace Tabula17\Satelles\Omnia\Roga\Collection;

use Tabula17\Satelles\Omnia\Roga\Descriptor\OrderDescriptor;
use Tabula17\Satelles\Utilis\Collection\GenericCollection;

class OrderCollection extends GenericCollection
{
    public function __construct(OrderDescriptor ...$descriptor)
    {
        $this->values = $descriptor;
    }
}