<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('seed:audit {--tables=*}', function () {
    $defaultTables = [
        'unit_of_measures',
        'item_categories',
        'items',
        'suppliers',
        'groupings',
        'accounting_group_codes',
        'accounting_codes',
        'bs_groupings',
    ];

    $tables = $this->option('tables');
    $tables = empty($tables) ? $defaultTables : $tables;

    $this->info('Seed audit summary');

    $rows = [];
    foreach ($tables as $table) {
        try {
            $rows[] = [
                'table' => $table,
                'rows' => DB::table($table)->count(),
            ];
        } catch (Throwable $e) {
            $rows[] = [
                'table' => $table,
                'rows' => 'ERROR: ' . $e->getMessage(),
            ];
        }
    }

    $this->table(['table', 'rows'], $rows);

    if (in_array('suppliers', $tables, true)) {
        $softDeleted = DB::table('suppliers')->whereNotNull('deleted_at')->count();
        $this->line("suppliers.deleted_at IS NOT NULL: {$softDeleted}");
    }
})->purpose('Show seeded table counts for quick post-seed audit');
