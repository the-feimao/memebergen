<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSystemSettingsRequest;
use App\Services\AuditLogger;
use App\Services\SystemSettings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SystemSettingsController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly SystemSettings $settings,
    ) {}

    public function index(): Response
    {
        return Inertia::render('settings/system/index', [
            'settings' => [
                'max_kilobytes' => $this->settings->getInt(SystemSettings::IMPORT_MAX_KILOBYTES),
                'max_rows' => $this->settings->getInt(SystemSettings::IMPORT_MAX_ROWS),
                'chunk_size' => $this->settings->getInt(SystemSettings::IMPORT_CHUNK_SIZE),
            ],
            'defaults' => [
                'max_kilobytes' => (int) config('membership.import.max_kilobytes', 10240),
                'max_rows' => (int) config('membership.import.max_rows', 10000),
                'chunk_size' => (int) config('membership.import.chunk_size', 500),
            ],
            'numbering' => [
                'width' => 6,
                'expands_at' => 999999,
                'gap_fill' => true,
            ],
            'upgradeRules' => [
                'transition' => 'one_step',
                'path' => 'Graduate → Associate → Member → Fellow',
            ],
            'links' => [
                'upgrade_types' => route('settings.upgrade-types.index'),
            ],
        ]);
    }

    public function update(UpdateSystemSettingsRequest $request): RedirectResponse
    {
        $before = [
            'max_kilobytes' => $this->settings->getInt(SystemSettings::IMPORT_MAX_KILOBYTES),
            'max_rows' => $this->settings->getInt(SystemSettings::IMPORT_MAX_ROWS),
            'chunk_size' => $this->settings->getInt(SystemSettings::IMPORT_CHUNK_SIZE),
        ];

        $this->settings->update([
            SystemSettings::IMPORT_MAX_KILOBYTES => (int) $request->integer('max_kilobytes'),
            SystemSettings::IMPORT_MAX_ROWS => (int) $request->integer('max_rows'),
            SystemSettings::IMPORT_CHUNK_SIZE => (int) $request->integer('chunk_size'),
        ]);

        $after = [
            'max_kilobytes' => $this->settings->getInt(SystemSettings::IMPORT_MAX_KILOBYTES),
            'max_rows' => $this->settings->getInt(SystemSettings::IMPORT_MAX_ROWS),
            'chunk_size' => $this->settings->getInt(SystemSettings::IMPORT_CHUNK_SIZE),
        ];

        if ($before !== $after) {
            $this->audit->record('settings.import_updated', null, [
                'before' => $before,
                'after' => $after,
            ], $request->user());
        }

        return redirect()
            ->route('settings.system.index')
            ->with('success', 'System settings saved.');
    }
}
