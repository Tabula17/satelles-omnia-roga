## XVII: 🛰️ satelles-omnia-roga
![PHP Version](https://img.shields.io/badge/PHP-8.4%2B-blue)
![License](https://img.shields.io/github/license/Tabula17/satelles-omnia-roga)
![Last commit](https://img.shields.io/github/last-commit/Tabula17/satelles-omnia-roga)

SQL statement builder/loader for PHP. It lets you define query descriptors in XML and build parametrized SQL statements at runtime by selecting the appropriate variant from metadata. Useful for keeping SQL outside of code while still offering a strongly-typed builder API.

This is a library package intended to be used from your PHP application. An example script is included to demonstrate usage.

#### Overview

- Load statement descriptors from XML files organized by operation (SELECT, INSERT, UPDATE, DELETE, EXEC).
- Select a variant of a statement by metadata (e.g., `allowed`, `client`, `variant`).
- Build and pretty-print SQL; inspect required/optional parameters and final bindings.
- Optional caching of parsed XML using Redis or Memcached (via `xvii/satelles-utilis-proelio`).
- Swoole\Server derived class for building and executing SQL statements on the fly in a multithreaded environment.

#### Tech stack

- Language: PHP (>= 8.4)
- Package manager: Composer
- Type of project: PHP library (no framework)
- Notable runtime extensions (required): `ext-zip`, `ext-fileinfo`, `ext-swoole` (>= 6.0), `ext-dom`, `ext-simplexml`, `ext-pdo`
- Optional extensions (suggested): `ext-redis`, `ext-memcached`

#### Requirements

From `composer.json`:

- PHP 8.4+
- PHP extensions: zip, fileinfo, swoole>=6.0, dom, simplexml, pdo
- Optional for caching: redis or memcached

Library dependencies (selected):

- `xvii/satelles-utilis-proelio` (utilities, caching, config)

Other dependencies (optionals):

- `monolog/monolog` (logging)

#### Installation

Install via Composer:

```
composer require xvii/satelles-omnia-roga
```

If you plan to use caching, also ensure you have one of the extensions installed and enabled:

- Redis: `pecl install redis` and enable in `php.ini`
- Memcached: `pecl install memcached` and enable in `php.ini`

#### Project structure

Key paths in this repo:

- `src/` — library source code under namespace `Tabula17\Satelles\Omnia\Roga`
  - `Builder/` — statement builders (Select/Insert/Update/Delete/Union) and expressions
  - `Descriptor/` — descriptor classes representing parsed XML statements
  - `Loader/` — loaders, e.g., `Loader\XmlFile`
  - `Loader/Xml/` — example XML statement descriptors grouped by operation
  - `Database/` — enums and connection/pool collections
  - `Collection/`, `Exception/` — internal collections and exceptions
- `tests/` — lightweight test script(s)
- `vendor/` — Composer dependencies

#### Usage

Minimal example using the XML loader and statement builder. The repository contains a runnable example at `src/Loader/example.php` which you can adapt; below is a simplified version:

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use Tabula17\Satelles\Omnia\Roga\StatementBuilder;
use Tabula17\Satelles\Omnia\Roga\Loader\XmlFile;
use Tabula17\Satelles\Utilis\Cache\RedisStorage;
use Tabula17\Satelles\Utilis\Config\RedisConfig;

// Optional caching of parsed XML using Redis (you can also use MemcachedStorage)
$redisConfig = new RedisConfig([
    'host' => '127.0.0.1',
    'port' => 6379,
    'database' => 0,
]);

$cache = new RedisStorage($redisConfig); // or null to disable caching

// Base directory containing XML descriptors (see src/Loader/Xml)
$loader = new XmlFile(baseDir: __DIR__ . '/src/Loader/Xml', cacheManager: $cache);

// Choose one statement by name (folder/file path without extension)
$statementName = 'SELECT/Basic';

// Build and select a variant by metadata (e.g., "allowed")
$builder = new StatementBuilder(statementName: $statementName, loader: $loader);
$builder->loadStatementBy('allowed', 1); // or other metadata keys like 'client', 'variant'

// Optionally set parameter values
$builder->setValues([
    ':param_1' => true,
    ':param_10' => '100',
]);

// Get SQL and bindings
$sql = $builder->getPrettyStatement();
$bindings = $builder->getBindings();

echo $sql, PHP_EOL;
var_export($bindings);
```

See and run the comprehensive example:

```
php src/Loader/example.php
```

Notes:
- The example toggles different XML statements by changing `$xml` (e.g., `SELECT/Union`, `INSERT/Basic`, `EXEC/SPSqlServer`).
- Ensure your cache backend is running if you use `RedisStorage` or `MemcachedStorage`.

#### Scripts and entry points

- This library defines no Composer scripts in its own `composer.json`.
- Primary entry points are library classes; there is no CLI entry point provided by this package.
- Example/demo script: `src/Loader/example.php` (invoked with `php src/Loader/example.php`).

#### Environment variables and configuration

There are no environment variables required by the library itself. If you use Redis or Memcached caching, configure them via their respective config objects from `xvii/satelles-utilis-proelio`, e.g., `RedisConfig` accepts an array with `host`, `port`, and `database`.

TODO:
- Document a standardized way to configure cache backends via environment variables or `.env` if/when added.

#### Running tests

There is a lightweight test script for the expression builder:

```
php tests/run_expression_tests.php
```

PHPUnit is not configured for this repository at the moment for the library code itself. Some vendor packages include their own PHPUnit setup, but those are not meant to be run from this repository.

TODO:
- Add PHPUnit configuration and unit tests for the library.

#### Requirements recap

Make sure your environment satisfies:

- PHP 8.4+
- Enabled extensions: zip, fileinfo, swoole>=6.0, dom, simplexml, pdo
- Optional: redis or memcached if you enable caching

#### Development

After cloning:

```
composer install
```

Useful paths while exploring:

- XML descriptor samples: `src/Loader/Xml/**`
- Builder API entry: `src/StatementBuilder.php`
- XML loader: `src/Loader/XmlFile.php`

#### License

MIT License. See `composer.json` (`license: MIT`).

#### Links

- Homepage: https://github.com/Tabula17/satelles-omnia-roga

#### Changelog / Roadmap

TODO:
- Provide CHANGELOG and roadmap once releases are tagged.
