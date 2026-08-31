<?php

namespace Tests\Feature;

use App\Models\DailyPrice;
use App\Models\ItemType;
use App\Models\ItemVariety;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyPriceReportTest extends TestCase
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

        // Seed Historical Daily Prices
        DailyPrice::create([
            'date' => '2026-07-01',
            'item_variety_id' => $this->variety1->id,
            'buying_price' => 110.00,
            'selling_price' => 130.00,
            'created_by' => $this->user->id,
        ]);

        DailyPrice::create([
            'date' => '2026-07-15',
            'item_variety_id' => $this->variety1->id,
            'buying_price' => 115.00,
            'selling_price' => 135.00,
            'created_by' => $this->user->id,
        ]);

        DailyPrice::create([
            'date' => '2026-08-01',
            'item_variety_id' => $this->variety1->id,
            'buying_price' => 120.00,
            'selling_price' => 145.00,
            'created_by' => $this->user->id,
        ]);

        DailyPrice::create([
            'date' => '2026-08-01',
            'item_variety_id' => $this->variety2->id,
            'buying_price' => 100.00,
            'selling_price' => 120.00,
            'created_by' => $this->user->id,
        ]);

        DailyPrice::create([
            'date' => '2026-08-15',
            'item_variety_id' => $this->variety2->id,
            'buying_price' => 105.00,
            'selling_price' => 128.00,
            'created_by' => $this->user->id,
        ]);
    }

    /**
     * Test retrieving day-by-day daily price report.
     */
    public function test_can_generate_daily_price_report_with_day_by_day_grouping()
    {
        $response = $this->getJson('/api/v1/reports/daily-prices?group_by=daily');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'group_by' => 'daily',
            ])
            ->assertJsonStructure([
                'summary' => [
                    'total_records',
                    'avg_buying_price',
                    'avg_selling_price',
                    'min_buying_price',
                    'max_buying_price',
                    'min_selling_price',
                    'max_selling_price',
                    'avg_margin',
                    'avg_margin_percentage',
                ],
                'varieties_breakdown',
                'data',
            ]);
    }

    /**
     * Test retrieving monthly aggregated report.
     */
    public function test_can_generate_daily_price_report_with_monthly_grouping()
    {
        $response = $this->getJson('/api/v1/reports/daily-prices?group_by=monthly&year=2026');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'group_by' => 'monthly',
            ])
            ->assertJsonStructure([
                'summary',
                'varieties_breakdown',
                'data' => [
                    '*' => [
                        'period',
                        'year',
                        'month',
                        'item_variety_id',
                        'days_recorded',
                        'avg_buying_price',
                        'avg_selling_price',
                        'avg_margin',
                    ],
                ],
            ]);
    }

    /**
     * Test retrieving yearly aggregated report.
     */
    public function test_can_generate_daily_price_report_with_yearly_grouping()
    {
        $response = $this->getJson('/api/v1/reports/daily-prices?group_by=yearly');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'group_by' => 'yearly',
            ])
            ->assertJsonStructure([
                'summary',
                'varieties_breakdown',
                'data' => [
                    '*' => [
                        'period',
                        'year',
                        'item_variety_id',
                        'days_recorded',
                        'avg_buying_price',
                        'avg_selling_price',
                    ],
                ],
            ]);
    }

    /**
     * Test filtering report by date range.
     */
    public function test_can_filter_report_by_date_range()
    {
        $response = $this->getJson('/api/v1/reports/daily-prices?start_date=2026-08-01&end_date=2026-08-31');

        $response->assertStatus(200);
        $data = $response->json('data.data');

        $this->assertCount(3, $data);
    }

    /**
     * Test filtering report by specific item variety.
     */
    public function test_can_filter_report_by_specific_variety()
    {
        $response = $this->getJson("/api/v1/reports/daily-prices?item_variety_id={$this->variety1->id}");

        $response->assertStatus(200);
        $data = $response->json('data.data');

        $this->assertCount(3, $data);
        foreach ($data as $item) {
            $this->assertEquals($this->variety1->id, $item['item_variety_id']);
        }
    }

    /**
     * Test filtering report by buying and selling price ranges.
     */
    public function test_can_filter_report_by_price_ranges()
    {
        $response = $this->getJson('/api/v1/reports/daily-prices?min_buying_price=110&max_buying_price=120');

        $response->assertStatus(200);
        $data = $response->json('data.data');

        $this->assertCount(3, $data);
    }

    /**
     * Test report endpoint is protected by DailyPriceReport Index permission.
     */
    public function test_daily_price_report_requires_permission()
    {
        $restrictedUser = User::create([
            'name' => 'Restricted User',
            'username' => 'restricted',
            'email' => 'restricted@localhost.com',
            'password' => bcrypt('password123'),
            'is_active' => true,
            'can_login' => true,
        ]);

        $token = auth('api')->login($restrictedUser);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/reports/daily-prices');

        $response->assertStatus(403);
    }
}
