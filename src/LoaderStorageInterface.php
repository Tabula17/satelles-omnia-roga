<?php

namespace Tabula17\Satelles\Omnia\Roga;

interface LoaderStorageInterface
{
    public function listAvailableStatements(): array;
    public function getLoader(): LoaderInterface;
    public function clearCache(): void;
    public function getStatementInfo(string $name): array;
}