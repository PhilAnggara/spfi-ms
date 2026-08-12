<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;

class RbacGuards
{
    public const PROTECTED_PERMISSION = 'reset-activity-logs';

    public const PROTECTED_ROLE = 'administrator';

    public static function actorIsAdministrator(?User $actor): bool
    {
        return (bool) $actor?->hasRole(self::PROTECTED_ROLE);
    }

    /**
     * @param  list<string>|null  $permissions
     * @param  Collection<int, string>|null  $existingNames
     * @return list<string>
     */
    public static function sanitizePermissionNames(?User $actor, ?array $permissions, ?Collection $existingNames = null): array
    {
        $names = array_values(array_unique($permissions ?? []));
        $existingNames ??= collect();

        if (self::actorIsAdministrator($actor)) {
            return $names;
        }

        $names = array_values(array_filter(
            $names,
            fn (string $name): bool => $name !== self::PROTECTED_PERMISSION
        ));

        if ($existingNames->contains(self::PROTECTED_PERMISSION)) {
            $names[] = self::PROTECTED_PERMISSION;
        }

        return array_values(array_unique($names));
    }

    /**
     * @param  list<string>|null  $roles
     * @param  Collection<int, string>  $existingRoleNames
     * @return list<string>|null Null means the update must be rejected.
     */
    public static function sanitizeRoleNames(?User $actor, ?array $roles, Collection $existingRoleNames): ?array
    {
        $names = array_values(array_unique($roles ?? []));

        if (self::actorIsAdministrator($actor)) {
            return $names;
        }

        $hadAdministrator = $existingRoleNames->contains(self::PROTECTED_ROLE);
        $wantsAdministrator = in_array(self::PROTECTED_ROLE, $names, true);

        if ($hadAdministrator !== $wantsAdministrator) {
            return null;
        }

        if (! $hadAdministrator) {
            $names = array_values(array_filter(
                $names,
                fn (string $name): bool => $name !== self::PROTECTED_ROLE
            ));
        }

        return $names;
    }
}
