<?php

namespace App\Http\Controllers\Api\V1\DataMigration;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use App\Domain\Imports\Models\ImportStatus;
use App\Domain\Imports\Jobs\ProcessProductImportChunk;
use Illuminate\Validation\ValidationException;

class ImportController extends Controller
{
    public function importProducts(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240', // 10MB limit
        ]);

        $businessId = auth()->user()->business_id;
        $file = $request->file('file');
        
        // Securely store the file for background processing
        $path = $file->storeAs('imports', 'products_' . $businessId . '_' . time() . '.csv');
        $absolutePath = storage_path('app/' . $path);

        // Initialize Tracking Record
        $importStatus = ImportStatus::create([
            'business_id' => $businessId,
            'type' => 'products',
            'status' => 'pending',
            'total_rows' => 0, // Master job will calculate this
        ]);

        // Dispatch Master Job to handle everything asynchronously
        \App\Domain\Imports\Jobs\ImportFileMasterJob::dispatch($businessId, $importStatus->id, $path);

        return response()->json([
            'message' => 'Import queued successfully.',
            'import_id' => $importStatus->id,
            'total_rows' => 0 // Client polls for status
        ], 202);
    }

    public function getStatus($id)
    {
        $businessId = auth()->user()->business_id;

        $importStatus = ImportStatus::where('business_id', $businessId)
            ->where('id', $id)
            ->firstOrFail();

        // Dynamically check if completed
        if ($importStatus->status === 'processing') {
            if ($importStatus->processed_rows >= $importStatus->total_rows) {
                $status = ($importStatus->failed_rows > 0) ? 'partial_success' : 'completed';
                $importStatus->update(['status' => $status]);
            }
        }

        return response()->json([
            'data' => [
                'id' => $importStatus->id,
                'status' => $importStatus->status,
                'total_rows' => $importStatus->total_rows,
                'processed_rows' => $importStatus->processed_rows,
                'successful_rows' => $importStatus->successful_rows,
                'failed_rows' => $importStatus->failed_rows,
                'errors' => $importStatus->errors ?? (object)[],
            ]
        ]);
    }
}
