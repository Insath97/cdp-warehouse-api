<?php

namespace App\Console\Commands;

use App\Services\BulkImportService;
use Illuminate\Console\Command;

class GenerateExcelTemplate extends Command
{
    protected $signature = 'import:generate-excel {--output=storage/app/import_templates.xlsx : Output file path}';
    protected $description = 'Generate a single Excel file with all bulk import templates';

    public function handle(BulkImportService $importService)
    {
        $outputPath = $this->option('output');
        $directory = dirname($outputPath);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $tables = $importService->getImportableTables();
        $xml = $this->generateExcelXml($tables, $importService);

        file_put_contents($outputPath, $xml);

        $this->info("Excel template generated successfully: {$outputPath}");
        $this->info("Total tables: " . count($tables));
        $this->newLine();
        $this->info("You can open this file directly in Microsoft Excel or Google Sheets.");
        $this->info("Each sheet contains the column headers, required fields, and sample data for one table.");

        return Command::SUCCESS;
    }

    private function generateExcelXml(array $tables, BulkImportService $importService): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<?mso-application progid="Excel.Sheet"?>';
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"';
        $xml .= ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
        $xml .= '<Styles>';
        $xml .= '<Style ss:ID="header">';
        $xml .= '<Font ss:Bold="1" ss:Color="#FFFFFF" ss:Size="11"/>';
        $xml .= '<Interior ss:Color="#4472C4" ss:Pattern="Solid"/>';
        $xml .= '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>';
        $xml .= '<Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/></Borders>';
        $xml .= '</Style>';
        $xml .= '<Style ss:ID="required">';
        $xml .= '<Font ss:Bold="1" ss:Color="#FF0000" ss:Size="11"/>';
        $xml .= '<Interior ss:Color="#FFF2CC" ss:Pattern="Solid"/>';
        $xml .= '</Style>';
        $xml .= '<Style ss:ID="sampleHeader">';
        $xml .= '<Font ss:Bold="1" ss:Color="#FFFFFF" ss:Size="11"/>';
        $xml .= '<Interior ss:Color="#548235" ss:Pattern="Solid"/>';
        $xml .= '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>';
        $xml .= '</Style>';
        $xml .= '<Style ss:ID="sampleData">';
        $xml .= '<Interior ss:Color="#E2EFDA" ss:Pattern="Solid"/>';
        $xml .= '</Style>';
        $xml .= '<Style ss:ID="instructions">';
        $xml .= '<Font ss:Bold="1" ss:Color="#C00000" ss:Size="12"/>';
        $xml .= '</Style>';
        $xml .= '<Style ss:ID="instructionText">';
        $xml .= '<Font ss:Color="#404040" ss:Size="11"/>';
        $xml .= '</Style>';
        $xml .= '<Style ss:ID="title">';
        $xml .= '<Font ss:Bold="1" ss:Size="14" ss:Color="#2F5496"/>';
        $xml .= '</Style>';
        $xml .= '<Style ss:ID="subtitle">';
        $xml .= '<Font ss:Bold="1" ss:Size="11" ss:Color="#548235"/>';
        $xml .= '</Style>';
        $xml .= '</Styles>';

        foreach ($tables as $tableInfo) {
            $tableName = $tableInfo['table'];
            $headers = $tableInfo['headers'];
            $uniqueKey = $tableInfo['unique_key'];

            $template = $importService->getTemplateData($tableName);
            if (!$template) {
                continue;
            }

            $xml .= $this->generateSheet($tableName, $headers, $template['sample'], $uniqueKey, $tableInfo['name']);
        }

        $xml .= '</Workbook>';

        return $xml;
    }

    private function generateSheet(string $tableName, array $headers, array $sample, string $uniqueKey, string $displayName): string
    {
        $sheetName = substr(ucwords(str_replace('_', ' ', $tableName)), 0, 31);

        $xml = '<Worksheet ss:Name="' . htmlspecialchars($sheetName) . '">';
        $xml .= '<Table ss:DefaultColumnWidth="120" ss:DefaultRowHeight="20">';

        // Column widths
        $xml .= '<Column ss:Width="180"/>';  // Column Name
        $xml .= '<Column ss:Width="80"/>';   // Required
        $xml .= '<Column ss:Width="300"/>';  // Description

        // Title row
        $xml .= '<Row ss:Height="25">';
        $xml .= '<Cell ss:StyleID="title" ss:MergeAcross="4">';
        $xml .= '<Data ss:Type="String">Bulk Import Template: ' . htmlspecialchars($displayName) . '</Data>';
        $xml .= '</Cell>';
        $xml .= '</Row>';

        // Empty row
        $xml .= '<Row/>';

        // Header row for field info
        $xml .= '<Row ss:Height="22">';
        $xml .= '<Cell ss:StyleID="header"><Data ss:Type="String">Column Name</Data></Cell>';
        $xml .= '<Cell ss:StyleID="header"><Data ss:Type="String">Required</Data></Cell>';
        $xml .= '<Cell ss:StyleID="header"><Data ss:Type="String">Description</Data></Cell>';
        $xml .= '</Row>';

        // Field info rows
        foreach ($headers as $header) {
            $isUnique = $header === $uniqueKey;
            $styleId = $isUnique ? 'required' : '';

            $xml .= '<Row>';
            $xml .= '<Cell' . ($styleId ? ' ss:StyleID="' . $styleId . '"' : '') . '><Data ss:Type="String">' . htmlspecialchars($header) . '</Data></Cell>';
            $xml .= '<Cell' . ($styleId ? ' ss:StyleID="' . $styleId . '"' : '') . '><Data ss:Type="String">' . ($isUnique ? 'YES' : 'NO') . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars($this->getFieldDescription($header)) . '</Data></Cell>';
            $xml .= '</Row>';
        }

        // Empty row
        $xml .= '<Row/>';

        // Sample data section title
        $xml .= '<Row ss:Height="22">';
        $xml .= '<Cell ss:StyleID="subtitle" ss:MergeAcross="4">';
        $xml .= '<Data ss:Type="String">SAMPLE DATA ROW (Copy this row to start importing)</Data>';
        $xml .= '</Cell>';
        $xml .= '</Row>';

        // Sample data header row
        $xml .= '<Row ss:Height="22">';
        foreach ($headers as $header) {
            $xml .= '<Cell ss:StyleID="sampleHeader"><Data ss:Type="String">' . htmlspecialchars($header) . '</Data></Cell>';
        }
        $xml .= '</Row>';

        // Sample data row
        $xml .= '<Row ss:Height="20">';
        foreach ($headers as $index => $header) {
            $value = isset($sample[$index]) ? $sample[$index] : '';
            $xml .= '<Cell ss:StyleID="sampleData"><Data ss:Type="String">' . htmlspecialchars((string)$value) . '</Data></Cell>';
        }
        $xml .= '</Row>';

        // Empty row
        $xml .= '<Row/>';

        // Instructions section
        $xml .= '<Row ss:Height="22">';
        $xml .= '<Cell ss:StyleID="instructions" ss:MergeAcross="4">';
        $xml .= '<Data ss:Type="String">INSTRUCTIONS</Data>';
        $xml .= '</Cell>';
        $xml .= '</Row>';

        $instructions = [
            "1. Copy the sample data row above and paste it below",
            "2. Replace the sample values with your actual data",
            "3. The '{$uniqueKey}' column (marked as REQUIRED in red) must be unique for each row",
            "4. Foreign key columns ending with '_id' must reference existing database record IDs",
            "5. Date fields: Use YYYY-MM-DD format (e.g., 2026-01-15)",
            "6. Boolean fields: Use 1/0, true/false, yes/no, or on/off",
            "7. Phone numbers: Include country code (e.g., +94771234567)",
            "8. Leave optional fields empty if not applicable",
            "9. Save the file as CSV format before importing",
            "10. Import order matters - import parent tables first (e.g., countries before provinces)",
        ];

        foreach ($instructions as $instruction) {
            $xml .= '<Row>';
            $xml .= '<Cell ss:StyleID="instructionText" ss:MergeAcross="4">';
            $xml .= '<Data ss:Type="String">' . htmlspecialchars($instruction) . '</Data>';
            $xml .= '</Cell>';
            $xml .= '</Row>';
        }

        $xml .= '</Table>';
        $xml .= '</Worksheet>';

        return $xml;
    }

    private function getFieldDescription(string $field): string
    {
        $descriptions = [
            'name' => 'Name of the record',
            'code' => 'Unique code identifier',
            'description' => 'Description text',
            'is_active' => 'Active status: 1=Active, 0=Inactive',
            'country_id' => 'Database ID of the country',
            'province_id' => 'Database ID of the province',
            'district_id' => 'Database ID of the district',
            'group_id' => 'Database ID of the group',
            'branch_id' => 'Database ID of the branch',
            'department_id' => 'Database ID of the department',
            'designation_id' => 'Database ID of the designation',
            'item_type_id' => 'Database ID of the item type',
            'item_variety_id' => 'Database ID of the item variety',
            'supplier_id' => 'Database ID of the supplier',
            'warehouse_id' => 'Database ID of the warehouse',
            'vehicle_id' => 'Database ID of the vehicle',
            'buyer_id' => 'Database ID of the buyer',
            'bank_id' => 'Database ID of the bank',
            'stock_in_batch_id' => 'Database ID of the stock-in batch',
            'stock_dispatch_id' => 'Database ID of the stock dispatch',
            'stock_bag_id' => 'Database ID of the stock bag',
            'barcode_token_batch_id' => 'Database ID of the barcode token batch',
            'created_by' => 'Database ID of the user who created the record',
            'updated_by' => 'Database ID of the user who last updated',
            'logged_by' => 'Database ID of the user who logged the entry',
            'inspected_by' => 'Database ID of the user who performed inspection',
            'used_by' => 'Database ID of the user who used the token',
            'address_line1' => 'Primary address line',
            'address_line2' => 'Secondary address line',
            'city' => 'City name',
            'postal_code' => 'Postal/ZIP code',
            'phone_primary' => 'Primary phone with country code',
            'phone_secondary' => 'Secondary phone with country code',
            'phone' => 'Phone number with country code',
            'email' => 'Email address',
            'fax' => 'Fax number',
            'opening_date' => 'Date format: YYYY-MM-DD',
            'branch_type' => 'Type: main/branch/sub-branch',
            'latitude' => 'GPS latitude coordinate',
            'longitude' => 'GPS longitude coordinate',
            'is_head_office' => '1=Head office, 0=Regular branch',
            'level' => 'Level: junior/mid/senior',
            'order_weight' => 'Sorting weight (numeric)',
            'id_type' => 'ID type: nic/passport/license',
            'id_number' => 'Identification number',
            'payment_terms' => 'Payment terms (e.g., Net 30)',
            'notes' => 'Additional notes',
            'username' => 'Login username (unique)',
            'password' => 'Login password (min 6 chars)',
            'user_type' => 'Type: admin/staff',
            'can_login' => '1=Can login, 0=Cannot login',
            'role' => 'Role name (must exist in roles table)',
            'brand_name' => 'Brand/trade name',
            'company_name' => 'Registered company name',
            'address' => 'Full address',
            'tax_number' => 'Tax registration number',
            'vehicle_number' => 'Vehicle registration number (unique)',
            'vehicle_type' => 'Type: lorry/truck/van/tractor',
            'ownership_type' => 'Type: own/rented/contractor',
            'tare_weight' => 'Empty weight in kg',
            'availability_status' => 'Status: available/in_transit/maintenance',
            'f_name' => 'First name',
            'l_name' => 'Last name',
            'full_name' => 'Full name',
            'name_with_initials' => 'Name with initials',
            'employee_code' => 'Employee code (unique)',
            'employee_type' => 'Type: permanent/contract/intern',
            'date_of_birth' => 'Date format: YYYY-MM-DD',
            'address_line_1' => 'Primary address',
            'state' => 'State/Province',
            'country' => 'Country name',
            'have_whatsapp' => '1=Has WhatsApp, 0=No',
            'whatsapp_number' => 'WhatsApp number with code',
            'start_date' => 'Employment start date',
            'end_date' => 'Employment end date',
            'joined_at' => 'Joining date/time',
            'bank_account_no' => 'Bank account number (unique)',
            'bank_branch' => 'Bank branch name',
            'account_type' => 'Type: savings/current',
            'is_primary' => '1=Primary account, 0=Secondary',
            'batch_number' => 'Auto-generated or custom batch number',
            'type' => 'Type: purchase/return/adjustment',
            'gross_weight' => 'Total weight including packaging',
            'net_weight' => 'Weight excluding packaging',
            'total_bags' => 'Total number of bags',
            'total_amount' => 'Total monetary value',
            'status' => 'Status: pending/received/cancelled',
            'bag_number' => 'Bag sequence number',
            'bag_weight' => 'Individual bag weight in kg',
            'unit_price' => 'Price per unit',
            'selling_price' => 'Selling price per unit',
            'total_price' => 'Total price for quantity',
            'total_sales_amount' => 'Total sales value',
            'location_id' => 'Storage location identifier',
            'quantity_bags' => 'Number of bags',
            'unit_weight' => 'Weight per unit',
            'total_weight' => 'Total weight',
            'remaining_quantity_bags' => 'Remaining bag count',
            'remaining_weight' => 'Remaining weight',
            'dispatch_number' => 'Dispatch reference number (unique)',
            'dispatch_type' => 'Type: sale/transfer/return',
            'dispatch_date' => 'Date format: YYYY-MM-DD',
            'delivery_note_reference' => 'External delivery note number',
            'gate_pass_number' => 'Gate pass reference',
            'gate_exit_at' => 'DateTime format: YYYY-MM-DD HH:MM:SS',
            'receipt_number' => 'Receipt reference number (unique)',
            'receipt_date' => 'Date format: YYYY-MM-DD',
            'invoice_number' => 'Invoice reference number (unique)',
            'invoice_date' => 'Date format: YYYY-MM-DD',
            'due_date' => 'Payment due date',
            'sub_total' => 'Subtotal before tax/discount',
            'discount_amount' => 'Discount value',
            'tax_amount' => 'Tax amount',
            'payment_status' => 'Status: unpaid/partial/paid',
            'payment_method' => 'Method: cash/bank_transfer/cheque',
            'log_number' => 'Vehicle log number (unique)',
            'log_type' => 'Type: inbound/outbound',
            'direction' => 'Direction: in/out',
            'entry_time' => 'DateTime format: YYYY-MM-DD HH:MM:SS',
            'exit_time' => 'DateTime format: YYYY-MM-DD HH:MM:SS',
            'driver_name' => 'Driver full name',
            'driver_phone' => 'Driver phone number',
            'driver_nic' => 'Driver NIC number',
            'purpose' => 'Purpose of visit',
            'token_type' => 'Type: EAN-13/QR-CODE/CUSTOM',
            'quantity_requested' => 'Number of tokens requested',
            'token_code' => 'Unique token code',
            'used_at' => 'Usage timestamp',
            'original_weight' => 'Original weight at inspection',
            'current_weight' => 'Current weight at inspection',
            'weight_difference' => 'Calculated weight difference',
            'weight_change_type' => 'Type: weight_loss/weight_gain/no_change',
            'moisture_percentage' => 'Moisture content percentage',
            'grade' => 'Quality grade: A/B/C/D',
            'broken_percentage' => 'Broken percentage',
            'colour_quality' => 'Quality: excellent/good/fair/poor',
            'inspection_result' => 'Result: passed/failed/pending',
            'remarks' => 'Inspector remarks',
            'inspected_at' => 'Inspection timestamp',
        ];

        return $descriptions[$field] ?? ucfirst(str_replace('_', ' ', $field));
    }
}
