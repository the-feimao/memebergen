<?php

use App\Models\AuditLog;
use App\Models\Member;
use App\Models\MembershipGrade;
use App\Models\UpgradeType;
use App\Models\User;
use App\Services\AuditLogger;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\MembershipGradeSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed([
        AccessControlSeeder::class,
        MembershipGradeSeeder::class,
    ]);
});

test('super admins can review audit logs while editors are forbidden', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');

    $editor = User::factory()->create();
    $editor->assignRole('Editor');

    $this->actingAs($admin);
    app(AuditLogger::class)->record('upgrade_type.created', null, [
        'after' => ['name' => 'ITMA'],
    ], $admin, 'ITMA');

    $this->actingAs($editor)
        ->get(route('audit-logs.index'))
        ->assertForbidden();

    $this->actingAs($admin)
        ->get(route('audit-logs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('audit-logs/index')
            ->has('logs.data', 1)
            ->where('logs.data.0.action', 'upgrade_type.created')
            ->where('logs.data.0.actor.name', $admin->name)
            ->where('logs.data.0.subject_label', 'ITMA')
            ->where('stats.total', 1)
            ->etc());
});

test('guests cannot review audit logs', function () {
    $this->get(route('audit-logs.index'))
        ->assertRedirect(route('login'));
});

test('audit logs can be filtered by action and searched', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');

    $this->actingAs($admin);
    app(AuditLogger::class)->record('membership.created', null, [
        'membership_number' => '267118',
    ], $admin, '267118');
    app(AuditLogger::class)->record('upgrade_type.updated', null, [
        'before' => ['name' => 'Old type'],
    ], $admin, 'ITMA');

    $this->get(route('audit-logs.index', ['action' => 'membership.created']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('logs.total', 1)
            ->where('logs.data.0.action', 'membership.created')
            ->etc());

    $this->get(route('audit-logs.index', ['search' => 'ITMA']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('logs.total', 1)
            ->where('logs.data.0.action', 'upgrade_type.updated')
            ->etc());

    $this->get(route('audit-logs.index', ['search' => '267118']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('logs.total', 1)
            ->where('logs.data.0.action', 'membership.created')
            ->etc());
});

test('audit entries capture the actor, subject, and sanitised context', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');
    $grade = MembershipGrade::query()->where('name', 'Graduate')->firstOrFail();
    $member = Member::factory()->for($grade, 'grade')->create([
        'membership_number' => '267118',
        'full_name' => 'Audited Member',
    ]);

    $this->actingAs($admin);

    app(AuditLogger::class)->record('membership.created', $member, [
        'membership_number' => '267118',
        'password' => 'secret-password',
        'nested' => [
            'remember_token' => 'secret-token',
            'note' => 'retained',
        ],
    ]);

    $log = AuditLog::query()->sole();

    expect($log->actor_id)->toBe($admin->id)
        ->and($log->actor_name)->toBe($admin->name)
        ->and($log->subject_type)->toBe($member->getMorphClass())
        ->and($log->subject_id)->toBe($member->id)
        ->and($log->subject_label)->toBe('267118')
        ->and($log->metadata)->toHaveKey('membership_number')
        ->and($log->metadata)->not->toHaveKey('password')
        ->and($log->metadata['nested'])->toBe(['note' => 'retained']);
});

test('audit entries cannot be edited or deleted', function () {
    $log = app(AuditLogger::class)->record('test.immutable');

    expect(fn () => $log->update(['action' => 'tampered']))
        ->toThrow(RuntimeException::class);

    expect(fn () => $log->delete())
        ->toThrow(RuntimeException::class);

    expect(AuditLog::query()->count())->toBe(1);
});

test('upgrade type changes record before and after values', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');

    $this->actingAs($admin)
        ->post(route('settings.upgrade-types.store'), [
            'name' => 'ITMA',
            'description' => 'Initial description',
            'is_active' => true,
        ])
        ->assertRedirect();

    $upgradeType = UpgradeType::query()->sole();

    $this->actingAs($admin)
        ->patch(route('settings.upgrade-types.update', $upgradeType), [
            'name' => 'ITMA',
            'description' => 'Revised description',
            'is_active' => false,
        ])
        ->assertRedirect();

    $logs = AuditLog::query()->orderBy('id')->get();

    expect($logs)->toHaveCount(2)
        ->and($logs[0]->action)->toBe('upgrade_type.created')
        ->and($logs[0]->metadata['after']['name'])->toBe('ITMA')
        ->and($logs[1]->action)->toBe('upgrade_type.updated')
        ->and($logs[1]->metadata['before']['description'])->toBe('Initial description')
        ->and($logs[1]->metadata['after']['description'])->toBe('Revised description')
        ->and($logs[1]->metadata['after']['is_active'])->toBeFalse();
});

test('authentication events are audited without credentials', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect();

    expect(AuditLog::query()->where('action', 'auth.login')->sole()->metadata)
        ->not->toHaveKey('password');

    $this->post(route('logout'))->assertRedirect();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    expect(AuditLog::query()->where('action', 'auth.logout')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'auth.failed')->exists())->toBeTrue();
});

test('membership register exports are audited', function () {
    $editor = User::factory()->create();
    $editor->assignRole('Editor');

    $this->actingAs($editor)
        ->get(route('members.export'))
        ->assertOk();

    $log = AuditLog::query()->where('action', 'membership.register.exported')->sole();

    expect($log->actor_id)->toBe($editor->id);
});
