<?php

namespace App\Enums;

enum ChatSystemBroadcastAudience: string
{
    case All = 'all';
    case Departments = 'departments';
    case Users = 'users';

    public function label(): string
    {
        return match ($this) {
            self::All => 'All users',
            self::Departments => 'Selected departments',
            self::Users => 'Selected users',
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
