#!/usr/bin/env php
<?php
/**
 * SKU Universe Self-Test
 * 
 * Validates that:
 * 1. All SKUs are 3-part format (PREFIX-VOLUME-FRAGRANCE)
 * 2. Universe count equals expected catalog SKU count
 * 3. After sync, missing lists are empty
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/stock/sku_universe.php';

echo "=====================================\n";
echo "SKU UNIVERSE SELF-TEST\n";
echo "=====================================\n\n";

// Test 1: Load Universe
echo "[TEST 1] Loading SKU Universe...\n";
$universe = loadSkuUniverse();
$universeCount = count($universe);
echo "✓ Loaded {$universeCount} SKUs\n\n";

// Test 2: Validate 3-part format for ALL SKUs
echo "[TEST 2] Validating 3-part SKU format...\n";
$formatViolations = [];
$naSkus = [];

foreach ($universe as $sku => $data) {
    $parts = explode('-', $sku);
    
    if (count($parts) !== 3) {
        $formatViolations[] = $sku;
    }
    
    // Track NA SKUs
    if (isset($data['fragrance']) && strtoupper($data['fragrance']) === 'NA') {
        $naSkus[] = $sku;
    }
}

if (empty($formatViolations)) {
    echo "✓ All {$universeCount} SKUs follow 3-part format\n";
} else {
    echo "✗ FAIL: Found " . count($formatViolations) . " format violations:\n";
    foreach (array_slice($formatViolations, 0, 10) as $sku) {
        echo "  - {$sku}\n";
    }
    if (count($formatViolations) > 10) {
        echo "  ... and " . (count($formatViolations) - 10) . " more\n";
    }
}
echo "\n";

// Test 3: Check NA SKUs
echo "[TEST 3] Checking NA fragrance SKUs...\n";
echo "✓ Found " . count($naSkus) . " SKUs with fragrance=NA (no fragrance selector)\n";
if (!empty($naSkus)) {
    echo "Examples:\n";
    foreach (array_slice($naSkus, 0, 5) as $sku) {
        echo "  - {$sku} ({$universe[$sku]['product_name']})\n";
    }
}
echo "\n";

// Test 4: Get diagnostics
echo "[TEST 4] Running diagnostics...\n";
$diagnostics = getSkuUniverseDiagnostics();

echo "Universe count: {$diagnostics['universe_count']}\n";
echo "stock.json keys: {$diagnostics['stock_keys_count']}\n";
echo "branch_stock.json keys: {$diagnostics['branch_stock_total_keys_count']}\n";
echo "\n";

echo "Missing in stock.json: " . count($diagnostics['missing_in_stock_json']) . "\n";
echo "Missing in branch_stock.json: " . count($diagnostics['missing_in_branch_stock_json']) . "\n";
echo "Extra in stock.json: " . count($diagnostics['extra_in_stock_json']) . "\n";
echo "Extra in branch_stock.json: " . count($diagnostics['extra_in_branch_stock_json']) . "\n";
echo "Format violations: " . count($diagnostics['format_violations']) . "\n";
echo "\n";

// Test 5: Final verdict
echo "=====================================\n";
echo "FINAL VERDICT\n";
echo "=====================================\n";

$allPassed = empty($formatViolations) && $diagnostics['passed'];

if ($allPassed) {
    echo "✅ ALL TESTS PASSED\n";
    echo "SKU Universe is consistent and all SKUs follow 3-part format.\n";
    exit(0);
} else {
    echo "❌ TESTS FAILED\n";
    if (!empty($formatViolations)) {
        echo "- Format violations detected\n";
    }
    if (!$diagnostics['passed']) {
        echo "- Missing/extra SKUs detected\n";
        echo "  Run: php tools/stock_sync_dry_run.php to see what would be added\n";
    }
    exit(1);
}
