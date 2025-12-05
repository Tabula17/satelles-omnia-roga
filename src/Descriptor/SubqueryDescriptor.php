<?php

namespace Tabula17\Satelles\Omnia\Roga\Descriptor;

use Tabula17\Satelles\Utilis\Config\AbstractDescriptor;

/**
 * Class SubqueryDescriptor
 *
 * Represents a descriptor for a subquery, extending the functionality of AbstractDescriptor.
 * The class facilitates the encapsulation of query descriptors and their arguments.
 *
 * Properties:
 * - descriptor: Manages the SelectDescriptor object or encapsulates it based on the input type.
 * - arguments: Holds the array of arguments related to the subquery.
 */
class SubqueryDescriptor extends AbstractDescriptor
{
    protected(set) SelectDescriptor $descriptor {
        set(array|AbstractDescriptor $value) {
            if (is_array($value)) {
                $this->descriptor = new SelectDescriptor($value);
            } elseif ($value instanceof SelectDescriptor) {
                $this->descriptor = $value;
            }
        }
    }
    protected(set) array $arguments;
}