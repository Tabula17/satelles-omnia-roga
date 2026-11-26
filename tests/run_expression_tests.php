<?php
// Simple test runner para Expression::in() y notIn()
require_once __DIR__ . '/../vendor/autoload.php';

use Tabula17\Satelles\Omnia\Roga\Builder\Expression;

$expr = new Expression();

$failures = [];

function assert_eq($expected, $actual, $message, &$failures)
{
    if ($expected !== $actual) {
        $failures[] = [$message, $expected, $actual];
    }
}

// Test: placeholder single
$out = (string)$expr->in('col', ':param');
assert_eq("col IN(:param)", $out, 'IN single placeholder', $failures);

// Test: placeholder array
$out = (string)$expr->in('col', [':p1', ':p2']);
assert_eq("col IN(:p1, :p2)", $out, 'IN multiple placeholders', $failures);

// Test: non-placeholder string is quoted
$out = (string)$expr->in('col', 'abc');
assert_eq("col IN('abc')", $out, 'IN literal quoted', $failures);

// Test: numeric literal is not quoted
$out = (string)$expr->in('col', 123);
assert_eq("col IN(123)", $out, 'IN numeric literal', $failures);

// Test: notIn placeholder
$out = (string)$expr->notIn('col', ':np');
assert_eq("col NOT IN(:np)", $out, 'NOT IN single placeholder', $failures);

// Test: notIn array mixed
$out = (string)$expr->notIn('col', [':np1', 'xyz', 7]);
assert_eq("col NOT IN(:np1, 'xyz', 7)", $out, 'NOT IN mixed', $failures);

if (count($failures) === 0) {
    echo "ALL TESTS PASSED\n";
    exit(0);
}

echo "TESTS FAILED:\n";
foreach ($failures as $f) {
    [$msg, $exp, $act] = $f;
    echo "- $msg\n  expected: $exp\n  actual:   $act\n";
}
exit(1);

