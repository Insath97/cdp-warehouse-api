<?php

namespace App\Console\Commands;

use App\Services\BulkImportService;
use Illuminate\Console\Command;

class GenerateImportTemplates extends Command
{
    protected $signature = 'import:generate-templates {--output=storage/app/import_templates : Output directory}';
    protected $description = 'Generate CSV templates for all bulk import tables';

    public function handle(BulkImportService $importService)
    {
        $outputDir = $this->option('output');

        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $tables = $importService->getImportableTables();
        $generatedFiles = [];

        foreach ($tables as $tableInfo) {
            $tableName = $tableInfo['table'];
            $headers = $tableInfo['headers'];
            $uniqueKey = $tableInfo['unique_key'];

            $template = $importService->getTemplateData($tableName);
            if (!$template) {
                continue;
            }

            $filename = "{$tableName}_import_template.csv";
            $filepath = "{$outputDir}/{$filename}";

            $this->generateCSV($filepath, $headers, $template['sample'], $uniqueKey, $tableInfo['name']);
            $generatedFiles[] = $filename;
        }

        $this->info("Generated " . count($generatedFiles) . " template files in: {$outputDir}");
        $this->newLine();
        $this->info("Files generated:");
        foreach ($generatedFiles as $file) {
            $this->line("  - {$file}");
        }

        return Command::SUCCESS;
    }

    private function generateCSV(string $filepath, array $headers, array $sample, string $uniqueKey, string $tableName): void
    {
        $handle = fopen($filepath, 'w');

        // Add BOM for Excel compatibility
        fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

        // Write header row
        fputcsv($handle, $headers);

        // Write sample data row
        fputcsv($handle, $sample);

        fclose($handle);
    }
}
