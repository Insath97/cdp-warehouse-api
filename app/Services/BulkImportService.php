<?php

namespace App\Services;

use App\Models\Bank;
use App\Models\Branch;
use App\Models\Buyer;
use App\Models\Country;
use App\Models\Department;
use App\Models\Designation;
use App\Models\District;
use App\Models\Group;
use App\Models\ItemType;
use App\Models\ItemVariety;
use App\Models\Province;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\Traits\ActivityLogTrait;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class BulkImportService
{
    use ActivityLogTrait;

    /**
     * Get configuration for all importable tables in the system using Database IDs.
     */
    public function getImportableConfig(): array
    {
        return [
            'countries' => [
                'model' => Country::class,
                'unique_key' => 'code',
                'fillable' => ['name', 'code', 'description', 'is_active'],
            ],
            'provinces' => [
                'model' => Province::class,
                'unique_key' => 'code',
                'dependencies' => [
                    'country_id' => ['model' => Country::class, 'foreign_key' => 'country_id']
                ],
                'fillable' => ['country_id', 'name', 'code', 'is_active'],
            ],
            'districts' => [
                'model' => District::class,
                'unique_key' => 'code',
                'dependencies' => [
                    'province_id' => ['model' => Province::class, 'foreign_key' => 'province_id']
                ],
                'fillable' => ['province_id', 'name', 'code', 'is_active'],
            ],
            'groups' => [
                'model' => Group::class,
                'unique_key' => 'code',
                'fillable' => ['name', 'code', 'description', 'is_active'],
            ],
            'branches' => [
                'model' => Branch::class,
                'unique_key' => 'code',
                'dependencies' => [
                    'province_id' => ['model' => Province::class, 'foreign_key' => 'province_id'],
                    'group_id' => ['model' => Group::class, 'foreign_key' => 'group_id'],
                ],
                'fillable' => ['province_id', 'group_id', 'name', 'code', 'address_line1', 'address_line2', 'city', 'postal_code', 'phone_primary', 'phone_secondary', 'email', 'fax', 'opening_date', 'branch_type', 'latitude', 'longitude', 'is_active', 'is_head_office'],
            ],
            'departments' => [
                'model' => Department::class,
                'unique_key' => 'code',
                'fillable' => ['name', 'code', 'description', 'is_active'],
            ],
            'designations' => [
                'model' => Designation::class,
                'unique_key' => 'code',
                'dependencies' => [
                    'department_id' => ['model' => Department::class, 'foreign_key' => 'department_id']
                ],
                'fillable' => ['department_id', 'name', 'code', 'level', 'order_weight', 'description', 'is_active'],
            ],
            'item_types' => [
                'model' => ItemType::class,
                'unique_key' => 'code',
                'fillable' => ['name', 'code', 'description', 'is_active'],
            ],
            'item_varieties' => [
                'model' => ItemVariety::class,
                'unique_key' => 'code',
                'dependencies' => [
                    'item_type_id' => ['model' => ItemType::class, 'foreign_key' => 'item_type_id']
                ],
                'fillable' => ['item_type_id', 'name', 'code', 'description', 'is_active'],
            ],
            'banks' => [
                'model' => Bank::class,
                'unique_key' => 'code',
                'fillable' => ['name', 'code', 'is_active'],
            ],
            'suppliers' => [
                'model' => Supplier::class,
                'unique_key' => 'code',
                'dependencies' => [
                    'country_id'  => ['model' => Country::class,  'foreign_key' => 'country_id'],
                    'district_id' => ['model' => District::class, 'foreign_key' => 'district_id'],
                ],
                'fillable' => ['country_id', 'district_id', 'name', 'code', 'phone_primary', 'phone_secondary', 'email', 'address_line1', 'address_line2', 'city', 'id_type', 'id_number', 'payment_terms', 'notes', 'is_active'],
            ],
            'warehouses' => [
                'model' => Warehouse::class,
                'unique_key' => 'code',
                'dependencies' => [
                    'branch_id' => ['model' => Branch::class, 'foreign_key' => 'branch_id']
                ],
                'fillable' => ['branch_id', 'name', 'code', 'address_line1', 'address_line2', 'city', 'phone', 'email', 'is_active'],
            ],
            'buyers' => [
                'model' => Buyer::class,
                'unique_key' => 'code',
                'fillable' => ['name', 'code', 'brand_name', 'company_name', 'phone_primary', 'phone_secondary', 'email', 'address', 'tax_number', 'notes', 'is_active'],
            ],
            'vehicles' => [
                'model' => Vehicle::class,
                'unique_key' => 'vehicle_number',
                'fillable' => ['vehicle_number', 'vehicle_type', 'ownership_type', 'supplier_id', 'tare_weight', 'availability_status', 'is_active'],
            ],
            'users' => [
                'model' => User::class,
                'unique_key' => 'username',
                'special_fields' => ['password', 'role'],
                'fillable' => ['name', 'username', 'email', 'user_type', 'is_active', 'can_login'],
            ],
        ];
    }

    /**
     * Import data from CSV/Excel file for a specific table.
     */
    public function import(UploadedFile $file, string $table): array
    {
        $results = [
            'total' => 0,
            'imported' => 0,
            'failed' => 0,
            'errors' => []
        ];

        $config = $this->getImportableConfig()[$table] ?? null;
        if (!$config) {
            return array_merge($results, ['errors' => ["Unsupported table: {$table}"]]);
        }

        $handle = fopen($file->getRealPath(), 'r');

        // Read headers
        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            return array_merge($results, ['errors' => ['Empty or invalid CSV file.']]);
        }

        // Clean BOM and trim headers
        $headers = array_map(function($header) {
            return trim($header, "\xEF\xBB\xBF");
        }, $headers);
        $headers = array_map('trim', $headers);

        $rowNumber = 1;
        while (($rowData = fgetcsv($handle)) !== false) {
            $rowNumber++;
            $results['total']++;

            if (count($headers) !== count($rowData)) {
                $results['failed']++;
                $results['errors'][] = [
                    'row' => $rowNumber,
                    'error' => "Column count mismatch. Expected " . count($headers) . ", got " . count($rowData)
                ];
                continue;
            }

            $data = array_combine($headers, $rowData);
            $data = $this->cleanData($data);

            // Preprocess specific fields
            $data = $this->preprocessRowData($data);

            DB::beginTransaction();
            try {
                $this->processGenericRow($config, $data);
                DB::commit();
                $results['imported']++;
            } catch (\Throwable $e) {
                DB::rollBack();
                $results['failed']++;
                $results['errors'][] = [
                    'row' => $rowNumber,
                    'error' => $e->getMessage()
                ];
                $this->logActivity(
                    'BULK_IMPORT_ROW_FAILED',
                    'BulkImport',
                    "Import failed for table {$table} at row {$rowNumber}: " . $e->getMessage(),
                    ['table' => $table, 'row' => $rowNumber, 'error' => $e->getMessage()],
                    'error'
                );
            }
        }

        fclose($handle);

        $this->logActivity(
            'BULK_IMPORT_SUCCESS',
            'BulkImport',
            "Bulk import completed for table {$table}. Total: {$results['total']}, Imported: {$results['imported']}, Failed: {$results['failed']}",
            ['table' => $table, 'results' => $results]
        );

        return $results;
    }

    /**
     * Clean and sanitize row input data
     */
    protected function cleanData(array $data): array
    {
        $cleaned = [];
        foreach ($data as $key => $value) {
            if ($value === null || $value === '') {
                $cleaned[$key] = null;
            } else {
                $cleaned[$key] = trim($value);
            }
        }
        return $cleaned;
    }

    /**
     * Common Row Data Preprocessing
     */
    protected function preprocessRowData(array $data): array
    {
        // Date parsing
        $dateFields = ['opening_date', 'received_date', 'dispatch_date', 'date_of_birth', 'start_date', 'end_date'];
        foreach ($dateFields as $field) {
            if (!empty($data[$field])) {
                $data[$field] = $this->parseDate($data[$field]);
            }
        }

        // Booleans
        $booleanFields = ['is_active', 'can_login', 'is_head_office', 'have_whatsapp'];
        foreach ($booleanFields as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = $this->parseBoolean($data[$field]);
            }
        }

        // Phone numbers
        $phoneFields = ['phone_primary', 'phone_secondary', 'phone', 'driver_phone'];
        foreach ($phoneFields as $field) {
            if (!empty($data[$field])) {
                $data[$field] = $this->cleanPhoneNumber($data[$field]);
            }
        }

        return $data;
    }

    /**
     * Parse boolean values
     */
    protected function parseBoolean($value): bool
    {
        if ($value === null) return true; // Default active
        $val = strtolower(trim((string)$value));
        return in_array($val, ['1', 'true', 'yes', 'y', 'on']);
    }

    /**
     * Parse dates to Y-m-d format
     */
    protected function parseDate($date): ?string
    {
        if (empty($date)) return null;

        $date = trim($date);

        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $date, $matches)) {
            $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
            $year = $matches[3];

            if (checkdate((int)$month, (int)$day, (int)$year)) {
                return "{$year}-{$month}-{$day}";
            }
        }

        try {
            return Carbon::parse($date)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Clean phone numbers
     */
    protected function cleanPhoneNumber($phone): ?string
    {
        if (empty($phone)) return null;
        return preg_replace('/[^0-9+]/', '', $phone);
    }

    /**
     * Process single row using configuration and database ID validation.
     */
    protected function processGenericRow(array $config, array $data): void
    {
        $modelClass = $config['model'];
        $uniqueKeyField = $config['unique_key'];

        // Fallback unique key resolution
        if (!isset($data[$uniqueKeyField]) || $data[$uniqueKeyField] === '') {
            if (isset($data['id']) && $data['id'] !== '') {
                $uniqueKeyField = 'id';
            }
        }

        if (!isset($data[$uniqueKeyField])) {
            throw new \Exception("Missing unique identifier '{$uniqueKeyField}' in row data.");
        }

        // Validate Foreign Key Dependencies (Database IDs)
        if (isset($config['dependencies'])) {
            foreach ($config['dependencies'] as $foreignKey => $dep) {
                if (!empty($data[$foreignKey])) {
                    $idVal = (int) $data[$foreignKey];
                    $exists = $dep['model']::where('id', $idVal)->exists();
                    if (!$exists) {
                        throw new \Exception("Invalid foreign key reference: {$foreignKey} with ID '{$idVal}' does not exist in database.");
                    }
                }
            }
        }

        // Special handling for Users
        if ($modelClass === User::class) {
            if (!empty($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            } else {
                $data['password'] = Hash::make($data['username']); // Default fallback
            }
            $roleName = $data['role'] ?? null;
            unset($data['role']);

            $user = User::updateOrCreate([$uniqueKeyField => $data[$uniqueKeyField]], $data);

            if ($roleName) {
                $user->syncRoles([$roleName]);
            }
            return;
        }

        // Allowed fields
        $allowedFields = $config['fillable'];
        if (!in_array($uniqueKeyField, $allowedFields)) {
            $allowedFields[] = $uniqueKeyField;
        }

        // Filter row data
        $filteredData = array_intersect_key($data, array_flip($allowedFields));

        try {
            $modelClass::updateOrCreate(
                [$uniqueKeyField => $filteredData[$uniqueKeyField]],
                $filteredData
            );
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() === '23000') {
                throw new \Exception("Database foreign key integrity constraint violation. Please check that referenced parent IDs exist.");
            }
            throw $e;
        }
    }

    /**
     * Get list of importable tables for dynamic select box selection.
     */
    public function getImportableTables(): array
    {
        $configs = $this->getImportableConfig();
        $list = [];

        foreach ($configs as $table => $config) {
            $headers = $config['fillable'];
            if (isset($config['special_fields'])) {
                $headers = array_merge($headers, $config['special_fields']);
            }

            $list[] = [
                'table' => $table,
                'name'  => ucwords(str_replace('_', ' ', $table)),
                'headers' => array_values(array_unique($headers)),
                'unique_key' => $config['unique_key']
            ];
        }

        return $list;
    }

    /**
     * Get template data (headers and actual sample row) for a specific table using IDs.
     */
    public function getTemplateData(string $table): ?array
    {
        $configs = $this->getImportableConfig();
        if (!isset($configs[$table])) {
            return null;
        }

        $config = $configs[$table];
        $headers = $config['fillable'];
        if (isset($config['special_fields'])) {
            $headers = array_merge($headers, $config['special_fields']);
        }
        if (!in_array($config['unique_key'], $headers)) {
            $headers[] = $config['unique_key'];
        }

        $headers = array_values(array_unique($headers));

        // Mock realistic sample values using database IDs
        $sampleData = [
            'name' => 'Sample Name',
            'code' => 'CODE001',
            'description' => 'Sample description.',
            'is_active' => '1',
            'country_id' => '1',
            'province_id' => '1',
            'district_id' => '1',
            'zone_id' => '1',
            'region_id' => '1',
            'group_id' => '1',
            'branch_id' => '1',
            'department_id' => '1',
            'item_type_id' => '1',
            'address_line1' => '123 Main St',
            'address_line2' => 'Suite 4B',
            'city' => 'Colombo',
            'postal_code' => '00100',
            'phone_primary' => '+94771234567',
            'phone_secondary' => '+94777654321',
            'phone' => '+94112345678',
            'email' => 'sample@example.com',
            'fax' => '',
            'opening_date' => '2026-01-01',
            'branch_type' => 'main',
            'latitude' => '6.9271',
            'longitude' => '79.8612',
            'is_head_office' => '0',
            'level' => 'mid',
            'order_weight' => '1',
            'id_type' => 'nic',
            'id_number' => '951234567V',
            'payment_terms' => 'Net 30',
            'notes' => 'Sample note.',
            'username' => 'user01',
            'password' => 'password123',
            'user_type' => 'staff',
            'can_login' => '1',
            'role' => 'Super Admin',
            'brand_name' => 'Apex Exporters',
            'company_name' => 'Apex Corp',
            'address' => '45 Commercial Ave',
            'tax_number' => 'TAX998877',
            'vehicle_number' => 'WP-CAB-1234',
            'vehicle_type' => 'lorry',
            'ownership_type' => 'own',
            'supplier_id' => '',
            'tare_weight' => '1500.00',
            'availability_status' => 'available',
        ];

        $sampleRow = [];
        foreach ($headers as $header) {
            $sampleRow[] = $sampleData[$header] ?? '1';
        }

        return [
            'filename' => "{$table}_import_template.csv",
            'headers'  => $headers,
            'sample'   => $sampleRow
        ];
    }
}
