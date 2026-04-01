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
    $dataRoot . '/catalog_version.json'
];
foreach (glob($dataRoot . '/i18n/ui_*.json') as $file) {
    $filesToRestore[] = $file;
}

$backups = [];
foreach ($filesToRestore as $file) {
    $backups[$file] = file_exists($file) ? file_get_contents($file) : null;
}

$categoryId = 'admin_test_category';
$productId = 'admin_test_product';

try {
    $categories = loadJSON('categories.json');
    $categories[$categoryId] = [
        'id' => $categoryId,
        'name_key' => 'category.' . $categoryId . '.name',
        'short_key' => 'category.' . $categoryId . '.short',
        'long_key' => 'category.' . $categoryId . '.long',
        'image' => 'Dubai.jpg',
        'images' => ['Dubai.jpg', 'Palermo.jpg'],
        'use_custom_image' => true,
        'volumes' => ['100ml', '200ml'],
        'has_fragrance' => true,
        'allowed_fragrances' => ['bellini', 'eden'],
        'sort_order' => 99,
        'active' => true,
        'show_in_catalog' => true,
        'show_in_navigation' => true,
        'show_in_footer' => true
    ];
    assertTrue(saveJSON('categories.json', $categories), 'Failed to save test category.');

    $categoryTranslations = [];
    foreach (I18N::getSupportedLanguages() as $lang) {
        $categoryTranslations[$lang] = [
            'name' => 'Admin Test Category',
            'short' => 'Short description ' . strtoupper($lang),
            'long' => 'Long description ' . strtoupper($lang)
        ];
    }
    assertTrue(saveEntityTranslations('category', $categoryId, $categoryTranslations), 'Failed to save category translations.');

    $products = loadJSON('products.json');
    $products[$productId] = [
        'id' => $productId,
        'category' => $categoryId,
        'name_key' => 'product.' . $productId . '.name',
        'desc_key' => 'product.' . $productId . '.desc',
        'image' => 'Etna.jpg',
        'images' => ['Etna.jpg', 'Bellini.jpg'],
        'allowed_fragrances' => ['bellini', 'eden'],
        'has_fragrance_selector' => true,
        'active' => true,
        'variants' => [
            ['volume' => '100ml', 'fragrance' => 'bellini', 'priceCHF' => 10.5],
            ['volume' => '100ml', 'fragrance' => 'eden', 'priceCHF' => 11.5],
            ['volume' => '200ml', 'fragrance' => 'bellini', 'priceCHF' => 18.5],
            ['volume' => '200ml', 'fragrance' => 'eden', 'priceCHF' => 19.5]
        ]
    ];
    assertTrue(saveJSON('products.json', $products), 'Failed to save test product.');

    $productTranslations = [];
    foreach (I18N::getSupportedLanguages() as $lang) {
        $productTranslations[$lang] = [
            'name' => 'Admin Test Product',
            'desc' => 'Product description ' . strtoupper($lang)
        ];
    }
    assertTrue(saveEntityTranslations('product', $productId, $productTranslations), 'Failed to save product translations.');

    initializeMissingSkuKeys(false);

    $expectedSkus = [
        generateSKU($productId, '100ml', 'bellini'),
        generateSKU($productId, '100ml', 'eden'),
        generateSKU($productId, '200ml', 'bellini'),
        generateSKU($productId, '200ml', 'eden')
    ];

    assertTrue(abs(getProductPrice($productId, '100ml', 'bellini') - 10.5) < 0.001, 'Exact variant pricing failed for 100ml bellini.');
    assertTrue(abs(getProductPrice($productId, '200ml', 'eden') - 19.5) < 0.001, 'Exact variant pricing failed for 200ml eden.');

    $universe = loadSkuUniverse();
    $stock = loadJSON('stock.json');
    $branchStock = loadBranchStock();
    foreach ($expectedSkus as $sku) {
        assertTrue(isset($universe[$sku]), "Missing SKU in universe: $sku");
        assertTrue(isset($stock[$sku]), "Missing SKU in stock.json: $sku");
        assertTrue((int)($stock[$sku]['quantity'] ?? -1) === 0, "Stock quantity must start at 0 for $sku");
        foreach ($branchStock as $branchId => $branchData) {
            assertTrue(isset($branchData[$sku]), "Missing SKU $sku in branch $branchId");
            assertTrue((int)($branchData[$sku]['quantity'] ?? -1) === 0, "Branch quantity must start at 0 for $sku in $branchId");
        }
    }

    $catalogHtml = renderPhpPage($repoRoot . '/catalog.php', ['lang' => 'en']);
    assertTrue(strpos($catalogHtml, 'category.php?slug=' . $categoryId . '&amp;lang=en') !== false, 'New category missing from catalog output.');
    assertTrue(strpos($catalogHtml, 'Admin Test Category') !== false, 'New category name missing from catalog output.');

    $categoryHtml = renderPhpPage($repoRoot . '/category.php', ['slug' => $categoryId, 'lang' => 'en']);
    assertTrue(strpos($categoryHtml, 'Admin Test Product') !== false, 'New product missing from category page.');
    assertTrue(strpos($categoryHtml, 'data-category-gallery') !== false, 'Category gallery slider markup missing from category page.');
    assertTrue(strpos($categoryHtml, 'value="200ml"') !== false, 'Volume selector missing 200ml option.');
    assertTrue(strpos($categoryHtml, 'value="eden"') !== false, 'Fragrance selector missing eden option.');
    assertTrue(strpos($categoryHtml, '/img/Etna.jpg') !== false, 'Category product card did not render the admin product image.');

    $productHtml = renderPhpPage($repoRoot . '/product.php', ['id' => $productId, 'lang' => 'en']);
    assertTrue(strpos($productHtml, 'Admin Test Product') !== false, 'Product page did not render test product.');
    assertTrue(strpos($productHtml, '"admin_test_product"') !== false, 'Product pricing payload missing product key.');
    assertTrue(strpos($productHtml, '/img/Etna.jpg') !== false, 'Product page did not render the primary admin product image.');
    assertTrue(strpos($productHtml, 'Bellini.jpg') !== false, 'Product gallery did not render custom images.');

    echo "PASS\n";
} finally {
    foreach ($backups as $file => $content) {
        if ($content === null) {
            @unlink($file);
        } else {
            file_put_contents($file, $content);
        }
    }
}
