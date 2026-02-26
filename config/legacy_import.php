<?php

return [
    // Sumber utama seeding: csv atau legacy.
    'source' => env('SEED_SOURCE', 'csv'),

    // Jika legacy gagal konek/query, otomatis fallback ke CSV.
    'fallback_to_csv' => env('SEED_SOURCE_FALLBACK_TO_CSV', true),

    // Koneksi default legacy jika dataset tidak override connection.
    'default_connection' => env('LEGACY_DB_DEFAULT_CONNECTION', 'legacy_sqlsrv_1'),

    // Mapping dataset -> sumber legacy + path CSV fallback.
    'datasets' => [
        'uom' => [
            'csv_path' => 'document/csv/uom.csv',
            'connection' => env('LEGACY_DB_UOM_CONNECTION'),
            'table' => env('LEGACY_DB_UOM_TABLE', 'uom'),
        ],
        'product_category' => [
            'csv_path' => 'document/csv/product_category.csv',
            'connection' => env('LEGACY_DB_PRODUCT_CATEGORY_CONNECTION'),
            'table' => env('LEGACY_DB_PRODUCT_CATEGORY_TABLE', 'product_category'),
        ],
        'product' => [
            'csv_path' => 'document/csv/product.csv',
            'connection' => env('LEGACY_DB_PRODUCT_CONNECTION'),
            'table' => env('LEGACY_DB_PRODUCT_TABLE', 'product'),
        ],
        'supplier' => [
            'csv_path' => 'document/csv/supplier.csv',
            'connection' => env('LEGACY_DB_SUPPLIER_CONNECTION'),
            'table' => env('LEGACY_DB_SUPPLIER_TABLE', 'supplier'),
        ],
        'acct_sub_group' => [
            'csv_path' => 'document/csv/[tbl_Acct_SubGroup].csv',
            'connection' => env('LEGACY_DB_ACCT_SUB_GROUP_CONNECTION'),
            'table' => env('LEGACY_DB_ACCT_SUB_GROUP_TABLE', 'tbl_Acct_SubGroup'),
        ],
        'acct_group_codes' => [
            'csv_path' => 'document/csv/[tbl_Acct_GroupCodes].csv',
            'connection' => env('LEGACY_DB_ACCT_GROUP_CODES_CONNECTION'),
            'table' => env('LEGACY_DB_ACCT_GROUP_CODES_TABLE', 'tbl_Acct_GroupCodes'),
        ],
        'accounting_codes' => [
            'csv_path' => 'document/csv/[tbl_AccountingCodes].csv',
            'connection' => env('LEGACY_DB_ACCOUNTING_CODES_CONNECTION'),
            'table' => env('LEGACY_DB_ACCOUNTING_CODES_TABLE', 'tbl_AccountingCodes'),
        ],
        'bs_grouping' => [
            'csv_path' => 'document/csv/[tbl_BSGrouping].csv',
            'connection' => env('LEGACY_DB_BS_GROUPING_CONNECTION'),
            'table' => env('LEGACY_DB_BS_GROUPING_TABLE', 'tbl_BSGrouping'),
        ],
    ],
];
