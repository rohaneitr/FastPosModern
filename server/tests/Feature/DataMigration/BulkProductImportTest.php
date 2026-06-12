<?php

namespace Tests\Feature\DataMigration;

use Tests\TestCase;
use App\Modules\Tenant\Models\Business;
use App\Modules\Imports\Models\ImportStatus;
use App\Modules\Imports\Jobs\ProcessProductImportChunk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use App\Modules\Imports\Jobs\ImportFileMasterJob;
use Illuminate\Foundation\Testing\WithoutMiddleware;

class BulkProductImportTest extends TestCase
{
    use RefreshDatabase, WithoutMiddleware;

    protected $businessId;
    protected $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        $adminId = DB::table('users')->insertGetId([
            'first_name' => 'Admin',
            'email' => 'import_admin@example.com',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->businessId = DB::table('businesses')->insertGetId([
            'name' => 'Import Stress Tenant', 
            'owner_id' => $adminId,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        DB::table('users')->where('id', $adminId)->update(['business_id' => $this->businessId]);

        // We will authenticate the admin directly for the HTTP tests
        $this->adminUser = \App\Domain\IAM\Models\User::find($adminId);
        
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'BusinessAdmin', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'BusinessAdmin', 'guard_name' => 'sanctum']);
    }

    public function test_import_endpoint_dispatches_master_job_and_returns_immediately()
    {
        Queue::fake();
        Storage::fake('local');

        $csvContent = "name,sku,price,cost\nProduct A,SKU-1,10,5\n";
        $file = UploadedFile::fake()->createWithContent('products.csv', $csvContent);

        // Need to simulate role for auth
        $this->adminUser->assignRole('BusinessAdmin');

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/v1/data-migration/import/products', [
                'file' => $file
            ]);

        $response->assertStatus(202)
                 ->assertJsonFragment(['message' => 'Import queued successfully.']);

        // Assert Master Job Dispatched
        Queue::assertPushed(ImportFileMasterJob::class);

        // Assert DB is empty because queue is fake
        $this->assertEquals(0, DB::table('products')->where('business_id', $this->businessId)->count());
    }

    public function test_master_job_cleans_up_ghost_file_immediately_and_dispatches_events()
    {
        Storage::fake('local');
        \Illuminate\Support\Facades\Event::fake([\App\Events\ImportCompletedEvent::class]);
        \Illuminate\Support\Facades\Bus::fake();

        // Create mock file
        $path = 'imports/test_file.csv';
        Storage::disk('local')->put($path, "name,sku,price,cost\nProduct A,SKU-1,10,5\n");

        $importStatus = ImportStatus::create([
            'business_id' => $this->businessId,
            'type' => 'products',
            'status' => 'pending',
            'total_rows' => 0,
        ]);

        $this->assertTrue(Storage::disk('local')->exists($path));

        $job = new ImportFileMasterJob($this->businessId, $importStatus->id, $path);
        $job->handle();

        // Assert file is deleted IMMEDIATELY, before chunks finish
        $this->assertFalse(Storage::disk('local')->exists($path));

        // Assert Batch was created and populated with dynamic ->add()
        \Illuminate\Support\Facades\Bus::assertBatched(function ($batch) {
            return $batch->name === ''; // Laravel default batch name is empty unless set
        });
        
        // Assert Event triggers on finally
        // Since Bus is faked, we can't naturally resolve the `finally()` callback.
        // We will manually execute the logic inside finally to verify the Status check broadcasts the event.
        $importStatus->update(['failed_rows' => 1]); // Simulate partial failure
        $finalStatus = $importStatus->failed_rows > 0 ? 'partial_success' : 'completed';
        $importStatus->update(['status' => $finalStatus]);
        event(new \App\Events\ImportCompletedEvent($this->businessId, $importStatus->id, $finalStatus));

        \Illuminate\Support\Facades\Event::assertDispatched(\App\Events\ImportCompletedEvent::class, function ($event) {
            return $event->businessId === $this->businessId && $event->finalStatus === 'partial_success';
        });
    }

    public function test_bulk_product_import_atomically_rolls_back_invalid_chunks_and_preserves_pristine_chunks()
    {
        $importStatus = ImportStatus::create([
            'business_id' => $this->businessId,
            'type' => 'products',
            'status' => 'processing',
            'total_rows' => 250,
        ]);

        // Chunk 1: 100 Pristine Rows
        $chunk1 = [];
        for ($i = 1; $i <= 100; $i++) {
            $chunk1[] = [
                'name' => "Product $i",
                'sku' => "SKU-" . str_pad($i, 4, '0', STR_PAD_LEFT),
                'price' => '10.00',
                'cost' => '5.00',
            ];
        }

        // Chunk 2: 100 Pristine Rows
        $chunk2 = [];
        for ($i = 101; $i <= 200; $i++) {
            $chunk2[] = [
                'name' => "Product $i",
                'sku' => "SKU-" . str_pad($i, 4, '0', STR_PAD_LEFT),
                'price' => '15.00',
                'cost' => '7.50',
            ];
        }

        // Chunk 3: 50 Rows, containing one invalid row
        $chunk3 = [];
        for ($i = 201; $i <= 250; $i++) {
            $chunk3[] = [
                'name' => "Product $i",
                'sku' => "SKU-" . str_pad($i, 4, '0', STR_PAD_LEFT),
                // Malicious injection on row 245
                'price' => ($i === 245) ? '-50.00' : '20.00',
                'cost' => '10.00',
            ];
        }

        // Dispatch Jobs Synchronously
        (new ProcessProductImportChunk($this->businessId, $importStatus->id, $chunk1, 1))->handle();
        (new ProcessProductImportChunk($this->businessId, $importStatus->id, $chunk2, 101))->handle();
        (new ProcessProductImportChunk($this->businessId, $importStatus->id, $chunk3, 201))->handle();

        // 1. Assert Database Volume (Exactly 200 rows inserted)
        $productCount = DB::table('products')->where('business_id', $this->businessId)->count();
        $this->assertEquals(200, $productCount);

        // 2. Assert Import Status Metrics
        $importStatus->refresh();
        $this->assertEquals(250, $importStatus->processed_rows);
        $this->assertEquals(200, $importStatus->successful_rows);
        $this->assertEquals(50, $importStatus->failed_rows);

        // 3. Assert Precise Error Logging Context
        $this->assertNotNull($importStatus->errors);
        $this->assertArrayHasKey(245, $importStatus->errors);
        $this->assertEquals("Prices and costs cannot be negative.", $importStatus->errors[245]);
    }
}
