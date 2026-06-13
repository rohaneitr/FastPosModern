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
        // ── Strict Mode (bug catcher in non-production) ───────────────────────
        // shouldBeStrict() combines three protections:
        //   1. preventLazyLoading()   — throws on unloaded relations (N+1 catcher)
        //   2. preventSilentlyDiscardingAttributes() — throws on fillable violations
        //   3. preventAccessingMissingAttributes()  — throws on undefined attr reads
        \Illuminate\Database\Eloquent\Model::shouldBeStrict(! app()->isProduction());
        // Keep the explicit preventLazyLoading call as defence-in-depth for production:
        // shouldBeStrict(false) in prod still allows preventLazyLoading to be set separately.
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

        // 1b. Checkout Gateway (Anti-Spam / Double-Billing)
        \Illuminate\Support\Facades\RateLimiter::for('checkout', function (\Illuminate\Http\Request $request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(10)->by($request->user()?->id ?: $request->ip())->response(function () {
                return response()->json(['message' => 'Checkout Rate Limit Exceeded', 'error_code' => 'RATE_LIMIT_EXCEEDED'], 429);
            });
        });

        // 2. Mobile Pulse Telemetry
        \Illuminate\Support\Facades\RateLimiter::for('mobile_pulse', function (\Illuminate\Http\Request $request) {
            $fingerprint = $request->header('X-Device-Fingerprint') ?: $request->ip();
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(60)->by("device_{$fingerprint}")->response(function () {
                return response()->json(['message' => 'Too Many Requests', 'error_code' => 'RATE_LIMIT_EXCEEDED'], 429);
            });
        });

        // 3. General Tenant API — 60 req/min per authenticated user (or IP if unauthenticated)
        // Applied via throttle:api middleware on the global api middleware stack.
        \Illuminate\Support\Facades\RateLimiter::for('api', function (\Illuminate\Http\Request $request) {
            if ($request->header('X-E2E-Bypass') === 'true' && ! app()->environment('production')) {
                return \Illuminate\Cache\RateLimiting\Limit::none();
            }
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(60)
                ->by($request->user()?->id ?: $request->ip())
                ->response(fn() => response()->json([
                    'success'    => false,
                    'message'    => 'Too many requests. Please slow down.',
                    'code'       => 'RATE_LIMIT_EXCEEDED',
                ], 429));
        });

        // 4. POS High-Burst Gateway — 500 req/min per authenticated user
        // POS tablets issue rapid barcode scans, offline sync pushes, and heartbeats.
        // A tight limit here would cause false 429s during bulk sync sessions.
        // Applied explicitly on: sync/push, sync/pull, checkout.
        \Illuminate\Support\Facades\RateLimiter::for('pos', function (\Illuminate\Http\Request $request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(500)
                ->by($request->user()?->id ?: $request->ip())
                ->response(fn() => response()->json([
                    'success' => false,
                    'message' => 'POS rate limit exceeded. Reduce sync frequency.',
                    'code'    => 'POS_RATE_LIMIT_EXCEEDED',
                ], 429));
        });

        // ── Email event listeners (global mail audit logging) ──────────────────
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Mail\Events\MessageSent::class,   \App\Listeners\LogSentEmail::class);
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Mail\Events\MessageFailed::class, \App\Listeners\LogFailedEmail::class);

        // ── Sales Domain Events (Phase 5 — Synchronous Listeners) ─────────────
        // CRITICAL: These listeners do NOT implement ShouldQueue.
        // They execute synchronously inside the DB::transaction() opened by
        // ProcessSaleService. Any exception thrown rolls back the entire sale.
        // Registration order = execution order:
        //   1. DeductStockFromSale         — Inventory domain (throws on stock shortage)
        //   2. ApplyLoyaltyPointsFromSale  — CRM domain      (throws on wallet error)
        //   3. RecordSaleJournalEntry      — Accounting domain (throws on imbalance)
        \Illuminate\Support\Facades\Event::listen(
            \App\Modules\Sales\Events\SaleCompleted::class,
            \App\Modules\Inventory\Listeners\DeductStockFromSale::class,
        );
        \Illuminate\Support\Facades\Event::listen(
            \App\Modules\Sales\Events\SaleCompleted::class,
            \App\Modules\CRM\Listeners\ApplyLoyaltyPointsFromSale::class,
        );
        \Illuminate\Support\Facades\Event::listen(
            \App\Modules\Sales\Events\SaleCompleted::class,
            \App\Modules\Accounting\Listeners\RecordSaleJournalEntry::class,
        );

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

