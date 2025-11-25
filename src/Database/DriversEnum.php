<?php

namespace Tabula17\Satelles\Omnia\Roga\Database;

use Tabula17\Satelles\Omnia\Roga\Exception\ExceptionDefinitions;
use Tabula17\Satelles\Omnia\Roga\Exception\InvalidArgumentException;

enum DriversEnum: string
{
    case MYSQL = 'mysql';
    case SQLSRV = 'sqlsrv';
    case PGSQL = 'pgsql';
    case SQLITE = 'sqlite';
    case ORACLE = 'oci';

    /**
     * @throws InvalidArgumentException
     */
    public static function fromName(string $value): DriversEnum
    {
        return match ($value) {
            'mysql', 'mariadb', 'maria' => self::MYSQL,
            'mssql', 'sqlsrv' => self::SQLSRV,
            'pgsql', 'postgres', 'postgresql' => self::PGSQL,
            'sqlite' => self::SQLITE,
            'oracle', 'oci', 'oci8' => self::ORACLE,
            default => throw new InvalidArgumentException(sprintf(ExceptionDefinitions::DATABASE_DRIVER_NOT_SUPPORTED->value, $value))
        };
    }

    public static function getAvailableDrivers(): array
    {
        return array_map(static fn($enum) => $enum->value, self::cases());
    }

    public static function isSupported(string $driver): bool
    {
        return in_array($driver, self::getAvailableDrivers(), true);
    }

    public static function isAvailable(string|DriversEnum $driver): bool
    {
        $driver = $driver instanceof self ? $driver->value : $driver;
        if (!self::isSupported($driver)) {
            return false;
        }
        if (!str_starts_with($driver, 'pdo_')) {
            $driver = 'pdo_' . $driver;
        }
        return extension_loaded($driver);
    }
}
