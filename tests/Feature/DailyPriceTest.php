<?php

namespace Tests\Feature;

use App\Models\DailyPrice;
use App\Models\ItemType;
use App\Models\ItemVariety;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyPriceTest extends TestCase
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
    }

    /**
     * Test retrieving a paginated list of daily prices.
     */
    public function test_can_get_paginated_daily_prices()
    {
        DailyPrice::create([
            'date' => Carbon::today()->toDateString(),
            'item_variety_id' => $this->variety1->id,
            'buying_price' => 120.50,
            'selling_price' => 145.00,
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/daily-prices');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Daily prices retrieved successfully',
            ])
            ->assertJsonStructure([
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'date',
                            'item_variety_id',
                            'buying_price',
                            'selling_price',
                            'created_by',
                            'item_variety',
                            'creator',
                        ],
                    ],
                ],
            ]);
    }

    /**
     * Test creating a new daily price entry with auto date (defaults to today).
     */
    public function test_can_create_daily_price_without_date_defaults_to_today()
    {
        $payload = [
            'item_variety_id' => $this->variety1->id,
            'buying_price' => 115.00,
            'selling_price' => 135.50,
        ];

        $response = $this->postJson('/api/v1/daily-prices', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'message' => 'Daily price created successfully',
            ]);

        $this->assertDatabaseHas('daily_prices', [
            'date' => Carbon::today()->toDateString(),
            'item_variety_id' => $this->variety1->id,
            'buying_price' => 115.00,
            'selling_price' => 135.50,
            'created_by' => $this->user->id,
        ]);
    }

    /**
     * Test validation failure on duplicate entry for same variety on same date (only once per day).
     */
    public function test_cannot_create_duplicate_price_for_same_variety_on_same_date()
    {
        DailyPrice::create([
            'date' => Carbon::today()->toDateString(),
            'item_variety_id' => $this->variety1->id,
            'buying_price' => 120.00,
            'selling_price' => 140.00,
            'created_by' => $this->user->id,
        ]);

        $payload = [
            'item_variety_id' => $this->variety1->id,
            'buying_price' => 125.00,
            'selling_price' => 145.00,
        ];

        $response = $this->postJson('/api/v1/daily-prices', $payload);

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);
    }

    /**
     * Test viewing a specific daily price.
     */
    public function test_can_show_daily_price()
    {
        $price = DailyPrice::create([
            'date' => Carbon::today()->toDateString(),
            'item_variety_id' => $this->variety1->id,
            'buying_price' => 120.00,
            'selling_price' => 140.00,
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson("/api/v1/daily-prices/{$price->id}");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'id' => $price->id,
                    'item_variety_id' => $this->variety1->id,
                ],
            ]);
    }

    /**
     * Test updating buying and selling prices.
     */
    public function test_can_update_daily_price()
    {
        $price = DailyPrice::create([
            'date' => Carbon::today()->toDateString(),
            'item_variety_id' => $this->variety1->id,
            'buying_price' => 120.00,
            'selling_price' => 140.00,
            'created_by' => $this->user->id,
        ]);

        $payload = [
            'buying_price' => 125.75,
            'selling_price' => 148.25,
        ];

        $response = $this->putJson("/api/v1/daily-prices/{$price->id}", $payload);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Daily price updated successfully',
            ]);

        $this->assertDatabaseHas('daily_prices', [
            'id' => $price->id,
            'buying_price' => 125.75,
            'selling_price' => 148.25,
            'updated_by' => $this->user->id,
        ]);
    }

    /**
     * Test deleting a daily price.
     */
    public function test_can_delete_daily_price()
    {
        $price = DailyPrice::create([
            'date' => Carbon::today()->toDateString(),
            'item_variety_id' => $this->variety1->id,
            'buying_price' => 120.00,
            'selling_price' => 140.00,
            'created_by' => $this->user->id,
        ]);

        $response = $this->deleteJson("/api/v1/daily-prices/{$price->id}");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Daily price deleted successfully',
            ]);

        $this->assertDatabaseMissing('daily_prices', [
            'id' => $price->id,
        ]);
    }

    /**
     * Test fetching today's active price for a specific variety.
     */
    public function test_can_get_today_price_for_variety()
    {
        $today = Carbon::today()->toDateString();

        DailyPrice::create([
            'date' => $today,
            'item_variety_id' => $this->variety1->id,
            'buying_price' => 130.00,
            'selling_price' => 155.00,
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson("/api/v1/daily-prices/today?item_variety_id={$this->variety1->id}");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'item_variety_id' => $this->variety1->id,
                ],
            ]);
    }
}
