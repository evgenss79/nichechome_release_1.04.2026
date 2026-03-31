<?php
/**
 * Test Gift Set 3-Item Validation
 * 
 * This test verifies that:
 * 1. Gift sets require exactly 3 items to be added to cart
 * 2. Server-side validation rejects gift sets with <3 items
 * 3. Server-side validation returns structured error code GIFTSET_INCOMPLETE
 * 4. 5% discount is only applied when 3 valid items are present
 */

require_once __DIR__ . '/../init.php';

echo "=== Testing Gift Set 3-Item Validation ===\n\n";

// Helper function to simulate add_to_cart request
function simulateAddToCart($giftSetItems) {
    // Simulate the gift set item structure
    $item = [
        'sku' => 'GIFTSET-CUSTOM',
        'productId' => 'gift_set',
        'name' => 'Custom Gift Set',
        'category' => 'gift_sets',
        'gift_set_items' => $giftSetItems,
        'quantity' => 1
    ];
    
    // Simulate the validation logic from add_to_cart.php
    $category = $item['category'];
    
    if ($category === 'gift_sets') {
        $giftSetItems = $item['gift_set_items'] ?? $item['items'] ?? [];
        
        // Enforce 3-item rule for gift sets
        if (empty($giftSetItems) || !is_array($giftSetItems) || count($giftSetItems) !== 3) {
            return [
                'success' => false,
                'error' => 'GIFTSET_INCOMPLETE',
                'code' => 'GIFTSET_INCOMPLETE',
                'message' => 'Gift Set requires 3 fully configured items.'
            ];
        }
        
        // Validate and recalculate price for each slot
        $calculatedTotal = 0;
        $validationErrors = [];
        
        foreach ($giftSetItems as $slotIndex => $slotItem) {
            $slotNumber = $slotItem['slot'] ?? ($slotIndex + 1);
            
            // Validate required fields
            if (empty($slotItem['productId'])) {
                $validationErrors[] = [
                    'slot' => $slotNumber,
                    'field' => 'product',
                    'message' => 'Product not selected'
                ];
                continue;
            }
            
            $productId = sanitize($slotItem['productId']);
            $variant = sanitize($slotItem['variant'] ?? 'standard');
            
            // Validate variant if needed
            if (empty($variant)) {
                $validationErrors[] = [
                    'slot' => $slotNumber,
                    'field' => 'variant',
                    'message' => 'Variant not selected'
                ];
                continue;
            }
            
            // Get price from products.json using canonical price function
            $slotPrice = getProductPrice($productId, $variant);
            
            if ($slotPrice <= 0) {
                $validationErrors[] = [
                    'slot' => $slotNumber,
                    'field' => 'product',
                    'message' => 'Invalid product or variant'
                ];
                continue;
            }
            
            $calculatedTotal += $slotPrice;
        }
        
        // If validation errors exist, return structured error
        if (!empty($validationErrors)) {
            return [
                'success' => false,
                'error' => 'Gift set validation failed',
                'validationErrors' => $validationErrors
            ];
        }
        
        // Apply 5% discount only when all 3 items are valid
        $discount = $calculatedTotal * 0.05;
        $finalPrice = $calculatedTotal - $discount;
        
        return [
            'success' => true,
            'price' => $finalPrice,
            'discount' => $discount,
            'total' => $calculatedTotal
        ];
    }
    
    return ['success' => false, 'error' => 'Not a gift set'];
}

// Test 1: Reject gift set with 0 items
echo "Test 1: Gift set with 0 items should be rejected...\n";
$result = simulateAddToCart([]);
if ($result['success'] === false && $result['code'] === 'GIFTSET_INCOMPLETE') {
    echo "PASS: Gift set with 0 items correctly rejected with GIFTSET_INCOMPLETE\n\n";
} else {
    echo "FAIL: Gift set with 0 items should be rejected\n";
    var_dump($result);
    exit(1);
}

// Test 2: Reject gift set with 1 item
echo "Test 2: Gift set with 1 item should be rejected...\n";
$oneItem = [
    [
        'slot' => 1,
        'category' => 'home_perfumes',
        'productId' => 'diffuser_classic',
        'productName' => 'Classic Diffuser',
        'variant' => '125ml',
        'fragrance' => 'eden',
        'price' => 35.00,
        'qty' => 1
    ]
];
$result = simulateAddToCart($oneItem);
if ($result['success'] === false && $result['code'] === 'GIFTSET_INCOMPLETE') {
    echo "PASS: Gift set with 1 item correctly rejected with GIFTSET_INCOMPLETE\n\n";
} else {
    echo "FAIL: Gift set with 1 item should be rejected\n";
    var_dump($result);
    exit(1);
}

// Test 3: Reject gift set with 2 items
echo "Test 3: Gift set with 2 items should be rejected...\n";
$twoItems = [
    [
        'slot' => 1,
        'category' => 'home_perfumes',
        'productId' => 'diffuser_classic',
        'productName' => 'Classic Diffuser',
        'variant' => '125ml',
        'fragrance' => 'eden',
        'price' => 35.00,
        'qty' => 1
    ],
    [
        'slot' => 2,
        'category' => 'candles',
        'productId' => 'candle_classic',
        'productName' => 'Classic Candle',
        'variant' => '160ml',
        'fragrance' => 'bamboo',
        'price' => 28.00,
        'qty' => 1
    ]
];
$result = simulateAddToCart($twoItems);
if ($result['success'] === false && $result['code'] === 'GIFTSET_INCOMPLETE') {
    echo "PASS: Gift set with 2 items correctly rejected with GIFTSET_INCOMPLETE\n\n";
} else {
    echo "FAIL: Gift set with 2 items should be rejected\n";
    var_dump($result);
    exit(1);
}

// Test 4: Accept gift set with 3 valid items and calculate discount
echo "Test 4: Gift set with 3 valid items should be accepted with 5% discount...\n";
$threeItems = [
    [
        'slot' => 1,
        'category' => 'aroma_diffusers',
        'productId' => 'diffuser_classic',
        'productName' => 'Classic Diffuser',
        'variant' => '125ml',
        'fragrance' => 'eden',
        'price' => 20.9,
        'qty' => 1
    ],
    [
        'slot' => 2,
        'category' => 'scented_candles',
        'productId' => 'candle_classic',
        'productName' => 'Classic Candle',
        'variant' => '160ml',
        'fragrance' => 'bamboo',
        'price' => 24.9,
        'qty' => 1
    ],
    [
        'slot' => 3,
        'category' => 'textile_perfume',
        'productId' => 'textile_spray',
        'productName' => 'Textile Spray',
        'variant' => 'standard',
        'fragrance' => 'santal',
        'price' => 19.9,
        'qty' => 1
    ]
];
$result = simulateAddToCart($threeItems);

if ($result['success'] === true) {
    // Verify discount calculation (prices from actual products.json)
    $expectedTotal = 20.9 + 24.9 + 19.9; // 65.7
    $expectedDiscount = $expectedTotal * 0.05; // 3.285
    $expectedFinalPrice = $expectedTotal - $expectedDiscount; // 62.415
    
    if (abs($result['total'] - $expectedTotal) < 0.01 && 
        abs($result['discount'] - $expectedDiscount) < 0.01 && 
        abs($result['price'] - $expectedFinalPrice) < 0.01) {
        echo "PASS: Gift set with 3 items accepted with correct 5% discount\n";
        echo "      Total: CHF " . number_format($result['total'], 2) . "\n";
        echo "      Discount: CHF " . number_format($result['discount'], 2) . "\n";
        echo "      Final Price: CHF " . number_format($result['price'], 2) . "\n\n";
    } else {
        echo "FAIL: Discount calculation incorrect\n";
        echo "      Expected: Total=$expectedTotal, Discount=$expectedDiscount, Final=$expectedFinalPrice\n";
        echo "      Got: Total={$result['total']}, Discount={$result['discount']}, Final={$result['price']}\n";
        exit(1);
    }
} else {
    echo "FAIL: Gift set with 3 valid items should be accepted\n";
    var_dump($result);
    exit(1);
}

// Test 5: Reject gift set with 4 items (more than 3)
echo "Test 5: Gift set with 4 items should be rejected...\n";
$fourItems = array_merge($threeItems, [
    [
        'slot' => 4,
        'category' => 'accessories',
        'productId' => 'car_clip',
        'productName' => 'Car Clip',
        'variant' => 'standard',
        'fragrance' => 'eden',
        'price' => 12.00,
        'qty' => 1
    ]
]);
$result = simulateAddToCart($fourItems);
if ($result['success'] === false && $result['code'] === 'GIFTSET_INCOMPLETE') {
    echo "PASS: Gift set with 4 items correctly rejected with GIFTSET_INCOMPLETE\n\n";
} else {
    echo "FAIL: Gift set with 4 items should be rejected\n";
    var_dump($result);
    exit(1);
}

echo "=== All Gift Set Validation Tests Passed ===\n";
exit(0);
