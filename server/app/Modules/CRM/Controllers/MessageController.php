<?php

namespace App\Modules\CRM\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CRM\Requests\BulkMessageRequest;
use App\Modules\CRM\Jobs\BulkMessageJob;
use Illuminate\Support\Facades\DB;

class MessageController extends Controller
{
    public function sendBulk(BulkMessageRequest $request)
    {
        $businessId = $request->user()->business_id;

        // Collect target emails/phones based on payload
        $targets = [];

        if ($request->has('user_ids') && is_array($request->user_ids)) {
            // Verify users belong to the same business to prevent cross-tenant messaging
            $validUsers = DB::table('users')
                ->where('business_id', $businessId)
                ->whereIn('id', $request->user_ids)
                ->select('email', 'first_name', 'last_name')
                ->get();
            
            foreach ($validUsers as $user) {
                if ($user->email) {
                    $targets[] = ['email' => $user->email, 'name' => $user->first_name . ' ' . $user->last_name];
                }
            }
        }

        if ($request->has('customer_ids') && is_array($request->customer_ids)) {
            // Verify contacts belong to the same business
            $validContacts = DB::table('contacts')
                ->where('business_id', $businessId)
                ->whereIn('id', $request->customer_ids)
                ->select('email', 'first_name', 'last_name', 'name')
                ->get();
            
            foreach ($validContacts as $contact) {
                if ($contact->email) {
                    $targets[] = ['email' => $contact->email, 'name' => $contact->name ?? ($contact->first_name . ' ' . $contact->last_name)];
                }
            }
        }

        if (empty($targets)) {
            return response()->json(['message' => 'No valid recipients found or selected recipients do not belong to your business.'], 422);
        }

        // Dispatch background job to prevent API timeout
        BulkMessageJob::dispatch(
            $targets,
            $request->subject,
            $request->message,
            $request->user()->business->name ?? 'FastPOS Business'
        );

        return response()->json([
            'message' => 'Bulk message queued successfully. ' . count($targets) . ' recipients will be notified.'
        ], 202);
    }
}
