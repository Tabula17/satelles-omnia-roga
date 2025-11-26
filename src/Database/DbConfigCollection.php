<?php

namespace Tabula17\Satelles\Omnia\Roga\Database;

use Tabula17\Satelles\Omnia\Roga\Exception\InvalidArgumentException;
use Tabula17\Satelles\Utilis\Collection\ConnectionCollection;
use Tabula17\Satelles\Utilis\Collection\GenericCollection;

class DbConfigCollection extends ConnectionCollection
{
    public static string $type = DbConfig::class;

    public function __construct(DbConfig ...$config)
    {
        parent::__construct(...$config);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function findByDriver(string $driver): DbConfig|null
    {
        return $this->findBy('driver', DriversEnum::fromName($driver));
    }

    /**
     * @throws InvalidArgumentException
     */
    public function filterByDriver(string $driver): DbConfigCollection
    {
        return $this->filterBy('driver', DriversEnum::fromName($driver));
    }

    public function findBy(string $key, mixed $value)
    {
        return $this->find(fn(DbConfig $config) => $config->$key === $value);
    }

    public function filterBy(string $key, mixed $value): DbConfigCollection
    {
        return $this->filter(fn(DbConfig $config) => $config->$key === $value);
    }

    public static function fromArray(array $config, ?string $type = null): DbConfigCollection
    {
        return new self(...array_map(static fn($item) => $item instanceof self::$type ? $item : new self::$type($item), $config));
    }

    public function collect(string $key): array
    {
        return array_filter(array_map(static fn(DbConfig $config) => $config->$key, $this->values));
    }
}