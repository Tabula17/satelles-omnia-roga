<?php

namespace Tabula17\Satelles\Omnia\Roga;

use Tabula17\Satelles\Omnia\Roga\Collection\StatementCollection;

interface LoaderInterface
{
    public function getStatementDescriptors(string $name, bool $forceReload = false): ?array;

    /**
     * @throws \JsonException
     */
    public function getStatementCollection(string $name, bool $forceReload = false): ?StatementCollection;
}