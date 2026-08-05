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
    ];

    public function resolve(User $user): string
    {
        if ($user->hasAnyRole(['administrator', 'general-manager', 'it-manager', 'it-staff'])) {
            return 'admin';
        }

        $user->loadMissing('department');

        $alias = strtoupper(trim((string) ($user->department?->alias ?? '')));
        if ($alias === 'IT') {
            return 'admin';
        }
        if ($alias !== '' && isset(self::ALIAS_MAP[$alias])) {
            return self::ALIAS_MAP[$alias];
        }

        $name = trim((string) ($user->department?->name ?? ''));
        if ($name === 'Information Technology') {
            return 'admin';
        }
        if ($name !== '' && isset(self::NAME_MAP[$name])) {
            return self::NAME_MAP[$name];
        }

        return 'default';
    }
}
