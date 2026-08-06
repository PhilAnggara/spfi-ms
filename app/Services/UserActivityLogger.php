<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserActivityLog;
use Illuminate\Http\Request;

class UserActivityLogger
{
    /**
     * @param  array<string, mixed>|null  $meta
     */
    public function log(
        User $user,
        string $action,
        Request $request,
        ?User $actor = null,
        ?array $meta = null,
    ): UserActivityLog {
        return UserActivityLog::query()->create([
            'user_id' => $user->id,
            'actor_id' => $actor?->id,
            'action' => $action,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'meta' => $meta,
        ]);
    }

    public function touchLastSeen(User $user, Request $request): void
    {
        $user->forceFill([
            'last_seen_at' => now(),
            'last_ip_address' => $request->ip(),
            'last_user_agent' => $request->userAgent(),
        ])->save();
    }
}
