<?php

namespace Tabula17\Satelles\Omnia\Roga\Builder;

use \Stringable;

interface StatementProcessorInterface extends Stringable
{
    public function getRequiredParams(): array;

    public function getOptionalParams(): array;

    public function getParams(): array;
    public function getParam(string $placeholder): ?Paraminterface;

    public function setValues(array $values): StatementProcessorInterface;

    public function setValue(string $placeholder, mixed $value): StatementProcessorInterface;

    public function getValue(string $placeholder): mixed;
    public function getValues(): array;
    public function getBindings(): array;
    public function hasValue(string $placeholder): bool;

    public function removeValue(string $placeholder): StatementProcessorInterface;

    public function process(): StatementProcessorInterface;
}