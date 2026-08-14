{{--
    Expected:
    - $permissionMatrix from PermissionModuleGroups::matrix()
    - $selected list<string>
    - $idPrefix string
    - $permissionsLocked bool
    - $actorIsAdmin bool
    - optional $viaRolePermissionNames list<string>
    - optional $viaRoleSources array<string, list<string>>
--}}
@php
    $selected = $selected ?? [];
    $idPrefix = $idPrefix ?? 'perm';
    $permissionsLocked = $permissionsLocked ?? false;
    $actorIsAdmin = $actorIsAdmin ?? false;
    $viaRolePermissionNames = $viaRolePermissionNames ?? [];
    $viaRoleSources = $viaRoleSources ?? [];
    $searchId = $idPrefix.'-search';
@endphp

@include('includes.partials.rbac-permission-matrix-styles')

<div class="rbac-perm-toolbar">
    <div class="rbac-perm-search">
        <i class="fa-light fa-magnifying-glass" aria-hidden="true"></i>
        <input type="search"
               id="{{ $searchId }}"
               class="form-control"
               placeholder="Search permissions"
               autocomplete="off"
               data-rbac-perm-search="1">
    </div>
    <div class="rbac-perm-legend text-muted">
        <span><span class="rbac-perm-via-dot is-legend"></span> Via role</span>
        <span class="rbac-perm-legend-sep">·</span>
        <span>Empty cells are unavailable</span>
    </div>
</div>

<div class="rbac-perm-empty d-none" data-rbac-perm-empty="1">
    <i class="fa-light fa-filter-slash"></i>
    <p class="mb-0">No permissions match your search.</p>
</div>

@forelse ($permissionMatrix as $group)
    @php $rowCount = count($group['rows']); @endphp
    <section class="rbac-perm-group" data-rbac-perm-group="{{ $group['key'] }}">
        <header class="rbac-perm-group-head">
            <h6 class="rbac-perm-group-title">{{ $group['label'] }}</h6>
            <span class="rbac-perm-group-count">{{ $rowCount }}</span>
        </header>
        <div class="rbac-perm-table-wrap">
            <table class="rbac-perm-matrix">
                <thead>
                    <tr>
                        <th class="rbac-perm-col-resource">Resource</th>
                        <th class="rbac-perm-col-crud">View</th>
                        <th class="rbac-perm-col-crud">Create</th>
                        <th class="rbac-perm-col-crud">Update</th>
                        <th class="rbac-perm-col-crud">Delete</th>
                        <th class="rbac-perm-col-other">Other</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($group['rows'] as $row)
                        @php
                            $searchParts = [$group['label'], $row['label'], $row['resource']];
                            foreach (['view', 'create', 'update', 'delete'] as $verb) {
                                if ($row[$verb]) {
                                    $searchParts[] = $row[$verb]->name;
                                }
                            }
                            foreach ($row['other'] as $otherPermission) {
                                $searchParts[] = $otherPermission->name;
                                $searchParts[] = \App\Support\PermissionModuleGroups::actionLabel($otherPermission->name, $row['resource']);
                            }
                        @endphp
                        <tr data-rbac-perm-row="1" data-rbac-search="{{ implode(' ', $searchParts) }}">
                            <td class="rbac-perm-col-resource">
                                <span class="rbac-perm-resource-name">{{ $row['label'] }}</span>
                            </td>
                            @foreach (['view', 'create', 'update', 'delete'] as $verb)
                                <td class="rbac-perm-col-crud">
                                    @if ($row[$verb])
                                        @include('includes.partials.rbac-permission-checkbox', [
                                            'permission' => $row[$verb],
                                            'showName' => false,
                                        ])
                                    @else
                                        <span class="rbac-perm-na" aria-hidden="true">·</span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="rbac-perm-col-other">
                                @if ($row['other'] === [])
                                    <span class="rbac-perm-na" aria-hidden="true">·</span>
                                @else
                                    <div class="rbac-perm-other-list">
                                        @foreach ($row['other'] as $otherPermission)
                                            @include('includes.partials.rbac-permission-checkbox', [
                                                'permission' => $otherPermission,
                                                'showName' => true,
                                                'labelText' => \App\Support\PermissionModuleGroups::actionLabel($otherPermission->name, $row['resource']),
                                            ])
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@empty
    <p class="text-muted mb-0">No permissions available.</p>
@endforelse

@once
    @push('addon-script')
    <script>
        (function () {
            const input = document.querySelector('[data-rbac-perm-search]');
            if (!input) {
                return;
            }

            const emptyState = document.querySelector('[data-rbac-perm-empty]');

            input.addEventListener('input', function () {
                const query = this.value.toLowerCase().trim();
                let anyVisible = 0;

                document.querySelectorAll('[data-rbac-perm-group]').forEach(function (group) {
                    let visible = 0;

                    group.querySelectorAll('[data-rbac-perm-row]').forEach(function (row) {
                        const haystack = (row.getAttribute('data-rbac-search') || '').toLowerCase();
                        const show = query === '' || haystack.indexOf(query) !== -1;
                        row.classList.toggle('d-none', !show);
                        if (show) {
                            visible += 1;
                        }
                    });

                    group.classList.toggle('d-none', visible === 0);
                    anyVisible += visible;
                });

                if (emptyState) {
                    emptyState.classList.toggle('d-none', anyVisible > 0 || query === '');
                }
            });
        })();
    </script>
    @endpush
@endonce
