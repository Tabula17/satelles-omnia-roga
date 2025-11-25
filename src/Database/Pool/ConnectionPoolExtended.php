<?php

namespace Tabula17\Satelles\Omnia\Roga\Database\Pool;

use Swoole\ConnectionPool;

class ConnectionPoolExtended extends ConnectionPool
{
    public function available(): int
    {
        return $this->pool->length();
    }

    public function isFull(): bool
    {
        return $this->pool->isFull();
    }

    public function isEmpty(): bool
    {
        return $this->pool->isEmpty();
    }

    public function stats(): array
    {
        return $this->pool->stats();
    }

    public function size()
    {
        return $this->pool->capacity;

    }

}