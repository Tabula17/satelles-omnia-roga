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

/**
 * Expression class for SQL comparison expressions.
 *
 */
class Comparison
{
    public const string EQ  = '=';
    public const string NEQ = '<>';
    public const string LT  = '<';
    public const string LTE = '<=';
    public const string GT  = '>';
    public const string GTE = '>=';

    /**
     * @var mixed
     */
    protected mixed $leftExpr {
        get {
            return $this->leftExpr;
        }
    }

    /**
     * @var string
     */
    protected string $operator {
        get {
            return $this->operator;
        }
    }

    /**
     * @var mixed
     */
    protected mixed $rightExpr {
        get {
            return $this->rightExpr;
        }
    }

    /**
     * Creates a comparison expression with the given arguments.
     * 
     * @param mixed  $leftExpr
     * @param string $operator
     * @param mixed  $rightExpr
     */
    public function __construct(mixed $leftExpr, string $operator, mixed $rightExpr)
    {
        $this->leftExpr  = $leftExpr;
        $this->operator  = $operator;
        $this->rightExpr = $rightExpr;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->leftExpr . ' ' . $this->operator . ' ' . $this->rightExpr;
    }
}
