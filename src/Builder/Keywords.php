<?php

namespace Tabula17\Satelles\Omnia\Roga\Builder;

enum Keywords: string
{
    case SELECT = 'SELECT';
    case DISTINCT = 'DISTINCT';
    case INSERT = 'INSERT';
    case UPDATE = 'UPDATE';
    case DELETE = 'DELETE';
    case CREATE = 'CREATE';
    case ALTER = 'ALTER';
    case DROP = 'DROP';
    case TRUNCATE = 'TRUNCATE';
    case SET = 'SET';
    case VALUES = 'VALUES';
    case AND = 'AND';
    case OR = 'OR';
    case NOT = 'NOT';
    case EXISTS = 'EXISTS';
    case INTO = 'INTO';
    case CALL = 'CALL';
    case PREPARE = 'PREPARE';
    case EXECUTE = 'EXECUTE';
    case DEALLOCATE = 'DEALLOCATE';
    case COMMIT = 'COMMIT';
    case ROLLBACK = 'ROLLBACK';
    case SAVEPOINT = 'SAVEPOINT';
    case RELEASE = 'RELEASE';
    case LOCK = 'LOCK';
    case UNLOCK = 'UNLOCK';
    case WITH = 'WITH';
    case FROM = 'FROM';
    case JOIN = 'JOIN';
    case WHERE = 'WHERE';
    case GROUP_BY = 'GROUP BY';
    case HAVING = 'HAVING';
    case ORDER_BY = 'ORDER BY';
    case LIMIT = 'LIMIT';
    case OFFSET = 'OFFSET';
    case UNION = 'UNION';
    case UNION_ALL = 'UNION ALL';
    case INTERSECT = 'INTERSECT';
    case INTERSECT_ALL = 'INTERSECT ALL';
    case EXCEPT = 'EXCEPT';
    case EXCEPT_ALL = 'EXCEPT ALL';
    case ALL = 'ALL';
    case ANY = 'ANY';
    case AS = 'AS';
    case ON = 'ON';
    case ASC = 'ASC';
    case DESC = 'DESC';
    case BETWEEN = 'BETWEEN';
    case IN = 'IN';
    case LEFT = 'LEFT';
    case RIGHT = 'RIGHT';
    case INNER = 'INNER';
    case OUTER = 'OUTER';
    case FULL = 'FULL';
    case CROSS = 'CROSS';
    case JOIN_STRAIGHT = 'STRAIGHT_JOIN';
    case JOIN_NATURAL = 'NATURAL';
    case JOIN_NATURAL_LEFT = 'NATURAL LEFT';
    case JOIN_NATURAL_RIGHT = 'NATURAL RIGHT';
    case NULL = 'NULL';
    case TOP = 'TOP';
    case TOP_PERCENT = 'TOP PERCENT';
    case ROW_NUMBER = 'ROW_NUMBER';
    case RANK = 'RANK';
    case DENSE_RANK = 'DENSE_RANK';
    case PERCENT_RANK = 'PERCENT_RANK';
    case CUME_DIST = 'CUME_DIST';
    case NTILE = 'NTILE';
    case LAG = 'LAG';
    case LEAD = 'LEAD';
    case FORUPDATE = 'FOR UPDATE';
    case _none = '';

    public static function fromName(string $value): self
    {
        $spaced = str_replace('_', ' ', $value);
        // Add a space before any uppercase letter (for camel case)
        // The '(?!^)' ensures that a space isn't added at the beginning of the string
        $value = preg_replace('/(?<!^)([A-Z])/', ' $1', $spaced);
        // lower case everything
        $value = strtolower($value);

        return match ($value) {
            'select' => self::SELECT,
            'distinct' => self::DISTINCT,
            'insert' => self::INSERT,
            'update' => self::UPDATE,
            'delete' => self::DELETE,
            'create' => self::CREATE,
            'alter' => self::ALTER,
            'drop' => self::DROP,
            'truncate' => self::TRUNCATE,
            'set' => self::SET,
            'values' => self::VALUES,
            'and' => self::AND,
            'or' => self::OR,
            'not' => self::NOT,
            'exists' => self::EXISTS,
            'into' => self::INTO,
            'call' => self::CALL,
            'prepare' => self::PREPARE,
            'exec', 'execute' => self::EXECUTE,
            'deallocate' => self::DEALLOCATE,
            'commit' => self::COMMIT,
            'rollback' => self::ROLLBACK,
            'savepoint' => self::SAVEPOINT,
            'release' => self::RELEASE,
            'lock' => self::LOCK,
            'unlock' => self::UNLOCK,
            'with' => self::WITH,
            'from' => self::FROM,
            'join' => self::JOIN,
            'where' => self::WHERE,
            'group by' => self::GROUP_BY,
            'having' => self::HAVING,
            'order by' => self::ORDER_BY,
            'limit' => self::LIMIT,
            'offset' => self::OFFSET,
            'union' => self::UNION,
            'union all' => self::UNION_ALL,
            'intersect' => self::INTERSECT,
            'intersect all' => self::INTERSECT_ALL,
            'except' => self::EXCEPT,
            'except all' => self::EXCEPT_ALL,
            'all' => self::ALL,
            'any' => self::ANY,
            'as' => self::AS,
            'on' => self::ON,
            'asc' => self::ASC,
            'collection' => self::DESC,
            'between' => self::BETWEEN,
            'in' => self::IN,
            'left' => self::LEFT,
            'right' => self::RIGHT,
            'inner' => self::INNER,
            'outer' => self::OUTER,
            'full' => self::FULL,
            'cross' => self::CROSS,
            'straight join' => self::JOIN_STRAIGHT,
            'natural' => self::JOIN_NATURAL,
            'natural left' => self::JOIN_NATURAL_LEFT,
            'natural right' => self::JOIN_NATURAL_RIGHT,
            'null' => self::NULL,
            'top' => self::TOP,
            'top percent' => self::TOP_PERCENT,
            'row number' => self::ROW_NUMBER,
            'rank' => self::RANK,
            'dense rank' => self::DENSE_RANK,
            'percent rank' => self::PERCENT_RANK,
            'cume dist' => self::CUME_DIST,
            'ntile' => self::NTILE,
            'lag' => self::LAG,
            'lead' => self::LEAD,
            'for update' => self::FORUPDATE,
            default => self::_none

        };
    }
}
