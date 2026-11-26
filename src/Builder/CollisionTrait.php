<?php

namespace Tabula17\Satelles\Omnia\Roga\Builder;

use Tabula17\Satelles\Omnia\Roga\Exception\ConfigException;
use Tabula17\Satelles\Omnia\Roga\Exception\ExceptionDefinitions;

/**
 * Validates mutually exclusive filters declared with <collides>.
 *
 * A boolean collision group applies to one column. A named collision group
 * can be shared by several columns.
 */
trait CollisionTrait
{
    /** @return array{0: string, 1: string}|null Group key and human-readable label. */
    private function collisionGroup(bool|string|null $collides, string $scope, string $columnExpression): ?array
    {
        if ($collides === null || $collides === false || $collides === '') {
            return null;
        }
        if ($collides === true) {
            $label = implode('.', array_map(
                static fn(string $part): string => trim($part, "\"'`[]"),
                explode('.', $columnExpression)
            ));
            return [$scope . '|column|' . $columnExpression, $label];
        }
        return [$scope . '|group|' . $collides, $collides];
    }

    /**
     * @param array<string, string> $seen
     * @param array{0: string, 1: string}|null $collision
     */
    private function assertNoCollision(array &$seen, ?array $collision, string $rendered, string $context): void
    {
        if ($collision === null || $rendered === '') {
            return;
        }
        [$key, $label] = $collision;
        if (isset($seen[$key]) && $seen[$key] !== $rendered) {
            throw new ConfigException(sprintf(
                ExceptionDefinitions::COLLIDING_FILTERS->value,
                $context,
                $label,
                $seen[$key],
                $rendered
            ));
        }
        $seen[$key] = $rendered;
    }
}
