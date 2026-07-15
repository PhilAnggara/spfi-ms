<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Supported Document Types
    |--------------------------------------------------------------------------
    |
    | Doc Entry covers operational documents that also appear in the legacy
    | General Ledger DocTran table. Transfer Slip (TS) is intentionally
    | excluded — there is no DocCode TS in legacy GL.
    |
    */

    'doc_types' => ['RR', 'DR'],

];
