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

use InvalidArgumentException;

/**
 * Abstract base Expr class for building SQL parts.
 *
 */
abstract class Base
{
    /**
     * @var string
     */
    protected string $preSeparator = '(';

    /**
     * @var string
     */
    protected string $separator = ', ';

    /**
     * @var string
     */
    protected string $postSeparator = ')';

    public bool $prettyPrint = false;
    protected array $prettyChars = ['', '', ''];

    /**
     * @var array
     */
    protected array $allowedClasses = [];

    /**
     * @var array
     */
    protected array $parts = [];

    /**
     * @param array $args
     */
    public function __construct(...$args)
    {
        $this->addMultiple($args);
    }

    /**
     * @param array $args
     *
     * @return Base
     */
    public function addMultiple(array $args): Base
    {
        foreach ($args as $arg) {
            $this->add($arg);
        }
        return $this;
    }

    /**
     * @param mixed $arg
     *
     * @return Base
     *
     * @throws InvalidArgumentException
     */
    public function add(mixed $arg): Base
    {
        if ($arg !== null && (!$arg instanceof self || $arg->count() > 0)) {
            // If we decide to keep Expr\Base instances, we can use this check
            if (!is_string($arg)) {
                $class = get_class($arg);
                if (!in_array($class, $this->allowedClasses, true)) {
                    throw new InvalidArgumentException("Expression of type '$class' not allowed in this context.");
                }
            }

            $this->parts[] = $arg;
        }

        return $this;
    }

    public function partExists(mixed $part): bool
    {
        return in_array($part, $this->parts);
    }

    public function remove(mixed $part): Base
    {
        if ($this->partExists($part)) {
            unset($this->parts[array_search($part, $this->parts)]);
        }
        return $this;
    }

    public function clear(): Base
    {
        $this->parts = [];
        return $this;
    }

    /**
     * @return integer
     */
    public function count(): int
    {
        return count($this->parts);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        if ($this->count() === 1) {
            return (string)$this->parts[0];
        }
        $pre = $this->preSeparator;
        $sep = $this->separator;
        $post = $this->postSeparator;
        if ($this->prettyPrint) {
            $pre = ($this->prettyChars[0] ?? '') . $pre;
            $sep .= $this->prettyChars[1] ?? '';
            $post .= ($this->prettyChars[2] ?? '');
        }

        return $pre. implode($sep, $this->parts) . $post;
    }
}
