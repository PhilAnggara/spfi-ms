<?php

use App\Support\PermissionModuleGroups;

it('parses crud permission names into matrix columns', function () {
    expect(PermissionModuleGroups::parse('view-uom'))->toMatchArray([
        'column' => 'view',
        'resource' => 'uom',
        'verb' => 'view',
    ]);

    expect(PermissionModuleGroups::parse('create-employees')['column'])->toBe('create');
    expect(PermissionModuleGroups::parse('update-rr')['column'])->toBe('update');
    expect(PermissionModuleGroups::parse('delete-roles')['resource'])->toBe('roles');
});

it('places special actions in the other column', function () {
    expect(PermissionModuleGroups::parse('view-all-prs'))->toMatchArray([
        'column' => 'other',
        'resource' => 'prs',
        'verb' => 'view all',
    ]);

    expect(PermissionModuleGroups::parse('update-department-prs')['column'])->toBe('other');
    expect(PermissionModuleGroups::parse('update-all-prs')['resource'])->toBe('prs');
    expect(PermissionModuleGroups::parse('delete-all-stores-withdrawal')['column'])->toBe('other');
    expect(PermissionModuleGroups::parse('update-all-po')['resource'])->toBe('po');
    expect(PermissionModuleGroups::parse('assign-canvasser')['column'])->toBe('other');
    expect(PermissionModuleGroups::parse('force-logout-users')['resource'])->toBe('active-sessions');
    expect(PermissionModuleGroups::parse('assign-user-access')['resource'])->toBe('users');
    expect(PermissionModuleGroups::parse('select-supplier-comparison')['column'])->toBe('other');
    expect(PermissionModuleGroups::parse('approve-po')['resource'])->toBe('po');
});

it('groups elevated prs and stores withdrawal permissions into modules', function () {
    expect(PermissionModuleGroups::resolveGroup('update-department-prs'))->toBe('prs');
    expect(PermissionModuleGroups::resolveGroup('delete-all-prs'))->toBe('prs');
    expect(PermissionModuleGroups::resolveGroup('view-all-stores-withdrawal'))->toBe('stores_withdrawal');
    expect(PermissionModuleGroups::resolveGroup('update-all-stores-withdrawal'))->toBe('stores_withdrawal');
    expect(PermissionModuleGroups::resolveGroup('delete-rr'))->toBe('rr');
    expect(PermissionModuleGroups::resolveGroup('update-all-po'))->toBe('po');
});
