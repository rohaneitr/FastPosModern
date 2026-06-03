<?php

namespace App\Domain\Sales\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdvancedSalesController extends Controller
{
    /**
     * Get all Sales (filtered by status)
     */
    public function index(Request $request)
    {
        $status = $request->query('status'); // final, draft, quotation
        
        $query = DB::table('transactions')
            ->leftJoin('contacts', 'transactions.contact_id', '=', 'contacts.id')
            ->where('transactions.business_id', $request->user()->business_id)
            ->where('transactions.type', 'sell');

        if ($status === 'quotation') {
            $query->where('is_quotation', true);
        } elseif ($status) {
            $query->where('status', $status)->where('is_quotation', false);
        }

        $sales = $query->select(
            'transactions.*', 
            'contacts.name as customer_name'
        )
        ->orderBy('transaction_date', 'desc')
        ->paginate(20);

        return response()->json($sales);
    }

    /**
     * Process a Sell Return
     */
    public function sellReturn(Request $request)
    {
        $businessId = $request->user()->business_id;

        $validated = $request->validate([
            'transaction_id' => [
                'required',
                Rule::exists('transactions', 'id')->where('business_id', $businessId)
            ],
            'return_amount' => 'required|numeric|min:0',
            'lines' => 'required|array', // Array of product_id and qty to return
        ]);

        try {
            DB::beginTransaction();

            $original = DB::table('transactions')
                ->where('id', $validated['transaction_id'])
                ->where('business_id', $businessId)
                ->first();

            if (!$original) {
                return response()->json(['message' => 'Transaction not found or access denied'], 404);
            }

            // Create Return Transaction
            $returnTxId = DB::table('transactions')->insertGetId([
                'business_id' => $request->user()->business_id,
                'location_id' => $original->location_id,
                'type' => 'sell_return',
                'status' => 'final',
                'contact_id' => $original->contact_id,
                'return_parent_id' => $original->id,
                'total_before_tax' => $validated['return_amount'],
                'tax_amount' => 0,
                'final_total' => $validated['return_amount'],
                'transaction_date' => now(),
                'created_by' => $request->user()->id,
                'created_at' => now(),
            ]);

            // Restore Inventory and create return lines
            foreach ($validated['lines'] as $line) {
                DB::table('transaction_lines')->insert([
                    'transaction_id' => $returnTxId,
                    'product_id' => $line['product_id'],
                    'quantity' => $line['quantity'],
                    'unit_price' => 0,
                    'unit_price_inc_tax' => 0,
                    'item_tax' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('product_stocks')
                    ->where('product_id', $line['product_id'])
                    ->where('location_id', $original->location_id)
                    ->increment('qty_available', $line['quantity']);
            }

            DB::commit();
            return response()->json(['message' => 'Sell return processed successfully', 'return_id' => $returnTxId]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to process return', 'error' => $e->getMessage()], 500);
        }
    }
}
