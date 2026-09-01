<?php

namespace Tests\Feature;

use App\Models\DailyPrice;
use App\Models\ItemType;
use App\Models\ItemVariety;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardPriceTrendsTest extends TestCase
{
    use RefreshDatabase;

    private $user;
    private $itemType;
    private $variety1;
    private $variety2;

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

        // Seed Item Type and Varieties
        $this->itemType = ItemType::create([
            'name' => 'Paddy',
            'code' => 'PDY',
            'description' => 'Paddy grains',
            'is_active' => true,
        ]);

        $this->variety1 = ItemVariety::create([
            'item_type_id' => $this->itemType->id,
            'name' => 'Keeri Samba',
            'code' => 'PDY-KS',
            'is_active' => true,
        ]);

        $this->variety2 = ItemVariety::create([
            'item_type_id' => $this->itemType->id,
            'name' => 'Nadu',
            'code' => 'PDY-NAD',
            'is_active' => true,
        ]);

        // Seed 7 consecutive days of prices for Keeri Samba (e.g. Aug 25 to Aug 31)
        DailyPrice::create([
            'date' => '2026-08-25',
            'item_variety_id' => $this->variety1->id,
            'buying_price' => 230.00,
            'selling_price' => 248.00,
            'created_by' => $this->user->id,
        ]);

        DailyPrice::create([
            'date' => '2026-08-26',
            'item_variety_id' => $this->variety1->id,
            'buying_price' => 232.00,
            'selling_price' => 250.00,
            'created_by' => $this->user->id,
        ]);

        DailyPrice::create([
            'date' => '2026-08-27',
            'item_variety_id' => $this->variety1->id,
            'buying_price' => 234.00,
            'selling_price' => 251.50,
            'created_by' => $this->user->id,
        ]);

        DailyPrice::create([
            'date' => '2026-08-28',
            'item_variety_id' => $this->variety1->id,
            'buying_price' => 236.50,
            'selling_price' => 254.00,
            'created_by' => $this->user->id,
        ]);

        DailyPrice::create([
            'date' => '2026-08-29',
            'item_variety_id' => $this->variety1->id,
            'buying_price' => 239.00,
            'selling_price' => 256.50,
            'created_by' => $this->user->id,
        ]);

        DailyPrice::create([
            'date' => '2026-08-30',
            'item_variety_id' => $this->variety1->id,
            'buying_price' => 240.50,
            'selling_price' => 258.00,
            'created_by' => $this->user->id,
        ]);

        DailyPrice::create([
            'date' => '2026-08-31',
            'item_variety_id' => $this->variety1->id,
            'buying_price' => 242.50,
            'selling_price' => 260.00,
            'created_by' => $this->user->id,
        ]);
    }

    /**
     * Test retrieving default 7-days price trends.
     */
    public function test_can_retrieve_default_7_days_price_trends(): void
    {
        $response = $this->getJson('/api/v1/dashboard/price-trends?date=2026-08-31');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'selected_variety' => [
                        'id',
                        'name',
                        'code',
                        'item_type',
                    ],
                    'live_quote' => [
                        'date',
                        'is_live',
                        'currency',
                        'unit',
                        'selling_price',
                        'buying_price',
                        'margin',
                        'margin_percentage',
                        'change_amount',
                        'change_percentage',
                        'direction',
                    ],
                    'range_summary' => [
                        'range',
                        'from_date',
                        'to_date',
                        'total_points',
                        'min_selling_price',
                        'max_selling_price',
                        'avg_selling_price',
                        'min_buying_price',
                        'max_buying_price',
                        'avg_buying_price',
                    ],
                    'trend_points' => [
                        '*' => [
                            'date',
                            'label',
                            'day_name',
                            'selling_price',
                            'buying_price',
                            'margin',
                            'is_recorded',
                        ],
                    ],
                    'available_varieties' => [
                        '*' => [
                            'id',
                            'name',
                            'code',
                            'item_type_name',
                        ],
                    ],
                ],
            ]);

        $data = $response->json('data');
        $this->assertEquals('Keeri Samba', $data['selected_variety']['name']);
        $this->assertEquals(260.00, $data['live_quote']['selling_price']);
        $this->assertEquals(242.50, $data['live_quote']['buying_price']);
        $this->assertEquals(17.50, $data['live_quote']['margin']);
        $this->assertEquals('up', $data['live_quote']['direction']);
        $this->assertCount(7, $data['trend_points']);
        $this->assertEquals('25 Aug', $data['trend_points'][0]['label']);
        $this->assertEquals('31 Aug', $data['trend_points'][6]['label']);
    }

    /**
     * Test filtering trends by specific variety.
     */
    public function test_can_filter_trends_by_specific_variety(): void
    {
        // Seed price for Nadu
        DailyPrice::create([
            'date' => '2026-08-31',
            'item_variety_id' => $this->variety2->id,
            'buying_price' => 190.00,
            'selling_price' => 210.00,
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson("/api/v1/dashboard/price-trends?item_variety_id={$this->variety2->id}&date=2026-08-31");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals('Nadu', $data['selected_variety']['name']);
        $this->assertEquals(210.00, $data['live_quote']['selling_price']);
        $this->assertEquals(190.00, $data['live_quote']['buying_price']);
    }

    /**
     * Test retrieving monthly range.
     */
    public function test_can_retrieve_monthly_range(): void
    {
        $response = $this->getJson('/api/v1/dashboard/price-trends?range=monthly&date=2026-08-31');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals('monthly', $data['range_summary']['range']);
        $this->assertCount(30, $data['trend_points']);
    }

    /**
     * Test retrieving annual range.
     */
    public function test_can_retrieve_annual_range(): void
    {
        $response = $this->getJson('/api/v1/dashboard/price-trends?range=annual&date=2026-08-31');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals('annual', $data['range_summary']['range']);
        $this->assertCount(12, $data['trend_points']);
        $this->assertArrayHasKey('period', $data['trend_points'][0]);
    }

    /**
     * Test calculating price change and percentage accurately.
     */
    public function test_calculates_price_change_and_percentage_accurately(): void
    {
        $response = $this->getJson('/api/v1/dashboard/price-trends?date=2026-08-31');

        $response->assertStatus(200);
        $quote = $response->json('data.live_quote');

        // From 248.00 (Aug 25) to 260.00 (Aug 31) => change is 12.00, percentage is (12/248)*100 = 4.84%
        $this->assertEquals(12.00, $quote['change_amount']);
        $this->assertEquals(4.84, $quote['change_percentage']);
        $this->assertEquals('up', $quote['direction']);
    }

    /**
     * Test permissions: User with Dashboard PriceTrends or Dashboard Index can access.
     */
    public function test_price_trends_requires_permission(): void
    {
        // Create user without permission
        $unauthorizedUser = User::create([
            'name' => 'Restricted Staff',
            'username' => 'staff_user',
            'email' => 'staff@localhost.com',
            'password' => bcrypt('password123'),
            'is_active' => true,
            'can_login' => true,
        ]);
        $token = auth('api')->tokenById($unauthorizedUser->id);
        auth('api')->setUser($unauthorizedUser);

        $response = $this->flushHeaders()
            ->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/dashboard/price-trends');

        $response->assertStatus(403);

        // Assign 'Dashboard PriceTrends' permission
        $permission = Permission::firstOrCreate(['name' => 'Dashboard PriceTrends', 'guard_name' => 'api']);
        $unauthorizedUser->givePermissionTo($permission);

        // Reset permission cache for test
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $responseAuthorized = $this->flushHeaders()
            ->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/dashboard/price-trends');

        $responseAuthorized->assertStatus(200);
    }

    /**
     * Test convenience alias endpoint.
     */
    public function test_convenience_alias_daily_price_trends_endpoint(): void
    {
        $response = $this->getJson('/api/v1/dashboard/daily-price-trends?date=2026-08-31');
        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');
    }
}
