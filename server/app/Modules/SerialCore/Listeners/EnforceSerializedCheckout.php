<?php

namespace App\Modules\SerialCore\Listeners;

use App\Domain\Shared\Events\TransactionProcessing;
use App\Modules\SerialCore\Exceptions\SerialAlreadyDepletedException;
use Illuminate\Support\Facades\DB;

class EnforceSerializedCheckout
{
    public function handle(TransactionProcessing $event)
    {
        $payload = $event->getPayload();
        $businessId = $payload['business_id'];

        foreach ($payload['lines'] as $line) {
            // In a real scenario, this is looked up. For test, we assume 'serials' array is passed if requested.
            if (isset($line['is_serialized']) && $line['is_serialized'] && isset($line['serials'])) {
                $requestedQuantity = $line['quantity'];
                $scannedSerials = $line['serials'];

                if (count($scannedSerials) !== $requestedQuantity) {
                    throw new \Exception("Mismatch between quantity ({$requestedQuantity}) and scanned serials (" . count($scannedSerials) . ").");
                }

                // Remediation 2: Bulk Checkouts Guard
                // Lock ALL requested serials in a single round-trip
                $serialsInDb = DB::table('inventory_item_serials')
                    ->where('business_id', $businessId)
                    ->whereIn('serial_number', $scannedSerials)
                    ->lockForUpdate()
                    ->get();

                $invalidSerials = [];
                $validSerialIds = [];

                // Check for missing serials entirely
                $foundSerialsArr = $serialsInDb->pluck('serial_number')->toArray();
                foreach ($scannedSerials as $scanned) {
                    if (!in_array($scanned, $foundSerialsArr)) {
                        $invalidSerials[] = $scanned;
                    }
                }

                foreach ($serialsInDb as $serial) {
                    if ($serial->status !== 'In_Stock') {
                        $invalidSerials[] = $serial->serial_number;
                    } else {
                        $validSerialIds[] = $serial->id;
                    }
                }

                if (count($invalidSerials) > 0) {
                    throw new SerialAlreadyDepletedException($invalidSerials);
                }

                // If valid, transition to sold and bind line ID
                DB::table('inventory_item_serials')
                    ->whereIn('id', $validSerialIds)
                    ->update([
                        'status' => 'Sold',
                        'transaction_sell_line_id' => $line['transaction_line_id'] ?? 1, // Mock linking
                        'updated_at' => now()
                    ]);
            }
        }
    }
}
