<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Approved By Overrides (creator department code)
    |--------------------------------------------------------------------------
    |
    | Optional print name/title for the Approved By column on the SWS slip,
    | keyed by the full department code of the SWS creator (exact match) —
    | same basis as get_manager(), not the department selected on the SWS.
    | Priority:
    | - fallback: use only when the creator's department has no Manager user
    | - override: always use this entry, even when a Manager exists
    |
    */

    'approved_by_overrides' => [
        // '7046' => [
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
            'name' => 'Rikky Manik',
            'title' => 'Operation Manager',
            'priority' => 'fallback',
        ],
        '7063' => [
            'name' => 'Hakimin Mamonto',
            'title' => 'Team Leader TV',
            'priority' => 'fallback',
        ],
    ],

];
