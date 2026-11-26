<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Psr\Log\AbstractLogger;
use Tabula17\Satelles\Omnia\Roga\Exception\ConfigException;
use Tabula17\Satelles\Omnia\Roga\Exception\InvalidArgumentException;
use Tabula17\Satelles\Omnia\Roga\Loader\XmlFile;
use Tabula17\Satelles\Utilis\Cache\CacheManagerInterface;

final class CollectingLogger extends AbstractLogger
{
    public array $records = [];

    public function log($level, $message, array $context = []): void
    {
        $this->records[] = [(string)$level, (string)$message];
    }
}

final class ArrayCacheStub implements CacheManagerInterface
{
    public array $items = [];

    public function get(string $key) { return $this->items[$key] ?? false; }
    public function set(string $key, mixed $value, ?int $ttl = null) { $this->items[$key] = $value; }
    public function has(string $key): bool { return array_key_exists($key, $this->items); }
    public function delete(string $key) { unset($this->items[$key]); }
    public function clear() { $this->items = []; }
}

$root = sys_get_temp_dir() . '/roga-loader-tests';
foreach (['base1', 'base2'] as $dir) {
    @mkdir($root . '/' . $dir . '/SELECT', 0777, true);
    file_put_contents($root . '/' . $dir . '/SELECT/SingleAllowed.xml', <<<XML
<statements>
    <statement>
        <type>select</type>
        <metadata><allowed>1</allowed><version>1.0</version></metadata>
        <from><table>child_$dir</table></from>
    </statement>
</statements>
XML);
    file_put_contents($root . '/' . $dir . '/SELECT/Parent.xml', <<<XML
<statements>
    <statement>
        <type>select</type>
        <metadata><allowed>1</allowed><version>1.0</version></metadata>
        <from>
            <table>parent_$dir</table>
            <columns>
                <column>
                    <name>c</name>
                    <subquery><path>SELECT/SingleAllowed</path></subquery>
                </column>
            </columns>
        </from>
    </statement>
</statements>
XML);
}

$failures = [];
function check(bool $condition, string $message, array &$failures): void
{
    if (!$condition) {
        $failures[] = $message;
    }
}

// A single scalar <allowed> in a subquery must not discard the parent.
$loader = new XmlFile($root . '/base1');
$descriptors = $loader->getStatementDescriptors('SELECT/Parent');
check(count($descriptors) === 1, 'Scalar allowed in subquery loads the parent', $failures);
if ($descriptors !== []) {
    $array = $descriptors[0]->toArray();
    check(
        ($array['from']['columns'][0]['subquery']['descriptor']['metadata']['allowed'] ?? null) === '1',
        'Subquery inherits the tenant identifier',
        $failures
    );
}

// A shared cache backend must not mix definitions from different baseDirs.
$cache = new ArrayCacheStub();
$a = (new XmlFile($root . '/base1', $cache))->getStatementDescriptors('SELECT/Parent')[0]->toArray()['from']['table'] ?? null;
$b = (new XmlFile($root . '/base2', $cache))->getStatementDescriptors('SELECT/Parent')[0]->toArray()['from']['table'] ?? null;
check($a === 'parent_base1' && $b === 'parent_base2', 'Cache keys are scoped to baseDir', $failures);

try {
    $loader->getStatementDescriptors('../base2/SELECT/Parent');
    check(false, 'Path traversal is rejected', $failures);
} catch (InvalidArgumentException) {
    check(true, 'Path traversal is rejected', $failures);
}

file_put_contents($root . '/base1/SELECT/Broken.xml', '<statements><statement>');
try {
    (new XmlFile($root . '/base1'))->getStatementDescriptors('SELECT/Broken');
    check(false, 'Malformed XML is rejected', $failures);
} catch (ConfigException) {
    check(true, 'Malformed XML is rejected', $failures);
}

file_put_contents($root . '/base1/SELECT/LoopA.xml', '<statements><statement><type>select</type><metadata><allowed>1</allowed></metadata><from><table>a</table><columns><column><name>c</name><subquery><path>SELECT/LoopB</path></subquery></column></columns></from></statement></statements>');
file_put_contents($root . '/base1/SELECT/LoopB.xml', '<statements><statement><type>select</type><metadata><allowed>1</allowed></metadata><from><table>b</table><columns><column><name>c</name><subquery><path>SELECT/LoopA</path></subquery></column></columns></from></statement></statements>');
try {
    (new XmlFile($root . '/base1'))->getStatementDescriptors('SELECT/LoopA');
    check(false, 'Circular subqueries are rejected', $failures);
} catch (ConfigException) {
    check(true, 'Circular subqueries are rejected', $failures);
}

file_put_contents($root . '/base1/SELECT/SkipParent.xml', <<<'XML'
<statements>
    <statement>
        <type>select</type>
        <metadata><allowed>1</allowed><allowed>3</allowed><version>1.0</version></metadata>
        <from>
            <table>skip_parent</table>
            <columns>
                <column>
                    <name>c</name>
                    <subquery><path>SELECT/SingleAllowed</path></subquery>
                </column>
            </columns>
        </from>
    </statement>
</statements>
XML);
$logger = new CollectingLogger();
$skipped = (new XmlFile($root . '/base1', null, $logger))->getStatementDescriptors('SELECT/SkipParent');
check(count($skipped) === 1, 'Tenant without a subquery variant is skipped', $failures);
$warnings = array_values(array_filter(
    $logger->records,
    static fn(array $record): bool => $record[0] === 'warning'
));
check(count($warnings) === 1 && str_contains($warnings[0][1], '"allowed"="3"'), 'Missing subquery variant is reported precisely', $failures);
$messages = implode(' ', array_column($logger->records, 1));
check(!str_contains($messages, 'No se pudo resolver'), 'Missing variant is not reported as an unresolved path', $failures);

if ($failures === []) {
    echo "ALL LOADER TESTS PASSED\n";
    exit(0);
}

echo "LOADER TESTS FAILED:\n";
foreach ($failures as $failure) {
    echo "- $failure\n";
}
exit(1);
