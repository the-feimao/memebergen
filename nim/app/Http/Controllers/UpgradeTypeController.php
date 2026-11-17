<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUpgradeTypeRequest;
use App\Http\Requests\UpdateUpgradeTypeRequest;
use App\Models\UpgradeType;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class UpgradeTypeController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function index(): Response
    {
        $upgradeTypes = UpgradeType::query()
            ->orderBy('name')
            ->get(['id', 'name', 'description', 'is_active', 'created_at']);

        return Inertia::render('settings/upgrade-types', [
            'upgradeTypes' => $upgradeTypes->map(static fn (UpgradeType $type): array => [
                'id' => $type->id,
                'name' => $type->name,
                'description' => $type->description,
                'is_active' => $type->is_active,
            ]),
        ]);
    }

    public function store(StoreUpgradeTypeRequest $request): RedirectResponse
    {
        $upgradeType = UpgradeType::query()->create([
            'name' => $request->string('name')->trim()->toString(),
            'description' => $request->filled('description')
                ? $request->string('description')->trim()->toString()
                : null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        $this->audit->record('upgrade_type.created', $upgradeType, [
            'after' => $this->snapshot($upgradeType),
        ], $request->user());

        return redirect()
            ->route('settings.upgrade-types.index')
            ->with('success', 'Upgrade type created.');
    }

    public function update(UpdateUpgradeTypeRequest $request, UpgradeType $upgradeType): RedirectResponse
    {
        $before = $this->snapshot($upgradeType);

        $upgradeType->update([
            'name' => $request->string('name')->trim()->toString(),
            'description' => $request->filled('description')
                ? $request->string('description')->trim()->toString()
                : null,
            'is_active' => $request->boolean('is_active'),
        ]);

        if ($upgradeType->wasChanged()) {
            $this->audit->record('upgrade_type.updated', $upgradeType, [
                'before' => $before,
                'after' => $this->snapshot($upgradeType),
            ], $request->user());
        }

        return redirect()
            ->route('settings.upgrade-types.index')
            ->with('success', 'Upgrade type updated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(UpgradeType $upgradeType): array
    {
        return [
            'name' => $upgradeType->name,
            'description' => $upgradeType->description,
            'is_active' => $upgradeType->is_active,
        ];
    }
}
