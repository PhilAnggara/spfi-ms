<?php

use App\Models\Department;
use App\Models\User;

it('returns null when manager and override are missing', function () {
    expect(resolve_print_signer(null, '7046', []))->toBeNull();
});

it('returns manager name and title when no override exists', function () {
    $department = new Department(['name' => 'Engineering', 'alias' => 'ENG', 'code' => '7046']);
    $manager = new User(['name' => 'Dept Manager', 'role' => 'Manager']);
    $manager->setRelation('department', $department);

    $signer = resolve_print_signer($manager, '7046', []);

    expect($signer)->not->toBeNull()
        ->and($signer->name)->toBe('Dept Manager')
        ->and($signer->title)->toBe(get_job_title($manager));
});

it('uses fallback override only when manager is missing', function () {
    $overrides = [
        '7046' => [
            'name' => 'Config Supervisor',
            'title' => 'Engineering Supervisor',
            'priority' => 'fallback',
        ],
    ];

    $fallbackSigner = resolve_print_signer(null, '7046', $overrides);

    expect($fallbackSigner)->not->toBeNull()
        ->and($fallbackSigner->name)->toBe('Config Supervisor')
        ->and($fallbackSigner->title)->toBe('Engineering Supervisor');

    $department = new Department(['name' => 'Engineering', 'alias' => 'ENG', 'code' => '7046']);
    $manager = new User(['name' => 'Dept Manager', 'role' => 'Manager']);
    $manager->setRelation('department', $department);

    $managerSigner = resolve_print_signer($manager, '7046', $overrides);

    expect($managerSigner)->not->toBeNull()
        ->and($managerSigner->name)->toBe('Dept Manager')
        ->and($managerSigner->title)->toBe(get_job_title($manager));
});

it('uses override priority even when manager exists', function () {
    $department = new Department(['name' => 'Engineering', 'alias' => 'ENG', 'code' => '7046']);
    $manager = new User(['name' => 'Dept Manager', 'role' => 'Manager']);
    $manager->setRelation('department', $department);

    $signer = resolve_print_signer($manager, '7046', [
        '7046' => [
            'name' => 'Forced Approver',
            'title' => 'Acting Manager',
            'priority' => 'override',
        ],
    ]);

    expect($signer)->not->toBeNull()
        ->and($signer->name)->toBe('Forced Approver')
        ->and($signer->title)->toBe('Acting Manager');
});

it('matches overrides by exact department code only', function () {
    $signer = resolve_print_signer(null, '7033C', [
        '7033' => [
            'name' => 'Prefix Only',
            'title' => 'Wrong Match',
            'priority' => 'fallback',
        ],
    ]);

    expect($signer)->toBeNull();
});
