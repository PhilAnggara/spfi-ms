<?php

namespace App\Enums;

enum ScreenMessageTargetType: string
{
    case User = 'user';
    case Department = 'department';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
