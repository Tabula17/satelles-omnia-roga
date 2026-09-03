<?php

namespace Tabula17\Satelles\Omnia\Roga\Collection;

use Tabula17\Satelles\Omnia\Roga\Builder\TypeOperations;
use Tabula17\Satelles\Utilis\Collection\TypedEnumCollection;

class TypeOperationsCollection extends TypedEnumCollection
{

    protected static function getEnumType(): string
    {
        return TypeOperations::class;
    }
    public function canHaveResultset():bool
    {
        return $this->contains(TypeOperations::SELECT);
    }
    public function canModifyData():bool
    {
        return $this->contains(TypeOperations::UPDATE) || $this->contains(TypeOperations::DELETE) || $this->contains(TypeOperations::INSERT) || $this->contains(TypeOperations::SYNC);
    }
    public function isExecutable():bool
    {
        return $this->contains(TypeOperations::EXEC) || $this->contains(TypeOperations::SYNC);
    }
}