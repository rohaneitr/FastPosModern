<?php

namespace Tests\Feature\Security;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use App\Modules\Tenant\Models\Business;
use App\Domain\IAM\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Exception;
use Illuminate\Database\QueryException;

class AuditTrailImmutabilityTest extends TestCase
{
    /**
     * Because we are testing PostgreSQL rules (not standard schemas), 
     * we cannot easily use standard SQLite in-memory or RefreshDatabase 
     * if the rule was only added to the real DB.
     * We will just use the real DB connection for this specific forensic test.
     */
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_audit_logs_are_immutable_against_raw_sql_deletes()
    {
        // 1. Insert a mock audit log directly to bypass any service
        $logId = DB::table('audit_logs')->insertGetId([
            'business_id' => 1,
            'causer_id' => 1,
            'event' => 'tamper_test',
            'action' => 'test_immutability',
            'description' => 'Test',
            'created_at' => now(),
        ]);

        $this->assertDatabaseHas('audit_logs', ['id' => $logId]);

        // 2. Attempt to DELETE the record via raw DB query builder
        // The PostgreSQL RULE should intercept this and DO NOTHING.
        // It won't throw an error in Postgres if DO INSTEAD NOTHING is used, 
        // it just returns 0 rows affected.
        
        $deletedCount = DB::table('audit_logs')->where('id', $logId)->delete();

        // 3. Assert that the record still exists (Immutability proven)
        $this->assertDatabaseHas('audit_logs', ['id' => $logId]);
        
        // Assert that the delete query claimed to delete 0 rows (because of the RULE)
        $this->assertEquals(0, $deletedCount);
    }
    
    public function test_audit_logs_are_immutable_against_raw_sql_updates()
    {
        // 1. Insert a mock audit log
        $logId = DB::table('audit_logs')->insertGetId([
            'business_id' => 1,
            'causer_id' => 1,
            'event' => 'tamper_test',
            'action' => 'test_immutability',
            'description' => 'Original Description',
            'created_at' => now(),
        ]);

        // 2. Attempt to UPDATE the record
        $updatedCount = DB::table('audit_logs')->where('id', $logId)->update([
            'description' => 'Hacked Description'
        ]);

        // 3. Assert that the record was NOT updated
        $this->assertDatabaseHas('audit_logs', [
            'id' => $logId,
            'description' => 'Original Description' // Value should not change
        ]);
        
        $this->assertDatabaseMissing('audit_logs', [
            'id' => $logId,
            'description' => 'Hacked Description'
        ]);
        
        $this->assertEquals(0, $updatedCount);
    }
}
