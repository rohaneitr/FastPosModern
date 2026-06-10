<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

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
        \Illuminate\Database\Eloquent\Model::preventLazyLoading(! app()->isProduction());

        // ── Pulse Authorization ────────────────────────────────────────────────
        \Illuminate\Support\Facades\Gate::define('viewPulse', function ($user) {
            return $user->hasRole('SuperAdmin');
        });

        // ── Observers ──────────────────────────────────────────────────────────
        \App\Modules\Tenant\Models\Business::observe(\App\Modules\Tenant\Observers\BusinessObserver::class);

        // ── Tenant query macro ─────────────────────────────────────────────────
        \Illuminate\Database\Query\Builder::macro('tenant', function ($tablePrefix = null) {
            if (auth()->hasUser()) {
                $column = $tablePrefix ? $tablePrefix . '.business_id' : 'business_id';
                $this->where($column, auth()->user()->business_id ?? -1);
            }
            return $this;
        });

        // ── Advanced Dynamic Rate-Limiting Matrix (Redis Backed) ──────────────────────────────────────────────────────
        
        // 1. Auth Core Gateway
        \Illuminate\Support\Facades\RateLimiter::for('auth_gateway', function (\Illuminate\Http\Request $request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(5)->by($request->ip())->response(function () {
                return response()->json(['message' => 'Too Many Requests', 'error_code' => 'RATE_LIMIT_EXCEEDED'], 429);
            });
        });

        // 2. Mobile Pulse Telemetry
        \Illuminate\Support\Facades\RateLimiter::for('mobile_pulse', function (\Illuminate\Http\Request $request) {
            $fingerprint = $request->header('X-Device-Fingerprint') ?: $request->ip();
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(60)->by("device_{$fingerprint}")->response(function () {
                return response()->json(['message' => 'Too Many Requests', 'error_code' => 'RATE_LIMIT_EXCEEDED'], 429);
            });
        });

        // 3. Public Global API Routes
        \Illuminate\Support\Facades\RateLimiter::for('api', function (\Illuminate\Http\Request $request) {
            if ($request->header('X-E2E-Bypass') === 'true' && !app()->environment('production')) {
                return \Illuminate\Cache\RateLimiting\Limit::none();
            }
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(200)->by($request->user()?->id ?: $request->ip())->response(function () {
                return response()->json(['message' => 'Too Many Requests', 'error_code' => 'RATE_LIMIT_EXCEEDED'], 429);
            });
        });

        // ── Email event listeners (global mail audit logging) ──────────────────
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Mail\Events\MessageSent::class,   \App\Listeners\LogSentEmail::class);
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Mail\Events\MessageFailed::class, \App\Listeners\LogFailedEmail::class);

        // ── Dynamic SMTP override from database/cache ──────────────────────────
        // If a SuperAdmin has saved SMTP settings via the API, they are stored in
        // the Redis cache under 'global_smtp_settings'. We apply them at boot time
        // so all Mail:: calls in this request use the DB-configured SMTP server.
        $this->applyDynamicSmtpConfig();
    }

    /**
     * Reads SMTP config from Redis (set by SettingsController::updateSmtp)
     * and overrides the runtime config() values.
     *
     * Falls back to .env values if no DB config is stored.
     * Silently swallows exceptions so a missing Redis connection
     * never breaks the boot cycle.
     */
    private function applyDynamicSmtpConfig(): void
    {
        try {
            $smtp = \Illuminate\Support\Facades\Cache::get('global_smtp_settings');

            if (!$smtp || !is_array($smtp) || empty($smtp['mail_host'])) {
                return; // No override — use .env values
            }

            config([
                'mail.mailers.smtp.host'       => $smtp['mail_host']       ?? config('mail.mailers.smtp.host'),
                'mail.mailers.smtp.port'        => (int) ($smtp['mail_port'] ?? config('mail.mailers.smtp.port')),
                'mail.mailers.smtp.username'    => $smtp['mail_username']   ?? config('mail.mailers.smtp.username'),
                'mail.mailers.smtp.password'    => $smtp['mail_password']   ?? config('mail.mailers.smtp.password'),
                'mail.mailers.smtp.encryption'  => $smtp['mail_encryption'] ?? config('mail.mailers.smtp.encryption'),
                'mail.from.address'             => $smtp['mail_from_address'] ?? config('mail.from.address'),
                'mail.from.name'                => $smtp['mail_from_name']   ?? config('mail.from.name'),
                'mail.default'                  => 'smtp',
            ]);
        } catch (\Throwable $e) {
            // Log but never throw — boot must always succeed
            \Illuminate\Support\Facades\Log::warning('Dynamic SMTP config load failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}

