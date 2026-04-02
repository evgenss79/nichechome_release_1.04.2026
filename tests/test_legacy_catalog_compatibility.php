<?php

require_once __DIR__ . '/../init.php';

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
];
foreach (glob($dataRoot . '/i18n/ui_*.json') as $file) {
    $filesToRestore[] = $file;
}

$backups = [];
foreach ($filesToRestore as $file) {
    $backups[$file] = file_exists($file) ? file_get_contents($file) : null;
}

$suffix = substr(md5(uniqid('legacy_catalog_', true)), 0, 8);
$categoryId = 'legacy_image_category_' . $suffix;
$productId = 'legacy_selector_product_' . $suffix;

try {
    $categories = loadJSON('categories.json');
    $categories[$categoryId] = [
        'id' => $categoryId,
        'name_key' => 'category.' . $categoryId . '.name',
        'short_key' => 'category.' . $categoryId . '.short',
        'long_key' => 'category.' . $categoryId . '.long',
        'image' => '/img/Dubai.jpg',
        'images' => ['img/Dubai.jpg', '../assets/img/fragrances/Palermo.jpg'],
        'use_custom_image' => false,
        'volumes' => ['100ml', '200ml'],
        'has_fragrance' => true,
        'allowed_fragrances' => ['bellini', 'eden'],
        'sort_order' => 997,
        'active' => true,
        'show_in_catalog' => true,
        'show_in_navigation' => true,
        'show_in_footer' => true,
    ];
    assertTrue(saveJSON('categories.json', $categories), 'Failed to save legacy compatibility category.');

    $categoryTranslations = [];
    foreach (I18N::getSupportedLanguages() as $lang) {
        $categoryTranslations[$lang] = [
            'name' => 'Legacy Image Category',
            'short' => 'Legacy category short ' . strtoupper($lang),
            'long' => 'Legacy category long ' . strtoupper($lang),
        ];
    }
    assertTrue(saveEntityTranslations('category', $categoryId, $categoryTranslations), 'Failed to save legacy category translations.');

    $products = loadJSON('products.json');
    $products[$productId] = [
        'id' => $productId,
        'category' => $categoryId,
        'name_key' => 'product.' . $productId . '.name',
        'desc_key' => 'product.' . $productId . '.desc',
        'fragrance' => 'eden',
        'active' => true,
        'variants' => [
            ['volume' => '100ml', 'priceCHF' => 15.5],
            ['volume' => '200ml', 'priceCHF' => 25.5],
        ],
    ];
    assertTrue(saveJSON('products.json', $products), 'Failed to save legacy compatibility product.');

    $productTranslations = [];
    foreach (I18N::getSupportedLanguages() as $lang) {
        $productTranslations[$lang] = [
            'name' => 'Legacy Selector Product',
            'desc' => 'Legacy selector product description ' . strtoupper($lang),
        ];
    }
    assertTrue(saveEntityTranslations('product', $productId, $productTranslations), 'Failed to save legacy product translations.');

    $savedCategory = loadJSON('categories.json')[$categoryId];
    $savedProduct = loadJSON('products.json')[$productId];

    assertTrue(getCategoryImage($categoryId) === '/img/Dubai.jpg', 'Legacy non-custom category did not prefer stored canonical image.');
    assertTrue(getProductFragranceOptions($savedProduct, $categoryId) === ['bellini', 'eden'], 'Legacy product did not inherit category fragrances.');
    assertTrue(productHasFragranceSelector($savedProduct, $categoryId), 'Legacy product should expose fragrance selector from category defaults.');
    assertTrue(getProductDefaultFragrance($savedProduct, $categoryId) === 'eden', 'Legacy product did not preserve stored default fragrance.');

    $catalogHtml = renderPhpPage($repoRoot . '/catalog.php', ['lang' => 'en']);
    $categoryHtml = renderPhpPage($repoRoot . '/category.php', ['slug' => $categoryId, 'lang' => 'en']);
    $productHtml = renderPhpPage($repoRoot . '/product.php', ['id' => $productId, 'lang' => 'en']);

    assertTrue(strpos($catalogHtml, '/img/Dubai.jpg') !== false, 'Catalog card did not render legacy category image from stored canonical path.');
    assertTrue(strpos($categoryHtml, '/img/Dubai.jpg') !== false, 'Category hero did not render legacy category image from stored canonical path.');
    assertTrue((bool)preg_match('/<option[^>]+value="eden"[^>]+selected/', $categoryHtml), 'Legacy category product selector did not preserve stored default fragrance.');
    assertTrue((bool)preg_match('/<option[^>]+value="eden"[^>]+selected/', $productHtml), 'Legacy product page selector did not preserve stored default fragrance.');
    assertTrue(strpos($categoryHtml, '/img/Eden.jpg') !== false, 'Legacy category product card did not fall back to the canonical default fragrance image.');
    assertTrue(strpos($productHtml, '/img/Eden.jpg') !== false, 'Legacy product page did not fall back to the canonical default fragrance image.');
    assertTrue(strpos($categoryHtml, 'assets/img/fragrances') === false, 'Legacy category page still rendered non-canonical fragrance image paths.');
    assertTrue(strpos($productHtml, 'assets/img/fragrances') === false, 'Legacy product page still rendered non-canonical fragrance image paths.');

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
