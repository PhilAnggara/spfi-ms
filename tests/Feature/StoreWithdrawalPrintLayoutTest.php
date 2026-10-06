<?php

use Illuminate\Support\Collection;

it('renders sws print layout in prs style with only two signatures', function () {
    $manager = (object) [
        'name' => 'Dept Manager',
        'role' => 'Manager',
        'department' => (object) [
            'name' => 'Engineering',
            'alias' => 'ENG',
        ],
    ];

    $approver = (object) [
        'name' => 'Dept Manager',
        'title' => get_job_title($manager),
    ];

    $sws = (object) [
        'sws_number' => 'SWS-PRINT-001',
        'sws_date' => now()->toDateString(),
        'department_code' => '7046',
        'department_name' => 'Engineering',
        'type' => 'opex',
        'info' => 'Print layout test info',
        'created_by_name' => 'Requester User',
        'approved_by_name' => null,
        'created_at' => now()->subDay(),
        'approved_at' => null,
    ];

    $items = new Collection([
        (object) [
            'item_name' => 'Test Item',
            'item_code' => 'ITM-001',
            'product_code' => 'ITM-001',
            'quantity' => 2.5,
            'stock_on_hand_snapshot' => 10,
            'uom' => 'PCS',
            'item_uom_name' => 'PCS',
            'prs_number' => null,
            'po_number' => null,
            'rr_number' => null,
        ],
    ]);

    $html = view('pdf.store-withdrawal-slip', [
        'sws' => $sws,
        'items' => $items,
        'manager' => $manager,
        'approver' => $approver,
    ])->render();

    expect($html)->toContain('Stores Withdrawal Slip')
        ->and($html)->toContain('PT. SINAR PURE FOODS INTERNATIONAL')
        ->and($html)->toContain('SWS Number')
        ->and($html)->toContain('SWS-PRINT-001')
        ->and($html)->toContain('Requester User')
        ->and($html)->toContain('Dept Manager')
        ->and($html)->toContain($approver->title)
        ->and($html)->toContain('Remarks')
        ->and($html)->toContain('Print layout test info')
        ->and($html)->toContain('UOM')
        ->and($html)->not->toContain('>Info<')
        ->and($html)->toContain('class="signature-section"')
        ->and($html)->toContain('class="sig-line"')
        ->and($html)->toContain('class="header-rule"')
        ->and(substr_count($html, 'Requested By'))->toBe(2)
        ->and(substr_count($html, 'Approved By'))->toBe(1)
        ->and($html)->not->toContain('Checked by')
        ->and($html)->not->toContain('Checked By')
        ->and($html)->not->toContain('Reviewed By')
        ->and($html)->toContain('Date: __________');
});

it('falls back to approved_by_name when manager is missing', function () {
    $sws = (object) [
        'sws_number' => 'SWS-FALLBACK-001',
        'sws_date' => now()->toDateString(),
        'department_code' => '7046',
        'department_name' => 'Engineering',
        'type' => 'opex',
        'info' => null,
        'created_by_name' => 'Requester User',
        'approved_by_name' => 'Legacy Approver',
        'created_at' => now(),
        'approved_at' => null,
    ];

    $html = view('pdf.store-withdrawal-slip', [
        'sws' => $sws,
        'items' => new Collection,
        'manager' => null,
        'approver' => null,
    ])->render();

    expect($html)->toContain('Legacy Approver')
        ->and($html)->toContain('Approver')
        ->and(substr_count($html, '>Approved By<'))->toBe(1);
});

it('uses config approved_by override with fallback priority when manager is missing', function () {
    config()->set('stores-withdrawal.approved_by_overrides', [
        '7046' => [
            'name' => 'Config Supervisor',
            'title' => 'Engineering Supervisor',
            'priority' => 'fallback',
        ],
    ]);

    $sws = (object) [
        'sws_number' => 'SWS-CFG-FALLBACK-001',
        'sws_date' => now()->toDateString(),
        'department_code' => '7046',
        'department_name' => 'Engineering',
        'type' => 'opex',
        'info' => null,
        'created_by_name' => 'Requester User',
        'approved_by_name' => 'Legacy Approver',
        'created_at' => now(),
        'approved_at' => null,
    ];

    $approver = resolve_print_signer(null, '7046', config('stores-withdrawal.approved_by_overrides', []));

    $html = view('pdf.store-withdrawal-slip', [
        'sws' => $sws,
        'items' => new Collection,
        'manager' => null,
        'approver' => $approver,
    ])->render();

    expect($html)->toContain('Config Supervisor')
        ->and($html)->toContain('Engineering Supervisor')
        ->and($html)->not->toContain('Legacy Approver');
});

it('uses config approved_by override with override priority even when manager exists', function () {
    $manager = (object) [
        'name' => 'Dept Manager',
        'role' => 'Manager',
        'department' => (object) [
            'name' => 'Engineering',
            'alias' => 'ENG',
        ],
    ];

    config()->set('stores-withdrawal.approved_by_overrides', [
        '7046' => [
            'name' => 'Forced Approver',
            'title' => 'Acting Manager',
            'priority' => 'override',
        ],
    ]);

    $approver = (object) [
        'name' => 'Forced Approver',
        'title' => 'Acting Manager',
    ];

    $sws = (object) [
        'sws_number' => 'SWS-CFG-OVERRIDE-001',
        'sws_date' => now()->toDateString(),
        'department_code' => '7046',
        'department_name' => 'Engineering',
        'type' => 'opex',
        'info' => null,
        'created_by_name' => 'Requester User',
        'approved_by_name' => null,
        'created_at' => now(),
        'approved_at' => null,
    ];

    $html = view('pdf.store-withdrawal-slip', [
        'sws' => $sws,
        'items' => new Collection,
        'manager' => $manager,
        'approver' => $approver,
    ])->render();

    expect($html)->toContain('Forced Approver')
        ->and($html)->toContain('Acting Manager')
        ->and($html)->not->toContain('Dept Manager');
});

it('keeps manager when fallback override exists and manager is present', function () {
    $approver = (object) [
        'name' => 'Dept Manager',
        'title' => 'ENG Manager',
    ];

    config()->set('stores-withdrawal.approved_by_overrides', [
        '7046' => [
            'name' => 'Config Supervisor',
            'title' => 'Engineering Supervisor',
            'priority' => 'fallback',
        ],
    ]);

    $sws = (object) [
        'sws_number' => 'SWS-CFG-KEEP-MGR-001',
        'sws_date' => now()->toDateString(),
        'department_code' => '7046',
        'department_name' => 'Engineering',
        'type' => 'opex',
        'info' => null,
        'created_by_name' => 'Requester User',
        'approved_by_name' => null,
        'created_at' => now(),
        'approved_at' => null,
    ];

    $html = view('pdf.store-withdrawal-slip', [
        'sws' => $sws,
        'items' => new Collection,
        'manager' => null,
        'approver' => $approver,
    ])->render();

    expect($html)->toContain('Dept Manager')
        ->and($html)->toContain('ENG Manager')
        ->and($html)->not->toContain('Config Supervisor');
});

it('renders capex columns and tag on sws print layout', function () {
    $sws = (object) [
        'sws_number' => 'SWS-CAPEX-001',
        'sws_date' => now()->toDateString(),
        'department_code' => '7046',
        'department_name' => 'Engineering',
        'type' => 'capex',
        'info' => null,
        'created_by_name' => 'Requester User',
        'approved_by_name' => null,
        'created_at' => now(),
        'approved_at' => null,
    ];

    $items = new Collection([
        (object) [
            'item_name' => 'Capex Part',
            'item_code' => 'CPX-001',
            'product_code' => 'CPX-001',
            'quantity' => 1,
            'stock_on_hand_snapshot' => 1,
            'uom' => 'PCS',
            'item_uom_name' => 'PCS',
            'prs_number' => 'PRS-001',
            'po_number' => 'PO-001',
            'rr_number' => 'RR-001',
        ],
    ]);

    $html = view('pdf.store-withdrawal-slip', [
        'sws' => $sws,
        'items' => $items,
        'manager' => null,
        'approver' => null,
    ])->render();

    expect($html)->toContain('(CAPEX)')
        ->and($html)->toContain('PRS-001')
        ->and($html)->toContain('PO-001')
        ->and($html)->toContain('RR-001')
        ->and(substr_count($html, '>Requested By<'))->toBe(2)
        ->and(substr_count($html, '>Approved By<'))->toBe(1);
});
