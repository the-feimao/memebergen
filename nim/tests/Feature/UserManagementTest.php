<?php

use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(AccessControlSeeder::class);
});

function manageUserWithRole(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

test('super admins can manage users while editors and guests cannot', function () {
    $this->get(route('users.index'))->assertRedirect(route('login'));
    $this->post(route('users.store'))->assertRedirect(route('login'));

    $admin = manageUserWithRole('Super Admin');
    $editor = manageUserWithRole('Editor');
    $target = User::factory()->create();

    $this->actingAs($admin)
        ->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('users/index')
            ->where('currentUserId', $admin->id)
            ->where('stats.total', 3)
            ->has('users.data', 3)
            ->etc());

    foreach ([
        ['get', route('users.index')],
        ['post', route('users.store')],
        ['patch', route('users.update', $target)],
        ['post', route('users.password-reset', $target)],
    ] as [$method, $url]) {
        $this->actingAs($editor)
            ->{$method}($url)
            ->assertForbidden();
    }
});

test('super admins can create users with hashed passwords and audited roles', function () {
    $admin = manageUserWithRole('Super Admin');

    $this->actingAs($admin)
        ->post(route('users.store'), [
            'name' => 'Registry Editor',
            'email' => 'EDITOR@example.com',
            'role' => 'Editor',
            'is_active' => true,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
        ->assertRedirect(route('users.index'));

    $created = User::query()->where('email', 'editor@example.com')->sole();

    expect($created->password)->not->toBe('new-password-123')
        ->and(Hash::check('new-password-123', $created->password))->toBeTrue()
        ->and($created->hasRole('Editor'))->toBeTrue();

    $log = AuditLog::query()->where('action', 'user.created')->sole();

    expect($log->actor_id)->toBe($admin->id)
        ->and($log->subject_id)->toBe($created->id)
        ->and($log->metadata['after']['role'])->toBe('Editor')
        ->and($log->metadata['after'])->not->toHaveKey('password')
        ->and(json_encode($log->metadata))->not->toContain('new-password-123');

    $this->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->missing('users.data.0.password')
            ->etc());
});

test('user index can be searched and last login is recorded', function () {
    $admin = manageUserWithRole('Super Admin');
    $target = User::factory()->create(['name' => 'Findable Registrar']);

    $this->post(route('login.store'), [
        'email' => $target->email,
        'password' => 'password',
    ])->assertRedirect();

    expect($target->refresh()->last_login_at)->not->toBeNull();

    $this->actingAs($admin)
        ->get(route('users.index', ['search' => 'Findable']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.search', 'Findable')
            ->has('users.data', 1)
            ->where('users.data.0.id', $target->id)
            ->where('users.data.0.last_login_at', $target->last_login_at?->toISOString())
            ->etc());

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'auth.login',
        'actor_id' => $target->id,
    ]);
});

test('inactive users cannot authenticate', function () {
    $inactive = User::factory()->inactive()->create();

    $this->post(route('login.store'), [
        'email' => $inactive->email,
        'password' => 'password',
    ]);

    $this->assertGuest();

    expect(AuditLog::query()->where('action', 'auth.login')->exists())->toBeFalse()
        ->and(AuditLog::query()->where('action', 'auth.failed')->exists())->toBeTrue();
});

test('a super admin cannot deactivate or demote their own account', function () {
    $admin = manageUserWithRole('Super Admin');

    $this->actingAs($admin)
        ->patch(route('users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => 'Super Admin',
            'is_active' => false,
        ])
        ->assertSessionHasErrors('is_active');

    $this->actingAs($admin)
        ->patch(route('users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => 'Editor',
            'is_active' => true,
        ])
        ->assertSessionHasErrors('role');

    expect($admin->refresh()->is_active)->toBeTrue()
        ->and($admin->hasRole('Super Admin'))->toBeTrue();
});

test('the last active super admin cannot be deactivated or demoted', function () {
    $target = manageUserWithRole('Super Admin');
    $staleAdmin = User::factory()->inactive()->create();
    $staleAdmin->assignRole('Super Admin');

    $payload = [
        'name' => $target->name,
        'email' => $target->email,
        'role' => 'Editor',
        'is_active' => true,
    ];

    $this->actingAs($staleAdmin)
        ->patch(route('users.update', $target), $payload)
        ->assertSessionHasErrors('role');

    $this->actingAs($staleAdmin)
        ->patch(route('users.update', $target), [
            ...$payload,
            'role' => 'Super Admin',
            'is_active' => false,
        ])
        ->assertSessionHasErrors('role');

    expect($target->refresh()->hasRole('Super Admin'))->toBeTrue()
        ->and($target->is_active)->toBeTrue();
});

test('role and status changes are audited with before and after snapshots', function () {
    $admin = manageUserWithRole('Super Admin');
    $target = manageUserWithRole('Editor');

    $this->actingAs($admin)
        ->patch(route('users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'role' => 'Super Admin',
            'is_active' => true,
        ])
        ->assertRedirect(route('users.index'));

    $roleLog = AuditLog::query()->where('action', 'user.role_changed')->sole();

    expect($roleLog->actor_id)->toBe($admin->id)
        ->and($roleLog->subject_id)->toBe($target->id)
        ->and($roleLog->metadata['before']['role'])->toBe('Editor')
        ->and($roleLog->metadata['after']['role'])->toBe('Super Admin')
        ->and(json_encode($roleLog->metadata))->not->toContain($target->password);

    $this->actingAs($admin)
        ->patch(route('users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'role' => 'Super Admin',
            'is_active' => false,
        ])
        ->assertRedirect(route('users.index'));

    $statusLog = AuditLog::query()->where('action', 'user.deactivated')->sole();

    expect($statusLog->metadata['before']['is_active'])->toBeTrue()
        ->and($statusLog->metadata['after']['is_active'])->toBeFalse();
});

test('password reset requests are delivered and audited without secrets', function () {
    Notification::fake();

    $admin = manageUserWithRole('Super Admin');
    $target = User::factory()->create();

    $this->actingAs($admin)
        ->post(route('users.password-reset', $target))
        ->assertRedirect();

    Notification::assertSentTo($target, ResetPassword::class);

    $log = AuditLog::query()->where('action', 'user.password_reset_requested')->sole();

    expect($log->actor_id)->toBe($admin->id)
        ->and($log->subject_id)->toBe($target->id)
        ->and($log->metadata['email'])->toBe($target->email)
        ->and($log->metadata)->not->toHaveKey('token')
        ->and(json_encode($log->metadata))->not->toContain($target->password);
});
