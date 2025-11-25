<?php

namespace Tabula17\Satelles\Omnia\Roga\Descriptor;
use Tabula17\Satelles\Utilis\Config\AbstractDescriptor;

class StatementDescriptor extends AbstractDescriptor
{
    protected(set) string $type;
    protected(set) MetaDataDescriptor $metadata {
        set(array|MetaDataDescriptor $value) {
            if (is_array($value)) {
                $this->metadata = new MetaDataDescriptor($value);
            } elseif ($value instanceof MetaDataDescriptor) {
                $this->metadata = $value;
            }
        }
    }

}