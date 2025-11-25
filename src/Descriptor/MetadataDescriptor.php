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
    protected(set) string $connection;
    protected(set) string $operation;
    protected(set) array|string $variant = [];
    protected(set) array|string $allowed = [];
    protected(set) array|string $client = [];
    protected(set) bool $quoteIdentifier = false;
    protected(set) string $version;


}