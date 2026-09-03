<?php

namespace Tabula17\Satelles\Omnia\Roga\Descriptor;

/**
 * Enum DescriptorClasses represents a set of descriptors mapped to their corresponding class names.
 * Each case in the enum corresponds to a specific descriptor.
 *
 * This enum provides a static method to retrieve descriptor values based on given names or values.
 *
 * The following cases are available:
 * - SELECT
 * - INSERT
 * - UPDATE
 * - UNION
 * - EXEC
 * - DELETE
 *
 * Method:
 * - fromName(): Converts a given name or value to its corresponding descriptor value, or provides a default mapping.
 */
enum DescriptorClasses: string
{
    case SELECT = SelectDescriptor::class;
    case INSERT = InsertDescriptor::class;
    case UPDATE = UpdateDescriptor::class;
    case UNION = UnionDescriptor::class;
    case EXEC = ExecuteDescriptor::class;
    case DELETE = DeleteDescriptor::class;
    public static function fromValue(int|string $value): ?self
    {
        return match ($value) {
            default => self::SELECT,
            'insert' => self::INSERT,
            'union' => self::UNION,
            'exec', 'execute', 'call', 'func', 'function', 'sp', 'procedure' => self::EXEC,
            'update' => self::UPDATE,
            'delete' => self::DELETE,
        };
    }

}
