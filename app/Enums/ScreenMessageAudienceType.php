<?php

namespace App\Enums;

enum ScreenMessageAudienceType: string
{
    case All = 'all';
    case Users = 'users';
    case Departments = 'departments';

    public function label(): string
    {
        return match ($this) {
            self::All => 'All users',
            self::Users => 'Selected users',
            self::Departments => 'Selected departments',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
