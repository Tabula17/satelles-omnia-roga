<?php

namespace Tabula17\Satelles\Omnia\Roga\Builder;

use Tabula17\Satelles\Omnia\Roga\Builder\Expr\Arguments;
use Tabula17\Satelles\Omnia\Roga\Builder\Expr\From;
use Tabula17\Satelles\Omnia\Roga\Descriptor\ExecuteDescriptor;
use Tabula17\Satelles\Omnia\Roga\Exception\ConfigException;

class ExecuteStatement implements StatementProcessorInterface
{
    private From $procedure;
    private Arguments $body;

    private(set) array $values = [];
    private(set) array $params = [];

    private(set) bool $namedArguments = false;
    private Keywords $execKeyword;
    private string $prefixVariable;
    private bool $surroundedArgs;

    public bool $prettyPrint = false;

    private(set) string $statementId;
    private(set) array $bindings = [];

    /**
     * @throws ConfigException
     */
    public function __construct(
        private readonly ExecuteDescriptor $statementParts,
        private readonly Expression        $expression = new Expression()
    )
    {
        $this->body = new Arguments();
        $this->statementId = uniqid('exec::', false);
        $this->namedArguments = $statementParts->namedArguments ?? false;
        $this->execKeyword = Keywords::fromName($statementParts->execKeyword) ?? Keywords::EXECUTE;
        $this->prefixVariable = $statementParts->prefixVariable ?? '';
        $this->surroundedArgs = $statementParts->argListSurrounded ?? true;
        $this->setProcedure()->setArguments();
    }


    private function setProcedure(): ExecuteStatement
    {
        if (isset($this->statementParts['procedure'])) {
            $this->procedure = new From(is_array($this->statementParts['procedure']) ? implode('.', $this->statementParts['procedure']) : $this->statementParts['procedure']);
        }
        return $this;
    }

    /**
     * @throws ConfigException
     */
    private function setArguments(): void
    {
        if (isset($this->statementParts['arguments'])) {
            foreach ($this->statementParts['arguments'] as $argument) {
                $param = new Param(
                    descriptor: $argument,
                    expression: $this->expression,
                    pre_procedure_variable: $this->prefixVariable
                );
                $this->params[$param->placeholder] = $param;
            }
        }
    }

    public function getRequiredParams(): array
    {
        $params = [];
        foreach ($this->params as $param) {
            if ($param->required === true) {
                $params[] = $param;
            }
        }
        return $params;
    }

    public function getOptionalParams(): array
    {
        $params = [];
        foreach ($this->params as $param) {
            if (!$param->required) {
                $params[] = $param;
            }
        }
        return $params;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function getBindings(): array
    {
        return $this->bindings;
    }

    public function setValues(array $values): ExecuteStatement
    {
        foreach ($values as $placeholder => $value) {
            $this->setValue($placeholder, $value);
        }
        return $this;
    }

    public function setValue(string $placeholder, mixed $value): ExecuteStatement
    {
        if (!str_starts_with($placeholder, ':')) {
            $placeholder = ':' . $placeholder;
        }
        $this->values[$placeholder] = $value;
        if (isset($this->params[$placeholder])) {
            $this->params[$placeholder]->setValue($value);
            if ($this->params[$placeholder]->bindable) {
                $this->bindings[$placeholder] = $this->params[$placeholder]->getValue();
            }
        }
        return $this;
    }

    public function getValue(string $placeholder): mixed
    {
        return $this->values[$placeholder] ?? null;
    }

    public function removeValue(string $placeholder): ExecuteStatement
    {
        unset($this->values[$placeholder], $this->bindings[$placeholder]);
        return $this;
    }

    public function process(): ExecuteStatement
    {
        foreach ($this->params as $argument) {
            if (!isset($this->params[$argument->placeholder])) {
                $this->params[$argument->placeholder] = $argument;
            }
            if (isset($this->values[$argument->placeholder]) || $argument->required === true) {
                $this->setValue($argument->placeholder, $this->values[$argument->placeholder] ?? $argument->defaultValue);
                $this->body->add($this->namedArguments ? $argument : $argument->placeholder);
            }
        }
        return $this;
    }

    public function getValues(): array
    {
        return $this->values;
    }

    public function hasValue(string $placeholder): bool
    {
        return isset($this->values[$placeholder]);
    }

    public function __toString(): string
    {
        $this->process();
        $prettyChar = $this->prettyPrint ? "\n" : '';
        $this->body->prettyPrint = $this->prettyPrint;
        $this->body->surrounded = $this->surroundedArgs;
        return $this->execKeyword->value . ' ' . $prettyChar . $this->procedure . $prettyChar . $this->body;
    }

    public function getParam(string $placeholder): ?Param
    {
        return $this->params[$placeholder];
    }
}