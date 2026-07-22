<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Purchase Order Print Signature Names
    |--------------------------------------------------------------------------
    |
    | Names printed on the PO PDF. Certified by is always fixed. Approved by
    | switches at the total threshold (inclusive).
    |
    */

    'signature' => [
        'certified_by_name' => 'Denny Tuhatelu',
        'approved_by_below_threshold_name' => 'Denny Tuhatelu',
        'approved_by_at_or_above_threshold_name' => 'Sam Calamba',
        'approval_threshold' => 4000000,
        'threshold_currency' => 'IDR',
    ],

];
