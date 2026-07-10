<?php

use App\Services\DatabaseSnapshot;
use Illuminate\Support\Facades\Config;

it('refuses legacy database connections for snapshots', function () {
    Config::set('database.default', 'legacy_sqlsrv_1');
    Config::set('database.connections.legacy_sqlsrv_1', [
        'driver' => 'sqlsrv',
        'database' => 'legacy_db',
    ]);

    $service = app(DatabaseSnapshot::class);

    expect(fn () => $service->currentTarget())
        ->toThrow(RuntimeException::class, 'Refusing to snapshot or restore legacy connection');
});

it('refuses sqlite connections for snapshots', function () {
    Config::set('database.default', 'sqlite');
    Config::set('database.connections.sqlite', [
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    $service = app(DatabaseSnapshot::class);

    expect(fn () => $service->currentTarget())
        ->toThrow(RuntimeException::class, 'Snapshots are only supported for mysql and sqlsrv dev connections');
});

it('returns current dev target for mysql connection', function () {
    Config::set('database.default', 'mysql');
    Config::set('database.connections.mysql', [
        'driver' => 'mysql',
        'host' => '127.0.0.1',
        'port' => '3306',
        'database' => 'spfi_ms',
        'username' => 'root',
        'password' => '',
    ]);

    $target = app(DatabaseSnapshot::class)->currentTarget();

    expect($target)->toBe([
        'name' => 'mysql',
        'driver' => 'mysql',
        'database' => 'spfi_ms',
    ]);
});

it('lists snapshots from manifest files', function () {
    $directory = storage_path('app/db-snapshots');
    $fileName = 'test_snapshot_mysql.sql';
    $manifest = [
        'name' => 'test_snapshot',
        'connection' => 'mysql',
        'driver' => 'mysql',
        'database' => 'spfi_ms',
        'file' => $fileName,
        'created_at' => now()->toIso8601String(),
        'size_bytes' => 1024,
    ];

    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    file_put_contents($directory.'/test_snapshot_mysql.json', json_encode($manifest));

    $snapshots = app(DatabaseSnapshot::class)->list();

    expect($snapshots)->not->toBeEmpty()
        ->and(collect($snapshots)->firstWhere('name', 'test_snapshot'))->not->toBeNull();

    @unlink($directory.'/test_snapshot_mysql.json');
});

it('requires sqlserver snapshot path for remote sql server hosts', function () {
    Config::set('database.default', 'sqlsrv');
    Config::set('database.connections.sqlsrv', [
        'driver' => 'sqlsrv',
        'host' => '192.168.11.250',
        'port' => '1433',
        'database' => 'spfi_ms',
        'username' => 'sa',
        'password' => 'secret',
    ]);
    Config::set('database.snapshot.sqlserver_path', null);

    $service = app(DatabaseSnapshot::class);

    expect(fn () => $service->create())
        ->toThrow(RuntimeException::class, 'SQLSERVER_SNAPSHOT_PATH');
});
