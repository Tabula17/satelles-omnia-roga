<?php

namespace Tabula17\Satelles\Omnia\Roga\Database;

use Tabula17\Satelles\Omnia\Roga\Exception\InvalidArgumentException;
use Tabula17\Satelles\Utilis\Collection\GenericCollection;

class DbConfigCollection extends GenericCollection
{
    protected static string $type = DbConfig::class;

    public function __construct(DbConfig ...$config)
    {
        $this->values = $config;
    }

    /**
     * @param string $name
     * @return DbConfig|null
     */
    public function findByName(string $name): DbConfig|null
    {
        return $this->findBy('name', $name);
    }
    public function removeByName(string $name): void
    {
        $this->removeBy('name', $name);
    }

    public function filterByHost(string $name): DbConfigCollection
    {
        return $this->filterBy('host', $name);
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
    public function removeBy(string $key, mixed $value): void
    {
         $this->remove($this->findBy($key, $value));
    }
    public static function fromArray(array $config): DbConfigCollection
    {
        return new self(...array_map(static fn($item) => $item instanceof self::$type ? $item : new self::$type($item), $config));
    }

    public function collect(string $key): array
    {
        return array_filter(array_map(static fn(DbConfig $config) => $config->$key, $this->values));
    }
}