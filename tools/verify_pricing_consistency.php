#!/usr/bin/env php
<?php
/**
 * Verify Pricing Consistency
 * 
 * This tool verifies that prices are consistent across:
 * - products.json data source
 * - getProductPrice() function (used by cart)
 * - getDefaultDisplayedPrice() function (used by storefront)
 * 
 * Tests 3 known products with variants to ensure consistency.
 */

require_once __DIR__ . '/../init.php';

// ANSI color codes for terminal output
define('COLOR_GREEN', "\033[32m");
define('COLOR_RED', "\033[31m");
define('COLOR_YELLOW', "\033[33m");
define('COLOR_BLUE', "\033[34m");
define('COLOR_RESET', "\033[0m");

echo COLOR_BLUE . "╔════════════════════════════════════════════════════════╗\n";
echo "║       Price Consistency Verification Tool             ║\n";
echo "╚════════════════════════════════════════════════════════╝" . COLOR_RESET . "\n\n";

// Test products - 3 products with multiple variants
$testProducts = [
    [
        'id' => 'diffuser_classic',
        'name' => 'Classic Aroma Diffuser',
        'variants' => [
            ['volume' => '125ml', 'expectedPrice' => 20.90],
            ['volume' => '250ml', 'expectedPrice' => 29.90],
            ['volume' => '500ml', 'expectedPrice' => 50.90]
        ]
    ],
    [
        'id' => 'candle_classic',
        'name' => 'Classic Scented Candle',
        'variants' => [
            ['volume' => '160ml', 'expectedPrice' => 24.90],
            ['volume' => '500ml', 'expectedPrice' => 59.90]
        ]
    ],
    [
        'id' => 'home_spray',
        'name' => 'Home Perfume Spray',
        'variants' => [
            ['volume' => '10ml', 'expectedPrice' => 9.90],
            ['volume' => '50ml', 'expectedPrice' => 19.90]
        ]
    ]
];

$allPassed = true;
$totalTests = 0;
$passedTests = 0;

// Load products.json
echo COLOR_YELLOW . "Loading products.json..." . COLOR_RESET . "\n";
$products = loadJSON('products.json');

if (empty($products)) {
    echo COLOR_RED . "✗ FAIL: Could not load products.json\n" . COLOR_RESET;
    exit(1);
}

echo COLOR_GREEN . "✓ Successfully loaded " . count($products) . " products\n" . COLOR_RESET;
echo "\n";

// Test each product
foreach ($testProducts as $testProduct) {
    $productId = $testProduct['id'];
    $productName = $testProduct['name'];
    
    echo COLOR_BLUE . "Testing: $productName ($productId)" . COLOR_RESET . "\n";
    echo str_repeat("─", 60) . "\n";
    
    // Check if product exists in products.json
    if (!isset($products[$productId])) {
        echo COLOR_RED . "✗ FAIL: Product not found in products.json\n" . COLOR_RESET;
        $allPassed = false;
        echo "\n";
        continue;
    }
    
    $product = $products[$productId];
    $productVariants = $product['variants'] ?? [];
    
    if (empty($productVariants)) {
        echo COLOR_RED . "✗ FAIL: No variants found for product\n" . COLOR_RESET;
        $allPassed = false;
        echo "\n";
        continue;
    }
    
    // Test default displayed price
    echo "\nTest: Default Displayed Price\n";
    $totalTests++;
    $defaultPrice = getDefaultDisplayedPrice($productId);
    $firstVariantPrice = (float)($productVariants[0]['priceCHF'] ?? 0);
    
    if ($defaultPrice === $firstVariantPrice && $defaultPrice > 0) {
        echo COLOR_GREEN . "✓ PASS: getDefaultDisplayedPrice() = CHF $defaultPrice (matches first variant)\n" . COLOR_RESET;
        $passedTests++;
    } else {
        echo COLOR_RED . "✗ FAIL: getDefaultDisplayedPrice() = CHF $defaultPrice, expected CHF $firstVariantPrice\n" . COLOR_RESET;
        $allPassed = false;
    }
    
    // Test each variant
    foreach ($testProduct['variants'] as $testVariant) {
        $volume = $testVariant['volume'];
        $expectedPrice = $testVariant['expectedPrice'];
        
        echo "\nTest: Volume Variant '$volume'\n";
        $totalTests++;
        
        // 1. Check price in products.json
        $jsonPrice = null;
        foreach ($productVariants as $variant) {
            if (($variant['volume'] ?? '') === $volume) {
                $jsonPrice = (float)($variant['priceCHF'] ?? 0);
                break;
            }
        }
        
        if ($jsonPrice === null) {
            echo COLOR_RED . "  ✗ Variant not found in products.json\n" . COLOR_RESET;
            $allPassed = false;
            continue;
        }
        
        echo "  products.json price:     CHF " . number_format($jsonPrice, 2) . "\n";
        
        // 2. Check getProductPrice() (used by cart)
        $cartPrice = getProductPrice($productId, $volume);
        echo "  getProductPrice() (cart): CHF " . number_format($cartPrice, 2) . "\n";
        
        // 3. Check getVariantPrice() (alias)
        $variantPrice = getVariantPrice($productId, $volume);
        echo "  getVariantPrice():        CHF " . number_format($variantPrice, 2) . "\n";
        
        // 4. Compare all sources
        if ($jsonPrice === $cartPrice && $cartPrice === $variantPrice && $jsonPrice > 0) {
            echo COLOR_GREEN . "  ✓ PASS: All prices match (CHF " . number_format($jsonPrice, 2) . ")\n" . COLOR_RESET;
            $passedTests++;
        } else {
            echo COLOR_RED . "  ✗ FAIL: Price mismatch detected!\n" . COLOR_RESET;
            $allPassed = false;
        }
    }
    
    echo "\n";
}

// Test catalog version
echo COLOR_BLUE . "Testing: Catalog Version System" . COLOR_RESET . "\n";
echo str_repeat("─", 60) . "\n";
$totalTests++;

$catalogVersion = getCatalogVersion();
if ($catalogVersion > 0) {
    echo COLOR_GREEN . "✓ PASS: Catalog version = $catalogVersion\n" . COLOR_RESET;
    $passedTests++;
} else {
    echo COLOR_RED . "✗ FAIL: Catalog version not available\n" . COLOR_RESET;
    $allPassed = false;
}

// Summary
echo "\n";
echo COLOR_BLUE . "═══════════════════════════════════════════════════════\n";
echo "                   TEST SUMMARY\n";
echo "═══════════════════════════════════════════════════════" . COLOR_RESET . "\n";
echo "Total Tests: $totalTests\n";
echo COLOR_GREEN . "Passed: $passedTests\n" . COLOR_RESET;
echo COLOR_RED . "Failed: " . ($totalTests - $passedTests) . "\n" . COLOR_RESET;
echo "Pass Rate: " . round(($passedTests / $totalTests) * 100, 1) . "%\n\n";

if ($allPassed) {
    echo COLOR_GREEN . "╔════════════════════════════════════════════════════════╗\n";
    echo "║  ✓ ALL TESTS PASSED - Pricing is consistent!          ║\n";
    echo "╚════════════════════════════════════════════════════════╝" . COLOR_RESET . "\n";
    exit(0);
} else {
    echo COLOR_RED . "╔════════════════════════════════════════════════════════╗\n";
    echo "║  ✗ SOME TESTS FAILED - Review pricing system!         ║\n";
    echo "╚════════════════════════════════════════════════════════╝" . COLOR_RESET . "\n";
    exit(1);
}
