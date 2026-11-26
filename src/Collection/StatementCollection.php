<?php

namespace Tabula17\Satelles\Omnia\Roga\Collection;

use Tabula17\Satelles\Omnia\Roga\Descriptor\MetadataDescriptor;
use Tabula17\Satelles\Omnia\Roga\Descriptor\StatementDescriptor;
use Tabula17\Satelles\Utilis\Collection\GenericCollection;

class StatementCollection extends GenericCollection
{
    /**
     * Keywords historically used to identify variants. Kept for backwards compatibility;
     * the current source of truth is MetadataDescriptor::getIdentifiedBy().
     */
    public static array $metadataVariantKeywords = [
        'allowed'
    ];

    public function __construct(StatementDescriptor ...$descriptor)
    {
        $this->values = $descriptor;
    }

    /**
     * Normalizes a metadata member (scalar or list) into a list of strings.
     */
    private static function metadataValues(mixed $value): array
    {
        $items = is_array($value) ? $value : [$value];
        $values = [];
        foreach ($items as $item) {
            if (is_string($item) || is_int($item) || is_float($item)) {
                $values[] = (string)$item;
            }
        }
        return array_values(array_unique($values));
    }

    /**
     * Exact matches take precedence over the '*' wildcard.
     */
    private function findBy(string $member, mixed $value): array
    {
        if ($value === null) {
            return [];
        }
        $needle = (string)$value;
        $exact = [];
        $wildcard = [];
        foreach ($this->values as $descriptor) {
            if (!isset($descriptor->metadata) || !isset($descriptor->metadata->{$member})) {
                continue;
            }
            $values = self::metadataValues($descriptor->metadata->{$member});
            if ($values === []) {
                continue;
            }
            if (in_array($needle, $values, true)) {
                $exact[] = $descriptor;
            } elseif (in_array('*', $values, true)) {
                $wildcard[] = $descriptor;
            }
        }
        return $exact !== [] ? $exact : $wildcard;
    }

    private function collectionFrom(array $descriptors): ?self
    {
        return $descriptors === [] ? null : new self(...$descriptors);
    }

    public function getDescriptorByMetadata(string $member, mixed $value): ?StatementDescriptor
    {
        return $this->findBy($member, $value)[0] ?? null;
    }

    public function availableVariantsByMetadata(string $member): array
    {
        return $this->getMetadataMemberValues($member);
    }

    public function getMetadataMemberValues(string $member): array
    {
        $values = [];
        foreach ($this->values as $descriptor) {
            if (!isset($descriptor->metadata) || !isset($descriptor->metadata->{$member})) {
                continue;
            }
            foreach (self::metadataValues($descriptor->metadata->{$member}) as $value) {
                $values[$value] = $value;
            }
        }
        return array_values($values);
    }

    public function getDescriptorsByMetadata(string $member, mixed $value): ?self
    {
        return $this->collectionFrom($this->findBy($member, $value));
    }

    private function resolveVersion(string $version): ?string
    {
        if ($version !== 'latest') {
            return $version;
        }
        $best = null;
        foreach ($this->getMetadataMemberValues('version') as $candidate) {
            if ($best === null || version_compare($candidate, $best) === 1) {
                $best = $candidate;
            }
        }
        return $best;
    }

    public function getDescriptorByVersion(string $version = 'latest'): ?StatementDescriptor
    {
        return $this->getDescriptorsByVersion($version)?->first();
    }

    public function getDescriptorsByVersion(string $version = 'latest'): ?self
    {
        $versions = $this->getMetadataMemberValues('version');
        if ($versions === []) {
            // Definitions without version metadata are treated as a single unversioned set.
            return $version === 'latest' ? $this : null;
        }
        return $this->getDescriptorsByMetadata('version', $this->resolveVersion($version));
    }

    public function hasVersions(): bool
    {
        return count($this->getMetadataMemberValues('version')) > 1;
    }

    public function getVersions(): array
    {
        return $this->getMetadataMemberValues('version');
    }

    public function hasVariants(): bool
    {
        return count($this->getMetadataMemberValues(MetadataDescriptor::getVariantMember())) > 1;
    }

    public function getVariant(string $variant = 'default'): ?StatementDescriptor
    {
        if ($this->getMetadataMemberValues(MetadataDescriptor::getVariantMember()) === []) {
            return $this->first();
        }
        return $this->getDescriptorByMetadata(MetadataDescriptor::getVariantMember(), $variant);
    }

    public function getVariants(): array
    {
        return $this->getMetadataMemberValues(MetadataDescriptor::getVariantMember());
    }

    public function getDescriptorsVariants(): ?self
    {
        $descriptors = [];
        foreach ($this->values as $descriptor) {
            $member = MetadataDescriptor::getVariantMember();
            if (isset($descriptor->metadata->{$member}) &&
                in_array('*', self::metadataValues($descriptor->metadata->{$member}), true)) {
                $descriptors[] = $descriptor;
            }
        }
        return $this->collectionFrom($descriptors);
    }
}
