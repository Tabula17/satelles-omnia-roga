<?php

declare(strict_types=1);

use Bramus\Monolog\Formatter\ColoredLineFormatter;
use Tabula17\Satelles\Omnia\Roga\Loader\XmlFile;
use Tabula17\Satelles\Omnia\Roga\StatementBuilder;

require __DIR__ . '/../../vendor/autoload.php';

$logger = new Monolog\Logger('roga-db-xml');
$handler = new Monolog\Handler\StreamHandler('php://stdout', Monolog\Logger::DEBUG);
$handler->setFormatter(new ColoredLineFormatter());
$logger->pushHandler($handler);

$start = microtime(true);

try {
    // To enable caching, pass a CacheManagerInterface here, for example:
    // $cacheManager = new RedisStorage(new RedisConfig(['host' => '127.0.0.1', 'port' => 6379, 'database' => 0]));
    $loader = new XmlFile(baseDir: __DIR__ . '/Xml', cacheManager: null, logger: $logger);

    $xml = 'SELECT/Complex';
    $member = 'allowed';
    $memberValues = [1, 2];
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
        ':subinventarios' => '397',
    ];

    $builder = new StatementBuilder(
        statementName: $xml,
        loader: $loader,
        reload: false,
        logger: $logger
    );
} catch (\Throwable $e) {
    $logger->error($e->getMessage(), ['trace' => $e->getTraceAsString()]);
    exit;
}

foreach ($memberValues as $memberValue) {
    try {
        $builder->loadStatementBy($member, $memberValue);
        $builder->setValues($params);

        $logger->info('STATEMENT FOUND FOR MEMBER [' . strtoupper($member) . '] ::: ' . $memberValue);
        foreach ($builder->getMetadata() as $key => $value) {
            $logger->debug('Metadata -> ' . $key . ' => ' . (is_array($value) ? implode(', ', $value) : (string)$value));
        }
        foreach ($builder->getRequiredParams() as $param) {
            $default = $param->defaultValue ?? 'S/V';
            $logger->debug('REQ PARAM -> ' . $param::class . ' || ' . $param->placeholder . '|'
                . ($param->required ? '* :: ' : ' :: ') . '|' . $default . '| :: |' . $param->type);
        }
        foreach ($builder->getOptionalParams() as $param) {
            $default = $param->defaultValue ?? 'S/V';
            $logger->debug('OPT PARAM -> ' . $param::class . ' || ' . $param->placeholder . '|'
                . ($param->required ? '* :: ' : ' :: ') . '|' . $default . '| :: |' . $param->type);
        }

        $logger->info($builder->getPrettyStatement());
        $logger->debug('BINDINGS: ' . var_export($builder->getBindings(), true));
    } catch (\Throwable $e) {
        $logger->error('[ERR variant (' . $member . ') ' . $memberValue . ']: ' . $e->getMessage(), [
            'trace' => $e->getTraceAsString(),
        ]);
    }
}

$logger->debug('Time: ' . (microtime(true) - $start));
