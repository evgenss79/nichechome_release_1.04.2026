#!/usr/bin/env php
<?php
/**
 * Catalog Consistency Validation Tool
 * 
 * Validates SKU format, fragrance rules, product/accessory consistency,
 * and branch validity across all data files.
 * 
 * Usage: php tools/validate_catalog_consistency.php
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/stock/sku_universe.php';

echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║  NicheHome Catalog Consistency Validation Tool                ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "\n";

$allPassed = true;
$warnings = [];
$errors = [];

// Load all data
echo "📁 Loading data files...\n";
$products = loadJSON('products.json');
$categories = loadJSON('categories.json');
$accessories = loadJSON('accessories.json');
$stock = loadJSON('stock.json');
$branchStock = loadBranchStock();
$branches = getAllBranches();
$universe = loadSkuUniverse();

echo "   ✓ products.json: " . count($products) . " products\n";
echo "   ✓ categories.json: " . count($categories) . " categories\n";
echo "   ✓ accessories.json: " . count($accessories) . " accessories\n";
echo "   ✓ stock.json: " . count($stock) . " SKUs\n";
echo "   ✓ branches.json: " . count($branches) . " branches\n";
echo "   ✓ SKU Universe: " . count($universe) . " total SKUs\n";
echo "\n";

// Test 1: Validate 3-part SKU format (PREFIX-VOLUME-FRAGRANCE)
echo "🔍 Test 1: Validating SKU format (3-part: PREFIX-VOLUME-FRAGRANCE)...\n";
$formatViolations = [];

foreach ($universe as $sku => $data) {
    $parts = explode('-', $sku);
    if (count($parts) !== 3) {
        $formatViolations[] = $sku;
    }
}

if (empty($formatViolations)) {
    echo "   ✅ PASS: All SKUs follow 3-part format\n";
} else {
    echo "   ❌ FAIL: " . count($formatViolations) . " SKUs violate 3-part format:\n";
    foreach (array_slice($formatViolations, 0, 10) as $sku) {
        echo "      - $sku\n";
    }
    if (count($formatViolations) > 10) {
        echo "      ... and " . (count($formatViolations) - 10) . " more\n";
    }
    $allPassed = false;
    $errors[] = count($formatViolations) . " SKUs violate 3-part format";
}
echo "\n";

// Test 2: Verify non-fragrance items use fragrance=NA
echo "🔍 Test 2: Validating non-fragrance items use fragrance=NA...\n";
$fragranceViolations = [];

foreach ($products as $productId => $product) {
    $category = $product['category'] ?? '';
    
    // Check accessories without fragrance selector
    if ($category === 'accessories') {
        $hasFragranceSelector = false;
        $allowedFragrances = [];
        
        if (isset($accessories[$productId])) {
            $hasFragranceSelector = $accessories[$productId]['has_fragrance_selector'] ?? false;
            $allowedFragrances = $accessories[$productId]['allowed_fragrances'] ?? [];
        } elseif (isset($product['allowed_fragrances'])) {
            $hasFragranceSelector = true;
            $allowedFragrances = $product['allowed_fragrances'];
        }
        
        // If no fragrance selector and no fragrances, should use NA
        if (!$hasFragranceSelector && empty($allowedFragrances)) {
            // Check if any SKUs for this product use fragrances other than NA
            foreach ($universe as $sku => $skuData) {
                if ($skuData['productId'] === $productId) {
                    $fragrance = $skuData['fragrance'];
                    if (strtoupper($fragrance) !== 'NA' && $fragrance !== 'NA') {
                        $fragranceViolations[] = "$sku (has fragrance '$fragrance' but should be 'NA')";
                    }
                }
            }
        }
    }
}

if (empty($fragranceViolations)) {
    echo "   ✅ PASS: Non-fragrance items correctly use fragrance=NA\n";
} else {
    echo "   ⚠️  WARNING: " . count($fragranceViolations) . " potential fragrance violations:\n";
    foreach (array_slice($fragranceViolations, 0, 5) as $violation) {
        echo "      - $violation\n";
    }
    if (count($fragranceViolations) > 5) {
        echo "      ... and " . (count($fragranceViolations) - 5) . " more\n";
    }
    $warnings[] = count($fragranceViolations) . " non-fragrance items may not use NA correctly";
}
echo "\n";

// Test 3: Verify product category references are valid
echo "🔍 Test 3: Validating product category references...\n";
$missingCategories = [];

foreach ($products as $productId => $product) {
    $categoryId = $product['category'] ?? '';
    if ($categoryId === '' || isset($categories[$categoryId])) {
        continue;
    }
    $missingCategories[] = "$productId -> $categoryId";
}

if (empty($missingCategories)) {
    echo "   ✅ PASS: All product category references point to existing categories\n";
} else {
    echo "   ❌ FAIL: " . count($missingCategories) . " products reference missing categories:\n";
    foreach (array_slice($missingCategories, 0, 10) as $reference) {
        echo "      - $reference\n";
    }
    if (count($missingCategories) > 10) {
        echo "      ... and " . (count($missingCategories) - 10) . " more\n";
    }
    $allPassed = false;
    $errors[] = count($missingCategories) . " products reference missing categories";
}
echo "\n";

// Test 4: Verify accessories visibility/manageability
echo "🔍 Test 4: Validating accessories are visible/manageable...\n";
$orphanAccessories = [];
$missingFromProducts = [];

// Check accessories in products.json
foreach ($products as $productId => $product) {
    if (($product['category'] ?? '') === 'accessories') {
        if (!isset($accessories[$productId])) {
            $orphanAccessories[] = $productId;
        }
    }
}

// Check accessories.json items that aren't in products.json
foreach ($accessories as $accessoryId => $accessory) {
    if (!isset($products[$accessoryId])) {
        $missingFromProducts[] = $accessoryId;
    } elseif (($products[$accessoryId]['category'] ?? '') !== 'accessories') {
        $missingFromProducts[] = "$accessoryId (exists but category is not 'accessories')";
    }
}

if (empty($orphanAccessories) && empty($missingFromProducts)) {
    echo "   ✅ PASS: All accessories are properly configured\n";
} else {
    if (!empty($orphanAccessories)) {
        echo "   ⚠️  INFO: " . count($orphanAccessories) . " orphan accessories (in products.json but no config in accessories.json):\n";
        foreach (array_slice($orphanAccessories, 0, 5) as $id) {
            echo "      - $id (visible in admin, can create config)\n";
        }
        if (count($orphanAccessories) > 5) {
            echo "      ... and " . (count($orphanAccessories) - 5) . " more\n";
        }
        $warnings[] = count($orphanAccessories) . " orphan accessories (no config)";
    }
    
    if (!empty($missingFromProducts)) {
        echo "   ❌ FAIL: " . count($missingFromProducts) . " accessories in accessories.json but missing from products.json:\n";
        foreach ($missingFromProducts as $id) {
            echo "      - $id\n";
        }
        $allPassed = false;
        $errors[] = count($missingFromProducts) . " accessories missing from products.json";
    }
}
echo "\n";

// Test 5: Report branches that exist in data but not in canonical list
echo "🔍 Test 5: Validating branch consistency...\n";
$extraBranches = [];

// Check for branches in branch_stock.json that aren't in branches.json
foreach (array_keys($branchStock) as $branchId) {
    if (!isset($branches[$branchId])) {
        $extraBranches[] = $branchId;
    }
}

if (empty($extraBranches)) {
    echo "   ✅ PASS: All branches in branch_stock.json exist in branches.json\n";
} else {
    echo "   ❌ FAIL: " . count($extraBranches) . " branches in branch_stock.json but not in branches.json:\n";
    foreach ($extraBranches as $branchId) {
        $skuCount = isset($branchStock[$branchId]) ? count($branchStock[$branchId]) : 0;
        echo "      - $branchId ($skuCount SKUs)\n";
    }
    echo "   Action: Use admin panel to delete these branches\n";
    $allPassed = false;
    $errors[] = count($extraBranches) . " orphaned branches in branch_stock.json";
}
echo "\n";

// Test 6: Universe consistency check
echo "🔍 Test 6: Checking SKU Universe consistency...\n";
$diagnostics = getSkuUniverseDiagnostics();

if ($diagnostics['passed']) {
    echo "   ✅ PASS: SKU Universe is consistent\n";
} else {
    if (!empty($diagnostics['missing_in_stock_json'])) {
        echo "   ⚠️  WARNING: " . count($diagnostics['missing_in_stock_json']) . " SKUs missing in stock.json\n";
        echo "      Action: Run 'Sync SKU Universe' in admin stock page\n";
        $warnings[] = count($diagnostics['missing_in_stock_json']) . " SKUs missing in stock.json";
    }
    
    if (!empty($diagnostics['missing_in_branch_stock_json'])) {
        echo "   ⚠️  WARNING: " . count($diagnostics['missing_in_branch_stock_json']) . " SKUs missing in branch_stock.json\n";
        echo "      Action: Run 'Sync SKU Universe' in admin stock page\n";
        $warnings[] = count($diagnostics['missing_in_branch_stock_json']) . " SKUs missing in branch_stock.json";
    }
    
    if (!empty($diagnostics['extra_in_stock_json'])) {
        echo "   ℹ️  INFO: " . count($diagnostics['extra_in_stock_json']) . " orphaned SKUs in stock.json (not in catalog)\n";
    }
    
    if (!empty($diagnostics['extra_in_branch_stock_json'])) {
        echo "   ℹ️  INFO: " . count($diagnostics['extra_in_branch_stock_json']) . " orphaned SKUs in branch_stock.json (not in catalog)\n";
    }
}
echo "\n";

// Summary
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║  VALIDATION SUMMARY                                            ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "\n";

if ($allPassed && empty($warnings)) {
    echo "✅ ALL TESTS PASSED! Catalog is consistent.\n";
} else {
    if (!empty($errors)) {
        echo "❌ ERRORS (" . count($errors) . "):\n";
        foreach ($errors as $error) {
            echo "   - $error\n";
        }
        echo "\n";
    }
    
    if (!empty($warnings)) {
        echo "⚠️  WARNINGS (" . count($warnings) . "):\n";
        foreach ($warnings as $warning) {
            echo "   - $warning\n";
        }
        echo "\n";
    }
    
    if (!$allPassed) {
        echo "❌ VALIDATION FAILED - Please fix errors above\n";
        exit(1);
    } else {
        echo "⚠️  VALIDATION PASSED WITH WARNINGS - Review warnings above\n";
        exit(0);
    }
}

echo "\n";
exit(0);
