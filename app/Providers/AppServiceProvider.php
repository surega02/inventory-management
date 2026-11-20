<?php

namespace App\Providers;

use App\Chat\LaravelAi\LaravelAiClient;
use App\Chat\LlmClient;
use App\Http\Controllers\ChatController;
use App\Models\ChatLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
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
        $this->configureChatRateLimit();
    }

    /** throttle:chat — 20 messages/hour per user; the 429 is logged and uses the chat error format. */
    protected function configureChatRateLimit(): void
    {
        RateLimiter::for('chat', fn (Request $request) => Limit::perHour(20)
            ->by('chat:'.$request->user()?->getAuthIdentifier())
            ->response(function (Request $request, array $headers) {
                $retryAfter = (int) ($headers['Retry-After'] ?? 3600);
                $message = $request->input('message');

                if ($request->user() instanceof User) {
                    ChatLog::record($request->user(), is_string($message) ? $message : '', 'rate_limited');
                }

                return ChatController::error(429, 'rate_limited', 'You have reached the limit of 20 messages per hour. Please try again later.', ['retry_after' => $retryAfter], $headers);
            }));
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
