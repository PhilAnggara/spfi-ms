<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$connection = 'legacy_sqlsrv_3';
$tables = ['tbl_DocTran', 'tbl_DocTranDetails'];

foreach ($tables as $table) {
    echo "=== {$table} ===".PHP_EOL;
    try {
        $cols = DB::connection($connection)->select(
            'SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_NAME = ?
             ORDER BY ORDINAL_POSITION',
            [$table]
        );
        foreach ($cols as $col) {
            echo implode('|', [
                $col->COLUMN_NAME,
                $col->DATA_TYPE,
                $col->CHARACTER_MAXIMUM_LENGTH ?? '',
                $col->IS_NULLABLE,
            ]).PHP_EOL;
        }
    } catch (Throwable $e) {
        echo 'ERR: '.$e->getMessage().PHP_EOL;
    }
    echo PHP_EOL;
}
