<?php

namespace Tabula17\Satelles\Omnia\Roga\Descriptor;

use Tabula17\Satelles\Utilis\Config\AbstractDescriptor;

class MetadataFilterDescriptor extends AbstractDescriptor
{
    /**
     * @var string $cfg Represents the configuration associated with the metadata.
     * Used to filter metadata based on the configuration.
     */
    protected(set) string $cfg;
    /**
     * @var string|null $variant Can be a string or null. Represents the variant(s) associated with the metadata.
     * Used to filter metadata based on the variant if necessary, e.g., for differentiating between live and replicate data, etc...
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
    /**
     * Sets the version value after validating its format. If the provided value is invalid,
     * a warning is triggered and the version is set to '0.0.0'.
     *
     * The input value is trimmed of a leading 'v' (if present) before validation.
     * The valid version is stored in the internal private backing property.
     *
     * @param string $value The version string to set. It can optionally start with 'v', which will be removed during processing.
     */
    protected(set) ?string $version = 'latest';

    /**
     * Checks if the current object is valid based on its properties and conditions.
     *
     * @return bool Returns true if the required properties are set and the identifier condition is met, otherwise false.
     */
    public function isValid(): bool
    {
        $identifier = ($this->allowed || $this->client);
        return isset($this->cfg, $this->variant, $identifier, $this->version);
    }
}