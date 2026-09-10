<?php

namespace App\Http\Controllers;

use App\Enums\ScreenMessageAudienceType;
use App\Enums\ScreenMessageDisplayMode;
use App\Enums\ScreenMessageTheme;
use App\Http\Requests\StoreScreenMessageRequest;
use App\Models\Department;
use App\Models\ScreenMessage;
use App\Models\User;
use App\Services\ScreenMessageService;
use App\Support\ScreenMessageAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ScreenMessageController extends Controller
{
    public function __construct(private ScreenMessageService $screenMessages) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless(ScreenMessageAccess::canViewAny($user), 403);

        $messages = $this->screenMessages
            ->scopedIndexQuery($user)
            ->withCount([
                'recipients',
                'recipients as seen_recipients_count' => fn ($q) => $q->whereNotNull('seen_at'),
                'replies',
            ])
            ->paginate(20);

        return view('pages.screen-messages.index', [
            'messages' => $messages,
            'canCreate' => ScreenMessageAccess::canCreate($user),
        ]);
    }

    public function create(Request $request): View
    {
        $user = $request->user();
        abort_unless(ScreenMessageAccess::canCreate($user), 403);

        $canCreateAll = ScreenMessageAccess::canCreateForAll($user);

        $usersQuery = User::query()->whereNull('deleted_at')->whereKeyNot($user->id)->orderBy('name');
        $departmentsQuery = Department::query()->where('is_active', true)->orderBy('name');

        if (! $canCreateAll) {
            abort_unless((bool) $user->department_id, 403);
            $usersQuery->where('department_id', $user->department_id);
            $departmentsQuery->whereKey($user->department_id);
        }

        return view('pages.screen-messages.create', [
            'users' => $usersQuery->with('department:id,name,alias')->get(['id', 'name', 'username', 'department_id']),
            'departments' => $departmentsQuery->withCount('users')->get(['id', 'name', 'code', 'alias']),
            'displayModes' => ScreenMessageDisplayMode::cases(),
            'audienceTypes' => ScreenMessageAudienceType::cases(),
            'themes' => ScreenMessageTheme::cases(),
            'canCreateAll' => $canCreateAll,
            'canCreatePermanent' => ScreenMessageAccess::canCreatePermanent($user),
        ]);
    }

    public function store(StoreScreenMessageRequest $request): RedirectResponse
    {
        $message = $this->screenMessages->create($request->user(), $request->validated());

        return redirect()
            ->route('screen-messages.show', $message)
            ->with('success', 'Screen message sent.');
    }

    public function show(Request $request, ScreenMessage $screenMessage): View
    {
        $user = $request->user();
        abort_unless(ScreenMessageAccess::canView($user, $screenMessage), 403);

        $screenMessage->load([
            'user.department',
            'deactivatedBy',
            'targets',
            'recipients.user.department',
            'replies.user',
        ]);

        $canViewReplies = ScreenMessageAccess::canViewReplies($user, $screenMessage);

        return view('pages.screen-messages.show', [
            'message' => $screenMessage,
            'canViewReplies' => $canViewReplies,
            'canDeactivate' => ScreenMessageAccess::canDeactivate($user, $screenMessage),
            'canDelete' => ScreenMessageAccess::canDelete($user, $screenMessage),
            'seenCount' => $screenMessage->recipients->whereNotNull('seen_at')->count(),
            'recipientCount' => $screenMessage->recipients->count(),
        ]);
    }

    public function live(Request $request, ScreenMessage $screenMessage): JsonResponse
    {
        $user = $request->user();
        abort_unless(ScreenMessageAccess::canView($user, $screenMessage), 403);

        return response()->json(
            $this->screenMessages->livePayload($screenMessage, $user)
        );
    }

    public function deactivate(Request $request, ScreenMessage $screenMessage): RedirectResponse
    {
        abort_unless(ScreenMessageAccess::canDeactivate($request->user(), $screenMessage), 403);

        $this->screenMessages->deactivate($screenMessage, $request->user());

        return redirect()
            ->route('screen-messages.show', $screenMessage)
            ->with('success', 'Screen message deactivated.');
    }

    public function destroy(Request $request, ScreenMessage $screenMessage): RedirectResponse
    {
        abort_unless(ScreenMessageAccess::canDelete($request->user(), $screenMessage), 403);

        $this->screenMessages->delete($screenMessage, $request->user());

        return redirect()
            ->route('screen-messages.index')
            ->with('success', 'Screen message deleted.');
    }
}
