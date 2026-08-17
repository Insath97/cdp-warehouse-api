<?php

namespace App\Services;

use App\Models\ActivityLog;
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
use Illuminate\Support\Facades\DB;

class DataExportService
{
    use ActivityLogTrait;

    /**
     * Get all exportable tables with their models and columns
     */
    public function getExportableTables(): array
    {
        return [
            'countries' => [
                'model' => Country::class,
                'columns' => ['id', 'name', 'code', 'description', 'is_active', 'created_at', 'updated_at'],
                'display_name' => 'Countries',
            ],
            'provinces' => [
                'model' => Province::class,
                'columns' => ['id', 'country_id', 'name', 'code', 'is_active', 'created_at', 'updated_at'],
                'display_name' => 'Provinces',
            ],
            'districts' => [
                'model' => District::class,
                'columns' => ['id', 'province_id', 'name', 'code', 'is_active', 'created_at', 'updated_at'],
                'display_name' => 'Districts',
            ],
            'groups' => [
                'model' => Group::class,
                'columns' => ['id', 'name', 'code', 'description', 'is_active', 'created_at', 'updated_at'],
                'display_name' => 'Groups',
            ],
            'branches' => [
                'model' => Branch::class,
                'columns' => ['id', 'province_id', 'group_id', 'name', 'code', 'address_line1', 'address_line2', 'city', 'postal_code', 'phone_primary', 'phone_secondary', 'email', 'fax', 'opening_date', 'branch_type', 'latitude', 'longitude', 'is_active', 'is_head_office', 'created_at', 'updated_at'],
                'display_name' => 'Branches',
            ],
            'departments' => [
                'model' => Department::class,
                'columns' => ['id', 'name', 'code', 'description', 'is_active', 'created_at', 'updated_at'],
                'display_name' => 'Departments',
            ],
            'designations' => [
                'model' => Designation::class,
                'columns' => ['id', 'department_id', 'name', 'code', 'level', 'order_weight', 'description', 'is_active', 'created_at', 'updated_at'],
                'display_name' => 'Designations',
            ],
            'item_types' => [
                'model' => ItemType::class,
                'columns' => ['id', 'name', 'code', 'description', 'is_active', 'created_at', 'updated_at'],
                'display_name' => 'Item Types',
            ],
            'item_varieties' => [
                'model' => ItemVariety::class,
                'columns' => ['id', 'item_type_id', 'name', 'code', 'description', 'is_active', 'created_at', 'updated_at'],
                'display_name' => 'Item Varieties',
            ],
            'banks' => [
                'model' => Bank::class,
                'columns' => ['id', 'name', 'code', 'is_active', 'created_at', 'updated_at'],
                'display_name' => 'Banks',
            ],
            'suppliers' => [
                'model' => Supplier::class,
                'columns' => ['id', 'country_id', 'district_id', 'name', 'code', 'phone_primary', 'phone_secondary', 'email', 'address_line1', 'address_line2', 'city', 'id_type', 'id_number', 'payment_terms', 'outstanding_balance', 'notes', 'is_active', 'created_at', 'updated_at'],
                'display_name' => 'Suppliers',
            ],
            'warehouses' => [
                'model' => Warehouse::class,
                'columns' => ['id', 'branch_id', 'name', 'code', 'contact_person', 'phone_primary', 'phone_secondary', 'email', 'address_line1', 'address_line2', 'city', 'capacity_mt', 'description', 'is_active', 'created_at', 'updated_at'],
                'display_name' => 'Warehouses',
            ],
            'buyers' => [
                'model' => Buyer::class,
                'columns' => ['id', 'code', 'name', 'phone_primary', 'phone_secondary', 'email', 'address_line1', 'address_line2', 'city', 'country_id', 'district_id', 'tax_number', 'is_active', 'notes', 'created_by', 'updated_by', 'created_at', 'updated_at'],
                'display_name' => 'Buyers',
            ],
            'vehicles' => [
                'model' => Vehicle::class,
                'columns' => ['id', 'vehicle_number', 'vehicle_type', 'ownership_type', 'supplier_id', 'availability_status', 'tare_weight', 'is_active', 'created_by', 'updated_by', 'created_at', 'updated_at'],
                'display_name' => 'Vehicles',
            ],
            'users' => [
                'model' => User::class,
                'columns' => ['id', 'name', 'username', 'email', 'phone', 'user_scope', 'branch_id', 'warehouse_id', 'is_active', 'can_login', 'password_change_count', 'last_login_at', 'last_login_ip', 'created_at', 'updated_at'],
                'display_name' => 'Users',
            ],
            'employees' => [
                'model' => Employee::class,
                'columns' => ['id', 'f_name', 'l_name', 'full_name', 'name_with_initials', 'employee_code', 'reporting_manager_id', 'province_id', 'district_id', 'branch_id', 'department_id', 'designation_id', 'employee_type', 'id_type', 'id_number', 'date_of_birth', 'email', 'phone', 'address_line_1', 'city', 'state', 'country', 'postal_code', 'phone_primary', 'phone_secondary', 'have_whatsapp', 'whatsapp_number', 'start_date', 'end_date', 'is_active', 'created_at', 'updated_at'],
                'display_name' => 'Employees',
            ],
            'supplier_bank_accounts' => [
                'model' => SupplierBankAccount::class,
                'columns' => ['id', 'supplier_id', 'bank_id', 'bank_account_no', 'bank_branch', 'account_type', 'is_primary', 'is_active', 'notes', 'created_at', 'updated_at'],
                'display_name' => 'Supplier Bank Accounts',
            ],
            'stock_in_batches' => [
                'model' => StockInBatch::class,
                'columns' => ['id', 'batch_number', 'type', 'supplier_id', 'warehouse_id', 'vehicle_id', 'vehicle_log_id', 'received_date', 'gross_weight', 'tare_weight', 'net_weight', 'total_bags', 'total_amount', 'status', 'notes', 'created_by', 'updated_by', 'created_at', 'updated_at'],
                'display_name' => 'Stock In Batches',
            ],
            'stock_bags' => [
                'model' => StockBag::class,
                'columns' => ['id', 'bag_code', 'bag_number', 'stock_in_batch_id', 'stock_in_batch_item_id', 'branch_id', 'warehouse_id', 'supplier_id', 'item_type_id', 'item_variety_id', 'bag_weight', 'unit_price', 'selling_price', 'total_price', 'total_sales_amount', 'status', 'barcode_code', 'qr_code', 'location_id', 'notes', 'created_by', 'updated_by', 'created_at', 'updated_at'],
                'display_name' => 'Stock Bags',
            ],
            'stock_in_batch_items' => [
                'model' => StockInBatchItem::class,
                'columns' => ['id', 'stock_in_batch_id', 'item_type_id', 'item_variety_id', 'quantity_bags', 'unit_weight', 'total_weight', 'unit_price', 'total_price', 'remaining_quantity_bags', 'remaining_weight', 'notes', 'created_at', 'updated_at'],
                'display_name' => 'Stock In Batch Items',
            ],
            'stock_dispatches' => [
                'model' => StockDispatch::class,
                'columns' => ['id', 'dispatch_number', 'warehouse_id', 'branch_id', 'buyer_id', 'dispatch_type', 'dispatch_date', 'delivery_note_reference', 'vehicle_id', 'vehicle_log_id', 'total_bags', 'total_weight', 'total_sales_amount', 'status', 'gate_pass_number', 'gate_exit_at', 'notes', 'created_by', 'updated_by', 'created_at', 'updated_at'],
                'display_name' => 'Stock Dispatches',
            ],
            'dispatch_items' => [
                'model' => DispatchItem::class,
                'columns' => ['id', 'stock_dispatch_id', 'stock_bag_id', 'selling_price', 'bag_weight', 'notes', 'created_by', 'updated_by', 'created_at', 'updated_at'],
                'display_name' => 'Dispatch Items',
            ],
            'receipts' => [
                'model' => Receipt::class,
                'columns' => ['id', 'receipt_number', 'stock_in_batch_id', 'supplier_id', 'warehouse_id', 'branch_id', 'receipt_date', 'total_bags', 'total_weight', 'total_amount', 'status', 'notes', 'created_by', 'printed_at', 'printed_by', 'created_at', 'updated_at'],
                'display_name' => 'Receipts',
            ],
            'invoices' => [
                'model' => Invoice::class,
                'columns' => ['id', 'invoice_number', 'buyer_id', 'stock_dispatch_id', 'invoice_date', 'due_date', 'sub_total', 'discount_amount', 'tax_amount', 'total_amount', 'payment_status', 'payment_method', 'notes', 'created_by', 'updated_by', 'created_at', 'updated_at'],
                'display_name' => 'Invoices',
            ],
            'vehicle_logs' => [
                'model' => VehicleLog::class,
                'columns' => ['id', 'log_number', 'vehicle_id', 'log_type', 'direction', 'entry_time', 'exit_time', 'driver_name', 'driver_phone', 'driver_nic', 'purpose', 'notes', 'entry_license_plate_image', 'entry_vehicle_image', 'entry_document', 'exit_license_plate_image', 'exit_vehicle_image', 'exit_document', 'logged_by', 'created_at', 'updated_at'],
                'display_name' => 'Vehicle Logs',
            ],
            'barcode_token_batches' => [
                'model' => BarcodeTokenBatch::class,
                'columns' => ['id', 'batch_number', 'item_type_id', 'item_variety_id', 'token_type', 'quantity_requested', 'notes', 'created_by', 'created_at', 'updated_at'],
                'display_name' => 'Barcode Token Batches',
            ],
            'barcode_tokens' => [
                'model' => BarcodeToken::class,
                'columns' => ['id', 'barcode_token_batch_id', 'token_code', 'status', 'used_at', 'used_by', 'created_at', 'updated_at'],
                'display_name' => 'Barcode Tokens',
            ],
            'quality_inspections' => [
                'model' => QualityInspection::class,
                'columns' => ['id', 'stock_in_batch_id', 'stock_bag_id', 'item_type_id', 'item_variety_id', 'original_weight', 'current_weight', 'weight_difference', 'weight_change_type', 'moisture_percentage', 'grade', 'broken_percentage', 'colour_quality', 'inspection_result', 'remarks', 'inspected_by', 'inspected_at', 'created_at', 'updated_at'],
                'display_name' => 'Quality Inspections',
            ],
        ];
    }

    /**
     * Get list of exportable tables for API response
     */
    public function getExportableTableList(): array
    {
        $tables = $this->getExportableTables();
        $list = [];

        foreach ($tables as $key => $config) {
            $model = $config['model'];
            $count = $model::count();

            $list[] = [
                'id' => $key,
                'name' => $config['display_name'],
                'columns' => count($config['columns']),
                'record_count' => $count,
            ];
        }

        return $list;
    }

    /**
     * Export a single table to CSV with real data
     */
    public function exportTableToCsv(string $table): ?array
    {
        $tables = $this->getExportableTables();

        if (!isset($tables[$table])) {
            return null;
        }

        $config = $tables[$table];
        $model = $config['model'];
        $columns = $config['columns'];

        $records = $model::select($columns)->orderBy('id')->get();

        if ($records->isEmpty()) {
            return [
                'filename' => "{$table}_export.csv",
                'headers' => $columns,
                'data' => [],
                'record_count' => 0,
                'display_name' => $config['display_name'],
            ];
        }

        $data = $records->map(function ($record) use ($columns) {
            $row = [];
            foreach ($columns as $column) {
                $value = $record->{$column};
                $row[] = $value !== null ? (string) $value : '';
            }
            return $row;
        })->toArray();

        return [
            'filename' => "{$table}_export.csv",
            'headers' => $columns,
            'data' => $data,
            'record_count' => $records->count(),
            'display_name' => $config['display_name'],
        ];
    }

    /**
     * Export a single table to CSV file
     */
    public function exportTableToFile(string $table): ?string
    {
        $exportData = $this->exportTableToCsv($table);

        if (!$exportData) {
            return null;
        }

        $directory = storage_path('app/exports');

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $filePath = $directory . DIRECTORY_SEPARATOR . $exportData['filename'];
        $handle = fopen($filePath, 'w');

        // Add BOM for Excel compatibility
        fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // Write headers
        fputcsv($handle, $exportData['headers']);

        // Write data rows
        foreach ($exportData['data'] as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);

        return $filePath;
    }

    /**
     * Export all tables to individual CSV files
     */
    public function exportAllTablesToFiles(): array
    {
        $tables = $this->getExportableTables();
        $exportedFiles = [];

        $directory = storage_path('app/exports');

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        foreach (array_keys($tables) as $tableName) {
            $filePath = $this->exportTableToFile($tableName);

            if ($filePath) {
                $exportedFiles[] = [
                    'table' => $tableName,
                    'filename' => basename($filePath),
                    'path' => $filePath,
                ];
            }
        }

        return $exportedFiles;
    }

    /**
     * Generate a combined CSV with all tables (single file)
     */
    public function exportAllToSingleCsv(): string
    {
        $tables = $this->getExportableTables();

        $directory = storage_path('app/exports');

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $filename = "full_export_" . date('Y-m-d_H-i-s') . ".csv";
        $filePath = $directory . DIRECTORY_SEPARATOR . $filename;
        $handle = fopen($filePath, 'w');

        // Add BOM for Excel compatibility
        fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

        foreach ($tables as $tableName => $config) {
            $exportData = $this->exportTableToCsv($tableName);

            if (!$exportData || empty($exportData['data'])) {
                continue;
            }

            // Write table section header
            fputcsv($handle, ['=== ' . strtoupper($config['display_name']) . ' ===']);

            // Write column headers
            fputcsv($handle, $exportData['headers']);

            // Write data rows
            foreach ($exportData['data'] as $row) {
                fputcsv($handle, $row);
            }

            // Add empty row as separator
            fputcsv($handle, []);
        }

        fclose($handle);

        return $filePath;
    }

    /**
     * Export all tables to a single Excel-compatible XML file
     */
    public function exportAllToExcel(): string
    {
        $tables = $this->getExportableTables();

        $directory = storage_path('app/exports');

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $filename = "full_export_" . date('Y-m-d_H-i-s') . ".xls";
        $filePath = $directory . DIRECTORY_SEPARATOR . $filename;

        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<?mso-application progid="Excel.Sheet"?>';
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"';
        $xml .= ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
        $xml .= '<Styles>';
        $xml .= '<Style ss:ID="header">';
        $xml .= '<Font ss:Bold="1" ss:Color="#FFFFFF" ss:Size="11"/>';
        $xml .= '<Interior ss:Color="#4472C4" ss:Pattern="Solid"/>';
        $xml .= '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>';
        $xml .= '</Style>';
        $xml .= '<Style ss:ID="data">';
        $xml .= '<Font ss:Size="11"/>';
        $xml .= '</Style>';
        $xml .= '</Styles>';

        foreach ($tables as $tableName => $config) {
            $exportData = $this->exportTableToCsv($tableName);

            if (!$exportData) {
                continue;
            }

            $sheetName = substr($config['display_name'], 0, 31);
            $xml .= '<Worksheet ss:Name="' . htmlspecialchars($sheetName) . '">';
            $xml .= '<Table ss:DefaultColumnWidth="120" ss:DefaultRowHeight="20">';

            // Column widths
            foreach ($exportData['headers'] as $header) {
                $xml .= '<Column ss:Width="150"/>';
            }

            // Header row
            $xml .= '<Row ss:Height="22">';
            foreach ($exportData['headers'] as $header) {
                $xml .= '<Cell ss:StyleID="header"><Data ss:Type="String">' . htmlspecialchars($header) . '</Data></Cell>';
            }
            $xml .= '</Row>';

            // Data rows
            foreach ($exportData['data'] as $row) {
                $xml .= '<Row>';
                foreach ($row as $cellValue) {
                    $xml .= '<Cell ss:StyleID="data"><Data ss:Type="String">' . htmlspecialchars($cellValue) . '</Data></Cell>';
                }
                $xml .= '</Row>';
            }

            $xml .= '</Table>';
            $xml .= '</Worksheet>';
        }

        $xml .= '</Workbook>';

        file_put_contents($filePath, $xml);

        return $filePath;
    }
}
