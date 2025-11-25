<?php

namespace Tabula17\Satelles\Omnia\Roga\Descriptor;

use Tabula17\Satelles\Omnia\Roga\Collection\ParamCollection;

class ExecuteDescriptor extends StatementDescriptor
{
    protected(set) bool $return = false;
    protected(set) bool $namedArguments = false;
    protected(set) string $execKeyword = 'exec';
    protected(set) string $prefixVariable = '';
    protected(set) bool $argListSurrounded = false;
    protected(set) string|array $procedure;
    protected(set) ?ParamCollection $arguments {
        set(array|ParamCollection|null $value) {

            if (is_array($value)) {
                $params = [];
                foreach ($value as $param) {
                    if ($param instanceof ParamDescriptor) {
                        $params[] = $param;
                    } else {
                        $params[] = new ParamDescriptor($param);
                    }
                }
                $this->arguments = new ParamCollection(...$params);
            } elseif ($value instanceof ParamCollection) {
                $this->arguments = $value;
            }
        }
    }
    protected(set) ?bool $quoteIdentifier;

}