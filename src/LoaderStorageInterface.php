<?php

namespace Tabula17\Satelles\Omnia\Roga;

interface LoaderStorageInterface
{
    public function listAvailableStatements(): array;

    public function getLoader(bool $withCache = false): LoaderInterface;

    public function clearCache(): void;

    public function clearStatementCache(string $name): void;

    public function getStatementInfo(string $name): array;

    public function compareFromCache(string $name): bool;
}