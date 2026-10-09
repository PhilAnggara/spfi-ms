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
