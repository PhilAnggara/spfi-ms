<?php

use App\Models\PrintCalibrationProfile;
use App\Models\User;
use App\Services\Print\PrintCalibrationService;
use Database\Seeders\PrintCalibrationProfileSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(PrintCalibrationProfileSeeder::class);

    $this->user = User::query()->create([
        'name' => 'Calibration Admin',
        'username' => 'calibration-admin',
        'email' => 'calibration-admin@example.test',
        'password' => Hash::make('password'),
        'role' => 'Staff',
    ]);

    $this->user->assignRole('administrator');
});

it('resolves zero offset when measured anchor matches design anchor', function () {
    $service = app(PrintCalibrationService::class);
    $anchor = $service->getDesignAnchor(PrintCalibrationProfile::DOCUMENT_TYPE_RR);

    $offsets = $service->resolve(
        PrintCalibrationProfile::DOCUMENT_TYPE_RR,
        null,
        $anchor['x'],
        $anchor['y'],
    );

    expect($offsets['x'])->toBe(0.0)
        ->and($offsets['y'])->toBe(0.0);
});

it('computes positive offset when measured anchor is lower and righter than design', function () {
    $service = app(PrintCalibrationService::class);
    $anchor = $service->getDesignAnchor(PrintCalibrationProfile::DOCUMENT_TYPE_TS);

    $offsets = $service->offsetFromMeasured(
        PrintCalibrationProfile::DOCUMENT_TYPE_TS,
        $anchor['x'] + 2,
        $anchor['y'] + 1.5,
    );

    expect($offsets['x'])->toBe(2.0)
        ->and($offsets['y'])->toBe(1.5);
});

it('applies nudge on top of measured anchor offset', function () {
    $service = app(PrintCalibrationService::class);
    $anchor = $service->getDesignAnchor(PrintCalibrationProfile::DOCUMENT_TYPE_RR);

    $offsets = $service->resolve(
        PrintCalibrationProfile::DOCUMENT_TYPE_RR,
        null,
        $anchor['x'],
        $anchor['y'],
        0.5,
        -1,
    );

    expect($offsets['x'])->toBe(0.5)
        ->and($offsets['y'])->toBe(-1.0);
});

it('uses default profile when no measured values are provided', function () {
    $service = app(PrintCalibrationService::class);
    $anchor = $service->getDesignAnchor(PrintCalibrationProfile::DOCUMENT_TYPE_RR);

    $profile = PrintCalibrationProfile::factory()->rr()->create([
        'name' => 'Shifted RR',
        'measured_anchor_x_mm' => $anchor['x'] + 2,
        'measured_anchor_y_mm' => $anchor['y'] + 2,
        'is_default' => true,
    ]);

    PrintCalibrationProfile::query()
        ->where('document_type', PrintCalibrationProfile::DOCUMENT_TYPE_RR)
        ->where('id', '!=', $profile->id)
        ->update(['is_default' => false]);

    $offsets = $service->resolve(
        PrintCalibrationProfile::DOCUMENT_TYPE_RR,
    );

    expect($offsets['x'])->toBe(2.0)
        ->and($offsets['y'])->toBe(2.0);
});

it('allows administrators to view print calibration index', function () {
    $this->actingAs($this->user)
        ->get(route('print-calibration-profiles.index'))
        ->assertSuccessful()
        ->assertSee('Print Calibration')
        ->assertSee('Default RR');
});

it('allows administrators to create a calibration profile', function () {
    $service = app(PrintCalibrationService::class);
    $anchor = $service->getDesignAnchor(PrintCalibrationProfile::DOCUMENT_TYPE_TS);

    $this->actingAs($this->user)
        ->post(route('print-calibration-profiles.store'), [
            'document_type' => PrintCalibrationProfile::DOCUMENT_TYPE_TS,
            'name' => 'Printer Line 2',
            'measured_anchor_x_mm' => $anchor['x'] + 1,
            'measured_anchor_y_mm' => $anchor['y'] + 0.5,
            'is_default' => false,
            'description' => 'Test profile',
        ])
        ->assertRedirect(route('print-calibration-profiles.index', ['type' => PrintCalibrationProfile::DOCUMENT_TYPE_TS]));

    $this->assertDatabaseHas('print_calibration_profiles', [
        'name' => 'Printer Line 2',
        'document_type' => PrintCalibrationProfile::DOCUMENT_TYPE_TS,
    ]);
});

it('streams a calibration sample preview pdf', function () {
    $service = app(PrintCalibrationService::class);
    $anchor = $service->getDesignAnchor(PrintCalibrationProfile::DOCUMENT_TYPE_RR);

    $response = $this->actingAs($this->user)
        ->get(route('print-calibration-profiles.preview-sample', [
            'document_type' => PrintCalibrationProfile::DOCUMENT_TYPE_RR,
            'measured_anchor_x_mm' => $anchor['x'] + 1,
            'measured_anchor_y_mm' => $anchor['y'],
            'format' => 'pdf',
        ]));

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

it('returns html for live calibration preview', function () {
    $service = app(PrintCalibrationService::class);
    $anchor = $service->getDesignAnchor(PrintCalibrationProfile::DOCUMENT_TYPE_RR);

    $response = $this->actingAs($this->user)
        ->get(route('print-calibration-profiles.preview-sample', [
            'document_type' => PrintCalibrationProfile::DOCUMENT_TYPE_RR,
            'measured_anchor_x_mm' => $anchor['x'],
            'measured_anchor_y_mm' => $anchor['y'],
        ]));

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain('text/html');
});

it('forbids users without permission from managing calibration profiles', function () {
    $viewer = User::query()->create([
        'name' => 'Viewer Only',
        'username' => 'viewer-only',
        'email' => 'viewer-only@example.test',
        'password' => Hash::make('password'),
        'role' => 'Staff',
    ]);

    $this->actingAs($viewer)
        ->get(route('print-calibration-profiles.index'))
        ->assertForbidden();
});
