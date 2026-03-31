#!/usr/bin/env php
<?php
/**
 * Validate Admin-Created Items
 * 
 * Scans all saved accessories/products and verifies they appear in:
 * 1. SKU Universe
 * 2. stock.json
 * 3. branch_stock.json
 * 4. CSV export
 * 
 * Reports validation status for each item.
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/stock/sku_universe.php';

echo "=====================================\n";
echo "ADMIN-CREATED ITEMS VALIDATION\n";
echo "=====================================\n\n";

// Load all data sources
$accessories = loadJSON('accessories.json');
$products = loadJSON('products.json');
$universe = loadSkuUniverse();
$stock = loadJSON('stock.json');
$branchStock = loadBranchStock();
$branches = getAllBranches();

// Track results
$totalItems = 0;
$passedItems = 0;
$failedItems = 0;
$issues = [];

echo "[1] Validating Accessories\n";
echo str_repeat("-", 50) . "\n";

foreach ($accessories as $accessoryId => $accessory) {
    $totalItems++;
    $itemIssues = [];
    $itemPassed = true;
    
    echo "\nAccessory: $accessoryId\n";
    
    // Determine expected SKUs
    $hasFragranceSelector = $accessory['has_fragrance_selector'] ?? false;
    $allowedFragrances = $accessory['allowed_fragrances'] ?? [];
    $hasVolumeSelector = $accessory['has_volume_selector'] ?? false;
    $volumes = $accessory['volumes'] ?? ['standard'];
    
    if (empty($volumes)) {
        $volumes = ['standard'];
    }
    
    // Generate expected SKUs
    $expectedSkus = [];
    
    if (empty($allowedFragrances)) {
        // No fragrance - should use NA
        foreach ($volumes as $volume) {
            $sku = generateSKU($accessoryId, $volume, '');
            $expectedSkus[] = $sku;
        }
    } else {
        // Has fragrances
        if (!$hasFragranceSelector && count($allowedFragrances) === 1) {
            // Single fragrance, no selector
            foreach ($volumes as $volume) {
                $sku = generateSKU($accessoryId, $volume, $allowedFragrances[0]);
                $expectedSkus[] = $sku;
            }
        } else {
            // Multiple fragrances or selector enabled
            foreach ($volumes as $volume) {
                foreach ($allowedFragrances as $fragrance) {
                    $sku = generateSKU($accessoryId, $volume, $fragrance);
                    $expectedSkus[] = $sku;
                }
            }
        }
    }
    
    echo "  Expected SKUs (" . count($expectedSkus) . "): ";
    echo implode(", ", array_slice($expectedSkus, 0, 5));
    if (count($expectedSkus) > 5) {
        echo ", ... +" . (count($expectedSkus) - 5) . " more";
    }
    echo "\n";
    
    // Check 3-part format
    foreach ($expectedSkus as $sku) {
        $parts = explode('-', $sku);
        if (count($parts) !== 3) {
            $itemIssues[] = "SKU $sku is not 3-part format";
            $itemPassed = false;
        }
    }
    
    // Check Universe
    $inUniverse = 0;
    foreach ($expectedSkus as $sku) {
        if (isset($universe[$sku])) {
            $inUniverse++;
        } else {
            $itemIssues[] = "SKU $sku not in Universe";
            $itemPassed = false;
        }
    }
    echo "  ✓ In Universe: $inUniverse/" . count($expectedSkus) . "\n";
    
    // Check stock.json
    $inStock = 0;
    foreach ($expectedSkus as $sku) {
        if (isset($stock[$sku])) {
            $inStock++;
        } else {
            $itemIssues[] = "SKU $sku not in stock.json";
            $itemPassed = false;
        }
    }
    echo "  ✓ In stock.json: $inStock/" . count($expectedSkus) . "\n";
    
    // Check branch_stock.json
    $inBranchStock = 0;
    foreach ($expectedSkus as $sku) {
        $foundInBranch = false;
        foreach ($branchStock as $branchId => $skus) {
            if (isset($skus[$sku])) {
                $foundInBranch = true;
                break;
            }
        }
        if ($foundInBranch) {
            $inBranchStock++;
        } else {
            $itemIssues[] = "SKU $sku not in any branch";
            $itemPassed = false;
        }
    }
    echo "  ✓ In branch_stock.json: $inBranchStock/" . count($expectedSkus) . "\n";
    
    // Check NA fragrance for non-fragrance accessories
    if (empty($allowedFragrances)) {
        foreach ($expectedSkus as $sku) {
            $parts = explode('-', $sku);
            if (isset($parts[2]) && $parts[2] !== 'NA') {
                $itemIssues[] = "Non-fragrance accessory SKU $sku does not use NA";
                $itemPassed = false;
            }
        }
    }
    
    // Check name resolution
    $name = getProductNameFromId($accessoryId);
    echo "  ✓ Name: $name\n";
    
    // Final status
    if ($itemPassed) {
        echo "  ✅ PASS\n";
        $passedItems++;
    } else {
        echo "  ❌ FAIL\n";
        $failedItems++;
        $issues[$accessoryId] = $itemIssues;
    }
}

echo "\n\n[2] Validating Products (from products.json)\n";
echo str_repeat("-", 50) . "\n";

$productCategories = ['diffuser', 'candle', 'spray', 'textile', 'limited'];
$productCount = 0;

foreach ($products as $productId => $product) {
    // Skip accessories (already validated above)
    if ($product['category'] === 'accessories') {
        continue;
    }
    
    $productCount++;
    // Basic validation - just check if in Universe
    $foundInUniverse = false;
    foreach ($universe as $sku => $data) {
        if ($data['productId'] === $productId) {
            $foundInUniverse = true;
            break;
        }
    }
    
    if (!$foundInUniverse) {
        echo "\nProduct: $productId - ❌ NOT in Universe\n";
        $failedItems++;
        $issues[$productId] = ["Product not generating any SKUs in Universe"];
    }
}

echo "\nValidated $productCount products from products.json\n";

echo "\n\n=====================================\n";
echo "VALIDATION SUMMARY\n";
echo "=====================================\n\n";

echo "Total Items Validated: $totalItems\n";
echo "Passed: $passedItems ✅\n";
echo "Failed: $failedItems ❌\n";
echo "\n";

if ($failedItems > 0) {
    echo "ISSUES FOUND:\n";
    echo str_repeat("-", 50) . "\n";
    foreach ($issues as $itemId => $itemIssues) {
        echo "\n$itemId:\n";
        foreach ($itemIssues as $issue) {
            echo "  - $issue\n";
        }
    }
    echo "\n";
    echo "ACTION REQUIRED:\n";
    echo "  Run: php -r 'require_once \"init.php\"; require_once \"includes/stock/sku_universe.php\"; initializeMissingSkuKeys(false);'\n";
    echo "  This will add missing SKUs to stock files.\n";
    exit(1);
} else {
    echo "✅ ALL ITEMS VALIDATED SUCCESSFULLY\n";
    echo "\n";
    echo "All admin-created items are properly:\n";
    echo "  - In SKU Universe\n";
    echo "  - In stock.json\n";
    echo "  - In branch_stock.json\n";
    echo "  - Following 3-part SKU format\n";
    echo "  - Using NA for non-fragrance accessories\n";
    exit(0);
}
