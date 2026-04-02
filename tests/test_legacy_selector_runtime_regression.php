<?php

require_once __DIR__ . '/../init.php';

$repoRoot = dirname(__DIR__);

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

$products = loadJSON('products.json');

$legacyPositiveProduct = $products['diffuser_classic'] ?? null;
assertTrue($legacyPositiveProduct !== null, 'Missing legacy positive selector product diffuser_classic.');
assertTrue(productHasFragranceSelector($legacyPositiveProduct, 'aroma_diffusers'), 'Legacy diffuser helper no longer reports a fragrance selector.');
$positiveOptions = getProductFragranceOptions($legacyPositiveProduct, 'aroma_diffusers');
assertTrue(in_array('cherry_blossom', $positiveOptions, true), 'Legacy diffuser helper lost cherry_blossom.');
assertTrue(in_array('dubai', $positiveOptions, true), 'Legacy diffuser helper lost dubai.');

$aromaCategoryHtml = renderPhpPage($repoRoot . '/category.php', ['slug' => 'aroma_diffusers', 'lang' => 'en']);
assertTrue(strpos($aromaCategoryHtml, 'value="cherry_blossom"') !== false, 'Legacy aroma_diffusers category page lost the positive fragrance selector option.');
assertTrue(strpos($aromaCategoryHtml, 'value="dubai"') !== false, 'Legacy aroma_diffusers category page did not preserve the full legacy fragrance selector list.');

$legacyNegativeProduct = $products['limited_new_york'] ?? null;
assertTrue($legacyNegativeProduct !== null, 'Missing legacy negative selector product limited_new_york.');
assertTrue(!productHasFragranceSelector($legacyNegativeProduct, 'limited_edition'), 'Limited edition helper incorrectly exposes a fragrance selector.');
$limitedProductHtml = renderPhpPage($repoRoot . '/product.php', ['id' => 'limited_new_york', 'lang' => 'en']);
assertTrue(strpos($limitedProductHtml, 'product-card__select--fragrance') === false, 'Limited edition product page incorrectly rendered a fragrance selector.');
assertTrue(strpos($limitedProductHtml, 'value="new_york"') !== false, 'Limited edition product page lost its fixed fragrance state.');

$newProduct = $products['qa_full_cycle_product_20260401'] ?? null;
assertTrue($newProduct !== null, 'Missing admin-created regression product qa_full_cycle_product_20260401.');
assertTrue(!productHasFragranceSelector($newProduct, 'qa_full_cycle_category_20260401'), 'Admin-created fixed-fragrance product incorrectly exposes a selector.');
$newProductHtml = renderPhpPage($repoRoot . '/product.php', ['id' => 'qa_full_cycle_product_20260401', 'lang' => 'en']);
assertTrue(strpos($newProductHtml, 'product-card__select--fragrance') === false, 'Admin-created fixed-fragrance product page regressed by rendering a fragrance selector.');

echo "PASS\n";
