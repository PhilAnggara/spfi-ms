<?php

namespace App\Http\Middleware;

use App\Models\UserActivityLog;
use App\Services\UserActivityLogger;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogUserCrudActivity
{
    /**
     * Auth / force-logout / polling endpoints that should not create CRUD activity rows.
     *
     * @var list<string>
     */
    private const IGNORED_ACTIVITY_ROUTES = [
        'login',
        'logout',
        'password.email',
        'password.store',
        'password.update',
        'verification.send',
        'active-sessions.destroy-sessions',
        'active-sessions.reset-activity-logs',
        'notifications.recent',
        'notifications.unread-count',
        'notifications.read',
        'notifications.mark-all-read',
        'notifications.destroy',
        'notifications.clear-read',
    ];

    /**
     * Infrastructure / auth path prefixes that are not application CRUD.
     *
     * @var list<string>
     */
    private const IGNORED_PATH_PREFIXES = [
        'login',
        'logout',
        'broadcasting',
        'livewire',
        'sanctum',
        'horizon',
        'telescope',
        '_debugbar',
        'forgot-password',
        'reset-password',
        'confirm-password',
        'email/verification-notification',
    ];

    /**
     * Named route action segments that mutate nothing meaningful for activity history.
     *
     * @var list<string>
     */
    private const SKIPPED_ROUTE_ACTIONS = [
        'preview',
        'print',
        'datatables',
        'report',
        'reports',
    ];

    public function __construct(private UserActivityLogger $logger) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = $request->user();

        if ($user === null || ! $this->isMutatingMethod($request)) {
            return $response;
        }

        if ($response->getStatusCode() >= 400) {
            return $response;
        }

        if ($request->session()->has('errors')) {
            return $response;
        }

        if ($this->shouldSkipActivityLog($request)) {
            return $response;
        }

        $action = $this->actionForRoute($request);

        if ($action === null) {
            return $response;
        }

        $routeName = $request->route()?->getName();
        $path = '/'.$request->path();
        $subject = $this->resolveSubject($request);

        $this->logger->log(
            $user,
            $action,
            $request,
            meta: array_filter([
                'route' => $routeName,
                'path' => $path,
                'page' => UserActivityLog::labelForRoute($routeName, $path),
                'method' => $request->method(),
                ...$subject,
            ], static fn ($value) => $value !== null && $value !== ''),
        );

        return $response;
    }

    private function isMutatingMethod(Request $request): bool
    {
        return in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }

    /**
     * Named mutating routes are logged. Unnamed noise (login, broadcasting) is skipped.
     */
    private function actionForRoute(Request $request): ?string
    {
        $routeName = (string) $request->route()?->getName();

        if ($routeName === '') {
            return null;
        }

        $parts = explode('.', $routeName);
        $segment = str_replace('-', '_', (string) end($parts));

        if ($segment === '' || in_array($segment, self::SKIPPED_ROUTE_ACTIONS, true)) {
            return null;
        }

        return match ($segment) {
            'store' => UserActivityLog::ACTION_CREATED,
            'update', 'number' => UserActivityLog::ACTION_UPDATED,
            'destroy' => UserActivityLog::ACTION_DELETED,
            'approve' => UserActivityLog::ACTION_APPROVED,
            'reject' => UserActivityLog::ACTION_REJECTED,
            'hold' => UserActivityLog::ACTION_HELD,
            'reassign' => UserActivityLog::ACTION_REASSIGNED,
            'submit' => UserActivityLog::ACTION_SUBMITTED,
            'withdraw' => UserActivityLog::ACTION_WITHDRAWN,
            'cancel' => UserActivityLog::ACTION_CANCELLED,
            'request_changes' => UserActivityLog::ACTION_REQUESTED_CHANGES,
            default => $segment,
        };
    }

    /**
     * @return array{subject?: string, subject_type?: string, subject_id?: int|string}
     */
    private function resolveSubject(Request $request): array
    {
        $parameters = $request->route()?->parameters() ?? [];

        foreach ($parameters as $name => $value) {
            if ($value instanceof Model) {
                $key = $value->getKey();

                return [
                    'subject' => '#'.$key,
                    'subject_type' => $name,
                    'subject_id' => $key,
                ];
            }
        }

        foreach ($parameters as $name => $value) {
            if (is_object($value) || $value === null || $value === '') {
                continue;
            }

            if (is_numeric($value) || (is_string($value) && ctype_digit($value))) {
                $key = is_numeric($value) ? $value + 0 : $value;

                return [
                    'subject' => '#'.$key,
                    'subject_type' => $name,
                    'subject_id' => $key,
                ];
            }
        }

        return [];
    }

    private function shouldSkipActivityLog(Request $request): bool
    {
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
        )) {
            return true;
        }

        return false;
    }
}
