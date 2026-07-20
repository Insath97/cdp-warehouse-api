<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Country;
use App\Models\District;
use App\Models\Province;
use App\Models\Supplier;
use App\Models\SupplierBankAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierTest extends TestCase
{
    use RefreshDatabase;

    private $user;
    private $country;
    private $foreignCountry;
    private $district;
    private $bank;

    protected function setUp(): void
    {
        parent::setUp();

        // Run seeders for SQLite in-memory testing DB
        $this->seed(\Database\Seeders\PermissionsSeeder::class);
        $this->seed(\Database\Seeders\UserSeeder::class);

        // 1. Find the seeded user
        $this->user = User::where('email', 'dev@localhost.com')->first();

        // Generate JWT Token and set authorization header
        $token = auth('api')->login($this->user);
        $this->withHeader('Authorization', 'Bearer ' . $token);

        // 2. Setup Master Data
        $this->country = Country::create([
            'name' => 'Sri Lanka',
            'code' => 'SL',
            'is_active' => true,
        ]);

        $this->foreignCountry = Country::create([
            'name' => 'India',
            'code' => 'IN',
            'is_active' => true,
        ]);

        $province = Province::create([
            'name' => 'Southern',
            'code' => 'SP',
            'country_id' => $this->country->id,
            'is_active' => true,
        ]);

        $this->district = District::create([
            'name' => 'Matara',
            'code' => 'MH',
            'province_id' => $province->id,
            'is_active' => true,
        ]);

        $this->bank = Bank::create([
            'name' => 'Bank of Ceylon',
            'code' => 'BOC',
            'is_active' => true,
        ]);
    }

    /**
     * Test retrieving paginated list of suppliers.
     */
    public function test_can_get_paginated_suppliers()
    {
        Supplier::create([
            'code' => 'SUP-001',
            'name' => 'Supplier One',
            'phone_primary' => '1234567890',
            'address_line1' => 'Galle Road',
            'city' => 'Matara',
            'country_id' => $this->country->id,
            'district_id' => $this->district->id,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/suppliers');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Suppliers retrieved successfully',
            ])
            ->assertJsonStructure([
                'data' => [
                    'data' => [
                        '*' => [
                            'id', 'code', 'name', 'phone_primary', 'bank_accounts', 'district', 'country'
                        ]
                    ]
                ]
            ]);
    }

    /**
     * Test retrieving lightweight active list of suppliers.
     */
    public function test_can_get_active_suppliers_list()
    {
        Supplier::create([
            'code' => 'SUP-001',
            'name' => 'Supplier One',
            'phone_primary' => '1234567890',
            'address_line1' => 'Galle Road',
            'city' => 'Matara',
            'country_id' => $this->country->id,
            'district_id' => $this->district->id,
            'is_active' => true,
        ]);

        Supplier::create([
            'code' => 'SUP-002',
            'name' => 'Supplier Two',
            'phone_primary' => '1234567890',
            'address_line1' => 'Galle Road',
            'city' => 'Matara',
            'country_id' => $this->country->id,
            'district_id' => $this->district->id,
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/v1/suppliers/list');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Active suppliers list retrieved successfully'
            ]);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('SUP-001', $data[0]['code']);
    }

    /**
     * Test creating supplier with bank details in a single request.
     */
    public function test_can_create_supplier_with_bank_accounts()
    {
        $payload = [
            'code' => 'SUP-003',
            'name' => 'Ranjith Paddy Farms',
            'phone_primary' => '+94712345678',
            'email' => 'ranjith@farms.com',
            'address_line1' => 'No. 45, Galle Road',
            'city' => 'Ambalantota',
            'country_id' => $this->country->id,
            'district_id' => $this->district->id,
            'id_type' => 'nic',
            'id_number' => '198765432V',
            'payment_terms' => 'net_7',
            'is_active' => true,
            'bank_accounts' => [
                [
                    'bank_id' => $this->bank->id,
                    'bank_account_no' => '1234567890',
                    'bank_branch' => 'Ambalantota Branch',
                    'account_type' => 'savings',
                    'is_primary' => true,
                ],
                [
                    'bank_id' => $this->bank->id,
                    'bank_account_no' => '0987654321',
                    'bank_branch' => 'Colombo Branch',
                    'account_type' => 'current',
                    'is_primary' => false,
                ]
            ]
        ];

        $response = $this->postJson('/api/v1/suppliers', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'message' => 'Supplier created successfully',
            ]);

        $this->assertDatabaseHas('suppliers', [
            'code' => 'SUP-003',
            'name' => 'Ranjith Paddy Farms',
            'country_id' => $this->country->id,
            'district_id' => $this->district->id,
            'id_type' => 'nic',
            'id_number' => '198765432V',
        ]);

        $this->assertDatabaseHas('supplier_bank_accounts', [
            'bank_account_no' => '1234567890',
            'bank_branch' => 'Ambalantota Branch',
            'is_primary' => true,
        ]);
    }

    /**
     * Test validation checks.
     */
    public function test_create_supplier_fails_with_validation_errors()
    {
        // 1. Missing required field (name)
        $payload = [
            'code' => 'SUP-003',
            'phone_primary' => '+94712345678',
            'address_line1' => 'No. 45, Galle Road',
            'city' => 'Ambalantota',
            'country_id' => $this->country->id,
            'district_id' => $this->district->id,
        ];

        $response = $this->postJson('/api/v1/suppliers', $payload);
        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);

        // 2. Duplicate code check
        Supplier::create([
            'code' => 'SUP-004',
            'name' => 'Existing Supplier',
            'phone_primary' => '1234567890',
            'address_line1' => 'Galle Road',
            'city' => 'Matara',
            'country_id' => $this->country->id,
            'district_id' => $this->district->id,
        ]);

        $payload = [
            'code' => 'SUP-004',
            'name' => 'Duplicate Supplier Code',
            'phone_primary' => '+94712345678',
            'address_line1' => 'No. 45, Galle Road',
            'city' => 'Ambalantota',
            'country_id' => $this->country->id,
            'district_id' => $this->district->id,
        ];

        $response = $this->postJson('/api/v1/suppliers', $payload);
        $response->assertStatus(422);

        // 3. Multiple primary bank accounts validation
        $payload = [
            'code' => 'SUP-005',
            'name' => 'Invalid Bank Accounts',
            'phone_primary' => '+94712345678',
            'address_line1' => 'No. 45, Galle Road',
            'city' => 'Ambalantota',
            'country_id' => $this->country->id,
            'district_id' => $this->district->id,
            'bank_accounts' => [
                [
                    'bank_id' => $this->bank->id,
                    'bank_account_no' => '1234567890',
                    'is_primary' => true,
                ],
                [
                    'bank_id' => $this->bank->id,
                    'bank_account_no' => '0987654321',
                    'is_primary' => true,
                ]
            ]
        ];

        $response = $this->postJson('/api/v1/suppliers', $payload);
        $response->assertStatus(422)
            ->assertJsonFragment([
                'field' => 'bank_accounts',
            ]);
    }

    /**
     * Enforces that Sri Lankan country selection requires a district_id.
     */
    public function test_create_sri_lankan_supplier_requires_district_id()
    {
        $payload = [
            'code' => 'SUP-SL-FAIL',
            'name' => 'Local Supplier Fail',
            'phone_primary' => '+94712345678',
            'address_line1' => 'No. 45, Galle Road',
            'city' => 'Colombo',
            'country_id' => $this->country->id, // Sri Lanka
            'district_id' => null, // Should fail because country is Sri Lanka
        ];

        $response = $this->postJson('/api/v1/suppliers', $payload);
        $response->assertStatus(422)
            ->assertJsonFragment([
                'field' => 'district_id',
            ]);
    }

    /**
     * Confirms foreign suppliers (non-Sri Lankan) do not require a district_id.
     */
    public function test_can_create_foreign_supplier_without_district_id()
    {
        $payload = [
            'code' => 'SUP-IN-OK',
            'name' => 'Indian Farms Ltd',
            'phone_primary' => '+919999999999',
            'address_line1' => '123 MG Road',
            'city' => 'Mumbai',
            'country_id' => $this->foreignCountry->id, // India
            'district_id' => null, // Allowed to be null
            'id_type' => 'passport',
            'id_number' => 'Z1234567',
        ];

        $response = $this->postJson('/api/v1/suppliers', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('suppliers', [
            'code' => 'SUP-IN-OK',
            'country_id' => $this->foreignCountry->id,
            'district_id' => null,
        ]);
    }

    /**
     * Test retrieving a specific supplier.
     */
    public function test_can_show_supplier()
    {
        $supplier = Supplier::create([
            'code' => 'SUP-006',
            'name' => 'Show Supplier Test',
            'phone_primary' => '1234567890',
            'address_line1' => 'Galle Road',
            'city' => 'Matara',
            'country_id' => $this->country->id,
            'district_id' => $this->district->id,
        ]);

        $response = $this->getJson("/api/v1/suppliers/{$supplier->id}");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'id' => $supplier->id,
                    'code' => 'SUP-006',
                ]
            ]);
    }

    /**
     * Test updating supplier and syncing bank accounts array.
     */
    public function test_can_update_supplier_and_sync_bank_accounts()
    {
        $supplier = Supplier::create([
            'code' => 'SUP-007',
            'name' => 'Update Supplier Test',
            'phone_primary' => '1234567890',
            'address_line1' => 'Galle Road',
            'city' => 'Matara',
            'country_id' => $this->country->id,
            'district_id' => $this->district->id,
        ]);

        $bankAccount1 = $supplier->bankAccounts()->create([
            'bank_id' => $this->bank->id,
            'bank_account_no' => 'account-1',
            'is_primary' => true,
        ]);

        $bankAccount2 = $supplier->bankAccounts()->create([
            'bank_id' => $this->bank->id,
            'bank_account_no' => 'account-2',
            'is_primary' => false,
        ]);

        $payload = [
            'code' => 'SUP-007',
            'name' => 'Update Supplier Test Name Changed',
            'phone_primary' => '1234567890',
            'address_line1' => 'Galle Road',
            'city' => 'Matara',
            'country_id' => $this->country->id,
            'district_id' => $this->district->id,
            'bank_accounts' => [
                [
                    'id' => $bankAccount1->id,
                    'bank_id' => $this->bank->id,
                    'bank_account_no' => 'account-1',
                    'bank_branch' => 'Updated Branch',
                    'is_primary' => true,
                ],
                [
                    'bank_id' => $this->bank->id,
                    'bank_account_no' => 'account-3',
                    'bank_branch' => 'New Branch',
                    'is_primary' => false,
                ]
            ]
        ];

        $response = $this->putJson("/api/v1/suppliers/{$supplier->id}", $payload);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Supplier updated successfully',
            ]);

        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'name' => 'Update Supplier Test Name Changed',
        ]);

        // Account 1 should be updated
        $this->assertDatabaseHas('supplier_bank_accounts', [
            'id' => $bankAccount1->id,
            'bank_branch' => 'Updated Branch',
        ]);

        // Account 2 should be deleted
        $this->assertDatabaseMissing('supplier_bank_accounts', [
            'id' => $bankAccount2->id,
        ]);

        // Account 3 should be created
        $this->assertDatabaseHas('supplier_bank_accounts', [
            'supplier_id' => $supplier->id,
            'bank_account_no' => 'account-3',
            'bank_branch' => 'New Branch',
        ]);
    }

    /**
     * Test toggling active/inactive status.
     */
    public function test_can_toggle_supplier_status()
    {
        $supplier = Supplier::create([
            'code' => 'SUP-008',
            'name' => 'Toggle Status Test',
            'phone_primary' => '1234567890',
            'address_line1' => 'Galle Road',
            'city' => 'Matara',
            'country_id' => $this->country->id,
            'district_id' => $this->district->id,
            'is_active' => true,
        ]);

        $response = $this->patchJson("/api/v1/suppliers/{$supplier->id}/toggle-status");

        $response->assertStatus(200);
        $this->assertFalse(Supplier::find($supplier->id)->is_active);

        $response2 = $this->patchJson("/api/v1/suppliers/{$supplier->id}/toggle-status");
        $response2->assertStatus(200);
        $this->assertTrue(Supplier::find($supplier->id)->is_active);
    }

    /**
     * Test deleting a supplier (cascade deletes bank accounts).
     */
    public function test_can_delete_supplier()
    {
        $supplier = Supplier::create([
            'code' => 'SUP-009',
            'name' => 'Delete Supplier Test',
            'phone_primary' => '1234567890',
            'address_line1' => 'Galle Road',
            'city' => 'Matara',
            'country_id' => $this->country->id,
            'district_id' => $this->district->id,
        ]);

        $bankAccount = $supplier->bankAccounts()->create([
            'bank_id' => $this->bank->id,
            'bank_account_no' => 'account-to-delete',
        ]);

        $response = $this->deleteJson("/api/v1/suppliers/{$supplier->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('suppliers', [
            'id' => $supplier->id,
        ]);

        $this->assertDatabaseMissing('supplier_bank_accounts', [
            'id' => $bankAccount->id,
        ]);
    }
}
