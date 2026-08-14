<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Province;
use App\Models\District;
use App\Models\Branch;
use App\Models\ItemType;
use App\Models\ItemVariety;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\PurchaseOrderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class PurchaseOrderNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected $adminUser;
    protected $creatorUser;
    protected $approverUser;
    protected $verifierUser;
    protected $supplier;
    protected $warehouse;
    protected $itemVariety;
    protected $country;
    protected $district;

    protected function setUp(): void
    {
        parent::setUp();

        // Run seeders
        $this->seed(\Database\Seeders\PermissionsSeeder::class);

        // Setup master data
        $this->country = Country::create([
            'name' => 'Sri Lanka',
            'code' => 'LK',
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

        $this->supplier = Supplier::create([
            'code' => 'SUP-001',
            'name' => 'Test Supplier',
            'phone_primary' => '0771234567',
            'address_line1' => 'No 1, Main Rd',
            'city' => 'Colombo',
            'country_id' => $this->country->id,
            'district_id' => $this->district->id,
            'payment_terms' => 'immediate',
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

        // Setup users
        $this->adminUser = User::create([
            'name' => 'Admin User',
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
            'user_scope' => 'global',
            'is_active' => true,
            'can_login' => true,
        ]);
        $this->adminUser->assignRole('Super Admin');

        // User A: Creator
        $this->creatorUser = User::create([
            'name' => 'Creator User',
            'username' => 'creator',
            'email' => 'creator@example.com',
            'password' => bcrypt('password123'),
            'user_scope' => 'global',
            'is_active' => true,
            'can_login' => true,
        ]);
        $this->creatorUser->givePermissionTo([
            'PurchaseOrder Index',
            'PurchaseOrder Create',
        ]);

        // User B: Approver
        $this->approverUser = User::create([
            'name' => 'Approver User',
            'username' => 'approver',
            'email' => 'approver@example.com',
            'password' => bcrypt('password123'),
            'user_scope' => 'global',
            'is_active' => true,
            'can_login' => true,
        ]);
        $this->approverUser->givePermissionTo([
            'PurchaseOrder Index',
            'PurchaseOrder Approve',
        ]);

        // User C: Verifier / Payment Staff
        $this->verifierUser = User::create([
            'name' => 'Verifier User',
            'username' => 'verifier',
            'email' => 'verifier@example.com',
            'password' => bcrypt('password123'),
            'user_scope' => 'global',
            'is_active' => true,
            'can_login' => true,
        ]);
        $this->verifierUser->givePermissionTo([
            'PurchaseOrder Index',
            'PurchaseOrder Verify',
            'Notification Index',
        ]);
    }

    protected function authenticate($user)
    {
        $token = JWTAuth::fromUser($user);
        $this->withHeader('Authorization', "Bearer {$token}");
    }

    public function test_purchase_order_notifications_flow()
    {
        Notification::fake();

        // 1. User A creates PO -> Approvers (User B) should be notified
        $this->authenticate($this->creatorUser);
        $response = $this->postJson('/api/v1/purchase-orders', [
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'item_variety_id' => $this->itemVariety->id,
            'variety_type' => 'dry',
            'purchase_price_per_kg' => 110.00,
            'number_of_bags' => 100,
            'total_weights' => 5000.00,
        ]);
        $response->assertStatus(201);
        $poId = $response->json('data.id');

        Notification::assertSentTo(
            $this->approverUser,
            PurchaseOrderNotification::class,
            function ($notification) {
                return str_contains($notification->title, 'New Purchase Order Created');
            }
        );

        // 2. User B counter-offers -> User A should be notified
        $po = PurchaseOrder::find($poId);
        $this->authenticate($this->approverUser);
        $response = $this->patchJson("/api/v1/purchase-orders/{$poId}/bargain", [
            'action' => 'suggest_price',
            'purchase_price_per_kg' => 105.00,
            'note' => 'Offer 105 LKR',
        ]);
        $response->assertStatus(200);

        Notification::assertSentTo(
            $this->creatorUser,
            PurchaseOrderNotification::class,
            function ($notification) {
                return str_contains($notification->title, 'New Counter-Offer');
            }
        );

        // 3. Creator User counter-offers back
        $this->authenticate($this->creatorUser);
        $response = $this->patchJson("/api/v1/purchase-orders/{$poId}/bargain", [
            'action' => 'suggest_price',
            'purchase_price_per_kg' => 108.00,
            'note' => 'Countering with 108 LKR',
        ]);
        $response->assertStatus(200);

        // 3b. User B (Approver) approves the PO -> Verifiers (User C) should be notified
        $this->authenticate($this->approverUser);
        $po->refresh();
        $response = $this->patchJson("/api/v1/purchase-orders/{$poId}/bargain", [
            'action' => 'approve',
            'note' => 'Price agreed',
        ]);
        $response->assertStatus(200);

        Notification::assertSentTo(
            $this->verifierUser,
            PurchaseOrderNotification::class,
            function ($notification) {
                return str_contains($notification->title, 'Purchase Order Approved');
            }
        );

        // 4. User C verifies the PO -> User A and User B should be notified
        $this->authenticate($this->verifierUser);
        $response = $this->patchJson("/api/v1/purchase-orders/{$poId}/verify");
        $response->assertStatus(200);

        Notification::assertSentTo(
            [$this->creatorUser, $this->approverUser],
            PurchaseOrderNotification::class,
            function ($notification) {
                return str_contains($notification->title, 'Purchase Order Verified');
            }
        );

        // 5. User C marks PO as paid -> User A and User B should be notified
        $response = $this->postJson("/api/v1/purchase-orders/{$poId}/payment", [
            'payment_status' => 'paid',
        ]);
        $response->assertStatus(200);

        Notification::assertSentTo(
            [$this->creatorUser, $this->approverUser],
            PurchaseOrderNotification::class,
            function ($notification) {
                return str_contains($notification->title, 'Purchase Order Paid');
            }
        );
    }

    public function test_system_notifications_api_endpoints()
    {
        // Generate a database notification for User C
        $po = PurchaseOrder::create([
            'po_number' => 'PO-NOTIF-TEST',
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'item_variety_id' => $this->itemVariety->id,
            'variety_type' => 'dry',
            'purchase_price_per_kg' => 110.00,
            'number_of_bags' => 100,
            'total_weights' => 5000.00,
            'total_sales_price' => 550000.00,
            'status' => 'approved',
            'created_by' => $this->creatorUser->id,
        ]);

        $this->verifierUser->notify(new PurchaseOrderNotification(
            $po,
            "PO Approved Notification",
            "Details..."
        ));

        // Fetch notifications via API
        $this->authenticate($this->verifierUser);
        $response = $this->getJson('/api/v1/notifications?filter=unread');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'message',
            'data' => [
                'data' => [
                    '*' => [
                        'id', 'type', 'notifiable_type', 'notifiable_id', 'data', 'read_at'
                    ]
                ]
            ]
        ]);

        $this->assertCount(1, $response->json('data.data'));
        $notificationId = $response->json('data.data.0.id');

        // Mark as read
        $response = $this->patchJson("/api/v1/notifications/{$notificationId}/read");
        $response->assertStatus(200);

        // Verify it is now read
        $this->verifierUser->refresh();
        $this->assertCount(0, $this->verifierUser->unreadNotifications);

        // Test mark all as read
        $this->verifierUser->notify(new PurchaseOrderNotification($po, "Po Notification 2", "Details..."));
        $this->verifierUser->refresh();
        $this->assertCount(1, $this->verifierUser->unreadNotifications);

        $response = $this->postJson('/api/v1/notifications/read-all');
        $response->assertStatus(200);

        $this->verifierUser->refresh();
        $this->assertCount(0, $this->verifierUser->unreadNotifications);
    }
}
