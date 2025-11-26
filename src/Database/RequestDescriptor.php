<?php

namespace Tabula17\Satelles\Omnia\Roga\Database;

use Tabula17\Satelles\Omnia\Roga\Descriptor\StatementDescriptor;
use Tabula17\Satelles\Utilis\Config\AbstractDescriptor;

class RequestDescriptor extends AbstractDescriptor
{
    protected(set) ?string $cfg;
    protected(set) int|string $variant;
    protected(set) int|string $allowed;
    protected(set) int|string $client;
    protected(set) array $params = [];

    /**
     * @throws \JsonException
     */
    public function __construct(array|string|null $values = [])
    {
        if (is_string($values)) {
            $values = json_validate($values) ? json_decode($values, true, 512, JSON_THROW_ON_ERROR) : ['cfg' => $values];
        }
        parent::__construct($values);
    }

    public function getFor(): array
    {
        if (isset($this->variant)) {
            return ['variant', $this->variant];
        }
        if (isset($this->allowed)) {
            return ['allowed', $this->allowed];
        }
        if (isset($this->client)) {
            return ['client', $this->client];
        }
        return [];
    }

}