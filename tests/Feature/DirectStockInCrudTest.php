<?php

namespace Tests\Feature;

use App\Models\ItemType;
use App\Models\ItemVariety;
use App\Models\StockInBatch;
use App\Models\StockInBatchItem;
use App\Models\StockBag;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleLog;
use App\Models\Receipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DirectStockInCrudTest extends TestCase
{
    use RefreshDatabase;

    private $user;
    private $warehouse;
    private $itemType;
    private $itemVariety;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed permissions and user
        $this->seed(\Database\Seeders\PermissionsSeeder::class);
        $this->seed(\Database\Seeders\UserSeeder::class);

        // Find the seeded user and login
        $this->user = User::where('email', 'dev@localhost.com')->first();
        $token = auth('api')->login($this->user);
        $this->withHeader('Authorization', 'Bearer ' . $token);

        // Setup dependent models
        $country = \App\Models\Country::create(['name' => 'Sri Lanka', 'code' => 'LK', 'is_active' => true]);
        $province = \App\Models\Province::create(['name' => 'Western', 'code' => 'WP', 'country_id' => $country->id, 'is_active' => true]);
        $district = \App\Models\District::create(['name' => 'Colombo', 'code' => 'CMB', 'province_id' => $province->id, 'is_active' => true]);
        $branch = \App\Models\Branch::create([
            'name' => 'Colombo Branch',
            'code' => 'CMB01',
            'province_id' => $province->id,
            'district_id' => $district->id,
            'address_line1' => 'Address Line 1',
            'city' => 'Colombo',
            'phone_primary' => '+94112345678',
            'opening_date' => '2026-01-01',
            'branch_type' => 'main',
            'is_active' => true,
        ]);
        $this->warehouse = \App\Models\Warehouse::create([
            'name' => 'Main Warehouse',
            'code' => 'WH-001',
            'branch_id' => $branch->id,
            'phone_primary' => '+94112223333',
            'address_line1' => 'Address Line 1',
            'city' => 'Colombo',
            'is_active' => true,
        ]);

        $this->itemType = ItemType::create([
            'name' => 'Paddy',
            'code' => 'PDY',
            'description' => 'Paddy grains',
            'is_active' => true,
        ]);

        $this->itemVariety = ItemVariety::create([
            'name' => 'Samba Rice',
            'code' => 'SMB',
            'item_type_id' => $this->itemType->id,
            'is_active' => true,
        ]);
    }

    /**
     * Test retrieving direct stock in batches index.
     */
    public function test_can_list_direct_stock_in_batches()
    {
        // Create a direct batch and a non-direct batch
        StockInBatch::create([
            'type' => 'direct',
            'warehouse_id' => $this->warehouse->id,
            'received_date' => now()->format('Y-m-d'),
            'net_weight' => 100,
            'total_bags' => 2,
            'total_amount' => 500,
            'status' => 'received',
            'created_by' => $this->user->id,
        ]);

        StockInBatch::create([
            'type' => 'supplier',
            'warehouse_id' => $this->warehouse->id,
            'received_date' => now()->format('Y-m-d'),
            'net_weight' => 200,
            'total_bags' => 4,
            'total_amount' => 1000,
            'status' => 'received',
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/direct-stock-ins');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.net_weight', '100.00');
    }

    /**
     * Test creating a direct stock in.
     */
    public function test_can_create_direct_stock_in()
    {
        $this->withoutExceptionHandling();
        $payload = [
            'vehicle_number' => 'WP-CAB-9999',
            'vehicle_type' => 'lorry',
            'driver_name' => 'Ranil Silva',
            'driver_phone' => '+94775556666',
            'driver_nic' => '199012345678',
            'purpose' => 'Direct Intake Test',
            'vehicle_notes' => 'Some vehicle notes',
            'warehouse_id' => $this->warehouse->id,
            'received_date' => now()->format('Y-m-d'),
            'notes' => 'Main batch notes',
            'items' => [
                [
                    'item_type_id' => $this->itemType->id,
                    'item_variety_id' => $this->itemVariety->id,
                    'unit_price' => 150.00,
                    'notes' => 'Variety 1 notes',
                    'bags' => [
                        [
                            'bag_weight' => 50.25,
                            'location_id' => 'LOC-A1',
                            'notes' => 'Bag 1 notes',
                        ],
                        [
                            'bag_weight' => 49.75,
                            'location_id' => 'LOC-A2',
                            'notes' => 'Bag 2 notes',
                        ]
                    ]
                ]
            ]
        ];

        $response = $this->postJson('/api/v1/direct-stock-ins', $payload);
        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.type', 'direct')
            ->assertJsonPath('data.total_bags', 2)
            ->assertJsonPath('data.net_weight', '100.00')
            ->assertJsonPath('data.total_amount', '15000.00'); // 100 kg * 150 unit price = 15000

        // Verify DB insertions
        $this->assertDatabaseHas('vehicles', [
            'vehicle_number' => 'WP-CAB-9999',
            'vehicle_type' => 'lorry',
        ]);

        $this->assertDatabaseHas('vehicle_logs', [
            'driver_name' => 'Ranil Silva',
            'log_type' => 'stock_in',
        ]);

        $this->assertDatabaseHas('stock_in_batches', [
            'type' => 'direct',
            'warehouse_id' => $this->warehouse->id,
            'total_bags' => 2,
        ]);

        $this->assertDatabaseHas('stock_in_batch_items', [
            'item_variety_id' => $this->itemVariety->id,
            'quantity_bags' => 2,
            'total_weight' => 100,
        ]);

        $this->assertDatabaseHas('stock_bags', [
            'bag_weight' => 50.25,
            'location_id' => 'LOC-A1',
        ]);

        $this->assertDatabaseHas('stock_bags', [
            'bag_weight' => 49.75,
            'location_id' => 'LOC-A2',
        ]);

        $this->assertDatabaseHas('receipts', [
            'total_bags' => 2,
            'total_weight' => 100,
            'total_amount' => 15000.00,
        ]);
    }

    /**
     * Test showing direct stock in details.
     */
    public function test_can_show_direct_stock_in()
    {
        $batch = StockInBatch::create([
            'type' => 'direct',
            'warehouse_id' => $this->warehouse->id,
            'received_date' => now()->format('Y-m-d'),
            'net_weight' => 50,
            'total_bags' => 1,
            'total_amount' => 300,
            'status' => 'received',
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson("/api/v1/direct-stock-ins/{$batch->id}");

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.net_weight', '50.00');
    }

    /**
     * Test updating a direct stock in (adding/updating/deleting bags and items).
     */
    public function test_can_update_direct_stock_in()
    {
        // 1. Create initial direct stock-in
        $batch = StockInBatch::create([
            'type' => 'direct',
            'warehouse_id' => $this->warehouse->id,
            'received_date' => now()->format('Y-m-d'),
            'net_weight' => 100,
            'total_bags' => 2,
            'total_amount' => 1000,
            'status' => 'received',
            'created_by' => $this->user->id,
        ]);

        $item = $batch->items()->create([
            'item_type_id' => $this->itemType->id,
            'item_variety_id' => $this->itemVariety->id,
            'quantity_bags' => 2,
            'unit_weight' => 50,
            'total_weight' => 100,
            'unit_price' => 10,
            'total_price' => 1000,
            'remaining_quantity_bags' => 2,
            'remaining_weight' => 100,
        ]);

        $bag1 = StockBag::create([
            'stock_in_batch_id' => $batch->id,
            'stock_in_batch_item_id' => $item->id,
            'branch_id' => 1,
            'warehouse_id' => $this->warehouse->id,
            'item_type_id' => $this->itemType->id,
            'item_variety_id' => $this->itemVariety->id,
            'bag_weight' => 50.00,
            'unit_price' => 10,
            'selling_price' => 10,
            'status' => 'in_stock',
            'created_by' => $this->user->id,
        ]);

        $bag2 = StockBag::create([
            'stock_in_batch_id' => $batch->id,
            'stock_in_batch_item_id' => $item->id,
            'branch_id' => 1,
            'warehouse_id' => $this->warehouse->id,
            'item_type_id' => $this->itemType->id,
            'item_variety_id' => $this->itemVariety->id,
            'bag_weight' => 50.00,
            'unit_price' => 10,
            'selling_price' => 10,
            'status' => 'in_stock',
            'created_by' => $this->user->id,
        ]);

        $receipt = Receipt::create([
            'receipt_number' => 'RCP-UPDATE-TEST',
            'stock_in_batch_id' => $batch->id,
            'warehouse_id' => $this->warehouse->id,
            'receipt_date' => $batch->received_date,
            'total_bags' => 2,
            'total_weight' => 100,
            'total_amount' => 1000,
            'status' => 'pending',
            'created_by' => $this->user->id,
        ]);

        // 2. Perform Update: 
        // We will update the weight of bag1 (using its id), delete bag2 (by omitting its id), and add a new bag3.
        $payload = [
            'warehouse_id' => $this->warehouse->id,
            'received_date' => now()->format('Y-m-d'),
            'notes' => 'Updated notes',
            'items' => [
                [
                    'id' => $item->id,
                    'item_type_id' => $this->itemType->id,
                    'item_variety_id' => $this->itemVariety->id,
                    'unit_price' => 15.00, // Price updated from 10 to 15
                    'bags' => [
                        [
                            'id' => $bag1->id,
                            'bag_weight' => 55.00, // Weight updated from 50 to 55
                            'location_id' => 'LOC-UPDATED',
                        ],
                        [
                            // New bag added
                            'bag_weight' => 45.00,
                            'location_id' => 'LOC-NEW',
                        ]
                    ]
                ]
            ]
        ];

        $response = $this->putJson("/api/v1/direct-stock-ins/{$batch->id}", $payload);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.total_bags', 2)
            ->assertJsonPath('data.net_weight', '100.00') // 55 + 45 = 100
            ->assertJsonPath('data.total_amount', '1500.00'); // 100 kg * 15 unit price = 1500

        // Verify Database state
        $this->assertDatabaseHas('stock_bags', [
            'id' => $bag1->id,
            'bag_weight' => 55.00,
            'location_id' => 'LOC-UPDATED',
        ]);

        // Bag2 was deleted because it was omitted
        $this->assertDatabaseMissing('stock_bags', [
            'id' => $bag2->id,
        ]);

        // New bag was created
        $this->assertDatabaseHas('stock_bags', [
            'bag_weight' => 45.00,
            'location_id' => 'LOC-NEW',
        ]);

        // Receipt was updated
        $this->assertDatabaseHas('receipts', [
            'id' => $receipt->id,
            'total_amount' => 1500.00,
            'total_weight' => 100,
        ]);
    }
}
