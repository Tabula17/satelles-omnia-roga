<?php

namespace Tabula17\Satelles\Omnia\Roga\Collection;

use Tabula17\Satelles\Omnia\Roga\Descriptor\StatementDescriptor;
use Tabula17\Satelles\Utilis\Collection\GenericCollection;

class StatementCollection extends GenericCollection
{

    public function __construct(StatementDescriptor ...$descriptor)
    {
        $this->values = $descriptor;
    }

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

    public function availableVariantsByMetadata(string $member): array
    {
        return array_unique(array_map(static function ($descriptor) use ($member) {
            return $descriptor->metadata->{$member};
        }, $this->values));
    }
}