<?php
/**
 * Dynamic CSV Export from SKU Universe
 * 
 * Generates CSV template on-the-fly from current SKU Universe
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/stock/sku_universe.php';

if (!isAdminLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Set headers for CSV download
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="stock_template_' . date('Ymd_His') . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

// Add UTF-8 BOM for Excel compatibility
echo "\xEF\xBB\xBF";

// Load data
$universe = loadSkuUniverse();
$branches = getAllBranches();
$fragrances = loadJSON('fragrances.json');
$branchStock = loadBranchStock();

// Open output stream
$output = fopen('php://output', 'w');

// Build header row
$headers = ['sku', 'product_name', 'category', 'volume', 'fragrance_key', 'fragrance_label'];
foreach (array_keys($branches) as $branchId) {
    $headers[] = $branchId;
}
$headers[] = 'total';

// Write header
fputcsv($output, $headers);

// Write data rows
foreach ($universe as $sku => $data) {
    $productName = $data['product_name'];
    $category = $data['category'] ?? 'unknown';
    $volume = $data['volume'];
    $fragrance = $data['fragrance'];
    
    // Format fragrance label - handle NA specially
    $fragranceLabel = $fragrance;
    if (strtoupper($fragrance) === 'NA' || $fragrance === 'NA') {
        $fragranceLabel = 'No fragrance / Device';
    } else {
        // Get human-readable fragrance name
        if (isset($fragrances[$fragrance])) {
            $fragranceLabel = $fragrances[$fragrance]['name_en'] ?? ucfirst(str_replace('_', ' ', $fragrance));
        } else {
            $fragranceLabel = ucfirst(str_replace('_', ' ', $fragrance));
        }
    }
    
    // Build row
    $row = [
        $sku,
        $productName,
        $category,
        $volume,
        $fragrance,
        $fragranceLabel
    ];
    
    $total = 0;
    foreach (array_keys($branches) as $branchId) {
        $quantity = (int)($branchStock[$branchId][$sku]['quantity'] ?? 0);
        $row[] = $quantity;
        $total += $quantity;
    }
    $row[] = $total;
    
    // Write row
    fputcsv($output, $row);
}

fclose($output);
exit;
