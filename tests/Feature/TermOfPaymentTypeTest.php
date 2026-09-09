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

it('builds display labels for ui badges', function () {
    expect(TermOfPaymentType::displayLabel('credit'))->toBe('Credit')
        ->and(TermOfPaymentType::displayLabel('cash'))->toBe('Cash')
        ->and(TermOfPaymentType::displayLabel('cash_advance'))->toBe('Cash Advance')
        ->and(TermOfPaymentType::displayLabel('materials_in_transit'))->toBe('Materials In Transit')
        ->and(TermOfPaymentType::displayLabel(null))->toBe('');
});

it('maps badge classes for payment types', function () {
    expect(TermOfPaymentType::badgeClass('credit'))->toBe('badge bg-light-info text-info')
        ->and(TermOfPaymentType::badgeClass('cash'))->toBe('badge bg-light-primary text-primary')
        ->and(TermOfPaymentType::badgeClass('cash_advance'))->toBe('badge bg-light-primary text-primary')
        ->and(TermOfPaymentType::badgeClass('materials_in_transit'))->toBe('badge bg-light-primary text-primary')
        ->and(TermOfPaymentType::badgeClass(null))->toBe('badge bg-light-secondary text-secondary')
        ->and(TermOfPaymentType::badgeClass('unknown'))->toBe('badge bg-light-secondary text-secondary');
});

it('formats pdf term of payment display with a bullet separator', function () {
    expect(TermOfPaymentType::formatDisplay('credit', 'DP 50%'))->toBe('CREDIT • DP 50%')
        ->and(TermOfPaymentType::formatDisplay('cash_advance', null))->toBe('CASH ADVANCE')
        ->and(TermOfPaymentType::formatDisplay(null, 'DP 50%'))->toBe('DP 50%')
        ->and(TermOfPaymentType::formatDisplay('cash', 'COD'))->toBe('CASH • COD')
        ->and(TermOfPaymentType::formatDisplay(null, null))->toBe('-');
});
