<?php
/**
 * Creado por el Dpto. de Sistemas Papelera Tucumán - Grupo Telecentro.
 * Parte de SIG-Library
 * Author: marpan
 * Date: 04/06/13
 * Time: 13:56
 *
 */

namespace Tabula17\Satelles\Omnia\Roga\Builder;


use Tabula17\Satelles\Omnia\Roga\Builder\Expr\Func;

/**
 * Class Expression
 *
 * Devuelve las distintas funciones y/o expresiones SQL.
 * @package Tabula17\Satelles\Omnia\Roga\Builder
 * @author Martín Panizzo
 */
class Expression
{
    //  public string $quote_identifier = '"';
    public string $quote_value = "'";

    /**
     */
    public function __construct()
    {
    }

    /**
     * Creates a conjunction of the given boolean expressions.
     *
     * Example:
     *
     *     [php]
     *     // (u.type = ?1) AND (u.role = ?2)
     *     $expr->andX($expr->eq('u.type', ':1'), $expr->eq('u.role', ':2'));
     *
     * @param null $x
     * @return Expr\Andx
     */
    public function andX($x = null): Expr\Andx
    {
        return new Expr\Andx(func_get_args());
    }

    /**
     * Creates a disjunction of the given boolean expressions.
     *
     * Example:
     *
     *     [php]
     *     // (u.type = ?1) OR (u.role = ?2)
     *     $q->where($q->expr()->orX('u.type = ?1', 'u.role = ?2'));
     *
     * @param mixed|null $x Optional clause. Defaults to null, but requires
     *                 at least one defined when converting to string.
     *
     * @return Expr\Orx
     */
    public function orX(mixed $x = null): Expr\Orx
    {
        return new Expr\Orx(func_get_args());
    }

    /**
     * Creates an ASCending order expression.
     *
     * @param mixed $expr
     *
     * @return Expr\OrderBy
     */
    public function asc(mixed $expr): Expr\OrderBy
    {
        return new Expr\OrderBy($expr, 'ASC');
    }

    /**
     * Creates a DESCending order expression.
     *
     * @param mixed $expr
     *
     * @return Expr\OrderBy
     */
    public function desc(mixed $expr): Expr\OrderBy
    {
        return new Expr\OrderBy($expr, 'DESC');
    }

    /**
     * Creates an equality comparison expression with the given arguments.
     *
     * First argument is considered the left expression and the second is the right expression.
     * When converted to string, it will generated a <left expr> = <right expr>. Example:
     *
     *     [php]
     *     // u.id = ?1
     *     $expr->eq('u.id', '?1');
     *
     * @param mixed $x Left expression.
     * @param mixed $y Right expression.
     *
     * @return Expr\Comparison
     */
    public function eq(mixed $x, mixed $y): Expr\Comparison
    {
        return new Expr\Comparison($x, Expr\Comparison::EQ, $y);
    }

    /**
     * Creates an instance of Expr\Comparison, with the given arguments.
     * First argument is considered the left expression and the second is the right expression.
     * When converted to string, it will generated a <left expr> <> <right expr>. Example:
     *
     *     [php]
     *     // u.id <> ?1
     *     $q->where($q->expr()->neq('u.id', '?1'));
     *
     * @param mixed $x Left expression.
     * @param mixed $y Right expression.
     *
     * @return Expr\Comparison
     */
    public function neq(mixed $x, mixed $y): Expr\Comparison
    {
        return new Expr\Comparison($x, Expr\Comparison::NEQ, $y);
    }

    /**
     * Creates an instance of Expr\Comparison, with the given arguments.
     * First argument is considered the left expression and the second is the right expression.
     * When converted to string, it will generated a <left expr> < <right expr>. Example:
     *
     *     [php]
     *     // u.id < ?1
     *     $q->where($q->expr()->lt('u.id', '?1'));
     *
     * @param mixed $x Left expression.
     * @param mixed $y Right expression.
     *
     * @return Expr\Comparison
     */
    public function lt(mixed $x, mixed $y): Expr\Comparison
    {
        return new Expr\Comparison($x, Expr\Comparison::LT, $y);
    }

    /**
     * Creates an instance of Expr\Comparison, with the given arguments.
     * First argument is considered the left expression and the second is the right expression.
     * When converted to string, it will generated a <left expr> <= <right expr>. Example:
     *
     *     [php]
     *     // u.id <= ?1
     *     $q->where($q->expr()->lte('u.id', '?1'));
     *
     * @param mixed $x Left expression.
     * @param mixed $y Right expression.
     *
     * @return Expr\Comparison
     */
    public function lte(mixed $x, mixed $y): Expr\Comparison
    {
        return new Expr\Comparison($x, Expr\Comparison::LTE, $y);
    }

    /**
     * Creates an instance of Expr\Comparison, with the given arguments.
     * First argument is considered the left expression and the second is the right expression.
     * When converted to string, it will generated a <left expr> > <right expr>. Example:
     *
     *     [php]
     *     // u.id > ?1
     *     $q->where($q->expr()->gt('u.id', '?1'));
     *
     * @param mixed $x Left expression.
     * @param mixed $y Right expression.
     *
     * @return Expr\Comparison
     */
    public function gt(mixed $x, mixed $y): Expr\Comparison
    {
        return new Expr\Comparison($x, Expr\Comparison::GT, $y);
    }

    /**
     * Creates an instance of Expr\Comparison, with the given arguments.
     * First argument is considered the left expression and the second is the right expression.
     * When converted to string, it will generated a <left expr> >= <right expr>. Example:
     *
     *     [php]
     *     // u.id >= ?1
     *     $q->where($q->expr()->gte('u.id', '?1'));
     *
     * @param mixed $x Left expression.
     * @param mixed $y Right expression.
     *
     * @return Expr\Comparison
     */
    public function gte(mixed $x, mixed $y): Expr\Comparison
    {
        return new Expr\Comparison($x, Expr\Comparison::GTE, $y);
    }

    /**
     * Creates an instance of AVG() function, with the given argument.
     *
     * @param mixed $x Argument to be used in AVG() function.
     *
     * @return Expr\Func
     */
    public function avg(...$x): Expr\Func
    {
        return new Expr\Func('AVG', ...$x);
    }

    /**
     * Creates an instance of MAX() function, with the given argument.
     *
     * @param mixed $x Argument to be used in MAX() function.
     *
     * @return Expr\Func
     */
    public function max(...$x): Expr\Func
    {
        return new Expr\Func('MAX', ...$x);
    }

    /**
     * Creates an instance of MIN() function, with the given argument.
     *
     * @param mixed $x Argument to be used in MIN() function.
     *
     * @return Expr\Func
     */
    public function min(...$x): Expr\Func
    {
        return new Expr\Func('MIN', ...$x);
    }

    /**
     * Creates an instance of COUNT() function, with the given argument.
     *
     * @param mixed $x Argument to be used in COUNT() function.
     *
     * @return Expr\Func
     */
    public function count(...$x): Expr\Func
    {
        return new Expr\Func('COUNT', ...$x);
    }

    /**
     * Creates an instance of COUNT(DISTINCT) function, with the given argument.
     *
     * @param mixed $x Argument to be used in COUNT(DISTINCT) function.
     *
     * @return string
     */
    public function countDistinct(mixed $x): string
    {
        return 'COUNT(DISTINCT ' . implode(', ', func_get_args()) . ')';
    }

    /**
     * Creates an instance of EXISTS() function, with the given DQL subqueriesForPath.
     *
     * @param mixed $subquery DQL subqueriesForPath to be used in EXISTS() function.
     *
     * @return Expr\Func
     */
    public function exists(string $subquery): Expr\Func
    {
        return new Expr\Func(Keywords::EXISTS->value, $subquery);
    }

    public function notexists(string $subquery): Expr\Func
    {
        return new Expr\Func(Keywords::NOT->value . ' ' . Keywords::EXISTS->value, $subquery);
    }

    /**
     * Creates an instance of ALL() function, with the given DQL subqueriesForPath.
     *
     * @param mixed $subquery DQL subqueriesForPath to be used in ALL() function.
     *
     * @return Expr\Func
     */
    public function all(mixed $subquery): Expr\Func
    {
        return new Expr\Func(Keywords::ALL->value, $subquery);
    }

    /**
     * Creates a SOME() function expression with the given DQL subqueriesForPath.
     *
     * @param mixed $subquery DQL subqueriesForPath to be used in SOME() function.
     *
     * @return Expr\Func
     */
    public function some(mixed $subquery): Expr\Func
    {
        return new Expr\Func('SOME', $subquery);
    }

    /**
     * Creates an ANY() function expression with the given DQL subqueriesForPath.
     *
     * @param mixed $subquery DQL subqueriesForPath to be used in ANY() function.
     *
     * @return Expr\Func
     */
    public function any(mixed $subquery): Expr\Func
    {
        return new Expr\Func(Keywords::ANY->value, $subquery);
    }

    /**
     * Creates a negation expression of the given restriction.
     *
     * @param mixed $restriction Restriction to be used in NOT() function.
     *
     * @return Expr\Func
     */
    public function not(mixed $restriction): Expr\Func
    {
        return new Expr\Func('NOT', $restriction);
    }

    /**
     * Creates an ABS() function expression with the given argument.
     *
     * @param mixed $x Argument to be used in ABS() function.
     *
     * @return Expr\Func
     */
    public function abs(mixed ...$x): Expr\Func
    {
        return new Expr\Func('ABS', ...$x);
    }

    /**
     * Creates a product mathematical expression with the given arguments.
     *
     * First argument is considered the left expression and the second is the right expression.
     * When converted to string, it will generated a <left expr> * <right expr>. Example:
     *
     *     [php]
     *     // u.salary * u.percentAnnualSalaryIncrease
     *     $q->expr()->prod('u.salary', 'u.percentAnnualSalaryIncrease')
     *
     * @param mixed $x Left expression.
     * @param mixed $y Right expression.
     *
     * @return Expr\Math
     */
    public function prod(mixed $x, mixed $y): Expr\Math
    {
        return new Expr\Math($x, '*', $y);
    }

    /**
     * Creates a difference mathematical expression with the given arguments.
     * First argument is considered the left expression and the second is the right expression.
     * When converted to string, it will generated a <left expr> - <right expr>. Example:
     *
     *     [php]
     *     // u.monthlySubscriptionCount - 1
     *     $q->expr()->diff('u.monthlySubscriptionCount', '1')
     *
     * @param mixed $x Left expression.
     * @param mixed $y Right expression.
     *
     * @return Expr\Math
     */
    public function diff(mixed $x, mixed $y): Expr\Math
    {
        return new Expr\Math($x, '-', $y);
    }

    /**
     * Creates a sum mathematical expression with the given arguments.
     * First argument is considered the left expression and the second is the right expression.
     * When converted to string, it will generated a <left expr> + <right expr>. Example:
     *
     *     [php]
     *     // u.numChildren + 1
     *     $q->expr()->diff('u.numChildren', '1')
     *
     * @param mixed $x Left expression.
     * @param mixed $y Right expression.
     *
     * @return Expr\Math
     */
    public function addition(mixed $x, mixed $y): Expr\Math
    {
        return new Expr\Math($x, '+', $y);
    }


    /**
     * Creates a SUM() function expression with the given argument.
     *
     * @param mixed $x Argument to be used in SUM() function.
     *
     * @return Expr\Func
     */
    public function sum(mixed $x): Expr\Func
    {
        return new Expr\Func('SUM', $x);

    }

    /**
     * Creates a quotient mathematical expression with the given arguments.
     * First argument is considered the left expression and the second is the right expression.
     * When converted to string, it will generated a <left expr> / <right expr>. Example:
     *
     *     [php]
     *     // u.total / u.period
     *     $expr->quot('u.total', 'u.period')
     *
     * @param mixed $x Left expression.
     * @param mixed $y Right expression.
     *
     * @return Expr\Math
     */
    public function quot(mixed $x, mixed $y): Expr\Math
    {
        return new Expr\Math($x, '/', $y);
    }

    /**
     * Creates a SQRT() function expression with the given argument.
     *
     * @param mixed $x Argument to be used in SQRT() function.
     *
     * @return Expr\Func
     */
    public function sqrt(mixed $x): Expr\Func
    {
        return new Expr\Func('SQRT', array($x));
    }

    /**
     * Creates an IN() expression with the given arguments.
     *
     * @param string $x Field in string format to be restricted by IN() function.
     * @param mixed $y Argument to be used in IN() function.
     *
     * @return Expr\Func
     */
    public function in(string $x, mixed $y): Expr\Func
    {
        if (!is_array($y)) {
            $y = [$y];
        }
        foreach ($y as &$literal) {
            // If already an Expr\Literal, keep as-is
            if ($literal instanceof Expr\Literal) {
                continue;
            }
            // If it's a parameter placeholder (starts with ':' or '?'), leave untouched
            if (is_string($literal) && (str_starts_with($literal, ':') || str_starts_with($literal, '?'))) {
                continue;
            }
            $literal = $this->_quoteLiteral($literal);
        }

        return new Expr\Func($x . ' IN', ...$y);
    }

    /**
     * Quotes a literal value, if necessary, according to the DQL syntax.
     *
     * @param mixed $literal The literal value.
     *
     * @return string
     */
    private function _quoteLiteral(mixed $literal): string
    {
        if (is_numeric($literal) /*&& !is_string($literal)*/) {
            return (string)$literal;
        }
        if (is_string($literal) && strtoupper($literal) === 'NULL') {
            return 'NULL';
        }
        if (is_bool($literal)) {
            return $literal ? "true" : "false";
        }
        $quote_char = $this->quote_value;

        return $quote_char . str_replace($quote_char, $quote_char . $quote_char, $literal) . $quote_char;
    }

    /**
     * Creates a NOT IN() expression with the given arguments.
     *
     * @param string $x Field in string format to be restricted by NOT IN() function.
     * @param mixed $y Argument to be used in NOT IN() function.
     *
     * @return Expr\Func
     */
    public function notIn(string $x, mixed $y): Expr\Func
    {
        if (!is_array($y)) {
            $y = [$y];
        }
        foreach ($y as &$literal) {
            // If already an Expr\Literal, keep as-is
            if ($literal instanceof Expr\Literal) {
                continue;
            }
            // If it's a parameter placeholder (starts with ':' or '?'), leave untouched
            if (is_string($literal) && (str_starts_with($literal, ':') || str_starts_with($literal, '?'))) {
                continue;
            }
            $literal = $this->_quoteLiteral($literal);
        }

        return new Expr\Func($x . ' NOT IN', ...$y);
    }

    /**
     * Creates an IS NULL expression with the given arguments.
     *
     * @param string $x Field in string format to be restricted by IS NULL.
     *
     * @return string
     */
    public function isNull(string $x): string
    {
        return $x . ' IS NULL';
    }

    /**
     * Creates an IS NOT NULL expression with the given arguments.
     *
     * @param string $x Field in string format to be restricted by IS NOT NULL.
     *
     * @return string
     */
    public function isNotNull(string $x): string
    {
        return $x . ' IS NOT NULL';
    }

    /**
     * Creates a LIKE() comparison expression with the given arguments.
     *
     * @param string $x Field in string format to be inspected by LIKE() comparison.
     * @param mixed $y Argument to be used in LIKE() comparison.
     *
     * @return Expr\Comparison
     */
    public function like(string $x, mixed $y): Expr\Comparison
    {
        return new Expr\Comparison($x, 'LIKE', $y);
    }

    /**
     * Creates a NOT LIKE() comparison expression with the given arguments.
     *
     * @param string $x Field in string format to be inspected by LIKE() comparison.
     * @param mixed $y Argument to be used in LIKE() comparison.
     *
     * @return Expr\Comparison
     */
    public function notLike(string $x, mixed $y): Expr\Comparison
    {
        return new Expr\Comparison($x, 'NOT LIKE', $y);
    }

    /**
     * Creates a CONCAT() function expression with the given arguments.
     *
     * @param mixed ...$arguments
     * @return Func
     */
    public function concat(...$arguments): Expr\Func
    {
        return new Expr\Func('CONCAT', ...$arguments);
    }

    /**
     * Creates a CONCAT() function expression with the given arguments.
     *
     * @return string
     */
    public function concatplus(): string
    {
        return '( ' . implode(' + ', func_get_args()) . ')';
    }

    /**
     * Creates a SUBSTRING() function expression with the given arguments.
     *
     * @param mixed $x Argument to be used as string to be cropped by SUBSTRING() function.
     * @param int $from Initial offset to start cropping string. May accept negative values.
     * @param int|null $len Length of crop. May accept negative values.
     *
     * @return Expr\Func
     */
    public function substring(mixed $x, int $from, ?int $len = null): Expr\Func
    {
        $args = array($x, $from);
        if (null !== $len) {
            $args[] = $len;
        }

        return new Expr\Func('SUBSTRING', ...$args);
    }

    /**
     * Creates a LOWER() function expression with the given argument.
     *
     * @param mixed $x Argument to be used in LOWER() function.
     *
     * @return Expr\Func A LOWER function expression.
     */
    public function lower(...$x): Expr\Func
    {
        return new Expr\Func('LOWER', ...$x);
    }

    /**
     * Creates an UPPER() function expression with the given argument.
     *
     * @param mixed $x Argument to be used in UPPER() function.
     *
     * @return Expr\Func An UPPER function expression.
     */
    public function upper(...$x): Expr\Func
    {
        return new Expr\Func('UPPER', ...$x);
    }

    /**
     * Creates a LENGTH() function expression with the given argument.
     *
     * @param mixed $x Argument to be used as argument of LENGTH() function.
     *
     * @return Expr\Func A LENGTH function expression.
     */
    public function length(...$x): Expr\Func
    {
        return new Expr\Func('LENGTH', ...$x);
    }

    /**
     * Creates a literal expression of the given argument.
     *
     * @param mixed $literal Argument to be converted to literal.
     *
     * @return Expr\Literal
     */
    public function literal(mixed $literal): Expr\Literal
    {
        return new Expr\Literal($this->_quoteLiteral($literal));
    }

    /**
     * Creates an instance of BETWEEN() function, with the given argument.
     *
     * @param mixed $val Valued to be inspected by range values.
     * @param mixed $x Starting range value to be used in BETWEEN() function.
     * @param mixed|null $y End point value to be used in BETWEEN() function.
     *
     * @return string A BETWEEN expression.
     */
    public function between(mixed $val, mixed $x, mixed $y = null): string
    {
        //echo var_export($x, true);
        if (!isset($y)) {
            if (is_array($x)) {
                [$x, $y] = $x;
            } else {
                [$x, $y] = explode(",", str_replace(" ", "", $x));
            }
        }

        return $val . ' BETWEEN ' . $x . ' AND ' . $y;
    }

    /**
     * Creates an instance of BETWEEN() function, with the given argument.
     *
     * @param mixed $val Valued to be inspected by range values.
     * @param integer|array $x Starting range value to be used in BETWEEN() function.
     * @param mixed|null $y End point value to be used in BETWEEN() function.
     *
     * @return string A BETWEEN expression.
     */
    public function notbetween(mixed $val, int|array $x, mixed $y = null): string
    {
        if (!isset($y)) {
            if (is_array($x)) {
                [$x, $y] = $x;
            } else {
                [$x, $y] = explode(",", str_replace(" ", "", $x));
            }
        }

        return $val . ' NOT BETWEEN ' . $x . ' AND ' . $y;
    }

    /**
     * Creates an instance of TRIM() function, with the given argument.
     *
     * @param mixed $x Argument to be used as argument of TRIM() function.
     *
     * @return Expr\Func a TRIM expression.
     */
    public function trim(mixed $x): Expr\Func
    {
        return new Expr\Func('TRIM', $x);
    }

    /**
     * Expr ISNULL(x,default)
     * @param $x
     * @param $y
     * @return Expr\Func
     */
    public function isColNull(...$x): Expr\Func
    {
        return new Expr\Func('ISNULL', ...$x);
    }

    /**
     * Magic method!!
     * @param $func
     * @param $args
     *
     * @return Expr\Func
     */
    public function __call($func, $args)
    {
        $func = str_contains($func, '.') ? $func : strtoupper($func);
        return new Expr\Func($func, ...$args);
    }

}