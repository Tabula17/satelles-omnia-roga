<?php

namespace Tabula17\Satelles\Omnia\Roga\Descriptor;

use Tabula17\Satelles\Omnia\Roga\Builder\QuotesEnum;
use Tabula17\Satelles\Omnia\Roga\Builder\TypeOperations;
use Tabula17\Satelles\Omnia\Roga\Collection\TypeOperationsCollection;
use Tabula17\Satelles\Utilis\Config\AbstractDescriptor;

/**
 * Represents a descriptor for metadata operations, providing functionality
 * for configuring and managing supported operations, connection properties,
 * and other related metadata attributes.
 */
class MetadataDescriptor extends AbstractDescriptor
{
    private array $availableOperations = ['select', 'insert', 'update', 'delete', 'execute', 'exec', 'call', 'sync'];
    private static array $identifiedBy = ['allowed', 'client'];
    private static string $variantMember = 'variant';

    protected(set) string $connection;
    /**
     * @var string $operation Can be a string or an array of strings. Represents the operation(s) associated with the metadata.
     * The setter validates the provided value against a predefined list of available operations and normalizes it to lowercase.
     * If any invalid operations are included, they are removed from the final value.
     */
    protected(set) TypeOperationsCollection $operation {
        set(string|array|TypeOperations|TypeOperationsCollection $value) {

            if($value instanceof TypeOperationsCollection){
                $this->operation = $value;
                return;
            }

            $this->operation = new TypeOperationsCollection();
            if (is_string($value)) {
                $value = array_map('trim', explode(',', $value));
            }
            if($value instanceof TypeOperations){
                $value = [$value];
            }
            $this->operation->import($value);
        }
    }
    /**
     * @var string|null $variant Can be a string or null. Represents the variant(s) associated with the metadata.
     * Used to filter metadata based on the variant if necessary, e.g., for differentiating between sync and async operations
                }
            }
            $value = implode(',', $check);

            $this->operation = strtolower($value);
        }
    }
    /**
     * @var string|null $variant Can be a string or null. Represents the variant(s) associated with the metadata.
     * Used to filter metadata based on the variant if necessary, e.g., for differentiating between sync and async operations
     */
    protected(set) string|null $variant = 'default';
    /**
     * @var array|string|null $allowed Can be a string, an array of strings, or null. Represents the allowed identifier associated with the metadata.
     * Used to filter metadata based on the allowed identifier.
     */
    protected(set) array|string|null $allowed = null;
    /**
     * @var array|string|null $client Can be a string, an array of strings, or null. Represents the client(s) associated with the metadata.
     * Used to filter metadata based on the client.
     */
    protected(set) array|string|null $client = null;
    protected(set) bool $quoteIdentifier = false;
    public QuotesEnum $quoteType = QuotesEnum::NONE
        {
            set(QuotesEnum|string $value) {
                if(is_string($value)){
                    $value = QuotesEnum::for($value);
                }
                $this->quoteType = $value;
            }
        }
    /**
     * Sets the version value after validating its format. If the provided value is invalid,
     * a warning is triggered and the version is set to '0.0.0'.
     *
     * The input value is trimmed of a leading 'v' (if present) before validation.
     * The valid version is stored in the internal private backing property.
     *
     * @param string $value The version string to set. It can optionally start with 'v', which will be removed during processing.
     */
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
    public static function getIdentifiedBy(): array
    {
        return self::$identifiedBy;
    }
    public static function getVariantMember(): string
    {
        return self::$variantMember;
    }
    public function identifyBy(): array
    {
        $output = [];
        foreach (self::$identifiedBy as $item) {
            if(isset($this->$item)) {
                $output[$item] = $this->$item;
            }
        }
        return $output;
    }
}