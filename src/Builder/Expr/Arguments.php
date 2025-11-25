<?php

namespace Tabula17\Satelles\Omnia\Roga\Builder\Expr;

use Tabula17\Satelles\Omnia\Roga\Builder\Param;

class Arguments extends Base
{
    public bool $surrounded = false;
    protected array $allowedClasses = [
        self::class,
        Param::class,
        Func::class,
        Composite::class,
    ];
    protected array $prettyChars = ['    ', "\n    ", ''];
    /**
     * @return string
     */
    public function __toString()
    {
        if ($this->count() === 1) {
            return (string)$this->parts[0];
        }

        $components = array();

        foreach ($this->parts as $part) {
            $components[] = $this->processQueryPart($part);
        }
        if (!$this->surrounded) {
            $this->preSeparator = '';
            $this->postSeparator = '';
        }
        if ($this->prettyPrint) {
            $this->preSeparator = ($this->prettyChars[0] ?? '') . $this->preSeparator;
            $this->separator .= $this->prettyChars[1] ?? '';
            $this->postSeparator .= ($this->prettyChars[2] ?? '');
        }
        return $this->preSeparator . implode($this->separator, $components) . $this->postSeparator;
    }

    /**
     * @param string|object|self $part
     *
     * @return string
     */
    private function processQueryPart($part): string
    {
        $queryPart = (string)$part;

        if ($part instanceof self && $part->count() > 1) {
            return $this->preSeparator . $queryPart . $this->postSeparator;
        }

        // Fixes DDC-1237: User may have added a where item containing a nested expression (with "OR" or "AND")
        if (stripos($queryPart, ' OR ') !== false || stripos($queryPart, ' AND ') !== false) {
            return $this->preSeparator . $queryPart . $this->postSeparator;
        }

        return $queryPart;
    }

}