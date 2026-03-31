#!/usr/bin/env php
<?php
/**
 * Test: Create accessory without fragrance selector
 * Verify NA SKU is generated and appears in Universe/stock
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/stock/sku_universe.php';

echo "=====================================\n";
echo "TEST: NA SKU GENERATION\n";
echo "=====================================\n\n";

// Test 1: Generate SKU with empty fragrance
echo "[TEST 1] Generate SKU with empty fragrance...\n";
$sku1 = generateSKU('test_product', 'standard', '');
echo "Result: {$sku1}\n";
$parts1 = explode('-', $sku1);
if (count($parts1) === 3 && $parts1[2] === 'NA') {
    echo "✓ PASS: Generated 3-part SKU with NA fragrance\n";
} else {
    echo "✗ FAIL: Expected 3-part SKU with NA, got: {$sku1}\n";
}
echo "\n";

// Test 2: Generate SKU with null fragrance
echo "[TEST 2] Generate SKU with 'null' string...\n";
$sku2 = generateSKU('test_product', 'standard', 'null');
echo "Result: {$sku2}\n";
$parts2 = explode('-', $sku2);
if (count($parts2) === 3 && $parts2[2] === 'NA') {
    echo "✓ PASS: Generated 3-part SKU with NA fragrance\n";
} else {
    echo "✗ FAIL: Expected 3-part SKU with NA, got: {$sku2}\n";
}
echo "\n";

// Test 3: Generate SKU with 'none' fragrance
echo "[TEST 3] Generate SKU with 'none' string...\n";
$sku3 = generateSKU('test_product', 'standard', 'none');
echo "Result: {$sku3}\n";
$parts3 = explode('-', $sku3);
if (count($parts3) === 3 && $parts3[2] === 'NA') {
    echo "✓ PASS: Generated 3-part SKU with NA fragrance\n";
} else {
    echo "✗ FAIL: Expected 3-part SKU with NA, got: {$sku3}\n";
}
echo "\n";

// Test 4: Generate SKU with actual fragrance
echo "[TEST 4] Generate SKU with actual fragrance (cherry_blossom)...\n";
$sku4 = generateSKU('test_product', 'standard', 'cherry_blossom');
echo "Result: {$sku4}\n";
$parts4 = explode('-', $sku4);
if (count($parts4) === 3 && $parts4[2] === 'CHE') {
    echo "✓ PASS: Generated 3-part SKU with fragrance code\n";
} else {
    echo "✗ FAIL: Expected TES-STA-CHE, got: {$sku4}\n";
}
echo "\n";

// Test 5: Check existing accessories in Universe
echo "[TEST 5] Check existing accessories in Universe...\n";
$universe = loadSkuUniverse();
$accessorySkus = [];
foreach ($universe as $sku => $data) {
    if ($data['category'] === 'accessories') {
        $accessorySkus[] = $sku;
    }
}
echo "Found " . count($accessorySkus) . " accessory SKUs in Universe\n";
echo "Sample accessory SKUs:\n";
foreach (array_slice($accessorySkus, 0, 10) as $sku) {
    $parts = explode('-', $sku);
    $hasNA = (isset($parts[2]) && $parts[2] === 'NA');
    echo "  - {$sku}" . ($hasNA ? " (NA fragrance)" : "") . "\n";
}
echo "\n";

// Test 6: Verify NA SKUs in diagnostics
echo "[TEST 6] Check NA SKUs in diagnostics...\n";
$diagnostics = getSkuUniverseDiagnostics();
$naCount = count($diagnostics['na_sku_list']);
echo "Found {$naCount} SKUs with NA fragrance\n";
if ($naCount > 0) {
    echo "Examples:\n";
    foreach (array_slice($diagnostics['na_sku_list'], 0, 5) as $sku) {
        echo "  - {$sku}\n";
    }
}
echo "\n";

echo "=====================================\n";
echo "TEST COMPLETE\n";
echo "=====================================\n";
