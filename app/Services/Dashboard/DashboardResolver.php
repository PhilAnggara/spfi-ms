<?php

namespace App\Services\Dashboard;

use App\Models\User;

class DashboardResolver
{
    /**
     * @var array<string, string>
     */
    private const ALIAS_MAP = [
        'MD' => 'md',
        'PUR' => 'purchasing',
        'IM' => 'im',
        'FIN' => 'finance',
        'ENG' => 'engineering',
        'IT' => 'admin',
    ];

    /**
     * @var array<string, string>
     */
    private const NAME_MAP = [
        'Office Of The Managing Director' => 'md',
        'Purchasing' => 'purchasing',
        'Inventory Management' => 'im',
        'Finance' => 'finance',
        'Engineering' => 'engineering',
        'Information Technology' => 'admin',
    ];

    public function resolve(User $user): string
    {
        $user->loadMissing('department');

        $alias = strtoupper(trim((string) ($user->department?->alias ?? '')));
        if ($alias !== '' && isset(self::ALIAS_MAP[$alias])) {
            return self::ALIAS_MAP[$alias];
        }

        $name = trim((string) ($user->department?->name ?? ''));
        if ($name !== '' && isset(self::NAME_MAP[$name])) {
            return self::NAME_MAP[$name];
        }

        return 'default';
    }
}
