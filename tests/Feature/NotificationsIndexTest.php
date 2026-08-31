<?php

use App\Models\Department;
use App\Models\User;
use App\Notifications\ProcessNotification;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->department = Department::query()->create([
        'name' => 'Notifications Dept',
        'code' => '7042-NOTIF',
        'alias' => 'NOTIF',
    ]);

    $this->user = User::query()->create([
        'name' => 'Notification User',
        'username' => 'notif-user',
        'email' => 'notif-user@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);
    $this->user->assignRole('im-staff');
});

it('paginates notifications index and keeps ajax results wrapper', function () {
    for ($i = 1; $i <= 21; $i++) {
        $this->user->notify(new ProcessNotification([
            'title' => 'Notif Page Test '.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
            'message' => 'Pagination test message '.$i,
        ]));
    }

    $firstPage = $this->actingAs($this->user)
        ->get(route('notifications.index'))
        ->assertOk();

    $html = $firstPage->getContent();
    $containerPos = strpos($html, 'id="notifications-page-container"');
    $resultsPos = strpos($html, 'id="notifications-page-results"');

    expect($containerPos)->not->toBeFalse()
        ->and($resultsPos)->not->toBeFalse()
        ->and($containerPos)->toBeLessThan($resultsPos);

    $firstPage
        ->assertSee('Notif Page Test 021')
        ->assertSee('Notif Page Test 002');

    $this->actingAs($this->user)
        ->get(route('notifications.index', ['page' => 2]))
        ->assertOk()
        ->assertSee('Notif Page Test 001');
});
