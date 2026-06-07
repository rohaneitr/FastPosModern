<?php

namespace App\Domain\Sales\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RMAController extends Controller
{
    public function warrantyCheck(Request $request)
    {
        $identifier = $request->input('imei') ?? $request->input('serial');
        if (!$identifier) {
            throw ValidationException::withMessages(['imei' => 'Please provide an IMEI or Serial number']);
        }

        $businessId = $request->user()->business_id;

        $serial = DB::table('product_serials')
            ->where('business_id', $businessId)
            ->where('serial_number', $identifier)
            ->first();

        if (!$serial) {
            return response()->json(['message' => 'Serial/IMEI not found', 'status' => 'Not Found'], 404);
        }

        // Try to find the associated product
        $product = DB::table('products')->where('id', $serial->product_id)->first();
        
        // Find sale date
        $sale = DB::table('transaction_sell_lines')
            ->join('transactions', 'transaction_sell_lines.transaction_id', '=', 'transactions.id')
            ->where('transactions.business_id', $businessId)
            ->where('transaction_sell_lines.product_id', $serial->product_id)
            ->orderBy('transactions.transaction_date', 'desc')
            ->select('transactions.transaction_date')
            ->first();

        $saleDate = $sale ? $sale->transaction_date : null;
        $warrantyDuration = $product->warranty_days ?? 0;
        
        $daysRemaining = 0;
        $status = 'Expired';
        
        if ($saleDate && $warrantyDuration > 0) {
            $saleCarbon = \Carbon\Carbon::parse($saleDate);
            $expiryDate = $saleCarbon->copy()->addDays($warrantyDuration);
            $daysRemaining = \Carbon\Carbon::now()->diffInDays($expiryDate, false);
            if ($daysRemaining > 0) {
                $status = 'Valid';
            } else {
                $daysRemaining = 0;
            }
        }

        return response()->json([
            'serial_number' => $serial->serial_number,
            'product_name' => $product->name ?? 'Unknown',
            'sale_date' => $saleDate,
            'warranty_days' => $warrantyDuration,
            'days_remaining' => ceil($daysRemaining),
            'status' => $status
        ]);
    }

    public function index(Request $request)
    {
        $tickets = DB::table('rma_tickets')
            ->where('rma_tickets.business_id', $request->user()->business_id)
            ->leftJoin('products', 'rma_tickets.product_id', '=', 'products.id')
            ->leftJoin('contacts', 'rma_tickets.customer_id', '=', 'contacts.id')
            ->select('rma_tickets.*', 'products.name as product_name', 'contacts.name as customer_name')
            ->orderBy('rma_tickets.created_at', 'desc')
            ->get();
            
        return response()->json(['data' => $tickets]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|integer',
            'customer_id' => 'nullable|integer',
            'serial_number' => 'nullable|string',
            'complaint' => 'required|string'
        ]);

        $id = DB::table('rma_tickets')->insertGetId([
            'business_id' => $request->user()->business_id,
            'product_id' => $request->input('product_id'),
            'customer_id' => $request->input('customer_id'),
            'serial_number' => $request->input('serial_number'),
            'complaint' => $request->input('complaint'),
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'RMA Ticket created successfully', 'id' => $id]);
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|string'
        ]);

        DB::table('rma_tickets')
            ->where('business_id', $request->user()->business_id)
            ->where('id', $id)
            ->update([
                'status' => $request->input('status'),
                'updated_at' => now()
            ]);

        return response()->json(['message' => 'RMA Status updated successfully']);
    }
}
