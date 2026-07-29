<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Purchase Order Print Signature Names
    |--------------------------------------------------------------------------
    |
    | Names printed on the PO PDF. Certified by is always fixed. Approved by
    | switches at the total threshold (inclusive) for the threshold currency
    | (IDR). Any other currency always uses the at-or-above threshold name.
    |
    */

    'signature' => [
        'certified_by_name' => 'Denny Tuhatelu',
        'approved_by_below_threshold_name' => 'Denny Tuhatelu',
        'approved_by_at_or_above_threshold_name' => 'S.C Calamba, Jr',
        'approval_threshold' => 4000000,
        'threshold_currency' => 'IDR',
    ],

    /*
    |--------------------------------------------------------------------------
    | Purchase Order Paper Form
    |--------------------------------------------------------------------------
    |
    | Physical pre-printed PO form size (same family as RR form paper).
    | Used by PDF generation and the Confirm Print modal checklist.
    |
    */

    'paper' => [
        'width_mm' => 215,
        'height_mm' => 160,
        'label' => 'PO Form 215 x 160 mm',
    ],

    /*
    |--------------------------------------------------------------------------
    | Purchase Order Print Number Formatting
    |--------------------------------------------------------------------------
    |
    | Decimal places used when formatting money amounts on the PO PDF.
    | Chosen in the print confirmation modal; default is 2.
    |
    */

    'print' => [
        'decimal_places' => [
            'default' => 2,
            'options' => [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
        ],
    ],

];
