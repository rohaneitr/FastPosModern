<?php

namespace App\Modules\SuperAdmin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SuperAdmin\Models\Ticket;
use App\Modules\SuperAdmin\Models\TicketReply;
use App\Modules\SuperAdmin\Requests\TicketReplyRequest;
use App\Modules\SuperAdmin\Requests\UpdateTicketStatusRequest;
use Illuminate\Http\Request;

class TicketManagementController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'status' => 'nullable|string',
            'priority' => 'nullable|string',
        ]);

        $query = Ticket::withoutGlobalScopes()->with(['business:id,company_name', 'user:id,name,email']);

        if (!empty($validated['search'])) {
            $query->where(function($q) use ($validated) {
                $q->where('subject', 'LIKE', '%' . $validated['search'] . '%')
                  ->orWhereHas('user', function($uq) use ($validated) {
                      $uq->where('name', 'LIKE', '%' . $validated['search'] . '%')
                         ->orWhere('email', 'LIKE', '%' . $validated['search'] . '%');
                  })
                  ->orWhereHas('business', function($bq) use ($validated) {
                      $bq->where('company_name', 'LIKE', '%' . $validated['search'] . '%');
                  });
            });
        }

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (!empty($validated['priority'])) {
            $query->where('priority', $validated['priority']);
        }

        $tickets = $query->orderBy('updated_at', 'desc')->paginate(20);

        return response()->json([
            'tickets' => $tickets
        ]);
    }

    public function show($id)
    {
        $ticket = Ticket::withoutGlobalScopes()
            ->with([
                'business:id,company_name',
                'user:id,name,email',
                'replies' => function ($q) {
                    $q->orderBy('created_at', 'asc')->with('user:id,name,email');
                }
            ])
            ->findOrFail($id);

        return response()->json([
            'ticket' => $ticket
        ]);
    }

    public function reply(TicketReplyRequest $request, $id)
    {
        $ticket = Ticket::withoutGlobalScopes()->findOrFail($id);

        $reply = $ticket->replies()->create([
            'user_id' => $request->user()->id,
            'message' => $request->message,
            'is_admin_reply' => true,
        ]);

        // Automatically mark as 'In Progress' if Open
        if ($ticket->status === 'Open') {
            $ticket->update(['status' => 'In Progress']);
        }

        // Touch the ticket to update its updated_at timestamp for sorting
        $ticket->touch();

        return response()->json([
            'message' => 'Reply posted successfully.',
            'reply' => $reply->load('user:id,name,email')
        ]);
    }

    public function updateStatus(UpdateTicketStatusRequest $request, $id)
    {
        $ticket = Ticket::withoutGlobalScopes()->findOrFail($id);
        
        $ticket->update([
            'status' => $request->status
        ]);

        return response()->json([
            'message' => 'Ticket status updated to ' . $request->status,
            'ticket' => $ticket
        ]);
    }
}
