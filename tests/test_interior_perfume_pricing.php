#!/usr/bin/env php
<?php
/**
 * Test Price Change for Interior Perfume (home_perfume)
 * 
 * Specifically tests the Interior Perfume category mentioned in the issue
 */

require_once __DIR__ . '/../init.php';

define('GREEN', "\033[32m");
define('RED', "\033[31m");
define('YELLOW', "\033[33m");
define('BLUE', "\033[34m");
define('RESET', "\033[0m");

echo BLUE . "╔════════════════════════════════════════════════════════╗\n";
echo "║     Interior Perfume (home_perfume) Price Test         ║\n";
echo "╚════════════════════════════════════════════════════════╝" . RESET . "\n\n";

// Test product: home_spray (Interior Perfume category)
$testProductId = 'home_spray';
$testVolume = '50ml';  // Test the 50ml variant
$originalPrice = null;
$testPrice = 25.90;  // Temporary test price

echo "Testing: Interior Perfume - 50ml variant\n";
echo "Product ID: $testProductId\n";
echo "Category: home_perfume\n\n";

// Step 1: Record original price
echo YELLOW . "Step 1: Recording original price...\n" . RESET;
$products = loadJSON('products.json');
if (!isset($products[$testProductId])) {
    echo RED . "✗ FAIL: Product not found\n" . RESET;
    exit(1);
}

foreach ($products[$testProductId]['variants'] as $variant) {
    if (($variant['volume'] ?? '') === $testVolume) {
        $originalPrice = (float)($variant['priceCHF'] ?? 0);
        break;
    }
}

if ($originalPrice === null) {
    echo RED . "✗ FAIL: Variant not found\n" . RESET;
    exit(1);
}

echo GREEN . "✓ Original price: CHF $originalPrice\n" . RESET;
echo "\n";

// Step 2: Change price (simulating admin action)
echo YELLOW . "Step 2: Changing Interior Perfume 50ml price to CHF $testPrice...\n" . RESET;

$products = loadJSON('products.json');
$changed = false;
foreach ($products[$testProductId]['variants'] as $index => $variant) {
    if (($variant['volume'] ?? '') === $testVolume) {
        $products[$testProductId]['variants'][$index]['priceCHF'] = $testPrice;
        $changed = true;
        break;
    }
}

if (!$changed) {
    echo RED . "✗ FAIL: Could not change price\n" . RESET;
    exit(1);
}

if (!saveJSON('products.json', $products)) {
    echo RED . "✗ FAIL: Could not save products.json\n" . RESET;
    exit(1);
}

updateCatalogVersion();

echo GREEN . "✓ Price changed in products.json\n" . RESET;
echo GREEN . "✓ Catalog version updated\n" . RESET;
echo "\n";

// Step 3: Verify price is reflected in all resolvers
echo YELLOW . "Step 3: Verifying price in all systems...\n" . RESET;

$allMatch = true;

// 3a. Check products.json
$products = loadJSON('products.json');
$jsonPrice = null;
foreach ($products[$testProductId]['variants'] as $variant) {
    if (($variant['volume'] ?? '') === $testVolume) {
        $jsonPrice = (float)($variant['priceCHF'] ?? 0);
        break;
    }
}

echo "  products.json:           CHF " . number_format($jsonPrice, 2);
if ($jsonPrice === $testPrice) {
    echo GREEN . " ✓\n" . RESET;
} else {
    echo RED . " ✗ (expected CHF $testPrice)\n" . RESET;
    $allMatch = false;
}

// 3b. Check getProductPrice() (used by cart)
$cartPrice = getProductPrice($testProductId, $testVolume);
echo "  getProductPrice() (cart): CHF " . number_format($cartPrice, 2);
if ($cartPrice === $testPrice) {
    echo GREEN . " ✓\n" . RESET;
} else {
    echo RED . " ✗ (expected CHF $testPrice)\n" . RESET;
    $allMatch = false;
}

// 3c. Check getVariantPrice()
$variantPrice = getVariantPrice($testProductId, $testVolume);
echo "  getVariantPrice():        CHF " . number_format($variantPrice, 2);
if ($variantPrice === $testPrice) {
    echo GREEN . " ✓\n" . RESET;
} else {
    echo RED . " ✗ (expected CHF $testPrice)\n" . RESET;
    $allMatch = false;
}

// 3d. Simulate storefront: check that category.php would build correct window.PRICES
$category = 'home_perfume';
$categoryProducts = array_filter($products, function($p) use ($category) {
    return ($p['category'] ?? '') === $category;
});

if (!empty($categoryProducts)) {
    $firstProduct = reset($categoryProducts);
    $variants = $firstProduct['variants'] ?? [];
    
    // Build price map as category.php does
    $storefrontPrices = [];
    if (count($variants) > 1) {
        foreach ($variants as $variant) {
            $vol = $variant['volume'] ?? 'standard';
            $storefrontPrices[$vol] = (float)($variant['priceCHF'] ?? 0);
        }
    }
    
    $storefrontPrice = $storefrontPrices[$testVolume] ?? 0;
    echo "  Storefront (window.PRICES): CHF " . number_format($storefrontPrice, 2);
    if ($storefrontPrice === $testPrice) {
        echo GREEN . " ✓\n" . RESET;
    } else {
        echo RED . " ✗ (expected CHF $testPrice)\n" . RESET;
        $allMatch = false;
    }
}

echo "\n";

if (!$allMatch) {
    echo RED . "✗ FAIL: Price mismatch detected!\n" . RESET;
    echo YELLOW . "Restoring original price...\n" . RESET;
    
    // Restore
    $products = loadJSON('products.json');
    foreach ($products[$testProductId]['variants'] as $index => $variant) {
        if (($variant['volume'] ?? '') === $testVolume) {
            $products[$testProductId]['variants'][$index]['priceCHF'] = $originalPrice;
            break;
        }
    }
    saveJSON('products.json', $products);
    updateCatalogVersion();
    
    exit(1);
}

echo GREEN . "✓ All systems reflect new Interior Perfume price correctly!\n" . RESET;
echo "\n";

// Step 4: Restore original price
echo YELLOW . "Step 4: Restoring original price (CHF $originalPrice)...\n" . RESET;

$products = loadJSON('products.json');
foreach ($products[$testProductId]['variants'] as $index => $variant) {
    if (($variant['volume'] ?? '') === $testVolume) {
        $products[$testProductId]['variants'][$index]['priceCHF'] = $originalPrice;
        break;
    }
}

if (!saveJSON('products.json', $products)) {
    echo RED . "✗ FAIL: Could not restore original price\n" . RESET;
    exit(1);
}

updateCatalogVersion();

// Verify restoration
$restoredPrice = getProductPrice($testProductId, $testVolume);
if ($restoredPrice === $originalPrice) {
    echo GREEN . "✓ Original price restored: CHF $originalPrice\n" . RESET;
} else {
    echo RED . "✗ FAIL: Price restoration failed\n" . RESET;
    exit(1);
}

echo "\n";
echo GREEN . "╔════════════════════════════════════════════════════════╗\n";
echo "║  ✓ INTERIOR PERFUME TEST PASSED                       ║\n";
echo "║                                                        ║\n";
echo "║  Confirmed: Interior Perfume (home_perfume) pricing   ║\n";
echo "║  works correctly in cart AND storefront                ║\n";
echo "╚════════════════════════════════════════════════════════╝" . RESET . "\n";

exit(0);
