<?php

use App\Enums\TermOfPaymentType;

it('exposes po form options for credit and cash subtypes', function () {
    expect(TermOfPaymentType::poFormOptions())->toBe([
        'credit' => 'Credit',
        'cash_advance' => 'Cash Advance',
        'materials_in_transit' => 'Materials In Transit',
    ]);
});

it('maps credit account codes for accounting entries', function (string $value, string $account) {
    expect(TermOfPaymentType::fromStored($value)?->creditAccountCode())->toBe($account);
})->with([
    'credit' => ['credit', '201'],
    'cash advance' => ['cash_advance', '122'],
    'materials in transit' => ['materials_in_transit', '148'],
    'legacy cash' => ['cash', '148'],
]);

it('treats cash subtypes and legacy cash as cash for reports', function () {
    expect(TermOfPaymentType::isCashValue('cash'))->toBeTrue()
        ->and(TermOfPaymentType::isCashValue('cash_advance'))->toBeTrue()
        ->and(TermOfPaymentType::isCashValue('materials_in_transit'))->toBeTrue()
        ->and(TermOfPaymentType::isCashValue('credit'))->toBeFalse()
        ->and(TermOfPaymentType::cashReportValues())->toBe([
            'cash',
            'cash_advance',
            'materials_in_transit',
        ]);
});

it('maps canvassing cash default to materials in transit for po forms', function () {
    expect(TermOfPaymentType::fromCanvassingDefault('cash'))->toBe('materials_in_transit')
        ->and(TermOfPaymentType::fromCanvassingDefault('credit'))->toBe('credit')
        ->and(TermOfPaymentType::fromCanvassingDefault(null))->toBeNull();
});
