<?php

namespace Tabula17\Satelles\Omnia\Roga\Collection;

use Tabula17\Satelles\Utilis\Collection\GenericCollection;
use Tabula17\Satelles\Omnia\Roga\Descriptor\JoinDescriptor;

class JoinCollection extends GenericCollection
{
    public function __construct(JoinDescriptor ...$descriptor)
    {
        $this->values = $descriptor;
    }
}