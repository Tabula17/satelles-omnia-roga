<?php

namespace Tabula17\Satelles\Omnia\Roga\Descriptor;

use Tabula17\Satelles\Omnia\Roga\Builder\Column;
use Tabula17\Satelles\Utilis\Config\AbstractDescriptor;

class ModelDescriptor extends AbstractDescriptor
{

    private(set) string $title;
    private(set) ?string $description = null;
    private(set) string $type;
    private(set) string $primitiveType;
    private(set) ?string $format = null;
    private(set) \Closure $hydrate;

    public function __construct(Column $column)
    {
        parent::__construct();
        $this->title = $column->title;
        $this->description = $column->description;
        $this->type = $column->type->value;
        $this->primitiveType = $column->type->primitiveType();
        //$this->format = $column->format;
        $this->hydrate = $column->type->hydrate(...);
    }
}