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
    ])->render();

    expect($html)->toContain('Stores Withdrawal Slip')
        ->and($html)->toContain('PT. SINAR PURE FOODS INTERNATIONAL')
        ->and($html)->toContain('SWS Number')
        ->and($html)->toContain('SWS-PRINT-001')
        ->and($html)->toContain('Requester User')
        ->and($html)->toContain('Dept Manager')
        ->and($html)->toContain(get_job_title($manager))
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
    ])->render();

    expect($html)->toContain('Legacy Approver')
        ->and($html)->toContain('Approver')
        ->and(substr_count($html, '>Approved By<'))->toBe(1);
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
    ])->render();

    expect($html)->toContain('(CAPEX)')
        ->and($html)->toContain('PRS-001')
        ->and($html)->toContain('PO-001')
        ->and($html)->toContain('RR-001')
        ->and(substr_count($html, '>Requested By<'))->toBe(2)
        ->and(substr_count($html, '>Approved By<'))->toBe(1);
});
