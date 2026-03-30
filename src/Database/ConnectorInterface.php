<?php

namespace Tabula17\Satelles\Omnia\Roga\Database;


use JsonException;
use Tabula17\Satelles\Omnia\Roga\Database\Pool\ConnectionPoolExtended;
use Tabula17\Satelles\Omnia\Roga\Database\Pool\PDOPoolExtended;
use Tabula17\Satelles\Utilis\Exception\InvalidArgumentException;

/**
 * Manages a collection of database connection pools, allowing for creating,
 * retrieving, and removing connection pools and individual connections.
 */
interface ConnectorInterface
{
    /**
     * Loads connections from the given collection of connection configurations.
     *
     * @param DbConfigCollection $configs A collection of connection configuration objects to be loaded.
     * @param int $poolSize The size of the connection pool must be greater than 0. Defaults to 12.
     * @return void
     * @throws InvalidArgumentException
     */
    public function loadConnections(DbConfigCollection $configs, int $poolSize = 12): void;

    /**
     * Loads a connection pool based on the provided configuration and pool size.
     *
     * @param DbConfig $config The configuration object for establishing a database connection.
     * @param int $poolSize The size of the connection pool must be greater than 0. Defaults to 12.
     * @return bool
     * @throws InvalidArgumentException If the provided pool size is less than or equal to 0.
     */
    public function loadConnection(DbConfig $config, int $poolSize = 12): bool;

    public function fetchUnreachableConnections(): DbConfigCollection;

    public function resetUnreachableConnections(): void;

    public function fetchPermanentlyFailedConnections(): DbConfigCollection;

    /**
     * @throws InvalidArgumentException
     */
    public function retryFailedConnections(int $maxRetries = 3): array;

    /**
     * @param int $maxRetries
     * @return void
     * @throws InvalidArgumentException
     */
    public function healthCheckLoadedConnections(int $maxRetries = 3): void;

    public function getPermanentlyFailedConnections(): DbConfigCollection;

    public function getUnreachableConnections(): DbConfigCollection;

    /**
     * @return int Número de conexiones cargadas activas
     */
    public function getActiveConnectionsCount(): int;

    /**
     * @return int Número de conexiones inalcanzables
     */
    public function getUnreachableCount(): int;

    public function getPermanentlyFailedCount(): int;

    /**
     * @return array Estado general del connector
     */
    public function getHealthStatus(): array;

    public function getActivePoolsCount(): int;

    /**
     * Retrieves an available connection pool by its name or creates a new one if possible.
     *
     * @param string $name The name of the connection pool to retrieve.
     * @param int $retryCount The current retry attempt count for retrieving a pool. Defaults to 0.
     * @return ConnectionPoolExtended|null The requested connection pool or null if unavailable.
     */
    public function getPool(string $name, int $retryCount = 0): PDOPoolExtended|null;

    public function hasPool(string $name): bool;

    /**
     * Removes one or all instances of the specified pool from the collection.
     *
     * @param string $name The name of the pool to remove.
     * @param bool $all Whether to remove all instances of the pool. Defaults to true.
     * @return void
     */
    public function removePool(string $name, bool $all = true): void;

    /**
     * Reduces the pool by removing one instance of the specified pool from the collection.
     *
     * @param string $name The name of the pool to reduce.
     * @return void
     */
    public function reducePool(string $name): void;

    /**
     * Removes all pools from the collection and resets the pool count.
     *
     * @return void
     */
    public function removeAllPools(): void;

    /**
     * Retrieves a connection from the specified pool.
     *
     * @param string $name The name of the pool to fetch the connection from.
     * @return mixed A connection from the pool if available, or null if the pool does not exist.
     */
    public function getConnection(string $name): mixed;

    /**
     * Returns a connection back to its corresponding pool and removes it from the list of used connections.
     *
     * @param mixed $connection The connection instance to be returned to the pool.
     * @return void
     */
    public function putConnection(mixed $connection): void;

    public function closePool(string $name): void;

    public function fillPool(string $name): void;

    public function closeAllPools(): void;

    public function fillAllPools(): void;

    /**
     * @throws JsonException
     */
    public function getAllPoolNames(): array;

    public function getPoolGroupNames(): array;

    public function getAllPoolGroupsNames(): array;

    public function getPoolNamesForGroup($name): array;

    public function getPoolCount(string $name): int;

    public function getPoolSize(string $name): int;

    /**
     * Retrieves the pool statistics in JSON format.
     *
     * @return string A JSON-encoded string representing the pool statistics.
     * @throws JsonException
     */
    public function getPoolStatsJson(): string;

    /**
     * Retrieves statistics for the specified pool or all available pools.
     *
     * @param string|null $name The name of the specific pool to retrieve stats for, or null to retrieve stats for all pools.
     *
     * @return array An associative array containing pool statistics, where keys are pool names and values are their corresponding stats.
     */
    public function getPoolStats(?string $name = null): array;
}