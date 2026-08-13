<?php

namespace App\Services;

use DateTimeInterface;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class ProductionDatabaseSync
{
    public const SOURCE_CONNECTION = 'production_sqlsrv';

    private const SKIP_TABLES = [
        'migrations',
        'sysdiagrams',
        'dtproperties',
    ];

    /**
     * @return array{
     *     source: array{name: string, driver: string, host: string, database: string},
     *     target: array{name: string, driver: string, host: string, database: string},
     *     tables: list<array{table: string, source_rows: int, target_rows: int}>
     * }
     */
    public function preview(): array
    {
        $source = $this->resolveSource();
        $target = $this->resolveTarget();
        $this->assertSafe($source, $target);
        $this->assertReachable($source['name'], $target['name']);

        $sourceDb = DB::connection($source['name']);
        $targetDb = DB::connection($target['name']);
        $tables = [];

        foreach ($this->resolveSharedTables($source['name'], $target['name']) as $table) {
            $tables[] = [
                'table' => $table,
                'source_rows' => (int) $sourceDb->table($table)->count(),
                'target_rows' => (int) $targetDb->table($table)->count(),
            ];
        }

        return [
            'source' => $this->publicConnection($source),
            'target' => $this->publicConnection($target),
            'tables' => $tables,
        ];
    }

    /**
     * @param  callable(string, int): void|null  $onTable
     * @return array{
     *     source: array{name: string, driver: string, host: string, database: string},
     *     target: array{name: string, driver: string, host: string, database: string},
     *     tables: list<array{table: string, rows: int}>
     * }
     */
    public function sync(int $chunkSize = 1000, ?callable $onTable = null): array
    {
        $this->assertChunkSize($chunkSize);

        $source = $this->resolveSource();
        $target = $this->resolveTarget();
        $this->assertSafe($source, $target);
        $this->assertReachable($source['name'], $target['name']);

        $tables = $this->resolveSharedTables($source['name'], $target['name']);
        $targetDb = DB::connection($target['name']);

        $this->disableConstraints($targetDb);

        $copied = [];

        try {
            foreach ($tables as $table) {
                $this->emptyTable($targetDb, $table);
            }

            foreach ($tables as $table) {
                $rows = $this->copyTable($source['name'], $target['name'], $table, $chunkSize);
                $copied[] = [
                    'table' => $table,
                    'rows' => $rows,
                ];

                if ($onTable !== null) {
                    $onTable($table, $rows);
                }
            }

            foreach ($tables as $table) {
                $this->resetIdentity($targetDb, $table);
            }
        } finally {
            $this->enableConstraints($targetDb);
        }

        return [
            'source' => $this->publicConnection($source),
            'target' => $this->publicConnection($target),
            'tables' => $copied,
        ];
    }

    /**
     * @return array{name: string, driver: string, host: string, port: string, database: string}
     */
    public function resolveSource(): array
    {
        return $this->resolveConnection(self::SOURCE_CONNECTION);
    }

    /**
     * @return array{name: string, driver: string, host: string, port: string, database: string}
     */
    public function resolveTarget(): array
    {
        return $this->resolveConnection((string) config('database.default'));
    }

    /**
     * @param  array{name: string, driver: string, host: string, port: string, database: string}  $source
     * @param  array{name: string, driver: string, host: string, port: string, database: string}  $target
     */
    public function assertSafe(array $source, array $target): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('Refusing to pull production data while APP_ENV is production.');
        }

        if ($target['name'] === self::SOURCE_CONNECTION) {
            throw new RuntimeException('Refusing to write to the production connection ['.self::SOURCE_CONNECTION.'].');
        }

        if (str_starts_with($target['name'], 'legacy_')) {
            throw new RuntimeException("Refusing to write to legacy connection [{$target['name']}].");
        }

        if ($target['driver'] === 'sqlite' && ! app()->environment('testing')) {
            throw new RuntimeException('Pulling production data into sqlite is not supported.');
        }

        if ($target['database'] === '' || $source['database'] === '') {
            throw new RuntimeException('Production and target database names must be configured.');
        }

        if ($this->isSameSqlServerDatabase($source, $target)) {
            throw new RuntimeException(
                "Refusing to overwrite production: target SQL Server database [{$target['database']}] matches production."
            );
        }

        if ($this->identitiesMatch($source, $target)) {
            throw new RuntimeException(
                'Refusing to overwrite production: target is the same database as production.'
            );
        }
    }

    /**
     * @return list<string>
     */
    public function resolveSharedTables(string $sourceConnection, string $targetConnection): array
    {
        $sourceTables = $this->tableListing($sourceConnection);
        $targetTables = $this->tableListing($targetConnection);
        $targetLookup = array_fill_keys($targetTables, true);
        $shared = [];

        foreach ($sourceTables as $table) {
            if (isset($targetLookup[$table]) && ! in_array($table, self::SKIP_TABLES, true)) {
                $shared[] = $table;
            }
        }

        sort($shared);

        return $shared;
    }

    /**
     * @return array{name: string, driver: string, host: string, port: string, database: string}
     */
    private function resolveConnection(string $connectionName): array
    {
        $config = config("database.connections.{$connectionName}");

        if (! is_array($config)) {
            throw new RuntimeException("Database connection [{$connectionName}] is not configured.");
        }

        return [
            'name' => $connectionName,
            'driver' => (string) ($config['driver'] ?? ''),
            'host' => (string) ($config['host'] ?? ''),
            'port' => (string) ($config['port'] ?? ''),
            'database' => (string) ($config['database'] ?? ''),
        ];
    }

    /**
     * @param  array{name: string, driver: string, host: string, port: string, database: string}  $connection
     * @return array{name: string, driver: string, host: string, database: string}
     */
    private function publicConnection(array $connection): array
    {
        return [
            'name' => $connection['name'],
            'driver' => $connection['driver'],
            'host' => $connection['host'],
            'database' => $connection['database'],
        ];
    }

    /**
     * @param  array{name: string, driver: string, host: string, port: string, database: string}  $source
     * @param  array{name: string, driver: string, host: string, port: string, database: string}  $target
     */
    private function isSameSqlServerDatabase(array $source, array $target): bool
    {
        return $source['driver'] === 'sqlsrv'
            && $target['driver'] === 'sqlsrv'
            && strcasecmp($source['database'], $target['database']) === 0;
    }

    /**
     * @param  array{name: string, driver: string, host: string, port: string, database: string}  $source
     * @param  array{name: string, driver: string, host: string, port: string, database: string}  $target
     */
    private function identitiesMatch(array $source, array $target): bool
    {
        return $source['driver'] === $target['driver']
            && $this->normalizeHost($source['host']) === $this->normalizeHost($target['host'])
            && strcasecmp($source['database'], $target['database']) === 0;
    }

    private function normalizeHost(string $host): string
    {
        $host = strtolower(trim($host));

        if (in_array($host, ['127.0.0.1', '::1', '(local)', '.', ''], true)) {
            return 'localhost';
        }

        return $host;
    }

    private function assertReachable(string $sourceConnection, string $targetConnection): void
    {
        try {
            DB::connection($sourceConnection)->getPdo();
        } catch (Throwable $exception) {
            throw new RuntimeException(
                "Cannot connect to production [{$sourceConnection}]: {$exception->getMessage()}",
                0,
                $exception
            );
        }

        try {
            DB::connection($targetConnection)->getPdo();
        } catch (Throwable $exception) {
            throw new RuntimeException(
                "Cannot connect to target [{$targetConnection}]: {$exception->getMessage()}",
                0,
                $exception
            );
        }
    }

    private function assertChunkSize(int $chunkSize): void
    {
        if ($chunkSize < 1) {
            throw new RuntimeException('Chunk size must be at least 1.');
        }
    }

    /**
     * @return list<string>
     */
    private function tableListing(string $connectionName): array
    {
        $listing = Schema::connection($connectionName)->getTableListing();

        $tables = [];

        foreach ($listing as $table) {
            $normalized = $this->normalizeTableName((string) $table);

            if ($normalized !== '') {
                $tables[] = $normalized;
            }
        }

        return array_values(array_unique($tables));
    }

    private function normalizeTableName(string $table): string
    {
        $table = trim($table, " \t\n\r\0\x0B[]`\"");

        if (str_contains($table, '.')) {
            $table = (string) last(explode('.', $table));
            $table = trim($table, " \t\n\r\0\x0B[]`\"");
        }

        return $table;
    }

    private function disableConstraints(Connection $connection): void
    {
        match ($connection->getDriverName()) {
            'mysql', 'mariadb' => $connection->statement('SET FOREIGN_KEY_CHECKS=0'),
            'sqlite' => $connection->statement('PRAGMA foreign_keys = OFF'),
            'sqlsrv' => $this->setSqlServerConstraints($connection, enabled: false),
            default => throw new RuntimeException(
                "Unsupported target driver [{$connection->getDriverName()}] for production pull."
            ),
        };
    }

    private function enableConstraints(Connection $connection): void
    {
        match ($connection->getDriverName()) {
            'mysql', 'mariadb' => $connection->statement('SET FOREIGN_KEY_CHECKS=1'),
            'sqlite' => $connection->statement('PRAGMA foreign_keys = ON'),
            'sqlsrv' => $this->setSqlServerConstraints($connection, enabled: true),
            default => null,
        };
    }

    private function setSqlServerConstraints(Connection $connection, bool $enabled): void
    {
        foreach ($this->tableListing($connection->getName()) as $table) {
            $quoted = $this->quoteTable($connection, $table);

            if ($enabled) {
                $connection->statement("ALTER TABLE {$quoted} CHECK CONSTRAINT ALL");
            } else {
                $connection->statement("ALTER TABLE {$quoted} NOCHECK CONSTRAINT ALL");
            }
        }
    }

    private function emptyTable(Connection $connection, string $table): void
    {
        $quoted = $this->quoteTable($connection, $table);

        if (in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
            $connection->statement("TRUNCATE TABLE {$quoted}");

            return;
        }

        $connection->table($table)->delete();
    }

    private function copyTable(string $sourceConnection, string $targetConnection, string $table, int $chunkSize): int
    {
        $sourceDb = DB::connection($sourceConnection);
        $targetDb = DB::connection($targetConnection);
        $columns = $this->sharedColumns($sourceConnection, $targetConnection, $table);

        if ($columns === []) {
            return 0;
        }

        $chunkSize = $this->resolveInsertChunkSize($chunkSize, count($columns), $targetDb->getDriverName());
        $orderColumn = $this->orderColumn($sourceConnection, $table, $columns);
        $hasIdentity = $this->tableHasIdentity($targetDb, $table);
        $copied = 0;
        $lastKey = null;

        $this->setIdentityInsert($targetDb, $table, $hasIdentity);

        try {
            // Keyset pagination avoids SQL Server OFFSET syntax issues on some hosts.
            while (true) {
                $query = $sourceDb->table($table)->orderBy($orderColumn)->limit($chunkSize);

                if ($lastKey !== null) {
                    $query->where($orderColumn, '>', $lastKey);
                }

                $rows = $query->get();

                if ($rows->isEmpty()) {
                    break;
                }

                $payload = [];

                foreach ($rows as $row) {
                    $payload[] = $this->normalizeRow((array) $row, $columns);
                }

                $targetDb->table($table)->insert($payload);
                $copied += count($payload);

                $lastRow = (array) $rows->last();
                $lastLookup = [];

                foreach ($lastRow as $key => $value) {
                    $lastLookup[strtolower((string) $key)] = $value;
                }

                $nextKey = $lastLookup[strtolower($orderColumn)] ?? null;

                if ($nextKey === null || $nextKey === $lastKey) {
                    break;
                }

                $lastKey = $nextKey;
            }
        } finally {
            $this->setIdentityInsert($targetDb, $table, false);
        }

        return $copied;
    }

    /**
     * SQL Server allows at most 2100 parameters per prepared statement.
     * Each inserted row consumes one parameter per column.
     */
    private function resolveInsertChunkSize(int $requestedChunkSize, int $columnCount, string $driver): int
    {
        $chunkSize = max(1, $requestedChunkSize);

        if ($columnCount < 1) {
            return $chunkSize;
        }

        if ($driver === 'sqlsrv') {
            return max(1, min($chunkSize, intdiv(2000, $columnCount)));
        }

        return $chunkSize;
    }

    /**
     * @return list<string>
     */
    private function sharedColumns(string $sourceConnection, string $targetConnection, string $table): array
    {
        $targetMap = [];

        foreach (Schema::connection($targetConnection)->getColumnListing($table) as $column) {
            $targetMap[strtolower((string) $column)] = (string) $column;
        }

        $shared = [];

        foreach (Schema::connection($sourceConnection)->getColumnListing($table) as $column) {
            $key = strtolower((string) $column);

            if (isset($targetMap[$key])) {
                $shared[] = $targetMap[$key];
            }
        }

        return $shared;
    }

    /**
     * @param  list<string>  $columns
     */
    private function orderColumn(string $connectionName, string $table, array $columns): string
    {
        $listing = Schema::connection($connectionName)->getColumnListing($table);

        foreach ($listing as $column) {
            if (strtolower((string) $column) === 'id') {
                return (string) $column;
            }
        }

        return $columns[0];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $columns
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row, array $columns): array
    {
        $lookup = [];

        foreach ($row as $key => $value) {
            $lookup[strtolower((string) $key)] = $value;
        }

        $normalized = [];

        foreach ($columns as $column) {
            $value = $lookup[strtolower($column)] ?? null;

            if ($value instanceof DateTimeInterface) {
                $value = $value->format('Y-m-d H:i:s');
            }

            if (is_resource($value)) {
                $value = stream_get_contents($value);
            }

            $normalized[$column] = $value;
        }

        return $normalized;
    }

    private function tableHasIdentity(Connection $connection, string $table): bool
    {
        if ($connection->getDriverName() !== 'sqlsrv') {
            return false;
        }

        $result = $connection->selectOne(
            'SELECT TOP 1 1 as has_identity
             FROM sys.columns c
             INNER JOIN sys.tables t ON c.object_id = t.object_id
             INNER JOIN sys.schemas s ON t.schema_id = s.schema_id
             WHERE s.name = ? AND t.name = ? AND c.is_identity = 1',
            ['dbo', $this->normalizeTableName($table)]
        );

        return $result !== null;
    }

    private function setIdentityInsert(Connection $connection, string $table, bool $enabled): void
    {
        if ($connection->getDriverName() !== 'sqlsrv' || ! $this->tableHasIdentity($connection, $table)) {
            return;
        }

        $state = $enabled ? 'ON' : 'OFF';

        $connection->unprepared('SET IDENTITY_INSERT '.$this->sqlServerQuoteTable($table).' '.$state);
    }

    private function sqlServerQuoteTable(string $table): string
    {
        $safe = str_replace(']', ']]', $this->normalizeTableName($table));

        return "[dbo].[{$safe}]";
    }

    private function resetIdentity(Connection $connection, string $table): void
    {
        if (! $this->tableHasIdentity($connection, $table)) {
            return;
        }

        $safeTable = str_replace("'", "''", $table);
        $connection->statement("DBCC CHECKIDENT ('{$safeTable}', RESEED)");
    }

    private function quoteTable(Connection $connection, string $table): string
    {
        return $connection->getQueryGrammar()->wrapTable($table);
    }
}
