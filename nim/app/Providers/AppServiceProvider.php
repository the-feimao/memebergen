<?php

namespace App\Providers;

use App\Models\User;
use App\Services\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuditEvents();

        Gate::before(static fn (User $user, string $ability): ?bool => $user->hasRole('Super Admin') ? true : null);
    }

    /**
     * Record authentication activity without exposing credentials.
     */
    protected function configureAuditEvents(): void
    {
        Event::listen(Login::class, static function (Login $event): void {
            $actor = $event->user instanceof User ? $event->user : null;

            if ($actor !== null) {
                $actor->forceFill(['last_login_at' => now()])->saveQuietly();
            }

            app(AuditLogger::class)->record('auth.login', $actor, [
                'guard' => $event->guard,
            ], $actor);
        });

        Event::listen(Logout::class, static function (Logout $event): void {
            $actor = $event->user instanceof User ? $event->user : null;

            app(AuditLogger::class)->record('auth.logout', $actor, [
                'guard' => $event->guard,
            ], $actor);
        });

        Event::listen(Failed::class, static function (Failed $event): void {
            app(AuditLogger::class)->record('auth.failed', null, [
                'guard' => $event->guard,
                'email' => data_get($event->credentials, 'email'),
            ]);
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
