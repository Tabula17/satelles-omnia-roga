<?php

namespace Tabula17\Satelles\Omnia\Roga\Builder;

use Tabula17\Satelles\Utilis\Data\DataTypes;

enum QuotesEnum: string
{
    case DOUBLE_QUOTE = '"';
    case BACKTICK = '`';
    case SINGLE_QUOTE = "'";
    case SQUARE_BRACKET = '[]';
    case BRACKETS = '()';
    case BRACES = '{}';
    case NONE = '';

    public function start(): string
    {
        return $this->value[0];
    }

    public function end(): string
    {
        return $this->value[1] ?? $this->value[0];
    }

    /**
     * Escapes a given string by replacing occurrences of a specific value with its duplicated version.
     *
     * @param string $value The input string to be escaped.
     * @return string The escaped string with replaced values.
     */
    public function escape(string $value): string
    {
        if ($this === self::NONE) {
            return $value;
        }
        return str_replace($this->value, $this->value . $this->value, $value);
    }

    public function unescape(string $value): string
    {
        if ($this === self::NONE) {
            return $value;
        }
        return str_replace($this->value . $this->value, $this->value, $value);
    }

    public function forIdentifiers(): bool
    {
        return $this !== self::SINGLE_QUOTE;
    }

    public function forValues(): bool
    {
        return $this === self::SINGLE_QUOTE;
    }

    public static function fromString(string $value): self
    {
        return self::tryFrom($value) ?? self::NONE;
    }

    public static function fromDataTypes(DataTypes $type): self
    {
        return match ($type) {
            DataTypes::STR, DataTypes::DATE, DataTypes::DATETIME => self::SINGLE_QUOTE,
            default => self::NONE,
        };
    }

    public static function for(string $value): self
    {
        return match ($value) {
            'ansi', 'double', 'double_quote', 'oracle', 'postgresql', 'postgre', 'sqlite', 'identifier', 'column', 'table' => self::DOUBLE_QUOTE,
            'backtick', 'mysql', 'mariadb' => self::BACKTICK,
            'single_quote', 'single', 'value', 'data' => self::SINGLE_QUOTE,
            'square_bracket', 'square', 'sqlsrv', 'mssql' => self::SQUARE_BRACKET,
            'bracket', 'brackets', 'parenthesis', 'parens' => self::BRACKETS,
            'brace', 'braces', 'curly', 'curly_brace', 'arguments' => self::BRACES,
            default => self::NONE,
        };
    }

    public function quote(string $value): string
    {
        if ($this === self::NONE) {
            return $value;
        }
        return $this->start() . $this->escape($value) . $this->end();
    }

    public function unquote(string $value): string
    {
        if ($this === self::NONE) {
            return $value;
        }
        return $this->unescape(substr($value, 1, -1));
    }

    public function isQuoted(string $value): bool
    {
        if ($this === self::NONE) {
            return true;
        }
        return str_starts_with($value, $this->start()) && str_ends_with($value, $this->end());
    }
}
