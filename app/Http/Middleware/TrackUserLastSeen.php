<?php

namespace App\Http\Middleware;

use App\Models\UserActivityLog;
use App\Services\UserActivityLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackUserLastSeen
{
    /**
     * Background / polling endpoints that should not create activity history rows.
     *
     * @var list<string>
     */
    private const IGNORED_ACTIVITY_ROUTES = [
        'logout',
        'notifications.recent',
        'notifications.unread-count',
        'notifications.read',
        'notifications.mark-all-read',
        'notifications.destroy',
        'notifications.clear-read',
    ];

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

        if ($shouldTouch) {
            $this->logger->touchLastSeen($user, $request);
        }

        if ($this->shouldSkipActivityLog($request)) {
            return $response;
        }

        $routeName = $request->route()?->getName();
        $path = '/'.$request->path();

        if ($this->shouldLogPageVisit($user->id, $routeName, $path)) {
            $this->logger->log(
                $user,
                UserActivityLog::ACTION_ACTIVE,
                $request,
                meta: [
                    'route' => $routeName,
                    'path' => $path,
                    'page' => UserActivityLog::labelForRoute($routeName, $path),
                ],
            );
        }

        return $response;
    }

    private function shouldLogPageVisit(int $userId, ?string $routeName, string $path): bool
    {
        $recentLogs = UserActivityLog::query()
            ->where('user_id', $userId)
            ->where('action', UserActivityLog::ACTION_ACTIVE)
            ->where('created_at', '>=', now()->subMinutes(5))
            ->latest('id')
            ->limit(20)
            ->get(['meta', 'created_at']);

        foreach ($recentLogs as $log) {
            $meta = $log->meta ?? [];
            $sameRoute = $routeName !== null
                && $routeName !== ''
                && ($meta['route'] ?? null) === $routeName;
            $samePath = ($meta['path'] ?? null) === $path;

            if ($sameRoute || $samePath) {
                return false;
            }
        }

        return true;
    }

    private function shouldSkipActivityLog(Request $request): bool
    {
        if ($request->ajax()) {
            return true;
        }

        if ($request->routeIs(...self::IGNORED_ACTIVITY_ROUTES)) {
            return true;
        }

        $routeName = (string) $request->route()?->getName();

        if ($routeName !== '' && (
            str_ends_with($routeName, '.datatables')
            || str_contains($routeName, 'livewire')
            || str_ends_with($routeName, '.unread-count')
            || str_ends_with($routeName, '.recent')
        )) {
            return true;
        }

        return false;
    }
}
