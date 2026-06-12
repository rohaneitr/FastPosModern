<?php

namespace App\Modules\CRM\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\Contact;
use Illuminate\Validation\ValidationException;

class CustomerPortalController extends Controller
{
    /**
     * Issue an OTP for a given customer contact email or phone.
     */
    public function sendOtp(Request $request)
    {
        $request->validate(['identifier' => 'required|string']);

        $contact = Contact::where('type', 'customer')
            ->where(function($q) use ($request) {
                $q->where('email', $request->identifier)
                  ->orWhere('mobile', $request->identifier);
            })->first();

        if (!$contact) {
            // Prevent enumeration, return success even if not found
            return response()->json(['message' => 'If an account exists, an OTP has been sent.']);
        }

        $otp = random_int(100000, 999999);
        // Store for 5 minutes
        Cache::put("customer_otp_{$contact->id}", $otp, 300);

        // Simulated Dispatch (e.g., Mail::to($contact->email)->send(new OtpMail($otp)))
        \Illuminate\Support\Facades\Log::info("Customer Portal OTP for Contact ID {$contact->id} is {$otp}");

        return response()->json(['message' => 'If an account exists, an OTP has been sent.']);
    }

    /**
     * Verify OTP and issue restricted Sanctum token.
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'identifier' => 'required|string',
            'otp' => 'required|integer'
        ]);

        $contact = Contact::where('type', 'customer')
            ->where(function($q) use ($request) {
                $q->where('email', $request->identifier)
                  ->orWhere('mobile', $request->identifier);
            })->first();

        if (!$contact) {
            throw ValidationException::withMessages(['otp' => 'Invalid OTP.']);
        }

        $storedOtp = Cache::get("customer_otp_{$contact->id}");

        if (!$storedOtp || $storedOtp != $request->otp) {
            throw ValidationException::withMessages(['otp' => 'Invalid or expired OTP.']);
        }

        // OTP Valid - Clear it
        Cache::forget("customer_otp_{$contact->id}");

        // Revoke old customer tokens
        $contact->tokens()->where('name', 'customer_portal')->delete();

        // Issue strictly scoped token
        $token = $contact->createToken('customer_portal', ['customer:read-own-data'])->plainTextToken;

        return response()->json([
            'message' => 'Successfully authenticated',
            'token' => $token,
            'customer' => $contact->only('id', 'name', 'email', 'mobile')
        ]);
    }

    /**
     * Fetch aggregated dashboard metrics securely isolated to the contact's ID.
     */
    public function dashboardMetrics(Request $request)
    {
        $contactId = $request->user()->id;

        // Fetch Wallet Balance
        $walletBalance = DB::table('customer_wallets')->where('contact_id', $contactId)->value('balance') ?? 0;

        // Fetch Loyalty Points
        $loyaltyPoints = DB::table('loyalty_point_ledgers')->where('contact_id', $contactId)->sum('running_balance') ?? 0;

        // Fetch Invoices
        $invoices = DB::table('transactions')
            ->where('contact_id', $contactId)
            ->where('type', 'sell')
            ->orderBy('transaction_date', 'desc')
            ->limit(10)
            ->get(['invoice_no', 'final_total', 'payment_status', 'transaction_date', 'id']);

        // Fetch Diagnostic Reports
        $diagnosticReports = DB::table('diagnostic_reports')
            ->where('patient_id', $contactId)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get(['id', 'test_type', 'status', 'created_at']);

        return response()->json([
            'kpis' => [
                'wallet_balance' => $walletBalance,
                'loyalty_points' => $loyaltyPoints
            ],
            'recent_invoices' => $invoices,
            'diagnostic_reports' => $diagnosticReports
        ]);
    }
}
