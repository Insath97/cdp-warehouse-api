<?php

namespace Tests\Feature;

use App\Models\ItemType;
use App\Models\ItemVariety;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemVarietyTest extends TestCase
{
    use RefreshDatabase;

    private $user;
    private $itemType1;
    private $itemType2;

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

        // Seed some Item Types
        $this->itemType1 = ItemType::create([
            'name' => 'Paddy',
            'code' => 'PDY',
            'description' => 'Paddy grains',
            'is_active' => true,
        ]);

        $this->itemType2 = ItemType::create([
            'name' => 'Sugar',
            'code' => 'SGR',
            'description' => 'Sugarcane products',
            'is_active' => true,
        ]);
    }

    /**
     * Test retrieving a list of item varieties.
     */
    public function test_can_get_paginated_item_varieties()
    {
        ItemVariety::create([
            'item_type_id' => $this->itemType1->id,
            'name' => 'Samba',
            'code' => 'PDY-SAM',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/item-varieties');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Item varieties retrieved successfully',
            ])
            ->assertJsonStructure([
                'data' => [
                    'data' => [
                        '*' => [
                            'id', 'item_type_id', 'name', 'slug', 'code', 'description', 'is_active', 'item_type'
                        ]
                    ]
                ]
            ]);
    }

    /**
     * Test active varieties listing for dropdowns.
     */
    public function test_can_get_active_item_varieties_list()
    {
        // 1. Create varieties
        $variety1 = ItemVariety::create([
            'item_type_id' => $this->itemType1->id,
            'name' => 'Samba',
            'code' => 'PDY-SAM',
            'is_active' => true,
        ]);

        $variety2 = ItemVariety::create([
            'item_type_id' => $this->itemType2->id,
            'name' => 'White Sugar',
            'code' => 'SGR-WHT',
            'is_active' => true,
        ]);

        $varietyInactive = ItemVariety::create([
            'item_type_id' => $this->itemType1->id,
            'name' => 'Nadu Inactive',
            'code' => 'PDY-NAD-IN',
            'is_active' => false,
        ]);

        // 2. Fetch full active list
        $response = $this->getJson('/api/v1/item-varieties/list');
        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));

        // 3. Fetch active list filtered by item_type_id
        $responseFiltered = $this->getJson("/api/v1/item-varieties/list?item_type_id={$this->itemType1->id}");
        $responseFiltered->assertStatus(200);
        
        $data = $responseFiltered->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Samba', $data[0]['name']);
    }

    /**
     * Test creating a variety and verifying slug auto-generation.
     */
    public function test_can_create_item_variety_with_auto_slug()
    {
        $payload = [
            'item_type_id' => $this->itemType1->id,
            'name' => 'Keeri Samba',
            'code' => 'PDY-KSAM',
            'description' => 'Premium long grain rice variety',
            'is_active' => true,
        ];

        $response = $this->postJson('/api/v1/item-varieties', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'message' => 'Item variety created successfully',
            ]);

        $this->assertDatabaseHas('item_varieties', [
            'item_type_id' => $this->itemType1->id,
            'name' => 'Keeri Samba',
            'slug' => 'keeri-samba',
            'code' => 'PDY-KSAM',
        ]);
    }

    /**
     * Test unique constraint validations.
     */
    public function test_create_item_variety_validates_uniqueness()
    {
        // Create an initial variety
        ItemVariety::create([
            'item_type_id' => $this->itemType1->id,
            'name' => 'Samba',
            'code' => 'PDY-SAM',
        ]);

        // 1. Duplicate code should fail
        $payloadDuplicateCode = [
            'item_type_id' => $this->itemType2->id,
            'name' => 'White Sugar',
            'code' => 'PDY-SAM', // duplicate
        ];
        $response1 = $this->postJson('/api/v1/item-varieties', $payloadDuplicateCode);
        $response1->assertStatus(422)
            ->assertJsonFragment(['field' => 'code']);

        // 2. Duplicate name under the SAME item type should fail
        $payloadDuplicateNameSameType = [
            'item_type_id' => $this->itemType1->id,
            'name' => 'Samba', // duplicate under same type
            'code' => 'PDY-SAM-2',
        ];
        $response2 = $this->postJson('/api/v1/item-varieties', $payloadDuplicateNameSameType);
        $response2->assertStatus(422)
            ->assertJsonFragment(['field' => 'name']);

        // 3. Duplicate name under a DIFFERENT item type should succeed
        $payloadDuplicateNameDiffType = [
            'item_type_id' => $this->itemType2->id,
            'name' => 'Samba', // same name, different type
            'code' => 'SGR-SAM',
        ];
        $response3 = $this->postJson('/api/v1/item-varieties', $payloadDuplicateNameDiffType);
        $response3->assertStatus(201);
        $this->assertDatabaseHas('item_varieties', [
            'item_type_id' => $this->itemType2->id,
            'name' => 'Samba',
            'code' => 'SGR-SAM',
        ]);
    }

    /**
     * Test viewing variety.
     */
    public function test_can_show_item_variety()
    {
        $variety = ItemVariety::create([
            'item_type_id' => $this->itemType1->id,
            'name' => 'Samba',
            'code' => 'PDY-SAM',
        ]);

        $response = $this->getJson("/api/v1/item-varieties/{$variety->id}");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'id' => $variety->id,
                    'name' => 'Samba',
                ]
            ]);
    }

    /**
     * Test updating a variety and verifying slug auto-updates.
     */
    public function test_can_update_item_variety_and_regenerate_slug()
    {
        $variety = ItemVariety::create([
            'item_type_id' => $this->itemType1->id,
            'name' => 'Samba Old',
            'code' => 'PDY-SAM',
        ]);

        $payload = [
            'item_type_id' => $this->itemType1->id,
            'name' => 'Samba Updated',
            'code' => 'PDY-SAM',
        ];

        $response = $this->putJson("/api/v1/item-varieties/{$variety->id}", $payload);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Item variety updated successfully',
            ]);

        $this->assertDatabaseHas('item_varieties', [
            'id' => $variety->id,
            'name' => 'Samba Updated',
            'slug' => 'samba-updated',
        ]);
    }

    /**
     * Test toggling status.
     */
    public function test_can_toggle_item_variety_status()
    {
        $variety = ItemVariety::create([
            'item_type_id' => $this->itemType1->id,
            'name' => 'Samba',
            'code' => 'PDY-SAM',
            'is_active' => true,
        ]);

        $response = $this->patchJson("/api/v1/item-varieties/{$variety->id}/toggle-status");
        $response->assertStatus(200);
        $this->assertFalse(ItemVariety::find($variety->id)->is_active);

        $response2 = $this->patchJson("/api/v1/item-varieties/{$variety->id}/toggle-status");
        $response2->assertStatus(200);
        $this->assertTrue(ItemVariety::find($variety->id)->is_active);
    }

    /**
     * Test deleting a variety.
     */
    public function test_can_delete_item_variety()
    {
        $variety = ItemVariety::create([
            'item_type_id' => $this->itemType1->id,
            'name' => 'Samba',
            'code' => 'PDY-SAM',
        ]);

        $response = $this->deleteJson("/api/v1/item-varieties/{$variety->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('item_varieties', [
            'id' => $variety->id,
        ]);
    }
}
