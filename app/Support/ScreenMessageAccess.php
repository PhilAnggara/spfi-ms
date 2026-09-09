<?php

namespace App\Support;

use App\Enums\ScreenMessageAudienceType;
use App\Models\ScreenMessage;
use App\Models\User;

class ScreenMessageAccess
{
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

    public static function canViewAny(?User $actor): bool
    {
        return self::canAny($actor, [
            'view-own-screen-messages',
            'view-department-screen-messages',
            'view-all-screen-messages',
        ]);
    }

    public static function canCreate(?User $actor): bool
    {
        return self::canAny($actor, [
            'create-department-screen-messages',
            'create-all-screen-messages',
        ]);
    }

    public static function canCreatePermanent(?User $actor): bool
    {
        return (bool) $actor?->can('create-permanent-screen-messages');
    }

    public static function canCreateForAll(?User $actor): bool
    {
        return (bool) $actor?->can('create-all-screen-messages');
    }

    public static function canView(?User $actor, ScreenMessage $message): bool
    {
        if (! $actor) {
            return false;
        }

        if ($actor->can('view-all-screen-messages')) {
            return true;
        }

        $message->loadMissing('user');

        if ($actor->can('view-own-screen-messages') && (int) $actor->id === (int) $message->user_id) {
            return true;
        }

        return $actor->can('view-department-screen-messages')
            && DocumentAccess::sameCreatorDepartment($actor, $message->user);
    }

    public static function canViewReplies(?User $actor, ScreenMessage $message): bool
    {
        if (! $actor) {
            return false;
        }

        if ($actor->can('view-all-screen-message-replies')) {
            return true;
        }

        $message->loadMissing('user');

        if ($actor->can('view-own-screen-message-replies') && (int) $actor->id === (int) $message->user_id) {
            return true;
        }

        return $actor->can('view-department-screen-message-replies')
            && DocumentAccess::sameCreatorDepartment($actor, $message->user);
    }

    public static function canDeactivate(?User $actor, ScreenMessage $message): bool
    {
        return self::canMutate(
            $actor,
            $message,
            'deactivate-own-screen-messages',
            'deactivate-department-screen-messages',
            'deactivate-all-screen-messages',
        );
    }

    public static function canDelete(?User $actor, ScreenMessage $message): bool
    {
        return self::canMutate(
            $actor,
            $message,
            'delete-own-screen-messages',
            'delete-department-screen-messages',
            'delete-all-screen-messages',
        );
    }

    public static function canTargetAudience(?User $actor, ScreenMessageAudienceType $audienceType): bool
    {
        if (! $actor) {
            return false;
        }

        if ($actor->can('create-all-screen-messages')) {
            return true;
        }

        if (! $actor->can('create-department-screen-messages')) {
            return false;
        }

        return $audienceType !== ScreenMessageAudienceType::All;
    }

    private static function canMutate(
        ?User $actor,
        ScreenMessage $message,
        string $ownPermission,
        string $departmentPermission,
        string $allPermission,
    ): bool {
        if (! $actor) {
            return false;
        }

        if ($actor->can($allPermission)) {
            return true;
        }

        $message->loadMissing('user');

        if ($actor->can($ownPermission) && (int) $actor->id === (int) $message->user_id) {
            return true;
        }

        return $actor->can($departmentPermission)
            && DocumentAccess::sameCreatorDepartment($actor, $message->user);
    }
}
