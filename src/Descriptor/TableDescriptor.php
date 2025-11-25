<?php

namespace Tabula17\Satelles\Omnia\Roga\Descriptor;

use Tabula17\Satelles\Omnia\Roga\Collection\ColumnCollection;
use Tabula17\Satelles\Utilis\Config\AbstractDescriptor;

class TableDescriptor extends AbstractDescriptor
{
    protected(set) string $name {
        set(string|array $value) {
            $this->name = is_array($value) ? implode('.', $value) : $value;
        }
    }
    protected(set) ?string $table {
        set(string|array|null $value) {
            if ($value !== null) {
                $this->name = is_array($value) ? implode('.', $value) : $value;
            }
        }
        get {
            return $this->name ?? null;
        }
    }
    protected(set) ?string $alias;
    protected(set) SubqueryDescriptor $subquery {
        set(array|SubqueryDescriptor $value) {
            if (is_array($value)) {
               // echo 'GENERATE SUBQUERY ---> ', var_export($value, true), PHP_EOL;
                $this->subquery = new SubqueryDescriptor($value);
            } elseif ($value instanceof SubqueryDescriptor) {
                $this->subquery = $value;
            }
        }
    }
    protected(set) ?ColumnCollection $columns {
        set(array|ColumnCollection|null $value) {

            if (is_array($value)) {
                $params = [];
                foreach ($value as $param) {
                    if ($param instanceof ColumnDescriptor) {
                        $params[] = $param;
                    } else {
                        $params[] = new ColumnDescriptor($param);
                    }
                }
                $this->columns = new ColumnCollection(...$params);
            } elseif ($value instanceof ColumnCollection) {
                $this->columns = $value;
            }
        }
    }
    protected(set) ?bool $quoteIdentifier;
}