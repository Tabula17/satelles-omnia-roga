<?php

namespace Tabula17\Satelles\Omnia\Roga\Database;

use JsonException;
use PDO;
use Psr\Log\LoggerInterface;
use Tabula17\Satelles\Omnia\Roga\Exception\ConfigException;
use Tabula17\Satelles\Omnia\Roga\Exception\ConnectionException;
use Tabula17\Satelles\Omnia\Roga\Exception\ExceptionDefinitions;
use Tabula17\Satelles\Omnia\Roga\Exception\StatementExecutionException;
use Tabula17\Satelles\Omnia\Roga\LoaderInterface;
use Tabula17\Satelles\Omnia\Roga\StatementBuilder;
use Tabula17\Satelles\Utilis\Exception\InvalidArgumentException;
use Tabula17\Satelles\Utilis\Middleware\TCPmTLSAuthMiddleware;

class Server extends \Swoole\Server
{

    private array $privateEvents = ['workerStart', 'receive'];

    public function __construct(
        public Connector                        $connector,
        public ConnectionConfigCollection       $poolCollection,
        public LoaderInterface                  $loader,
        string                                  $host = '0.0.0.0',
        int                                     $port = 0,
        int                                     $mode = SWOOLE_BASE,
        int                                     $sock_type = SWOOLE_SOCK_TCP,
        public ?LoggerInterface                 $logger = null,
        private readonly ?LoggerInterface       $db_logger = null,
        private readonly ?TCPmTLSAuthMiddleware $mtlsMiddleware = null

    )
    {
        parent::__construct($host, $port, $mode, $sock_type);
        $this->onPrivateEvent('workerStart', [$this, 'init']);
        $this->onPrivateEvent('receive', [$this, 'process']);
    }

    public function start(): bool
    {
        $this->logger?->info("Iniciando servidor TCP en {$this->host}:{$this->port}");
        return parent::start();
    }

    public function on(string $event_name, callable $callback): bool
    {
        if (in_array($event_name, $this->privateEvents, true)) {
            $this->logger?->warning("Evento privado $event_name no permitido");
            return false;
        }
        return parent::on($event_name, $callback);
    }

    private function onPrivateEvent(string $event_name, callable $callback): void
    {
        parent::on($event_name, $callback);
    }

    /**
     * Initializes the TCP server and loads the connection pools.
     *
     * This method logs the status of the TCP server, loads the connections
     * from the pool collection, and outputs the readiness status of each
     * connection pool group. It also handles and logs unreachable connections
     * and their corresponding errors if any are detected.
     *
     * @param Server $server The server instance that is being initialized.
     * @param int $workerId The unique identifier of the worker process.
     * @return void
     * @throws InvalidArgumentException
     */
    public function init(Server $server, int $workerId): void
    {
        $server->logger?->info("Iniciando POOL de conexiones en worker #{$workerId}");
        $server->connector->loadConnections($server->poolCollection);
        foreach ($server->connector->getPoolGroupNames() as $poolName) {
            $server->logger?->info("Worker #{$workerId}:  $poolName READY: " . $server->connector->getPoolCount($poolName) . ' pools >> ' . implode(', ', $server->connector->getPoolNamesForGroup($poolName)));
        }
        if ($server->connector->getUnreachableConnections()->count() > 0) {
            $server->logger?->error("Unreachable connections: " . implode(', ', $server->connector->getUnreachableConnections()->collect('name')));
            foreach ($server->connector->getUnreachableConnections() as $conn) {
                if (isset($conn->lastConnectionError)) {
                    $server->logger?->notice($conn->name . ' -> ' . $conn->lastConnectionError);
                }
            }
        }
        $status = $server->getWorkerStatus($workerId);
        $server->logger?->info("Worker #{$workerId} status: " . $status);
        //return parent::start();
    }

    /**
     * Envía una respuesta al cliente
     *
     * @param Server $server
     * @param int $fd Descriptor de archivo del cliente
     * @param array $response Datos de la respuesta
     * @return void
     * @throws JsonException
     */
    private function sendResponse(Server $server, int $fd, array $response): void
    {
        $server->send($fd, json_encode($response, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_INVALID_UTF8_IGNORE) . "\n");
    }

    /**
     * Envía un mensaje de error al cliente
     *
     * @param Server $server
     * @param int $fd Descriptor de archivo del cliente
     * @param string $message Mensaje de error
     * @return void
     */
    private function sendError(Server $server, int $fd, string $message): void
    {
        try {
            $server->sendResponse($server, $fd, [
                'status' => 'error',
                'message' => $message
            ]);
        } catch (\Throwable $e) {
            $server->logger?->error($e->getMessage());
        } finally {
            $server->logger?->error($message);
        }
    }

    /**
     * Processes an incoming request, builds and executes a database statement
     * based on the provided data, and sends a response back with the results.
     *
     * @param int $fd The file descriptor representing the incoming client connection.
     * @param string $data The raw data received from the client, expected to be in a specific format.
     * @return void This method does not return a value but sends a response back to the client.
     * @throws ConfigException
     * @throws JsonException
     * @throws ConnectionException
     * @throws StatementExecutionException
     * @throws \Throwable
     */
    private function processRequest(Server $server, int $fd, string $data): void
    {
        $request = new RequestDescriptor($data);
        $builder = new StatementBuilder(
            statementName: $request->cfg,
            loader: $server->loader,
            reload: false
        );
        $identifier = $request->getFor();
        $server->logger?->debug('Buscando statement para ' . implode(': ', $identifier));
        $builder->loadStatementBy(...$identifier)?->setValues($request->params ?? []);
        $server->logger?->debug('Buscando conexión para ' . $builder->getMetadataValue('connection'));
        /** @var PDO $conn */
        $conn = $server->connector->getConnection($builder->getMetadataValue('connection'));
        if (!isset($conn)) {
            throw new ConnectionException(sprintf(ExceptionDefinitions::POOL_NOT_FOUND->value, $builder->getMetadataValue('connection')), 500);
        }
        try {
            $stmt = $conn->prepare($builder->getStatement());
            foreach ($builder->getBindings() as $key => $value) {
                $stmt->bindParam($key, $value, $builder->getParamType($key)); //bindValue($key, $value);
            }
            $stmt->setFetchMode(PDO::FETCH_ASSOC);
            try {
                $stmt->execute();
            } catch (\Throwable $e) {
                $server->db_logger?->error($builder->getPrettyStatement(), [
                    'error' => $e->getMessage(),
                    'connection' => $server->connector->getPoolStats($builder->getMetadataValue('connection')),
                    'bindings' => $builder->getBindings(),
                    'builderParams' => $builder->getParams() ?? [],
                    'request' => $request->toArray()
                ]);
                throw new StatementExecutionException($e->getMessage(), 500, $e);
            }
            $result = $stmt->fetchAll();
            $server->db_logger?->info($builder->getPrettyStatement(), [
                    'connection' => $server->connector->getPoolStats($builder->getMetadataValue('connection')),
                    'bindings' => $builder->getBindings(),
                    'requiredParams' => $builder->getRequiredParams() ?? [],
                    'request' => $request->toArray(),
                    'total' => count($result)
                ]
            );
            $server->sendResponse($server, $fd, [
                    'data' => $result,
                    'status' => 200,
                    'message' => $builder->getMetadataValue('operation') . ': OK',
                    'total' => count($result)
                ]
            );
        } finally {
            $server->connector->putConnection($conn);
        }
    }

    public function process(Server $server, int $fd, int $reactorId, string $data): void
    {
        $server->logger?->debug("Procesado request de $fd en proceso > #$reactorId");
        if ($this->mtlsMiddleware !== null) {
            $this->mtlsMiddleware->handle($server, $fd, $data, function ($server, $context) {
                $server->logger?->debug("Autorización SSL exitosa. Procesado request de {$context['fd']} en proceso > #{$context['reactor_id']}");
                $this->doProcess($server, $context['fd'], $context['data']);
            });
        } else {
            $this->doProcess($server, $fd, $data);
        }
    }

    /**
     * Processes a request received from the server, delegating it to the appropriate handler
     * and managing errors if any exception occurs.
     *
     * @param Server $server The server instance handling the request.
     * @param int $fd The file descriptor representing the connection.
     * @param string $data The data received from the client.
     *
     * @return void
     */
    private function doProcess(Server $server, int $fd, string $data): void
    {

        try {
            $this->processRequest($server, $fd, $data);
        } catch (\Throwable $e) {
            $this->sendError($server, $fd, $e->getMessage());
        }
    }
}