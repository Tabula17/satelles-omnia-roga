<?php

namespace Tabula17\Satelles\Omnia\Roga\Descriptor;

use Tabula17\Satelles\Omnia\Roga\Exception\ConfigException;
use Tabula17\Satelles\Omnia\Roga\Exception\ExceptionDefinitions;

/**
 * Maps the <type> value of a descriptor to its concrete descriptor class.
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
        $value = strtolower(trim((string)$value));
        return match ($value) {
            '', 'select' => self::SELECT,
            'insert' => self::INSERT,
            'union' => self::UNION,
            'exec', 'execute', 'call', 'func', 'function', 'sp', 'procedure' => self::EXEC,
            'update' => self::UPDATE,
            'delete' => self::DELETE,
            default => throw new ConfigException(sprintf(
                ExceptionDefinitions::STATEMENT_TYPE_NOT_SUPPORTED->value,
                $value
            )),
        };
    }
}
