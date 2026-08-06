<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Operations Approval Department Prefixes
    |--------------------------------------------------------------------------
    |
    | PRS from these department code prefixes (first 4 characters) require
    | operations approvers instead of the General Manager on the print form.
    | Sub-codes such as 7033C match via the same 4-digit prefix.
    |
    */

    'operations_approval_department_prefixes' => [
        '7031',
        '7033',
        '7034',
        '7035',
        '7042',
        '7044',
        '7046',
    ],

    'operations_approvers' => [
        [
            'name' => 'Rikky Manik',
            'title' => 'Operation Manager',
        ],
        [
            'name' => 'Tecs Calunod',
            'title' => 'Production Advisor',
        ],
    ],

    'general_manager_approver' => [
        'name' => 'S.C Calamba, Jr',
        'title' => 'General Manager',
    ],

];
