<?php

namespace Tabula17\Satelles\Omnia\Roga\Database;

use Tabula17\Satelles\Omnia\Roga\Descriptor\StatementDescriptor;
use Tabula17\Satelles\Utilis\Config\AbstractDescriptor;

class RequestDescriptor extends AbstractDescriptor
{
    protected(set) ?string $cfg;
    protected(set) string $variant = 'default';
    protected(set) int|float|string $allowed;
    protected(set) int|float|string $client;
    protected(set) array $params = [];
    protected(set) ?string $version = 'latest';
    protected(set) ?bool $forceReload = false;

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
        if (!$this->isValid()) {
            return [];
        }
        // match arguments StatementBuilder::loadStatementBy(string $member, mixed $value, string $version = 'latest', ?string $variant = 'default'): self
        return [
            'member' => isset($this->allowed) ? 'allowed' : 'client',
            'value' => $this->allowed ?? $this->client,
            'version' => $this->version ?? 'latest',
            'variant' => $this->variant ?? 'default'
        ];
    }

    /**
     * Checks if the current object is valid based on its properties and conditions.
     *
     * @return bool Returns true if the required properties are set and the identifier condition is met, otherwise false.
     */
    public function isValid(): bool
    {
        $identifier = ($this->allowed || $this->client);
        return isset($this->cfg, $this->variant, $identifier, $this->version);
    }
}