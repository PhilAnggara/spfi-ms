<?php

namespace App\Enums;

enum MessagePersona: string
{
    case User = 'user';
    case System = 'system';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
