<?php

namespace Tabula17\Satelles\Omnia\Roga\Builder;

use PDO;

/**
 * PDO::PARAM_BOOL (int)
 * Represents a boolean data type.
 * PDO::PARAM_NULL (int)
 * Represents the SQL NULL data type.
 * PDO::PARAM_INT (int)
 * Represents the SQL INTEGER data type.
 * PDO::PARAM_STR (int)
 * Represents the SQL CHAR, VARCHAR, or other string data type.
 * PDO::PARAM_STR_NATL (int)
 * Flag to denote a string uses the national character set. Available since PHP 7.2.0
 * PDO::PARAM_STR_CHAR (int)
 * Flag to denote a string uses the regular character set. Available since PHP 7.2.0
 * PDO::PARAM_LOB (int)
 * Represents the SQL large object data type.
 * PDO::PARAM_STMT (int)
 * Represents a recordset type. Not currently supported by any drivers.
 * PDO::PARAM_INPUT_OUTPUT (int)
 * Specifies that the parameter is an INOUT parameter for a stored procedure. You must bitwise-OR this value with an explicit PDO::PARAM_* data type.
 */
enum ParamTypes: int
{
    case BOOL = PDO::PARAM_BOOL;
    case NULL = PDO::PARAM_NULL;
    case INT = PDO::PARAM_INT;
    case STR = PDO::PARAM_STR;
    case STR_NATL = PDO::PARAM_STR_NATL;
    case STR_CHAR = PDO::PARAM_STR_CHAR;
    case LOB = PDO::PARAM_LOB;
    case STMT = PDO::PARAM_STMT;

    //  case INPUT_OUTPUT = PDO::PARAM_INPUT_OUTPUT;

    public static function fromName(int|string $value): int
    {
        $value = strtolower($value);
        return match ($value) {
            'bool', 'boolean' => self::BOOL->value,
            'null' => self::NULL->value,
            'int', 'integer' => self::INT->value,
            'str', 'string', 'date', 'datestring', 'datetime', 'time', 'timestring', 'datetimestring', 'float', 'number', 'numeric' => self::STR->value,
            'str_char' => self::STR_CHAR->value,
            'str_natl' => self::STR_NATL->value,
            'lob' => self::LOB->value,
            'stmt' => self::STMT->value,
            //   'input_output' => self::INPUT_OUTPUT
        };
    }
}
