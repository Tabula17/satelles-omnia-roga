<?php

namespace Tabula17\Satelles\Omnia\Roga\Descriptor;

use Tabula17\Satelles\Omnia\Roga\Collection\ParamCollection;

class InsertDescriptor extends StatementDescriptor
{
    /*protected(set) TableDescriptor $table {
        set(array|TableDescriptor $value) {
            if(!($value instanceof TableDescriptor)){
                $value = new TableDescriptor(['name' => $value]);
            }
            $this->table = $value;
        }
    }*/
    protected(set) TableDescriptor $into {
        set(array|TableDescriptor $value) {
            if (is_array($value)) {
                $this->into = new TableDescriptor($value);
            } elseif ($value instanceof TableDescriptor) {
                $this->into = $value;
            }
        }
    }
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
    protected(set) ?StatementDescriptor $select {
        set(array|StatementDescriptor|null $value) {
            if ($value instanceof SelectDescriptor) {
                $this->select = $value;
            } else if (is_array($value) && isset($value['subquery'])) {
                if(!isset($value['subquery']['descriptor'])){
                    var_dump($value);
                   // throw new \Exception('Subquery descriptor not found');
                }else{
                    $this->select = new SelectDescriptor($value['subquery']['descriptor']);
                }
            } else {
                $this->select = new SelectDescriptor($value);
            }
        }
    }
    /*
     *  if ($value instanceof SelectDescriptor) {
                        $this->select = $value;
                    } else if(isset($value['subqueriesForPath'])){
                        $this->select = new SelectDescriptor($value['subqueriesForPath']['descriptor']);;
                    }else {
                        $this->select = new SelectDescriptor($value);
                    }
     */
}