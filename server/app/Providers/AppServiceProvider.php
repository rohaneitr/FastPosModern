<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Mail\Events\MessageFailed;
use App\Listeners\LogSentEmail;
use App\Listeners\LogFailedEmail;

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
        // ── Tenant query macro ─────────────────────────────────────────────────
        \Illuminate\Database\Query\Builder::macro('tenant', function ($tablePrefix = null) {
            if (auth()->hasUser()) {
                $column = $tablePrefix ? $tablePrefix . '.business_id' : 'business_id';
                $this->where($column, auth()->user()->business_id ?? -1);
            }
            return $this;
        });

        // ── Rate limiters ──────────────────────────────────────────────────────
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(60)->by($request->input('email', $request->ip()));
        });

        // ── Email event listeners (global mail audit logging) ──────────────────
        Event::listen(MessageSent::class,   LogSentEmail::class);
        Event::listen(MessageFailed::class, LogFailedEmail::class);

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
            $smtp = Cache::store('redis')->get('global_smtp_settings');

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
