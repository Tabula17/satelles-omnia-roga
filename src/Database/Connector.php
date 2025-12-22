<?php

namespace Tabula17\Satelles\Omnia\Roga\Database;

use JsonException;
use Psr\Log\LoggerInterface;
use Swoole\Coroutine;
use Swoole\Database\PDOConfig;
use Tabula17\Satelles\Omnia\Roga\Database\Pool\ConnectionPoolExtended;
use Tabula17\Satelles\Omnia\Roga\Database\Pool\PDOPoolExtended;
use Tabula17\Satelles\Omnia\Roga\Exception\ConnectionException;
use Tabula17\Satelles\Omnia\Roga\Exception\ExceptionDefinitions;
use Tabula17\Satelles\Utilis\Exception\InvalidArgumentException;
use Tabula17\Satelles\Utilis\Trait\CoroutineHelper;
use Throwable;

/**
 * Manages a collection of database connection pools, allowing for creating,
 * retrieving, and removing connection pools and individual connections.
 */
class Connector
{
    use CoroutineHelper;

    //protected(set) PoolCollection $pools ;
    private array $poolCount = [];
    private array $usedConnections = [];
    private DbConfigCollection $unreachableConnections;
    private DbConfigCollection $loadedConnections;
    private DbConfigCollection $permanentlyFailedConnections;

    /**
     * Constructor method for initializing the object with the provided parameters.
     *
     * @param PoolCollection $pools The collection of pools to be managed.
     * @param int $maxPoolInstances The maximum number of pool instances allowed.
     * @param float $waitTimeout The timeout duration (in seconds) to wait for tasks.
     * @param int $maxRetries The maximum number of retry attempts for failed tasks.
     *
     * @return void
     */
    public function __construct(
        protected PoolCollection $pools = new PoolCollection(),
        private readonly int     $maxPoolInstances = 3,
        private readonly float   $waitTimeout = 0.5,
        private readonly int     $maxRetries = 3,
        private ?LoggerInterface $logger = null
    )
    {
        $this->unreachableConnections = new DbConfigCollection();
        $this->loadedConnections = new DbConfigCollection();
        $this->permanentlyFailedConnections = new DbConfigCollection();
    }

    /**
     * Loads connections from the given collection of connection configurations.
     *
     * @param DbConfigCollection $configs A collection of connection configuration objects to be loaded.
     * @param int $poolSize The size of the connection pool must be greater than 0. Defaults to 12.
     * @return void
     * @throws InvalidArgumentException
     */
    public function loadConnections(DbConfigCollection $configs, int $poolSize = 12): void
    {
        foreach ($configs as $config) {
            $this->loadConnection($config, $poolSize);
        }
    }

    /**
     * Loads a connection pool based on the provided configuration and pool size.
     *
     * @param DbConfig $config The configuration object for establishing a database connection.
     * @param int $poolSize The size of the connection pool must be greater than 0. Defaults to 12.
     * @return bool
     * @throws InvalidArgumentException If the provided pool size is less than or equal to 0.
     */
    public function loadConnection(DbConfig $config, int $poolSize = 12): bool
    {
        if ($config->canConnect()) {
            if (isset($config->maxConnections) && ($config->maxConnections > 0)) {
                $poolSize = $config->maxConnections;
            }
            if ($poolSize <= 0) {
                $this->logger?->error(ExceptionDefinitions::POOL_SIZE_GREATER_THAN_ZERO->value, $config->toArray());
                throw new InvalidArgumentException(ExceptionDefinitions::POOL_SIZE_GREATER_THAN_ZERO->value);
            }
            $this->logger?->info("Creating pool for $config->name");
            $this->logger?->debug("Connection delay: $config->dealy ms");
            $pdoConfig = new PDOConfig()
                ->withDriver($config->driver->value)
                ->withHost($config->host);
            if (isset($config->port)) {
                $pdoConfig->withPort($config->port);
            }
            if (isset($config->dbname)) {
                $pdoConfig->withDbname($config->dbname);
            }
            if (isset($config->username)) {
                $pdoConfig->withUsername($config->username);
            }
            if (isset($config->password)) {
                $pdoConfig->withPassword($config->password);
            }
            if (isset($config->charset)) {
                $pdoConfig->withCharset($config->charset);
            }
            if (isset($config->options)) {
                $pdoConfig->withOptions($config->options);
            }
            if (!isset($this->poolCount[$config->name])) {
                $this->poolCount[$config->name] = 0;
            }
            $this->poolCount[$config->name]++;
            $effectiveName = $config->name . '::' . $this->poolCount[$config->name];
            $this->pools[$effectiveName] = new PDOPoolExtended(
                config: $pdoConfig,
                poolName: $effectiveName,
                size: $poolSize
            );
            $this->logger?->debug("Pool created: $effectiveName");
            try {
                $this->pools[$effectiveName]->fill();
                $this->logger?->debug("Pool filled: $effectiveName");
                $this->loadedConnections->addIfNotExist($config);
                return true;
            } catch (\Throwable $e) {
                $this->logger?->error("Error filling pool: $effectiveName: " . $e->getMessage());
                $config->setLastConnectionError($e->getMessage());
                $this->unreachableConnections->addIfNotExist($config);
                $this->removePool($config->name);
                return false;
            }
        } else {
            $cfg = $config->toArray();
            if (array_key_exists('password', $cfg)) {
                $cfg['password'] = '********';
            }
            $this->logger?->warning("⚠️ Connection test failed: $config->name", $cfg);
            $this->unreachableConnections->addIfNotExist($config);
            return false;
        }
    }

    public function fetchUnreachableConnections(): DbConfigCollection
    {
        $connections = clone $this->unreachableConnections;
        // LIMPIA ANTES de reintentar (evita bucle infinito)
        $this->unreachableConnections->clear();
        return $connections;
    }
    public function resetUnreachableConnections(): void
    {
        $connections = $this->fetchUnreachableConnections();
        foreach ($connections as $config) {
            $this->loadedConnections->addIfNotExist($config);
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    private function reloadUnreachableConnections(int $maxRetries = 3): void
    {
        $try = 0;
        while ($try++ < $maxRetries) {
            $connections = $this->fetchUnreachableConnections();
            if($connections->count() > 0) {
                $this->logger?->debug("Attempting to reload unreachable connections (try #$try)");
                $this->reloadConnections($connections, $maxRetries);
            }
        }

    }

    public function fetchPermanentlyFailedConnections(): DbConfigCollection
    {
        $connections = clone $this->permanentlyFailedConnections;
        $this->permanentlyFailedConnections->clear();
        foreach ($connections as $config) {
            $config->unsetMetadataProperty('retry_count');
            $config->unsetMetadataProperty('last_retry_at');
        }
        return $connections;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function retryFailedConnections(int $maxRetries = 3): array
    {
        return $this->reloadConnections($this->fetchUnreachableConnections(), $maxRetries);
    }

    /**
     * Reloads and attempts reconnections to a collection of database connections, with a specified maximum number of retries.
     *
     * @param DbConfigCollection $connections A collection of database configurations to reload.
     * @param int $maxRetries The maximum number of reconnection attempts for each connection (default is 3).
     * @return array An array containing the total number of connections, and counts of successful and failed reconnections.
     * @throws InvalidArgumentException
     */
    private function reloadConnections(DbConfigCollection $connections, int $maxRetries = 3): array
    {
        //$connections = $this->unreachableConnections;
        $originalCount = $connections->count();

        if ($originalCount === 0) {
            $this->logger?->debug("No connections to reload");
            return ['total' => 0, 'success' => 0, 'failed' => 0];
        }


        $this->logger?->info("︎🔂 Reloading {$originalCount} connections (max {$maxRetries} retries)");

        $results = ['success' => 0, 'failed' => 0];
        /* @var DbConfig $config */
        foreach ($connections as $config) {
            // Manejo de reintentos con backoff
            $retryCount = $config->metadata['retry_count'] ?? 0;

            if ($retryCount >= $maxRetries) {
                $this->logger?->warning(
                    "Connection {$config->name} exceeded max retries, moving to permanent failures",
                    ['retry_count' => $retryCount, 'last_error' => $config->lastConnectionError ?? 'unknown']
                );
                $this->permanentlyFailedConnections->addIfNotExist($config);
                $results['failed']++;
                continue;
            }

            // Backoff exponencial (esperar más en cada reintento)
            if ($retryCount > 0) {
                $backoffSeconds = min(300, (2 ** $retryCount) * 2); // 2, 4, 8, 16... segundos
                $this->logger?->debug("Backoff {$backoffSeconds}s for {$config->name} (retry #{$retryCount})");

                $this->safeSleep($backoffSeconds);
            }

            // Actualizar contador de reintentos
            $config->setMetadataProperty('retry_count', $retryCount + 1);
            $config->setMetadataProperty('last_retry_at', time());

            // Intentar reconexión
            $this->logger?->debug("Attempting reconnect to {$config->name} (retry #{$config->metadata['retry_count']})");

            if ($this->loadConnection($config)) {
                $results['success']++;
                $this->logger?->info("✅ Successfully reconnected to {$config->name}");

                // Resetear contador de reintentos si tuvo éxito
                $config->unsetMetadataProperty('retry_count');
            } else {
                $results['failed']++;
                $this->logger?->warning("❌ Failed to reconnect to {$config->name}", [
                    'retry_count' => $config->metadata['retry_count'],
                    'error' => $config->lastConnectionError ?? 'Unknown error'
                ]);
            }
        }

        $results['total'] = $originalCount;
        $this->logger?->info("Reload completed", $results);

        return $results;
    }

    /**
     * @param int $maxRetries
     * @return void
     * @throws InvalidArgumentException
     */
    public function healthCheckLoadedConnections(int $maxRetries = 3): void
    {
        foreach ($this->loadedConnections as $config) {
            if (!$config->canConnect()) {
                $cfg = $config->toArray();
                if (array_key_exists('password', $cfg)) {
                    $cfg['password'] = '********';
                }
                $this->logger?->warning("🚦 Connection test failed: $config->name", $cfg);
                $this->unreachableConnections->addIfNotExist($config);
                $this->removePool($config->name);
            }
        }
        if($this->unreachableConnections->count() > 0) {
            $this->reloadUnreachableConnections($maxRetries);
        }
    }

    public function getPermanentlyFailedConnections(): DbConfigCollection
    {
        return $this->permanentlyFailedConnections;
    }

    public function getUnreachableConnections(): DbConfigCollection
    {
        return $this->unreachableConnections;
    }

    /**
     * @return int Número de conexiones cargadas activas
     */
    public function getActiveConnectionsCount(): int
    {
        return $this->loadedConnections->count();
    }

    /**
     * @return int Número de conexiones inalcanzables
     */
    public function getUnreachableCount(): int
    {
        return $this->unreachableConnections->count();
    }

    public function getPermanentlyFailedCount(): int
    {
        return $this->permanentlyFailedConnections->count();
    }

    /**
     * Realiza health check de todas las conexiones cargadas
     * @return array Estadísticas del health check
     * public function performHealthCheck(): array
     * {
     * $startTime = microtime(true);
     * $initialCount = $this->loadedConnections->count();
     * $initialPoolsUp = array_keys($this->poolCount);
     *
     * // También intentar reconectar las inalcanzables periódicamente
     * if (time() % 300 === 0) { // Cada 5 minutos
     * try {
     * $this->reloadUnreachableConnections();
     * } catch (Throwable $e) {
     * $this->logger?->error("Error reloading unreachable connections: " . $e->getMessage());
     * }
     * }
     * $this->healthCheckLoadedConnections();
     * $this->logger?->debug("Health check finished in " . round((microtime(true) - $startTime) * 1000, 2) . "ms");
     *
     * $online = $this->loadedConnections->count();
     * $unreachable = $this->unreachableConnections->count();
     * $permanentFailures = $this->permanentlyFailedConnections->count();
     * $totalConnections = $initialCount + $unreachable + $permanentFailures;
     * $failed = $unreachable + $permanentFailures;
     * $healthy = $totalConnections - $failed;
     * $poolsNow = array_keys($this->poolCount);
     * $poolsUp = array_diff($poolsNow, $initialPoolsUp);
     * $poolsDown = array_diff($initialPoolsUp, $poolsNow);
     * $changed = count($poolsUp) + count($poolsDown);
     *
     *
     * return [
     * 'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
     * 'loaded_connections' => $online,
     * 'unreachable_connections' => $unreachable,
     * 'permanent_failures' => $permanentFailures,
     * 'status_change' => abs($changed),
     * 'status_change_percentage' => round(($changed / $totalConnections) * 100, 2),
     * 'healthy' => $healthy,
     * 'failed' => $failed,
     * 'overall_healthy' => $healthy === $totalConnections,
     * 'healthy_percentage' => round(($healthy / $totalConnections) * 100, 2),
     * 'active_pools' => $this->pools->count(),
     * 'pools_online' => $poolsNow,
     * 'pools_up' => $poolsUp,
     * 'pools_down' => $poolsDown,
     * 'pools_unchanged' => count($initialPoolsUp) - count($poolsUp) - count($poolsDown),
     * 'timestamp' => time()
     * ];
     * }
     */

    /**
     * @return array Estado general del connector
     */
    public function getHealthStatus(): array
    {
        return [
            'loaded_connections' => $this->loadedConnections->count(),
            'unreachable_connections' => $this->unreachableConnections->count(),
            'permanent_failures' => $this->permanentlyFailedConnections->count(),
            'active_pools' => $this->pools->count(),
            'pool_groups' => array_keys($this->poolCount),
            'timestamp' => time()
        ];
    }

    public function getActivePoolsCount(): int
    {
        return $this->pools->count();
    }

    /**
     * Retrieves an available connection pool by its name or creates a new one if possible.
     *
     * @param string $name The name of the connection pool to retrieve.
     * @param int $retryCount The current retry attempt count for retrieving a pool. Defaults to 0.
     * @return ConnectionPoolExtended|null The requested connection pool or null if unavailable.
     */
    public function getPool(string $name, int $retryCount = 0): PDOPoolExtended|null
    {
        if ($retryCount >= $this->maxRetries) {
            return null;
        }

        $poolCount = $this->poolCount[$name] ?? 0;
        $this->logger?->debug("Retrieving pool for $name. Count: $poolCount");
        if ($poolCount === 0) {
            return null;
        }

        // Buscar pool disponible
        for ($i = 1; $i <= $poolCount; $i++) {
            $effectiveName = $name . '::' . $i;
            if (isset($this->pools[$effectiveName])) {
                $pool = $this->pools[$effectiveName];
                if ($pool->available() > 0) {
                    $this->logger?->debug("Found available pool: $effectiveName");
                    return $pool;
                }
            }
        }

        // Crear nuevo pool si es posible
        if ($poolCount < $this->maxPoolInstances && isset($pool)) {
            $this->logger?->debug("Creating new pool for $name");
            $newPoolName = $name . '::' . ($poolCount + 1);
            $this->pools[$newPoolName] = new PDOPoolExtended(
                config: $pool->getConfig(),
                poolName: $newPoolName,
                size: $pool->getSize()
            );
            $this->poolCount[$name] = $poolCount + 1;
            $this->pools[$newPoolName]->fill();
            $this->logger?->debug("New pool created: $newPoolName");
            return $this->pools[$newPoolName];
        }

        // Retry con delay asíncrono
        if ($retryCount < $this->maxRetries) {
            //Coroutine::sleep($this->waitTimeout);
            $this->safeSleep($this->waitTimeout);
            return $this->getPool($name, $retryCount + 1);
        }

        return null;
    }

    public function hasPool(string $name): bool
    {
        return isset($this->poolCount[$name]) && $this->poolCount[$name] > 0;
    }

    /**
     * Removes one or all instances of the specified pool from the collection.
     *
     * @param string $name The name of the pool to remove.
     * @param bool $all Whether to remove all instances of the pool. Defaults to true.
     * @return void
     */
    public function removePool(string $name, bool $all = true): void
    {
        $total = $this->poolCount[$name] ?? 0;
        if ($total > 0) {
            $limit = $all ? 0 : $total - 1;
            for ($i = $total; $i >= $limit; $i--) {
                $effectiveName = $name . '::' . $i;
                if (isset($this->pools[$effectiveName])) {
                    $this->pools[$effectiveName]->close();
                    unset($this->pools[$effectiveName]);
                    --$this->poolCount[$name];
                }
            }
            if ($this->poolCount[$name] === 0) {
                unset($this->poolCount[$name]);
                $this->loadedConnections->removeByName($name);
            }
        }
    }

    /**
     * Reduces the pool by removing one instance of the specified pool from the collection.
     *
     * @param string $name The name of the pool to reduce.
     * @return void
     */
    public function reducePool(string $name): void
    {
        $this->removePool($name, false);
    }

    /**
     * Removes all pools from the collection and resets the pool count.
     *
     * @return void
     */
    public function removeAllPools(): void
    {
        $this->pools = new PoolCollection();
        $this->poolCount = [];
        $this->loadedConnections = new DbConfigCollection();
        $this->unreachableConnections = new DbConfigCollection();
    }

    /**
     * Retrieves a connection from the specified pool.
     *
     * @param string $name The name of the pool to fetch the connection from.
     * @return mixed A connection from the pool if available, or null if the pool does not exist.
     */
    public function getConnection(string $name): mixed
    {
        try {
            $pool = $this->getPool($name);
            if (!$pool) {
                throw new ConnectionException(sprintf(ExceptionDefinitions::POOL_NOT_FOUND->value, $name));
            }
            $this->logger?->debug("Getting connection from pool: $name");
            $conn = $pool->get($this->waitTimeout);
            if ($conn === false) {
                throw new ConnectionException(sprintf(ExceptionDefinitions::POOL_CONNECTION_TIMEOUT->value, $name, $this->waitTimeout));
            }

            $poolName = $pool->poolName;
            $this->logger?->debug("Connection retrieved from pool: $poolName");
            $connectionId = spl_object_id($conn);
            $this->logger?->debug("Connection ID: $connectionId");
            $this->usedConnections[$connectionId] = $poolName;

            return $conn;
        } catch (\Exception $e) {
            // Log the error
            $this->logger?->error("Failed to get connection from pool '$name': " . $e->getMessage());
            return null;
        }
    }

    /**
     * Returns a connection back to its corresponding pool and removes it from the list of used connections.
     *
     * @param mixed $connection The connection instance to be returned to the pool.
     * @return void
     */
    public function putConnection(mixed $connection): void
    {
        $connectionId = spl_object_id($connection);
        $this->logger?->debug("Putting connection back to pool: $connectionId");
        $poolName = $this->usedConnections[$connectionId] ?? null;

        if ($poolName) {
            //here we don't need check if pool exists or create one because we already checked it before getting the connection
            $this->pools[$poolName]?->put($connection);
            $this->logger?->debug("Connection returned to pool: $poolName");
            unset($this->usedConnections[$connectionId]); // ✅ Correcto
        }
    }

    public function closePool(string $name): void
    {
        $total = $this->poolCount[$name] ?? 0;
        if ($total > 0) {
            $pool = $this->getPool($name);
            if (($pool instanceof PDOPoolExtended) && $pool->isDestroyed()) {
                return;
            }
            $pool?->close();
        }
    }

    public function fillPool(string $name): void
    {
        $this->getPool($name)?->fill();
    }

    public function closeAllPools(): void
    {
        foreach ($this->pools as $pool) {
            $this->logger?->debug("🔑 Closing pool: " . $pool->poolName);
            if (($pool instanceof PDOPoolExtended) && $pool->isDestroyed()) {
                return;
            }
            $pool?->close();
        }
    }

    public function fillAllPools(): void
    {
        foreach ($this->pools as $pool) {
            if (($pool instanceof PDOPoolExtended) && $pool->isDestroyed()) {
                return;
            }
            $pool->fill();
        }
    }

    /**
     * @throws JsonException
     */
    public function getAllPoolNames(): array
    {
        return array_keys($this->pools->toArray());
    }

    public function getPoolGroupNames(): array
    {
        return array_keys($this->poolCount);
    }

    public function getPoolNamesForGroup($name): array
    {
        return array_keys(array_filter($this->pools->toArray(), static fn($pool) => str_starts_with($pool, $name), ARRAY_FILTER_USE_KEY));
    }

    public function getPoolCount(string $name): int
    {
        return $this->poolCount[$name] ?? 0;
    }

    public function getPoolSize(string $name): int
    {
        return $this->pools[$name]->getSize();
    }

    /**
     * Retrieves the pool statistics in JSON format.
     *
     * @return string A JSON-encoded string representing the pool statistics.
     * @throws JsonException
     */
    public function getPoolStatsJson(): string
    {
        return json_encode($this->getPoolStats(null), JSON_THROW_ON_ERROR);
    }

    /**
     * Retrieves statistics for the specified pool or all available pools.
     *
     * @param string|null $name The name of the specific pool to retrieve stats for, or null to retrieve stats for all pools.
     *
     * @return array An associative array containing pool statistics, where keys are pool names and values are their corresponding stats.
     */
    public function getPoolStats(?string $name = null): array
    {
        $stats = [];
        if ($name && $this->poolCount[$name] > 0) {
            $start = 1;
            while ($start <= $this->poolCount[$name]) {
                $effectiveName = $name . '::' . $start++;
                $stats[$effectiveName] = $this->getSinglePoolStats($effectiveName);
            }
        } else {
            foreach ($this->pools as $poolName => $pool) {
                $stats[$poolName] = $this->getSinglePoolStats($poolName);
            }
        }
        return $stats;
    }

    private function getSinglePoolStats(string $name): array
    {
        /**
         * @var PDOPoolExtended $pool
         */
        $pool = $this->pools[$name] ?? null;
        if (!$pool) {
            return ['error' => 'Pool not found'];
        }

        $out = [
            'available' => $pool->available(),
            'capacity' => $pool->getSize(),
            'utilization' => (($pool->getSize() - $pool->available()) / $pool->getSize()) * 100,
            'used_connections' => count(array_filter($this->usedConnections, static fn($pn) => $pn === $name)),
            'stats' => $pool->stats()
        ];
        if ($pool instanceof PDOPoolExtended) {
            $out = array_merge($out, [
                'host' => $pool->getConfig()->getHost(),
                'port' => (string)$pool->getConfig()->getPort(),
                'dbname' => $pool->getConfig()->getDbname(),
                'username' => $pool->getConfig()->getUsername(),
                'charset' => $pool->getConfig()->getCharset(),
                'driver' => $pool->getConfig()->getDriver(),
            ]);
        }
        return $out;
    }

    public function __destruct()
    {
        $this->closeAllPools();
    }
}