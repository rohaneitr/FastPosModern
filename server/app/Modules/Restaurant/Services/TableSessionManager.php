<?php

namespace App\Modules\Restaurant\Services;

use Illuminate\Support\Facades\DB;
use App\Modules\Restaurant\Exceptions\TableAlreadyOccupiedException;
use App\Domain\Shared\Events\KotTicketEmitted;
use Carbon\Carbon;

class TableSessionManager
{
    /**
     * Atomically open a session on a table with a pessimistic lock.
     * Returns the new session ID.
     */
    public function openSession(int $tableId, int $waiterId, int $businessId): int
    {
        return DB::transaction(function () use ($tableId, $waiterId, $businessId) {
            $table = DB::table('restaurant_tables')
                ->where('id', $tableId)
                ->where('business_id', $businessId)
                ->lockForUpdate()
                ->first();

            if (!$table) {
                throw new \Exception("Table not found.", 404);
            }

            if ($table->status !== 'Available') {
                throw new TableAlreadyOccupiedException(
                    "Table #{$table->table_number} is currently {$table->status}."
                );
            }

            // Transition table status
            DB::table('restaurant_tables')
                ->where('id', $tableId)
                ->update(['status' => 'Occupied', 'updated_at' => now()]);

            // Create session
            return DB::table('restaurant_sessions')->insertGetId([
                'business_id' => $businessId,
                'table_id'    => $tableId,
                'waiter_id'   => $waiterId,
                'status'      => 'Active',
                'started_at'  => now(),
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        });
    }

    /**
     * Submit a KOT ticket for a session, dispatch to Event Bus + KDS.
     */
    public function submitKot(int $sessionId, int $businessId, array $items): string
    {
        $ticketNumber = $this->generateMonotonicTicketNumber($businessId);

        DB::table('restaurant_kot_tickets')->insert([
            'session_id'    => $sessionId,
            'ticket_number' => $ticketNumber,
            'status'        => 'Pending',
            'item_payload'  => json_encode($items),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        // Fire event to bus (KDS WebSocket + thermal printer queue)
        event(new KotTicketEmitted($businessId, $sessionId, $ticketNumber, $items));

        return $ticketNumber;
    }

    /**
     * Settle a session and free the table.
     */
    public function settleSession(int $sessionId, int $businessId): void
    {
        DB::transaction(function () use ($sessionId, $businessId) {
            $session = DB::table('restaurant_sessions')
                ->where('id', $sessionId)
                ->where('business_id', $businessId)
                ->lockForUpdate()
                ->first();

            if (!$session || $session->status !== 'Active') {
                throw new \Exception("Session is not active.", 422);
            }

            DB::table('restaurant_sessions')
                ->where('id', $sessionId)
                ->update(['status' => 'Settled', 'ended_at' => now(), 'updated_at' => now()]);

            DB::table('restaurant_tables')
                ->where('id', $session->table_id)
                ->update(['status' => 'Available', 'updated_at' => now()]);
        });
    }

    /**
     * Generate a monotonically increasing ticket number per business per day.
     * Format: KOT-YYYYMMDD-N (e.g., KOT-20260608-1, KOT-20260608-2)
     */
    private function generateMonotonicTicketNumber(int $businessId): string
    {
        $today  = Carbon::today()->toDateString();
        $prefix = 'KOT-' . Carbon::today()->format('Ymd') . '-';

        $lastTicket = DB::table('restaurant_kot_tickets')
            ->join('restaurant_sessions', 'restaurant_kot_tickets.session_id', '=', 'restaurant_sessions.id')
            ->where('restaurant_sessions.business_id', $businessId)
            ->whereDate('restaurant_kot_tickets.created_at', $today)
            ->orderBy('restaurant_kot_tickets.id', 'desc')
            ->value('ticket_number');

        if (!$lastTicket) {
            return $prefix . '1';
        }

        $parts = explode('-', $lastTicket);
        $seq   = (int) end($parts);

        return $prefix . ($seq + 1);
    }
}
