<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Country;
use App\Models\District;
use App\Models\ItemType;
use App\Models\ItemVariety;
use App\Models\Province;
use App\Models\StockBag;
use App\Models\StockInBatch;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemVarietyReportTest extends TestCase
{
    use RefreshDatabase;

    private $user;
    private $branch;
    private $warehouse;
    private $supplier;
    private $itemType;
    private $variety1;
    private $variety2;
    private $batch1;
    private $batch2;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed permissions and user
        $this->seed(\Database\Seeders\PermissionsSeeder::class);
        $this->seed(\Database\Seeders\UserSeeder::class);

        $this->user = User::where('email', 'dev@localhost.com')->first();
        $token = auth('api')->login($this->user);
        $this->withHeader('Authorization', 'Bearer ' . $token);

        // Setup master locations
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

        $this->branch = Branch::create([
            'province_id' => $province->id,
            'district_id' => $district->id,
            'name' => 'Main Branch',
            'code' => 'BR-01',
            'address_line1' => 'Main Street',
            'city' => 'Matara',
            'phone_primary' => '0412233445',
            'opening_date' => '2026-01-01',
            'branch_type' => 'main',
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::create([
            'branch_id' => $this->branch->id,
            'name' => 'Main Warehouse',
            'code' => 'WH-01',
            'phone_primary' => '0112223335',
            'address_line1' => 'Duplication Road',
            'city' => 'Colombo',
            'capacity' => 10000,
            'is_active' => true,
        ]);

        $this->supplier = Supplier::create([
            'code' => 'SUP-01',
            'name' => 'Supplier One',
            'phone_primary' => '1234567890',
            'address_line1' => 'Galle Road',
            'city' => 'Matara',
            'country_id' => $country->id,
            'district_id' => $district->id,
            'is_active' => true,
        ]);

        // Seed Item Type & Varieties
        $this->itemType = ItemType::create([
            'name' => 'Paddy',
            'code' => 'PDY',
            'description' => 'Paddy grains',
            'is_active' => true,
        ]);

        $this->variety1 = ItemVariety::create([
            'item_type_id' => $this->itemType->id,
            'name' => 'Samba',
            'code' => 'PDY-SAM',
            'is_active' => true,
        ]);

        $this->variety2 = ItemVariety::create([
            'item_type_id' => $this->itemType->id,
            'name' => 'Nadu',
            'code' => 'PDY-NAD',
            'is_active' => true,
        ]);

        // Seed Batches
        $this->batch1 = StockInBatch::create([
            'batch_number' => 'BAT-001',
            'branch_id' => $this->branch->id,
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'received_date' => '2026-08-15',
            'status' => 'completed',
            'total_bags' => 3,
            'net_weight' => 150,
            'total_amount' => 15000,
            'type' => 'direct',
            'created_by' => $this->user->id,
        ]);

        $this->batch2 = StockInBatch::create([
            'batch_number' => 'BAT-002',
            'branch_id' => $this->branch->id,
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'received_date' => '2026-08-20',
            'status' => 'completed',
            'total_bags' => 2,
            'net_weight' => 100,
            'total_amount' => 10000,
            'type' => 'direct',
            'created_by' => $this->user->id,
        ]);

        // Variety 1 Bags: 2 bags in Batch 1, 1 bag in Batch 2 -> total 3 bags, 2 batches, 150kg weight
        StockBag::create([
            'stock_in_batch_id' => $this->batch1->id,
            'branch_id' => $this->branch->id,
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'item_type_id' => $this->itemType->id,
            'item_variety_id' => $this->variety1->id,
            'bag_weight' => 50,
            'unit_price' => 100,
            'selling_price' => 120,
            'total_price' => 5000,
            'status' => 'in_stock',
            'created_by' => $this->user->id,
        ]);

        StockBag::create([
            'stock_in_batch_id' => $this->batch1->id,
            'branch_id' => $this->branch->id,
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'item_type_id' => $this->itemType->id,
            'item_variety_id' => $this->variety1->id,
            'bag_weight' => 50,
            'unit_price' => 100,
            'selling_price' => 120,
            'total_price' => 5000,
            'status' => 'in_stock',
            'created_by' => $this->user->id,
        ]);

        StockBag::create([
            'stock_in_batch_id' => $this->batch2->id,
            'branch_id' => $this->branch->id,
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'item_type_id' => $this->itemType->id,
            'item_variety_id' => $this->variety1->id,
            'bag_weight' => 50,
            'unit_price' => 100,
            'selling_price' => 120,
            'total_price' => 5000,
            'status' => 'dispatched',
            'created_by' => $this->user->id,
        ]);

        // Variety 2 Bags: 1 bag in Batch 2 -> total 1 bag, 1 batch, 50kg weight
        StockBag::create([
            'stock_in_batch_id' => $this->batch2->id,
            'branch_id' => $this->branch->id,
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'item_type_id' => $this->itemType->id,
            'item_variety_id' => $this->variety2->id,
            'bag_weight' => 50,
            'unit_price' => 100,
            'selling_price' => 120,
            'total_price' => 5000,
            'status' => 'in_stock',
            'created_by' => $this->user->id,
        ]);
    }

    public function test_can_retrieve_item_variety_wise_report()
    {
        $response = $this->getJson('/api/v1/reports/item-variety-wise');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'summary' => [
                        'total_varieties' => 2,
                        'total_batches' => 3, // 2 for variety1 + 1 for variety2
                        'total_bags' => 4,
                        'total_weight' => 200,
                    ],
                ],
            ]);

        $data = $response->json('data');
        $this->assertCount(2, $data['varieties']);

        // Check variety 1: 3 bags, 2 batches, 150kg
        $v1 = collect($data['varieties'])->firstWhere('id', $this->variety1->id);
        $this->assertNotNull($v1);
        $this->assertEquals(3, $v1['total_bags']);
        $this->assertEquals(2, $v1['total_batches']);
        $this->assertEquals(150, $v1['total_weight']);
        $this->assertEquals(2, $v1['bag_status']['in_stock']);
        $this->assertEquals(1, $v1['bag_status']['dispatched']);

        // Check variety 2: 1 bag, 1 batch, 50kg
        $v2 = collect($data['varieties'])->firstWhere('id', $this->variety2->id);
        $this->assertNotNull($v2);
        $this->assertEquals(1, $v2['total_bags']);
        $this->assertEquals(1, $v2['total_batches']);
        $this->assertEquals(50, $v2['total_weight']);
    }

    public function test_filter_item_variety_report_by_status()
    {
        $response = $this->getJson('/api/v1/reports/item-variety-wise?status=in_stock');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'summary' => [
                        'total_bags' => 3,
                        'total_weight' => 150,
                    ],
                ],
            ]);
    }

    public function test_filter_item_variety_report_by_variety_id()
    {
        $response = $this->getJson('/api/v1/reports/item-variety-wise?item_variety_id=' . $this->variety1->id);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'summary' => [
                        'total_varieties' => 1,
                        'total_bags' => 3,
                        'total_weight' => 150,
                    ],
                ],
            ]);

        $this->assertCount(1, $response->json('data.varieties'));
    }

    public function test_item_variety_report_requires_permission()
    {
        // Remove permissions from user
        $this->user->syncPermissions([]);
        $this->user->syncRoles([]);

        $response = $this->getJson('/api/v1/reports/item-variety-wise');

        $response->assertStatus(403);
    }
}
