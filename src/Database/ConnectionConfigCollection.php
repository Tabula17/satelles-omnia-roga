<?php

namespace Tabula17\Satelles\Omnia\Roga\Database;

use Tabula17\Satelles\Omnia\Roga\Exception\InvalidArgumentException;
use Tabula17\Satelles\Utilis\Collection\GenericCollection;

class ConnectionConfigCollection extends GenericCollection
{
    protected static string $type = ConnectionConfig::class;

    public function __construct(ConnectionConfig ...$config)
    {
        $this->values = $config;
    }

    /**
     * @param string $name
     * @return ConnectionConfig|null
     */
    public function findByName(string $name): ConnectionConfig|null
    {
        return $this->findBy('name', $name);
    }
    public function removeByName(string $name): void
    {
        $this->removeBy('name', $name);
    }

    public function filterByHost(string $name): ConnectionConfigCollection
    {
        return $this->filterBy('host', $name);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function findByDriver(string $driver): ConnectionConfig|null
    {
        return $this->findBy('driver', DriversEnum::fromName($driver));
    }

    /**
     * @throws InvalidArgumentException
     */
    public function filterByDriver(string $driver): ConnectionConfigCollection
    {
        return $this->filterBy('driver', DriversEnum::fromName($driver));
    }

    public function findBy(string $key, mixed $value)
    {
        return $this->find(fn(ConnectionConfig $config) => $config->$key === $value);
    }

    public function filterBy(string $key, mixed $value): ConnectionConfigCollection
    {
        return $this->filter(fn(ConnectionConfig $config) => $config->$key === $value);
    }
    public function removeBy(string $key, mixed $value): void
    {
         $this->remove($this->findBy($key, $value));
    }
    public static function fromArray(array $config): ConnectionConfigCollection
    {
        return new self(...array_map(static fn($item) => $item instanceof self::$type ? $item : new self::$type($item), $config));
    }

    public function collect(string $key): array
    {
        return array_filter(array_map(static fn(ConnectionConfig $config) => $config->$key, $this->values));
    }
}