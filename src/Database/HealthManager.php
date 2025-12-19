<?php
declare(strict_types=1);

namespace Tabula17\Satelles\Omnia\Roga\Database;

use Closure;
use Psr\Log\LoggerInterface;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Swoole\Server;
use Tabula17\Satelles\Utilis\Connectable\HealthManagerInterface;
use Tabula17\Satelles\Utilis\Trait\CoroutineHelper;

/**
 * Class HealthManager
 *
 * This class is responsible for managing health checks across workers in a server ecosystem.
 * It provides mechanisms to start, stop, and monitor health checks, as well as perform periodic diagnostics
 * such as database and memory health inspections. The class also handles graceful stopping of health checks
 * to ensure a clean shutdown process.
 */
class HealthManager implements HealthManagerInterface
{
    use CoroutineHelper;

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
    private const int MAX_HISTORY = 100;

    protected(set) ?Closure $notifier;

    public function __construct(
        Connector                         $connector,
        int                               $checkInterval = 30000, // 30 segundos por defecto
        private readonly ?LoggerInterface $logger = null
    )
    {
        $this->connector = $connector;
        $this->checkInterval = $checkInterval;

        // Crear canal de control principal
        $this->controlChannel = new Channel(32);
    }

    /**
     * Inicia el ciclo de health checks para un worker
     */
    public function startHealthCheckCycle(Server $server, int $workerId): void
    {
        // Verificar si es worker principal (no task worker)
        if ($workerId >= $server->setting['worker_num']) {
            $this->logger?->debug("🏥 Worker #{$workerId} es task worker, omitiendo health checks");
            return;
        }

        if (isset($this->runningWorkers[$workerId])) {
            $this->logger?->warning("🏥 Worker #{$workerId} ya tiene health checks configurados");
            return;
        }
        $this->logger?->info("⚙️ Configurando health checks para worker #{$workerId}");
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

        $this->logger?->debug("🏥 Worker #{$workerId}: Health check coroutine iniciada (CID: {$coroutineId})");
    }

    /**
     * Loop principal de health checks con control por Channel
     */
    private function runHealthCheckLoop(Server $server, int $workerId, Channel $controlChannel): void
    {
        $cid = Coroutine::getCid();
        $this->logger?->info("🚀 [Worker #{$workerId}] HealthCheckLoop INICIADO - Coroutine ID: {$cid}");

        $loopCounter = 0;
        try {
            while (true) {
                $loopCounter++;
                $this->logger?->debug("🔄 [Worker #{$workerId}] Ciclo #{$loopCounter} - Verificando shouldStop...");

                if ($this->shouldStop($controlChannel, $workerId)) {
                    $this->logger?->info("🛑 [Worker #{$workerId}] shouldStop() retornó true");
                    break;
                }

                $this->logger?->debug("🏥 [Worker #{$workerId}] Ejecutando health check...");
                $checkStart = microtime(true);
                $result = $this->performHealthChecks($workerId);
                $checkDuration = microtime(true) - $checkStart;

                // Actualizar estadísticas del worker
                $this->runningWorkers[$workerId]['last_check'] = time();
                $this->runningWorkers[$workerId]['cycle_count'] = $loopCounter;
                $this->runningWorkers[$workerId]['last_duration'] = $checkDuration;
                $this->runningWorkers[$workerId]['last_result'] = $result['overall_healthy'];

                // Manejar fallos consecutivos (PARTE CRUCIAL QUE FALTABA)
                if ($result['overall_healthy']) {
                    $this->runningWorkers[$workerId]['consecutive_failures'] = 0;
                    $this->runningWorkers[$workerId]['last_success'] = time();
                    $this->logger?->debug("🏥 [Worker #{$workerId}] Health check OK ({$checkDuration}s)");
                } else {
                    $this->runningWorkers[$workerId]['consecutive_failures']++;
                    $this->runningWorkers[$workerId]['last_failure'] = time();

                    $failures = $this->runningWorkers[$workerId]['consecutive_failures'];
                    $this->logger?->warning("🏥 [Worker #{$workerId}] Health check FAILED ({$failures} consecutivos)");

                    // Si hay muchos fallos consecutivos, intentar recuperación
                    if ($failures >= 3) {
                        $this->logger?->warning("🏥 [Worker #{$workerId}] 3+ fallos consecutivos, intentando recuperación...");
                        $this->handleConsecutiveFailures($workerId);
                    }
                }

                // Guardar en historial
                $lastCheck = $this->checkHistory[array_key_last($this->checkHistory)] ?? [];
                $newCheck = [
                    'worker_id' => $workerId,
                    'timestamp' => time(),
                    'duration' => $checkDuration,
                    'healthy' => $result['overall_healthy'],
                    'health_status' => $result['health_status'] ?? [],
                    'stats' => $result['pool_stats'] ?? []
                ];
                $this->addToHistory($newCheck);

                // Notificar cambios si hay notificador configurado
                if (isset($this->notifier)) {
                    $this->notifyIfChanges($lastCheck, $newCheck);
                }

                $this->logger?->debug("🏥 [Worker #{$workerId}] Esperando {$this->checkInterval}ms...");

                if (!$this->sleepWithStopCheck($this->checkInterval / 1000, $controlChannel, $workerId)) {
                    $this->logger?->info("⏸️ [Worker #{$workerId}] Sleep interrumpido");
                    break;
                }
            }
        } catch (\Throwable $e) {
            $this->logger?->error("💥 [Worker #{$workerId}] Error en health check loop: " . $e->getMessage());
            // Intentar una recuperación de emergencia ante errores críticos
            try {
                $this->logger?->warning("🏥 [Worker #{$workerId}] Intentando recuperación de emergencia...");
                $recovery = $this->retryPermanentFailures($workerId);
                $this->logger?->info("🏥 [Worker #{$workerId}] Recuperación de emergencia: " .
                    json_encode($recovery));
            } catch (\Throwable $recoveryError) {
                $this->logger?->error("🏥 [Worker #{$workerId}] Error en recuperación de emergencia: " .
                    $recoveryError->getMessage());
            }
        } finally {
            $this->logger?->info("✅ [Worker #{$workerId}] HealthCheckLoop FINALIZADO - Total ciclos: {$loopCounter}");
        }
    }
    /**
     * Sleep que puede ser interrumpido por señal de stop (versión para Swoole original)
     */
    private function sleepWithStopCheck(float $seconds, Channel $controlChannel): bool
    {
        $endTime = microtime(true) + $seconds;

        while (microtime(true) < $endTime && !$this->stopping) {
            $remaining = $endTime - microtime(true);
            $chunkTime = min(0.5, max(0.001, $remaining));

            // VERIFICAR PRIMERO SI HAY MENSAJE SIN BLOQUEAR
            $stats = $controlChannel->stats();

            if ($stats['queue_num'] > 0) {
                // Hay mensaje en el canal, leerlo
                $message = $controlChannel->pop(0.001);
                if ($message === 'stop' || $message === false) {
                    $this->logger?->debug("🏥 Recibido 'stop' durante sleep");
                    return false;
                }
            }

            // Si no hay mensaje, dormir en chunks pequeños
            // Pero usar sleep normal en lugar de pop() bloqueante
            Coroutine::sleep(min(0.1, $chunkTime));

            // Verificar flag stopping periódicamente
            if ($this->stopping) {
                $this->logger?->debug("🏥 Flag stopping activado durante sleep");
                return false;
            }
        }

        return true;
    }

    /**
     * Verifica si debemos detener el loop (versión para Swoole original)
     */
    /**
     * Verifica si debemos detener el loop (versión corregida)
     */
    private function shouldStop(Channel $controlChannel): bool
    {
        // DEBUG: Verificar estado actual
        $cid = Coroutine::getCid();
        $this->logger?->debug("🏥 Worker #{$this->getCurrentWorkerId()}: Verificando shouldStop. CID={$cid}, Stopping={$this->stopping}");

        // 1. Verificar bandera global de stopping
        if ($this->stopping) {
            $this->logger?->info("🏥 Worker #{$this->getCurrentWorkerId()}: Deteniendo por flag stopping=true");
            return true;
        }

        // 2. Verificar mensaje en el canal (NON-BLOCKING)
        try {
            // Verificar si hay algo en el canal sin bloquear
            $message = $controlChannel->pop(0.001); // Timeout muy corto

            if ($message === 'stop') {
                $this->logger?->info("🏥 Worker #{$this->getCurrentWorkerId()}: Recibido mensaje 'stop' en el canal");
                return true;
            }

            if ($message === false) {
                // Canal cerrado o timeout (pero timeout es muy corto, así que probablemente vacío)
                // No retornamos true aquí a menos que sepamos que el canal fue cerrado
                $stats = $controlChannel->stats();
                if ($stats['closed'] ?? false) {
                    $this->logger?->info("🏥 Worker #{$this->getCurrentWorkerId()}: Canal de control cerrado");
                    return true;
                }
            }

        } catch (\Throwable $e) {
            $this->logger?->error("🏥 Error verificando canal: " . $e->getMessage());
            // Si hay error con el canal, es mejor detener
            return true;
        }

        // 3. Condición adicional: si el worker ya no está en runningWorkers
        $currentWorkerId = $this->getCurrentWorkerId();
        if (!isset($this->runningWorkers[$currentWorkerId])) {
            $this->logger?->warning("🏥 Worker #{$currentWorkerId}: No encontrado en runningWorkers");
            return true;
        }

        if (($this->runningWorkers[$currentWorkerId]['status'] ?? '') === 'stopped') {
            $this->logger?->debug("🏥 Worker #{$currentWorkerId}: Estado marcado como stopped");
            return true;
        }

        return false;
    }

    /**
     * Obtiene el ID del worker actual (método auxiliar)
     */
    private function getCurrentWorkerId(): int
    {
        // Necesitas implementar esto basado en cómo trackeas el worker
        // Si no tienes forma de saberlo, puedes pasarlo como parámetro a shouldStop
        foreach ($this->workerCoroutineIds as $workerId => $coroutineId) {
            if ($coroutineId === Coroutine::getCid()) {
                return $workerId;
            }
        }
        return -1; // No encontrado
    }

    /**
     * Maneja fallos consecutivos
     */
    private function handleConsecutiveFailures(int $workerId): void
    {
        $failures = $this->runningWorkers[$workerId]['consecutive_failures'];
        $this->logger?->warning("🏥 Worker #{$workerId}: {$failures} fallos consecutivos detectados");

        // Intentar recuperar conexiones fallidas usando el método del Connector
        try {
            $recoveryResult = $this->connector->retryFailedConnections();

            if ($recoveryResult['recovered'] > 0) {
                $this->logger?->info("Worker #{$workerId}: Recuperadas {$recoveryResult['recovered']} conexiones");
                $this->runningWorkers[$workerId]['consecutive_failures'] = 0;
                $this->runningWorkers[$workerId]['recovery_attempts'] =
                    ($this->runningWorkers[$workerId]['recovery_attempts'] ?? 0) + 1;
            } else {
                $this->logger?->warning("🏥 Worker #{$workerId}: No se pudieron recuperar conexiones");
            }

            // Notificar al canal de control principal
            $this->notifyControlChannel('recovery_attempt', [
                'worker_id' => $workerId,
                'recovery_result' => $recoveryResult,
                'consecutive_failures' => $failures,
                'timestamp' => time()
            ]);

        } catch (\Exception $e) {
            $this->logger?->error("🏥 Worker #{$workerId}: Error en recuperación: " . $e->getMessage());
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
    public function stopHealthCheckCycle(int $timeout = 5): array
    {
        if ($this->stopping) {
            return ['status' => 'already_stopping'];
        }

        $this->logger?->info("🛑 Deteniendo health checks...");
        $this->stopping = true;

        $results = [];
        $startTime = microtime(true);

        foreach ($this->workerControlChannels as $workerId => $channel) {
            try {
                // Intentar múltiples veces el push
                $maxAttempts = 3;
                $pushSuccess = false;

                for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                    if ($channel->push('stop', 0.05)) { // Timeout más corto
                        $pushSuccess = true;
                        $results[$workerId] = 'stop_sent';
                        $this->logger?->debug("✅ Señal stop enviada a worker #{$workerId} (intento {$attempt})");
                        break;
                    }

                    $this->logger?->debug("⏳ Intento {$attempt} fallado para worker #{$workerId}, reintentando...");
                    Coroutine::sleep(0.01); // Pequeña pausa entre intentos
                }

                if (!$pushSuccess) {
                    $this->logger?->warning("❌ No se pudo enviar stop a worker #{$workerId} después de {$maxAttempts} intentos");
                    $results[$workerId] = 'failed';
                }

            } catch (\Exception $e) {
                $this->logger?->error("💥 Error con worker #{$workerId}: " . $e->getMessage());
                $results[$workerId] = 'error';
            }
        }

        // Esperar que terminen (con timeout)
        $elapsed = 0;
        while (!empty($this->workerControlChannels) && $elapsed < $timeout) {
            $elapsed = microtime(true) - $startTime;
            $remainingWorkers = count($this->workerControlChannels);

            if ($remainingWorkers > 0) {
                $this->logger?->debug("⏳ Esperando que {$remainingWorkers} workers terminen... ({$elapsed}s)");
                Coroutine::sleep(0.1);
            }
        }

        if (!empty($this->workerControlChannels)) {
            $this->logger?->warning("⏰ Timeout después de {$timeout}s, forzando cierre");
            $this->closeAllChannels();
        }

        $totalTime = microtime(true) - $startTime;
        $this->logger?->info(sprintf("✅ Health checks detenidos en %.2fs", $totalTime));

        return $results;
    }

    /**
     * Ejecuta los health checks
     */
    public function performHealthChecks(int $workerId = 0, bool $resetFailures = false): array
    {
        if ($resetFailures) {
            $retry = $this->retryPermanentFailures($workerId);
        }

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
            $this->logger?->error("🏥 Worker #{$workerId}: Error en health check: " . $e->getMessage());
            $results['error'] = $e->getMessage();
            $results['overall_healthy'] = false;
        }

        return $results;
    }

    /**
     * Retry de fallos permanentes usando el Connector
     */
    private function retryPermanentFailures(int $workerId = 0): array
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
            $this->logger?->error("🏥 Worker #{$workerId}: Error en retryPermanentFailures: " . $e->getMessage());
            return [
                'attempted' => 0,
                'recovered' => 0,
                'failed' => 1,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * @inheritDoc
     */
    public function registerNotifier(callable $notifier): void
    {
        $this->notifier = $notifier;
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

        $this->logger?->debug("🏥 Worker #{$workerId}: Recursos limpiados");
    }

    /**
     * Cierra todos los canales de control
     */
    private function closeAllChannels(): void
    {
        foreach ($this->workerControlChannels as $workerId => $channel) {
            try {
                $channel->close();
                $this->logger?->debug("🏥 Canal cerrado para worker #{$workerId}");
            } catch (\Exception $e) {
                $this->logger?->error("🏥 Error cerrando canal worker #{$workerId}: " . $e->getMessage());
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
            $this->logger?->warning("🏥 HealthManager destruido sin stopGracefully(), limpiando canales");
            $this->closeAllChannels();
        }
    }

    private function notifyIfChanges(mixed $lastCheck, array $result)
    {
        if (!$this->notifier || empty($result) || empty($lastCheck)) {
            return;
        }
        if ($lastCheck['health_status']['timestamp']) {
            $lastCheckTimestamp = $lastCheck['health_status']['timestamp'];
            unset($lastCheck['health_status']['timestamp']);
        }
        if ($result['health_status']['timestamp']) {
            $resultTimestamp = $result['health_status']['timestamp'];
            unset($result['health_status']['timestamp']);
        }
        $notif = $this->notifier;
        // REVISAR, son diferentes los arrays!
        if ($lastCheck['overall_healthy'] !== $result['overall_healthy'] || $lastCheck['health_status'] !== $result['health_status']) {

            /**
             * health_status:
             *  active_pools: 5
             *  loaded_connections: 5
             *  permanent_failures: 0
             *  pool_groups: ['sqldev', 'sqlsj', 'sqlpch', 'sqltuc', 'sigmydevsj']
             *  unreachable_connections: 1
             */
            if ($result['health_status']['active_pools'] > $lastCheck['health_status']['active_pools']) {
                $notif([
                    'type' => 'pool_recovered',
                    'data' => $result['health_status'],
                    'lastChecked' => $lastCheckTimestamp ?? null,
                    'currentChecked' => $resultTimestamp ?? time()
                ]);
            }
        }
    }

}