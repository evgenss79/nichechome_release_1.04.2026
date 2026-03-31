#!/usr/bin/env php
<?php
/**
 * Verify Storefront vs Cart Prices
 * 
 * This tool verifies that storefront display prices match cart resolver prices
 * for all products, ensuring no price drift after admin updates.
 * 
 * Tests:
 * - All product variants in products.json
 * - getProductPrice() (cart resolver)
 * - getVariantPrice() (cart resolver alias)
 * - getDefaultDisplayedPrice() (initial storefront display)
 * 
 * Explicitly includes Interior Perfume (home_perfume) category.
 */

require_once __DIR__ . '/../init.php';

// ANSI color codes
define('GREEN', "\033[32m");
define('RED', "\033[31m");
define('YELLOW', "\033[33m");
define('BLUE', "\033[34m");
define('CYAN', "\033[36m");
define('BOLD', "\033[1m");
define('RESET', "\033[0m");

echo BLUE . BOLD . "╔════════════════════════════════════════════════════════════╗\n";
echo "║     Storefront vs Cart Price Verification Tool            ║\n";
echo "╚════════════════════════════════════════════════════════════╝" . RESET . "\n\n";

// Load products
$products = loadJSON('products.json');
if (empty($products)) {
    echo RED . "✗ FAIL: Could not load products.json\n" . RESET;
    exit(1);
}

echo GREEN . "✓ Loaded " . count($products) . " products from products.json\n" . RESET;
echo "\n";

$allPassed = true;
$totalTests = 0;
$passedTests = 0;
$failedProducts = [];

// Categories to explicitly test (including Interior Perfume)
$explicitCategories = [
    'aroma_diffusers' => 'Aroma Diffusers',
    'scented_candles' => 'Scented Candles',
    'home_perfume' => 'Interior Perfume', // CRITICAL: Interior Perfume category
    'car_perfume' => 'Car Perfume',
    'textile_perfume' => 'Textile Perfume',
    'limited_edition' => 'Limited Edition',
    'accessories' => 'Accessories'
];

$testedCategories = [];

// Test each product
foreach ($products as $productId => $product) {
    $productName = $product['name_key'] ?? $productId;
    $category = $product['category'] ?? 'unknown';
    $variants = $product['variants'] ?? [];
    
    if (empty($variants)) {
        echo YELLOW . "⚠ SKIP: $productId has no variants\n" . RESET;
        continue;
    }
    
    // Track categories we've tested
    if (!in_array($category, $testedCategories)) {
        $testedCategories[] = $category;
    }
    
    // Display category name if it's one of our explicit test categories
    $categoryDisplay = $explicitCategories[$category] ?? ucfirst(str_replace('_', ' ', $category));
    
    echo CYAN . "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n" . RESET;
    echo BOLD . "Product: $productName ($productId)\n" . RESET;
    echo "Category: " . CYAN . $categoryDisplay . RESET . " ($category)\n";
    echo "Variants: " . count($variants) . "\n";
    echo CYAN . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n" . RESET;
    
    $productPassed = true;
    
    // Test default displayed price (first variant)
    $totalTests++;
    $defaultPrice = getDefaultDisplayedPrice($productId);
    $firstVariantPrice = (float)($variants[0]['priceCHF'] ?? 0);
    
    echo "\n  Test: Default Displayed Price (storefront initial)\n";
    echo "  ├─ getDefaultDisplayedPrice(): CHF " . number_format($defaultPrice, 2) . "\n";
    echo "  └─ First variant price:        CHF " . number_format($firstVariantPrice, 2);
    
    if ($defaultPrice === $firstVariantPrice && $defaultPrice > 0) {
        echo GREEN . " ✓\n" . RESET;
        $passedTests++;
    } else {
        echo RED . " ✗ MISMATCH!\n" . RESET;
        $allPassed = false;
        $productPassed = false;
    }
    
    // Test each variant
    foreach ($variants as $index => $variant) {
        $volume = $variant['volume'] ?? 'standard';
        $jsonPrice = (float)($variant['priceCHF'] ?? 0);
        
        $totalTests++;
        
        echo "\n  Test: Variant #" . ($index + 1) . " - Volume: $volume\n";
        echo "  ├─ products.json:       CHF " . number_format($jsonPrice, 2) . "\n";
        
        // Get price from cart resolver functions
        $cartPrice = getProductPrice($productId, $volume);
        echo "  ├─ getProductPrice():   CHF " . number_format($cartPrice, 2);
        
        $variantPrice = getVariantPrice($productId, $volume);
        echo "\n  └─ getVariantPrice():   CHF " . number_format($variantPrice, 2);
        
        // Check if all match
        if ($jsonPrice === $cartPrice && $cartPrice === $variantPrice && $jsonPrice > 0) {
            echo GREEN . " ✓ ALL MATCH\n" . RESET;
            $passedTests++;
        } else {
            echo RED . " ✗ PRICE MISMATCH!\n" . RESET;
            $allPassed = false;
            $productPassed = false;
            
            if ($jsonPrice !== $cartPrice) {
                echo RED . "    ERROR: products.json ($jsonPrice) ≠ getProductPrice() ($cartPrice)\n" . RESET;
            }
            if ($cartPrice !== $variantPrice) {
                echo RED . "    ERROR: getProductPrice() ($cartPrice) ≠ getVariantPrice() ($variantPrice)\n" . RESET;
            }
        }
    }
    
    if (!$productPassed) {
        $failedProducts[] = [
            'id' => $productId,
            'name' => $productName,
            'category' => $categoryDisplay
        ];
    }
    
    echo "\n";
}

// Check that all explicit categories were tested
echo BLUE . "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n" . RESET;
echo BOLD . "Category Coverage Check\n" . RESET;
echo BLUE . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n" . RESET;

foreach ($explicitCategories as $slug => $name) {
    if (in_array($slug, $testedCategories)) {
        echo GREEN . "✓ $name ($slug) - TESTED\n" . RESET;
    } else {
        echo YELLOW . "⚠ $name ($slug) - NO PRODUCTS FOUND\n" . RESET;
    }
}

// Summary
echo "\n";
echo BLUE . BOLD . "═══════════════════════════════════════════════════════════\n";
echo "                      TEST SUMMARY\n";
echo "═══════════════════════════════════════════════════════════" . RESET . "\n";
echo "Total Tests:    $totalTests\n";
echo GREEN . "Passed:         $passedTests\n" . RESET;
echo RED . "Failed:         " . ($totalTests - $passedTests) . "\n" . RESET;

if ($totalTests > 0) {
    $passRate = round(($passedTests / $totalTests) * 100, 1);
    echo "Pass Rate:      " . ($passRate == 100 ? GREEN : YELLOW) . "$passRate%" . RESET . "\n";
}

// List failed products if any
if (!empty($failedProducts)) {
    echo "\n" . RED . BOLD . "Failed Products:\n" . RESET;
    foreach ($failedProducts as $fp) {
        echo RED . "  ✗ {$fp['name']} ({$fp['id']}) - {$fp['category']}\n" . RESET;
    }
}

echo "\n";

if ($allPassed) {
    echo GREEN . BOLD . "╔════════════════════════════════════════════════════════════╗\n";
    echo "║  ✓✓✓ ALL TESTS PASSED ✓✓✓                                 ║\n";
    echo "║                                                            ║\n";
    echo "║  Storefront and cart prices are CONSISTENT!               ║\n";
    echo "║  Interior Perfume and all other categories verified.      ║\n";
    echo "╚════════════════════════════════════════════════════════════╝" . RESET . "\n";
    exit(0);
} else {
    echo RED . BOLD . "╔════════════════════════════════════════════════════════════╗\n";
    echo "║  ✗✗✗ TESTS FAILED ✗✗✗                                     ║\n";
    echo "║                                                            ║\n";
    echo "║  Price mismatches detected!                                ║\n";
    echo "║  Review products.json and helper functions.                ║\n";
    echo "╚════════════════════════════════════════════════════════════╝" . RESET . "\n";
    exit(1);
}
