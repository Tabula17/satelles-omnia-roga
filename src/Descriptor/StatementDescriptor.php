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

    /**
     * Determines if the current instance can have a result set based on its metadata or type properties.
     *
     * @return bool Returns true if the operation or type contains 'select', indicating the potential for a result set; otherwise, false.
     */
    public function canHaveResultset(): bool
    {
        if (isset($this->metadata, $this->metadata->operation)) {
            return str_contains($this->metadata->operation, 'select');
        }
        return isset($this->type) && str_contains($this->type, 'select');
    }
    public function isInsert(): bool
    {
        return isset($this->metadata) && $this->metadata->operation === 'insert';
    }
    public function getOperations(): array
    {
        return $this->metadata?->getAvailableOperations();
    }

}