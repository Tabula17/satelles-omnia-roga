<?php

namespace Tabula17\Satelles\Omnia\Roga\Database;

use Swoole\Database\PDOPool;
use Tabula17\Satelles\Utilis\Collection\GenericCollection;

class PoolCollection extends GenericCollection
{
    protected string $type = PDOPool::class;
    protected string $poolId;

    public function __construct(PDOPool ...$pdoPool)
    {
        $this->values = $pdoPool;
        $this->poolId = uniqid('pool:pdo:', false);
    }
}