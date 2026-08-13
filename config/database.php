<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for database operations. This is
    | the connection which will be utilized unless another connection
    | is explicitly specified when you execute a query / statement.
    |
    | DB_PROFILE options:
    |   1 = Home (local MySQL)
    |   2 = Office (SQL Server)
    |
    */

    'default' => env('APP_ENV') === 'testing'
        ? env('DB_CONNECTION', 'sqlite')
        : match (env('DB_PROFILE', '1')) {
            '2' => 'sqlsrv',  // Office - SQL Server
            '1' => 'mysql',   // Home - MySQL
            default => env('DB_CONNECTION', 'sqlite'),
        },

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Below are all of the database connections defined for your application.
    | An example configuration is provided for each database system which
    | is supported by Laravel. You're free to add / remove connections.
    |
    */

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
            'transaction_mode' => 'DEFERRED',
        ],

        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_MYSQL_HOST', env('DB_HOST', '127.0.0.1')),
            'port' => env('DB_MYSQL_PORT', env('DB_PORT', '3306')),
            'database' => env('DB_MYSQL_DATABASE', env('DB_DATABASE', 'laravel')),
            'username' => env('DB_MYSQL_USERNAME', env('DB_USERNAME', 'root')),
            'password' => env('DB_MYSQL_PASSWORD', env('DB_PASSWORD', '')),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                (PHP_VERSION_ID >= 80500 ? \Pdo\Mysql::ATTR_SSL_CA : \PDO::MYSQL_ATTR_SSL_CA) => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'mariadb' => [
            'driver' => 'mariadb',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                (PHP_VERSION_ID >= 80500 ? \Pdo\Mysql::ATTR_SSL_CA : \PDO::MYSQL_ATTR_SSL_CA) => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'url' => env('DB_URL'),
            'host' => env('DB_SQLSRV_HOST', env('DB_HOST', 'localhost')),
            'port' => env('DB_SQLSRV_PORT', env('DB_PORT', '1433')),
            'database' => env('DB_SQLSRV_DATABASE', env('DB_DATABASE', 'laravel')),
            'username' => env('DB_SQLSRV_USERNAME', env('DB_USERNAME', 'sa')),
            'password' => env('DB_SQLSRV_PASSWORD', env('DB_PASSWORD', '')),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'encrypt' => env('DB_SQLSRV_ENCRYPT', env('DB_ENCRYPT', 'yes')),
            'trust_server_certificate' => env('DB_SQLSRV_TRUST_SERVER_CERTIFICATE', env('DB_TRUST_SERVER_CERTIFICATE', 'false')),
        ],

        // Production SQL Server (read-only source for db:pull-production).
        'production_sqlsrv' => [
            'driver' => 'sqlsrv',
            'host' => env('PROD_DB_HOST', env('DB_SQLSRV_HOST', env('DB_HOST', 'localhost'))),
            'port' => env('PROD_DB_PORT', env('DB_SQLSRV_PORT', env('DB_PORT', '1433'))),
            'database' => env('PROD_DB_DATABASE', 'spfi_ms'),
            'username' => env('PROD_DB_USERNAME', env('DB_SQLSRV_USERNAME', env('DB_USERNAME', 'sa'))),
            'password' => env('PROD_DB_PASSWORD', env('DB_SQLSRV_PASSWORD', env('DB_PASSWORD', ''))),
            'charset' => env('PROD_DB_CHARSET', env('DB_CHARSET', 'utf8')),
            'prefix' => '',
            'prefix_indexes' => true,
            'encrypt' => env('PROD_DB_ENCRYPT', env('DB_SQLSRV_ENCRYPT', env('DB_ENCRYPT', 'yes'))),
            'trust_server_certificate' => env('PROD_DB_TRUST_SERVER_CERTIFICATE', env('DB_SQLSRV_TRUST_SERVER_CERTIFICATE', env('DB_TRUST_SERVER_CERTIFICATE', 'false'))),
        ],

        // Legacy DB koneksi 1 (mis. sistem lama utama).
        'legacy_sqlsrv_1' => [
            'driver' => 'sqlsrv',
            'host' => env('LEGACY_DB1_HOST', '127.0.0.1'),
            'port' => env('LEGACY_DB1_PORT', '1433'),
            'database' => env('LEGACY_DB1_DATABASE', ''),
            'username' => env('LEGACY_DB1_USERNAME', ''),
            'password' => env('LEGACY_DB1_PASSWORD', ''),
            'charset' => env('LEGACY_DB1_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'encrypt' => env('LEGACY_DB1_ENCRYPT', 'no'),
            'trust_server_certificate' => env('LEGACY_DB1_TRUST_SERVER_CERTIFICATE', true),
        ],

        // Legacy DB koneksi 2 (mis. sistem lama lain).
        'legacy_sqlsrv_2' => [
            'driver' => 'sqlsrv',
            'host' => env('LEGACY_DB2_HOST', '127.0.0.1'),
            'port' => env('LEGACY_DB2_PORT', '1433'),
            'database' => env('LEGACY_DB2_DATABASE', ''),
            'username' => env('LEGACY_DB2_USERNAME', ''),
            'password' => env('LEGACY_DB2_PASSWORD', ''),
            'charset' => env('LEGACY_DB2_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'encrypt' => env('LEGACY_DB2_ENCRYPT', 'no'),
            'trust_server_certificate' => env('LEGACY_DB2_TRUST_SERVER_CERTIFICATE', true),
        ],

        // Legacy DB koneksi 3 (mis. sistem lama lain).
        'legacy_sqlsrv_3' => [
            'driver' => 'sqlsrv',
            'host' => env('LEGACY_DB3_HOST', '127.0.0.1'),
            'port' => env('LEGACY_DB3_PORT', '1433'),
            'database' => env('LEGACY_DB3_DATABASE', ''),
            'username' => env('LEGACY_DB3_USERNAME', ''),
            'password' => env('LEGACY_DB3_PASSWORD', ''),
            'charset' => env('LEGACY_DB3_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'encrypt' => env('LEGACY_DB3_ENCRYPT', 'no'),
            'trust_server_certificate' => env('LEGACY_DB3_TRUST_SERVER_CERTIFICATE', true),
        ],

        // Legacy DB koneksi 4 (casualtimekeeping — sumber data employee & employee_department).
        'legacy_sqlsrv_4' => [
            'driver' => 'sqlsrv',
            'host' => env('LEGACY_DB4_HOST', '127.0.0.1'),
            'port' => env('LEGACY_DB4_PORT', '1433'),
            'database' => env('LEGACY_DB4_DATABASE', ''),
            'username' => env('LEGACY_DB4_USERNAME', ''),
            'password' => env('LEGACY_DB4_PASSWORD', ''),
            'charset' => env('LEGACY_DB4_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'encrypt' => env('LEGACY_DB4_ENCRYPT', 'no'),
            'trust_server_certificate' => env('LEGACY_DB4_TRUST_SERVER_CERTIFICATE', true),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run on the database.
    |
    */

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Snapshot Tooling
    |--------------------------------------------------------------------------
    |
    | Paths to native database CLI tools used by db:snapshot / db:restore.
    | Leave null to auto-detect common Windows install locations.
    |
    */

    'snapshot' => [
        'mysql_bin_path' => env('MYSQL_BIN_PATH'),
        'sqlserver_bin_path' => env('SQLSERVER_BIN_PATH'),
        'sqlserver_path' => env('SQLSERVER_SNAPSHOT_PATH'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer body of commands than a typical key-value system
    | such as Memcached. You may define your connection settings here.
    |
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')).'-database-'),
            'persistent' => env('REDIS_PERSISTENT', false),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

    ],

];
