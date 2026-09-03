<?php

namespace Tabula17\Satelles\Omnia\Roga\Descriptor;

use Tabula17\Satelles\Utilis\Config\AbstractDescriptor;

class StatementDescriptor extends AbstractDescriptor
{
    protected(set) DescriptorClasses $type = DescriptorClasses::SELECT
        {
            set(string|DescriptorClasses $value) => is_string($value) ? DescriptorClasses::fromValue($value) : $value;
        }
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