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
    $dataRoot . '/fragrances.json',
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

$oldSession = $_SESSION ?? [];
$oldPost = $_POST;
$oldGet = $_GET;
$oldRequestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$renderCategoryId = 'canonical_render_category';
$renderProductId = 'canonical_render_product';
$savedCategoryId = 'canonical_saved_category';
$savedProductId = 'saved_img_product';
$invalidProductId = 'invalid_img_product';

try {
    assertTrue(normalizeImageFilename('/img/Etna.jpg', true) === 'Etna.jpg', 'Failed to normalize /img/Etna.jpg.');
    assertTrue(normalizeImageFilename('img/Bellini.jpg', true) === 'Bellini.jpg', 'Failed to normalize img/Bellini.jpg.');
    assertTrue(normalizeImageFilename('../assets/img/fragrances/Palermo.jpg', true) === 'Palermo.jpg', 'Failed to normalize legacy local image path.');
    $missingImageError = null;
    assertTrue(normalizeImageFilename('img/does-not-exist.jpg', true, $missingImageError) === '', 'Missing img file should not normalize successfully.');
    assertTrue($missingImageError !== null, 'Missing img file should set an explicit error.');
    $remoteImageError = null;
    assertTrue(normalizeImageFilename('https://example.com/test.jpg', true, $remoteImageError) === '', 'Remote image URL should be rejected.');
    assertTrue($remoteImageError !== null, 'Remote image URL rejection should set an error message.');

    $fragrances = loadJSON('fragrances.json');
    $fragrances['bellini']['image'] = '../assets/img/fragrances/Bellini.jpg';
    assertTrue(saveJSON('fragrances.json', $fragrances), 'Failed to save fragrance image test data.');
    assertTrue(getFragranceImage('bellini') === '/img/Bellini.jpg', 'Fragrance image did not normalize to canonical /img path.');

    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_role'] = 'full_access';

    $categories = loadJSON('categories.json');
    $categories[$renderCategoryId] = [
        'id' => $renderCategoryId,
        'name_key' => 'category.' . $renderCategoryId . '.name',
        'short_key' => 'category.' . $renderCategoryId . '.short',
        'long_key' => 'category.' . $renderCategoryId . '.long',
        'image' => '/img/Dubai.jpg',
        'images' => ['img/Dubai.jpg', '../assets/img/fragrances/Palermo.jpg'],
        'use_custom_image' => true,
        'volumes' => ['100ml', '200ml'],
        'has_fragrance' => true,
        'allowed_fragrances' => ['bellini'],
        'sort_order' => 998,
        'active' => true,
        'show_in_catalog' => true,
        'show_in_navigation' => true,
        'show_in_footer' => true,
    ];
    assertTrue(saveJSON('categories.json', $categories), 'Failed to save render category.');

    $categoryTranslations = [];
    foreach (I18N::getSupportedLanguages() as $lang) {
        $categoryTranslations[$lang] = [
            'name' => 'Canonical Render Category',
            'short' => 'Canonical category short ' . strtoupper($lang),
            'long' => 'Canonical category long ' . strtoupper($lang),
        ];
    }
    assertTrue(saveEntityTranslations('category', $renderCategoryId, $categoryTranslations), 'Failed to save render category translations.');

    $products = loadJSON('products.json');
    $products[$renderProductId] = [
        'id' => $renderProductId,
        'category' => $renderCategoryId,
        'name_key' => 'product.' . $renderProductId . '.name',
        'desc_key' => 'product.' . $renderProductId . '.desc',
        'image' => '/img/Etna.jpg',
        'images' => ['img/Etna.jpg', '../assets/img/fragrances/Bellini.jpg'],
        'allowed_fragrances' => ['bellini'],
        'has_fragrance_selector' => true,
        'active' => true,
        'variants' => [
            ['volume' => '100ml', 'fragrance' => 'bellini', 'priceCHF' => 11.1],
            ['volume' => '200ml', 'fragrance' => 'bellini', 'priceCHF' => 19.9],
        ],
    ];
    assertTrue(saveJSON('products.json', $products), 'Failed to save render product.');

    $productTranslations = [];
    foreach (I18N::getSupportedLanguages() as $lang) {
        $productTranslations[$lang] = [
            'name' => 'Canonical Render Product',
            'desc' => 'Canonical product description ' . strtoupper($lang),
        ];
    }
    assertTrue(saveEntityTranslations('product', $renderProductId, $productTranslations), 'Failed to save render product translations.');

    $categoryHtml = renderPhpPage($repoRoot . '/category.php', ['slug' => $renderCategoryId, 'lang' => 'en']);
    $productHtml = renderPhpPage($repoRoot . '/product.php', ['id' => $renderProductId, 'lang' => 'en']);

    assertTrue(strpos($categoryHtml, '/img/Dubai.jpg') !== false, 'Category hero did not render canonical /img main image.');
    assertTrue(strpos($categoryHtml, '/img/Palermo.jpg') !== false, 'Category slider did not render canonical /img image.');
    assertTrue(strpos($categoryHtml, 'assets/img/fragrances') === false, 'Category page still renders non-canonical fragrance image folder.');
    assertTrue(strpos($productHtml, '/img/Etna.jpg') !== false, 'Product page did not render canonical /img main image.');
    assertTrue(strpos($productHtml, '/img/Bellini.jpg') !== false, 'Product gallery did not render canonical /img image.');
    assertTrue(strpos($productHtml, 'assets/img/fragrances') === false, 'Product page still renders non-canonical fragrance image folder.');

    $adminFragranceHtml = renderPhpPage($repoRoot . '/admin/fragrances.php');
    assertTrue(strpos($adminFragranceHtml, '../assets/img/fragrances/') === false, 'Admin fragrances page still renders assets/img/fragrances paths.');
    assertTrue(strpos($adminFragranceHtml, '/img/Bellini.jpg') !== false, 'Admin fragrances page did not render the canonical /img fragrance image.');

    $_POST = [
        'action' => 'save_category',
        'original_id' => '',
        'category_id' => $savedCategoryId,
        'image' => '/img/Dubai.jpg',
        'images' => "img/Dubai.jpg\n../assets/img/fragrances/Palermo.jpg",
        'sort_order' => '997',
        'active' => '1',
        'show_in_catalog' => '1',
        'show_in_navigation' => '1',
        'show_in_footer' => '1',
        'use_custom_image' => '1',
        'has_fragrance' => '1',
        'volumes' => "100ml\n200ml",
        'allowed_fragrances' => ['bellini'],
    ];
    foreach (I18N::getSupportedLanguages() as $lang) {
        $_POST['name_' . $lang] = 'Saved Category ' . strtoupper($lang);
        $_POST['short_' . $lang] = 'Saved short ' . strtoupper($lang);
        $_POST['long_' . $lang] = 'Saved long ' . strtoupper($lang);
    }
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_GET = [];
    ob_start();
    include $repoRoot . '/admin/categories.php';
    $adminCategoryHtml = ob_get_clean();

    assertTrue(strpos($adminCategoryHtml, 'Category created successfully.') !== false, 'Category admin save did not succeed with normalizable img paths.');
    $categories = loadJSON('categories.json');
    assertTrue(($categories[$savedCategoryId]['image'] ?? '') === 'Dubai.jpg', 'Category primary image was not normalized to filename-only storage.');
    assertTrue(($categories[$savedCategoryId]['images'] ?? []) === ['Dubai.jpg', 'Palermo.jpg'], 'Category slider images were not normalized to filename-only storage.');

    $_POST = [
        'product_id' => $savedProductId,
        'original_id' => '',
        'category' => $renderCategoryId,
        'images' => "/img/Etna.jpg\n../assets/img/fragrances/Bellini.jpg",
        'fragrance_mode' => 'fixed_fragrance',
        'fixed_fragrance' => 'bellini',
        'active' => '1',
        'variants' => [
            ['volume' => '100ml', 'fragrance' => '', 'price' => '11.10'],
        ],
    ];
    foreach (I18N::getSupportedLanguages() as $lang) {
        $_POST['name_' . $lang] = 'Saved Product ' . strtoupper($lang);
        $_POST['description_' . $lang] = 'Saved product description ' . strtoupper($lang);
    }
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_GET = [];
    ob_start();
    include $repoRoot . '/admin/product-edit.php';
    $adminProductHtml = ob_get_clean();

    assertTrue(strpos($adminProductHtml, 'Product created successfully.') !== false, 'Product admin save did not succeed with normalizable img paths.');
    $products = loadJSON('products.json');
    assertTrue(($products[$savedProductId]['image'] ?? '') === 'Etna.jpg', 'Product primary image was not normalized to filename-only storage.');
    assertTrue(($products[$savedProductId]['images'] ?? []) === ['Etna.jpg', 'Bellini.jpg'], 'Product gallery images were not normalized to filename-only storage.');

    $_POST = [
        'product_id' => $invalidProductId,
        'original_id' => '',
        'category' => $renderCategoryId,
        'images' => "https://example.com/bad.jpg",
        'fragrance_mode' => 'fixed_fragrance',
        'fixed_fragrance' => 'bellini',
        'active' => '1',
        'variants' => [
            ['volume' => '100ml', 'fragrance' => '', 'price' => '12.50'],
        ],
    ];
    foreach (I18N::getSupportedLanguages() as $lang) {
        $_POST['name_' . $lang] = 'Invalid Product ' . strtoupper($lang);
        $_POST['description_' . $lang] = 'Invalid product description ' . strtoupper($lang);
    }
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_GET = [];
    ob_start();
    include $repoRoot . '/admin/product-edit.php';
    $invalidAdminProductHtml = ob_get_clean();

    assertTrue(strpos($invalidAdminProductHtml, 'Product images must reference existing files from img/') !== false, 'Invalid remote product image input was not rejected.');
    $products = loadJSON('products.json');
    assertTrue(!isset($products[$invalidProductId]), 'Invalid product image save should not persist the product.');

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
