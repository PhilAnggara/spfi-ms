<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class DocumentAccess
{
    /**
     * Whether the actor shares the creator's department.
     */
    public static function sameCreatorDepartment(?User $actor, ?User $creator): bool
    {
        if (! $actor || ! $creator) {
            return false;
        }

        if (! $actor->department_id || ! $creator->department_id) {
            return false;
        }

        return (int) $actor->department_id === (int) $creator->department_id;
    }

    /**
     * @param  list<string>  $permissions
     */
    public static function canAny(?User $actor, array $permissions): bool
    {
        if (! $actor) {
            return false;
        }

        foreach ($permissions as $permission) {
            if ($actor->can($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * View: own, same creator department, or view-all.
     */
    public static function canView(
        ?User $actor,
        int $ownerId,
        ?User $creator,
        string $viewAllPermission,
    ): bool {
        if (! $actor) {
            return false;
        }

        if ($actor->can($viewAllPermission)) {
            return true;
        }

        if ((int) $actor->id === $ownerId) {
            return true;
        }

        return self::sameCreatorDepartment($actor, $creator);
    }

    /**
     * Mutate (update/delete): own, department elevated, or global elevated.
     */
    public static function canMutate(
        ?User $actor,
        int $ownerId,
        ?User $creator,
        string $departmentPermission,
        string $allPermission,
    ): bool {
        if (! $actor) {
            return false;
        }

        if ($actor->can($allPermission)) {
            return true;
        }

        if ((int) $actor->id === $ownerId) {
            return true;
        }

        return $actor->can($departmentPermission)
            && self::sameCreatorDepartment($actor, $creator);
    }

    /**
     * Convenience for Eloquent models with a user relation as creator.
     */
    public static function canViewPrs(?User $actor, Model $prs): bool
    {
        $prs->loadMissing('user');

        return self::canView(
            $actor,
            (int) $prs->getAttribute('user_id'),
            $prs->getRelationValue('user'),
            'view-all-prs',
        );
    }

    public static function canUpdatePrs(?User $actor, Model $prs): bool
    {
        $prs->loadMissing('user');

        return self::canMutate(
            $actor,
            (int) $prs->getAttribute('user_id'),
            $prs->getRelationValue('user'),
            'update-department-prs',
            'update-all-prs',
        );
    }

    public static function canDeletePrs(?User $actor, Model $prs): bool
    {
        $prs->loadMissing('user');

        return self::canMutate(
            $actor,
            (int) $prs->getAttribute('user_id'),
            $prs->getRelationValue('user'),
            'delete-department-prs',
            'delete-all-prs',
        );
    }
}
