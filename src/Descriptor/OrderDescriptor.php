<?php

namespace Tabula17\Satelles\Omnia\Roga\Descriptor;

use Tabula17\Satelles\Utilis\Config\AbstractDescriptor;

class OrderDescriptor extends AbstractDescriptor
{
    protected(set) int $position;
    protected(set) string $direction = 'ASC';
}