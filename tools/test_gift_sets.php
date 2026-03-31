#!/usr/bin/env php
<?php
/**
 * Test Gift Sets - Verify unique cart keys for different configurations
 * 
 * Usage: php tools/test_gift_sets.php
 */

require_once __DIR__ . '/../init.php';

echo "\n=== Gift Set Cart Uniqueness Test ===\n\n";

// Test 1: Create two different gift set configurations
echo "Test 1: Different gift set configurations should create separate cart lines\n";
echo str_repeat('-', 70) . "\n";

// Configuration A: Diffuser 125ml Cherry Blossom + Candle 160ml Bellini + Car Perfume standard Bellini
$configA = [
    [
        'slot' => 1,
        'category' => 'aroma_diffusers',
        'productId' => 'diffuser_classic',
        'variant' => '125ml',
        'fragrance' => 'cherry_blossom',
        'qty' => 1
    ],
    [
        'slot' => 2,
        'category' => 'scented_candles',
        'productId' => 'candle_classic',
        'variant' => '160ml',
        'fragrance' => 'bellini',
        'qty' => 1
    ],
    [
        'slot' => 3,
        'category' => 'car_perfume',
        'productId' => 'car_clip',
        'variant' => 'standard',
        'fragrance' => 'bellini',
        'qty' => 1
    ]
];

// Configuration B: Different products/fragrances
$configB = [
    [
        'slot' => 1,
        'category' => 'aroma_diffusers',
        'productId' => 'diffuser_classic',
        'variant' => '250ml',
        'fragrance' => 'eden',
        'qty' => 1
    ],
    [
        'slot' => 2,
        'category' => 'scented_candles',
        'productId' => 'candle_classic',
        'variant' => '500ml',
        'fragrance' => 'rosso',
        'qty' => 1
    ],
    [
        'slot' => 3,
        'category' => 'home_perfume',
        'productId' => 'home_spray',
        'variant' => '50ml',
        'fragrance' => 'santal',
        'qty' => 1
    ]
];

$keyA = generateGiftSetConfigKey($configA);
$keyB = generateGiftSetConfigKey($configB);

echo "Config A key: $keyA\n";
echo "Config B key: $keyB\n";

if ($keyA !== $keyB) {
    echo "✓ PASS: Different configurations generate different keys\n\n";
} else {
    echo "✗ FAIL: Different configurations generated same key!\n\n";
}

// Test 2: Same configuration should generate same key
echo "Test 2: Identical gift set configurations should generate same key\n";
echo str_repeat('-', 70) . "\n";

$configA2 = [
    [
        'slot' => 1,
        'category' => 'aroma_diffusers',
        'productId' => 'diffuser_classic',
        'variant' => '125ml',
        'fragrance' => 'cherry_blossom',
        'qty' => 1
    ],
    [
        'slot' => 2,
        'category' => 'scented_candles',
        'productId' => 'candle_classic',
        'variant' => '160ml',
        'fragrance' => 'bellini',
        'qty' => 1
    ],
    [
        'slot' => 3,
        'category' => 'car_perfume',
        'productId' => 'car_clip',
        'variant' => 'standard',
        'fragrance' => 'bellini',
        'qty' => 1
    ]
];

$keyA2 = generateGiftSetConfigKey($configA2);

echo "Config A  key: $keyA\n";
echo "Config A2 key: $keyA2\n";

if ($keyA === $keyA2) {
    echo "✓ PASS: Identical configurations generate same key\n\n";
} else {
    echo "✗ FAIL: Identical configurations generated different keys!\n\n";
}

// Test 3: Test formatGiftSetContents function
echo "Test 3: Format gift set contents for display\n";
echo str_repeat('-', 70) . "\n";

$breakdown = formatGiftSetContents($configA, 'en');
echo "Breakdown: $breakdown\n";

if (!empty($breakdown) && strpos($breakdown, '×') !== false) {
    echo "✓ PASS: Breakdown generated successfully\n\n";
} else {
    echo "✗ FAIL: Breakdown not generated properly\n\n";
}

// Test 4: Simulate adding to cart
echo "Test 4: Test cart operations\n";
echo str_repeat('-', 70) . "\n";

// Clear cart
clearCart();
echo "Cleared cart\n";

// Add config A
$cartItemA = [
    'sku' => $keyA,
    'productId' => 'gift_set',
    'name' => 'Custom Gift Set A',
    'category' => 'gift_sets',
    'price' => 66.55,
    'quantity' => 1,
    'isGiftSet' => true,
    'items' => $configA,
    'breakdown' => formatGiftSetContents($configA, 'en'),
    'meta' => [
        'gift_set_items' => $configA,
        'breakdown' => formatGiftSetContents($configA, 'en')
    ]
];
addToCart($cartItemA);
echo "Added Gift Set A to cart\n";

$cart = getCart();
$cartCount = count($cart);
echo "Cart has $cartCount item(s)\n";

// Add config B
$cartItemB = [
    'sku' => $keyB,
    'productId' => 'gift_set',
    'name' => 'Custom Gift Set B',
    'category' => 'gift_sets',
    'price' => 120.45,
    'quantity' => 1,
    'isGiftSet' => true,
    'items' => $configB,
    'breakdown' => formatGiftSetContents($configB, 'en'),
    'meta' => [
        'gift_set_items' => $configB,
        'breakdown' => formatGiftSetContents($configB, 'en')
    ]
];
addToCart($cartItemB);
echo "Added Gift Set B to cart\n";

$cart = getCart();
$cartCount = count($cart);
echo "Cart now has $cartCount item(s)\n";

if ($cartCount === 2) {
    echo "✓ PASS: Two different gift sets created two cart line items\n\n";
} else {
    echo "✗ FAIL: Expected 2 cart items, got $cartCount\n\n";
}

// Test 5: Add same config again - should merge
echo "Test 5: Adding identical gift set should merge (increase qty)\n";
echo str_repeat('-', 70) . "\n";

$cartItemA2 = [
    'sku' => $keyA,
    'productId' => 'gift_set',
    'name' => 'Custom Gift Set A',
    'category' => 'gift_sets',
    'price' => 66.55,
    'quantity' => 1,
    'isGiftSet' => true,
    'items' => $configA,
    'breakdown' => formatGiftSetContents($configA, 'en'),
    'meta' => [
        'gift_set_items' => $configA,
        'breakdown' => formatGiftSetContents($configA, 'en')
    ]
];
addToCart($cartItemA2);
echo "Added Gift Set A again\n";

$cart = getCart();
$cartCount = count($cart);
$itemAQty = 0;
foreach ($cart as $item) {
    if ($item['sku'] === $keyA) {
        $itemAQty = $item['quantity'];
        break;
    }
}

echo "Cart still has $cartCount line items\n";
echo "Gift Set A quantity: $itemAQty\n";

if ($cartCount === 2 && $itemAQty === 2) {
    echo "✓ PASS: Identical gift set merged (qty increased to 2)\n\n";
} else {
    echo "✗ FAIL: Expected 2 cart items with Gift Set A qty=2\n\n";
}

// Test 6: Test quantity update
echo "Test 6: Update quantity of specific gift set\n";
echo str_repeat('-', 70) . "\n";

updateCartQuantity($keyB, 3);
echo "Updated Gift Set B quantity to 3\n";

$cart = getCart();
$itemBQty = 0;
foreach ($cart as $item) {
    if ($item['sku'] === $keyB) {
        $itemBQty = $item['quantity'];
        break;
    }
}

echo "Gift Set B quantity: $itemBQty\n";

if ($itemBQty === 3) {
    echo "✓ PASS: Quantity updated correctly\n\n";
} else {
    echo "✗ FAIL: Expected Gift Set B qty=3, got $itemBQty\n\n";
}

// Test 7: Display cart totals
echo "Test 7: Cart totals calculation\n";
echo str_repeat('-', 70) . "\n";

$total = getCartTotal();
$expectedTotal = (66.55 * 2) + (120.45 * 3);

echo "Cart total: CHF " . number_format($total, 2) . "\n";
echo "Expected:   CHF " . number_format($expectedTotal, 2) . "\n";

if (abs($total - $expectedTotal) < 0.01) {
    echo "✓ PASS: Cart total calculated correctly\n\n";
} else {
    echo "✗ FAIL: Cart total mismatch\n\n";
}

// Test 8: Display full cart details
echo "Test 8: Full cart contents\n";
echo str_repeat('-', 70) . "\n";

foreach ($cart as $index => $item) {
    echo "Item " . ($index + 1) . ":\n";
    echo "  SKU: " . $item['sku'] . "\n";
    echo "  Name: " . $item['name'] . "\n";
    echo "  Breakdown: " . ($item['breakdown'] ?? 'N/A') . "\n";
    echo "  Price: CHF " . number_format($item['price'], 2) . "\n";
    echo "  Qty: " . $item['quantity'] . "\n";
    echo "  Total: CHF " . number_format($item['price'] * $item['quantity'], 2) . "\n";
    echo "\n";
}

// Test 9: Remove one gift set
echo "Test 9: Remove specific gift set\n";
echo str_repeat('-', 70) . "\n";

removeFromCart($keyA);
echo "Removed Gift Set A\n";

$cart = getCart();
$cartCount = count($cart);

echo "Cart now has $cartCount item(s)\n";

if ($cartCount === 1) {
    echo "✓ PASS: Gift Set A removed, Gift Set B remains\n\n";
} else {
    echo "✗ FAIL: Expected 1 cart item after removal\n\n";
}

// Clean up
clearCart();
echo "Cleaned up - cart cleared\n";

echo "\n=== All Tests Complete ===\n\n";
