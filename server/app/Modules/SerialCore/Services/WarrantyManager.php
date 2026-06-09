<?php

namespace App\Modules\SerialCore\Services;

use Illuminate\Support\Facades\DB;
use App\Modules\SerialCore\Exceptions\WarrantyExpiredException;
use Carbon\Carbon;
use Exception;

class WarrantyManager
{
    /**
     * Verify if a serial number is currently under active warranty coverage.
     */
    public function verifyWarranty(string $serialNumber, int $businessId)
    {
        $serialInfo = DB::table('inventory_item_serials')
            ->join('products', 'inventory_item_serials.product_id', '=', 'products.id')
            ->leftJoin('transaction_sell_lines', 'inventory_item_serials.transaction_sell_line_id', '=', 'transaction_sell_lines.id')
            ->leftJoin('transactions', 'transaction_sell_lines.transaction_id', '=', 'transactions.id')
            ->where('inventory_item_serials.business_id', $businessId)
            ->where('inventory_item_serials.serial_number', $serialNumber)
            ->select(
                'inventory_item_serials.id',
                'inventory_item_serials.status',
                'products.warranty_months',
                'transactions.transaction_date'
            )
            ->first();

        if (!$serialInfo) {
            throw new Exception("Serial Number not found.", 404);
        }

        if ($serialInfo->status !== 'Sold') {
            throw new Exception("Serial Status is {$serialInfo->status}. Cannot claim warranty unless item is Sold.", 422);
        }

        if (!$serialInfo->transaction_date || !$serialInfo->warranty_months) {
            throw new Exception("Insufficient warranty configuration or missing transaction record.", 422);
        }

        $purchaseDate = Carbon::parse($serialInfo->transaction_date);
        $expiryDate = $purchaseDate->copy()->addMonths($serialInfo->warranty_months);

        if (now()->greaterThan($expiryDate)) {
            throw new WarrantyExpiredException("Warranty expired on {$expiryDate->toDateString()}");
        }

        return [
            'status' => 'Valid',
            'purchase_date' => $purchaseDate->toDateString(),
            'expiry_date' => $expiryDate->toDateString(),
            'serial_id' => $serialInfo->id
        ];
    }

    /**
     * Atomic Replacement Swap Engine
     */
    public function swapReplacementSerial(string $oldSerialStr, string $newSerialStr, int $businessId)
    {
        return DB::transaction(function () use ($oldSerialStr, $newSerialStr, $businessId) {
            // 1. Lock and invalidate old serial
            $oldSerial = DB::table('inventory_item_serials')
                ->where('business_id', $businessId)
                ->where('serial_number', $oldSerialStr)
                ->lockForUpdate()
                ->first();

            if (!$oldSerial) {
                throw new Exception("Old serial not found.");
            }

            if ($oldSerial->status !== 'Sold') {
                throw new Exception("Cannot swap serial not marked as Sold.");
            }

            DB::table('inventory_item_serials')
                ->where('id', $oldSerial->id)
                ->update(['status' => 'Defective_Returned', 'updated_at' => now()]);

            // 2. Register and assign new serial to old transaction trail
            // Ensure new serial exists in stock
            $newSerial = DB::table('inventory_item_serials')
                ->where('business_id', $businessId)
                ->where('serial_number', $newSerialStr)
                ->lockForUpdate()
                ->first();

            if (!$newSerial || $newSerial->status !== 'In_Stock') {
                throw new Exception("New serial must be In_Stock to execute swap.");
            }

            DB::table('inventory_item_serials')
                ->where('id', $newSerial->id)
                ->update([
                    'status' => 'Sold',
                    'transaction_sell_line_id' => $oldSerial->transaction_sell_line_id,
                    'updated_at' => now()
                ]);

            return true;
        });
    }
}
