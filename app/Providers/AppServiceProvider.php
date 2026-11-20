<?php

namespace App\Providers;

use App\Chat\LaravelAi\LaravelAiClient;
use App\Chat\LlmClient;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(LlmClient::class, fn () => new LaravelAiClient(
            config('services.llm.provider'),
            config('services.llm.model'),
            (int) config('services.llm.timeout'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->auditLogins();
    }

    /** Login success/failure goes to the audit log (log name `auth`); success also stamps last_login_at. */
    protected function auditLogins(): void
    {
        Event::listen(function (Login $event) {
            if (! $event->user instanceof User) {
                return;
            }

            $event->user->forceFill(['last_login_at' => now()])->save();

            activity('auth')->causedBy($event->user)->event('login')
                ->withProperties(['ip' => request()->ip()])->log('Logged in');
        });

        Event::listen(function (Failed $event) {
            activity('auth')->causedBy($event->user instanceof User ? $event->user : null)->event('login_failed')
                ->withProperties(['email' => $event->credentials['email'] ?? null, 'ip' => request()->ip()])
                ->log('Login failed');
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
