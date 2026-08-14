<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Branch;
use App\Models\Country;
use App\Models\District;
use App\Models\ItemType;
use App\Models\ItemVariety;
use App\Models\Province;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderBargain;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    private $adminUser;
    private $otherUser;
    private $country;
    private $district;
    private $warehouse;
    private $otherWarehouse;
    private $supplier;
    private $itemVariety;
    private $bank;

    protected function setUp(): void
    {
        parent::setUp();

        // Run seeders
        $this->seed(\Database\Seeders\PermissionsSeeder::class);
        $this->seed(\Database\Seeders\UserSeeder::class);

        // Setup users
        $this->adminUser = User::where('email', 'dev@localhost.com')->first(); // Super Admin

        // Create another user
        $this->otherUser = User::create([
            'name' => 'Approver User',
            'username' => 'approver',
            'email' => 'approver@example.com',
            'password' => bcrypt('password123'),
            'user_scope' => 'global',
            'is_active' => true,
            'can_login' => true,
        ]);
        // Give permissions to otherUser
        $this->otherUser->givePermissionTo([
            'PurchaseOrder Index',
            'PurchaseOrder Create',
            'PurchaseOrder Approve',
            'PurchaseOrder Verify',
        ]);

        // Setup master data
        $this->country = Country::create([
            'name' => 'Sri Lanka',
            'code' => 'SL',
            'is_active' => true,
        ]);

        $province = Province::create([
            'name' => 'Western',
            'code' => 'WP',
            'country_id' => $this->country->id,
            'is_active' => true,
        ]);

        $this->district = District::create([
            'name' => 'Colombo',
            'code' => 'CO',
            'province_id' => $province->id,
            'is_active' => true,
        ]);

        $branch = Branch::create([
            'province_id' => $province->id,
            'district_id' => $this->district->id,
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

        $this->otherWarehouse = Warehouse::create([
            'branch_id' => $branch->id,
            'name' => 'Warehouse Beta',
            'code' => 'WH-BET',
            'phone_primary' => '0112223336',
            'address_line1' => 'Main Road',
            'city' => 'Galle',
            'is_active' => true,
        ]);

        $this->supplier = Supplier::create([
            'code' => 'SUP-001',
            'name' => 'Supplier One',
            'phone_primary' => '1234567890',
            'address_line1' => 'Galle Road',
            'city' => 'Colombo',
            'country_id' => $this->country->id,
            'district_id' => $this->district->id,
            'is_active' => true,
        ]);

        $itemType = ItemType::create([
            'name' => 'Paddy',
            'code' => 'PDY',
            'description' => 'Paddy grain',
            'is_active' => true,
        ]);

        $this->itemVariety = ItemVariety::create([
            'item_type_id' => $itemType->id,
            'name' => 'Keeri Samba',
            'slug' => 'keeri-samba',
            'code' => 'KS-001',
            'is_active' => true,
        ]);

        $this->bank = Bank::create([
            'name' => 'Peoples Bank',
            'code' => 'PB',
            'is_active' => true,
        ]);
    }

    private function authenticate(User $user)
    {
        $token = auth('api')->login($user);
        $this->withHeader('Authorization', 'Bearer ' . $token);
    }

    /**
     * Test creating a purchase order with an existing supplier.
     */
    public function test_can_create_po_with_existing_supplier()
    {
        $this->authenticate($this->adminUser);

        $payload = [
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'item_variety_id' => $this->itemVariety->id,
            'variety_type' => 'midwet',
            'purchase_price_per_kg' => 120.00,
            'market_price_per_kg' => 130.00,
            'number_of_bags' => 100,
            'total_weights' => 5000.00,
            'total_sales_price' => 600000.00,
            'total_market_price' => 650000.00,
            'notes' => 'Test PO existing supplier',
        ];

        $response = $this->postJson('/api/v1/purchase-orders', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'message' => 'Purchase order created successfully',
            ]);

        $po = PurchaseOrder::first();
        $this->assertNotNull($po);
        $this->assertStringStartsWith('PO-', $po->po_number);
        $this->assertEquals('pending_approval', $po->status);
        $this->assertEquals('pending', $po->payment_status);

        // Check bargain log created
        $this->assertEquals(1, $po->bargains()->count());
        $this->assertEquals('created', $po->latestBargain->action);
    }

    /**
     * Test creating a purchase order with on-the-fly supplier creation.
     */
    public function test_can_create_po_with_on_the_fly_supplier()
    {
        $this->authenticate($this->adminUser);

        $payload = [
            'warehouse_id' => $this->warehouse->id,
            'item_variety_id' => $this->itemVariety->id,
            'variety_type' => 'wet',
            'purchase_price_per_kg' => 100.00,
            'market_price_per_kg' => 105.00,
            'number_of_bags' => 50,
            'total_weights' => 2500.00,
            'total_sales_price' => 250000.00,
            'total_market_price' => 262500.00,
            'notes' => 'Test PO new supplier',
            'supplier' => [
                'code' => 'SUP-NEW-01',
                'name' => 'New Dynamic Supplier',
                'phone_primary' => '0779998887',
                'address_line1' => 'Galle Rd',
                'city' => 'Colombo',
                'country_id' => $this->country->id,
                'district_id' => $this->district->id,
                'id_type' => 'nic',
                'id_number' => '199044455566',
                'bank_accounts' => [
                    [
                        'bank_id' => $this->bank->id,
                        'bank_account_no' => '123456789012',
                        'bank_branch' => 'Colombo Fort',
                        'account_type' => 'savings',
                        'is_primary' => true,
                    ]
                ]
            ]
        ];

        $response = $this->postJson('/api/v1/purchase-orders', $payload);

        $response->assertStatus(201);

        $newSupplier = Supplier::where('code', 'SUP-NEW-01')->first();
        $this->assertNotNull($newSupplier);
        $this->assertEquals('New Dynamic Supplier', $newSupplier->name);
        $this->assertEquals(1, $newSupplier->bankAccounts()->count());

        $po = PurchaseOrder::where('supplier_id', $newSupplier->id)->first();
        $this->assertNotNull($po);
        $this->assertEquals('pending_approval', $po->status);
    }

    /**
     * Test the bargaining turn-taking logic.
     */
    public function test_bargaining_turn_taking_and_negotiation_loop()
    {
        // 1. Creator creates PO
        $this->authenticate($this->adminUser);
        $po = PurchaseOrder::create([
            'po_number' => 'PO-TEST-BARGAIN',
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'item_variety_id' => $this->itemVariety->id,
            'variety_type' => 'dry',
            'purchase_price_per_kg' => 120.00,
            'number_of_bags' => 100,
            'total_weights' => 5000.00,
            'total_sales_price' => 600000.00,
            'status' => 'pending_approval',
            'payment_status' => 'pending',
            'created_by' => $this->adminUser->id,
        ]);
        $po->bargains()->create([
            'user_id' => $this->adminUser->id,
            'action' => 'created',
            'purchase_price_per_kg' => 120.00,
            'total_sales_price' => 600000.00,
        ]);

        // 2. Creator tries to self-approve PO (Should fail)
        $response = $this->patchJson("/api/v1/purchase-orders/{$po->id}/bargain", [
            'action' => 'approve',
        ]);
        $response->assertStatus(400);

        // 3. Approver suggests a lower price
        $this->authenticate($this->otherUser);
        $response = $this->patchJson("/api/v1/purchase-orders/{$po->id}/bargain", [
            'action' => 'suggest_price',
            'purchase_price_per_kg' => 110.00,
            'total_sales_price' => 550000.00,
            'note' => 'Price is too high',
        ]);
        $response->assertStatus(200);

        $po->refresh();
        $this->assertEquals('price_suggested', $po->status);
        $this->assertEquals(110.00, $po->purchase_price_per_kg);
        $this->assertTrue($po->isWaitingForCreator());

        // 4. Approver tries to take action again (Should fail - not their turn)
        $response = $this->patchJson("/api/v1/purchase-orders/{$po->id}/bargain", [
            'action' => 'approve',
        ]);
        $response->assertStatus(400);

        // 5. Creator counter-suggests another price
        $this->authenticate($this->adminUser);
        $response = $this->patchJson("/api/v1/purchase-orders/{$po->id}/bargain", [
            'action' => 'suggest_price',
            'purchase_price_per_kg' => 115.00,
            'total_sales_price' => 575000.00,
            'note' => 'Split the difference',
        ]);
        $response->assertStatus(200);

        $po->refresh();
        $this->assertEquals('pending_approval', $po->status);
        $this->assertEquals(115.00, $po->purchase_price_per_kg);
        $this->assertTrue($po->isWaitingForApprover());

        // 6. Creator tries to take action again (Should fail - not their turn)
        $response = $this->patchJson("/api/v1/purchase-orders/{$po->id}/bargain", [
            'action' => 'approve',
        ]);
        $response->assertStatus(400);

        // 7. Approver approves the price
        $this->authenticate($this->otherUser);
        $response = $this->patchJson("/api/v1/purchase-orders/{$po->id}/bargain", [
            'action' => 'approve',
            'note' => 'Approved counter-offer',
        ]);
        $response->assertStatus(200);

        $po->refresh();
        $this->assertEquals('approved', $po->status);
    }

    /**
     * Test verification and payment updating stages.
     */
    public function test_po_verification_and_payment_stages()
    {
        Storage::fake('public');

        // Create approved PO
        $po = PurchaseOrder::create([
            'po_number' => 'PO-TEST-VERIFY',
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'item_variety_id' => $this->itemVariety->id,
            'variety_type' => 'dry',
            'purchase_price_per_kg' => 115.00,
            'number_of_bags' => 100,
            'total_weights' => 5000.00,
            'total_sales_price' => 575000.00,
            'status' => 'approved',
            'payment_status' => 'pending',
            'created_by' => $this->adminUser->id,
        ]);

        // Verify PO first
        $this->authenticate($this->otherUser);
        $response = $this->patchJson("/api/v1/purchase-orders/{$po->id}/verify");
        $response->assertStatus(200);

        $po->refresh();
        $this->assertEquals('verified', $po->status);
        $this->assertNotNull($po->verified_by);

        // Update payment to paid with fake proof document
        $file = UploadedFile::fake()->create('payment_proof.pdf', 100);

        $response = $this->postJson("/api/v1/purchase-orders/{$po->id}/payment", [
            'payment_status' => 'paid',
            'payment_proof_document' => $file,
        ]);
        $response->assertStatus(200);

        $po->refresh();
        $this->assertEquals('paid', $po->payment_status);
        $this->assertNotNull($po->payment_proof_document);
    }

    /**
     * Test warehouse scope filtering.
     */
    public function test_user_scoping_limits_visibility()
    {
        // Set otherUser to be warehouse-scoped to beta warehouse
        $this->otherUser->update([
            'user_scope' => 'warehouse',
            'warehouse_id' => $this->otherWarehouse->id,
        ]);

        // Create PO in alpha warehouse
        $poAlpha = PurchaseOrder::create([
            'po_number' => 'PO-ALPHA',
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'item_variety_id' => $this->itemVariety->id,
            'variety_type' => 'dry',
            'purchase_price_per_kg' => 115.00,
            'number_of_bags' => 100,
            'total_weights' => 5000.00,
            'total_sales_price' => 575000.00,
            'status' => 'pending_approval',
            'created_by' => $this->adminUser->id,
        ]);

        // Create PO in beta warehouse
        $poBeta = PurchaseOrder::create([
            'po_number' => 'PO-BETA',
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->otherWarehouse->id,
            'item_variety_id' => $this->itemVariety->id,
            'variety_type' => 'dry',
            'purchase_price_per_kg' => 115.00,
            'number_of_bags' => 100,
            'total_weights' => 5000.00,
            'total_sales_price' => 575000.00,
            'status' => 'pending_approval',
            'created_by' => $this->adminUser->id,
        ]);

        // Authenticate otherUser
        $this->authenticate($this->otherUser);

        // 1. Check index returns only Beta PO
        $response = $this->getJson('/api/v1/purchase-orders');
        $response->assertStatus(200);
        $data = $response->json('data.data');
        $this->assertCount(1, $data);
        $this->assertEquals('PO-BETA', $data[0]['po_number']);

        // 2. Check details of Alpha PO is hidden (404)
        $response = $this->getJson("/api/v1/purchase-orders/{$poAlpha->id}");
        $response->assertStatus(404);

        // 3. Check details of Beta PO is visible (200)
        $response = $this->getJson("/api/v1/purchase-orders/{$poBeta->id}");
        $response->assertStatus(200);
    }
}
