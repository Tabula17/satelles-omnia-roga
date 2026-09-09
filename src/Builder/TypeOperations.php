<?php

namespace Tabula17\Satelles\Omnia\Roga\Builder;

use JsonSerializable;
use Tabula17\Satelles\Utilis\Interface\EnumMethodsInterface;

enum TypeOperations implements JsonSerializable, EnumMethodsInterface
{
    case SELECT;
    case INSERT;
    case UPDATE;
    case DELETE;
    case EXEC;
    case SYNC;

    public static function tryFrom(string $value): TypeOperations
    {
        return match (strtoupper($value)) {
            'SELECT', 'GET' => self::SELECT,
            'INSERT', 'POST' => self::INSERT,
            'UPDATE' => self::UPDATE,
            'DELETE' => self::DELETE,
            'EXEC', 'EXECUTE', 'CALL' => self::EXEC,
            'SYNC' => self::SYNC,
            default => throw new \InvalidArgumentException("Invalid TypeOperations name: $value"),
        };
    }

    public function value(): string
    {
        return match ($this) {
            self::SELECT => 'select',
            self::INSERT => 'insert',
            self::UPDATE => 'update',
            self::DELETE => 'delete',
            self::EXEC => 'exec',
            self::SYNC => 'sync',
        };
    }

    public function canHaveResultset(): bool
    {
        return match ($this) {
            self::SELECT => true,
            default => false,
        };
    }

    public function canModifyData(): bool
    {
        return match ($this) {
            self::INSERT, self::UPDATE, self::DELETE, self::SYNC => true,
            default => false,
        };
    }

    public function canExecute(): bool
    {
        return match ($this) {
            self::EXEC, self::SYNC => true,
            default => false,
        };
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): string
    {
        return $this->value();
    }

    public static function fromValue(mixed $value): ?self
    {
        return self::tryFrom((string)$value);
    }

    public static function isValid(mixed $value): bool
    {
        try {
            self::fromValue($value);
            return true;
        } catch (\InvalidArgumentException $e) {
            return false;
        }
    }
}
