<?php

namespace App\Domain\Support\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Domain\Support\Models\SupportTicket;
use App\Domain\Support\Models\TicketReply;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->hasRole('SuperAdmin')) {
            $tickets = SupportTicket::with('user', 'business')->orderBy('created_at', 'desc')->get();
        } else {
            $tickets = SupportTicket::with('user', 'business')
                ->where('business_id', $user->business_id)
                ->orderBy('created_at', 'desc')
                ->get();
        }

        return response()->json($tickets);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'priority' => 'nullable|string|in:low,medium,high,urgent',
            'message' => 'required|string'
        ]);

        $ticket = SupportTicket::create([
            'business_id' => $request->user()->business_id,
            'user_id' => $request->user()->id,
            'subject' => $validated['subject'],
            'priority' => $validated['priority'] ?? 'medium',
            'status' => 'open'
        ]);

        TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'message' => $validated['message']
        ]);

        return response()->json(['message' => 'Support ticket created successfully', 'ticket' => $ticket], 201);
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();

        $query = SupportTicket::with(['user', 'business', 'replies.user']);

        if (!$user->hasRole('SuperAdmin')) {
            $query->where('business_id', $user->business_id);
        }

        $ticket = $query->findOrFail($id);

        return response()->json($ticket);
    }

    public function reply(Request $request, $id)
    {
        $validated = $request->validate([
            'message' => 'required|string'
        ]);

        $user = $request->user();
        
        $query = SupportTicket::query();
        if (!$user->hasRole('SuperAdmin')) {
            $query->where('business_id', $user->business_id);
        }

        $ticket = $query->findOrFail($id);

        $reply = TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'message' => $validated['message']
        ]);

        return response()->json(['message' => 'Reply added successfully', 'reply' => $reply]);
    }

    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|string|in:open,in_progress,resolved,closed'
        ]);

        $user = $request->user();
        
        $query = SupportTicket::query();
        if (!$user->hasRole('SuperAdmin')) {
            $query->where('business_id', $user->business_id);
        }

        $ticket = $query->findOrFail($id);
        $ticket->update(['status' => $validated['status']]);

        return response()->json(['message' => 'Ticket status updated', 'ticket' => $ticket]);
    }
}
