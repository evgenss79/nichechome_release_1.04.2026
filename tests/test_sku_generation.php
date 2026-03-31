<?php
/**
 * Test SKU Generation
 * 
 * This test verifies that:
 * 1. Salted Caramel and Salty Water generate unique SKU suffixes
 * 2. Other fragrances maintain their existing SKU patterns
 * 3. SKU generation works correctly across all product types
 */

require_once __DIR__ . '/../init.php';

echo "=== Testing SKU Generation ===\n\n";

// Test 1: Check that Salted Caramel and Salty Water have unique SKUs
echo "Test 1: Verifying unique SKUs for Salted Caramel and Salty Water...\n";

$saltedCaramelSKU = generateSKU('car_clip', 'standard', 'salted_caramel');
$saltyWaterSKU = generateSKU('car_clip', 'standard', 'salty_water');

if ($saltedCaramelSKU === $saltyWaterSKU) {
    echo "FAIL: Salted Caramel and Salty Water generate the same SKU: $saltedCaramelSKU\n";
    exit(1);
}

echo "PASS: Salted Caramel SKU: $saltedCaramelSKU, Salty Water SKU: $saltyWaterSKU\n\n";

// Test 2: Verify expected SKU suffixes
echo "Test 2: Verifying expected SKU suffixes...\n";

if ($saltedCaramelSKU !== 'CP-STA-SC') {
    echo "FAIL: Salted Caramel should generate 'CP-STA-SC', got: $saltedCaramelSKU\n";
    exit(1);
}

if ($saltyWaterSKU !== 'CP-STA-SW') {
    echo "FAIL: Salty Water should generate 'CP-STA-SW', got: $saltyWaterSKU\n";
    exit(1);
}

echo "PASS: SKU suffixes are correct (SC and SW)\n\n";

// Test 3: Verify that other fragrances maintain their existing SKU patterns
echo "Test 3: Verifying other fragrances maintain existing SKUs...\n";

$testCases = [
    ['fragrance' => 'eden', 'expected' => 'EDE'],
    ['fragrance' => 'bamboo', 'expected' => 'BAM'],
    ['fragrance' => 'santal', 'expected' => 'SAN'],
    ['fragrance' => 'cherry_blossom', 'expected' => 'CHE'],
    ['fragrance' => 'tobacco_vanilla', 'expected' => 'TOB'],
    ['fragrance' => 'lime_basil', 'expected' => 'LIM'],
];

foreach ($testCases as $test) {
    $sku = generateSKU('car_clip', 'standard', $test['fragrance']);
    $expectedSKU = 'CP-STA-' . $test['expected'];
    
    if ($sku !== $expectedSKU) {
        echo "FAIL: {$test['fragrance']} should generate '$expectedSKU', got: $sku\n";
        exit(1);
    }
}

echo "PASS: All other fragrances maintain their existing SKU patterns\n\n";

// Test 4: Test SKU generation across different product types and volumes
echo "Test 4: Testing SKU generation across product types...\n";

$productTests = [
    ['product' => 'diffuser_classic', 'volume' => '125ml', 'fragrance' => 'salted_caramel', 'expected' => 'DF-125-SC'],
    ['product' => 'diffuser_classic', 'volume' => '250ml', 'fragrance' => 'salty_water', 'expected' => 'DF-250-SW'],
    ['product' => 'candle_classic', 'volume' => '160ml', 'fragrance' => 'salted_caramel', 'expected' => 'CD-160-SC'],
    ['product' => 'candle_classic', 'volume' => '500ml', 'fragrance' => 'salty_water', 'expected' => 'CD-500-SW'],
    ['product' => 'home_spray', 'volume' => '10ml', 'fragrance' => 'salted_caramel', 'expected' => 'HP-10-SC'],
    ['product' => 'home_spray', 'volume' => '50ml', 'fragrance' => 'salty_water', 'expected' => 'HP-50-SW'],
    ['product' => 'aroma_sashe', 'volume' => 'standard', 'fragrance' => 'salty_water', 'expected' => 'ARO-STA-SW'],
];

foreach ($productTests as $test) {
    $sku = generateSKU($test['product'], $test['volume'], $test['fragrance']);
    
    if ($sku !== $test['expected']) {
        echo "FAIL: {$test['product']} {$test['volume']} {$test['fragrance']} should generate '{$test['expected']}', got: $sku\n";
        exit(1);
    }
}

echo "PASS: SKU generation works correctly across all product types\n\n";

// Test 5: Verify fragrances.json has sku_suffix field
echo "Test 5: Verifying fragrances.json has sku_suffix fields...\n";

$fragrances = loadJSON('fragrances.json');

if (!isset($fragrances['salted_caramel']['sku_suffix'])) {
    echo "FAIL: salted_caramel missing sku_suffix in fragrances.json\n";
    exit(1);
}

if ($fragrances['salted_caramel']['sku_suffix'] !== 'SC') {
    echo "FAIL: salted_caramel sku_suffix should be 'SC', got: {$fragrances['salted_caramel']['sku_suffix']}\n";
    exit(1);
}

if (!isset($fragrances['salty_water']['sku_suffix'])) {
    echo "FAIL: salty_water missing sku_suffix in fragrances.json\n";
    exit(1);
}

if ($fragrances['salty_water']['sku_suffix'] !== 'SW') {
    echo "FAIL: salty_water sku_suffix should be 'SW', got: {$fragrances['salty_water']['sku_suffix']}\n";
    exit(1);
}

echo "PASS: fragrances.json has correct sku_suffix fields\n\n";

// Test 6: Verify no other fragrances have duplicate SKU suffixes
echo "Test 6: Checking for SKU suffix collisions...\n";

$suffixes = [];
foreach ($fragrances as $code => $data) {
    $sku = generateSKU('car_clip', 'standard', $code);
    // Extract suffix (last 3 chars after last dash)
    $parts = explode('-', $sku);
    $suffix = end($parts);
    
    if (isset($suffixes[$suffix])) {
        echo "FAIL: SKU suffix collision detected: '$suffix' used by both '$code' and '{$suffixes[$suffix]}'\n";
        exit(1);
    }
    
    $suffixes[$suffix] = $code;
}

echo "PASS: No SKU suffix collisions detected\n\n";

echo "=== ALL TESTS PASSED ===\n";
echo "Summary:\n";
echo "- Salted Caramel and Salty Water now have unique SKU suffixes (SC and SW)\n";
echo "- All other fragrances maintain their existing SKU patterns\n";
echo "- SKU generation works correctly across all product types and volumes\n";
echo "- fragrances.json has correct sku_suffix fields\n";
echo "- No SKU suffix collisions detected\n";
echo "\n";
echo "SKU generation is working correctly!\n";

exit(0);
