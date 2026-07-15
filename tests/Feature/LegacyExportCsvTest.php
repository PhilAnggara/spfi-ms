<?php

use App\Services\Legacy\LegacyCsvExporter;
use App\Services\Legacy\LegacyImportDatasetRegistry;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\mock;

it('resolves dataset configuration from legacy_import config', function () {
    $registry = app(LegacyImportDatasetRegistry::class);

    expect($registry->datasetKeys())->toContain('uom', 'doc_tran', 'doc_tran_details');
    expect($registry->resolveConnection('uom'))->toBe('legacy_sqlsrv_1');
    expect($registry->resolveTable('supplier'))->toBe('supplier');
    expect($registry->resolveCsvRelativePath('uom'))->toBe('csv/[b12d4a36]/[uom].csv');
    expect($registry->resolveOrderBy('doc_tran'))->toBe('DocTranId');
    expect($registry->resolveOrderBy('doc_tran_details'))->toBe('ID');
    expect($registry->resolveChunkSize('doc_tran_details', 2000))->toBe(5000);
    expect($registry->resolveDelimiter())->toBe(';');
    expect($registry->isExportEnabled('uom'))->toBeTrue();
});

it('lists configured datasets via artisan command', function () {
    $this->artisan('legacy:export-csv', ['--list' => true])
        ->expectsOutputToContain('Configured legacy datasets:')
        ->expectsOutputToContain('uom')
        ->expectsOutputToContain('doc_tran')
        ->assertSuccessful();
});

it('fails when only references unknown dataset', function () {
    $this->artisan('legacy:export-csv', ['--only' => 'does_not_exist'])
        ->expectsOutputToContain('Unknown dataset')
        ->assertFailed();
});

it('delegates dry run export to exporter with filtered datasets', function () {
    mock(LegacyCsvExporter::class, function ($mock): void {
        $mock->shouldReceive('export')
            ->once()
            ->with(['uom'], 2000, true)
            ->andReturn([
                [
                    'dataset' => 'uom',
                    'connection' => 'legacy_sqlsrv_1',
                    'table' => 'uom',
                    'rows' => 94,
                    'bytes' => 0,
                    'path' => public_path('csv/[b12d4a36]/[uom].csv'),
                    'success' => true,
                    'message' => null,
                ],
            ]);
    });

    $this->artisan('legacy:export-csv', ['--only' => 'uom', '--dry-run' => true])
        ->expectsOutputToContain('Dry run')
        ->expectsOutputToContain('Counted 1/1 datasets')
        ->assertSuccessful();
});

it('returns failure when any dataset export fails', function () {
    mock(LegacyCsvExporter::class, function ($mock): void {
        $mock->shouldReceive('export')
            ->once()
            ->andReturn([
                [
                    'dataset' => 'uom',
                    'connection' => 'legacy_sqlsrv_1',
                    'table' => 'uom',
                    'rows' => 0,
                    'bytes' => 0,
                    'path' => public_path('csv/[b12d4a36]/[uom].csv'),
                    'success' => false,
                    'message' => 'Connection refused',
                ],
            ]);
    });

    $this->artisan('legacy:export-csv', ['--only' => 'uom'])
        ->expectsOutputToContain('FAIL')
        ->expectsOutputToContain('Connection refused')
        ->assertFailed();
});

it('exports rows to semicolon csv with null literal and header columns', function () {
    $relativePath = 'csv/test/[legacy_export_fixture].csv';
    $absolutePath = public_path($relativePath);

    config([
        'legacy_import.datasets.legacy_export_fixture' => [
            'csv_path' => $relativePath,
            'connection' => 'sqlite',
            'table' => 'legacy_export_fixture',
            'order_by' => 'id',
        ],
    ]);

    DB::connection('sqlite')->statement('DROP TABLE IF EXISTS legacy_export_fixture');
    DB::connection('sqlite')->statement('CREATE TABLE legacy_export_fixture (id INTEGER PRIMARY KEY, code TEXT, remarks TEXT)');
    DB::connection('sqlite')->table('legacy_export_fixture')->insert([
        ['id' => 1, 'code' => 'PCS', 'remarks' => null],
        ['id' => 2, 'code' => 'BOX', 'remarks' => 'sample'],
    ]);

    $exporter = app(LegacyCsvExporter::class);
    $result = $exporter->exportDataset('legacy_export_fixture', 2000, false);

    expect($result['success'])->toBeTrue();
    expect($result['rows'])->toBe(2);
    expect(file_exists($absolutePath))->toBeTrue();

    $contents = file_get_contents($absolutePath);
    expect($contents)->toContain('id;code;remarks');
    expect($contents)->toContain('PCS;NULL');

    @unlink($absolutePath);
    @rmdir(dirname($absolutePath));
    DB::connection('sqlite')->statement('DROP TABLE IF EXISTS legacy_export_fixture');
});

it('exports tables keyed by PascalCase Id without configured order_by', function () {
    $relativePath = 'csv/test/[legacy_export_pascal_id].csv';
    $absolutePath = public_path($relativePath);

    config([
        'legacy_import.datasets.legacy_export_pascal_id' => [
            'csv_path' => $relativePath,
            'connection' => 'sqlite',
            'table' => 'legacy_export_pascal_id',
        ],
        'legacy_import.export.default_order_by' => 'id',
    ]);

    DB::connection('sqlite')->statement('DROP TABLE IF EXISTS legacy_export_pascal_id');
    DB::connection('sqlite')->statement('CREATE TABLE legacy_export_pascal_id ("Id" INTEGER PRIMARY KEY, name TEXT)');
    DB::connection('sqlite')->table('legacy_export_pascal_id')->insert([
        ['Id' => 1, 'name' => 'Alpha'],
        ['Id' => 2, 'name' => 'Beta'],
    ]);

    $result = app(LegacyCsvExporter::class)->exportDataset('legacy_export_pascal_id', 2000, false);

    expect($result['success'])->toBeTrue();
    expect($result['rows'])->toBe(2);
    expect(file_get_contents($absolutePath))->toContain('Alpha');

    @unlink($absolutePath);
    @rmdir(dirname($absolutePath));
    DB::connection('sqlite')->statement('DROP TABLE IF EXISTS legacy_export_pascal_id');
});

it('exports tables without id using natural code key', function () {
    $relativePath = 'csv/test/[legacy_export_code_key].csv';
    $absolutePath = public_path($relativePath);

    config([
        'legacy_import.datasets.legacy_export_code_key' => [
            'csv_path' => $relativePath,
            'connection' => 'sqlite',
            'table' => 'legacy_export_code_key',
            'order_by' => 'po_code',
        ],
    ]);

    DB::connection('sqlite')->statement('DROP TABLE IF EXISTS legacy_export_code_key');
    DB::connection('sqlite')->statement('CREATE TABLE legacy_export_code_key (po_code INTEGER PRIMARY KEY, remarks TEXT)');
    DB::connection('sqlite')->table('legacy_export_code_key')->insert([
        ['po_code' => 100, 'remarks' => 'first'],
        ['po_code' => 200, 'remarks' => 'second'],
    ]);

    $result = app(LegacyCsvExporter::class)->exportDataset('legacy_export_code_key', 2000, false);

    expect($result['success'])->toBeTrue();
    expect($result['rows'])->toBe(2);
    expect(file_get_contents($absolutePath))->toContain('po_code;remarks');

    @unlink($absolutePath);
    @rmdir(dirname($absolutePath));
    DB::connection('sqlite')->statement('DROP TABLE IF EXISTS legacy_export_code_key');
});
