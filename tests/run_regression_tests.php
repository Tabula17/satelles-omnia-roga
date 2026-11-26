<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Tabula17\Satelles\Omnia\Roga\Builder\Column;
use Tabula17\Satelles\Omnia\Roga\Builder\Condition;
use Tabula17\Satelles\Omnia\Roga\Builder\Expression;
use Tabula17\Satelles\Omnia\Roga\Builder\Keywords;
use Tabula17\Satelles\Omnia\Roga\Builder\Expr\Andx;
use Tabula17\Satelles\Omnia\Roga\Builder\Expr\OrderBy;
use Tabula17\Satelles\Omnia\Roga\Builder\Param;
use Tabula17\Satelles\Omnia\Roga\Builder\ParamTypes;
use Tabula17\Satelles\Omnia\Roga\Builder\QuotesEnum;
use Tabula17\Satelles\Omnia\Roga\Builder\SelectStatement;
use Tabula17\Satelles\Omnia\Roga\Collection\StatementCollection;
use Tabula17\Satelles\Omnia\Roga\Descriptor\DescriptorClasses;
use Tabula17\Satelles\Omnia\Roga\Descriptor\ColumnDescriptor;
use Tabula17\Satelles\Omnia\Roga\Descriptor\ConditionDescriptor;
use Tabula17\Satelles\Omnia\Roga\Descriptor\ParamDescriptor;
use Tabula17\Satelles\Omnia\Roga\Descriptor\SelectDescriptor;
use Tabula17\Satelles\Omnia\Roga\Descriptor\SubqueryDescriptor;
use Tabula17\Satelles\Omnia\Roga\Exception\ConfigException;
use Tabula17\Satelles\Omnia\Roga\Loader\XmlFile;
use Tabula17\Satelles\Omnia\Roga\StatementBuilder;
use Tabula17\Satelles\Utilis\Exception\RuntimeException;

$failures = [];

function check(bool $condition, string $message, array &$failures): void
{
    if (!$condition) {
        $failures[] = $message;
    }
}

function checkSame(mixed $expected, mixed $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = sprintf('%s (expected %s, got %s)', $message, var_export($expected, true), var_export($actual, true));
    }
}

$loader = new XmlFile(__DIR__ . '/../src/Loader/Xml');

// 1. A missing variant must not leak the previously loaded statement.
$builder = new StatementBuilder('SELECT/Basic', $loader);
checkSame(null, $builder->getStatement(), 'No statement before load', $failures);
checkSame([], $builder->getValues(), 'No values before load', $failures);

$builder->loadStatementBy('allowed', 1);
check(is_string($builder->getStatement()), 'Statement loaded for allowed=1', $failures);
try {
    $builder->loadStatementBy('allowed', 999999);
    $failures[] = 'A missing variant did not throw';
} catch (RuntimeException) {
    checkSame(null, $builder->getStatement(), 'Processor reset after missing variant', $failures);
}

// 2. Direct descriptor lookups must not be cached across different arguments.
$builder = new StatementBuilder('SELECT/Basic', $loader);
checkSame('1', (string)$builder->getDescriptorBy('allowed', 1)?->metadata->allowed, 'Descriptor for allowed=1', $failures);
checkSame('4', (string)$builder->getDescriptorBy('allowed', 4)?->metadata->allowed, 'Descriptor for allowed=4', $failures);

// 3. Version resolution.
$v1 = new SelectDescriptor(['metadata' => ['allowed' => 1, 'version' => '1.0']]);
$v2 = new SelectDescriptor(['metadata' => ['allowed' => 1, 'version' => '2.0']]);
$versions = new StatementCollection($v1, $v2);
checkSame('2.0', $versions->getDescriptorsByVersion('latest')?->first()?->metadata->version, 'latest resolves to highest version', $failures);
checkSame('1.0', $versions->getDescriptorsByVersion('1.0')?->first()?->metadata->version, 'Explicit version is filtered', $failures);
checkSame(null, $versions->getDescriptorsByVersion('9.9'), 'Unknown version returns null', $failures);

$unversioned = new StatementCollection(new SelectDescriptor(['metadata' => ['allowed' => 1]]));
checkSame(1, $unversioned->getDescriptorsByVersion('latest')?->count(), 'Unversioned definitions use latest', $failures);

// 4. Variant precedence: exact match wins over '*'.
$wildcard = new SelectDescriptor(['metadata' => ['allowed' => 1, 'variant' => '*']]);
$default = new SelectDescriptor(['metadata' => ['allowed' => 1, 'variant' => 'default']]);
$variants = new StatementCollection($wildcard, $default);
checkSame($default, $variants->getVariant('default'), 'Exact variant wins over wildcard', $failures);
checkSame($wildcard, $variants->getVariant('custom'), 'Wildcard variant is the fallback', $failures);

// 5. Array metadata, wildcard arrays and falsy identifiers.
$anyTenant = new StatementCollection(new SelectDescriptor(['metadata' => ['allowed' => ['*']]]));
checkSame(1, $anyTenant->getDescriptorByMetadata('allowed', 7) !== null ? 1 : 0, 'allowed=["*"] matches', $failures);
$zeroTenant = new StatementCollection(new SelectDescriptor(['metadata' => ['allowed' => 0]]));
checkSame(['0'], $zeroTenant->getMetadataMemberValues('allowed'), 'Tenant 0 is preserved', $failures);

// 6. Descriptor type normalization.
checkSame(DescriptorClasses::INSERT, DescriptorClasses::fromValue(' INSERT '), 'Type normalized', $failures);
try {
    DescriptorClasses::fromValue('selec');
    $failures[] = 'Unknown statement type did not throw';
} catch (ConfigException) {
    check(true, 'Unknown statement type throws', $failures);
}

// 7. Parameter type fallback.
checkSame(PDO::PARAM_STR, ParamTypes::fromName('uuid'), 'Unknown param type falls back to STR', $failures);
checkSame(PDO::PARAM_INT, ParamTypes::fromName(PDO::PARAM_INT), 'Numeric param type is preserved', $failures);

// 8. Pretty printing must not change later renders.
$builder = new StatementBuilder('SELECT/Min', $loader);
$builder->loadStatementBy('allowed', 1);
$plain = $builder->getStatement();
$builder->getPrettyStatement();
checkSame($plain, $builder->getStatement(), 'getPrettyStatement does not mutate subsequent renders', $failures);

// 9. Fluent API on StatementBuilder.
checkSame($builder, $builder->setValues([]), 'setValues is fluent', $failures);

// 10. IN arrays emit one placeholder and one binding per item.
$inBuilder = new StatementBuilder('SELECT/ExtCol', $loader);
$inBuilder->loadStatementBy('allowed', 1);
$inBuilder->setValues(['idsProducto' => ['a', 'b'], 'in_cod_papel' => [10, 20]]);
$inSql = $inBuilder->getStatement();
check(str_contains($inSql, ':idsProducto0, :idsProducto1'), 'IN expands placeholders', $failures);
checkSame(['a', 'b'], [
    $inBuilder->getBindings()[':idsProducto0'] ?? null,
    $inBuilder->getBindings()[':idsProducto1'] ?? null,
], 'IN bindings are expanded', $failures);
checkSame(false, array_key_exists(':idsProducto', $inBuilder->getBindings()), 'No array binding for IN', $failures);
$inBuilder->removeValue('idsProducto');
checkSame(false, array_key_exists(':idsProducto0', $inBuilder->getBindings()), 'removeValue clears expanded bindings', $failures);

// 11. A zero value must still produce a predicate.
$zeroParam = new Param(new ParamDescriptor(['name' => 'f', 'type' => 'int', 'sqlexpression' => 'eq']));
$zeroParam->setColumnExpression('c')->setValue(0);
checkSame('c = :f', (string)$zeroParam, 'Zero value emits predicate', $failures);

// 12. Rendering twice must not duplicate INSERT columns.
$insert = new StatementBuilder('INSERT/Basic', $loader);
$insert->loadStatementBy('allowed', 1);
$insert->setValues([
    'param_name_to_insert_01' => 1,
    'param_name_to_insert_02' => 2,
    'param_name_to_insert_03' => 3,
]);
checkSame($insert->getStatement(), $insert->getStatement(), 'INSERT render is idempotent', $failures);

// 13. HAVING goes before ORDER BY and DESC resolves correctly.
$select = new SelectStatement($loader->getStatementDescriptors('SELECT/Min')[0]);
$having = new Andx();
$having->add('a.c1 >= 5');
$order = new OrderBy();
$order->add('a.c1', 'DESC');
foreach (['having' => $having, 'orderBy' => $order] as $property => $value) {
    (new ReflectionProperty(SelectStatement::class, $property))->setValue($select, $value);
}
$orderedSql = $select->__toString();
check(strpos($orderedSql, 'HAVING') < strpos($orderedSql, 'ORDER BY'), 'HAVING precedes ORDER BY', $failures);
check(str_contains($orderedSql, 'DESC'), 'DESC keyword is emitted', $failures);

// 14. Runtime values in paramTemplate are escaped, not injected.
$templated = new Param(new ParamDescriptor([
    'name' => 'term', 'type' => 'string', 'paramTemplate' => ':colname = :param',
]));
$templated->setColumnExpression('users.name')->setValue("x' OR 1=1 --");
checkSame("users.name = 'x'' OR 1=1 --'", (string)$templated, 'Template value is escaped', $failures);

// 15. Only real placeholders bypass literal quoting; delimiters are escaped.
$expression = new Expression();
checkSame("c IN(':p OR 1=1 --')", (string)$expression->in('c', [':p OR 1=1 --']), 'Placeholder-like input is quoted', $failures);
checkSame('[x]]; DROP TABLE users; --]', QuotesEnum::SQUARE_BRACKET->quote('x]; DROP TABLE users; --'), 'Identifier delimiters are escaped', $failures);

// 16. DELETE accepts placeholders without the leading colon and emits no HAVING.
$delete = new StatementBuilder('DELETE/Basic', $loader);
$delete->loadStatementBy('allowed', 1);
$delete->setValues(['filterIdParam' => 7]);
checkSame(7, $delete->getValues()[':filterIdParam'] ?? null, 'DELETE normalizes placeholders', $failures);
checkSame(false, str_contains($delete->getStatement() ?? '', 'HAVING'), 'DELETE has no HAVING clause', $failures);

// 17. EXISTS/NOT EXISTS conditions keep the subquery instead of the column.
$conditionSubquery = $loader->getStatementDescriptors('SELECT/Min')[0];
foreach (['exists' => 'EXISTS(SELECT', 'notexists' => 'NOT EXISTS(SELECT'] as $sqlExpression => $prefix) {
    $condition = new Condition(new ConditionDescriptor([
        'sqlexpression' => $sqlExpression,
        'subquery' => new SubqueryDescriptor(['descriptor' => $conditionSubquery, 'arguments' => []]),
    ]), new Expression(), QuotesEnum::NONE);
    $condition->setColumnExpression('t.c');
    $conditionSql = (string)$condition;
    check(str_starts_with($conditionSql, $prefix), "$sqlExpression keeps its subquery", $failures);
    checkSame(false, str_contains($conditionSql, 'EXISTS(t.c)'), "$sqlExpression does not emit the column alone", $failures);
    check($condition->subquery instanceof SelectStatement, "$sqlExpression exposes subquery to builders", $failures);
}
$equality = new Condition(new ConditionDescriptor([
    'sqlexpression' => 'eq',
    'subquery' => new SubqueryDescriptor(['descriptor' => $conditionSubquery, 'arguments' => []]),
]), new Expression(), QuotesEnum::NONE);
$equality->setColumnExpression('t.c');
check(str_contains((string)$equality, 't.c = (SELECT'), 'Equality still wraps the subquery', $failures);

// 18. literal/quoteliteral columns keep their expression and skip the table alias.
$literalColumn = new Column(
    new ColumnDescriptor(['name' => '1 + 1', 'tableAlias' => 't', 'literal' => 1]),
    new Expression(),
    QuotesEnum::DOUBLE_QUOTE
);
checkSame('1 + 1', (string)$literalColumn, 'literal column is not quoted', $failures);
$quotedLiteralColumn = new Column(
    new ColumnDescriptor(['name' => 'col literal', 'tableAlias' => 't', 'quoteliteral' => true]),
    new Expression(),
    QuotesEnum::DOUBLE_QUOTE
);
checkSame('"col literal"', (string)$quotedLiteralColumn, 'quoteliteral column is quoted without alias', $failures);
$normalColumn = new Column(
    new ColumnDescriptor(['name' => 'c', 'tableAlias' => 't']),
    new Expression(),
    QuotesEnum::DOUBLE_QUOTE
);
checkSame('"t"."c"', (string)$normalColumn, 'regular columns still include the alias', $failures);

// 19. SELECT lock modifiers are mapped to real SQL clauses.
$lockBase = $loader->getStatementDescriptors('SELECT/Min')[0]->toArray();
unset($lockBase['type'], $lockBase['metadata']['type']);
foreach ([
    'forUpdate' => 'FOR UPDATE',
    'forShare' => 'FOR SHARE',
    'forNoKeyUpdate' => 'FOR NO KEY UPDATE',
    'forKeyShare' => 'FOR KEY SHARE',
    'lockInShareMode' => 'LOCK IN SHARE MODE',
    'skipLocked' => 'SKIP LOCKED',
    'forKeyNoWait' => 'NOWAIT',
] as $modifier => $clause) {
    checkSame($clause, Keywords::fromName($modifier)->value, "$modifier is mapped", $failures);
    $lockDescriptor = $lockBase;
    $lockDescriptor[$modifier] = true;
    $lockStatement = new SelectStatement(new SelectDescriptor($lockDescriptor), new Expression());
    check(str_ends_with((string)$lockStatement, ' ' . $clause), "$modifier is emitted in SQL", $failures);
}

if ($failures === []) {
    echo "ALL REGRESSION TESTS PASSED\n";
    exit(0);
}

echo "REGRESSION TESTS FAILED:\n";
foreach ($failures as $failure) {
    echo "- $failure\n";
}
exit(1);
