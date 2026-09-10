<?php

namespace App\Enums;

enum ScreenMessageTheme: string
{
    case Default = 'default';
    case Info = 'info';
    case Success = 'success';
    case Warning = 'warning';
    case Danger = 'danger';

    public function label(): string
    {
        return match ($this) {
            self::Default => 'Default',
            self::Info => 'Info',
            self::Success => 'Success',
            self::Warning => 'Warning',
            self::Danger => 'Danger',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Default => 'bg-light-secondary text-secondary',
            self::Info => 'bg-light-info text-info',
            self::Success => 'bg-light-success text-success',
            self::Warning => 'bg-light-warning text-warning',
            self::Danger => 'bg-light-danger text-danger',
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
