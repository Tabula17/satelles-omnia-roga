<?php

namespace Tabula17\Satelles\Omnia\Roga\Builder;

/**
 * Shared placeholder normalization and binding registration for statements.
 *
 * The consuming class must declare a writable array property named $bindings.
 */
trait BindingsTrait
{
    private function normalizePlaceholder(string $placeholder): string
    {
        return str_starts_with($placeholder, ':') ? $placeholder : ':' . $placeholder;
    }

    private function removeExpandedBindings(string $placeholder): void
    {
        unset($this->bindings[$placeholder]);
        foreach (array_keys($this->bindings) as $key) {
            if (is_string($key) && str_starts_with($key, $placeholder) &&
                ctype_digit(substr($key, strlen($placeholder)))) {
                unset($this->bindings[$key]);
            }
        }
    }

    /**
     * Registers one binding per generated placeholder. IN(:ids0, :ids1, ...)
     * needs :ids0 and :ids1, not a single :ids => [..] entry.
     */
    private function registerBinding(string $placeholder, Paraminterface $param): void
    {
        $this->removeExpandedBindings($placeholder);
        if ($param instanceof Param && !$param->isBindablePlaceholder()) {
            return;
        }
        $value = $param->getValue();
        if (is_array($value)) {
            foreach (array_values($value) as $index => $item) {
                $this->bindings[$placeholder . $index] = $item;
            }
            return;
        }
        $this->bindings[$placeholder] = $value;
    }
}
