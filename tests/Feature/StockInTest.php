<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Country;
use App\Models\District;
use App\Models\ItemType;
use App\Models\ItemVariety;
use App\Models\Province;
use App\Models\StockInBatch;
use App\Models\StockBag;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockInTest extends TestCase
{
    use RefreshDatabase;

    private $user;
    private $warehouse;
    private $supplier;
    private $itemType;
    private $itemVariety;

    protected function setUp(): void
    {
        parent::setUp();

        // Run seeders for SQLite in-memory testing DB
        $this->seed(\Database\Seeders\PermissionsSeeder::class);
        $this->seed(\Database\Seeders\UserSeeder::class);

        // Find the seeded user and authenticate
        $this->user = User::where('email', 'dev@localhost.com')->first();
        $token = auth('api')->login($this->user);
        $this->withHeader('Authorization', 'Bearer ' . $token);

        // Setup master data
        $country = Country::create([
            'name' => 'Sri Lanka',
            'code' => 'SL',
            'is_active' => true,
        ]);

        $province = Province::create([
            'name' => 'Southern',
            'code' => 'SP',
            'country_id' => $country->id,
            'is_active' => true,
        ]);

        $district = District::create([
            'name' => 'Matara',
            'code' => 'MH',
            'province_id' => $province->id,
            'is_active' => true,
        ]);

        $branch = Branch::create([
            'province_id' => $province->id,
            'district_id' => $district->id,
            'name' => 'Colombo Main',
            'code' => 'CM-001',
            'address_line1' => 'Galle Rd',
            'city' => 'Colombo',
            'phone_primary' => '0112223334',
            'opening_date' => '2026-01-01',
            'branch_type' => 'main',
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::create([
            'branch_id' => $branch->id,
            'name' => 'Warehouse Alpha',
            'code' => 'WH-ALP',
            'phone_primary' => '0112223335',
            'address_line1' => 'Duplication Road',
            'city' => 'Colombo',
            'is_active' => true,
        ]);

        $this->supplier = Supplier::create([
            'code' => 'SUP-001',
            'name' => 'Supplier One',
            'phone_primary' => '1234567890',
            'address_line1' => 'Galle Road',
            'city' => 'Matara',
            'country_id' => $country->id,
            'district_id' => $district->id,
            'is_active' => true,
        ]);

        $this->itemType = ItemType::create([
            'name' => 'Paddy',
            'code' => 'PDY',
            'description' => 'Paddy grain',
            'is_active' => true,
        ]);

        $this->itemVariety = ItemVariety::create([
            'item_type_id' => $this->itemType->id,
            'name' => 'Samba',
            'code' => 'PDY-SAM',
            'is_active' => true,
        ]);
    }

    /**
     * Test creating a supplier stock-in batch.
     */
    public function test_can_create_supplier_stock_in()
    {
        $payload = [
            'type' => 'supplier',
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'received_date' => '2026-08-10',
            'status' => 'pending',
            'items' => [
                [
                    'item_type_id' => $this->itemType->id,
                    'item_variety_id' => $this->itemVariety->id,
                    'quantity_bags' => 10,
                    'unit_weight' => 50.00,
                    'unit_price' => 100.00,
                ]
            ]
        ];

        $response = $this->postJson('/api/v1/stock-ins', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'message' => 'Stock in batch created successfully'
            ]);

        $this->assertDatabaseHas('stock_in_batches', [
            'type' => 'supplier',
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'total_bags' => 10,
        ]);
    }

    /**
     * Test creating a direct stock-in batch which creates nested bags.
     */
    public function test_can_create_direct_stock_in_with_bags()
    {
        $payload = [
            'type' => 'direct',
            'warehouse_id' => $this->warehouse->id,
            'received_date' => '2026-08-10',
            'vehicle_number' => 'WP-CAB-1234',
            'vehicle_type' => 'lorry',
            'driver_name' => 'John Doe',
            'items' => [
                [
                    'item_type_id' => $this->itemType->id,
                    'item_variety_id' => $this->itemVariety->id,
                    'unit_price' => 120.00,
                    'bags' => [
                        [
                            'bag_weight' => 45.50,
                            'location_id' => 'LOC-A1',
                        ],
                        [
                            'bag_weight' => 46.00,
                            'location_id' => 'LOC-A2',
                        ]
                    ]
                ]
            ]
        ];

        $response = $this->postJson('/api/v1/stock-ins', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'message' => 'Stock in batch created successfully'
            ]);

        $this->assertDatabaseHas('stock_in_batches', [
            'type' => 'direct',
            'supplier_id' => null,
            'total_bags' => 2,
            'net_weight' => 91.50, // 45.5 + 46.0
        ]);

        $this->assertDatabaseHas('stock_bags', [
            'bag_weight' => 45.50,
            'location_id' => 'LOC-A1',
        ]);
        $this->assertDatabaseHas('stock_bags', [
            'bag_weight' => 46.00,
            'location_id' => 'LOC-A2',
        ]);
    }

    /**
     * Test validation rules for supplier type.
     */
    public function test_supplier_stock_in_requires_supplier_id()
    {
        $payload = [
            'type' => 'supplier',
            'warehouse_id' => $this->warehouse->id,
            'received_date' => '2026-08-10',
            'items' => [
                [
                    'item_type_id' => $this->itemType->id,
                    'item_variety_id' => $this->itemVariety->id,
                    'quantity_bags' => 10,
                ]
            ]
        ];

        $response = $this->postJson('/api/v1/stock-ins', $payload);

        $response->assertStatus(422)
            ->assertJsonFragment([
                'field' => 'supplier_id'
            ]);
    }

    /**
     * Test validation rules for direct type.
     */
    public function test_direct_stock_in_requires_bags()
    {
        $payload = [
            'type' => 'direct',
            'warehouse_id' => $this->warehouse->id,
            'received_date' => '2026-08-10',
            'items' => [
                [
                    'item_type_id' => $this->itemType->id,
                    'item_variety_id' => $this->itemVariety->id,
                    'unit_price' => 120.00,
                ]
            ]
        ];

        $response = $this->postJson('/api/v1/stock-ins', $payload);

        $response->assertStatus(422)
            ->assertJsonFragment([
                'field' => 'items.0.bags'
            ]);
    }

    /**
     * Test listing stock-in batches.
     */
    public function test_can_get_stock_ins()
    {
        // Create one direct and one supplier batch
        StockInBatch::create([
            'type' => 'direct',
            'warehouse_id' => $this->warehouse->id,
            'received_date' => '2026-08-10',
            'status' => 'received',
            'net_weight' => 100,
            'total_bags' => 2,
            'total_amount' => 1500,
            'created_by' => $this->user->id,
        ]);

        StockInBatch::create([
            'type' => 'supplier',
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'received_date' => '2026-08-11',
            'status' => 'received',
            'net_weight' => 200,
            'total_bags' => 4,
            'total_amount' => 3000,
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/stock-ins');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data.data'));
    }

    /**
     * Test updating a direct stock-in.
     */
    public function test_can_update_direct_stock_in()
    {
        $batch = StockInBatch::create([
            'type' => 'direct',
            'warehouse_id' => $this->warehouse->id,
            'received_date' => '2026-08-10',
            'status' => 'received',
            'net_weight' => 50,
            'total_bags' => 1,
            'total_amount' => 100,
            'created_by' => $this->user->id,
        ]);

        $item = $batch->items()->create([
            'item_type_id' => $this->itemType->id,
            'item_variety_id' => $this->itemVariety->id,
            'quantity_bags' => 1,
            'unit_weight' => 50,
            'total_weight' => 50,
            'unit_price' => 2,
            'total_price' => 100,
            'remaining_quantity_bags' => 1,
            'remaining_weight' => 50,
        ]);

        $bag = StockBag::create([
            'stock_in_batch_id' => $batch->id,
            'stock_in_batch_item_id' => $item->id,
            'branch_id' => $this->warehouse->branch_id,
            'warehouse_id' => $this->warehouse->id,
            'item_type_id' => $this->itemType->id,
            'item_variety_id' => $this->itemVariety->id,
            'bag_weight' => 50.00,
            'unit_price' => 2.00,
            'selling_price' => 2.00,
            'status' => 'in_stock',
            'created_by' => $this->user->id,
        ]);

        $payload = [
            'type' => 'direct',
            'warehouse_id' => $this->warehouse->id,
            'received_date' => '2026-08-10',
            'items' => [
                [
                    'id' => $item->id,
                    'item_type_id' => $this->itemType->id,
                    'item_variety_id' => $this->itemVariety->id,
                    'unit_price' => 3.00,
                    'bags' => [
                        [
                            'id' => $bag->id,
                            'bag_weight' => 60.00, // Updated weight
                        ]
                    ]
                ]
            ]
        ];

        $response = $this->putJson("/api/v1/stock-ins/{$batch->id}", $payload);

        $response->assertStatus(200);

        $this->assertDatabaseHas('stock_bags', [
            'id' => $bag->id,
            'bag_weight' => 60.00,
            'unit_price' => 3.00,
        ]);
    }

    /**
     * Test that bags are eagerly loaded and returned in index, list, and show responses.
     */
    public function test_bags_are_included_in_responses()
    {
        $batch = StockInBatch::create([
            'type' => 'direct',
            'warehouse_id' => $this->warehouse->id,
            'received_date' => '2026-08-10',
            'status' => 'received',
            'net_weight' => 50,
            'total_bags' => 1,
            'total_amount' => 100,
            'created_by' => $this->user->id,
        ]);

        $item = $batch->items()->create([
            'item_type_id' => $this->itemType->id,
            'item_variety_id' => $this->itemVariety->id,
            'quantity_bags' => 1,
            'unit_weight' => 50,
            'total_weight' => 50,
            'unit_price' => 2,
            'total_price' => 100,
            'remaining_quantity_bags' => 1,
            'remaining_weight' => 50,
        ]);

        $bag = StockBag::create([
            'stock_in_batch_id' => $batch->id,
            'stock_in_batch_item_id' => $item->id,
            'branch_id' => $this->warehouse->branch_id,
            'warehouse_id' => $this->warehouse->id,
            'item_type_id' => $this->itemType->id,
            'item_variety_id' => $this->itemVariety->id,
            'bag_weight' => 50.00,
            'unit_price' => 2.00,
            'selling_price' => 2.00,
            'status' => 'in_stock',
            'created_by' => $this->user->id,
        ]);

        // 1. Verify show endpoint response structure contains bags & items.bags
        $responseShow = $this->getJson("/api/v1/stock-ins/{$batch->id}");
        $responseShow->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'bags',
                    'items' => [
                        '*' => [
                            'bags'
                        ]
                    ]
                ]
            ]);
        $this->assertNotEmpty($responseShow->json('data.bags'));
        $this->assertNotEmpty($responseShow->json('data.items.0.bags'));

        // 2. Verify index endpoint response structure contains bags & items.bags
        $responseIndex = $this->getJson('/api/v1/stock-ins');
        $responseIndex->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'data' => [
                        '*' => [
                            'bags',
                            'items' => [
                                '*' => [
                                    'bags'
                                ]
                            ]
                        ]
                    ]
                ]
            ]);

        // 3. Verify getActiveList (list) endpoint response structure contains bags & items.bags
        $responseList = $this->getJson('/api/v1/stock-ins/list');
        $responseList->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'bags',
                        'items' => [
                            '*' => [
                                'bags'
                            ]
                        ]
                    ]
                ]
            ]);
    }
}
