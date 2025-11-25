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

use Tabula17\Satelles\Omnia\Roga\Descriptor\AbstractDescriptor;

/**
 * Expression class for SQL from.
 *

 */
final readonly class From
{
    /**
     * @var string|array
     */
    private(set) string|array $table;

    /**
     * @var ?string
     */
    private(set) ?string $alias;

    private(set) bool $isSubquery;


    /**
     * @param string|array|AbstractDescriptor $table The class name.
     * @param ?string $alias The alias of the class.
     * @param bool $isSubquery
     */
    public function __construct(string|array|AbstractDescriptor $table, ?string $alias = null, bool $isSubquery = false)
    {
        $this->table = is_array($table) ? implode('.', $table) : $table;
        $this->alias = $alias;
        $this->isSubquery = $isSubquery;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        $pre = $this->isSubquery ? '(' : '';
        $post = $this->isSubquery ? ')' : '';
        return $pre . $this->table . $post . ' ' . $this->alias;
    }
}
