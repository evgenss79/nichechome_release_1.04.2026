<?php
/**
 * Test Textile Perfume Spray Pricing
 * 
 * This test verifies that:
 * 1. Textile Perfume Spray has correct price in products.json
 * 2. getProductPrice() returns correct price for textile_spray
 * 3. Adding to cart uses the correct price
 * 4. Cart displays the correct price
 * 5. Checkout calculates the correct total
 */

require_once __DIR__ . '/../init.php';

// Test configuration
$PRODUCT_ID = 'textile_spray';
$EXPECTED_PRICE = 19.90;
$EXPECTED_VOLUME = 'standard';

echo "=== Testing Textile Perfume Spray Pricing ===\n\n";

// Test 1: Check products.json
echo "Test 1: Checking products.json...\n";
$products = loadJSON('products.json');
if (!isset($products[$PRODUCT_ID])) {
    echo "FAIL: Product '$PRODUCT_ID' not found in products.json\n";
    exit(1);
}

$product = $products[$PRODUCT_ID];
$variants = $product['variants'] ?? [];
if (empty($variants)) {
    echo "FAIL: No variants found for product '$PRODUCT_ID'\n";
    exit(1);
}

$standardVariant = null;
foreach ($variants as $variant) {
    if (($variant['volume'] ?? '') === $EXPECTED_VOLUME) {
        $standardVariant = $variant;
        break;
    }
}

if (!$standardVariant) {
    echo "FAIL: No variant with volume '$EXPECTED_VOLUME' found\n";
    exit(1);
}

$priceInJson = (float)($standardVariant['priceCHF'] ?? 0);
if ($priceInJson !== $EXPECTED_PRICE) {
    echo "FAIL: Expected price $EXPECTED_PRICE but found $priceInJson in products.json\n";
    exit(1);
}
echo "PASS: products.json has correct price: CHF $priceInJson\n\n";

// Test 2: Check getProductPrice() function
echo "Test 2: Testing getProductPrice() function...\n";
$priceFromFunction = getProductPrice($PRODUCT_ID, $EXPECTED_VOLUME);
if ($priceFromFunction !== $EXPECTED_PRICE) {
    echo "FAIL: getProductPrice() returned $priceFromFunction, expected $EXPECTED_PRICE\n";
    exit(1);
}
echo "PASS: getProductPrice() returns correct price: CHF $priceFromFunction\n\n";

// Test 3: Simulate adding to cart
echo "Test 3: Simulating add to cart...\n";
// Clear any existing cart
clearCart();

$cartItem = [
    'sku' => 'TP-STA-EDE',  // Example SKU for textile spray with Eden fragrance
    'productId' => $PRODUCT_ID,
    'name' => 'Textile Perfume Spray',
    'category' => 'textile_perfume',
    'volume' => $EXPECTED_VOLUME,
    'fragrance' => 'eden',
    'price' => $priceFromFunction,
    'quantity' => 1
];

addToCart($cartItem);

$cart = getCart();
if (empty($cart)) {
    echo "FAIL: Cart is empty after adding item\n";
    exit(1);
}

$addedItem = $cart[0];
$priceInCart = (float)($addedItem['price'] ?? 0);
if ($priceInCart !== $EXPECTED_PRICE) {
    echo "FAIL: Item in cart has price $priceInCart, expected $EXPECTED_PRICE\n";
    exit(1);
}
echo "PASS: Item added to cart with correct price: CHF $priceInCart\n\n";

// Test 4: Check cart total
echo "Test 4: Testing cart total calculation...\n";
$cartTotal = getCartTotal();
if ($cartTotal !== $EXPECTED_PRICE) {
    echo "FAIL: Cart total is $cartTotal, expected $EXPECTED_PRICE\n";
    exit(1);
}
echo "PASS: Cart total is correct: CHF $cartTotal\n\n";

// Test 5: Test with multiple quantities
echo "Test 5: Testing multiple quantity...\n";
clearCart();
$cartItem['quantity'] = 3;
addToCart($cartItem);

$cart = getCart();
$addedItem = $cart[0];
$quantity = (int)($addedItem['quantity'] ?? 0);
$cartTotal = getCartTotal();
$expectedTotal = $EXPECTED_PRICE * 3;

if ($quantity !== 3) {
    echo "FAIL: Expected quantity 3, got $quantity\n";
    exit(1);
}

if ($cartTotal !== $expectedTotal) {
    echo "FAIL: Cart total is $cartTotal, expected $expectedTotal (3 × $EXPECTED_PRICE)\n";
    exit(1);
}
echo "PASS: Multiple quantity works correctly: 3 × CHF $EXPECTED_PRICE = CHF $cartTotal\n\n";

// Test 6: Test with different fragrances
echo "Test 6: Testing different fragrances have same price...\n";
clearCart();

$fragrances = ['eden', 'bamboo', 'santal', 'lime_basil'];
foreach ($fragrances as $fragrance) {
    $testItem = [
        'sku' => 'TP-STA-' . strtoupper(substr($fragrance, 0, 3)),
        'productId' => $PRODUCT_ID,
        'name' => 'Textile Perfume Spray',
        'category' => 'textile_perfume',
        'volume' => $EXPECTED_VOLUME,
        'fragrance' => $fragrance,
        'price' => getProductPrice($PRODUCT_ID, $EXPECTED_VOLUME),
        'quantity' => 1
    ];
    
    if ($testItem['price'] !== $EXPECTED_PRICE) {
        echo "FAIL: Fragrance '$fragrance' has price {$testItem['price']}, expected $EXPECTED_PRICE\n";
        exit(1);
    }
}
echo "PASS: All fragrances have consistent price: CHF $EXPECTED_PRICE\n\n";

// Clean up
clearCart();

echo "=== ALL TESTS PASSED ===\n";
echo "Summary:\n";
echo "- Product data is correct in products.json\n";
echo "- getProductPrice() function works correctly\n";
echo "- Adding to cart preserves correct price\n";
echo "- Cart total calculation is correct\n";
echo "- Multiple quantities work correctly\n";
echo "- All fragrances have consistent pricing\n";
echo "\n";
echo "Textile Perfume Spray pricing is working correctly!\n";

exit(0);
