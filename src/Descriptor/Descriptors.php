<?php

namespace Tabula17\Satelles\Omnia\Roga\Descriptor;

enum Descriptors: string
{
    case SELECT = SelectDescriptor::class;
    case INSERT = InsertDescriptor::class;
    case UPDATE = UpdateDescriptor::class;
    case UNION = UnionDescriptor::class;
    case EXEC = ExecuteDescriptor::class;
    case DELETE = DeleteDescriptor::class;
    public static function fromName(int|string $value): ?string
    {
        $value = strtolower($value);
        return match ($value) {
            default => self::SELECT->value,
            'insert' => self::INSERT->value,
            'union' => self::UNION->value,
            'exec', 'execute', 'call', 'func', 'function', 'sp', 'procedure' => self::EXEC->value,
            'update' => self::UPDATE->value,
            'delete' => self::DELETE->value,
        };
    }

}
