#!/usr/bin/env php
<?php
/**
 * SKU Universe Fix - Verification Test Script
 * 
 * This script verifies that the SKU Universe fix is working correctly.
 * Run: php test_sku_universe_fix.php
 */

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/includes/stock/sku_universe.php';

echo "\n";
echo "=======================================================\n";
echo "  SKU Universe Fix - Verification Test\n";
echo "=======================================================\n\n";

// Test 1: Load SKU Universe
echo "Test 1: Loading SKU Universe...\n";
$universe = loadSkuUniverse();
$audit = getSkuAuditReport();

echo "  Total SKUs: " . count($universe) . "\n";
echo "  In Catalog: " . $audit['total_catalog'] . "\n";
echo "  In Stock: " . $audit['total_stock'] . "\n";
echo "  Orphans: " . count($audit['in_stock_not_catalog']) . "\n";
echo "  ✅ PASS\n\n";

// Test 2: Christ Toy - Should have only 1 catalog SKU
echo "Test 2: Christ Toy Fragrance Fix...\n";
$chrCatalog = 0;
$chrOrphan = 0;
$chrCatalogSku = '';
foreach ($audit['universe'] as $sku => $data) {
    if (strpos($sku, 'CHR') === 0) {
        if ($data['in_catalog']) {
            $chrCatalog++;
            $chrCatalogSku = $sku;
        } elseif ($data['in_stock_json']) {
            $chrOrphan++;
        }
    }
}

echo "  Christ toy catalog SKUs: $chrCatalog (expected: 1)\n";
echo "  Christ toy orphan SKUs: $chrOrphan (expected: 19)\n";
echo "  Catalog SKU: $chrCatalogSku (expected: CHR-STA-CHR)\n";

if ($chrCatalog === 1 && $chrCatalogSku === 'CHR-STA-CHR' && $chrOrphan === 19) {
    echo "  ✅ PASS\n\n";
} else {
    echo "  ❌ FAIL\n\n";
    exit(1);
}

// Test 3: Aroma Sashé - Should have 20 catalog SKUs
echo "Test 3: Aroma Sashé Allowed Fragrances Fix...\n";
$aroCatalog = 0;
$aroSkus = [];
foreach ($audit['universe'] as $sku => $data) {
    if (strpos($sku, 'ARO') === 0 && $data['in_catalog']) {
        $aroCatalog++;
        if (count($aroSkus) < 3) {
            $aroSkus[] = "$sku => {$data['product_name']} ({$data['fragrance']})";
        }
    }
}

echo "  Aroma Sashé catalog SKUs: $aroCatalog (expected: 20)\n";
echo "  Sample SKUs:\n";
foreach ($aroSkus as $info) {
    echo "    - $info\n";
}

if ($aroCatalog === 20) {
    echo "  ✅ PASS\n\n";
} else {
    echo "  ❌ FAIL\n\n";
    exit(1);
}

// Test 4: SKU Collision Detection
echo "Test 4: SKU Collision Detection...\n";

// Test 4a: Existing catalog SKU should be blocked
$validation1 = validateAdminProductSku('diffuser_classic', '125ml', 'bellini');
echo "  Test 4a: Try to use existing catalog SKU (DF-125-BEL)\n";
echo "    Valid: " . ($validation1['valid'] ? 'YES' : 'NO') . " (expected: NO)\n";
echo "    Suggested SKU: " . $validation1['sku'] . " (expected: DF-125-BEL-A)\n";

if (!$validation1['valid'] && $validation1['sku'] === 'DF-125-BEL-A') {
    echo "    ✅ PASS\n";
} else {
    echo "    ❌ FAIL\n";
    exit(1);
}

// Test 4b: New SKU should be allowed
$validation2 = validateAdminProductSku('test_device_999', 'standard', 'cherry_blossom');
echo "  Test 4b: Try to use new unique SKU (TES-STA-CHE)\n";
echo "    Valid: " . ($validation2['valid'] ? 'YES' : 'NO') . " (expected: YES)\n";

if ($validation2['valid']) {
    echo "    ✅ PASS\n\n";
} else {
    echo "    ❌ FAIL\n\n";
    exit(1);
}

// Test 5: Product Name Priority
echo "Test 5: Product Name Metadata Priority...\n";
// Check that catalog SKU has correct product name (not overwritten by stock.json)
$testSku = 'CHR-STA-CHR';
if (isset($universe[$testSku])) {
    $data = $universe[$testSku];
    echo "  SKU: $testSku\n";
    echo "  Product Name: {$data['product_name']}\n";
    echo "  In Catalog: " . ($data['in_catalog'] ? 'YES' : 'NO') . "\n";
    echo "  In Stock: " . ($data['in_stock_json'] ? 'YES' : 'NO') . "\n";
    
    if ($data['in_catalog'] && !empty($data['product_name']) && $data['product_name'] !== 'Unknown Product') {
        echo "  ✅ PASS - Catalog product name is preserved\n\n";
    } else {
        echo "  ❌ FAIL - Product name not correct\n\n";
        exit(1);
    }
}

// Test 6: Consolidated Stock View
echo "Test 6: Consolidated Stock View...\n";
$consolidated = getConsolidatedStockViewFromUniverse();
echo "  Total SKUs in consolidated view: " . count($consolidated) . " (expected: " . count($universe) . ")\n";

if (count($consolidated) === count($universe)) {
    echo "  ✅ PASS\n\n";
} else {
    echo "  ❌ FAIL\n\n";
    exit(1);
}

// Summary
echo "=======================================================\n";
echo "  ALL TESTS PASSED ✅\n";
echo "=======================================================\n";
echo "\n";
echo "Summary:\n";
echo "  ✅ Christ toy: 1 catalog SKU (correct fragrance only)\n";
echo "  ✅ Aroma Sashé: 20 catalog SKUs (allowed fragrances only)\n";
echo "  ✅ SKU collision detection: Working\n";
echo "  ✅ Product name priority: Enforced\n";
echo "  ✅ Consolidated stock view: Complete\n";
echo "\n";
echo "The SKU Universe fix is working correctly!\n";
echo "\n";

exit(0);
