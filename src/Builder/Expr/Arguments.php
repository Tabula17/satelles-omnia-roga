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
    public function __toString(): string
    {
        if ($this->count() === 0) {
            return '';
        }

        $components = [];
        foreach ($this->parts as $part) {
            $components[] = $this->processQueryPart($part);
        }

        $pre = $this->surrounded ? $this->preSeparator : '';
        $post = $this->surrounded ? $this->postSeparator : '';
        $separator = $this->separator;
        if ($this->prettyPrint) {
            $pre = ($this->prettyChars[0] ?? '') . $pre;
            $separator .= $this->prettyChars[1] ?? '';
            $post .= $this->prettyChars[2] ?? '';
        }
        return $pre . implode($separator, $components) . $post;
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