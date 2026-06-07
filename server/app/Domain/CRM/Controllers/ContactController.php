<?php

namespace App\Domain\CRM\Controllers;

use App\Domain\CRM\Models\Contact;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContactController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $type = $request->query('type');
        
        $query = Contact::query();
        
        if ($type === 'customer') {
            $query->customers();
        } elseif ($type === 'supplier') {
            $query->suppliers();
        }
        
        // Search by name, mobile, contact_id
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('mobile', 'like', "%{$search}%")
                  ->orWhere('contact_id', 'like', "%{$search}%");
            });
        }
        
        $paginator = $query->latest()->paginate(20);

        // Append basic ledger summary for each contact (can be optimized later)
        $contacts = collect($paginator->items())->map(function ($contact) use ($request) {
            $businessId = $request->user()->business_id;
            
            $totalSales = \Illuminate\Support\Facades\DB::table('transactions')
                ->where('contact_id', $contact->id)
                ->where('type', 'sell')
                ->where('status', 'final')
                ->sum('final_total');
                
            $totalReturns = \Illuminate\Support\Facades\DB::table('transactions')
                ->where('contact_id', $contact->id)
                ->where('type', 'sell_return')
                ->where('status', 'final')
                ->sum('final_total');
                
            $totalPayments = \Illuminate\Support\Facades\DB::table('transaction_payments')
                ->join('transactions', 'transaction_payments.transaction_id', '=', 'transactions.id')
                ->where('transactions.contact_id', $contact->id)
                ->sum('transaction_payments.amount');

            $openingBalance = $contact->opening_balance ?? 0;
            $totalDue = ($totalSales + $openingBalance) - $totalPayments - $totalReturns;

            $contact->total_sales = $totalSales;
            $contact->total_due = $totalDue;
            return $contact;
        });

        // We return the custom paginated structure
        return response()->json([
            'current_page' => $paginator->currentPage(),
            'data' => $contacts,
            'first_page_url' => $paginator->url(1),
            'from' => $paginator->firstItem(),
            'last_page' => $paginator->lastPage(),
            'last_page_url' => $paginator->url($paginator->lastPage()),
            'next_page_url' => $paginator->nextPageUrl(),
            'path' => $paginator->path(),
            'per_page' => $paginator->perPage(),
            'prev_page_url' => $paginator->previousPageUrl(),
            'to' => $paginator->lastItem(),
            'total' => $paginator->total(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['customer', 'supplier', 'both'])],
            'supplier_business_name' => 'nullable|string|max:255',
            'prefix' => 'nullable|string|max:255',
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'mobile' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
        ]);
        
        // Construct full name
        $nameParts = array_filter([$validated['prefix'] ?? null, $validated['first_name'], $validated['middle_name'] ?? null, $validated['last_name'] ?? null]);
        $validated['name'] = implode(' ', $nameParts);
        
        // Ensure business_id and created_by are set via the model's TenantModel trait or explicitly
        $validated['business_id'] = $request->user()->business_id;
        $validated['created_by'] = $request->user()->id;

        $contact = Contact::create($validated);

        return response()->json([
            'message' => 'Contact created successfully',
            'data' => $contact
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Contact $contact)
    {
        // TenantModel trait should auto-scope, but just to be sure
        return response()->json($contact);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Contact $contact)
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['customer', 'supplier', 'both'])],
            'supplier_business_name' => 'nullable|string|max:255',
            'prefix' => 'nullable|string|max:255',
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'mobile' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $nameParts = array_filter([$validated['prefix'] ?? null, $validated['first_name'], $validated['middle_name'] ?? null, $validated['last_name'] ?? null]);
        $validated['name'] = implode(' ', $nameParts);

        $contact->update($validated);

        return response()->json([
            'message' => 'Contact updated successfully',
            'data' => $contact
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Contact $contact)
    {
        $transactionsCount = \Illuminate\Support\Facades\DB::table('transactions')->where('contact_id', $contact->id)->count();
        if ($transactionsCount > 0) {
            return response()->json(['message' => 'Cannot delete contact with associated transactions.'], 422);
        }

        $contact->delete();

        return response()->json([
            'message' => 'Contact deleted successfully'
        ]);
    }
}
