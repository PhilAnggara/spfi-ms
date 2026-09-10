<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Models\UserActivityLog;
use App\Services\UserActivityLogger;
use App\Support\UserActivitySubject;
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
        'active-sessions.index',
        'active-sessions.show',
        'screen-messages.inbox.pending',
        'chat.conversations.index',
        'chat.users.search',
        'chat.unread-count',
    ];

    /**
     * Infrastructure path prefixes that are not real page visits.
     *
     * @var list<string>
     */
    private const IGNORED_PATH_PREFIXES = [
        'broadcasting',
        'livewire',
        'sanctum',
        'horizon',
        'telescope',
        '_debugbar',
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

        if ($response->getStatusCode() >= 400) {
            return $response;
        }

        $routeName = $request->route()?->getName();
        $path = '/'.$request->path();

        if (UserActivitySubject::isPrintOrDocumentReportRoute($routeName)) {
            $this->logPrintIfNeeded($user, $request, $path);

            return $response;
        }

        if ($this->shouldLogPageVisit($user->id, $routeName, $path)) {
            $this->logger->log(
                $user,
                UserActivityLog::ACTION_ACTIVE,
                $request,
                meta: array_filter([
                    'route' => $routeName,
                    'path' => $path,
                    'page' => UserActivityLog::labelForRoute($routeName, $path),
                    ...UserActivitySubject::resolve($request, $user),
                ], static fn ($value) => $value !== null && $value !== ''),
            );
        }

        return $response;
    }

    private function logPrintIfNeeded(User $user, Request $request, string $path): void
    {
        if (! $this->shouldLogPrint($user->id, $path)) {
            return;
        }

        $routeName = $request->route()?->getName();

        $this->logger->log(
            $user,
            UserActivityLog::ACTION_PRINTED,
            $request,
            meta: array_filter([
                'route' => $routeName,
                'path' => $path,
                'page' => UserActivityLog::labelForRoute($routeName, $path),
                'method' => $request->method(),
                ...UserActivitySubject::resolve($request, $user),
            ], static fn ($value) => $value !== null && $value !== ''),
        );
    }

    private function shouldLogPrint(int $userId, string $path): bool
    {
        $recentLogs = UserActivityLog::query()
            ->where('user_id', $userId)
            ->where('action', UserActivityLog::ACTION_PRINTED)
            ->where('created_at', '>=', now()->subMinutes(5))
            ->latest('id')
            ->limit(20)
            ->get(['meta']);

        foreach ($recentLogs as $log) {
            if (($log->meta['path'] ?? null) === $path) {
                return false;
            }
        }

        return true;
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

            // Opening different chat conversations shares one route name; only
            // treat the same conversation path as a duplicate visit.
            if ($routeName === 'chat.messages.index') {
                if ($samePath) {
                    return false;
                }

                continue;
            }

            if ($sameRoute || $samePath) {
                return false;
            }
        }

        return true;
    }

    private function shouldSkipActivityLog(Request $request): bool
    {
        if (! $request->isMethod('GET')) {
            return true;
        }

        if ($request->routeIs(...self::IGNORED_ACTIVITY_ROUTES)) {
            return true;
        }

        $path = trim($request->path(), '/');

        foreach (self::IGNORED_PATH_PREFIXES as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return true;
            }
        }

        $routeName = (string) $request->route()?->getName();

        if ($routeName !== '' && (
            str_ends_with($routeName, '.datatables')
            || str_contains($routeName, 'livewire')
            || str_ends_with($routeName, '.unread-count')
            || str_ends_with($routeName, '.recent')
            || str_ends_with($routeName, '.pending')
        )) {
            return true;
        }

        // Keep Active Users list/detail AJAX refreshes out of visit history,
        // but allow AJAX page navigations (e.g. PO list filters) to log once.
        if ($request->ajax() && $request->routeIs('active-sessions.index', 'active-sessions.show')) {
            return true;
        }

        return false;
    }
}
