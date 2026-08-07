<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Freeze transactional writes on spfi_ms
    |--------------------------------------------------------------------------
    |
    | When true, mutating HTTP requests for PRS / PO / RR / SWS / TS / canvassing
    | / deliveries that create or change inventory docs are blocked. Use during
    | IMS → spfi_ms incremental sync windows.
    |
    */
    'freeze_writes' => (bool) env('RECONCILE_FREEZE_WRITES', false),

    /*
    |--------------------------------------------------------------------------
    | Default cutoff for incremental reconcile
    |--------------------------------------------------------------------------
    */
    'default_since' => env('RECONCILE_DEFAULT_SINCE', '2026-07-15'),

    /*
    |--------------------------------------------------------------------------
    | Conflict resolution when the same document number exists in both systems
    | with different business content.
    |
    | skip            — report only, do not import IMS copy
    | import-as-alias — keep spfi_ms row; import IMS copy under a new number
    | prefer-ims      — retire conflicting spfi_ms row, then import IMS under the IMS number
    |
    */
    'conflict' => env('RECONCILE_CONFLICT', 'import-as-alias'),

    /*
    |--------------------------------------------------------------------------
    | Legacy SQL Server connection used as IMS source
    |--------------------------------------------------------------------------
    */
    'legacy_connection' => env('RECONCILE_LEGACY_CONNECTION', env('LEGACY_DB_DEFAULT_CONNECTION', 'legacy_sqlsrv_1')),

    /*
    |--------------------------------------------------------------------------
    | CSV report output directory (relative to storage/app)
    |--------------------------------------------------------------------------
    */
    'report_path' => env('RECONCILE_REPORT_PATH', 'reconcile-reports'),

    /*
    |--------------------------------------------------------------------------
    | Route name prefixes blocked when freeze_writes is enabled
    |--------------------------------------------------------------------------
    */
    'frozen_route_prefixes' => [
        'prs.',
        'purchase-orders.',
        'receiving-reports.',
        'store-withdrawals.',
        'transfer-slips.',
        'deliveries.',
        'canvassing.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowlist of route names that remain writable during freeze
    | (exports, prints, datatables, show/index).
    |--------------------------------------------------------------------------
    */
    'frozen_route_allow_suffixes' => [
        'index',
        'show',
        'print',
        'export',
        'export-by-department',
        'datatables',
        'datatable',
        'capex-lines',
    ],

];
