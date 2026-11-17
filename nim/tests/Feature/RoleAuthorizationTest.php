<?php

use App\Models\User;
use Database\Seeders\AccessControlSeeder;

beforeEach(function () {
    $this->seed(AccessControlSeeder::class);
});

test('editors can perform membership operations but cannot manage privileged resources', function () {
    $editor = User::factory()->create();
    $editor->assignRole('Editor');

    expect($editor->can('dashboard.view'))->toBeTrue()
        ->and($editor->can('membership.register.process'))->toBeTrue()
        ->and($editor->can('membership.upgrades.process'))->toBeTrue()
        ->and($editor->can('users.manage'))->toBeFalse()
        ->and($editor->can('settings.manage'))->toBeFalse()
        ->and($editor->can('audit-logs.view'))->toBeFalse();
});

test('super admins can perform all actions', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('Super Admin');

    expect($superAdmin->can('users.manage'))->toBeTrue()
        ->and($superAdmin->can('future.administration.action'))->toBeTrue();
});

test('users without a role have no application permissions', function () {
    $user = User::factory()->create();

    expect($user->can('dashboard.view'))->toBeFalse()
        ->and($user->can('membership.register.view'))->toBeFalse();
});
