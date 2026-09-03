<?php

declare(strict_types=1);

use Bramus\Monolog\Formatter\ColoredLineFormatter;
use Tabula17\Satelles\Omnia\Roga\Loader\XmlFile;
use Tabula17\Satelles\Omnia\Roga\StatementBuilder;
use Tabula17\Satelles\Utilis\Cache\MemcachedStorage;
use Tabula17\Satelles\Utilis\Cache\RedisStorage;
use Tabula17\Satelles\Utilis\Config\RedisConfig;

include_once 'vendor/autoload.php';
$logger = new Monolog\Logger('roga-db-xml');
$handler = new Monolog\Handler\StreamHandler('php://stdout', Monolog\Logger::DEBUG);
$handler->setFormatter(new ColoredLineFormatter());
$logger->pushHandler($handler);
$start = microtime(true);
$redisConfig = new RedisConfig([
    'host' => '127.0.0.1',
    'port' => 6379,
    'database' => 0,
]);
try {
// $cacheManager = new MemcachedStorage('roga-db-cache', ['127.0.0.1:11211']);
    $cacheManager = new RedisStorage($redisConfig);

    $loader = new XmlFile(baseDir: __DIR__ . DIRECTORY_SEPARATOR . 'Xml', cacheManager: $cacheManager, logger: $logger);

    $xml = 'SELECT/Complex';
//$xml = 'SELECT/Basic';
//$xml = 'SELECT/Union';
//$xml = 'EXEC/SPSqlServer';
//$xml = 'EXEC/Expedicion';
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
        ':subinventarios' => '397'
    ];

    $builder = new StatementBuilder(
        statementName: $xml,
        loader: $loader,
        reload: false,
        logger: $logger
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
    $logger->debug('XML LOADED, SEARCH FOR ' . implode(',', $memberValues));
} catch (\Throwable $e) {
    $logger->error($e->getMessage(), ['trace' => $e->getTraceAsString()]);
    exit;
}


foreach ($memberValues as $memberValue) {
    try {
        $builder->loadStatementBy($member, $memberValue)?->setValues($params);


        $logger->info('STATEMENT FOUND FOR MEMBER ['. strtoupper($member).'] ::: '. $memberValue);

        foreach ($builder->getMetadata() as $key => $value) {
            $logger->debug('Metadata ->'.$key. ' => '. (is_array($value) ? implode(', ', $value) : $value));
        }
        foreach ($builder->getRequiredParams() as $param) {
            $logger->debug('REQ PARAM ->' . $param::class . ' || ' . $param->placeholder . '|' . ($param->required ? '* :: ' : ' :: ') . '|' . $param->defaultValue ?? 'S/V'. '|' . ' :: '. '|' . $param->type);
        }
        foreach ($builder->getOptionalParams() as $param) {
            $logger->debug('OPT PARAM ->' . $param::class . ' || ' . $param->placeholder . '|' . ($param->required ? '* :: ' : ' :: ') . '|' . $param->defaultValue ?? 'S/V'. '|' . ' :: '. '|' . $param->type);
        }

        $logger->info($builder->getPrettyStatement());
        $logger->debug('BINDINGS: '. var_export($builder->getBindings(), true));
        $logger->debug('Time: '. microtime(true) - $start);
    } catch (\Throwable $e) {
        $logger->error("[ERR variant ($member) $memberValue]: " . $e->getMessage(), [
            'trace' => $e->getTraceAsString(),
            'descriptor' => $builder->getDescriptorBy($member, $memberValue),
            'bindings' => $builder->getBindings(),
        ]);
    }
}