@php
    $isViaRole = in_array($permission->name, $viaRolePermissionNames, true);
    $sourceRoles = $viaRoleSources[$permission->name] ?? [];
    $isProtectedPerm = $permission->name === 'reset-activity-logs';
    $permDisabled = $permissionsLocked || ($isProtectedPerm && ! $actorIsAdmin);
    $isChecked = in_array($permission->name, $selected, true);
    $inputId = $idPrefix.'-'.$permission->id;
    $showName = $showName ?? false;
    $labelText = $labelText ?? $permission->name;
    $viaTitle = $sourceRoles !== [] ? implode(', ', $sourceRoles) : 'Granted via role';
@endphp
@if ($permDisabled && $isChecked)
    <input type="hidden" name="permissions[]" value="{{ $permission->name }}">
@endif
@if ($showName)
    <label class="rbac-perm-chip {{ $isViaRole ? 'is-via-role' : '' }} {{ $isChecked ? 'is-checked' : '' }} {{ $permDisabled ? 'is-disabled' : '' }}"
           for="{{ $inputId }}"
           @if ($isViaRole)
               data-bstooltip-toggle="tooltip"
               data-bs-placement="top"
               title="{{ $viaTitle }}"
           @else
               title="{{ $permission->name }}"
           @endif>
        <input class="form-check-input rbac-perm-chip-input" type="checkbox" name="permissions[]"
               value="{{ $permission->name }}" id="{{ $inputId }}"
               @checked($isChecked)
               @disabled($permDisabled)>
        <span class="rbac-perm-chip-label {{ $isViaRole ? 'rbac-perm-via-role-text' : '' }}">{{ $labelText }}</span>
        @if ($isViaRole)
            <span class="rbac-perm-via-dot" aria-hidden="true"></span>
            <span class="visually-hidden">Via role</span>
        @endif
    </label>
@else
    <label class="rbac-perm-cell {{ $isViaRole ? 'is-via-role' : '' }} {{ $permDisabled ? 'is-disabled' : '' }}"
           for="{{ $inputId }}"
           @if ($isViaRole)
               data-bstooltip-toggle="tooltip"
               data-bs-placement="top"
               title="{{ $viaTitle }}"
           @else
               title="{{ $permission->name }}"
           @endif>
        <input class="form-check-input rbac-perm-cell-input" type="checkbox" name="permissions[]"
               value="{{ $permission->name }}" id="{{ $inputId }}"
               @checked($isChecked)
               @disabled($permDisabled)>
        @if ($isViaRole)
            <span class="rbac-perm-via-dot" aria-hidden="true"></span>
            <span class="visually-hidden rbac-perm-via-role-text">Via role</span>
        @endif
        <span class="visually-hidden">{{ $permission->name }}</span>
    </label>
@endif
