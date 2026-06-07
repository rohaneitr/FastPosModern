<?php

namespace App\Domain\Tenant\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Tenant\Models\EmailLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

/**
 * ─────────────────────────────────────────────────────────────────────────────
 * EmailLogController  (Phase 7 — Email Management)
 *
 * GET  /api/v1/superadmin/email-logs          → paginated email audit log
 * GET  /api/v1/superadmin/email-logs/stats    → summary counts
 * GET  /api/v1/superadmin/smtp-settings       → current SMTP config (masked)
 * POST /api/v1/superadmin/smtp-settings       → save & apply SMTP config
 * POST /api/v1/superadmin/smtp-settings/test  → send test email
 * ─────────────────────────────────────────────────────────────────────────────
 */
class EmailLogController extends Controller
{
    // ── Email log viewer ──────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $this->guard($request);

        $query = EmailLog::orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $s = strtolower($request->search);
            $query->where(function ($q) use ($s) {
                $q->whereRaw('LOWER(to_email) LIKE ?',  ["%{$s}%"])
                  ->orWhereRaw('LOWER(subject) LIKE ?', ["%{$s}%"]);
            });
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        $logs = $query->paginate(50);

        return response()->json(['logs' => $logs]);
    }

    // ── Stats summary ─────────────────────────────────────────────────────────

    public function stats(Request $request): JsonResponse
    {
        $this->guard($request);

        $stats = EmailLog::selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN status = 'sent'   THEN 1 ELSE 0 END) as sent,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
            SUM(CASE WHEN status = 'queued' THEN 1 ELSE 0 END) as queued
        ")->first();

        $last24h = EmailLog::where('created_at', '>=', now()->subDay())->count();

        return response()->json([
            'total'   => (int) $stats->total,
            'sent'    => (int) $stats->sent,
            'failed'  => (int) $stats->failed,
            'queued'  => (int) $stats->queued,
            'last_24h'=> $last24h,
        ]);
    }

    // ── SMTP settings reader ──────────────────────────────────────────────────

    public function getSmtpSettings(Request $request): JsonResponse
    {
        $this->guard($request);

        $stored = Cache::store('redis')->get('global_smtp_settings', []);

        // Mask the password — never send the real value to the frontend
        $masked = $stored;
        if (!empty($masked['mail_password'])) {
            $masked['mail_password'] = str_repeat('•', 16);
            $masked['_password_saved'] = true;
        }

        // Merge with .env fallbacks for display (but mark them as defaults)
        $defaults = [
            'mail_host'         => config('mail.mailers.smtp.host',      'smtp.gmail.com'),
            'mail_port'         => config('mail.mailers.smtp.port',      587),
            'mail_username'     => config('mail.mailers.smtp.username',  ''),
            'mail_password'     => '',
            'mail_encryption'   => config('mail.mailers.smtp.encryption','tls'),
            'mail_from_address' => config('mail.from.address',           ''),
            'mail_from_name'    => config('mail.from.name',              'FastPOS Platform'),
            '_password_saved'   => false,
            '_source'           => 'env', // tell the UI where config came from
        ];

        $result = array_merge($defaults, $masked);
        $result['_source'] = !empty($stored) ? 'database' : 'env';

        return response()->json($result);
    }

    // ── SMTP settings writer ──────────────────────────────────────────────────

    public function saveSmtpSettings(Request $request): JsonResponse
    {
        $this->guard($request);

        $validated = $request->validate([
            'mail_host'         => 'required|string|max:255',
            'mail_port'         => 'required|integer|in:25,465,587,2525',
            'mail_username'     => 'required|email|max:255',
            'mail_password'     => 'nullable|string|max:255',
            'mail_encryption'   => 'required|in:tls,ssl,none',
            'mail_from_address' => 'required|email|max:255',
            'mail_from_name'    => 'required|string|max:100',
        ]);

        // Preserve existing password if the frontend sends a masked placeholder
        if (empty($validated['mail_password']) || str_contains($validated['mail_password'], '•')) {
            $existing = Cache::store('redis')->get('global_smtp_settings', []);
            $validated['mail_password'] = $existing['mail_password'] ?? '';
        }

        Cache::store('redis')->forever('global_smtp_settings', $validated);

        // Apply immediately to this request's runtime config
        Config::set([
            'mail.mailers.smtp.host'      => $validated['mail_host'],
            'mail.mailers.smtp.port'      => $validated['mail_port'],
            'mail.mailers.smtp.username'  => $validated['mail_username'],
            'mail.mailers.smtp.password'  => $validated['mail_password'],
            'mail.mailers.smtp.encryption'=> $validated['mail_encryption'] === 'none' ? null : $validated['mail_encryption'],
            'mail.from.address'           => $validated['mail_from_address'],
            'mail.from.name'              => $validated['mail_from_name'],
        ]);

        Log::info('SMTP settings updated by SuperAdmin', [
            'host' => $validated['mail_host'],
            'user' => $validated['mail_username'],
            'by'   => $request->user()->email,
        ]);

        return response()->json(['message' => 'SMTP settings saved and applied.']);
    }

    // ── Test email ────────────────────────────────────────────────────────────

    public function testSmtp(Request $request): JsonResponse
    {
        $this->guard($request);

        $request->validate([
            'to_email' => 'required|email|max:255',
        ]);

        try {
            Mail::raw(
                "✅ This is a test email from FastPOS.\n\n"
                . "If you received this, your SMTP configuration is working correctly.\n\n"
                . "Sent at: " . now()->toDateTimeString(),
                function ($message) use ($request) {
                    $message->to($request->to_email)
                            ->subject('FastPOS — SMTP Connection Test');
                }
            );

            // Log the test email manually (raw mails don't fire Mailable events)
            EmailLog::create([
                'to_email'       => $request->to_email,
                'subject'        => 'FastPOS — SMTP Connection Test',
                'status'         => 'sent',
                'mailable_class' => 'smtp_test',
                'sent_at'        => now(),
            ]);

            return response()->json(['message' => "Test email sent to {$request->to_email} successfully."]);
        } catch (\Throwable $e) {
            EmailLog::create([
                'to_email'      => $request->to_email,
                'subject'       => 'FastPOS — SMTP Connection Test',
                'status'        => 'failed',
                'error_message' => substr($e->getMessage(), 0, 1000),
                'sent_at'       => null,
            ]);

            return response()->json([
                'message' => 'SMTP test failed: ' . $e->getMessage(),
            ], 422);
        }
    }

    // ── Guard ─────────────────────────────────────────────────────────────────

    private function guard(Request $request): void
    {
        if (!$request->user()?->hasRole('SuperAdmin')) {
            abort(403, 'Unauthorized.');
        }
    }
}
