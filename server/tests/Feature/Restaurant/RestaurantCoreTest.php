<?php

namespace Tests\Feature\Restaurant;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use App\Modules\Restaurant\Services\TableSessionManager;
use App\Modules\Restaurant\Exceptions\TableAlreadyOccupiedException;
use App\Modules\Restaurant\Jobs\PrintKitchenThermalTicketJob;

class RestaurantCoreTest extends TestCase
{
    use RefreshDatabase;

    protected int $businessId;
    protected int $tableId;

    protected function setUp(): void
    {
        parent::setUp();

        app()->register(\App\Modules\Restaurant\Providers\RestaurantServiceProvider::class);

        $this->businessId = DB::table('businesses')->insertGetId([
            'name'       => 'The Grand Bistro',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->tableId = DB::table('restaurant_tables')->insertGetId([
            'business_id'      => $this->businessId,
            'table_number'     => 'T-05',
            'seating_capacity' => 4,
            'status'           => 'Available',
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    public function test_double_seating_blockade_assert(): void
    {
        $manager = app(TableSessionManager::class);

        // First waiter opens the table — must succeed
        $sessionId = $manager->openSession($this->tableId, 1, $this->businessId);
        $this->assertIsInt($sessionId);

        // Assert table is now Occupied
        $this->assertEquals(
            'Occupied',
            DB::table('restaurant_tables')->where('id', $this->tableId)->value('status')
        );

        // Second waiter (or parallel request) attempts to open the same table
        $this->expectException(TableAlreadyOccupiedException::class);
        $manager->openSession($this->tableId, 2, $this->businessId);
    }

    public function test_kot_monotonic_sequence_and_json_parser(): void
    {
        Queue::fake();

        $manager = app(TableSessionManager::class);

        $sessionId = $manager->openSession($this->tableId, 1, $this->businessId);

        $items = [
            ['name' => 'Grilled Chicken', 'qty' => 2, 'modifier' => 'No Spicy'],
            ['name' => 'Lamb Chops',      'qty' => 1, 'modifier' => 'Medium Rare'],
            ['name' => 'Caesar Salad',    'qty' => 3, 'modifier' => 'Extra Cheese, No Croutons'],
        ];

        $ticketNumber = $manager->submitKot($sessionId, $this->businessId, $items);

        // Assert ticket format matches monotonic prefix
        $this->assertStringStartsWith('KOT-', $ticketNumber);

        // Assert ticket is persisted in DB
        $ticket = DB::table('restaurant_kot_tickets')
            ->where('ticket_number', $ticketNumber)
            ->first();

        $this->assertNotNull($ticket);
        $this->assertEquals('Pending', $ticket->status);

        // Assert JSON payload maps correctly
        $decoded = json_decode($ticket->item_payload, true);
        $this->assertCount(3, $decoded);
        $this->assertEquals('Grilled Chicken', $decoded[0]['name']);
        $this->assertEquals('Extra Cheese, No Croutons', $decoded[2]['modifier']);

        // Assert thermal print job was dispatched to Redis queue
        Queue::assertPushedOn('kitchen_print', PrintKitchenThermalTicketJob::class);

        // Assert second KOT is sequential
        $ticketNumber2 = $manager->submitKot($sessionId, $this->businessId, [
            ['name' => 'Tiramisu', 'qty' => 1, 'modifier' => '']
        ]);

        [$prefix1, $date1, $seq1] = explode('-', $ticketNumber);
        [$prefix2, $date2, $seq2] = explode('-', $ticketNumber2);

        $this->assertEquals((int) $seq1 + 1, (int) $seq2);
    }
}
