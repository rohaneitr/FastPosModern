<?php

namespace App\Modules\Clinical\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Clinical\Events\LabReportOTPRequestedEvent;
use App\Modules\Clinical\Models\Patient;
use App\Modules\Clinical\Services\DiagnosticReportGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

class PortalController extends Controller
{
    /**
     * STEP 1: Request OTP
     * Validates mobile number against the encrypted Patient record.
     */
    public function requestOtp(Request $request)
    {
        $request->validate([
            'order_number' => 'required|string',
            'mobile_number' => 'required|string',
        ]);

        $order = DB::table('clinical_lab_orders')->where('order_number', $request->order_number)->first();
        if (!$order) {
            // Return generic 404 to prevent enumeration
            return response()->json(['message' => 'Report not found or mobile number mismatch.'], 404);
        }

        $patient = Patient::find($order->patient_id);
        if (!$patient || $patient->mobile_number !== $request->mobile_number) {
            return response()->json(['message' => 'Report not found or mobile number mismatch.'], 404);
        }

        // Generate and store OTP (valid for 5 minutes)
        $otp = (string) rand(100000, 999999);
        Cache::put("lab_portal_otp_{$order->id}", $otp, now()->addMinutes(5));

        // Dispatch Event for SMS/WhatsApp
        event(new LabReportOTPRequestedEvent($patient->mobile_number, $otp));

        return response()->json(['message' => 'OTP sent securely to your registered mobile number.']);
    }

    /**
     * STEP 2: Verify OTP & Issue Signed URL
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'order_number' => 'required|string',
            'otp' => 'required|string|size:6',
        ]);

        $order = DB::table('clinical_lab_orders')->where('order_number', $request->order_number)->first();
        if (!$order) {
            return response()->json(['message' => 'Invalid request.'], 400);
        }

        $cachedOtp = Cache::get("lab_portal_otp_{$order->id}");
        if (!$cachedOtp || $cachedOtp !== $request->otp) {
            return response()->json(['message' => 'Invalid or expired OTP.'], 401);
        }

        // OTP Verified. Clear Cache.
        Cache::forget("lab_portal_otp_{$order->id}");

        // Generate Cryptographically Signed URL valid for 30 minutes
        $signedUrl = URL::temporarySignedRoute(
            'clinical.portal.download', 
            now()->addMinutes(30), 
            ['order_id' => $order->id]
        );

        return response()->json([
            'message' => 'Verification successful.',
            'signed_url' => $signedUrl,
        ]);
    }

    /**
     * STEP 3: Secure Download (Protected by Signed Middleware)
     */
    public function downloadReport(Request $request, $order_id, DiagnosticReportGenerator $generator)
    {
        if (!$request->hasValidSignature()) {
            abort(403, 'This secure link has expired or is invalid. Please request a new OTP.');
        }

        // In a real app, this generates the PDF and returns it as a download stream.
        // For this demo, we output the generated HTML.
        $html = $generator->generateReportPdf($order_id);
        
        return response($html)->header('Content-Type', 'text/html');
    }
}
