<?php

namespace App\Modules\HR\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HR\Requests\InviteStaffRequest;
use App\Modules\HR\Models\TeamInvitation;
use App\Modules\HR\Jobs\SendStaffInvitationJob;
use Illuminate\Support\Str;

class InviteController extends Controller
{
    public function store(InviteStaffRequest $request)
    {
        $businessId = $request->user()->business_id;

        // Revoke any existing pending invitations for this email in this business
        TeamInvitation::where('business_id', $businessId)
            ->where('email', $request->email)
            ->delete();

        $invitation = TeamInvitation::create([
            'business_id' => $businessId,
            'email' => $request->email,
            'role' => $request->role,
            'token' => hash('sha256', Str::random(40)),
            'expires_at' => now()->addDays(7),
        ]);

        // Dispatch async job for enterprise scale
        SendStaffInvitationJob::dispatch($invitation, $request->user()->business->name ?? 'FastPOS Business');

        return response()->json([
            'message' => 'Invitation sent successfully',
            'data' => $invitation,
        ], 202); // 202 Accepted because email delivery is async
    }
}
