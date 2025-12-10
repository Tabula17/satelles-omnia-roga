<?php

namespace Tabula17\Satelles\Omnia\Roga\Database\Pool;

use Swoole\ConnectionPool;

class ConnectionPoolExtended extends ConnectionPool
{
    public function available(): int
    {
        return $this->pool?->length() ?? 0;
    }

    public function isFull(): bool
    {
        return $this->pool?->isFull() ?? false;
    }

    public function isEmpty(): bool
    {
        return $this->pool?->isEmpty() ?? true;
    }

    public function stats(): array
    {
        return $this->pool?->stats() ?? [
            'consumer_num' => '0',
            'producer_num' => '0',
            'queue_length' => '0'
        ];
    }

    public function size()
    {
        return $this->pool?->capacity ?? 0;

    }

    public function isDestroyed(): bool
    {
        return !$this->pool;
    }
}