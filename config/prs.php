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
        '7032',
        '7033',
        '7034',
        '7035',
        '7042',
        // '7044',
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

    /*
    |--------------------------------------------------------------------------
    | Reviewed By Overrides (creator department code)
    |--------------------------------------------------------------------------
    |
    | Optional print name/title for the Reviewed By column, keyed by the full
    | department code of the PRS creator (exact match) — same basis as
    | get_manager(), not the department selected on the PRS document.
    | Priority:
    | - fallback: use only when the creator's department has no Manager user
    | - override: always use this entry, even when a Manager exists
    |
    */

    'reviewed_by_overrides' => [
        // '7033C' => [
        //     'name' => 'Nama Supervisor',
        //     'title' => 'Engineering Supervisor',
        //     'priority' => 'fallback', // fallback | override
        // ],
        '7036' => [
            'name' => 'Evita Patanduk',
            'title' => 'Export Documentation Supervisor',
            'priority' => 'fallback',
        ],
        '7034' => [
            'name' => 'James Runtukahu',
            'title' => 'Fixed Production Supervisor',
            'priority' => 'fallback',
        ],
        '7063' => [
            'name' => 'Hakimin Mamonto',
            'title' => 'Team Leader TV',
            'priority' => 'fallback',
        ],
    ],

];
