<?php

namespace Tabula17\Satelles\Omnia\Roga\Descriptor;

use Tabula17\Satelles\Omnia\Roga\Collection\JoinCollection;

class SelectDescriptor extends StatementDescriptor
{
    protected(set) bool $distinct = false;
    protected(set) bool $all = false;
    protected(set) int $top = 0;
    protected(set) bool $forUpdate = false;
    protected(set) bool $forShare = false;
    protected(set) bool $forNoKeyUpdate = false;
    protected(set) bool $forKeyShare = false;
    protected(set) bool $forKeyNoShare = false;
    protected(set) bool $forNoKey = false;
    protected(set) bool $forKey = false;
    protected(set) bool $forRead = false;
    protected(set) bool $forWrite = false;
    protected(set) bool $forAppend = false;
    protected(set) bool $forCheck = false;
    protected(set) bool $forReference = false;
    protected(set) bool $forShareUpdate = false;
    protected(set) bool $forShareRead = false;
    protected(set) bool $forShareNoKeyUpdate = false;
    protected(set) bool $forShareKeyShare = false;
    protected(set) bool $forShareKeyNoShare = false;
    protected(set) bool $forShareNoKey = false;
    protected(set) bool $forShareKey = false;

    protected(set) TableDescriptor $from {
        set(array|TableDescriptor $value) {
            if (is_array($value)) {
                $this->from = new TableDescriptor($value);
            } elseif ($value instanceof TableDescriptor) {
                $this->from = $value;
            }
        }
    }
    protected(set) JoinCollection $joins {
        set(array|JoinCollection|null $value) {
            if (is_array($value)) {
                $params = [];
                foreach ($value as $param) {
                    if ($param instanceof JoinDescriptor) {
                        $params[] = $param;
                    } else {
                        $params[] = new JoinDescriptor($param);
                    }
                }
                $this->joins = new JoinCollection(...$params);
            } elseif ($value instanceof JoinCollection) {
                $this->joins = $value;
            }
        }
    }
    protected(set) int $offset;
    protected(set) int $limit;
}