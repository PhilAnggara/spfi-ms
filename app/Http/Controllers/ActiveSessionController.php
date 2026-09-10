<?php

namespace App\Http\Controllers;

use App\Models\Session;
use App\Models\User;
use App\Models\UserActivityLog;
use App\Services\UserActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ActiveSessionController extends Controller
{
    private const ACTIVITY_PAGE_SIZE = 50;

    public function __construct(private UserActivityLogger $activityLogger) {}

    public function index(Request $request): View
    {
        $onlineThreshold = now()->timestamp - Session::ONLINE_THRESHOLD_SECONDS;

        $onlineUserIds = Session::query()
            ->whereNotNull('user_id')
            ->where('last_activity', '>=', $onlineThreshold)
            ->pluck('user_id')
            ->unique();

        $activeSessionUserIds = Session::query()
            ->active()
            ->pluck('user_id')
            ->unique();

        $users = User::query()
            ->with(['department', 'latestActivityLog'])
            ->orderByRaw('case when last_seen_at is null then 1 else 0 end')
            ->orderByDesc('last_seen_at')
            ->orderBy('name')
            ->get();

        $users->each(function (User $user) use ($onlineUserIds, $activeSessionUserIds): void {
            $user->setAttribute('is_online', $onlineUserIds->contains($user->id));
            $user->setAttribute('has_active_session', $activeSessionUserIds->contains($user->id));
        });

        $onlineCount = $users->where('is_online', true)->count();
        $totalCount = $users->count();

        $data = [
            'users' => $users,
            'onlineCount' => $onlineCount,
            'offlineCount' => $totalCount - $onlineCount,
            'totalCount' => $totalCount,
        ];

        if ($request->ajax()) {
            return view('pages.partials.active-session-list', $data);
        }

        return view('pages.active-sessions', $data);
    }

    public function show(Request $request, User $user): View
    {
        $beforeId = $request->integer('before_id') ?: null;
        $actionFilter = $this->normalizeActionFilter($request->string('action')->toString());

        if ($request->ajax() && $beforeId !== null) {
            $logs = $this->activityLogsPage($user, $beforeId, $actionFilter);
            $hasMore = $this->hasOlderActivityLogs($user, $logs, $actionFilter);

            return view('pages.partials.active-session-detail-logs', [
                'logs' => $logs,
                'hasMore' => $hasMore,
                'oldestId' => $logs->last()?->id,
                'appendOnly' => true,
            ]);
        }

        $user->load('department');

        $onlineThreshold = now()->timestamp - Session::ONLINE_THRESHOLD_SECONDS;
        $isOnline = Session::query()
            ->where('user_id', $user->id)
            ->where('last_activity', '>=', $onlineThreshold)
            ->exists();

        $logs = $this->activityLogsPage($user, null, $actionFilter);
        $hasMore = $this->hasOlderActivityLogs($user, $logs, $actionFilter);
        $availableActions = $this->availableActionsFor($user);

        $detailUrl = route('active-sessions.show', $user);
        if ($actionFilter !== null) {
            $detailUrl .= '?action='.urlencode($actionFilter);
        }

        return view('pages.partials.active-session-detail', [
            'user' => $user,
            'isOnline' => $isOnline,
            'logs' => $logs,
            'hasMore' => $hasMore,
            'oldestId' => $logs->last()?->id,
            'detailUrl' => $detailUrl,
            'actionFilter' => $actionFilter,
            'availableActions' => $availableActions,
        ]);
    }

    public function destroySessions(Request $request, User $user): RedirectResponse
    {
        if ($request->user()?->id === $user->id) {
            toast('You cannot force logout your own session.', 'error');

            return back();
        }

        Session::query()->where('user_id', $user->id)->delete();

        $this->activityLogger->log(
            $user,
            UserActivityLog::ACTION_FORCE_LOGOUT,
            $request,
            $request->user(),
            ['message' => 'All sessions terminated by administrator'],
        );

        toast("Force logged out {$user->name}.");

        return back();
    }

    public function resetActivityLogs(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('reset-activity-logs'), 403);

        $request->validate([
            'reset_password' => ['required', 'string'],
        ], [
            'reset_password.required' => 'Reset password is required.',
        ]);

        if (! hash_equals((string) config('active-sessions.reset_password'), (string) $request->input('reset_password'))) {
            toast('Incorrect reset password.', 'error');

            return back();
        }

        UserActivityLog::query()->truncate();
        Session::query()->delete();

        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        toast('Activity logs cleared. All users have been logged out.');

        return redirect()->route('login');
    }

    /**
     * @return Collection<int, UserActivityLog>
     */
    private function activityLogsPage(User $user, ?int $beforeId = null, ?string $actionFilter = null): Collection
    {
        $query = $user->activityLogs()
            ->with('actor')
            ->latest('id');

        if ($actionFilter !== null) {
            $query->where('action', $actionFilter);
        }

        if ($beforeId !== null) {
            $query->where('id', '<', $beforeId);
        }

        return $query->limit(self::ACTIVITY_PAGE_SIZE)->get();
    }

    /**
     * @param  Collection<int, UserActivityLog>  $logs
     */
    private function hasOlderActivityLogs(User $user, Collection $logs, ?string $actionFilter = null): bool
    {
        $oldestId = $logs->last()?->id;

        if ($oldestId === null) {
            return false;
        }

        $query = $user->activityLogs()->where('id', '<', $oldestId);

        if ($actionFilter !== null) {
            $query->where('action', $actionFilter);
        }

        return $query->exists();
    }

    private function normalizeActionFilter(string $action): ?string
    {
        $action = trim($action);

        if ($action === '' || $action === 'all') {
            return null;
        }

        return $action;
    }

    /**
     * @return Collection<int, string>
     */
    private function availableActionsFor(User $user): Collection
    {
        return $user->activityLogs()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->values();
    }
}
