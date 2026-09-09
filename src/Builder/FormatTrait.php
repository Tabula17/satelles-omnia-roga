<?php

namespace Tabula17\Satelles\Omnia\Roga\Builder;

trait FormatTrait
{

    private function quoteIdentifier(string|array $value, ?QuotesEnum $quote_identifier = null): string
    {
        if (is_null($quote_identifier) || $quote_identifier->isQuoted($value)) {
            return $value;
        }
        $quoter = static function ($value) use ($quote_identifier) {
           return $quote_identifier->quote($value);
        };

        if (is_array($value) || str_contains($value, '.')) {
            $parts = is_array($value) ? $value: explode('.', $value);
            $parts = array_map($quoter(...), $parts);
            return implode('.', $parts);
        }
        return $quoter($value);
    }
}