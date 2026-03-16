<?php

namespace Tabula17\Satelles\Omnia\Roga\Descriptor;

use Tabula17\Satelles\Omnia\Roga\Exception\InvalidArgumentException;
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
    protected(set) array|string|null $variant = null;
    protected(set) array|string|null $allowed = null;
    protected(set) array|string|null $client = null;
    protected(set) bool $quoteIdentifier = false;
    protected(set) ?string $version {
        set {
            $cleanValue = ltrim($value, 'v');

            if (!$this->isValidVersion($cleanValue)) {
                $cleanValue = '0.0.0';
                //throw new InvalidArgumentException("Invalid version format: $value");
                trigger_error("Invalid version format: $value", E_USER_WARNING);
            }

            // Store the clean, validated value in the private backing property
            $this->version = $cleanValue;
        }
    }

    private function isValidVersion(string $version): bool
    {
        $pattern = '/^\d+(?:\.\d+)*(?:(?:[_-]?(?:dev|alpha|a|beta|b|RC|rc|pl|p)\d*(?:\.\d+)*)|#\d*)*(?:\+\d+)?$/';
        if (preg_match($pattern, $version)) {
            return true;
        }

        return false;
    }

    public function getAvailableOperations(): array
    {
        return $this->availableOperations;
    }
}