<?php

namespace Tabula17\Satelles\Omnia\Roga\Database;

use Psr\Log\LoggerInterface;
use Tabula17\Satelles\Omnia\Roga\LoaderInterface;

class Server extends \Swoole\Server
{

    public ?LoggerInterface $logger;
    public LoaderInterface $loader;
    public ConnectionConfigCollection $poolCollection;
    public Connector $connector;

    public function __construct(
        Connector $connector,
        ConnectionConfigCollection $poolCollection,
        LoaderInterface $loader,
        string $host = '0.0.0.0',
        int $port = 0,
        int $mode = SWOOLE_BASE,
        int $sock_type = SWOOLE_SOCK_TCP,
        ?LoggerInterface $logger = null

    )
    {
        $this->connector = $connector;
        $this->poolCollection = $poolCollection;
        $this->loader = $loader;
        $this->logger = $logger;
        parent::__construct($host, $port, $mode, $sock_type);
    }
}