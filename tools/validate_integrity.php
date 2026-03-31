#!/usr/bin/env php
<?php
/**
 * Data Integrity Validation Tool
 * 
 * This tool validates:
 * 1. No deleted productIds still exist in stock.json/branch_stock.json
 * 2. No unknown branchIds in branch_stock.json
 * 3. All SKUs follow 3-part format (PREFIX-VOLUME-FRAGRANCE)
 * 4. All productIds referenced in stock exist in products.json
 * 
 * Exit codes:
 * - 0: All validations passed
 * - 1: One or more validations failed
 * 
 * Usage: php tools/validate_integrity.php
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/stock/sku_universe.php';

// ANSI color codes for terminal output
define('COLOR_GREEN', "\033[32m");
define('COLOR_RED', "\033[31m");
define('COLOR_YELLOW', "\033[33m");
define('COLOR_BLUE', "\033[34m");
define('COLOR_RESET', "\033[0m");
define('COLOR_BOLD', "\033[1m");

function printHeader(string $text): void {
    echo "\n";
    echo COLOR_BOLD . "╔" . str_repeat("═", strlen($text) + 2) . "╗" . COLOR_RESET . "\n";
    echo COLOR_BOLD . "║ " . $text . " ║" . COLOR_RESET . "\n";
    echo COLOR_BOLD . "╚" . str_repeat("═", strlen($text) + 2) . "╝" . COLOR_RESET . "\n";
    echo "\n";
}

function printTest(string $name): void {
    echo COLOR_BLUE . "🔍 Test: " . COLOR_RESET . $name . "\n";
}

function printPass(string $message): void {
    echo COLOR_GREEN . "   ✅ PASS: " . COLOR_RESET . $message . "\n";
}

function printFail(string $message): void {
    echo COLOR_RED . "   ❌ FAIL: " . COLOR_RESET . $message . "\n";
}

function printWarning(string $message): void {
    echo COLOR_YELLOW . "   ⚠️  WARN: " . COLOR_RESET . $message . "\n";
}

function printInfo(string $message): void {
    echo "   " . $message . "\n";
}

// Start validation
printHeader("NicheHome Data Integrity Validation Tool");

$hasErrors = false;
$hasWarnings = false;

// Load all data
echo "📁 Loading data files...\n";
try {
    $products = loadJSON('products.json');
    $stock = loadJSON('stock.json');
    $branchStock = loadBranchStock();
    $branches = loadBranches();
    $universe = loadSkuUniverse();
    
    printInfo("✓ products.json: " . count($products) . " products");
    printInfo("✓ stock.json: " . count($stock) . " SKUs");
    printInfo("✓ branch_stock.json: " . count($branchStock) . " branches");
    printInfo("✓ branches.json: " . count($branches) . " branches");
    printInfo("✓ SKU Universe: " . count($universe) . " total SKUs");
} catch (Exception $e) {
    printFail("Failed to load data files: " . $e->getMessage());
    exit(1);
}

echo "\n";

// Test 1: Validate SKU format (3-part: PREFIX-VOLUME-FRAGRANCE)
printTest("Validate SKU format (3-part: PREFIX-VOLUME-FRAGRANCE)");
$formatViolations = [];

foreach ($universe as $sku => $data) {
    $parts = explode('-', $sku);
    if (count($parts) !== 3) {
        $formatViolations[] = [
            'sku' => $sku,
            'parts' => count($parts),
            'productId' => $data['productId'] ?? 'unknown'
        ];
    }
}

if (empty($formatViolations)) {
    printPass("All " . count($universe) . " SKUs follow 3-part format");
} else {
    printFail(count($formatViolations) . " SKU(s) violate 3-part format rule");
    $hasErrors = true;
    
    foreach (array_slice($formatViolations, 0, 10) as $violation) {
        printInfo("  - SKU: " . $violation['sku'] . " (has " . $violation['parts'] . " parts, productId: " . $violation['productId'] . ")");
    }
    if (count($formatViolations) > 10) {
        printInfo("  ... and " . (count($formatViolations) - 10) . " more violations");
    }
}

echo "\n";

// Test 2: Validate no deleted productIds in stock files
printTest("Validate all productIds in stock.json exist in products.json");
$missingProducts = [];

foreach ($stock as $sku => $stockData) {
    $productId = $stockData['productId'] ?? '';
    if (!empty($productId) && !isset($products[$productId])) {
        if (!isset($missingProducts[$productId])) {
            $missingProducts[$productId] = [];
        }
        $missingProducts[$productId][] = $sku;
    }
}

if (empty($missingProducts)) {
    printPass("All productIds in stock.json exist in products.json");
} else {
    printFail(count($missingProducts) . " productId(s) in stock.json do not exist in products.json");
    $hasErrors = true;
    
    foreach (array_slice(array_keys($missingProducts), 0, 5) as $productId) {
        $skuCount = count($missingProducts[$productId]);
        printInfo("  - Product '$productId': $skuCount SKU(s) in stock.json");
        foreach (array_slice($missingProducts[$productId], 0, 3) as $sku) {
            printInfo("    → " . $sku);
        }
        if ($skuCount > 3) {
            printInfo("    ... and " . ($skuCount - 3) . " more SKUs");
        }
    }
    if (count($missingProducts) > 5) {
        printInfo("  ... and " . (count($missingProducts) - 5) . " more missing products");
    }
}

echo "\n";

// Test 3: Validate all productIds in branch_stock.json exist in products.json
printTest("Validate all productIds in branch_stock.json exist in products.json");
$missingProductsInBranch = [];

foreach ($branchStock as $branchId => $skus) {
    foreach ($skus as $sku => $data) {
        // Parse SKU to get productId (first part before first hyphen)
        $parts = explode('-', $sku);
        $productId = $parts[0] ?? '';
        
        // Also check if productId is in the data
        if (isset($data['productId'])) {
            $productId = $data['productId'];
        }
        
        // Check if this SKU exists in universe and get its productId
        if (isset($universe[$sku])) {
            $productId = $universe[$sku]['productId'];
        }
        
        if (!empty($productId) && !isset($products[$productId])) {
            if (!isset($missingProductsInBranch[$productId])) {
                $missingProductsInBranch[$productId] = 0;
            }
            $missingProductsInBranch[$productId]++;
        }
    }
}

if (empty($missingProductsInBranch)) {
    printPass("All productIds in branch_stock.json exist in products.json");
} else {
    printFail(count($missingProductsInBranch) . " productId(s) in branch_stock.json do not exist in products.json");
    $hasErrors = true;
    
    foreach (array_slice(array_keys($missingProductsInBranch), 0, 5) as $productId) {
        $count = $missingProductsInBranch[$productId];
        printInfo("  - Product '$productId': $count SKU entries across branches");
    }
    if (count($missingProductsInBranch) > 5) {
        printInfo("  ... and " . (count($missingProductsInBranch) - 5) . " more missing products");
    }
}

echo "\n";

// Test 4: Validate no unknown branchIds in branch_stock.json
printTest("Validate all branchIds in branch_stock.json exist in branches.json");
$unknownBranches = [];

foreach ($branchStock as $branchId => $skus) {
    if (!isset($branches[$branchId])) {
        $unknownBranches[$branchId] = count($skus);
    }
}

if (empty($unknownBranches)) {
    printPass("All branchIds in branch_stock.json exist in branches.json");
} else {
    printFail(count($unknownBranches) . " branchId(s) in branch_stock.json do not exist in branches.json");
    $hasErrors = true;
    
    foreach ($unknownBranches as $branchId => $skuCount) {
        printInfo("  - Branch '$branchId': $skuCount SKU entries");
    }
    printInfo("  Action: Delete these branches using admin/diagnostics.php or admin/branches.php");
}

echo "\n";

// Test 5: Check for non-fragrance items using correct fragrance=NA
printTest("Validate non-fragrance items use fragrance=NA");
$incorrectFragrances = [];

foreach ($universe as $sku => $data) {
    // Check if product has fragrance selector disabled
    $productId = $data['productId'] ?? '';
    if (empty($productId) || !isset($products[$productId])) {
        continue;
    }
    
    $product = $products[$productId];
    $category = $product['category'] ?? '';
    
    // Load accessories config if it's an accessory
    $hasFragranceSelector = false;
    if ($category === 'accessories') {
        $accessories = loadJSON('accessories.json');
        if (isset($accessories[$productId])) {
            $hasFragranceSelector = $accessories[$productId]['has_fragrance_selector'] ?? false;
        }
    } else {
        // For non-accessories, check if category allows fragrances
        $allowedFragrances = allowedFragrances($category);
        $hasFragranceSelector = !empty($allowedFragrances);
    }
    
    // If no fragrance selector, should use NA
    $fragrance = $data['fragrance'] ?? '';
    if (!$hasFragranceSelector && $fragrance !== 'NA' && $fragrance !== '') {
        $incorrectFragrances[] = [
            'sku' => $sku,
            'productId' => $productId,
            'fragrance' => $fragrance,
            'expected' => 'NA'
        ];
    }
}

if (empty($incorrectFragrances)) {
    printPass("All non-fragrance items correctly use fragrance=NA");
} else {
    printWarning(count($incorrectFragrances) . " SKU(s) for non-fragrance items don't use NA");
    $hasWarnings = true;
    
    foreach (array_slice($incorrectFragrances, 0, 5) as $item) {
        printInfo("  - SKU: " . $item['sku'] . " (productId: " . $item['productId'] . ", has: " . $item['fragrance'] . ", expected: NA)");
    }
    if (count($incorrectFragrances) > 5) {
        printInfo("  ... and " . (count($incorrectFragrances) - 5) . " more issues");
    }
}

echo "\n";

// Test 6: Check for orphan SKUs in stock files (SKUs not in catalog)
printTest("Validate no orphan SKUs in stock files");
$orphanSkusInStock = [];
$orphanSkusInBranchStock = [];

foreach ($stock as $sku => $stockData) {
    if (!isset($universe[$sku]) || !($universe[$sku]['in_catalog'] ?? false)) {
        $orphanSkusInStock[] = $sku;
    }
}

$branchSkusSeen = [];
foreach ($branchStock as $branchId => $skus) {
    foreach (array_keys($skus) as $sku) {
        if (!isset($branchSkusSeen[$sku])) {
            $branchSkusSeen[$sku] = true;
            if (!isset($universe[$sku]) || !($universe[$sku]['in_catalog'] ?? false)) {
                $orphanSkusInBranchStock[] = $sku;
            }
        }
    }
}

if (empty($orphanSkusInStock) && empty($orphanSkusInBranchStock)) {
    printPass("No orphan SKUs found in stock files");
} else {
    if (!empty($orphanSkusInStock)) {
        printWarning(count($orphanSkusInStock) . " orphan SKU(s) in stock.json (not in catalog)");
        $hasWarnings = true;
        foreach (array_slice($orphanSkusInStock, 0, 5) as $sku) {
            printInfo("  - " . $sku);
        }
        if (count($orphanSkusInStock) > 5) {
            printInfo("  ... and " . (count($orphanSkusInStock) - 5) . " more");
        }
    }
    
    if (!empty($orphanSkusInBranchStock)) {
        printWarning(count($orphanSkusInBranchStock) . " orphan SKU(s) in branch_stock.json (not in catalog)");
        $hasWarnings = true;
        foreach (array_slice($orphanSkusInBranchStock, 0, 5) as $sku) {
            printInfo("  - " . $sku);
        }
        if (count($orphanSkusInBranchStock) > 5) {
            printInfo("  ... and " . (count($orphanSkusInBranchStock) - 5) . " more");
        }
    }
    
    printInfo("  Action: These SKUs may be from deleted products. Review in admin/diagnostics.php");
}

echo "\n";

// Summary
printHeader("VALIDATION SUMMARY");

if ($hasErrors) {
    echo COLOR_RED . "❌ VALIDATION FAILED" . COLOR_RESET . "\n";
    echo "One or more critical errors were found. Please review the failures above.\n";
    echo "Use admin/diagnostics.php to identify and fix issues.\n";
    exit(1);
} elseif ($hasWarnings) {
    echo COLOR_YELLOW . "⚠️  VALIDATION PASSED WITH WARNINGS" . COLOR_RESET . "\n";
    echo "All critical checks passed, but some warnings were detected.\n";
    echo "Review the warnings above and consider fixing them.\n";
    exit(0);
} else {
    echo COLOR_GREEN . "✅ ALL VALIDATIONS PASSED" . COLOR_RESET . "\n";
    echo "Your data is consistent and properly structured.\n";
    exit(0);
}
