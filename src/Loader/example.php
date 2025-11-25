<?php

declare(strict_types=1);

use Tabula17\Satelles\Omnia\Roga\Loader\XmlFile;
use Tabula17\Satelles\Omnia\Roga\StatementBuilder;
use Tabula17\Satelles\Utilis\Cache\MemcachedStorage;
use Tabula17\Satelles\Utilis\Cache\RedisStorage;
use Tabula17\Satelles\Utilis\Config\RedisConfig;

include_once 'vendor/autoload.php';
$start = microtime(true);
/**
 * Creates a string consisting of a repeated character with an optional newline at the end.
 *
 * @param string $char The character to repeat. Default is '-'.
 * @param int $length The number of times to repeat the character. Default is 100.
 * @param bool $eol Whether to append a newline at the end. Default is true.
 * @return string The generated string.
 */
$separator = static function (string $char = '-', int $length = 100, bool $eol = true): string {
    return str_repeat($char, $length) . ($eol ? PHP_EOL : '');
};

$redisConfig = new RedisConfig([
    'host' => '127.0.0.1',
    'port' => 6379,
    'database' => 0,
]);
try {
// $cacheManager = new MemcachedStorage('roga-db-cache', ['127.0.0.1:11211']);
    $cacheManager = new RedisStorage($redisConfig);

    $loader = new XmlFile(baseDir: __DIR__ . DIRECTORY_SEPARATOR . 'Xml', cacheManager: $cacheManager);

//$xml = 'SELECT/Complex';
$xml = 'SELECT/Basic';
//$xml = 'SELECT/Union';
//$xml = 'EXEC/SPSqlServer';
//$xml = 'INSERT/Basic';
//$xml = 'INSERT/FromSelect';
    $member = 'allowed';
    $memberValues = [1, 2, 4];
//:param_name_to_bind_01
    $params = [
        ':param_1' => true,
        ':param_10' => '100',
        ':param_3' => '9100',
        ':evento' => 8,
        ':param_exists' => 397,
        ':x_param_2' => true,
        ':x_param_not_exists' => 397,
        ':param_name_to_bind_01' => 9865,
        ':param_name_to_bind_02' => 22,
        ':param_name_to_bind_03' => 9863.4125,
        ':param_name_to_bind_04' => 99,
        ':param_name_to_bind_05' => 7812,
        ':param_name_to_bind_06' => 456,
    ];

    $builder = new StatementBuilder(
        statementName: $xml,
        loader: $loader,
        reload: false
    );
    /*
     *     $statement->setValue(':param_1', true);
        $statement->setValue(':param_10', '100');
        //var_dump($statement->getRequiredParams());
        //$statement->setValue(':sinanular', true);
        $statement->setValue(':param_3', '9100');
        //$statement->setValue(':evento', 8);
        $statement->setValue(':param_exists', 397);
        $statement->setValue(':x_param_not_exists', 397);
     */
    $div = $separator('=');
    $subdiv = $separator();

    echo PHP_EOL, PHP_EOL, '   XML: ', $xml, PHP_EOL, '    SEARCH FOR ', implode(',', $memberValues), PHP_EOL, PHP_EOL;
    echo $div;
} catch (\Throwable $e) {
    echo $e->getMessage(), PHP_EOL;
    exit;
}


foreach ($memberValues as $memberValue) {
    try {
            $builder->loadStatementBy($member, $memberValue)?->setValues($params);


        echo 'STATEMENT FOUND FOR MEMBER [', strtoupper($member), '] ::: ', $memberValue, PHP_EOL;
        echo $div;

        foreach ($builder->getMetadata() as $key => $value) {
            echo $key, ' => ', is_array($value) ? implode(', ', $value) : $value, PHP_EOL;
        }
        echo $subdiv;
        foreach ($builder->getRequiredParams() as $param) {
            echo 'REQ PARAM ->', $param::class, ' || ', $param->placeholder, $param->required ? '* :: ' : ' :: ', $param->defaultValue ?? 'S/V', ' :: ', $param->type, "\n";
        }
        echo $subdiv;
        foreach ($builder->getOptionalParams() as $param) {
            echo 'OPT PARAM ->', $param::class, ' || ', $param->placeholder, $param->required ? '* :: ' : ' :: ', $param->defaultValue ?? 'S/V', ' :: ', $param->type, "\n";
        }
        echo $subdiv;

        echo $builder->getPrettyStatement(), PHP_EOL, PHP_EOL;
        echo $subdiv;

        echo 'BINDINGS: ', var_export($builder->getBindings(), true), PHP_EOL, PHP_EOL;
        echo $subdiv;
        //echo var_export($builder->getDescriptorBy($member, $memberValue)->toArray(), true), PHP_EOL;
        echo 'Time: ', microtime(true) - $start, PHP_EOL;
    } catch (\Throwable $e) {
        echo $div;
        echo "[ERR variant ($member) $memberValue]: ", $e->getMessage(), PHP_EOL;
        echo var_export($builder->getDescriptorBy($member, $memberValue), true), PHP_EOL;
        echo $e->getTraceAsString(), PHP_EOL;
        echo PHP_EOL;
    }
    echo $div;
}