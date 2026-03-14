<?php

return [
    // Sumber utama seeding: 'local' (seeder manual/csv) atau 'legacy' (database lama).
    'source' => env('SEED_SOURCE', 'local'),

    // Jika legacy gagal konek/query, otomatis fallback ke local seeder.
    'fallback_to_local' => env('SEED_SOURCE_FALLBACK_TO_LOCAL',
        env('SEED_SOURCE_FALLBACK_TO_CSV', true) // backward compat
    ),

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
        'customs_document_type' => [
            'csv_path' => 'document/csv/tbl_BCName.csv',
            'connection' => env('LEGACY_DB_CUSTOMS_DOCUMENT_TYPE_CONNECTION', 'legacy_sqlsrv_2'),
            'table' => env('LEGACY_DB_CUSTOMS_DOCUMENT_TYPE_TABLE', 'tbl_BCName'),
        ],
        'acct_sub_group' => [
            'csv_path' => 'document/csv/tbl_Acct_SubGroup.csv',
            'connection' => env('LEGACY_DB_ACCT_SUB_GROUP_CONNECTION', 'legacy_sqlsrv_3'),
            'table' => env('LEGACY_DB_ACCT_SUB_GROUP_TABLE', 'tbl_Acct_SubGroup'),
        ],
        'acct_group_codes' => [
            'csv_path' => 'document/csv/tbl_Acct_GroupCodes.csv',
            'connection' => env('LEGACY_DB_ACCT_GROUP_CODES_CONNECTION', 'legacy_sqlsrv_3'),
            'table' => env('LEGACY_DB_ACCT_GROUP_CODES_TABLE', 'tbl_Acct_GroupCodes'),
        ],
        'accounting_codes' => [
            'csv_path' => 'document/csv/tbl_AccountingCodes.csv',
            'connection' => env('LEGACY_DB_ACCOUNTING_CODES_CONNECTION', 'legacy_sqlsrv_3'),
            'table' => env('LEGACY_DB_ACCOUNTING_CODES_TABLE', 'tbl_AccountingCodes'),
        ],
        'bs_grouping' => [
            'csv_path' => 'document/csv/tbl_BSGrouping.csv',
            'connection' => env('LEGACY_DB_BS_GROUPING_CONNECTION', 'legacy_sqlsrv_3'),
            'table' => env('LEGACY_DB_BS_GROUPING_TABLE', 'tbl_BSGrouping'),
        ],
        'prs' => [
            'connection' => env('LEGACY_DB_PRS_CONNECTION'),
            'table' => env('LEGACY_DB_PRS_TABLE', 'prs'),
        ],
        'prs_detail' => [
            'connection' => env('LEGACY_DB_PRS_DETAIL_CONNECTION'),
            'table' => env('LEGACY_DB_PRS_DETAIL_TABLE', 'prs_detail'),
        ],
        'po' => [
            'csv_path' => 'document/csv/po.csv',
            'connection' => env('LEGACY_DB_PO_CONNECTION'),
            'table' => env('LEGACY_DB_PO_TABLE', 'po'),
        ],
        'po_detail' => [
            'csv_path' => 'document/csv/po_detail.csv',
            'connection' => env('LEGACY_DB_PO_DETAIL_CONNECTION'),
            'table' => env('LEGACY_DB_PO_DETAIL_TABLE', 'po_detail'),
        ],
        'rr' => [
            'csv_path' => 'document/csv/rr.csv',
            'connection' => env('LEGACY_DB_RR_CONNECTION'),
            'table' => env('LEGACY_DB_RR_TABLE', 'rr'),
        ],
        'rr_detail' => [
            'csv_path' => 'document/csv/rr_detail.csv',
            'connection' => env('LEGACY_DB_RR_DETAIL_CONNECTION'),
            'table' => env('LEGACY_DB_RR_DETAIL_TABLE', 'rr_detail'),
        ],
        'stock_inventory' => [
            'csv_path' => 'document/csv/stock_inventory.csv',
            'connection' => env('LEGACY_DB_STOCK_INVENTORY_CONNECTION'),
            'table' => env('LEGACY_DB_STOCK_INVENTORY_TABLE', 'stock_inventory'),
        ],
        'stock_balance' => [
            'csv_path' => 'document/csv/stock_balance.csv',
            'connection' => env('LEGACY_DB_STOCK_BALANCE_CONNECTION'),
            'table' => env('LEGACY_DB_STOCK_BALANCE_TABLE', 'stock_balance'),
        ],
        'employee_department' => [
            'csv_path' => 'document/csv/tblDeptList.csv',
            'connection' => env('LEGACY_DB_EMPLOYEE_DEPARTMENT_CONNECTION'),
            'table' => env('LEGACY_DB_EMPLOYEE_DEPARTMENT_TABLE', 'tblDeptList'),
        ],
        'employee' => [
            'csv_path' => 'document/csv/tblEmployeeMasterList.csv',
            'connection' => env('LEGACY_DB_EMPLOYEE_CONNECTION'),
            'table' => env('LEGACY_DB_EMPLOYEE_TABLE', 'tblEmployeeMasterList'),
        ],
        'sws' => [
            'csv_path' => 'document/csv/sws.csv',
            'connection' => env('LEGACY_DB_SWS_CONNECTION'),
            'table' => env('LEGACY_DB_SWS_TABLE', 'sws'),
        ],
        'sws_detail' => [
            'csv_path' => 'document/csv/sws_detail.csv',
            'connection' => env('LEGACY_DB_SWS_DETAIL_CONNECTION'),
            'table' => env('LEGACY_DB_SWS_DETAIL_TABLE', 'sws_detail'),
        ],
        'ts' => [
            'csv_path' => 'document/csv/ts.csv',
            'connection' => env('LEGACY_DB_TS_CONNECTION'),
            'table' => env('LEGACY_DB_TS_TABLE', 'ts'),
        ],
        'ts_detail' => [
            'csv_path' => 'document/csv/ts_detail.csv',
            'connection' => env('LEGACY_DB_TS_DETAIL_CONNECTION'),
            'table' => env('LEGACY_DB_TS_DETAIL_TABLE', 'ts_detail'),
        ],
    ],
];
