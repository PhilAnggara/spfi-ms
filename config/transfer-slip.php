<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Transfer Slip Paper Form
    |--------------------------------------------------------------------------
    |
    | Physical pre-printed TS form size. Used by PDF generation and the
    | Confirm Print modal checklist.
    |
    */

    'paper' => [
        'width_mm' => 215,
        'height_mm' => 105,
        'label' => 'TS Form 215 x 105 mm',
    ],

    /*
    |--------------------------------------------------------------------------
    | Overlay Offset
    |--------------------------------------------------------------------------
    |
    | Shift all printed field coordinates to align with the physical blank
    | form. Positive = right / down; negative = left / up. Units are mm.
    |
    */

    'offset_x_mm' => 0,
    'offset_y_mm' => 0,

    /*
    |--------------------------------------------------------------------------
    | Calibration Anchor (background table top-left)
    |--------------------------------------------------------------------------
    |
    | Matches the From row on the pre-printed TS form (see pdf/transfer-slip).
    |
    */

    'calibration_anchor' => [
        'x_mm' => 10.5,
        'y_mm' => 23.5,
        'label' => 'Top-left corner of the background table (From row)',
    ],

];
