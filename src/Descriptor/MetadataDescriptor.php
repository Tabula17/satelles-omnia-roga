<?php

namespace Tabula17\Satelles\Omnia\Roga\Descriptor;

use Tabula17\Satelles\Utilis\Config\AbstractDescriptor;

/**
 * 'metadata' => [
 * 'connection' => 'sql1',
 * 'operation' => 'select',
 * 'forClients' => [], // ex allowed plants
 * 'quoteIdentifier' => false,
 * 'version' => '1.0'
 * ],
 * 'client',
 * 'clients',
 * 'variant',
 * 'variants',
 * 'allowed',
 */
class MetadataDescriptor extends AbstractDescriptor
{
    private(set) array $availableOperations = ['select', 'insert', 'update', 'delete', 'execute', 'exec', 'call', 'sync'];
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


}