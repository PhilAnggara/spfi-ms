@extends('layouts.app')
@section('title', ' | New Screen Message')

@section('content')
@php
    $oldMode = old('display_mode', 'user_closable');
    $oldAudience = old('audience_type', 'users');
    $oldTargets = collect(old('target_ids', []))->map(fn ($id) => (int) $id);
@endphp
<div class="page-heading po-page sc-page prs-create-page" id="screen-message-create-page">
    <div class="page-title mb-4">
        <div class="row g-3 align-items-center">
            <div class="col-12 col-lg-7">
                <div class="po-hero">
                    <h3 class="mb-1">New Screen Message</h3>
                    <p class="text-muted mb-0">Compose an overlay that appears on recipients’ screens in real time.</p>
                </div>
            </div>
            <div class="col-12 col-lg-5">
                <div class="prs-create-actions">
                    <a href="{{ route('screen-messages.index') }}" class="btn btn-light-secondary icon icon-left">
                        <i class="fa-light fa-arrow-left"></i>
                        Back to List
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="sc-callout mb-3">
        <div class="fw-semibold">Tips</div>
        <ul>
            <li>Online users get the overlay immediately when broadcasting is available</li>
            <li>Offline users still receive it the next time they open the app</li>
            <li>Permanent mode needs a special permission and stays until you deactivate it</li>
        </ul>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger shadow-sm border-0 mb-3">
            <div class="fw-semibold mb-1">Message could not be sent.</div>
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('screen-messages.store') }}" id="screen-message-form" class="prs-create-form">
        @csrf

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-transparent">
                <span class="sc-section-title"><i class="fa-regular fa-pen-to-square"></i> Content</span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label for="title" class="form-label">Title</label>
                        <input type="text" name="title" id="title" class="form-control @error('title') is-invalid @enderror"
                               value="{{ old('title') }}" maxlength="255" required placeholder="Short headline shown on the overlay">
                        @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-12">
                        <label for="body" class="form-label">Message</label>
                        <textarea name="body" id="body" rows="5" class="form-control @error('body') is-invalid @enderror"
                                  maxlength="5000" required placeholder="Write the message recipients will see…">{{ old('body') }}</textarea>
                        @error('body') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" value="1" name="allow_reply" id="allow_reply"
                                @checked(old('allow_reply'))>
                            <label class="form-check-label" for="allow_reply">Allow recipients to reply</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-transparent">
                <span class="sc-section-title"><i class="fa-regular fa-display"></i> Display mode</span>
            </div>
            <div class="card-body">
                <input type="hidden" name="display_mode" id="display_mode" value="{{ $oldMode }}">
                <div class="sws-withdrawal-mode-switch sm-option-grid mb-3" role="group" aria-label="Display mode">
                    @foreach ($displayModes as $mode)
                        @php
                            $disabled = $mode->isPermanent() && ! $canCreatePermanent;
                            $desc = match ($mode) {
                                \App\Enums\ScreenMessageDisplayMode::AutoOnly => 'Timer closes the overlay. Recipients cannot dismiss early.',
                                \App\Enums\ScreenMessageDisplayMode::UserClosable => 'Recipients can close it. Optional timer still works.',
                                \App\Enums\ScreenMessageDisplayMode::Permanent => 'Stays until you deactivate it, even after refresh.',
                            };
                            $icon = match ($mode) {
                                \App\Enums\ScreenMessageDisplayMode::AutoOnly => 'fa-timer',
                                \App\Enums\ScreenMessageDisplayMode::UserClosable => 'fa-hand-pointer',
                                \App\Enums\ScreenMessageDisplayMode::Permanent => 'fa-lock',
                            };
                        @endphp
                        <button type="button"
                            class="sws-mode-option {{ $oldMode === $mode->value ? 'active' : '' }} {{ $disabled ? 'is-disabled' : '' }}"
                            data-display-mode="{{ $mode->value }}"
                            @disabled($disabled)
                            aria-pressed="{{ $oldMode === $mode->value ? 'true' : 'false' }}">
                            <span class="sws-mode-option-icon">
                                <i class="fa-light {{ $icon }}"></i>
                            </span>
                            <span class="sws-mode-option-body">
                                <span class="sws-mode-option-title">
                                    {{ $mode->label() }}
                                    @if ($disabled)
                                        <span class="badge bg-light-secondary text-secondary ms-1">No permission</span>
                                    @endif
                                </span>
                                <span class="sws-mode-option-desc">{{ $desc }}</span>
                            </span>
                        </button>
                    @endforeach
                </div>
                @error('display_mode') <div class="text-danger small">{{ $message }}</div> @enderror

                <div class="row g-3" id="duration-field">
                    <div class="col-md-4">
                        <label for="duration_seconds" class="form-label">Duration (seconds)</label>
                        <input type="number" name="duration_seconds" id="duration_seconds"
                               class="form-control @error('duration_seconds') is-invalid @enderror"
                               value="{{ old('duration_seconds', 30) }}" min="1" max="3600">
                        @error('duration_seconds') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="form-text" id="display-mode-help"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-transparent">
                <span class="sc-section-title"><i class="fa-regular fa-users"></i> Audience</span>
            </div>
            <div class="card-body">
                <input type="hidden" name="audience_type" id="audience_type" value="{{ $oldAudience }}">
                <div class="sws-withdrawal-mode-switch sm-option-grid mb-3" role="group" aria-label="Audience">
                    @foreach ($audienceTypes as $type)
                        @php
                            $disabled = $type->value === 'all' && ! $canCreateAll;
                            $desc = match ($type) {
                                \App\Enums\ScreenMessageAudienceType::All => 'Every active user in the system (except you).',
                                \App\Enums\ScreenMessageAudienceType::Users => 'Pick one or more people with search.',
                                \App\Enums\ScreenMessageAudienceType::Departments => 'Everyone in the selected department(s).',
                            };
                            $icon = match ($type) {
                                \App\Enums\ScreenMessageAudienceType::All => 'fa-globe',
                                \App\Enums\ScreenMessageAudienceType::Users => 'fa-user',
                                \App\Enums\ScreenMessageAudienceType::Departments => 'fa-building',
                            };
                        @endphp
                        <button type="button"
                            class="sws-mode-option {{ $oldAudience === $type->value ? 'active' : '' }} {{ $disabled ? 'is-disabled' : '' }}"
                            data-audience-type="{{ $type->value }}"
                            @disabled($disabled)
                            aria-pressed="{{ $oldAudience === $type->value ? 'true' : 'false' }}">
                            <span class="sws-mode-option-icon">
                                <i class="fa-light {{ $icon }}"></i>
                            </span>
                            <span class="sws-mode-option-body">
                                <span class="sws-mode-option-title">
                                    {{ $type->label() }}
                                    @if ($disabled)
                                        <span class="badge bg-light-secondary text-secondary ms-1">No permission</span>
                                    @endif
                                </span>
                                <span class="sws-mode-option-desc">{{ $desc }}</span>
                            </span>
                        </button>
                    @endforeach
                </div>
                @error('audience_type') <div class="text-danger small mb-2">{{ $message }}</div> @enderror

                <div id="targets-users" style="display:none;">
                    <label for="target_users" class="form-label">Select users</label>
                    <select name="target_ids[]" id="target_users"
                            class="form-select choices multiple-remove @error('target_ids') is-invalid @enderror"
                            multiple>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected($oldTargets->contains($user->id))>
                                {{ $user->name }}
                                @if ($user->username) · {{ $user->username }} @endif
                                @if ($user->department?->name) · {{ $user->department->alias ?: $user->department->name }} @endif
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text">Search by name or username, then pick multiple people.</div>
                    @error('target_ids') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </div>

                <div id="targets-departments" style="display:none;">
                    <label for="target_departments" class="form-label">Select departments</label>
                    <select name="target_ids[]" id="target_departments"
                            class="form-select choices multiple-remove"
                            multiple>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected($oldTargets->contains($department->id))>
                                {{ $department->name }}
                                ({{ $department->code }})
                                · {{ $department->users_count }} users
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text">Search and select one or more departments.</div>
                </div>

                <div id="targets-all" class="alert alert-info border-0 mb-0" style="display:none;">
                    This message will be sent to all active users except you.
                </div>
            </div>
        </div>

        <div class="sc-sticky-footer d-flex flex-wrap gap-3 align-items-center justify-content-between">
            <div class="text-muted small">Review recipients carefully before sending.</div>
            <div class="d-flex gap-2">
                <a href="{{ route('screen-messages.index') }}" class="btn btn-light-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary icon icon-left">
                    <i class="fa-duotone fa-solid fa-paper-plane"></i>
                    Send Message
                </button>
            </div>
        </div>
    </form>
</div>
@endsection

@push('addon-style')
    <link rel="stylesheet" href="{{ url('assets/css/purchase-orders-modern.css') }}">
    <link rel="stylesheet" href="{{ url('assets/css/stock-correction-modern.css') }}">
    <link rel="stylesheet" href="{{ url('assets/css/prs-modern.css') }}">
    <link rel="stylesheet" href="{{ url('assets/extensions/choices.js/public/assets/styles/choices.css') }}">
    <style>
        .sm-option-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 0.75rem;
        }
        .sm-option-grid .sws-mode-option {
            width: 100%;
            text-align: left;
        }
        .sm-option-grid .sws-mode-option.is-disabled,
        .sm-option-grid .sws-mode-option:disabled {
            opacity: 0.55;
            cursor: not-allowed;
        }
        #screen-message-create-page .choices {
            margin-bottom: 0;
        }
    </style>
@endpush

@push('addon-script')
<script src="{{ url('assets/extensions/choices.js/public/assets/scripts/choices.js') }}"></script>
<script>
(function () {
    const modeInput = document.getElementById('display_mode');
    const audienceInput = document.getElementById('audience_type');
    const durationField = document.getElementById('duration-field');
    const durationInput = document.getElementById('duration_seconds');
    const help = document.getElementById('display-mode-help');
    const usersBlock = document.getElementById('targets-users');
    const deptsBlock = document.getElementById('targets-departments');
    const allBlock = document.getElementById('targets-all');
    const usersSelect = document.getElementById('target_users');
    const deptsSelect = document.getElementById('target_departments');

    function initChoices(select) {
        if (!select || typeof Choices === 'undefined') {
            return null;
        }

        if (select.choicesInstance) {
            return select.choicesInstance;
        }

        const instance = new Choices(select, {
            removeItemButton: true,
            searchEnabled: true,
            searchPlaceholderValue: 'Type to search…',
            placeholder: true,
            placeholderValue: 'Select…',
            shouldSort: false,
            itemSelectText: '',
        });
        select.choicesInstance = instance;
        return instance;
    }

    const userChoices = initChoices(usersSelect);
    const deptChoices = initChoices(deptsSelect);

    function setActiveButtons(selector, attr, value) {
        document.querySelectorAll(selector).forEach((btn) => {
            const active = btn.getAttribute(attr) === value;
            btn.classList.toggle('active', active);
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    }

    function syncMode() {
        const mode = modeInput.value;
        const needsDuration = mode === 'auto_only' || mode === 'user_closable';
        durationField.style.display = needsDuration ? '' : 'none';
        durationInput.required = mode === 'auto_only';
        help.textContent = {
            auto_only: 'Required. Overlay auto-closes when the timer ends.',
            user_closable: 'Optional. Leave blank if recipients should only close manually.',
            permanent: 'No timer. Remains until deactivated.',
        }[mode] || '';
        setActiveButtons('[data-display-mode]', 'data-display-mode', mode);
    }

    function syncAudience() {
        const type = audienceInput.value;
        usersBlock.style.display = type === 'users' ? '' : 'none';
        deptsBlock.style.display = type === 'departments' ? '' : 'none';
        allBlock.style.display = type === 'all' ? '' : 'none';

        if (type === 'users') {
            usersSelect.disabled = false;
            usersSelect.name = 'target_ids[]';
            deptsSelect.disabled = true;
            deptsSelect.name = '';
            if (deptChoices) {
                deptChoices.disable();
            }
            if (userChoices) {
                userChoices.enable();
            }
        } else if (type === 'departments') {
            deptsSelect.disabled = false;
            deptsSelect.name = 'target_ids[]';
            usersSelect.disabled = true;
            usersSelect.name = '';
            if (userChoices) {
                userChoices.disable();
            }
            if (deptChoices) {
                deptChoices.enable();
            }
        } else {
            usersSelect.disabled = true;
            deptsSelect.disabled = true;
            usersSelect.name = '';
            deptsSelect.name = '';
            if (userChoices) {
                userChoices.disable();
            }
            if (deptChoices) {
                deptChoices.disable();
            }
        }

        setActiveButtons('[data-audience-type]', 'data-audience-type', type);
    }

    document.querySelectorAll('[data-display-mode]').forEach((btn) => {
        btn.addEventListener('click', () => {
            if (btn.disabled) {
                return;
            }
            modeInput.value = btn.getAttribute('data-display-mode');
            syncMode();
        });
    });

    document.querySelectorAll('[data-audience-type]').forEach((btn) => {
        btn.addEventListener('click', () => {
            if (btn.disabled) {
                return;
            }
            audienceInput.value = btn.getAttribute('data-audience-type');
            syncAudience();
        });
    });

    syncMode();
    syncAudience();
})();
</script>
@endpush
