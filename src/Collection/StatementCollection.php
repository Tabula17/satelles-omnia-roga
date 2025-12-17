<?php

namespace Tabula17\Satelles\Omnia\Roga\Collection;

use Tabula17\Satelles\Omnia\Roga\Descriptor\StatementDescriptor;
use Tabula17\Satelles\Utilis\Collection\GenericCollection;

class StatementCollection extends GenericCollection
{
    /**
     * An array containing specific metadata variant keywords used for processing.
     * Unused or commented keywords are provided for reference or future use.
     */
    public static array $metadataVariantKeywords = [
        'client',
        //'clients',
        'variant',
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
                    ($valIsInt ? (int)$descriptor->metadata->{$member} :
                        $descriptor->metadata->{$member}) === $value
                );
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
        return array_filter(array_unique(array_map(static function ($descriptor) use ($member) {//todo:review when setting $member, sometimes goes as array
            if(is_string($member) && $descriptor->metadata->offsetExists($member)) {
                return $descriptor->metadata->{$member};
            }
            return null;
        }, $this->values)));
    }
}