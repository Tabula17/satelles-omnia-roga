<?php

namespace Tabula17\Satelles\Omnia\Roga\Collection;

use Tabula17\Satelles\Omnia\Roga\Descriptor\ModelDescriptor;
use Tabula17\Satelles\Utilis\Collection\TypedCollection;

class ModelCollection extends TypedCollection
{

    protected static function getType(): string
    {
        return ModelDescriptor::class;
    }
}