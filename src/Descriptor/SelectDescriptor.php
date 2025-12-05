<?php

namespace Tabula17\Satelles\Omnia\Roga\Descriptor;

use Tabula17\Satelles\Omnia\Roga\Collection\JoinCollection;

/**
 * Represents a SQL SELECT statement descriptor.
 *
 * This class extends the functionality of the StatementDescriptor class
 * to provide specific properties and behavior for SELECT statements.
 * It supports various SQL SELECT options, including DISTINCT, TOP,
 * and different locking mechanisms. Additionally, it allows defining
 * table sources, join operations, and limit/offset constraints.
 *
 * Properties:
 * - $distinct: A boolean flag indicating whether the DISTINCT clause is used.
 * - $all: A boolean flag indicating if the ALL keyword is explicitly specified.
 * - $top: An integer representing the number of rows to return using the TOP clause.
 * - $forUpdate: A boolean flag indicating if the FOR UPDATE clause is applied.
 * - $forShare: A boolean flag indicating if the FOR SHARE clause is applied.
 * - $forNoKeyUpdate: A boolean flag for the FOR NO KEY UPDATE clause.
 * - $forKeyShare: A boolean flag for the FOR KEY SHARE clause.
 * - $forKeyNoShare: A boolean flag for the FOR KEY NO SHARE clause.
 * - $forNoKey: A boolean flag for general FOR NO KEY operations.
 * - $forKey: A boolean flag for general FOR KEY operations.
 * - $forRead: A boolean flag for FOR READ operation intent.
 * - $forWrite: A boolean flag for FOR WRITE operation intent.
 * - $forAppend: A boolean flag for FOR APPEND operation intent.
 * - $forCheck: A boolean flag for FOR CHECK operation intent.
 * - $forReference: A boolean flag for FOR REFERENCE operation intent.
 * - $forShareUpdate: A boolean flag for combined FOR SHARE and UPDATE intent.
 * - $forShareRead: A boolean flag for combined FOR SHARE and READ intent.
 * - $forShareNoKeyUpdate: A boolean flag for FOR SHARE with NO KEY UPDATE intent.
 * - $forShareKeyShare: A boolean flag for FOR SHARE with KEY SHARE intent.
 * - $forShareKeyNoShare: A boolean flag for FOR SHARE with KEY NO SHARE intent.
 * - $forShareNoKey: A boolean flag for FOR SHARE with NO KEY intent.
 * - $forShareKey: A boolean flag for FOR SHARE with general key operations.
 * - $from: A TableDescriptor instance representing the table or data source for the SELECT.
 * - $joins: A JoinCollection instance containing join operations for the SELECT statement.
 * - $offset: An integer defining the number of rows to skip in the result set.
 * - $limit: An integer limiting the number of rows in the result set.
 */
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