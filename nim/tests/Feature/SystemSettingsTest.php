<?php

use App\Models\AuditLog;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\SystemSettings;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(AccessControlSeeder::class);
});

test('guests and editors cannot manage system settings', function () {
    $this->get(route('settings.system.index'))->assertRedirect(route('login'));

    $editor = User::factory()->create();
    $editor->assignRole('Editor');

    $this->actingAs($editor)
        ->get(route('settings.system.index'))
        ->assertForbidden();

    $this->actingAs($editor)
        ->patch(route('settings.system.update'), [
            'max_kilobytes' => 1,
            'max_rows' => 1,
            'chunk_size' => 1,
        ])
        ->assertForbidden();
});

test('super admins can update settings and the change is audited', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');

    $this->actingAs($admin)
        ->get(route('settings.system.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/system/index')
            ->where('settings.max_kilobytes', 10240)
            ->where('settings.max_rows', 10000)
            ->where('settings.chunk_size', 500)
            ->where('numbering.width', 6)
            ->where('upgradeRules.transition', 'one_step')
            ->etc());

    $this->actingAs($admin)
        ->patch(route('settings.system.update'), [
            'max_kilobytes' => 2048,
            'max_rows' => 750,
            'chunk_size' => 250,
        ])
        ->assertRedirect(route('settings.system.index'));

    expect(SystemSetting::query()->where('key', SystemSettings::IMPORT_MAX_KILOBYTES)->value('value'))->toBe(2048)
        ->and(app(SystemSettings::class)->getInt(SystemSettings::IMPORT_MAX_ROWS))->toBe(750);

    $log = AuditLog::query()->where('action', 'settings.import_updated')->sole();

    expect($log->actor_id)->toBe($admin->id)
        ->and($log->metadata['before']['max_rows'])->toBe(10000)
        ->and($log->metadata['after']['max_rows'])->toBe(750);
});

test('updated import limits are applied to upload screens and validation', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');

    $this->actingAs($admin)
        ->patch(route('settings.system.update'), [
            'max_kilobytes' => 1,
            'max_rows' => 25,
            'chunk_size' => 10,
        ]);

    $this->get(route('membership-batches.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('maxFileSizeKilobytes', 1)
            ->where('maxRows', 25)
            ->etc());

    $this->post(route('membership-batches.store'), [
        'file' => UploadedFile::fake()->createWithContent('members.xlsx', str_repeat('x', 2048)),
    ])->assertSessionHasErrors('file');

    $this->get(route('upgrade-batches.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('maxFileSizeKilobytes', 1)
            ->where('maxRows', 25)
            ->etc());
});
