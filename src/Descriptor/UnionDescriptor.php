<?php

namespace Tabula17\Satelles\Omnia\Roga\Descriptor;

use Tabula17\Satelles\Omnia\Roga\Collection\StatementCollection;

class UnionDescriptor extends StatementDescriptor
{
    protected(set) ?bool $unionAll = null {
        set (bool|string|null $value) {
            if(is_numeric($value)){
                $value = (bool)$value;
            }
            if(is_string($value)){
                $positives = ['ALL', '*', '1',  'TRUE', 'YES', 'SI'];
                $value = in_array(strtoupper($value), $positives);
            }

            $this->unionAll = $value;
        }
    }
    protected(set) StatementCollection $unions {
        set(array|StatementCollection|null $value) {

            //echo $value instanceof StatementCollection ? 'true' : 'false';
            if (is_array($value)) {
                $params = [];
                foreach ($value as $param) {
                    //  echo $param instanceof SelectDescriptor ? 'true' : 'false', PHP_EOL;
                    if ($param instanceof SelectDescriptor) {
                        $params[] = $param;
                    } else if (isset($param['subquery'])) {
                        //$param = new SubqueryDescriptor($param['subqueriesForPath']);
                        $params[] = new SelectDescriptor($param['subquery']['descriptor']);;
                    } else {
                        $params[] = new SelectDescriptor($param);
                    }
                }
                $this->unions = new StatementCollection(...$params);
            } elseif ($value instanceof StatementCollection) {
                $this->unions = $value;
            }
        }
    }

}