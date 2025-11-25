<?php

namespace Tabula17\Satelles\Omnia\Roga\Builder;

enum DataTypes
{
    case BOOL;
    case NULL;
    case INT;
    case STR;
    case DATE;
    case DATETIME;
    case NUMERIC;
    case EXPRESSION;

    public static function fromName(string $value): DataTypes
    {
        return match ($value) {
            'bool', 'boolean' => self::BOOL,
            'null' => self::NULL,
            'int', 'integer' => self::INT,
            'date', 'datestring' => self::DATE,
            'datetime', 'time', 'timestring', 'datetimestring' => self::DATETIME,
            'float', 'number', 'numeric' => self::NUMERIC,
            'columnname', 'expression' => self::EXPRESSION,
            default => self::STR,
        };
    }
    /*
     *
            'bool', 'boolean' => self::BOOL->value,
            'null' => self::NULL->value,
            'int', 'integer' => self::INT->value,
            'str', 'string', 'date', 'datestring', 'datetime', 'time', 'timestring', 'datetimestring', 'float', 'number', 'numeric' => self::STR->value,
     */
}
