<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\BatchHistoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MembershipBatchController;
use App\Http\Controllers\MembershipRegisterController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SystemSettingsController;
use App\Http\Controllers\UpgradeBatchController;
use App\Http\Controllers\UpgradeTypeController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])
        ->middleware('can:dashboard.view')
        ->name('dashboard');

    Route::get('audit-logs', [AuditLogController::class, 'index'])
        ->middleware('can:audit-logs.view')
        ->name('audit-logs.index');

    Route::get('batch-history', [BatchHistoryController::class, 'index'])
        ->middleware('can:membership.batches.view')
        ->name('batch-history.index');

    Route::get('reports', [ReportController::class, 'index'])
        ->middleware('can:membership.register.view')
        ->name('reports.index');

    Route::get('reports/register/export', [ReportController::class, 'exportRegister'])
        ->middleware('can:membership.register.export')
        ->name('reports.register.export');

    Route::get('reports/upgrades/export', [ReportController::class, 'exportUpgrades'])
        ->middleware('can:membership.upgrades.export')
        ->name('reports.upgrades.export');

    Route::middleware('can:users.manage')->group(function () {
        Route::get('users', [UserManagementController::class, 'index'])->name('users.index');
        Route::post('users', [UserManagementController::class, 'store'])->name('users.store');
        Route::patch('users/{user}', [UserManagementController::class, 'update'])->name('users.update');
        Route::post('users/{user}/password-reset', [UserManagementController::class, 'sendPasswordReset'])->name('users.password-reset');
    });

    Route::get('members/export', [MembershipRegisterController::class, 'export'])
        ->middleware('can:membership.register.export')
        ->name('members.export');

    Route::resource('members', MembershipRegisterController::class)
        ->only(['index', 'show'])
        ->middleware('can:membership.register.view');

    Route::get('membership-batches/create', [MembershipBatchController::class, 'create'])
        ->middleware('can:membership.register.import')
        ->name('membership-batches.create');

    Route::post('membership-batches', [MembershipBatchController::class, 'store'])
        ->middleware('can:membership.register.import')
        ->name('membership-batches.store');

    Route::get('membership-batches/template', [MembershipBatchController::class, 'template'])
        ->middleware('can:membership.register.import')
        ->name('membership-batches.template');

    Route::get('membership-batches/{membershipBatch}', [MembershipBatchController::class, 'show'])
        ->middleware('can:membership.batches.view')
        ->name('membership-batches.show');

    Route::post('membership-batches/{membershipBatch}/process', [MembershipBatchController::class, 'process'])
        ->middleware('can:membership.register.process')
        ->name('membership-batches.process');

    Route::get('upgrade-batches/create', [UpgradeBatchController::class, 'create'])
        ->middleware('can:membership.upgrades.import')
        ->name('upgrade-batches.create');

    Route::post('upgrade-batches', [UpgradeBatchController::class, 'store'])
        ->middleware('can:membership.upgrades.import')
        ->name('upgrade-batches.store');

    Route::get('upgrade-batches/template', [UpgradeBatchController::class, 'template'])
        ->middleware('can:membership.upgrades.import')
        ->name('upgrade-batches.template');

    Route::post('upgrade-batches/{membershipBatch}/process', [UpgradeBatchController::class, 'process'])
        ->middleware('can:membership.upgrades.process')
        ->name('upgrade-batches.process');

    Route::get('upgrade-batches/{membershipBatch}/export', [UpgradeBatchController::class, 'exportResults'])
        ->middleware('can:membership.upgrades.export')
        ->name('upgrade-batches.export');

    Route::get('upgrade-batches/{membershipBatch}', [UpgradeBatchController::class, 'show'])
        ->middleware('can:membership.batches.view')
        ->name('upgrade-batches.show');

    Route::middleware('can:upgrade-types.manage')->group(function () {
        Route::get('settings/upgrade-types', [UpgradeTypeController::class, 'index'])
            ->name('settings.upgrade-types.index');
        Route::post('settings/upgrade-types', [UpgradeTypeController::class, 'store'])
            ->name('settings.upgrade-types.store');
        Route::patch('settings/upgrade-types/{upgradeType}', [UpgradeTypeController::class, 'update'])
            ->name('settings.upgrade-types.update');

        Route::get('settings/system', [SystemSettingsController::class, 'index'])
            ->name('settings.system.index');
        Route::patch('settings/system', [SystemSettingsController::class, 'update'])
            ->name('settings.system.update');
    });
});

require __DIR__.'/settings.php';
