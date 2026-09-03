<?php

namespace Tabula17\Satelles\Omnia\Roga\Builder;

use BcMath\Number;
use DateTimeImmutable;
use Throwable;

enum DataTypes: string
{
    case BOOL = 'bool';
    case NULL = 'null';
    case INT = 'int';
    case STR = 'string';
    case DATE = 'date';
    case DATETIME = 'datetime';
    case NUMERIC = 'numeric';
    case EXPRESSION = 'expression';

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

    /**
     * Returns the primitive type of the data type
     *
     * @return string
     */
    public function primitiveType(): string
    {
        return match ($this) {
            self::BOOL => 'bool',
            self::NULL => 'null',
            self::INT => 'int',
            self::NUMERIC => 'float',
            default => 'string'
        };
    }

    public function isDate(): bool
    {
        return match ($this) {
            self::DATE, self::DATETIME => true,
            default => false,
        };
    }

    public function hydrator(mixed $value): mixed
    {
        $dateTimeHandler = static function (string $value) {
            try {
                return new DateTimeImmutable($value);
            } catch (Throwable $ignored) {
                return $value;
            }
        };
        $mathHandler = static function (string $value) {
            try {
                return new Number($value);
            } catch (Throwable $ignored) {
                return (float)$value;
            }
        };
        return match ($this) {
            self::BOOL => (bool)$value,
            self::NULL => null,
            self::INT => (int)$value,
            self::NUMERIC => $mathHandler($value),
            self::DATE, self::DATETIME => $dateTimeHandler($value),
            default => $value,
        };
    }

}
