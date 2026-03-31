<?php
/**
 * Generate Stock Import Templates (Excel and CSV)
 * Run this script to create/update templates
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/stock/sku_universe.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Csv as CsvWriter;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;

// Load data from SKU Universe (complete list)
$universe = loadSkuUniverse();
$branches = getAllBranches();

// Create spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Stock Import');

// Set up headers
$headers = ['sku', 'product_name', 'category', 'volume', 'fragrance_key', 'fragrance_label'];
foreach (array_keys($branches) as $branchId) {
    $headers[] = $branchId;
}
$headers[] = 'total';

// Write headers
$col = 1;
foreach ($headers as $header) {
    $sheet->setCellValueByColumnAndRow($col, 1, $header);
    $sheet->getStyleByColumnAndRow($col, 1)->getFont()->setBold(true);
    $sheet->getStyleByColumnAndRow($col, 1)->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setARGB('FFD4AF37');
    $col++;
}

// Auto-size columns
foreach (range(1, count($headers)) as $colNum) {
    $sheet->getColumnDimensionByColumn($colNum)->setAutoSize(true);
}

// Write data rows
$row = 2;
foreach ($universe as $sku => $data) {
    $productId = $data['productId'];
    $volume = $data['volume'];
    $fragrance = $data['fragrance'];
    $productName = $data['product_name'];
    $category = $data['category'] ?? 'unknown';
    
    // Format fragrance label - handle NA specially
    $fragranceLabel = $fragrance;
    if (strtoupper($fragrance) === 'NA' || $fragrance === 'NA') {
        $fragranceLabel = 'No fragrance / Device';
    } else {
        // Get human-readable fragrance name
        $fragrances = loadJSON('fragrances.json');
        if (isset($fragrances[$fragrance])) {
            $fragranceLabel = $fragrances[$fragrance]['name_en'] ?? ucfirst(str_replace('_', ' ', $fragrance));
        } else {
            $fragranceLabel = ucfirst(str_replace('_', ' ', $fragrance));
        }
    }
    
    // Get current branch quantities
    $branchStock = loadBranchStock();
    
    $col = 1;
    $sheet->setCellValueByColumnAndRow($col++, $row, $sku);
    $sheet->setCellValueByColumnAndRow($col++, $row, $productName);
    $sheet->setCellValueByColumnAndRow($col++, $row, $category);
    $sheet->setCellValueByColumnAndRow($col++, $row, $volume);
    $sheet->setCellValueByColumnAndRow($col++, $row, $fragrance);
    $sheet->setCellValueByColumnAndRow($col++, $row, $fragranceLabel);
    
    $totalFormula = [];
    foreach (array_keys($branches) as $branchId) {
        $qty = $branchStock[$branchId][$sku]['quantity'] ?? 0;
        $cellRef = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
        $sheet->setCellValueByColumnAndRow($col, $row, $qty);
        $totalFormula[] = $cellRef . $row;
        $col++;
    }
    
    // Add total formula
    $formula = '=SUM(' . implode(',', $totalFormula) . ')';
    $sheet->setCellValueByColumnAndRow($col, $row, $formula);
    
    $row++;
}

// Freeze header row
$sheet->freezePane('A2');

// Save Excel file
$xlsxWriter = new Xlsx($spreadsheet);
$xlsxPath = __DIR__ . '/templates/stock_import_template.xlsx';
$xlsxWriter->save($xlsxPath);
echo "Created: $xlsxPath\n";

// Save CSV file
$csvWriter = new CsvWriter($spreadsheet);
$csvPath = __DIR__ . '/templates/stock_import_template.csv';
$csvWriter->save($csvPath);
echo "Created: $csvPath\n";

echo "\nTemplates generated successfully!\n";
echo "Total SKUs: " . (count($universe)) . "\n";
echo "Branches: " . implode(', ', array_keys($branches)) . "\n";
