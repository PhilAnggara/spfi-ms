<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Legacy Count Tag (Percount) Source
    |--------------------------------------------------------------------------
    |
    | Restatement and Stock Card per Count read physical count qty from the
    | legacy counttag database until a local Count Tag module exists.
    |
    */

    'count_tag' => [
        'source' => env('ACCOUNTING_COUNT_TAG_SOURCE', 'legacy'),
        'connection' => env('LEGACY_DB_COUNT_TAG_CONNECTION', 'legacy_sqlsrv_5'),
        'table' => env('LEGACY_DB_COUNT_TAG_TABLE', 'tblNonFGCountTag'),
    ],

];
