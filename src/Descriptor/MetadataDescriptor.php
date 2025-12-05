<?php

namespace Tabula17\Satelles\Omnia\Roga\Descriptor;

use Tabula17\Satelles\Utilis\Config\AbstractDescriptor;

/**
 * Represents a descriptor for metadata operations, providing functionality
 * for configuring and managing supported operations, connection properties,
 * and other related metadata attributes.
 */
class MetadataDescriptor extends AbstractDescriptor
{
    private array $availableOperations = ['select', 'insert', 'update', 'delete', 'execute', 'exec', 'call', 'sync'];
    protected(set) string $connection;
    protected(set) string $operation {
        set(string $value) {
            $check = explode(',', $value);
            foreach ($check as $k => $item) {
                if (!in_array($item, $this->availableOperations)) {
                    unset($check[$k]);
                }
            }
            $value = implode(',', $check);

            $this->operation = strtolower($value);
        }
    }
    protected(set) array|string $variant = [];
    protected(set) array|string $allowed = [];
    protected(set) array|string $client = [];
    protected(set) bool $quoteIdentifier = false;
    protected(set) string $version;

    public function getAvailableOperations(): array
    {
        return $this->availableOperations;
    }
}