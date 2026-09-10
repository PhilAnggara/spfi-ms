<?php

namespace App\Http\Middleware;

use App\Models\Conversation;
use App\Models\Item;
use App\Models\User;
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
        'print',
        'datatables',
        'report',
        'reports',
    ];

    /**
     * Attribute names checked (in order) when building a human-readable subject code.
     *
     * @var list<string>
     */
    private const SUBJECT_CODE_ATTRIBUTES = [
        'code',
        'item_code',
        'product_code',
        'ts_number',
        'po_number',
        'prs_number',
        'sws_number',
        'rr_number',
        'sa_number',
        'dr_number',
        'obc_number',
        'doc_number',
        'name',
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
        $subject = $this->resolveSubject($request, $user);

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
        $subject = $this->formatPersonSubject($peerId, $peer?->name);
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

    /**
     * @return array{
     *     subject?: string,
     *     subject_type?: string,
     *     subject_id?: int|string,
     *     subject_code?: string
     * }
     */
    private function resolveSubject(Request $request, User $actor): array
    {
        $routeName = (string) $request->route()?->getName();

        if (in_array($routeName, [
            'chat.direct-messages.store',
            'chat.conversations.store',
            'chat.typing',
        ], true)) {
            $peerId = (int) $request->input('user_id');

            if ($peerId > 0) {
                $peer = User::query()->find($peerId);

                return [
                    'subject' => $this->formatPersonSubject($peerId, $peer?->name),
                    'subject_type' => 'user',
                    'subject_id' => $peerId,
                    'subject_code' => $peer?->name,
                ];
            }
        }

        if ($routeName === 'chat.messages.store') {
            $conversation = $request->route('conversation');

            if ($conversation instanceof Conversation) {
                $peer = $conversation->otherParticipant($actor);

                if ($peer !== null) {
                    return [
                        'subject' => $this->formatPersonSubject($peer->id, $peer->name),
                        'subject_type' => 'user',
                        'subject_id' => $peer->id,
                        'subject_code' => $peer->name,
                    ];
                }

                return $this->subjectFromModel($conversation, 'conversation');
            }
        }

        $parameters = $request->route()?->parameters() ?? [];

        foreach ($parameters as $name => $value) {
            if ($value instanceof Model) {
                return $this->subjectFromModel($value, (string) $name);
            }
        }

        foreach ($parameters as $name => $value) {
            if (is_object($value) || $value === null || $value === '') {
                continue;
            }

            if (! is_numeric($value) && ! (is_string($value) && ctype_digit($value))) {
                continue;
            }

            $key = is_numeric($value) ? $value + 0 : (int) $value;
            $model = $this->resolveModelForParameter((string) $name, $key, $routeName);

            if ($model instanceof Model) {
                return $this->subjectFromModel($model, (string) $name);
            }

            return [
                'subject' => '#'.$key,
                'subject_type' => (string) $name,
                'subject_id' => $key,
            ];
        }

        return [];
    }

    private function resolveModelForParameter(string $name, int|string $key, string $routeName): ?Model
    {
        $normalized = str_replace('-', '_', strtolower($name));

        $map = [
            'product' => Item::class,
            'item' => Item::class,
            'currency' => \App\Models\Currency::class,
            'purchase_order' => \App\Models\PurchaseOrder::class,
            'purchaseorder' => \App\Models\PurchaseOrder::class,
            'transfer_slip' => \App\Models\TransferSlip::class,
            'transferslip' => \App\Models\TransferSlip::class,
            'prs' => \App\Models\Prs::class,
            'supplier' => \App\Models\Supplier::class,
            'buyer' => \App\Models\Buyer::class,
            'receiving_report' => \App\Models\ReceivingReport::class,
            'delivery' => \App\Models\Delivery::class,
            'screen_message' => \App\Models\ScreenMessage::class,
            'screenmessage' => \App\Models\ScreenMessage::class,
        ];

        if (str_starts_with($routeName, 'product.')) {
            return Item::query()->find($key);
        }

        if (isset($map[$normalized])) {
            return $map[$normalized]::query()->find($key);
        }

        $compact = str_replace('_', '', $normalized);
        if (isset($map[$compact])) {
            return $map[$compact]::query()->find($key);
        }

        $guesses = [
            'App\\Models\\'.\Illuminate\Support\Str::studly($normalized),
            'App\\Models\\'.\Illuminate\Support\Str::studly(\Illuminate\Support\Str::singular($normalized)),
        ];

        foreach ($guesses as $class) {
            if (class_exists($class) && is_subclass_of($class, Model::class)) {
                return $class::query()->find($key);
            }
        }

        return null;
    }

    /**
     * @return array{
     *     subject: string,
     *     subject_type: string,
     *     subject_id: int|string,
     *     subject_code?: string
     * }
     */
    private function subjectFromModel(Model $model, string $type): array
    {
        $key = $model->getKey();
        $code = $this->extractSubjectCode($model);

        if ($model instanceof User) {
            return [
                'subject' => $this->formatPersonSubject((int) $key, $model->name),
                'subject_type' => $type,
                'subject_id' => $key,
                'subject_code' => $model->name,
            ];
        }

        return array_filter([
            'subject' => $code !== null && $code !== ''
                ? '#'.$key.' ('.$code.')'
                : '#'.$key,
            'subject_type' => $type,
            'subject_id' => $key,
            'subject_code' => $code,
        ], static fn ($value) => $value !== null && $value !== '');
    }

    private function extractSubjectCode(Model $model): ?string
    {
        foreach (self::SUBJECT_CODE_ATTRIBUTES as $attribute) {
            if ($attribute === 'name' && ! $model instanceof User) {
                continue;
            }

            $value = $model->getAttribute($attribute);

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    private function formatPersonSubject(int $id, ?string $name): string
    {
        $name = trim((string) $name);

        return $name !== '' ? '#'.$id.' '.$name : '#'.$id;
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
