<?php

namespace App\Http\Controllers;

use App\Models\Session;
use App\Models\User;
use App\Models\UserActivityLog;
use App\Services\UserActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActiveSessionController extends Controller
{
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
            ->with('department')
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

    public function show(User $user): View
    {
        $user->load('department');

        $onlineThreshold = now()->timestamp - Session::ONLINE_THRESHOLD_SECONDS;
        $isOnline = Session::query()
            ->where('user_id', $user->id)
            ->where('last_activity', '>=', $onlineThreshold)
            ->exists();

        $logs = $user->activityLogs()
            ->with('actor')
            ->latest()
            ->limit(50)
            ->get();

        return view('pages.partials.active-session-detail', [
            'user' => $user,
            'isOnline' => $isOnline,
            'logs' => $logs,
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
        abort_unless($request->user()?->hasRole('administrator'), 403);

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
}
