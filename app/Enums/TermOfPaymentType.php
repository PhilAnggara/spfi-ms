<?php

namespace App\Enums;

enum TermOfPaymentType: string
{
    case Credit = 'credit';
    case CashAdvance = 'cash_advance';
    case MaterialsInTransit = 'materials_in_transit';

    public function label(): string
    {
        return match ($this) {
            self::Credit => 'Credit',
            self::CashAdvance => 'Cash Advance',
            self::MaterialsInTransit => 'Materials In Transit',
        };
    }

    public function isCash(): bool
    {
        return $this !== self::Credit;
    }

    public function creditAccountCode(): string
    {
        return match ($this) {
            self::Credit => '201',
            self::CashAdvance => '122',
            self::MaterialsInTransit => '148',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function poFormOptions(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }

    public static function validationRule(): string
    {
        return 'in:'.implode(',', array_column(self::cases(), 'value'));
    }

    /**
     * Resolve a stored value including legacy "cash" (treated as Materials In Transit).
     */
    public static function fromStored(?string $value): ?self
    {
        $normalized = strtolower(trim((string) $value));

        if ($normalized === '') {
            return null;
        }

        if ($normalized === 'cash') {
            return self::MaterialsInTransit;
        }

        return self::tryFrom($normalized);
    }

    /**
     * Map canvassing/supplier cash|credit defaults into PO form values.
     */
    public static function fromCanvassingDefault(?string $value): ?string
    {
        $normalized = strtolower(trim((string) $value));

        return match ($normalized) {
            'credit' => self::Credit->value,
            'cash' => self::MaterialsInTransit->value,
            'cash_advance', 'materials_in_transit' => $normalized,
            default => null,
        };
    }

    /**
     * Values that count as "cash" for purchasing report filters.
     *
     * @return list<string>
     */
    public static function cashReportValues(): array
    {
        return [
            'cash',
            self::CashAdvance->value,
            self::MaterialsInTransit->value,
        ];
    }

    public static function isCashValue(?string $value): bool
    {
        $normalized = strtolower(trim((string) $value));

        if ($normalized === 'cash') {
            return true;
        }

        $type = self::tryFrom($normalized);

        return $type?->isCash() ?? false;
    }

    /**
     * Human-readable label for UI badges (legacy "cash" stays "Cash").
     */
    public static function displayLabel(?string $value): string
    {
        $normalized = strtolower(trim((string) $value));

        if ($normalized === '') {
            return '';
        }

        if ($normalized === 'cash') {
            return 'Cash';
        }

        $type = self::tryFrom($normalized);

        if ($type !== null) {
            return $type->label();
        }

        return ucfirst($normalized);
    }

    /**
     * Bootstrap light badge classes aligned with PO status/meta badges.
     */
    public static function badgeClass(?string $value): string
    {
        $normalized = strtolower(trim((string) $value));

        if ($normalized === '') {
            return 'badge bg-light-secondary text-secondary';
        }

        if ($normalized === 'credit') {
            return 'badge bg-light-info text-info';
        }

        if (self::isCashValue($normalized)) {
            return 'badge bg-light-primary text-primary';
        }

        return 'badge bg-light-secondary text-secondary';
    }

    /**
     * Plain-text display for PDF prints: "CREDIT • DP 50%".
     */
    public static function formatDisplay(?string $type, ?string $description = null): string
    {
        $typeLabel = self::displayLabel($type);
        $typeDisplay = $typeLabel !== '' ? strtoupper($typeLabel) : '';
        $descriptionDisplay = trim((string) $description);

        $parts = array_values(array_filter([$typeDisplay, $descriptionDisplay], fn (string $part): bool => $part !== ''));

        if ($parts === []) {
            return '-';
        }

        return implode(' • ', $parts);
    }
}
