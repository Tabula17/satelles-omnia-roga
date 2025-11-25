<?php

namespace Tabula17\Satelles\Omnia\Roga\Collection;

use Tabula17\Satelles\Omnia\Roga\Descriptor\ColumnDescriptor;
use Tabula17\Satelles\Utilis\Collection\GenericCollection;

/**
 * Represents a collection of ColumnDescriptor objects.
 * Extends the GenericCollection class to provide functionality specific to handling column descriptors.
 */
class ColumnCollection extends GenericCollection
{
    public function __construct(ColumnDescriptor ...$columnDescriptor)
    {
        $this->values = $columnDescriptor;
    }
}