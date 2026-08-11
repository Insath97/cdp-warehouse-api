<?php

namespace App\Services;

use App\Models\Bank;
use App\Models\BarcodeToken;
use App\Models\BarcodeTokenBatch;
use App\Models\Branch;
use App\Models\Buyer;
use App\Models\Country;
use App\Models\Department;
use App\Models\Designation;
use App\Models\DispatchItem;
use App\Models\District;
use App\Models\Employee;
use App\Models\Group;
use App\Models\Invoice;
use App\Models\ItemType;
use App\Models\ItemVariety;
use App\Models\Province;
use App\Models\QualityInspection;
use App\Models\Receipt;
use App\Models\StockBag;
use App\Models\StockDispatch;
use App\Models\StockInBatch;
use App\Models\StockInBatchItem;
use App\Models\Supplier;
use App\Models\SupplierBankAccount;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleLog;
use App\Models\Warehouse;
use App\Traits\ActivityLogTrait;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

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
                    'country_id' => ['model' => Country::class, 'foreign_key' => 'country_id'],
                ],
                'fillable' => ['country_id', 'name', 'code', 'is_active'],
            ],
            'districts' => [
                'model' => District::class,
                'unique_key' => 'code',
                'dependencies' => [
                    'province_id' => ['model' => Province::class, 'foreign_key' => 'province_id'],
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
                    'department_id' => ['model' => Department::class, 'foreign_key' => 'department_id'],
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
                    'item_type_id' => ['model' => ItemType::class, 'foreign_key' => 'item_type_id'],
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
                    'country_id' => ['model' => Country::class,  'foreign_key' => 'country_id'],
                    'district_id' => ['model' => District::class, 'foreign_key' => 'district_id'],
                ],
                'fillable' => ['country_id', 'district_id', 'name', 'code', 'phone_primary', 'phone_secondary', 'email', 'address_line1', 'address_line2', 'city', 'id_type', 'id_number', 'payment_terms', 'notes', 'is_active'],
            ],
            'warehouses' => [
                'model' => Warehouse::class,
                'unique_key' => 'code',
                'dependencies' => [
                    'branch_id' => ['model' => Branch::class, 'foreign_key' => 'branch_id'],
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
            'employees' => [
                'model' => Employee::class,
                'unique_key' => 'employee_code',
                'dependencies' => [
                    'province_id' => ['model' => Province::class, 'foreign_key' => 'province_id'],
                    'district_id' => ['model' => District::class, 'foreign_key' => 'district_id'],
                    'branch_id' => ['model' => Branch::class, 'foreign_key' => 'branch_id'],
                    'department_id' => ['model' => Department::class, 'foreign_key' => 'department_id'],
                    'designation_id' => ['model' => Designation::class, 'foreign_key' => 'designation_id'],
                ],
                'fillable' => ['f_name', 'l_name', 'full_name', 'name_with_initials', 'employee_code', 'employee_type', 'id_type', 'id_number', 'date_of_birth', 'email', 'phone', 'address_line_1', 'city', 'state', 'country', 'postal_code', 'phone_primary', 'phone_secondary', 'have_whatsapp', 'whatsapp_number', 'start_date', 'end_date', 'joined_at', 'is_active'],
            ],
            'supplier_bank_accounts' => [
                'model' => SupplierBankAccount::class,
                'unique_key' => 'bank_account_no',
                'dependencies' => [
                    'supplier_id' => ['model' => Supplier::class, 'foreign_key' => 'supplier_id'],
                    'bank_id' => ['model' => Bank::class, 'foreign_key' => 'bank_id'],
                ],
                'fillable' => ['supplier_id', 'bank_id', 'bank_account_no', 'bank_branch', 'account_type', 'is_primary', 'is_active', 'notes'],
            ],
            'stock_in_batches' => [
                'model' => StockInBatch::class,
                'unique_key' => 'batch_number',
                'dependencies' => [
                    'supplier_id' => ['model' => Supplier::class, 'foreign_key' => 'supplier_id'],
                    'warehouse_id' => ['model' => Warehouse::class, 'foreign_key' => 'warehouse_id'],
                    'vehicle_id' => ['model' => Vehicle::class, 'foreign_key' => 'vehicle_id'],
                ],
                'fillable' => ['batch_number', 'type', 'supplier_id', 'warehouse_id', 'vehicle_id', 'received_date', 'gross_weight', 'tare_weight', 'net_weight', 'total_bags', 'total_amount', 'status', 'notes', 'created_by', 'updated_by'],
            ],
            'stock_bags' => [
                'model' => StockBag::class,
                'unique_key' => 'bag_code',
                'dependencies' => [
                    'stock_in_batch_id' => ['model' => StockInBatch::class, 'foreign_key' => 'stock_in_batch_id'],
                    'branch_id' => ['model' => Branch::class, 'foreign_key' => 'branch_id'],
                    'warehouse_id' => ['model' => Warehouse::class, 'foreign_key' => 'warehouse_id'],
                    'supplier_id' => ['model' => Supplier::class, 'foreign_key' => 'supplier_id'],
                    'item_type_id' => ['model' => ItemType::class, 'foreign_key' => 'item_type_id'],
                    'item_variety_id' => ['model' => ItemVariety::class, 'foreign_key' => 'item_variety_id'],
                ],
                'fillable' => ['bag_code', 'bag_number', 'stock_in_batch_id', 'branch_id', 'warehouse_id', 'supplier_id', 'item_type_id', 'item_variety_id', 'bag_weight', 'unit_price', 'selling_price', 'total_price', 'total_sales_amount', 'status', 'location_id', 'notes'],
            ],
            'stock_in_batch_items' => [
                'model' => StockInBatchItem::class,
                'unique_key' => 'id',
                'dependencies' => [
                    'stock_in_batch_id' => ['model' => StockInBatch::class, 'foreign_key' => 'stock_in_batch_id'],
                    'item_type_id' => ['model' => ItemType::class, 'foreign_key' => 'item_type_id'],
                    'item_variety_id' => ['model' => ItemVariety::class, 'foreign_key' => 'item_variety_id'],
                ],
                'fillable' => ['stock_in_batch_id', 'item_type_id', 'item_variety_id', 'quantity_bags', 'unit_weight', 'total_weight', 'unit_price', 'total_price', 'remaining_quantity_bags', 'remaining_weight', 'notes'],
            ],
            'stock_dispatches' => [
                'model' => StockDispatch::class,
                'unique_key' => 'dispatch_number',
                'dependencies' => [
                    'warehouse_id' => ['model' => Warehouse::class, 'foreign_key' => 'warehouse_id'],
                    'branch_id' => ['model' => Branch::class, 'foreign_key' => 'branch_id'],
                    'buyer_id' => ['model' => Buyer::class, 'foreign_key' => 'buyer_id'],
                    'vehicle_id' => ['model' => Vehicle::class, 'foreign_key' => 'vehicle_id'],
                    'created_by' => ['model' => User::class, 'foreign_key' => 'created_by'],
                ],
                'fillable' => ['dispatch_number', 'warehouse_id', 'branch_id', 'buyer_id', 'dispatch_type', 'dispatch_date', 'delivery_note_reference', 'vehicle_id', 'vehicle_log_id', 'total_bags', 'total_weight', 'total_sales_amount', 'status', 'gate_pass_number', 'gate_exit_at', 'notes', 'created_by', 'updated_by'],
            ],
            'dispatch_items' => [
                'model' => DispatchItem::class,
                'unique_key' => 'id',
                'dependencies' => [
                    'stock_dispatch_id' => ['model' => StockDispatch::class, 'foreign_key' => 'stock_dispatch_id'],
                    'stock_bag_id' => ['model' => StockBag::class, 'foreign_key' => 'stock_bag_id'],
                    'created_by' => ['model' => User::class, 'foreign_key' => 'created_by'],
                ],
                'fillable' => ['stock_dispatch_id', 'stock_bag_id', 'selling_price', 'bag_weight', 'notes', 'created_by', 'updated_by'],
            ],
            'receipts' => [
                'model' => Receipt::class,
                'unique_key' => 'receipt_number',
                'dependencies' => [
                    'stock_in_batch_id' => ['model' => StockInBatch::class, 'foreign_key' => 'stock_in_batch_id'],
                    'supplier_id' => ['model' => Supplier::class, 'foreign_key' => 'supplier_id'],
                    'warehouse_id' => ['model' => Warehouse::class, 'foreign_key' => 'warehouse_id'],
                    'branch_id' => ['model' => Branch::class, 'foreign_key' => 'branch_id'],
                    'created_by' => ['model' => User::class, 'foreign_key' => 'created_by'],
                ],
                'fillable' => ['receipt_number', 'stock_in_batch_id', 'supplier_id', 'warehouse_id', 'branch_id', 'receipt_date', 'total_bags', 'total_weight', 'total_amount', 'status', 'notes', 'created_by', 'printed_by'],
            ],
            'invoices' => [
                'model' => Invoice::class,
                'unique_key' => 'invoice_number',
                'dependencies' => [
                    'buyer_id' => ['model' => Buyer::class, 'foreign_key' => 'buyer_id'],
                    'stock_dispatch_id' => ['model' => StockDispatch::class, 'foreign_key' => 'stock_dispatch_id'],
                    'created_by' => ['model' => User::class, 'foreign_key' => 'created_by'],
                ],
                'fillable' => ['invoice_number', 'buyer_id', 'stock_dispatch_id', 'invoice_date', 'due_date', 'sub_total', 'discount_amount', 'tax_amount', 'total_amount', 'payment_status', 'payment_method', 'notes', 'created_by', 'updated_by'],
            ],
            'vehicle_logs' => [
                'model' => VehicleLog::class,
                'unique_key' => 'log_number',
                'dependencies' => [
                    'vehicle_id' => ['model' => Vehicle::class, 'foreign_key' => 'vehicle_id'],
                    'logged_by' => ['model' => User::class, 'foreign_key' => 'logged_by'],
                ],
                'fillable' => ['log_number', 'vehicle_id', 'log_type', 'direction', 'entry_time', 'exit_time', 'driver_name', 'driver_phone', 'driver_nic', 'purpose', 'notes', 'logged_by'],
            ],
            'barcode_token_batches' => [
                'model' => BarcodeTokenBatch::class,
                'unique_key' => 'batch_number',
                'dependencies' => [
                    'item_type_id' => ['model' => ItemType::class, 'foreign_key' => 'item_type_id'],
                    'item_variety_id' => ['model' => ItemVariety::class, 'foreign_key' => 'item_variety_id'],
                    'created_by' => ['model' => User::class, 'foreign_key' => 'created_by'],
                ],
                'fillable' => ['batch_number', 'item_type_id', 'item_variety_id', 'token_type', 'quantity_requested', 'notes', 'created_by'],
            ],
            'barcode_tokens' => [
                'model' => BarcodeToken::class,
                'unique_key' => 'token_code',
                'dependencies' => [
                    'barcode_token_batch_id' => ['model' => BarcodeTokenBatch::class, 'foreign_key' => 'barcode_token_batch_id'],
                    'used_by' => ['model' => User::class, 'foreign_key' => 'used_by'],
                ],
                'fillable' => ['barcode_token_batch_id', 'token_code', 'status', 'used_at', 'used_by'],
            ],
            'quality_inspections' => [
                'model' => QualityInspection::class,
                'unique_key' => 'id',
                'dependencies' => [
                    'stock_in_batch_id' => ['model' => StockInBatch::class, 'foreign_key' => 'stock_in_batch_id'],
                    'stock_bag_id' => ['model' => StockBag::class, 'foreign_key' => 'stock_bag_id'],
                    'item_type_id' => ['model' => ItemType::class, 'foreign_key' => 'item_type_id'],
                    'item_variety_id' => ['model' => ItemVariety::class, 'foreign_key' => 'item_variety_id'],
                    'inspected_by' => ['model' => User::class, 'foreign_key' => 'inspected_by'],
                ],
                'fillable' => ['stock_in_batch_id', 'stock_bag_id', 'item_type_id', 'item_variety_id', 'original_weight', 'current_weight', 'weight_difference', 'weight_change_type', 'moisture_percentage', 'grade', 'broken_percentage', 'colour_quality', 'inspection_result', 'remarks', 'inspected_by', 'inspected_at'],
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
            'errors' => [],
        ];

        $config = $this->getImportableConfig()[$table] ?? null;
        if (! $config) {
            return array_merge($results, ['errors' => ["Unsupported table: {$table}"]]);
        }

        $handle = fopen($file->getRealPath(), 'r');

        // Read headers
        $headers = fgetcsv($handle);
        if (! $headers) {
            fclose($handle);

            return array_merge($results, ['errors' => ['Empty or invalid CSV file.']]);
        }

        // Clean BOM and trim headers
        $headers = array_map(function ($header) {
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
                    'error' => 'Column count mismatch. Expected '.count($headers).', got '.count($rowData),
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
                    'error' => $e->getMessage(),
                ];
                $this->logActivity(
                    'BULK_IMPORT_ROW_FAILED',
                    'BulkImport',
                    "Import failed for table {$table} at row {$rowNumber}: ".$e->getMessage(),
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
        $dateFields = ['opening_date', 'received_date', 'dispatch_date', 'date_of_birth', 'start_date', 'end_date', 'joined_at', 'receipt_date', 'invoice_date', 'due_date', 'entry_time', 'exit_time', 'gate_exit_at', 'used_at', 'inspected_at', 'printed_at'];
        foreach ($dateFields as $field) {
            if (! empty($data[$field])) {
                $data[$field] = $this->parseDate($data[$field]);
            }
        }

        // Booleans
        $booleanFields = ['is_active', 'can_login', 'is_head_office', 'have_whatsapp', 'is_primary'];
        foreach ($booleanFields as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = $this->parseBoolean($data[$field]);
            }
        }

        // Phone numbers
        $phoneFields = ['phone_primary', 'phone_secondary', 'phone', 'driver_phone'];
        foreach ($phoneFields as $field) {
            if (! empty($data[$field])) {
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
        if ($value === null) {
            return true;
        } // Default active
        $val = strtolower(trim((string) $value));

        return in_array($val, ['1', 'true', 'yes', 'y', 'on']);
    }

    /**
     * Parse dates to Y-m-d (or Y-m-d H:i:s) format
     */
    protected function parseDate($date): ?string
    {
        if (empty($date)) {
            return null;
        }

        $date = trim($date);

        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})(?:\s+(\d{1,2}):(\d{2})(?::(\d{2}))?)?$/', $date, $matches)) {
            $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
            $year = $matches[3];

            if (checkdate((int) $month, (int) $day, (int) $year)) {
                $result = "{$year}-{$month}-{$day}";
                if (! empty($matches[4])) {
                    $hour = str_pad($matches[4], 2, '0', STR_PAD_LEFT);
                    $minute = str_pad($matches[5], 2, '0', STR_PAD_LEFT);
                    $second = ! empty($matches[6]) ? str_pad($matches[6], 2, '0', STR_PAD_LEFT) : '00';
                    $result .= " {$hour}:{$minute}:{$second}";
                }

                return $result;
            }
        }

        try {
            $parsed = Carbon::parse($date);
            $hasTime = in_array($parsed->format('H:i:s'), ['00:00:00']) === false;

            return $parsed->format($hasTime ? 'Y-m-d H:i:s' : 'Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Clean phone numbers
     */
    protected function cleanPhoneNumber($phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

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
        if (! isset($data[$uniqueKeyField]) || $data[$uniqueKeyField] === '') {
            if (isset($data['id']) && $data['id'] !== '') {
                $uniqueKeyField = 'id';
            }
        }

        if (! isset($data[$uniqueKeyField])) {
            throw new \Exception("Missing unique identifier '{$uniqueKeyField}' in row data.");
        }

        // Validate Foreign Key Dependencies (Database IDs)
        if (isset($config['dependencies'])) {
            foreach ($config['dependencies'] as $foreignKey => $dep) {
                if (! empty($data[$foreignKey])) {
                    $idVal = (int) $data[$foreignKey];
                    $exists = $dep['model']::where('id', $idVal)->exists();
                    if (! $exists) {
                        throw new \Exception("Invalid foreign key reference: {$foreignKey} with ID '{$idVal}' does not exist in database.");
                    }
                }
            }
        }

        // Special handling for Users
        if ($modelClass === User::class) {
            if (! empty($data['password'])) {
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
        if (! in_array($uniqueKeyField, $allowedFields)) {
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
                throw new \Exception('Database foreign key integrity constraint violation. Please check that referenced parent IDs exist.');
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
                'name' => ucwords(str_replace('_', ' ', $table)),
                'headers' => array_values(array_unique($headers)),
                'unique_key' => $config['unique_key'],
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
        if (! isset($configs[$table])) {
            return null;
        }

        $config = $configs[$table];
        $headers = $config['fillable'];
        if (isset($config['special_fields'])) {
            $headers = array_merge($headers, $config['special_fields']);
        }
        if (! in_array($config['unique_key'], $headers)) {
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
            'f_name' => 'Sampath',
            'l_name' => 'Perera',
            'full_name' => 'Sampath Perera',
            'name_with_initials' => 'S. Perera',
            'employee_code' => 'EMP001',
            'employee_type' => 'permanent',
            'date_of_birth' => '1990-05-15',
            'address_line_1' => '22 Lake Rd',
            'state' => 'Western',
            'country' => 'Sri Lanka',
            'whatsapp_number' => '+94771234567',
            'joined_at' => '2026-01-01',
            'bank_account_no' => '1234567890',
            'bank_branch' => 'Colombo Main',
            'account_type' => 'savings',
            'is_primary' => '1',
            'batch_number' => 'STK-20260721-0001',
            'type' => 'purchase',
            'gross_weight' => '10000.00',
            'net_weight' => '8500.00',
            'total_bags' => '100',
            'total_amount' => '1250000.00',
            'status' => 'received',
            'created_by' => '1',
            'bag_number' => '1',
            'bag_weight' => '85.00',
            'selling_price' => '150.00',
            'total_sales_amount' => '12750.00',
            'barcode_code' => 'BAG-20260721-0001',
            'qr_code' => 'QR-BAG-20260721-0001',
            'location_id' => 'A1',
            'quantity_bags' => '100',
            'unit_weight' => '85.00',
            'total_weight' => '8500.00',
            'total_price' => '1250000.00',
            'remaining_quantity_bags' => '100',
            'remaining_weight' => '8500.00',
            'dispatch_number' => 'DSP-20260728-0001',
            'dispatch_type' => 'sale',
            'dispatch_date' => '2026-07-28',
            'delivery_note_reference' => 'DN-001',
            'gate_pass_number' => 'GP-001',
            'gate_exit_at' => '2026-07-28 14:00:00',
            'receipt_number' => 'RCP-20260721-0001',
            'receipt_date' => '2026-07-21',
            'invoice_number' => 'INV-20260728-0001',
            'invoice_date' => '2026-07-28',
            'due_date' => '2026-08-28',
            'sub_total' => '1500000.00',
            'discount_amount' => '0.00',
            'tax_amount' => '15000.00',
            'payment_status' => 'unpaid',
            'payment_method' => 'bank_transfer',
            'log_number' => 'VLG-20260720-0001',
            'log_type' => 'inbound',
            'direction' => 'in',
            'entry_time' => '2026-07-20 08:30:00',
            'exit_time' => '2026-07-20 10:15:00',
            'driver_name' => 'Kasun Silva',
            'driver_nic' => '921234567V',
            'purpose' => 'Stock delivery',
            'logged_by' => '1',
            'token_type' => 'EAN-13',
            'quantity_requested' => '500',
            'token_code' => '9991234567895',
            'used_at' => '',
            'used_by' => '',
            'original_weight' => '85.00',
            'current_weight' => '84.50',
            'weight_difference' => '-0.50',
            'weight_change_type' => 'weight_loss',
            'moisture_percentage' => '12.00',
            'grade' => 'A',
            'broken_percentage' => '2.00',
            'colour_quality' => 'good',
            'inspection_result' => 'passed',
            'remarks' => 'Sample inspection.',
            'inspected_by' => '1',
            'inspected_at' => '2026-07-21 09:00:00',
        ];

        $sampleRow = [];
        foreach ($headers as $header) {
            $sampleRow[] = $sampleData[$header] ?? '1';
        }

        return [
            'filename' => "{$table}_import_template.csv",
            'headers' => $headers,
            'sample' => $sampleRow,
        ];
    }
}
