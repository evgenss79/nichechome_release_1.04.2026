<?php

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/stock/sku_universe.php';

$repoRoot = dirname(__DIR__);
$dataRoot = $repoRoot . '/data';

function assertTrue(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function renderPhpPage(string $path, array $query = []): string {
    $oldGet = $_GET;
    $oldRequestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $_GET = $query;
    $_SERVER['REQUEST_METHOD'] = 'GET';
    ob_start();
    include $path;
    $output = ob_get_clean();
    $_GET = $oldGet;
    $_SERVER['REQUEST_METHOD'] = $oldRequestMethod;
    return $output;
}

$filesToRestore = [
    $dataRoot . '/categories.json',
    $dataRoot . '/products.json',
    $dataRoot . '/stock.json',
    $dataRoot . '/branch_stock.json',
    $dataRoot . '/catalog_version.json',
];
foreach (glob($dataRoot . '/i18n/ui_*.json') as $file) {
    $filesToRestore[] = $file;
}

$backups = [];
foreach ($filesToRestore as $file) {
    $backups[$file] = file_exists($file) ? file_get_contents($file) : null;
}

$suffix = substr(md5(uniqid('optional_image_', true)), 0, 8);
$categoryId = 'admin_test_optional_image_category_' . $suffix;
$productId = 'admin_test_optional_image_product_' . $suffix;
$oldSession = $_SESSION ?? [];
$oldPost = $_POST;
$oldGet = $_GET;
$oldRequestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_role'] = 'full_access';

    $categories = loadJSON('categories.json');
    $categories[$categoryId] = [
        'id' => $categoryId,
        'name_key' => 'category.' . $categoryId . '.name',
        'short_key' => 'category.' . $categoryId . '.short',
        'long_key' => 'category.' . $categoryId . '.long',
        'image' => 'Dubai.jpg',
        'images' => ['Dubai.jpg', 'Palermo.jpg'],
        'use_custom_image' => true,
        'volumes' => ['100ml'],
        'has_fragrance' => true,
        'allowed_fragrances' => ['bellini', 'eden'],
        'sort_order' => 999,
        'active' => true,
        'show_in_catalog' => true,
        'show_in_navigation' => true,
        'show_in_footer' => true,
    ];
    assertTrue(saveJSON('categories.json', $categories), 'Failed to save optional-image test category.');

    $categoryTranslations = [];
    foreach (I18N::getSupportedLanguages() as $lang) {
        $categoryTranslations[$lang] = [
            'name' => 'Optional Image Category',
            'short' => 'Optional image short ' . strtoupper($lang),
            'long' => 'Optional image long ' . strtoupper($lang),
        ];
    }
    assertTrue(saveEntityTranslations('category', $categoryId, $categoryTranslations), 'Failed to save optional-image category translations.');

    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_GET = ['id' => ''];
    $_POST = [];
    ob_start();
    include $repoRoot . '/admin/product-edit.php';
    $createFormHtml = ob_get_clean();

    assertTrue(strpos($createFormHtml, 'This product class uses fragrance images as the canonical storefront visual model.') !== false, 'Create form did not explain fragrance-driven visual model.');
    assertTrue(strpos($createFormHtml, 'name="images"') === false, 'Create form still exposed standalone product images for fragrance-driven products.');

    $_POST = [
        'product_id' => $productId,
        'original_id' => '',
        'category' => $categoryId,
        'fragrance_mode' => 'fixed_fragrance',
        'fixed_fragrance' => 'bellini',
        'active' => '1',
        'variants' => [
            ['volume' => '100ml', 'fragrance' => '', 'price' => '11.10'],
        ],
    ];
    foreach (I18N::getSupportedLanguages() as $lang) {
        $_POST['name_' . $lang] = 'Optional Image Product ' . strtoupper($lang);
        $_POST['description_' . $lang] = 'Optional image product description ' . strtoupper($lang);
    }
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_GET = [];

    ob_start();
    include $repoRoot . '/admin/product-edit.php';
    $adminHtml = ob_get_clean();

    assertTrue(strpos($adminHtml, 'Product created successfully.') !== false, 'Admin product save still failed without product images.');

    $products = loadJSON('products.json');
    assertTrue(isset($products[$productId]), 'No-image product was not saved.');
    assertTrue(!isset($products[$productId]['image']), 'No-image product unexpectedly stored a primary image.');
    assertTrue(!isset($products[$productId]['images']), 'No-image product unexpectedly stored an image gallery.');

    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_GET = ['id' => $productId];
    $_POST = [];
    ob_start();
    include $repoRoot . '/admin/product-edit.php';
    $editFormHtml = ob_get_clean();

    assertTrue(strpos($editFormHtml, 'name="images"') === false, 'Edit form still exposed standalone product images for fragrance-driven products.');

    $expectedSku = generateSKU($productId, '100ml', 'bellini');
    $stock = loadJSON('stock.json');
    $branchStock = loadBranchStock();
    assertTrue(isset($stock[$expectedSku]), 'No-image product SKU missing from stock.json.');
    foreach ($branchStock as $branchId => $branchData) {
        assertTrue(isset($branchData[$expectedSku]), "No-image product SKU missing from branch $branchId.");
    }

    $productHtml = renderPhpPage($repoRoot . '/product.php', ['id' => $productId, 'lang' => 'en']);
    $categoryHtml = renderPhpPage($repoRoot . '/category.php', ['slug' => $categoryId, 'lang' => 'en']);
    $fragranceImage = getFragranceImage('bellini');

    assertTrue(strpos($productHtml, $fragranceImage) !== false, 'Product page did not fall back to the fragrance image.');
    assertTrue(strpos($categoryHtml, $fragranceImage) !== false, 'Category product card did not fall back to the fragrance image.');

    echo "PASS\n";
} finally {
    $_SESSION = $oldSession;
    $_POST = $oldPost;
    $_GET = $oldGet;
    $_SERVER['REQUEST_METHOD'] = $oldRequestMethod;

    foreach ($backups as $file => $content) {
        if ($content === null) {
            @unlink($file);
        } else {
            file_put_contents($file, $content);
        }
    }
}
