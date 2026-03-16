<?php

namespace Tabula17\Satelles\Omnia\Roga\Collection;

use Tabula17\Satelles\Omnia\Roga\Descriptor\MetadataDescriptor;
use Tabula17\Satelles\Omnia\Roga\Descriptor\StatementDescriptor;
use Tabula17\Satelles\Utilis\Collection\GenericCollection;

class StatementCollection extends GenericCollection
{
    /**
     * An array containing specific metadata variant keywords used for processing.
     * Unused or commented keywords are provided for reference or future use.
     */
    public static array $metadataVariantKeywords = [
        //'client',
        //'clients',
        //'variant',
        //'variants',
        'allowed'
    ];

    /**
     * @param StatementDescriptor ...$descriptor
     */
    public function __construct(StatementDescriptor ...$descriptor)
    {
        $this->values = $descriptor;
    }

    /**
     * Retrieves a statement descriptor from a collection based on the provided metadata key and value.
     *
     * @param string $member The metadata key used to filter descriptors.
     * @param mixed $value The metadata value to match against the specified key.
     * @return StatementDescriptor|null Returns the matching descriptor if found, or null otherwise.
     */
    public function getDescriptorByMetadata(string $member, mixed $value): ?StatementDescriptor
    {
        return array_find($this->values, static function ($descriptor) use ($member, $value) {
            $hasDescriptor = isset($descriptor?->metadata?->{$member});
            if (!$hasDescriptor) {
                return false;
            }

            $isValueArray = is_array($descriptor->metadata->{$member});
            $valIsInt = is_int($value);
            if ($isValueArray) {
                if (count($descriptor->metadata->{$member}) === 1 && $descriptor->metadata->{$member} === '*') {
                    return true;
                }
                return in_array($value, $descriptor->metadata->{$member});
            }
            return $descriptor->metadata->{$member} === '*' ||
                (
                    ($valIsInt ? (int)$descriptor->metadata->{$member} : $descriptor->metadata->{$member}) === $value
                ) || $descriptor->metadata->{$member} === $value;
        });
    }

    /**
     * Retrieves an array of unique metadata values associated with the given member from the descriptors.
     *
     * @param string $member The metadata key to look for in the descriptors.
     * @return array An array of unique metadata values for the specified member.
     */
    public function availableVariantsByMetadata(string $member): array
    {
        return $this->getMetadataMemberValues($member);
    }

    public function getMetadataMemberValues(string $member): array
    {
        return array_filter(array_unique(array_map(static function ($descriptor) use ($member) {
            if (is_string($member) && $descriptor->metadata->offsetExists($member)) {
                return $descriptor->metadata->{$member};
            }
            return null;
        }, $this->values)));
    }

    public function getDescriptorsByMetadata(string $member, mixed $value): ?self
    {
        $descriptors = array_filter($this->values, static function ($descriptor) use ($member, $value) {
            return $descriptor->metadata->{$member} === $value;
        });
        if (empty($descriptors)) {
            return null;
        }
        return new self(...$descriptors);
    }

    public function getDescriptorByVersion(string $version = 'latest'): ?StatementDescriptor
    {
        if ($version === 'latest') {
            if ($this->count() === 1) {
                return $this->first();
            }
            foreach ($this->getMetadataMemberValues('version') as $value) {
                if ($version === 'latest' || version_compare($value, $version) === 1) {
                    $version = $value;
                }
            }
        }

        return array_find($this->values, static function ($descriptor) use ($version) {
            return $descriptor->version === $version;
        });
    }
    public function getDescriptorsByVersion(string $version = 'latest'): ?self
    {
        if (!$this->hasVersions() || $this->count() === 1) {
            return $this;
        }
        return $this->getDescriptorsByMetadata('version', $version);
    }
    public function hasVersions(): bool
    {
        return count($this->getMetadataMemberValues('version')) > 1;
    }
    public function getVersions(): ?array
    {
        return $this->getMetadataMemberValues('version');
    }
    public function hasVariants(): bool
    {
        return count($this->getMetadataMemberValues(MetadataDescriptor::getVariantMember())) > 1;
    }
    public function getVariant(string $variant = 'default'): ?StatementDescriptor
    {
        if (!$this->hasVariants() || $this->count() === 1) {
            return $this->first();
        }
        return $this->getDescriptorByMetadata(MetadataDescriptor::getVariantMember(), $variant);
    }
    public function getVariants(): ?array
    {
        return $this->getMetadataMemberValues(MetadataDescriptor::getVariantMember());
    }
    public function getDescriptorsVariants(): ?self
    {
        return $this->getDescriptorsByMetadata(MetadataDescriptor::getVariantMember(), '*');
    }
}