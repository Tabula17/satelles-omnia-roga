<?php

namespace Tabula17\Satelles\Omnia\Roga\Database;

use Psr\Log\LoggerInterface;
use Tabula17\Satelles\Omnia\Roga\LoaderInterface;
use Tabula17\Satelles\Utilis\Exception\InvalidArgumentException;

class Server extends \Swoole\Server
{

    public ?LoggerInterface $logger;
    public LoaderInterface $loader;
    public ConnectionConfigCollection $poolCollection;
    public Connector $connector;

    public function __construct(
        Connector                  $connector,
        ConnectionConfigCollection $poolCollection,
        LoaderInterface            $loader,
        string                     $host = '0.0.0.0',
        int                        $port = 0,
        int                        $mode = SWOOLE_BASE,
        int                        $sock_type = SWOOLE_SOCK_TCP,
        ?LoggerInterface           $logger = null

    )
    {
        $this->connector = $connector;
        $this->poolCollection = $poolCollection;
        $this->loader = $loader;
        $this->logger = $logger;
        parent::__construct($host, $port, $mode, $sock_type);
        $this->on('workerStart', [$this, 'init']);
    }

    /**
     * Starts the TCP server and initializes connections.
     *
     * This method performs the necessary logging and loads connections
     * into the pool collection. It processes the pool groups and logs
     * their readiness status. If there are any unreachable connections,
     * they are logged along with any associated errors.
     *
     * @throws InvalidArgumentException
     */
    public function init(): void
    {
        $this->logger->info("Iniciando servidor TCP en {$this->host}:{$this->port}");
        $this->connector->loadConnections($this->poolCollection);
        foreach ($this->connector->getPoolGroupNames() as $poolName) {
            $this->logger->info("$poolName READY: " . $this->connector->getPoolCount($poolName) . ' pools >> ' . implode(', ', $this->connector->getPoolNamesForGroup($poolName)));
        }
        if ($this->connector->getUnreachableConnections()->count() > 0) {
            $this->logger->error("Unreachable connections: " . implode(', ', $this->connector->getUnreachableConnections()->collect('name')));
            foreach ($this->connector->getUnreachableConnections() as $conn) {
                if (isset($conn->lastConnectionError)) {
                    $this->logger->error($conn->name . ' -> ' . $conn->lastConnectionError);
                }
            }
        }
        //return parent::start();
    }
}