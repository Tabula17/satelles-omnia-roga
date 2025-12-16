<?php
declare(strict_types=1);

namespace Tabula17\Satelles\Omnia\Roga\Database;

use Psr\Log\LoggerInterface;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Swoole\Server;
use Tabula17\Satelles\Utilis\Exception\InvalidArgumentException;
use Tabula17\Satelles\Utilis\Trait\CoroutineHelper;
use Throwable;
use Swoole\Timer;

/**
 * Class HealthManager
 *
 * This class is responsible for managing health checks across workers in a server ecosystem.
 * It provides mechanisms to start, stop, and monitor health checks, as well as perform periodic diagnostics
 * such as database and memory health inspections. The class also handles graceful stopping of health checks
 * to ensure a clean shutdown process.
 */


class HealthManager
{
    private Connector $connector;
    private int $checkInterval;

    // Propiedades para control de workers
    private array $runningWorkers = [];
    private bool $stopping = false;

    // Sistema de control con Channels
    private Channel $controlChannel;
    private array $workerControlChannels = [];
    private array $workerCoroutineIds = [];

    // Estadísticas
    private array $healthStats = [];
    private array $checkHistory = [];
    private const MAX_HISTORY = 100;

    public function __construct(
        Connector $connector,
        int $checkInterval = 30000, // 30 segundos por defecto
        private readonly ?LoggerInterface $logger = null
    ) {
        $this->connector = $connector;
        $this->checkInterval = $checkInterval;

        // Crear canal de control principal
        $this->controlChannel = new Channel(32);
    }

    /**
     * Configura el health check en el servidor
     */
    public function setupServerTick(Server $server, int $workerId): void
    {
        // Solo workers principales (no task workers)
        if ($workerId >= $server->setting['worker_num']) {
            $this->logger?->debug("Worker #{$workerId} es task worker, omitiendo health checks");
            return;
        }

        if (isset($this->runningWorkers[$workerId])) {
            $this->logger?->warning("Worker #{$workerId} ya tiene health checks configurados");
            return;
        }

        $this->logger?->info("⚙️ Configurando health checks para worker #{$workerId}");
        $this->startHealthCheckCycle($server, $workerId);
    }

    /**
     * Inicia el ciclo de health checks para un worker
     */
    public function startHealthCheckCycle(Server $server, int $workerId): void
    {
        // Registrar worker
        $this->runningWorkers[$workerId] = [
            'started_at' => microtime(true),
            'last_check' => 0,
            'cycle_count' => 0,
            'status' => 'starting',
            'consecutive_failures' => 0
        ];

        // Calcular offset escalonado
        $offset = $this->calculateWorkerOffset($workerId, $server->setting['worker_num']);

        // Crear canal de control para este worker específico
        $workerControlChannel = new Channel(2);
        $this->workerControlChannels[$workerId] = $workerControlChannel;

        // Iniciar la coroutine de health checks
        $coroutineId = Coroutine::create(function () use ($server, $workerId, $offset, $workerControlChannel) {
            $this->workerCoroutineIds[$workerId] = Coroutine::getCid();

            $this->logger?->info("👷 Worker #{$workerId}: Health checks iniciarán en {$offset}s");

            // Esperar offset escalonado
            if ($offset > 0 && !$this->sleepWithStopCheck($offset, $workerControlChannel)) {
                $this->cleanupWorker($workerId);
                return;
            }

            if ($this->stopping) {
                $this->cleanupWorker($workerId);
                return;
            }

            // Cambiar estado a running
            $this->runningWorkers[$workerId]['status'] = 'running';

            // Ejecutar loop principal
            $this->runHealthCheckLoop($server, $workerId, $workerControlChannel);

            // Limpiar al finalizar
            $this->cleanupWorker($workerId);
        });

        $this->logger?->debug("Worker #{$workerId}: Health check coroutine iniciada (CID: {$coroutineId})");
    }

    /**
     * Loop principal de health checks con control por Channel
     */
    private function runHealthCheckLoop(Server $server, int $workerId, Channel $controlChannel): void
    {
        $this->logger?->debug("Worker #{$workerId}: Iniciando loop de health checks");

        try {
            while (true) {
                // 1. Verificar si debemos detenernos
                if ($this->shouldStop($controlChannel)) {
                    $this->logger?->debug("Worker #{$workerId}: Señal de stop recibida");
                    break;
                }

                // 2. Ejecutar health check
                $checkStart = microtime(true);
                $result = $this->performHealthChecks($workerId);
                $checkDuration = microtime(true) - $checkStart;

                // 3. Actualizar estadísticas del worker
                $this->runningWorkers[$workerId]['last_check'] = time();
                $this->runningWorkers[$workerId]['cycle_count']++;
                $this->runningWorkers[$workerId]['last_duration'] = $checkDuration;
                $this->runningWorkers[$workerId]['last_result'] = $result['overall_healthy'];

                // 4. Manejar fallos consecutivos
                if ($result['overall_healthy']) {
                    $this->runningWorkers[$workerId]['consecutive_failures'] = 0;
                    $this->runningWorkers[$workerId]['last_success'] = time();
                    $this->logger?->debug("Worker #{$workerId}: Health check OK ({$checkDuration}s)");
                } else {
                    $this->runningWorkers[$workerId]['consecutive_failures']++;
                    $this->runningWorkers[$workerId]['last_failure'] = time();

                    $failures = $this->runningWorkers[$workerId]['consecutive_failures'];
                    $this->logger?->warning("Worker #{$workerId}: Health check FAILED ({$failures} consecutivos)");

                    // Si hay muchos fallos consecutivos, intentar recuperación
                    if ($failures >= 3) {
                        $this->handleConsecutiveFailures($workerId, $result);
                    }
                }

                // 5. Guardar en historial
                $this->addToHistory([
                    'worker_id' => $workerId,
                    'timestamp' => time(),
                    'duration' => $checkDuration,
                    'healthy' => $result['overall_healthy'],
                    'stats' => $result['pool_stats'] ?? []
                ]);

                // 6. Esperar hasta el próximo check con posibilidad de stop
                $waitTime = $this->checkInterval / 1000; // ms a segundos
                if (!$this->sleepWithStopCheck($waitTime, $controlChannel)) {
                    break;
                }
            }
        } catch (\Throwable $e) {
            $this->logger?->error("Worker #{$workerId}: Error en health check loop: " . $e->getMessage());
        } finally {
            $this->logger?->info("Worker #{$workerId}: Loop de health checks finalizado");
        }
    }

    /**
     * Sleep que puede ser interrumpido por señal de stop
     */
    private function sleepWithStopCheck(float $seconds, Channel $controlChannel): bool
    {
        $endTime = microtime(true) + $seconds;

        while (microtime(true) < $endTime && !$this->stopping) {
            $remaining = $endTime - microtime(true);
            $chunkTime = min(0.1, max(0.001, $remaining));

            // Usar select para esperar con timeout O señal en el canal
            $read = [$controlChannel];
            $write = [];

            $result = Coroutine::select($read, $write, $chunkTime);

            // Si hay algo en el canal de lectura, es señal de stop
            if (!empty($result['read'])) {
                $message = $controlChannel->pop(0.001);
                if ($message === 'stop' || $message === false) {
                    return false; // Señal de stop recibida
                }
            }

            // Verificar flag stopping
            if ($this->stopping) {
                return false;
            }
        }

        return true; // Sleep completado normalmente
    }

    /**
     * Verifica si debemos detener el loop
     */
    private function shouldStop(Channel $controlChannel): bool
    {
        if ($this->stopping) {
            return true;
        }

        // Verificar si hay mensaje en el canal (non-blocking)
        $stats = $controlChannel->stats();
        if ($stats['queue_num'] > 0) {
            $message = $controlChannel->pop(0.001);
            return $message === 'stop' || $message === false;
        }

        return false;
    }

    /**
     * Maneja fallos consecutivos
     */
    private function handleConsecutiveFailures(int $workerId, array $checkResult): void
    {
        $failures = $this->runningWorkers[$workerId]['consecutive_failures'];
        $this->logger?->warning("Worker #{$workerId}: {$failures} fallos consecutivos detectados");

        // Intentar recuperar conexiones fallidas usando el método del Connector
        try {
            $recoveryResult = $this->connector->retryFailedConnections();

            if ($recoveryResult['recovered'] > 0) {
                $this->logger?->info("Worker #{$workerId}: Recuperadas {$recoveryResult['recovered']} conexiones");
                $this->runningWorkers[$workerId]['consecutive_failures'] = 0;
                $this->runningWorkers[$workerId]['recovery_attempts'] =
                    ($this->runningWorkers[$workerId]['recovery_attempts'] ?? 0) + 1;
            } else {
                $this->logger?->warning("Worker #{$workerId}: No se pudieron recuperar conexiones");
            }

            // Notificar al canal de control principal
            $this->notifyControlChannel('recovery_attempt', [
                'worker_id' => $workerId,
                'recovery_result' => $recoveryResult,
                'consecutive_failures' => $failures,
                'timestamp' => time()
            ]);

        } catch (\Exception $e) {
            $this->logger?->error("Worker #{$workerId}: Error en recuperación: " . $e->getMessage());
        }
    }

    /**
     * Notifica al canal de control principal
     */
    private function notifyControlChannel(string $type, array $data): void
    {
        try {
            $this->controlChannel->push([
                'type' => $type,
                'data' => $data,
                'timestamp' => time()
            ], 0.1);
        } catch (\Exception $e) {
            // Canal lleno o cerrado, es normal durante shutdown
        }
    }

    /**
     * Detiene todos los health checks gracefulmente
     */
    public function stopGracefully(int $timeout = 5): array
    {
        if ($this->stopping) {
            $this->logger?->warning("stopGracefully ya fue llamado anteriormente");
            return ['status' => 'already_stopping', 'workers' => count($this->runningWorkers)];
        }

        $this->stopping = true;
        $this->logger?->info("🛑 Iniciando stop graceful de health checks...");

        $results = [
            'total_workers' => count($this->workerControlChannels),
            'workers_stopped' => 0,
            'workers_failed' => 0,
            'timeout' => false,
            'timestamp' => time()
        ];

        $startTime = microtime(true);

        // 1. Enviar señal de stop a todos los workers
        foreach ($this->workerControlChannels as $workerId => $channel) {
            try {
                if ($channel->push('stop', 0.1)) {
                    $this->logger?->debug("Señal 'stop' enviada al worker #{$workerId}");
                    $results['workers_stopped']++;
                } else {
                    $this->logger?->warning("No se pudo enviar señal al worker #{$workerId} (canal lleno/cerrado)");
                    $results['workers_failed']++;
                }
            } catch (\Exception $e) {
                $this->logger?->error("Error enviando stop al worker #{$workerId}: " . $e->getMessage());
                $results['workers_failed']++;
            }
        }

        // 2. Esperar que todos los workers terminen
        $maxWaitTime = $timeout;
        $pollInterval = 0.1; // 100ms

        while (count($this->workerControlChannels) > 0) {
            $elapsed = microtime(true) - $startTime;

            if ($elapsed > $maxWaitTime) {
                $this->logger?->warning("Timeout de {$timeout}s esperando que workers terminen");
                $results['timeout'] = true;
                $results['workers_remaining'] = count($this->workerControlChannels);
                break;
            }

            $remaining = count($this->workerControlChannels);
            if ($remaining > 0) {
                $this->logger?->debug("Esperando que {$remaining} workers terminen... " .
                    sprintf("(%.1fs/%.1fs)", $elapsed, $maxWaitTime));
                Coroutine::sleep($pollInterval);
            }
        }

        // 3. Cerrar todos los canales
        $this->closeAllChannels();

        $totalTime = microtime(true) - $startTime;
        $this->logger?->info(sprintf(
            "✅ Health checks detenidos. Tiempo total: %.2fs. Workers: %d/%d",
            $totalTime,
            $results['workers_stopped'],
            $results['total_workers']
        ));

        $results['execution_time'] = $totalTime;
        $results['stopping'] = $this->stopping;

        return $results;
    }

    /**
     * Ejecuta los health checks
     */
    public function performHealthChecks(int $workerId = 0): array
    {
        $results = [
            'worker_id' => $workerId,
            'timestamp' => time(),
            'overall_healthy' => true,
            'checks' => []
        ];

        try {
            // 1. Obtener estadísticas de pools
            $poolStats = $this->connector->getPoolStats();
            $results['pool_stats'] = $poolStats;

            // 2. Obtener estado de salud
            $healthStatus = $this->connector->getHealthStatus();
            $results['health_status'] = $healthStatus;

            // 3. Evaluar salud general basado en pools
            foreach ($poolStats as $poolName => $stats) {
                $poolHealthy = true;
                $poolDetails = ['name' => $poolName];

                // Verificar conexiones activas
                if (isset($stats['active']) && $stats['active'] === 0) {
                    $poolHealthy = false;
                    $poolDetails['error'] = 'No hay conexiones activas';
                }

                // Verificar conexiones en espera (si aplica)
                if (isset($stats['waiting']) && $stats['waiting'] > 10) {
                    $poolDetails['warning'] = "Muchas conexiones en espera: {$stats['waiting']}";
                }

                // Verificar errores recientes
                if (isset($stats['errors']) && $stats['errors'] > 0) {
                    $poolDetails['errors'] = $stats['errors'];
                    if ($stats['errors'] > 5) {
                        $poolHealthy = false;
                    }
                }

                $results['checks'][$poolName] = [
                    'healthy' => $poolHealthy,
                    'details' => $poolDetails
                ];

                if (!$poolHealthy) {
                    $results['overall_healthy'] = false;
                }
            }

            // 4. Verificar estado general del connector
            if (isset($healthStatus['status']) && $healthStatus['status'] !== 'healthy') {
                $results['overall_healthy'] = false;
                $results['connector_status'] = $healthStatus['status'];
            }

        } catch (\Exception $e) {
            $this->logger?->error("Worker #{$workerId}: Error en health check: " . $e->getMessage());
            $results['error'] = $e->getMessage();
            $results['overall_healthy'] = false;
        }

        return $results;
    }

    /**
     * Retry de fallos permanentes usando el Connector
     */
    public function retryPermanentFailures(int $workerId = 0): array
    {
        $this->logger?->info("Worker #{$workerId}: Intentando recuperar conexiones fallidas");

        try {
            // Usar el método del Connector para recuperar conexiones
            $result = $this->connector->retryFailedConnections();

            $this->logger?->info(sprintf(
                "Worker #{$workerId}: Recuperación - Intentadas: %d, Recuperadas: %d, Fallidas: %d",
                $result['attempted'] ?? 0,
                $result['recovered'] ?? 0,
                $result['failed'] ?? 0
            ));

            // Resetear contador de fallos consecutivos si hubo recuperaciones
            if (isset($result['recovered']) && $result['recovered'] > 0 && isset($this->runningWorkers[$workerId])) {
                $this->runningWorkers[$workerId]['consecutive_failures'] = 0;
                $this->runningWorkers[$workerId]['last_recovery'] = time();
            }

            return $result;

        } catch (\Exception $e) {
            $this->logger?->error("Worker #{$workerId}: Error en retryPermanentFailures: " . $e->getMessage());
            return [
                'attempted' => 0,
                'recovered' => 0,
                'failed' => 1,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Calcula offset escalonado para workers
     */
    private function calculateWorkerOffset(int $workerId, int $totalWorkers): float
    {
        if ($totalWorkers <= 1) {
            return 0;
        }

        // Distribuir checks en el intervalo completo
        $baseInterval = $this->checkInterval / 1000; // en segundos
        $offset = ($workerId % $totalWorkers) * ($baseInterval / $totalWorkers);

        return min($offset, $baseInterval * 0.8); // Máximo 80% del intervalo
    }

    /**
     * Agrega entrada al historial
     */
    private function addToHistory(array $entry): void
    {
        $this->checkHistory[] = $entry;

        // Mantener tamaño limitado
        if (count($this->checkHistory) > self::MAX_HISTORY) {
            array_shift($this->checkHistory);
        }
    }

    /**
     * Limpia los recursos de un worker
     */
    private function cleanupWorker(int $workerId): void
    {
        // Cerrar canal de control
        if (isset($this->workerControlChannels[$workerId])) {
            try {
                $this->workerControlChannels[$workerId]->close();
            } catch (\Exception $e) {
                // Ignorar errores al cerrar
            }
            unset($this->workerControlChannels[$workerId]);
        }

        // Remover de tracking
        unset($this->workerCoroutineIds[$workerId]);

        // Marcar como detenido (no eliminar para mantener historial)
        if (isset($this->runningWorkers[$workerId])) {
            $this->runningWorkers[$workerId]['status'] = 'stopped';
            $this->runningWorkers[$workerId]['stopped_at'] = time();
        }

        $this->logger?->debug("Worker #{$workerId}: Recursos limpiados");
    }

    /**
     * Cierra todos los canales de control
     */
    private function closeAllChannels(): void
    {
        foreach ($this->workerControlChannels as $workerId => $channel) {
            try {
                $channel->close();
                $this->logger?->debug("Canal cerrado para worker #{$workerId}");
            } catch (\Exception $e) {
                $this->logger?->error("Error cerrando canal worker #{$workerId}: " . $e->getMessage());
            }
        }

        $this->workerControlChannels = [];
        $this->workerCoroutineIds = [];

        // También cerrar canal principal
        try {
            $this->controlChannel->close();
        } catch (\Exception $e) {
            // Ignorar
        }
    }

    /**
     * Obtiene estado de salud actual
     */
    public function getHealthStatus(): array
    {
        $activeWorkers = array_filter(
            $this->runningWorkers,
            fn($worker) => ($worker['status'] ?? '') === 'running'
        );

        return [
            'running_workers' => count($activeWorkers),
            'total_workers' => count($this->runningWorkers),
            'stopping' => $this->stopping,
            'active_coroutines' => count($this->workerCoroutineIds),
            'active_channels' => count($this->workerControlChannels),
            'check_interval_ms' => $this->checkInterval,
            'check_history_count' => count($this->checkHistory),
            'last_check' => end($this->checkHistory) ?: null,
            'timestamp' => time()
        ];
    }

    /**
     * Obtiene estadísticas detalladas por worker
     */
    public function getWorkerStats(): array
    {
        $stats = [];

        foreach ($this->runningWorkers as $workerId => $info) {
            $stats[$workerId] = [
                'status' => $info['status'] ?? 'unknown',
                'started_at' => $info['started_at'] ?? 0,
                'stopped_at' => $info['stopped_at'] ?? null,
                'last_check' => $info['last_check'] ?? 0,
                'cycle_count' => $info['cycle_count'] ?? 0,
                'last_duration' => $info['last_duration'] ?? 0,
                'consecutive_failures' => $info['consecutive_failures'] ?? 0,
                'last_success' => $info['last_success'] ?? 0,
                'last_failure' => $info['last_failure'] ?? 0,
                'recovery_attempts' => $info['recovery_attempts'] ?? 0,
                'last_recovery' => $info['last_recovery'] ?? 0,
                'has_channel' => isset($this->workerControlChannels[$workerId]),
                'coroutine_id' => $this->workerCoroutineIds[$workerId] ?? null,
                'last_result' => $info['last_result'] ?? null
            ];
        }

        return $stats;
    }

    /**
     * Obtiene historial de checks
     */
    public function getCheckHistory(int $limit = 20): array
    {
        return array_slice($this->checkHistory, -$limit);
    }

    /**
     * Destructor - asegura limpieza
     */
    public function __destruct()
    {
        if (!$this->stopping && !empty($this->workerControlChannels)) {
            $this->logger?->warning("HealthManager destruido sin stopGracefully(), limpiando canales");
            $this->closeAllChannels();
        }
    }
}