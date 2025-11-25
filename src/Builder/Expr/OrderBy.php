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
 * Expression class for building SQL Order By parts.
 *

 */
class OrderBy
{
    /**
     * @var string
     */
    protected string $preSeparator = '';

    /**
     * @var string
     */
    protected string $separator = ', ';

    /**
     * @var string
     */
    protected string $postSeparator = '';

    protected array $prettyChars = ['', "\n    ", "\r"];
    public bool $prettyPrint = false;
    /**
     * @var array
     */
    protected array $allowedClasses = [];

    /**
     * @var array
     */
    protected array $parts = [] {
        &get {
            return $this->parts;
        }
    }

    /**
     * @param string|null $sort
     * @param string|null $order
     */
    public function __construct(?string $sort = null, ?string $order = null)
    {
        if ($sort) {
            $this->add($sort, $order);
        }
    }

    /**
     * @param string $sort
     * @param string|null $order
     *
     * @return void
     */
    public function add(string $sort, ?string $order = null): void
    {
        $order = !$order ? Keywords::ASC->value : Keywords::fromName(strtolower($order))->value;
        $this->parts[] = $sort . ' ' . $order;
    }

    public function partExists(string $sort, ?string $order = null): bool
    {
        $order = !$order ? Keywords::ASC->value : Keywords::fromName(strtolower($order))->value;
        return in_array($sort . ' ' . $order, $this->parts);
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
        if ($this->prettyPrint) {
            $this->preSeparator = ($this->prettyChars[0] ?? '') . $this->preSeparator;
            $this->separator .= $this->prettyChars[1] ?? '';
            $this->postSeparator .= ($this->prettyChars[2] ?? '');
        }
        return $this->preSeparator . implode($this->separator, $this->parts) . $this->postSeparator;
    }
}
