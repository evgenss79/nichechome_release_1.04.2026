<?php
/**
 * Branch Stock CSV Export with Date Selection
 * 
 * Exports branch stock data as CSV, with support for historical snapshots.
 * Uses SKU Universe for product metadata and the compatibility branch_stock.json
 * mirror (or its snapshots) for display quantities derived from stock.json.
 * 
 * Query Parameters:
 * - branch_id (required): The branch ID to export
 * - date (optional): Date for snapshot export (YYYY-MM-DD format, defaults to today)
 * 
 * This is a READ-ONLY operation - no stock data is modified.
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/stock/sku_universe.php';

if (!isAdminLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Get and validate parameters
$branchId = $_GET['branch_id'] ?? '';
$requestedDate = $_GET['date'] ?? date('Y-m-d');

// Validate branch exists
$branches = getAllBranches();
if (empty($branchId) || !isset($branches[$branchId])) {
    http_response_code(400);
    die('Error: Invalid branch ID specified.');
}

$branchName = $branches[$branchId];

// Validate and parse date
$dateObj = DateTime::createFromFormat('Y-m-d', $requestedDate);
if (!$dateObj || $dateObj->format('Y-m-d') !== $requestedDate) {
    http_response_code(400);
    die('Error: Invalid date format. Use YYYY-MM-DD.');
}

// Convert to timestamp (end of day)
$requestedTimestamp = strtotime($requestedDate . ' 23:59:59');

// Find appropriate snapshot or use current data
$snapshotUsed = null;
$branchStock = null;
$useCurrentData = false;

// Look for snapshot files
$backupDir = __DIR__ . '/../data/backups';
if (is_dir($backupDir)) {
    $snapshotFiles = glob($backupDir . '/branch_stock.*.json');
    
    if (!empty($snapshotFiles)) {
        $eligibleSnapshots = [];
        
        // Parse timestamps from filenames (format: branch_stock.YYYYMMDD-HHMMSS.json)
        foreach ($snapshotFiles as $file) {
            $basename = basename($file);
            if (preg_match('/branch_stock\.(\d{8})-(\d{6})\.json/', $basename, $matches)) {
                $fileDate = $matches[1]; // YYYYMMDD
                $fileTime = $matches[2]; // HHMMSS
                
                // Convert to timestamp
                $fileTimestamp = strtotime($fileDate . ' ' . 
                    substr($fileTime, 0, 2) . ':' . 
                    substr($fileTime, 2, 2) . ':' . 
                    substr($fileTime, 4, 2));
                
                // Include snapshots that are <= requested timestamp
                if ($fileTimestamp <= $requestedTimestamp) {
                    $eligibleSnapshots[$fileTimestamp] = $file;
                }
            }
        }
        
        // Select the latest eligible snapshot
        if (!empty($eligibleSnapshots)) {
            krsort($eligibleSnapshots); // Sort by timestamp descending
            $snapshotFile = reset($eligibleSnapshots); // Get the most recent
            $snapshotUsed = basename($snapshotFile);
            
            // Load snapshot data
            $snapshotContent = file_get_contents($snapshotFile);
            $branchStock = json_decode($snapshotContent, true);
            
            if ($branchStock === null) {
                // Failed to parse snapshot, fall back to current
                $useCurrentData = true;
            }
        } else {
            // No eligible snapshots found
            $useCurrentData = true;
        }
    } else {
        // No snapshot files exist
        $useCurrentData = true;
    }
} else {
    // Backup directory doesn't exist
    $useCurrentData = true;
}

// Fall back to current data if needed
if ($useCurrentData || $branchStock === null) {
    $branchStock = loadBranchStock();
    $snapshotUsed = null;
}

// Verify branch exists in the data
if (!isset($branchStock[$branchId])) {
    http_response_code(404);
    die('Error: Branch stock data not found for the specified branch.');
}

// Load SKU Universe for metadata
$universe = loadSkuUniverse();

// Build CSV data
$csvRows = [];

// Add header row
$csvRows[] = [
    'SKU',
    'Product Name',
    'Category',
    'Size/Pack',
    'Fragrance',
    'Quantity'
];

// Get branch stock for this branch
$branchData = $branchStock[$branchId];

// Iterate through all SKUs in universe (to include metadata)
foreach ($universe as $sku => $metadata) {
    // Safely get quantity - check if SKU exists in branch data
    $quantity = 0;
    if (isset($branchData[$sku]) && isset($branchData[$sku]['quantity'])) {
        $quantity = $branchData[$sku]['quantity'];
    }
    
    // Build row
    $csvRows[] = [
        $sku,
        $metadata['product_name'],
        $metadata['category'],
        $metadata['volume'],
        $metadata['fragrance'],
        $quantity
    ];
}

// Set CSV headers
$filename = 'branch_stock_' . $branchId . '_' . $requestedDate . '_' . date('His') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Add UTF-8 BOM for Excel compatibility
echo "\xEF\xBB\xBF";

// Add metadata comment row if using snapshot or current data notice
if ($snapshotUsed) {
    echo "# Export for branch: " . $branchName . " | Requested date: " . $requestedDate . " | Snapshot: " . $snapshotUsed . "\n";
} else {
    echo "# Export for branch: " . $branchName . " | Requested date: " . $requestedDate . " | Source: Current data (no snapshot available for selected date)\n";
}

// Write CSV data
$output = fopen('php://output', 'w');
foreach ($csvRows as $row) {
    fputcsv($output, $row);
}
fclose($output);
exit;
