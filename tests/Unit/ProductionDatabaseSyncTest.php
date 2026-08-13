<?php

use App\Services\ProductionDatabaseSync;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function productionSyncSqliteConfig(): void
{
    $directory = storage_path('framework/testing');

    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    $sourcePath = $directory.DIRECTORY_SEPARATOR.'prod-sync-source.sqlite';
    $targetPath = $directory.DIRECTORY_SEPARATOR.'prod-sync-target.sqlite';

    foreach ([$sourcePath, $targetPath] as $path) {
        if (file_exists($path)) {
            unlink($path);
        }

        touch($path);
    }

    config()->set('database.default', 'mysql');
    config()->set('database.connections.mysql', [
        'driver' => 'sqlite',
        'database' => $targetPath,
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);
    config()->set('database.connections.production_sqlsrv', [
        'driver' => 'sqlite',
        'database' => $sourcePath,
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);

    DB::purge('mysql');
    DB::purge('production_sqlsrv');
}

function seedProductionSyncPair(): void
{
    foreach (['production_sqlsrv', 'mysql'] as $connection) {
        Schema::connection($connection)->create('items', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('qty');
        });

        Schema::connection($connection)->create('notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('item_id')->constrained('items');
            $table->string('body');
        });

        Schema::connection($connection)->create('migrations', function (Blueprint $table): void {
            $table->id();
            $table->string('migration');
        });
    }

    DB::connection('production_sqlsrv')->table('items')->insert([
        ['id' => 1, 'name' => 'Bolt', 'qty' => 10],
        ['id' => 2, 'name' => 'Nut', 'qty' => 20],
    ]);
    DB::connection('production_sqlsrv')->table('notes')->insert([
        ['id' => 5, 'item_id' => 1, 'body' => 'From production'],
    ]);
    DB::connection('production_sqlsrv')->table('migrations')->insert([
        ['id' => 1, 'migration' => 'prod_migration'],
    ]);

    DB::connection('mysql')->table('items')->insert([
        ['id' => 9, 'name' => 'Stale', 'qty' => 1],
    ]);
    DB::connection('mysql')->table('migrations')->insert([
        ['id' => 1, 'migration' => 'dev_migration'],
    ]);
}

afterEach(function (): void {
    app()['env'] = 'testing';
    DB::purge('mysql');
    DB::purge('production_sqlsrv');
});

it('refuses to pull while APP_ENV is production', function () {
    app()['env'] = 'production';

    $sync = app(ProductionDatabaseSync::class);

    expect(fn () => $sync->assertSafe($sync->resolveSource(), [
        'name' => 'mysql',
        'driver' => 'mysql',
        'host' => '127.0.0.1',
        'port' => '3306',
        'database' => 'spfi_ms',
    ]))->toThrow(RuntimeException::class, 'APP_ENV is production');
});

it('refuses to write to the production connection', function () {
    $sync = app(ProductionDatabaseSync::class);

    expect(fn () => $sync->assertSafe($sync->resolveSource(), [
        'name' => 'production_sqlsrv',
        'driver' => 'sqlsrv',
        'host' => '192.168.11.250',
        'port' => '1433',
        'database' => 'spfi_ms_dev',
    ]))->toThrow(RuntimeException::class, 'production connection');
});

it('refuses to write to a legacy connection', function () {
    $sync = app(ProductionDatabaseSync::class);

    expect(fn () => $sync->assertSafe($sync->resolveSource(), [
        'name' => 'legacy_sqlsrv_1',
        'driver' => 'sqlsrv',
        'host' => '192.168.11.250',
        'port' => '1433',
        'database' => 'b12d4a36',
    ]))->toThrow(RuntimeException::class, 'legacy connection');
});

it('refuses sqlite targets outside testing', function () {
    app()['env'] = 'local';

    $sync = app(ProductionDatabaseSync::class);

    expect(fn () => $sync->assertSafe($sync->resolveSource(), [
        'name' => 'sqlite',
        'driver' => 'sqlite',
        'host' => '',
        'port' => '',
        'database' => ':memory:',
    ]))->toThrow(RuntimeException::class, 'sqlite is not supported');
});

it('refuses a SQL Server target that shares the production database name', function () {
    $sync = app(ProductionDatabaseSync::class);

    expect(fn () => $sync->assertSafe([
        'name' => 'production_sqlsrv',
        'driver' => 'sqlsrv',
        'host' => '192.168.11.250',
        'port' => '1433',
        'database' => 'spfi_ms',
    ], [
        'name' => 'sqlsrv',
        'driver' => 'sqlsrv',
        'host' => 'localhost',
        'port' => '1433',
        'database' => 'spfi_ms',
    ]))->toThrow(RuntimeException::class, 'matches production');
});

it('refuses a target whose host and database match production', function () {
    $sync = app(ProductionDatabaseSync::class);

    expect(fn () => $sync->assertSafe([
        'name' => 'production_sqlsrv',
        'driver' => 'mysql',
        'host' => '127.0.0.1',
        'port' => '3306',
        'database' => 'spfi_ms',
    ], [
        'name' => 'mysql',
        'driver' => 'mysql',
        'host' => 'localhost',
        'port' => '3306',
        'database' => 'spfi_ms',
    ]))->toThrow(RuntimeException::class, 'same database as production');
});

it('allows a MySQL target that reuses the production database name', function () {
    $sync = app(ProductionDatabaseSync::class);

    $sync->assertSafe([
        'name' => 'production_sqlsrv',
        'driver' => 'sqlsrv',
        'host' => '192.168.11.250',
        'port' => '1433',
        'database' => 'spfi_ms',
    ], [
        'name' => 'mysql',
        'driver' => 'mysql',
        'host' => '127.0.0.1',
        'port' => '3306',
        'database' => 'spfi_ms',
    ]);

    expect(true)->toBeTrue();
});

it('full-replaces shared tables and leaves migrations untouched', function () {
    productionSyncSqliteConfig();
    seedProductionSyncPair();

    $result = app(ProductionDatabaseSync::class)->sync(1);

    expect($result['tables'])->toHaveCount(2)
        ->and(collect($result['tables'])->pluck('table')->all())->toBe(['items', 'notes'])
        ->and(DB::connection('mysql')->table('items')->orderBy('id')->get()->map(fn ($row): array => (array) $row)->all())->toBe([
            ['id' => 1, 'name' => 'Bolt', 'qty' => 10],
            ['id' => 2, 'name' => 'Nut', 'qty' => 20],
        ])
        ->and(DB::connection('mysql')->table('notes')->get()->map(fn ($row): array => (array) $row)->all())->toBe([
            ['id' => 5, 'item_id' => 1, 'body' => 'From production'],
        ])
        ->and(DB::connection('mysql')->table('migrations')->value('migration'))->toBe('dev_migration');
});

it('previews table counts without writing', function () {
    productionSyncSqliteConfig();
    seedProductionSyncPair();

    $preview = app(ProductionDatabaseSync::class)->preview();

    expect($preview['source']['name'])->toBe('production_sqlsrv')
        ->and($preview['target']['name'])->toBe('mysql')
        ->and(collect($preview['tables'])->firstWhere('table', 'items'))->toMatchArray([
            'table' => 'items',
            'source_rows' => 2,
            'target_rows' => 1,
        ])
        ->and(DB::connection('mysql')->table('items')->count())->toBe(1)
        ->and(DB::connection('mysql')->table('items')->value('name'))->toBe('Stale');
});

it('quotes sql server tables with dbo schema for identity insert', function () {
    $method = new ReflectionMethod(ProductionDatabaseSync::class, 'sqlServerQuoteTable');

    expect($method->invoke(app(ProductionDatabaseSync::class), 'accounting_codes'))
        ->toBe('[dbo].[accounting_codes]');
});

it('caps sql server insert chunks below the 2100 parameter limit', function () {
    $method = new ReflectionMethod(ProductionDatabaseSync::class, 'resolveInsertChunkSize');
    $sync = app(ProductionDatabaseSync::class);

    expect($method->invoke($sync, 1000, 11, 'sqlsrv'))->toBe(181)
        ->and($method->invoke($sync, 50, 11, 'sqlsrv'))->toBe(50)
        ->and($method->invoke($sync, 1000, 11, 'mysql'))->toBe(1000)
        ->and($method->invoke($sync, 1000, 1, 'sqlsrv'))->toBe(1000);
});
