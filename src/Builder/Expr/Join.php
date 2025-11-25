<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Tabula17\Satelles\Omnia\Roga\Builder\Expr;

use Tabula17\Satelles\Omnia\Roga\Builder\Keywords;

/**
 * Expression class for SQL join.
 */
class Join
{
    /**
     * @var string
     */
    protected string $joinType {
        get {
            return $this->joinType;
        }
    }

    /**
     * @var string
     */
    protected string $join {
        get {
            return $this->join;
        }
    }

    /**
     * @var ?string
     */
    protected ?string $alias {
        get {
            return $this->alias;
        }
    }

    /**
     * @var ?string
     */
    protected ?string $conditionType {
        get {
            return $this->conditionType;
        }
    }

    /**
     * @var string|array|null
     */
    protected string|array|null $condition {
        get {
            return $this->condition;
        }
    }

    /**
     * @param string $joinType The condition type constant. Either INNER_JOIN or LEFT_JOIN.
     * @param string $join The relationship to join.
     * @param string|null $alias The alias of the join.
     * @param string|null $conditionType The condition type constant. Either ON or WITH.
     * @param array|string|null $condition The condition for the join.
     */
    public function __construct(string $joinType, string $join, ?string $alias = null, ?string $conditionType = null, array|string|null $condition = null)
    {
        $this->joinType = $joinType;
        $this->join = $join;
        $this->alias = $alias;
        $this->conditionType = $conditionType;
        $this->condition = $condition;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        $join = [Keywords::fromName($this->joinType)->value, Keywords::JOIN->value, $this->join];
        if ($this->alias) {
            $join[] = $this->alias;
        }
        if ($this->condition) {
            $join[] = Keywords::fromName($this->conditionType)->value;
            if (is_array($this->condition)) {
                $join[] = implode(' ' . Keywords::AND->value . ' ', $this->condition);
            }else {
                $join[] = $this->condition;
            }
        }
        return implode(' ', $join);
    }
}
