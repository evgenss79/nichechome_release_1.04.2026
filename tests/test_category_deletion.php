<?php

require_once __DIR__ . '/../init.php';

$repoRoot = dirname(__DIR__);
$dataRoot = $repoRoot . '/data';
$i18nRoot = $dataRoot . '/i18n';
$backupRoot = $dataRoot . '/backups';
$stockLog = $repoRoot . '/logs/stock.log';

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function renderPhpPage(string $path, array $query = []): string
{
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

function renderAdminCategoriesPage(string $repoRoot, array $post = [], array $get = []): string
{
    $oldGet = $_GET;
    $oldPost = $_POST;
    $oldRequestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    $_GET = $get;
    $_POST = $post;
    $_SERVER['REQUEST_METHOD'] = empty($post) ? 'GET' : 'POST';

    ob_start();
    include $repoRoot . '/admin/categories.php';
    $output = ob_get_clean();

    $_GET = $oldGet;
    $_POST = $oldPost;
    $_SERVER['REQUEST_METHOD'] = $oldRequestMethod;

    return $output;
}

function saveLegacyCategoryTranslations(string $i18nRoot, string $categoryId, string $label): void
{
    foreach (I18N::getSupportedLanguages() as $lang) {
        $path = $i18nRoot . '/categories_' . $lang . '.json';
        $data = json_decode((string)file_get_contents($path), true);
        if (!is_array($data)) {
            $data = [];
        }
        if (!isset($data['category']) || !is_array($data['category'])) {
            $data['category'] = [];
        }

        $data['category'][$categoryId] = [
            'name' => $label . ' ' . strtoupper($lang),
            'short' => 'Short ' . $label . ' ' . strtoupper($lang),
            'long' => 'Long ' . $label . ' ' . strtoupper($lang),
        ];

        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}

$filesToRestore = [
    $dataRoot . '/categories.json',
    $dataRoot . '/products.json',
    $dataRoot . '/catalog_version.json',
    $stockLog,
];
foreach (glob($i18nRoot . '/ui_*.json') as $file) {
    $filesToRestore[] = $file;
}
foreach (glob($i18nRoot . '/categories_*.json') as $file) {
    $filesToRestore[] = $file;
}

$backups = [];
foreach ($filesToRestore as $file) {
    $backups[$file] = file_exists($file) ? file_get_contents($file) : null;
}

$backupFilesBefore = is_dir($backupRoot) ? glob($backupRoot . '/*') : [];
$oldSession = $_SESSION ?? [];
$oldPost = $_POST;
$oldGet = $_GET;
$oldRequestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$emptyCategoryId = 'category_delete_empty_test';
$blockedCategoryId = 'category_delete_blocked_test';
$blockedProductId = 'category_delete_product_test';

try {
    $categories = loadJSON('categories.json');
    $categories[$emptyCategoryId] = [
        'id' => $emptyCategoryId,
        'name_key' => 'category.' . $emptyCategoryId . '.name',
        'short_key' => 'category.' . $emptyCategoryId . '.short',
        'long_key' => 'category.' . $emptyCategoryId . '.long',
        'image' => 'Dubai.jpg',
        'images' => ['Dubai.jpg'],
        'use_custom_image' => true,
        'sort_order' => 991,
        'active' => true,
        'show_in_catalog' => true,
        'show_in_navigation' => true,
        'show_in_footer' => true,
        'has_fragrance' => false,
        'volumes' => [],
    ];
    $categories[$blockedCategoryId] = [
        'id' => $blockedCategoryId,
        'name_key' => 'category.' . $blockedCategoryId . '.name',
        'short_key' => 'category.' . $blockedCategoryId . '.short',
        'long_key' => 'category.' . $blockedCategoryId . '.long',
        'image' => 'Palermo.jpg',
        'images' => ['Palermo.jpg'],
        'use_custom_image' => true,
        'sort_order' => 992,
        'active' => true,
        'show_in_catalog' => true,
        'show_in_navigation' => true,
        'show_in_footer' => true,
        'has_fragrance' => false,
        'volumes' => ['100ml', '200ml'],
    ];
    assertTrue(saveJSON('categories.json', $categories), 'Failed to seed categories for delete tests.');

    $categoryTranslations = [];
    foreach (I18N::getSupportedLanguages() as $lang) {
        $categoryTranslations[$lang] = [
            'name' => 'Delete Category ' . strtoupper($lang),
            'short' => 'Delete short ' . strtoupper($lang),
            'long' => 'Delete long ' . strtoupper($lang),
        ];
    }
    assertTrue(saveEntityTranslations('category', $emptyCategoryId, $categoryTranslations), 'Failed to save empty-category translations.');
    assertTrue(saveEntityTranslations('category', $blockedCategoryId, $categoryTranslations), 'Failed to save blocked-category translations.');
    saveLegacyCategoryTranslations($i18nRoot, $emptyCategoryId, 'Legacy Empty Category');
    saveLegacyCategoryTranslations($i18nRoot, $blockedCategoryId, 'Legacy Blocked Category');

    $products = loadJSON('products.json');
    $products[$blockedProductId] = [
        'id' => $blockedProductId,
        'category' => $blockedCategoryId,
        'name_key' => 'product.' . $blockedProductId . '.name',
        'desc_key' => 'product.' . $blockedProductId . '.desc',
        'image' => 'Etna.jpg',
        'variants' => [
            ['volume' => '100ml', 'priceCHF' => 11.1],
            ['volume' => '200ml', 'priceCHF' => 19.9],
        ],
        'active' => true,
    ];
    assertTrue(saveJSON('products.json', $products), 'Failed to save blocked product for category delete test.');

    $productTranslations = [];
    foreach (I18N::getSupportedLanguages() as $lang) {
        $productTranslations[$lang] = [
            'name' => 'Blocked Delete Product ' . strtoupper($lang),
            'desc' => 'Blocked delete product description ' . strtoupper($lang),
        ];
    }
    assertTrue(saveEntityTranslations('product', $blockedProductId, $productTranslations), 'Failed to save blocked product translations.');

    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_role'] = 'full_access';

    $adminIndexHtml = renderAdminCategoriesPage($repoRoot);
    assertTrue(strpos($adminIndexHtml, 'delete_category') !== false, 'Categories admin page is missing delete action markup.');
    assertTrue(strpos($adminIndexHtml, '🗑️ Delete') !== false, 'Categories admin page is missing the delete button label.');
    assertTrue(strpos($adminIndexHtml, 'PERMANENT DELETION') !== false, 'Categories admin page is missing delete confirmation text.');

    $blockedResult = deleteCategory($blockedCategoryId);
    assertTrue($blockedResult['success'] === false, 'Category delete should be blocked when products are still assigned.');
    assertTrue(in_array($blockedProductId, $blockedResult['details']['blocked_products'] ?? [], true), 'Blocked delete should report the assigned product ID.');
    $categoriesAfterBlocked = loadJSON('categories.json');
    $productsAfterBlocked = loadJSON('products.json');
    assertTrue(isset($categoriesAfterBlocked[$blockedCategoryId]), 'Blocked category should remain after failed delete.');
    assertTrue(isset($productsAfterBlocked[$blockedProductId]), 'Blocked product should remain after failed category delete.');

    $blockedAdminHtml = renderAdminCategoriesPage($repoRoot, [
        'action' => 'delete_category',
        'category_id' => $blockedCategoryId,
    ]);
    assertTrue(strpos($blockedAdminHtml, 'Blocking products: ' . $blockedProductId . '.') !== false, 'Admin blocked-delete message must list the blocking product.');

    $emptyDeleteHtml = renderAdminCategoriesPage($repoRoot, [
        'action' => 'delete_category',
        'category_id' => $emptyCategoryId,
    ]);
    assertTrue(strpos($emptyDeleteHtml, "Category '$emptyCategoryId' deleted successfully!") !== false, 'Admin success message missing after empty category delete.');

    $categoriesAfterDelete = loadJSON('categories.json');
    assertTrue(!isset($categoriesAfterDelete[$emptyCategoryId]), 'Deleted category still exists in categories.json.');
    assertTrue(isset($categoriesAfterDelete[$blockedCategoryId]), 'Blocked category should still exist after empty-category delete.');

    foreach (I18N::getSupportedLanguages() as $lang) {
        $uiPath = $i18nRoot . '/ui_' . $lang . '.json';
        $uiData = json_decode((string)file_get_contents($uiPath), true);
        assertTrue(!isset($uiData['category'][$emptyCategoryId]), "Deleted category still exists in ui_$lang.json.");
        assertTrue(isset($uiData['category'][$blockedCategoryId]), "Blocked category translations were unexpectedly removed from ui_$lang.json.");

        $legacyPath = $i18nRoot . '/categories_' . $lang . '.json';
        $legacyData = json_decode((string)file_get_contents($legacyPath), true);
        assertTrue(!isset($legacyData['category'][$emptyCategoryId]), "Deleted category still exists in categories_$lang.json.");
        assertTrue(isset($legacyData['category'][$blockedCategoryId]), "Blocked category translations were unexpectedly removed from categories_$lang.json.");
    }

    $catalogHtml = renderPhpPage($repoRoot . '/catalog.php', ['lang' => 'en']);
    $deletedCategoryLink = 'category.php?slug=' . $emptyCategoryId . '&amp;lang=en';
    assertTrue(strpos($catalogHtml, $deletedCategoryLink) === false, 'Deleted category still appears in catalog/header/footer output.');

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

    $backupFilesAfter = is_dir($backupRoot) ? glob($backupRoot . '/*') : [];
    $newBackupFiles = array_diff($backupFilesAfter, $backupFilesBefore);
    foreach ($newBackupFiles as $file) {
        @unlink($file);
    }
}
