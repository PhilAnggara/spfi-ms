<?php

namespace App\Enums;

enum ScreenMessageDisplayMode: string
{
    case AutoOnly = 'auto_only';
    case UserClosable = 'user_closable';
    case Permanent = 'permanent';

    public function label(): string
    {
        return match ($this) {
            self::AutoOnly => 'Auto close only',
            self::UserClosable => 'User closable',
            self::Permanent => 'Permanent',
        };
    }

    public function requiresDuration(): bool
    {
        return $this === self::AutoOnly;
    }

    public function allowsUserClose(): bool
    {
        return $this === self::UserClosable;
    }

    public function isPermanent(): bool
    {
        return $this === self::Permanent;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
