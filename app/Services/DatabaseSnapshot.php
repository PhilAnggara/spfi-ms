<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;

class DatabaseSnapshot
{
    private const SNAPSHOT_DIR = 'db-snapshots';

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(): array
    {
        $snapshots = [];

        foreach ($this->manifestPaths() as $manifestPath) {
            $manifest = json_decode(File::get($manifestPath), true);

            if (is_array($manifest)) {
                $snapshots[] = $manifest;
            }
        }

        usort($snapshots, fn (array $a, array $b): int => strcmp((string) $b['created_at'], (string) $a['created_at']));

        return $snapshots;
    }

    /**
     * @return array<string, mixed>
     */
    public function create(?string $name = null): array
    {
        $connection = $this->resolveDevConnection();
        $driver = (string) $connection['driver'];
        $database = (string) $connection['database'];
        $timestamp = now()->format('Y-m-d_His');
        $snapshotName = $name ?: $timestamp;
        $extension = $driver === 'mysql' ? 'sql' : 'bak';
        $fileName = "{$snapshotName}_{$driver}.{$extension}";
        $backupPath = $this->resolveBackupPath($connection, $fileName);

        $this->ensureSnapshotDirectory();

        match ($driver) {
            'mysql' => $this->createMysqlSnapshot($connection, $backupPath),
            'sqlsrv' => $this->createSqlServerSnapshot($connection, $backupPath),
            default => throw new RuntimeException("Unsupported database driver [{$driver}] for snapshots."),
        };

        $this->assertBackupExists($connection, $backupPath);

        $manifest = [
            'name' => $snapshotName,
            'connection' => $connection['name'],
            'driver' => $driver,
            'database' => $database,
            'file' => $fileName,
            'backup_path' => $backupPath,
            'created_at' => now()->toIso8601String(),
            'size_bytes' => $this->resolveSnapshotSize($backupPath),
        ];

        File::put(
            $this->manifestPathFor($fileName),
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        return $manifest;
    }

    /**
     * @return array<string, mixed>
     */
    public function restore(?string $name = null): array
    {
        $manifest = $name === null
            ? $this->latestManifest()
            : $this->findManifestByName($name);

        if ($manifest === null) {
            throw new RuntimeException('No snapshot found to restore.');
        }

        $connection = $this->resolveDevConnection();
        $driver = (string) $connection['driver'];
        $backupPath = $this->resolveManifestBackupPath($manifest);

        if ((string) $manifest['driver'] !== $driver) {
            throw new RuntimeException(
                "Snapshot driver [{$manifest['driver']}] does not match current connection driver [{$driver}]."
            );
        }

        match ($driver) {
            'mysql' => $this->restoreMysqlSnapshot($connection, $backupPath),
            'sqlsrv' => $this->restoreSqlServerSnapshot($connection, $backupPath),
            default => throw new RuntimeException("Unsupported database driver [{$driver}] for restore."),
        };

        return $manifest;
    }

    /**
     * @return array{name: string, driver: string, database: string}
     */
    public function currentTarget(): array
    {
        $connection = $this->resolveDevConnection();

        return [
            'name' => $connection['name'],
            'driver' => $connection['driver'],
            'database' => $connection['database'],
        ];
    }

    /**
     * @return array{name: string, driver: string, database: string, host: string, port: string, username: string, password: string}
     */
    private function resolveDevConnection(): array
    {
        $connectionName = (string) config('database.default');

        if (str_starts_with($connectionName, 'legacy_')) {
            throw new RuntimeException(
                "Refusing to snapshot or restore legacy connection [{$connectionName}]. Only the dev default connection is allowed."
            );
        }

        if ($connectionName === 'sqlite') {
            throw new RuntimeException('Snapshots are only supported for mysql and sqlsrv dev connections.');
        }

        $config = config("database.connections.{$connectionName}");

        if (! is_array($config)) {
            throw new RuntimeException("Database connection [{$connectionName}] is not configured.");
        }

        $driver = (string) ($config['driver'] ?? '');

        if (! in_array($driver, ['mysql', 'sqlsrv'], true)) {
            throw new RuntimeException("Snapshots are only supported for mysql and sqlsrv, not [{$driver}].");
        }

        return [
            'name' => $connectionName,
            'driver' => $driver,
            'database' => (string) ($config['database'] ?? ''),
            'host' => (string) ($config['host'] ?? '127.0.0.1'),
            'port' => (string) ($config['port'] ?? ($driver === 'mysql' ? '3306' : '1433')),
            'username' => (string) ($config['username'] ?? ''),
            'password' => (string) ($config['password'] ?? ''),
        ];
    }

    /**
     * @param  array{name: string, driver: string, database: string, host: string, port: string, username: string, password: string}  $connection
     */
    private function resolveBackupPath(array $connection, string $fileName): string
    {
        if ($connection['driver'] === 'mysql') {
            return $this->snapshotDirectory().DIRECTORY_SEPARATOR.$fileName;
        }

        $configuredPath = config('database.snapshot.sqlserver_path');

        if (is_string($configuredPath) && $configuredPath !== '') {
            return $this->joinPath($configuredPath, $fileName);
        }

        if ($this->isLocalSqlServerHost($connection['host'])) {
            return $this->snapshotDirectory().DIRECTORY_SEPARATOR.$fileName;
        }

        throw new RuntimeException(
            'Remote SQL Server requires SQLSERVER_SNAPSHOT_PATH in .env. '.
            'Set a folder path on the SQL Server host that the SQL Server service can write to '.
            '(for example D:\\SqlBackups or \\\\fileserver\\share\\db-snapshots).'
        );
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function resolveManifestBackupPath(array $manifest): string
    {
        $backupPath = (string) ($manifest['backup_path'] ?? '');

        if ($backupPath !== '') {
            return $backupPath;
        }

        $fileName = (string) ($manifest['file'] ?? '');

        if ($fileName === '') {
            throw new RuntimeException('Snapshot manifest is missing backup file information.');
        }

        $localPath = $this->snapshotDirectory().DIRECTORY_SEPARATOR.$fileName;

        if (! File::exists($localPath)) {
            throw new RuntimeException("Snapshot file not found: {$localPath}");
        }

        return $localPath;
    }

    /**
     * @param  array{name: string, driver: string, database: string, host: string, port: string, username: string, password: string}  $connection
     */
    private function assertBackupExists(array $connection, string $backupPath): void
    {
        if ($connection['driver'] === 'sqlsrv' && ! $this->isBackupPathAccessibleFromClient($backupPath)) {
            return;
        }

        if (! File::exists($backupPath)) {
            throw new RuntimeException(
                "Snapshot file was not created at [{$backupPath}]. ".
                'For remote SQL Server, ensure SQLSERVER_SNAPSHOT_PATH exists on the server and is writable by the SQL Server service account.'
            );
        }
    }

    private function isBackupPathAccessibleFromClient(string $backupPath): bool
    {
        if (File::exists($backupPath)) {
            return true;
        }

        $directory = dirname($backupPath);

        return $directory !== '' && File::isDirectory($directory);
    }

    private function resolveSnapshotSize(string $backupPath): ?int
    {
        return File::exists($backupPath) ? File::size($backupPath) : null;
    }

    /**
     * @param  array{name: string, driver: string, database: string, host: string, port: string, username: string, password: string}  $connection
     */
    private function createMysqlSnapshot(array $connection, string $filePath): void
    {
        $command = [
            $this->mysqlBinary('mysqldump'),
            '--host='.$connection['host'],
            '--port='.$connection['port'],
            '--user='.$connection['username'],
            '--result-file='.$filePath,
            '--single-transaction',
            '--routines',
            '--triggers',
            $connection['database'],
        ];

        if ($connection['password'] !== '') {
            $command[] = '--password='.$connection['password'];
        }

        $this->runProcess($command);
    }

    /**
     * @param  array{name: string, driver: string, database: string, host: string, port: string, username: string, password: string}  $connection
     */
    private function restoreMysqlSnapshot(array $connection, string $filePath): void
    {
        if (! File::exists($filePath)) {
            throw new RuntimeException("Snapshot file not found: {$filePath}");
        }

        $command = [
            $this->mysqlBinary('mysql'),
            '--host='.$connection['host'],
            '--port='.$connection['port'],
            '--user='.$connection['username'],
            $connection['database'],
        ];

        if ($connection['password'] !== '') {
            $command[] = '--password='.$connection['password'];
        }

        $process = new Process($command, null, null, File::get($filePath));
        $process->setTimeout(null);
        $this->runProcessInstance($process);
    }

    /**
     * @param  array{name: string, driver: string, database: string, host: string, port: string, username: string, password: string}  $connection
     */
    private function createSqlServerSnapshot(array $connection, string $filePath): void
    {
        $database = $this->quoteSqlServerIdentifier($connection['database']);
        $escapedPath = str_replace("'", "''", $filePath);
        $query = "BACKUP DATABASE {$database} TO DISK = N'{$escapedPath}' WITH FORMAT, INIT, STATS = 10";

        $this->runSqlServerQuery($connection, $query);
    }

    /**
     * @param  array{name: string, driver: string, database: string, host: string, port: string, username: string, password: string}  $connection
     */
    private function restoreSqlServerSnapshot(array $connection, string $filePath): void
    {
        $database = $this->quoteSqlServerIdentifier($connection['database']);
        $escapedPath = str_replace("'", "''", $filePath);
        $query = implode(' ', [
            "ALTER DATABASE {$database} SET SINGLE_USER WITH ROLLBACK IMMEDIATE;",
            "RESTORE DATABASE {$database} FROM DISK = N'{$escapedPath}' WITH REPLACE;",
            "ALTER DATABASE {$database} SET MULTI_USER;",
        ]);

        $this->runSqlServerQuery($connection, $query);
    }

    /**
     * @param  array{name: string, driver: string, database: string, host: string, port: string, username: string, password: string}  $connection
     */
    private function runSqlServerQuery(array $connection, string $query): void
    {
        $command = [
            $this->sqlServerBinary(),
            '-S', $connection['host'].','.$connection['port'],
            '-U', $connection['username'],
            '-P', $connection['password'],
            '-Q', $query,
        ];

        $this->runProcess($command);
    }

    private function mysqlBinary(string $binary): string
    {
        $configuredPath = config('database.snapshot.mysql_bin_path');

        if (is_string($configuredPath) && $configuredPath !== '') {
            $candidate = rtrim($configuredPath, '\\/').DIRECTORY_SEPARATOR.$binary.($this->isWindows() ? '.exe' : '');

            if (File::exists($candidate)) {
                return $candidate;
            }
        }

        $windowsCandidates = [
            'C:\\xampp\\mysql\\bin\\'.$binary.'.exe',
            'C:\\laragon\\bin\\mysql\\mysql-8.0.30-winx64\\bin\\'.$binary.'.exe',
        ];

        foreach ($windowsCandidates as $candidate) {
            if (File::exists($candidate)) {
                return $candidate;
            }
        }

        return $binary;
    }

    private function sqlServerBinary(): string
    {
        $configuredPath = config('database.snapshot.sqlserver_bin_path');

        if (is_string($configuredPath) && $configuredPath !== '') {
            $candidate = rtrim($configuredPath, '\\/').DIRECTORY_SEPARATOR.'sqlcmd'.($this->isWindows() ? '.exe' : '');

            if (File::exists($candidate)) {
                return $candidate;
            }
        }

        $windowsCandidates = [
            'C:\\Program Files\\Microsoft SQL Server\\Client SDK\\ODBC\\170\\Tools\\Binn\\sqlcmd.exe',
            'C:\\Program Files\\Microsoft SQL Server\\150\\Tools\\Binn\\sqlcmd.exe',
            'C:\\Program Files\\Microsoft SQL Server\\110\\Tools\\Binn\\sqlcmd.exe',
        ];

        foreach ($windowsCandidates as $candidate) {
            if (File::exists($candidate)) {
                return $candidate;
            }
        }

        return 'sqlcmd';
    }

    /**
     * @param  array<int, string>  $command
     */
    private function runProcess(array $command): void
    {
        $process = new Process($command);
        $process->setTimeout(null);
        $this->runProcessInstance($process);
    }

    private function runProcessInstance(Process $process): void
    {
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(trim($process->getErrorOutput() ?: $process->getOutput()));
        }
    }

    private function snapshotDirectory(): string
    {
        return storage_path('app/'.self::SNAPSHOT_DIR);
    }

    private function ensureSnapshotDirectory(): void
    {
        File::ensureDirectoryExists($this->snapshotDirectory());
    }

    /**
     * @return array<int, string>
     */
    private function manifestPaths(): array
    {
        $directory = $this->snapshotDirectory();

        if (! File::isDirectory($directory)) {
            return [];
        }

        return File::glob($directory.DIRECTORY_SEPARATOR.'*.json') ?: [];
    }

    private function manifestPathFor(string $fileName): string
    {
        return $this->snapshotDirectory().DIRECTORY_SEPARATOR.pathinfo($fileName, PATHINFO_FILENAME).'.json';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function latestManifest(): ?array
    {
        $connection = $this->resolveDevConnection();
        $snapshots = array_values(array_filter(
            $this->list(),
            fn (array $snapshot): bool => (string) ($snapshot['driver'] ?? '') === $connection['driver']
        ));

        return $snapshots[0] ?? null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findManifestByName(string $name): ?array
    {
        foreach ($this->list() as $snapshot) {
            if (($snapshot['name'] ?? null) === $name
                || ($snapshot['file'] ?? null) === $name
                || ($snapshot['backup_path'] ?? null) === $name) {
                return $snapshot;
            }
        }

        return null;
    }

    private function isLocalSqlServerHost(string $host): bool
    {
        $host = strtolower(trim($host));

        return in_array($host, ['127.0.0.1', 'localhost', '(local)', '.', '::1'], true)
            || str_starts_with($host, 'localhost\\');
    }

    private function joinPath(string $directory, string $fileName): string
    {
        return rtrim($directory, '\\/').'\\'.$fileName;
    }

    private function quoteSqlServerIdentifier(string $identifier): string
    {
        return '['.str_replace(']', ']]', $identifier).']';
    }

    private function isWindows(): bool
    {
        return DIRECTORY_SEPARATOR === '\\';
    }
}
