<?php

namespace App\Http\Middleware;

use App\Models\UserActivityLog;
use App\Services\UserActivityLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackUserLastSeen
{
    public function __construct(private UserActivityLogger $logger) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = $request->user();

        if ($user === null || $request->routeIs('logout')) {
            return $response;
        }

        $shouldTouch = $user->last_seen_at === null
            || $user->last_seen_at->lt(now()->subMinute());

        if (! $shouldTouch) {
            return $response;
        }

        $this->logger->touchLastSeen($user, $request);

        $shouldLogActive = UserActivityLog::query()
            ->where('user_id', $user->id)
            ->where('action', UserActivityLog::ACTION_ACTIVE)
            ->where('created_at', '>=', now()->subMinutes(5))
            ->doesntExist();

        if ($shouldLogActive) {
            $this->logger->log(
                $user,
                UserActivityLog::ACTION_ACTIVE,
                $request,
                meta: [
                    'route' => $request->route()?->getName(),
                    'path' => '/'.$request->path(),
                ],
            );
        }

        return $response;
    }
}
