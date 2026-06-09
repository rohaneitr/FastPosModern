<?php

namespace App\Modules\Procurement\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Procurement\Models\Contact;
use App\Modules\Procurement\Requests\StoreSupplierRequest;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index()
    {
        try {
            $suppliers = Contact::where('type', 'supplier')->latest()->get();
            return response()->json(['data' => $suppliers]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Failed to fetch suppliers', 'error' => $e->getMessage()], 500);
        }
    }

    public function store(StoreSupplierRequest $request)
    {
        try {
            $data = $request->validated();
            $data['type'] = 'supplier'; // Force type
            $data['business_id'] = auth()->user()->business_id;

            $supplier = Contact::create($data);

            return response()->json([
                'message' => 'Supplier created successfully',
                'data' => $supplier
            ], 201);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Failed to create supplier', 'error' => $e->getMessage()], 500);
        }
    }

    public function show(Contact $supplier)
    {
        try {
            // Optional: verify it is a supplier
            if ($supplier->type !== 'supplier') {
                return response()->json(['message' => 'Contact is not a supplier'], 404);
            }

            return response()->json(['data' => $supplier]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Failed to fetch supplier', 'error' => $e->getMessage()], 500);
        }
    }

    public function update(StoreSupplierRequest $request, Contact $supplier)
    {
        try {
            if ($supplier->type !== 'supplier') {
                return response()->json(['message' => 'Contact is not a supplier'], 404);
            }

            $data = $request->validated();
            // Don't overwrite type
            unset($data['type']);
            
            $supplier->update($data);

            return response()->json([
                'message' => 'Supplier updated successfully',
                'data' => $supplier
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Failed to update supplier', 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy(Contact $supplier)
    {
        try {
            if ($supplier->type !== 'supplier') {
                return response()->json(['message' => 'Contact is not a supplier'], 404);
            }

            $supplier->delete();

            return response()->json(['message' => 'Supplier deleted successfully']);
        } catch (\PDOException $e) {
            if ($e->getCode() == 23000) {
                return response()->json(['message' => 'Cannot delete supplier because it is linked to other records.'], 409);
            }
            return response()->json(['message' => 'Failed to delete supplier', 'error' => $e->getMessage()], 500);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Failed to delete supplier', 'error' => $e->getMessage()], 500);
        }
    }
}
