<?php
/**
 * Product - Single product page
 */

require_once __DIR__ . '/init.php';

// Prevent caching of product pages to ensure latest prices are always shown
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$productId = $_GET['id'] ?? '';
$currentLang = I18N::getLanguage();

// Load data
$products = loadJSON('products.json');
$categories = loadJSON('categories.json');
$fragrances = loadJSON('fragrances.json');
$accessoriesData = loadJSON('accessories.json');

if (!isset($products[$productId])) {
    header('Location: catalog.php?lang=' . $currentLang);
    exit;
}

$product = $products[$productId];
$categorySlug = $product['category'] ?? '';
$category = $categories[$categorySlug] ?? [];

$productName = I18N::t('product.' . $productId . '.name', $product['name_key'] ?? $productId);
$productDesc = I18N::t('product.' . $productId . '.desc', $product['desc_key'] ?? '');
$categoryName = I18N::t('category.' . $categorySlug . '.name', ucfirst(str_replace('_', ' ', $categorySlug)));
$productVariants = getNormalizedProductVariants($product);

// Check if this is an accessory with multiple images
$productImages = [];
$isAccessory = false;
$accessoryData = null;
if ($categorySlug === 'accessories' && isset($accessoriesData[$productId])) {
    $isAccessory = true;
    $accessoryData = $accessoriesData[$productId];
    // DO NOT use priceCHF from accessories.json - always use variants[] from products.json
    // This ensures consistency and proper volume-based pricing
}

$allowedFrags = getProductFragranceOptions($product, $categorySlug, $accessoryData);
$volumes = getProductVolumeOptions($product, $categorySlug, $accessoryData);
$showVolumeSelector = count($volumes) > 1;
$showFragranceSelector = productHasFragranceSelector($product, $categorySlug, $accessoryData);
$fixedFragrance = !$showFragranceSelector && !empty($allowedFrags) ? $allowedFrags[0] : '';
$productImages = getProductImageList($product, $accessoryData);
$hasExplicitProductImages = !empty($productImages);
$initialFragranceImage = !empty($allowedFrags[0] ?? '') ? getFragranceImage($allowedFrags[0]) : getCanonicalImageUrl('placeholder.svg');
$primaryProductImage = $hasExplicitProductImages
    ? getCanonicalImageUrl($productImages[0])
    : $initialFragranceImage;

$errorPlaceholder = getCanonicalImageUrl('placeholder.svg');

// Get default price
if (!isset($defaultPrice)) {
    $defaultPrice = 0;
    if (!empty($productVariants)) {
        $defaultPrice = $productVariants[0]['priceCHF'] ?? 0;
    }
}

include __DIR__ . '/includes/header.php';
?>

<section class="category-hero <?php echo $isAccessory ? 'category-hero--accessory' : ''; ?>">
    <div class="category-hero-text">
        <div class="category-hero__content">
            <p class="section-heading__label">
                <a href="category.php?slug=<?php echo htmlspecialchars($categorySlug); ?>&lang=<?php echo $currentLang; ?>">
                    <?php echo htmlspecialchars($categoryName); ?>
                </a>
            </p>
            <h1><?php echo htmlspecialchars($productName); ?></h1>
            <p class="category-hero__desc"><?php echo nl2br(htmlspecialchars($productDesc)); ?></p>
        </div>
    </div>
    <div class="category-hero-image">
        <?php if (count($productImages) > 1): ?>
            <!-- Image Gallery/Slider for multiple images -->
            <div class="product-gallery" data-product-gallery>
                <div class="product-gallery__main">
                    <?php foreach ($productImages as $index => $imgFile): ?>
                        <img 
                            src="<?php echo htmlspecialchars(getCanonicalImageUrl($imgFile)); ?>"
                            alt="<?php echo htmlspecialchars($productName); ?>"
                            class="product-gallery__image <?php echo $index === 0 ? 'is-active' : ''; ?>"
                            data-gallery-image="<?php echo $index; ?>"
                            onerror="this.src='<?php echo $errorPlaceholder; ?>'">
                    <?php endforeach; ?>
                </div>
                <div class="product-gallery__nav">
                    <button type="button" class="product-gallery__prev" data-gallery-prev aria-label="Previous image">&lt;</button>
                    <button type="button" class="product-gallery__next" data-gallery-next aria-label="Next image">&gt;</button>
                </div>
                <div class="product-gallery__thumbs">
                    <?php foreach ($productImages as $index => $imgFile): ?>
                        <img
                            src="<?php echo htmlspecialchars(getCanonicalImageUrl($imgFile)); ?>"
                            alt="<?php echo htmlspecialchars($productName); ?>"
                            class="product-gallery__thumb <?php echo $index === 0 ? 'is-active' : ''; ?>"
                            data-gallery-thumb="<?php echo $index; ?>"
                            onerror="this.src='<?php echo $errorPlaceholder; ?>'">
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else: ?>
            <!-- Single image display -->
            <div class="category-hero__image" data-category="<?php echo htmlspecialchars($categorySlug); ?>">
                <img src="<?php echo htmlspecialchars($primaryProductImage); ?>" 
                     alt="<?php echo htmlspecialchars($productName); ?>" 
                     class="category-hero__image-el"
                      onerror="this.src='<?php echo $errorPlaceholder; ?>'">
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="catalog-section">
    <div class="container">
        <div class="product-card-wrapper">
            <article class="product-card" 
                     data-product-card 
                     data-product-id="<?php echo htmlspecialchars($productId); ?>"
                     data-product-name="<?php echo htmlspecialchars($productName); ?>"
                     data-category="<?php echo htmlspecialchars($categorySlug); ?>">
                <div class="product-card__inner">
                    <?php 
                    // Get first fragrance for initial image display
                    $firstFragCode = $showFragranceSelector ? ($allowedFrags[0] ?? null) : ($fixedFragrance ?: null);
                    
                    // Determine the image to show - use fragrance image from /img/ folder
                    $displayImage = $hasExplicitProductImages ? $primaryProductImage : getCanonicalImageUrl('placeholder.svg');
                    if (!$hasExplicitProductImages && $firstFragCode) {
                        $displayImage = getFragranceImage($firstFragCode);
                    }
                    ?>
                    <div class="product-card__image">
                        <img src="<?php echo htmlspecialchars($displayImage); ?>" 
                             alt="<?php echo htmlspecialchars($productName); ?>" 
                             class="product-card__image-el"
                             data-product-image
                             data-product-id="<?php echo htmlspecialchars($productId); ?>"
                             data-default-image="<?php echo htmlspecialchars($displayImage); ?>"
                             data-allow-fragrance-image="<?php echo $hasExplicitProductImages ? 'false' : 'true'; ?>"
                             onerror="this.src='/img/placeholder.svg'">
                    </div>
                    
                    <div class="product-card__content">
                        <header class="product-card__header">
                            <h2 class="product-card__title">
                                <?php echo htmlspecialchars($productName); ?>
                                <!-- Favorite Button inline with title -->
                                <?php
                                $customerId = getCurrentCustomerId();
                                $isFav = false;
                                if ($customerId) {
                                    $favorites = getCustomerFavorites($customerId);
                                    $isFav = in_array($productId, $favorites, true);
                                }
                                ?>
                                <button class="favorite-btn product-detail__favorite-btn <?php echo $isFav ? 'favorite-btn--active' : ''; ?>"
                                        data-product-id="<?php echo htmlspecialchars($productId); ?>"
                                        type="button"
                                        title="<?php echo $isFav ? I18N::t('common.removeFromFavorites', 'Remove from favorites') : I18N::t('common.addToFavorites', 'Add to favorites'); ?>">
                                    ❤
                                </button>
                            </h2>
                        </header>
                        
                        <div class="product-card__selectors">
                            <?php if ($showVolumeSelector): ?>
                                <div class="product-card__field">
                                    <label><?php echo I18N::t('common.volume', 'Volume'); ?></label>
                                    <select class="product-card__select product-card__select--volume" data-volume-select>
                                        <?php foreach ($volumes as $vol): ?>
                                            <option value="<?php echo htmlspecialchars($vol); ?>">
                                                <?php echo htmlspecialchars($vol); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php else: ?>
                                <input type="hidden" data-volume-select value="<?php echo htmlspecialchars($volumes[0] ?? 'standard'); ?>">
                            <?php endif; ?>
                            
                            <?php if (!empty($allowedFrags) && $showFragranceSelector): ?>
                                <div class="product-card__field">
                                    <label><?php echo I18N::t('common.fragrance', 'Fragrance'); ?></label>
                                    <select class="product-card__select product-card__select--fragrance" 
                                            data-fragrance-select
                                            data-product-id="<?php echo htmlspecialchars($productId); ?>">
                                        <?php foreach ($allowedFrags as $fragCode): ?>
                                            <?php
                                            $fragName = I18N::t('fragrance.' . $fragCode . '.name', ucfirst(str_replace('_', ' ', $fragCode)));
                                            ?>
                                            <option value="<?php echo htmlspecialchars($fragCode); ?>"
                                                    data-image="<?php echo htmlspecialchars(getFragranceImage($fragCode)); ?>">
                                                <?php echo htmlspecialchars($fragName); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php elseif ($fixedFragrance !== ''): ?>
                                <input type="hidden" data-fragrance-select value="<?php echo htmlspecialchars($fixedFragrance); ?>">
                            <?php else: ?>
                                <input type="hidden" data-fragrance-select value="none">
                            <?php endif; ?>
                        </div>
                        
                        <!-- Fragrance Description Block -->
                        <div class="product-card__fragrance-description"
                             data-product-id="<?php echo htmlspecialchars($productId); ?>">
                            <p class="product-card__fragrance-text product-card__fragrance-text--short"></p>
                            <p class="product-card__fragrance-text product-card__fragrance-text--full"></p>
                            <button type="button" class="product-card__fragrance-toggle">
                                <?php echo I18N::t('ui.fragrance.read_more', 'Read more'); ?>
                            </button>
                        </div>
                        
                        <div class="product-card__price-row">
                            <span class="product-card__price-label"><?php echo I18N::t('common.price', 'Price'); ?></span>
                            <span class="product-card__price-value" data-price-display>
                                CHF <?php echo number_format($defaultPrice, 2); ?>
                            </span>
                        </div>
                        
                        <button type="button" class="btn btn--gold product-card__add-to-cart" data-add-to-cart>
                            <?php echo I18N::t('common.addToCart', 'Add to cart'); ?>
                        </button>
                    </div>
                </div>
            </article>
        </div>
    </div>
</section>

<!-- Recommended Products -->
<section class="category-products category-products--recommended">
    <div class="container">
        <h2 class="text-center mb-4"><?php echo I18N::t('product.recommended', 'You might also like'); ?></h2>
        <div class="products-grid products-grid--recommended">
            <?php
            // Get recommended products: 10 random active products from all categories except current
            $allProducts = $products;
            unset($allProducts[$productId]);
            
            // Keep only active products
            $allProducts = array_filter($allProducts, function($p) {
                return !empty($p['active']);
            });
            
            // Shuffle and take 6
            $keys = array_keys($allProducts);
            shuffle($keys);
            $keys = array_slice($keys, 0, 6);
            
            $recommendedProducts = [];
            foreach ($keys as $k) {
                $recommendedProducts[$k] = $allProducts[$k];
            }
            
            foreach ($recommendedProducts as $recId => $recProduct):
                $recName = I18N::t('product.' . $recId . '.name', $recProduct['name_key'] ?? $recId);
                $recCategory = $recProduct['category'] ?? '';
                $recVariants = $recProduct['variants'] ?? [];
                // Always use variants for pricing - first variant's price is the default
                $recPrice = !empty($recVariants) ? ($recVariants[0]['priceCHF'] ?? 0) : 0;
                
                $recProductImages = getProductImageList($recProduct, $accessoriesData[$recId] ?? null);
                $recFragrances = getProductFragranceOptions($recProduct, $recCategory, $accessoriesData[$recId] ?? null);
                $recImgPath = !empty($recProductImages)
                    ? getCanonicalImageUrl($recProductImages[0])
                    : (!empty($recFragrances[0]) ? getFragranceImage($recFragrances[0]) : getCanonicalImageUrl('placeholder.svg'));
                $recPlaceholder = getCanonicalImageUrl('placeholder.svg');
                
                // All recommendations link to category page
                $recommendationUrl = '/category.php?slug=' . urlencode($recCategory) . '&lang=' . urlencode($currentLang);
            ?>
                <?php
                $customerId = getCurrentCustomerId();
                $isFav = false;
                if ($customerId) {
                    $favorites = getCustomerFavorites($customerId);
                    $isFav = in_array($recId, $favorites, true);
                }
                ?>
                <article class="catalog-card catalog-card--recommended"
                         data-product-card
                         data-product-id="<?php echo htmlspecialchars($recId); ?>"
                         data-product-name="<?php echo htmlspecialchars($recName); ?>"
                         data-category="<?php echo htmlspecialchars($recCategory); ?>">
                    <a href="<?php echo htmlspecialchars($recommendationUrl); ?>" 
                       class="catalog-card__link" style="text-decoration: none; color: inherit;">
                        <div class="catalog-card__image-wrapper catalog-card__image-wrapper--recommended">
                            <img src="<?php echo htmlspecialchars($recImgPath); ?>" 
                                 alt="<?php echo htmlspecialchars($recName); ?>" 
                                 class="catalog-card__image catalog-card__image--recommended"
                                 onerror="this.src='<?php echo htmlspecialchars($recPlaceholder); ?>'">
                        </div>
                        <div class="catalog-card__content catalog-card__content--recommended">
                            <div class="catalog-card__title-row">
                                <div class="catalog-card__title catalog-card__title--recommended">
                                    <?php echo htmlspecialchars($recName); ?>
                                </div>
                                <button class="favorite-btn favorite-btn--recommended <?php echo $isFav ? 'favorite-btn--active' : ''; ?>"
                                        data-product-id="<?php echo htmlspecialchars($recId); ?>"
                                        type="button"
                                        onclick="event.preventDefault(); event.stopPropagation();"
                                        title="<?php echo $isFav ? I18N::t('common.removeFromFavorites', 'Remove from favorites') : I18N::t('common.addToFavorites', 'Add to favorites'); ?>">
                                    ❤
                                </button>
                            </div>
                            <div class="catalog-card__price">
                                CHF <?php echo number_format($recPrice, 2); ?>
                            </div>
                        </div>
                    </a>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<script>
<?php
$fragranceCodesForPage = array_values(array_unique(array_filter($allowedFrags, 'strlen')));
$productPriceConfig = buildProductPriceConfig($product, $accessoryData);
?>
window.FRAGRANCES = <?php echo json_encode(array_map(function($code) {
    return [
        'name' => I18N::t('fragrance.' . $code . '.name', ucfirst(str_replace('_', ' ', $code))),
        'short' => I18N::t('fragrance.' . $code . '.short', ''),
        'image' => getFragranceImage($code)
    ];
}, !empty($fragranceCodesForPage) ? array_combine($fragranceCodesForPage, $fragranceCodesForPage) : [])); ?>;

// Pass multilingual fragrance descriptions from i18n
window.FRAGRANCE_DESCRIPTIONS = <?php 
$fragranceDescriptions = [];
foreach ($allowedFrags as $fragCode) {
    $fragranceDescriptions[$fragCode] = [
        'name' => I18N::t('fragrance.' . $fragCode . '.name', ucfirst(str_replace('_', ' ', $fragCode))),
        'short' => I18N::t('fragrance.' . $fragCode . '.short', ''),
        'full' => I18N::t('fragrance.' . $fragCode . '.full', '')
    ];
}
echo json_encode($fragranceDescriptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP); 
?>;

// Pass prices for volume-based pricing
// This creates a productId => pricing map used by app.js
window.PRICES = <?php echo json_encode([$productId => $productPriceConfig], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

// Pass I18N labels for JS
window.I18N_LABELS = {
    fragrance_read_more: <?php echo json_encode(I18N::t('ui.fragrance.read_more', 'Read more')); ?>,
    fragrance_collapse: <?php echo json_encode(I18N::t('ui.fragrance.collapse', 'Collapse')); ?>
};
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
