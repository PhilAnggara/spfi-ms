<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Receiving Report Paper Form
    |--------------------------------------------------------------------------
    |
    | Physical pre-printed RR form size. Used by PDF generation and the
    | Confirm Print modal checklist.
    |
    */

    'paper' => [
        'width_mm' => 215,
        'height_mm' => 160,
        'label' => 'RR Form 215 x 160 mm',
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
    | Design reference for measured-anchor calibration. Operators measure from
    | the page top-left corner to the top-left corner of the pre-printed table
    | grid (header row), not the first item-name cell.
    | RR coords use the same scale as pdf/receiving-report (base 297×210 mm).
    |
    */

    'calibration_anchor' => [
        'x_mm' => 8.5,
        'y_mm' => 29.5,
        'label' => 'Top-left corner of the background table',
    ],

];
