<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function pullProductionCommandSqliteConfig(): void
{
    $directory = storage_path('framework/testing');

    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    $sourcePath = $directory.DIRECTORY_SEPARATOR.'prod-pull-source.sqlite';
    $targetPath = $directory.DIRECTORY_SEPARATOR.'prod-pull-target.sqlite';

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

    foreach (['production_sqlsrv', 'mysql'] as $connection) {
        Schema::connection($connection)->create('items', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });
    }

    DB::connection('production_sqlsrv')->table('items')->insert([
        ['id' => 1, 'name' => 'Bolt'],
    ]);
    DB::connection('mysql')->table('items')->insert([
        ['id' => 9, 'name' => 'Stale'],
    ]);
}

afterEach(function (): void {
    DB::purge('mysql');
    DB::purge('production_sqlsrv');
});

it('rejects a chunk size below one', function () {
    $this->artisan('db:pull-production', ['--chunk' => 0])
        ->expectsOutputToContain('Chunk size must be at least 1.')
        ->assertFailed();
});

it('refuses a legacy default connection', function () {
    config()->set('database.default', 'legacy_sqlsrv_1');
    config()->set('database.connections.legacy_sqlsrv_1.database', 'b12d4a36');

    $this->artisan('db:pull-production', ['--dry-run' => true])
        ->expectsOutputToContain('legacy connection')
        ->assertFailed();
});

it('lists tables during a dry run without writing', function () {
    pullProductionCommandSqliteConfig();

    $this->artisan('db:pull-production', ['--dry-run' => true])
        ->expectsOutputToContain('Dry run only')
        ->assertSuccessful();

    expect(DB::connection('mysql')->table('items')->value('name'))->toBe('Stale');
});

it('cancels when the confirmation is declined', function () {
    pullProductionCommandSqliteConfig();

    $this->artisan('db:pull-production')
        ->expectsConfirmation(
            'This will ERASE all data in the target development database and replace it with production data. Continue?',
            'no'
        )
        ->expectsOutputToContain('Cancelled.')
        ->assertSuccessful();

    expect(DB::connection('mysql')->table('items')->value('name'))->toBe('Stale');
});

it('copies production rows when forced', function () {
    pullProductionCommandSqliteConfig();

    $this->artisan('db:pull-production', ['--force' => true])
        ->expectsOutputToContain('Pull complete')
        ->assertSuccessful();

    expect(DB::connection('mysql')->table('items')->count())->toBe(1)
        ->and(DB::connection('mysql')->table('items')->value('name'))->toBe('Bolt');
});
