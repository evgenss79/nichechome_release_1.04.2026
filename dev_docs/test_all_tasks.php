#!/usr/bin/env php
<?php
/**
 * Comprehensive Test Suite for All 3 Tasks
 */

echo "==========================================\n";
echo "COMPREHENSIVE TEST SUITE - 3 TASKS\n";
echo "==========================================\n\n";

$baseDir = '/home/runner/work/BV_alter/BV_alter';
chdir($baseDir);

$allPassed = true;

// ============================================
// TASK 1: ADMIN ACCESSORIES - GUGGUL/LOUBAN + OPTIONAL FRAGRANCES
// ============================================
echo "TASK 1: Admin Accessories - Guggul/Louban + Optional Fragrances\n";
echo "----------------------------------------------------------------\n";

// Test 1.1: Verify guggul_louban exists in fragrances.json
echo "Test 1.1: Verify guggul_louban exists in fragrances.json... ";
$fragrances = json_decode(file_get_contents("$baseDir/data/fragrances.json"), true);
if (isset($fragrances['guggul_louban'])) {
    echo "✓ PASS\n";
} else {
    echo "✗ FAIL - guggul_louban not found\n";
    $allPassed = false;
}

// Test 1.2: Verify guggul_louban translations in all 6 languages
echo "Test 1.2: Verify guggul_louban translations exist... ";
$languages = ['en', 'de', 'fr', 'it', 'ru', 'ukr'];
$translationsComplete = true;
foreach ($languages as $lang) {
    $fragFile = "$baseDir/data/i18n/fragrances_$lang.json";
    $fragData = json_decode(file_get_contents($fragFile), true);
    if (!isset($fragData['fragrance']['guggul_louban']['name'])) {
        echo "✗ FAIL - Missing translation in $lang\n";
        $translationsComplete = false;
        $allPassed = false;
        break;
    }
}
if ($translationsComplete) {
    echo "✓ PASS (all 6 languages)\n";
}

// Test 1.3: Verify admin/accessories.php has fragrance selector checkbox
echo "Test 1.3: Verify admin form has enable_fragrance_selector checkbox... ";
$adminContent = file_get_contents("$baseDir/admin/accessories.php");
if (strpos($adminContent, 'enable_fragrance_selector') !== false && 
    strpos($adminContent, 'has_fragrance_selector') !== false) {
    echo "✓ PASS\n";
} else {
    echo "✗ FAIL - Missing fragrance selector toggle\n";
    $allPassed = false;
}

// Test 1.4: Verify product.php checks has_fragrance_selector
echo "Test 1.4: Verify product.php checks has_fragrance_selector... ";
$productContent = file_get_contents("$baseDir/product.php");
if (strpos($productContent, 'has_fragrance_selector') !== false && 
    strpos($productContent, 'showFragranceSelector') !== false) {
    echo "✓ PASS\n";
} else {
    echo "✗ FAIL - Missing fragrance selector hide logic\n";
    $allPassed = false;
}

// Test 1.5: Verify validation allows empty fragrances when disabled
echo "Test 1.5: Verify validation allows empty fragrances when disabled... ";
if (strpos($adminContent, 'hasFragranceSelector') !== false && 
    strpos($adminContent, 'allowed_fragrances') !== false) {
    echo "✓ PASS\n";
} else {
    echo "✗ FAIL - Missing validation logic\n";
    $allPassed = false;
}

echo "\n";

// ============================================
// TASK 2: GIFT SETS PAGE - FULL RU TRANSLATION
// ============================================
echo "TASK 2: Gift Sets Page - Full RU Translation\n";
echo "---------------------------------------------\n";

// Test 2.1: Verify pages_ru.json has giftSets section
echo "Test 2.1: Verify pages_ru.json has giftSets section... ";
$pagesRu = json_decode(file_get_contents("$baseDir/data/i18n/pages_ru.json"), true);
if (isset($pagesRu['page']['giftSets'])) {
    echo "✓ PASS\n";
} else {
    echo "✗ FAIL - Missing giftSets section\n";
    $allPassed = false;
}

// Test 2.2: Verify all Russian translations are present
echo "Test 2.2: Verify all Russian translations are present... ";
$requiredKeys = ['title', 'subtitle', 'slot', 'selectCategory', 'totalPrice', 'discount', 'addToCart'];
$allKeysPresent = true;
foreach ($requiredKeys as $key) {
    if (!isset($pagesRu['page']['giftSets'][$key])) {
        echo "✗ FAIL - Missing key: $key\n";
        $allKeysPresent = false;
        $allPassed = false;
        break;
    }
}
if ($allKeysPresent) {
    echo "✓ PASS\n";
}

// Test 2.3: Verify Russian translations are not English
echo "Test 2.3: Verify Russian translations are actually in Russian... ";
$hasRussianChars = preg_match('/[А-Яа-я]/u', $pagesRu['page']['giftSets']['title']);
if ($hasRussianChars) {
    echo "✓ PASS\n";
} else {
    echo "✗ FAIL - Translations appear to be in English\n";
    $allPassed = false;
}

// Test 2.4: Verify ui_ru.json has giftSets.addedAlert
echo "Test 2.4: Verify ui_ru.json has giftSets.addedAlert... ";
$uiRu = json_decode(file_get_contents("$baseDir/data/i18n/ui_ru.json"), true);
if (isset($uiRu['page']['giftSets']['addedAlert'])) {
    echo "✓ PASS\n";
} else {
    echo "✗ FAIL - Missing addedAlert key\n";
    $allPassed = false;
}

// Test 2.5: Verify other language files have translations
echo "Test 2.5: Verify all language files have proper translations... ";
$otherLangs = ['de', 'fr', 'it', 'ukr'];
$allLangsComplete = true;
foreach ($otherLangs as $lang) {
    $uiFile = "$baseDir/data/i18n/ui_$lang.json";
    $uiData = json_decode(file_get_contents($uiFile), true);
    if (!isset($uiData['page']['giftSets']['addedAlert'])) {
        echo "✗ FAIL - Missing addedAlert in $lang\n";
        $allLangsComplete = false;
        $allPassed = false;
        break;
    }
}
if ($allLangsComplete) {
    echo "✓ PASS\n";
}

// Test 2.6: Verify gift-sets.php has I18N_LABELS
echo "Test 2.6: Verify gift-sets.php has I18N_LABELS for JavaScript... ";
$giftSetsContent = file_get_contents("$baseDir/gift-sets.php");
if (strpos($giftSetsContent, 'window.I18N_LABELS') !== false && 
    strpos($giftSetsContent, 'giftset_added') !== false) {
    echo "✓ PASS\n";
} else {
    echo "✗ FAIL - Missing I18N_LABELS\n";
    $allPassed = false;
}

echo "\n";

// ============================================
// TASK 3: GIFT SET CART PRICE - FIX CHF 0.00 + DUPLICATES
// ============================================
echo "TASK 3: Gift Set Cart Price - Fix CHF 0.00 + Duplicates\n";
echo "--------------------------------------------------------\n";

// Test 3.1: Verify app.js uses stable SKU
echo "Test 3.1: Verify app.js uses stable SKU (GIFTSET-CUSTOM)... ";
$appJsContent = file_get_contents("$baseDir/assets/js/app.js");
if (strpos($appJsContent, 'GIFTSET-CUSTOM') !== false) {
    echo "✓ PASS\n";
} else {
    echo "✗ FAIL - Still using timestamp SKU\n";
    $allPassed = false;
}

// Test 3.2: Verify app.js syncs with server
echo "Test 3.2: Verify app.js syncs with server via fetch... ";
if (strpos($appJsContent, 'fetch(\'add_to_cart.php\'') !== false) {
    echo "✓ PASS\n";
} else {
    echo "✗ FAIL - Missing server sync\n";
    $allPassed = false;
}

// Test 3.3: Verify button is disabled during processing
echo "Test 3.3: Verify button is disabled during processing... ";
if (strpos($appJsContent, 'addBtn.disabled = true') !== false) {
    echo "✓ PASS\n";
} else {
    echo "✗ FAIL - Button not disabled\n";
    $allPassed = false;
}

// Test 3.4: Verify alert uses I18N
echo "Test 3.4: Verify alert uses I18N... ";
if (strpos($appJsContent, 'window.I18N_LABELS') !== false && 
    strpos($appJsContent, 'giftset_added') !== false) {
    echo "✓ PASS\n";
} else {
    echo "✗ FAIL - Alert not using I18N\n";
    $allPassed = false;
}

// Test 3.5: Verify add_to_cart.php handles gift_sets category
echo "Test 3.5: Verify add_to_cart.php handles gift_sets category... ";
$addToCartContent = file_get_contents("$baseDir/add_to_cart.php");
if (strpos($addToCartContent, "category === 'gift_sets'") !== false) {
    echo "✓ PASS\n";
} else {
    echo "✗ FAIL - Missing gift_sets handling\n";
    $allPassed = false;
}

// Test 3.6: Verify add_to_cart.php accepts incoming price for gift sets
echo "Test 3.6: Verify add_to_cart.php accepts incoming price... ";
if (strpos($addToCartContent, "floatval(\$item['price'])") !== false) {
    echo "✓ PASS\n";
} else {
    echo "✗ FAIL - Not accepting incoming price\n";
    $allPassed = false;
}

// Test 3.7: Verify sync action also handles gift sets
echo "Test 3.7: Verify sync action handles gift sets... ";
$syncMatches = preg_match_all("/category === 'gift_sets'/", $addToCartContent, $matches);
if ($syncMatches >= 2) { // Should appear in both 'add' and 'sync' actions
    echo "✓ PASS\n";
} else {
    echo "✗ FAIL - Sync action might not handle gift sets\n";
    $allPassed = false;
}

echo "\n";
echo "==========================================\n";
if ($allPassed) {
    echo "ALL TESTS PASSED ✓\n";
    echo "==========================================\n";
    exit(0);
} else {
    echo "SOME TESTS FAILED ✗\n";
    echo "==========================================\n";
    exit(1);
}
