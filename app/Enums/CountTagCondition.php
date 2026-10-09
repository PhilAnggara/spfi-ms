<?php

namespace App\Enums;

enum CountTagCondition: string
{
    case Good = 'Good';
    case Damaged = 'Damaged';
    case Expired = 'Expired';
    case Quarantine = 'Quarantine';

    public function label(): string
    {
        return $this->value;
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Good => 'ct-condition-badge is-good',
            self::Damaged => 'ct-condition-badge is-damaged',
            self::Expired => 'ct-condition-badge is-expired',
            self::Quarantine => 'ct-condition-badge is-quarantine',
        };
    }

    public static function tryFromLegacy(?string $value): ?self
    {
        $normalized = trim((string) $value);

        if ($normalized === '') {
            return null;
        }

        foreach (self::cases() as $case) {
            if (strcasecmp($case->value, $normalized) === 0) {
                return $case;
            }
        }

        return null;
    }
}
