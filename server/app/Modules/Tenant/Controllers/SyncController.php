<?php

namespace App\Modules\Tenant\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SyncController extends Controller
{
    protected $allowedEntities = ['products', 'purchases', 'transactions'];

    /**
     * Pull Sync: Get records updated after a certain timestamp.
     */
    public function pull(Request $request)
    {
        $request->validate([
            'since' => 'required|date'
        ]);

        $since = Carbon::parse($request->query('since'));
        $businessId = $request->user()->business_id;
        
        $data = [];

        foreach ($this->allowedEntities as $entity) {
            $data[$entity] = DB::table($entity)
                ->where('business_id', $businessId)
                ->where('updated_at', '>', $since)
                ->get();
        }

        return response()->json([
            'success' => true,
            'timestamp' => now()->toIso8601String(),
            'data' => $data
        ]);
    }

    /**
     * Push Sync: Apply client updates, handle conflicts.
     */
    public function push(Request $request)
    {
        $request->validate([
            'sync_data' => 'required|array'
        ]);

        $syncData = $request->input('sync_data');
        $businessId = $request->user()->business_id;
        
        $conflicts = [];
        $successful = [];

        DB::beginTransaction();

        try {
            foreach ($syncData as $entity => $records) {
                if (!in_array($entity, $this->allowedEntities)) {
                    continue; // Skip unauthorized/unknown entities
                }

                foreach ($records as $record) {
                    if (!isset($record['id'])) continue;

                    // Lock the row for update to prevent concurrent modification
                    $serverRecord = DB::table($entity)
                        ->where('business_id', $businessId)
                        ->where('id', $record['id'])
                        ->lockForUpdate()
                        ->first();

                    if (!$serverRecord) {
                        // New record created offline
                        $record['business_id'] = $businessId;
                        $record['version'] = 1;
                        $record['created_at'] = now();
                        $record['updated_at'] = now();
                        DB::table($entity)->insert($record);
                        $successful[$entity][] = $record['id'];
                        continue;
                    }

                    $isConflict = false;
                    $conflictReason = '';

                    // Conflict resolution using Version tracking
                    if (isset($record['version']) && $record['version'] < $serverRecord->version) {
                        $isConflict = true;
                        $conflictReason = "Client version {$record['version']} is older than Server version {$serverRecord->version}";
                    } 
                    // Fallback to timestamp resolution if version isn't perfectly sequenced
                    else if (isset($record['updated_at']) && Carbon::parse($record['updated_at'])->lt(Carbon::parse($serverRecord->updated_at))) {
                         $isConflict = true;
                         $conflictReason = "Client updated_at is older than Server updated_at";
                    }

                    if ($isConflict) {
                        Log::warning("Sync Conflict on {$entity} #{$record['id']}: {$conflictReason}");
                        
                        // Server Wins Policy
                        $conflicts[$entity][] = [
                            'id' => $record['id'],
                            'server_version' => $serverRecord->version,
                            'server_record' => $serverRecord,
                            'message' => 'Server Wins: Your local record is outdated.'
                        ];
                        continue;
                    }

                    // Apply update
                    $updatePayload = $record;
                    unset($updatePayload['id']);
                    unset($updatePayload['business_id']); // Prevent hijacking business_id
                    
                    // Increment server version
                    $updatePayload['version'] = $serverRecord->version + 1;
                    $updatePayload['updated_at'] = now();

                    DB::table($entity)
                        ->where('business_id', $businessId)
                        ->where('id', $record['id'])
                        ->update($updatePayload);
                        
                    $successful[$entity][] = $record['id'];
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Push sync completed.',
                'successful_updates' => $successful,
                'conflicts' => $conflicts,
                'timestamp' => now()->toIso8601String(),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Sync Push Error: " . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Sync failed: ' . $e->getMessage()], 500);
        }
    }
}
