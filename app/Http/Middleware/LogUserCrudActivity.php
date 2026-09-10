<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Models\UserActivityLog;
use App\Services\UserActivityLogger;
use App\Support\UserActivitySubject;
use Closure;
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
        'chat.delivered',
        'chat.read',
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
        'datatables',
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

        $routeName = (string) $request->route()?->getName();

        if ($routeName === 'chat.typing') {
            $this->logTypingIfNeeded($user, $request);

            return $response;
        }

        $action = $this->actionForRoute($request);

        if ($action === null) {
            return $response;
        }

        $path = '/'.$request->path();
        $subject = UserActivitySubject::resolve($request, $user);

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

        if (UserActivitySubject::isGeneratedReportRoute($routeName)) {
            return UserActivityLog::ACTION_EXPORTED;
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
            'print', 'report' => UserActivityLog::ACTION_PRINTED,
            default => $segment,
        };
    }

    private function logTypingIfNeeded(User $user, Request $request): void
    {
        if (! $request->boolean('typing')) {
            return;
        }

        $peerId = (int) $request->input('user_id');

        if ($peerId <= 0 || $peerId === $user->id) {
            return;
        }

        if (! $this->shouldLogTyping($user->id, $peerId)) {
            return;
        }

        $peer = User::query()->find($peerId);
        $name = trim((string) ($peer?->name ?? ''));
        $subject = $name !== '' ? '#'.$peerId.' '.$name : '#'.$peerId;
        $path = '/'.$request->path();

        $this->logger->log(
            $user,
            UserActivityLog::ACTION_TYPING,
            $request,
            meta: array_filter([
                'route' => 'chat.typing',
                'path' => $path,
                'page' => 'Chat',
                'method' => $request->method(),
                'subject' => $subject,
                'subject_type' => 'user',
                'subject_id' => $peerId,
                'subject_code' => $peer?->name,
            ], static fn ($value) => $value !== null && $value !== ''),
        );
    }

    private function shouldLogTyping(int $userId, int $peerId): bool
    {
        $recent = UserActivityLog::query()
            ->where('user_id', $userId)
            ->whereIn('action', [
                UserActivityLog::ACTION_TYPING,
                UserActivityLog::ACTION_CREATED,
            ])
            ->latest('id')
            ->limit(100)
            ->get(['id', 'action', 'meta']);

        $lastChatSendId = null;

        foreach ($recent as $log) {
            if ($log->action !== UserActivityLog::ACTION_CREATED) {
                continue;
            }

            $route = (string) ($log->meta['route'] ?? '');
            $subjectId = (int) ($log->meta['subject_id'] ?? 0);

            if ($subjectId === $peerId
                && in_array($route, ['chat.messages.store', 'chat.direct-messages.store'], true)) {
                $lastChatSendId = (int) $log->id;
                break;
            }
        }

        foreach ($recent as $log) {
            if ($log->action !== UserActivityLog::ACTION_TYPING) {
                continue;
            }

            if ((int) ($log->meta['subject_id'] ?? 0) !== $peerId) {
                continue;
            }

            if ($lastChatSendId === null || (int) $log->id > $lastChatSendId) {
                return false;
            }
        }

        return true;
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
