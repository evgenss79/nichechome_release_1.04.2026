<?php
/**
 * Category - Products listing for a single category
 */

require_once __DIR__ . '/init.php';

// Prevent caching of category pages to ensure latest prices are always shown
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$slug = $_GET['slug'] ?? '';
$currentLang = I18N::getLanguage();

// Redirect special categories
if ($slug === 'gift_sets') {
    header('Location: gift-sets.php?lang=' . $currentLang);
    exit;
}
if ($slug === 'aroma_marketing') {
    header('Location: aroma-marketing.php?lang=' . $currentLang);
    exit;
}

// Load category data
$categories = loadJSON('categories.json');
$products = loadJSON('products.json');
$fragrances = loadJSON('fragrances.json');
$stock = loadJSON('stock.json');

if (!isset($categories[$slug])) {
    header('Location: catalog.php?lang=' . $currentLang);
    exit;
}

$category = $categories[$slug];
$categoryName = I18N::t('category.' . $slug . '.name', ucfirst(str_replace('_', ' ', $slug)));
$categoryShort = I18N::t('category.' . $slug . '.short', '');
$categoryLong = I18N::t('category.' . $slug . '.long', '');
$categoryImage = getCategoryImage($slug);

// Determine if this is Home Perfume category for hero image scaling
$heroImageClass = $slug === 'home_perfume' ? 'hero-home-perfume' : '';

// Handle accessories category specially
if ($slug === 'accessories') {
    // Load accessories data
    $accessories = loadJSON('accessories.json');
    $activeAccessories = array_filter($accessories, function($item) {
        return !empty($item['active']);
    });
    
    include __DIR__ . '/includes/header.php';
    ?>
    <main class="category-page">
    <section class="category-hero">
        <div class="category-hero-text">
            <div class="category-hero__content">
                <h1><?php echo htmlspecialchars($categoryName); ?></h1>
                <div class="category-hero__description-block"
                     data-full-description="<?php echo htmlspecialchars($categoryLong ?: $categoryShort, ENT_QUOTES); ?>">
                    <p class="category-hero__description-short"></p>
                    <p class="category-hero__description-full"></p>
                    <button type="button" class="category-hero__description-toggle">
                        <?php echo I18N::t('ui.category.read_more', 'Read more'); ?>
                    </button>
                </div>
            </div>
        </div>
        <div class="category-hero-image">
            <div class="category-hero__image <?php echo $heroImageClass; ?>" data-category="<?php echo htmlspecialchars($slug); ?>">
                <img src="<?php echo htmlspecialchars($categoryImage); ?>" 
                     alt="<?php echo htmlspecialchars($categoryName); ?>" 
                     class="category-hero__image-el" 
                     onerror="this.src='/img/placeholder.svg'">
            </div>
        </div>
    </section>

    <section class="category-products category-products--accessories">
        <div class="accessories-grid accessories-grid--accessories">
            <?php foreach ($activeAccessories as $accessoryId => $accessory): ?>
                <?php
                $productId   = $accessory['id'] ?? $accessoryId;
                $productName = I18N::t($accessory['name_key'] ?? '', $productId);
                $price       = $accessory['priceCHF'] ?? 0;
                $images      = $accessory['images'] ?? [];
                $mainImage   = !empty($images) ? '/img/' . rawurlencode($images[0]) : '/img/placeholder.svg';
                ?>
                <article class="catalog-card"
                         data-product-card
                         data-product-id="<?php echo htmlspecialchars($productId); ?>"
                         data-product-name="<?php echo htmlspecialchars($productName); ?>"
                         data-category="<?php echo htmlspecialchars($slug); ?>">
                    <a href="product.php?id=<?php echo htmlspecialchars($productId); ?>&lang=<?php echo $currentLang; ?>"
                       class="catalog-card__link" style="text-decoration: none; color: inherit;">
                        <div class="catalog-card__title-bar">
                            <?php echo htmlspecialchars($productName); ?>
                        </div>
                        <div class="catalog-card__image-wrapper">
                            <img src="<?php echo htmlspecialchars($mainImage); ?>"
                                 alt="<?php echo htmlspecialchars($productName); ?>"
                                 class="catalog-card__image"
                                 onerror="this.src='/img/placeholder.svg'">
                        </div>
                        <div class="catalog-card__price-wrapper">
                            <span class="catalog-card__price">
                                CHF <?php echo number_format($price, 2); ?>
                            </span>
                        </div>
                    </a>
                    <!-- Favorite Button -->
                    <?php
                    $customerId = getCurrentCustomerId();
                    $isFav = false;
                    if ($customerId) {
                        $favorites = getCustomerFavorites($customerId);
                        $isFav = in_array($productId, $favorites, true);
                    }
                    ?>
                    <button class="favorite-btn <?php echo $isFav ? 'favorite-btn--active' : ''; ?>"
                            data-product-id="<?php echo htmlspecialchars($productId); ?>"
                            type="button"
                            title="<?php echo $isFav ? I18N::t('common.removeFromFavorites', 'Remove from favorites') : I18N::t('common.addToFavorites', 'Add to favorites'); ?>">
                        ❤
                    </button>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
    </main>

    <script>
    // Pass prices for accessories from products.json
    // This ensures dynamic pricing from admin panel is reflected on the accessories page
    window.PRICES = <?php 
    $pricesData = [];
    foreach ($activeAccessories as $accessoryId => $accessory) {
        $productId = $accessory['id'] ?? $accessoryId;
        // Get price from products.json (the source of truth)
        $productFromJson = $products[$productId] ?? null;
        if ($productFromJson && !empty($productFromJson['variants'])) {
            $variants = $productFromJson['variants'];
            if (count($variants) > 1) {
                // Multiple variants with different prices
                $volumePrices = [];
                foreach ($variants as $variant) {
                    $vol = $variant['volume'] ?? 'standard';
                    $volumePrices[$vol] = (float)($variant['priceCHF'] ?? 0);
                }
                $pricesData[$productId] = $volumePrices;
            } else {
                // Single variant
                $pricesData[$productId] = (float)($variants[0]['priceCHF'] ?? 0);
            }
        }
    }
    echo json_encode($pricesData);
    ?>;

    // Pass I18N labels for JS
    window.I18N_LABELS = {
        category_read_more: <?php echo json_encode(I18N::t('ui.category.read_more', 'Read more')); ?>,
        category_collapse: <?php echo json_encode(I18N::t('ui.category.collapse', 'Collapse')); ?>
    };
    </script>

    <?php
    include __DIR__ . '/includes/footer.php';
    exit;
}

// Get products for this category
$categoryProducts = array_filter($products, function($p) use ($slug) {
    return ($p['category'] ?? '') === $slug;
});

$categoryDefaultFragrances = allowedFragrances($slug);

include __DIR__ . '/includes/header.php';
?>

<?php
// Get category full description for toggle
$fullCategoryDescription = $categoryLong ?: $categoryShort;
?>

<main class="category-page">
<section class="category-hero">
    <div class="category-hero-text">
        <div class="category-hero__content">
            <h1><?php echo htmlspecialchars($categoryName); ?></h1>
            <div class="category-hero__description-block"
                 data-full-description="<?php echo htmlspecialchars($fullCategoryDescription, ENT_QUOTES); ?>">
                <p class="category-hero__description-short"></p>
                <p class="category-hero__description-full"></p>
                <button type="button" class="category-hero__description-toggle">
                    <?php echo I18N::t('ui.category.read_more', 'Read more'); ?>
                </button>
            </div>
        </div>
    </div>
    <div class="category-hero-image">
        <div class="category-hero__image <?php echo $heroImageClass; ?>" data-category="<?php echo htmlspecialchars($slug); ?>">
            <img src="<?php echo htmlspecialchars($categoryImage); ?>" 
                 alt="<?php echo htmlspecialchars($categoryName); ?>" 
                 class="category-hero__image-el" 
                 onerror="this.src='/img/placeholder.svg'">
        </div>
    </div>
</section>

<section class="category-products">
    <div class="products-list">
        <?php foreach ($categoryProducts as $productId => $product): ?>
            <?php
            $productName = I18N::t('product.' . $productId . '.name', $product['name_key'] ?? $productId);
            $productDesc = I18N::t('product.' . $productId . '.desc', $product['desc_key'] ?? '');
            $productImage = $product['image'] ?? '';
            $productVariants = getNormalizedProductVariants($product);
            $productVolumes = getProductVolumeOptions($product, $slug);
            $productFragrances = getProductFragranceOptions($product, $slug);
            $showVolumeSelector = count($productVolumes) > 1;
            $showFragranceSelector = productHasFragranceSelector($product, $slug);
            $fixedFragrance = !$showFragranceSelector && !empty($productFragrances) ? $productFragrances[0] : '';
            $defaultPrice = !empty($productVariants) ? (float)($productVariants[0]['priceCHF'] ?? 0) : 0;
            $firstFragCode = $showFragranceSelector
                ? ($productFragrances[0] ?? null)
                : ($fixedFragrance ?: null);
            
            // Determine the image to show - use fragrance image from /img/ folder
            $displayImage = '/img/placeholder.svg';
            if ($firstFragCode) {
                $displayImage = getFragranceImage($firstFragCode);
            } elseif ($productImage) {
                $displayImage = '/img/' . rawurlencode($productImage);
            }
            ?>
            <article class="product-card" 
                     data-product-card 
                     data-product-id="<?php echo htmlspecialchars($productId); ?>"
                     data-product-name="<?php echo htmlspecialchars($productName); ?>"
                     data-category="<?php echo htmlspecialchars($slug); ?>">
                <div class="product-card__inner">
                    <div class="product-card__image">
                        <img src="<?php echo htmlspecialchars($displayImage); ?>" 
                             alt="<?php echo htmlspecialchars($productName); ?>" 
                             class="product-card__image-el"
                             data-product-image
                             data-product-id="<?php echo htmlspecialchars($productId); ?>"
                             data-default-image="<?php echo htmlspecialchars($displayImage); ?>"
                             onerror="this.src='/img/placeholder.svg'">
                    </div>
                    
                    <div class="product-card__content">
                        <header class="product-card__header">
                            <h2 class="product-card__title"><?php echo htmlspecialchars($productName); ?></h2>
                            <?php if ($slug !== 'limited_edition' && $slug !== 'car_perfume'): ?>
                                <p class="product-card__description"><?php echo htmlspecialchars($productDesc); ?></p>
                            <?php endif; ?>
                        </header>
                        
                        <!-- Favorite Button -->
                        <?php
                        $customerId = getCurrentCustomerId();
                        $isFav = false;
                        if ($customerId) {
                            $favorites = getCustomerFavorites($customerId);
                            $isFav = in_array($productId, $favorites, true);
                        }
                        ?>
                        <button class="favorite-btn <?php echo $isFav ? 'favorite-btn--active' : ''; ?>"
                                data-product-id="<?php echo htmlspecialchars($productId); ?>"
                                type="button"
                                title="<?php echo $isFav ? I18N::t('common.removeFromFavorites', 'Remove from favorites') : I18N::t('common.addToFavorites', 'Add to favorites'); ?>"
                                style="position: absolute; top: 1rem; right: 1rem;">
                            ❤
                        </button>
                        
                        <div class="product-card__selectors">
                            <?php if ($showVolumeSelector): ?>
                                <div class="product-card__field">
                                    <label><?php echo I18N::t('common.volume', 'Volume'); ?></label>
                                    <select class="product-card__select product-card__select--volume" data-volume-select>
                                        <?php foreach ($productVolumes as $vol): ?>
                                            <option value="<?php echo htmlspecialchars($vol); ?>">
                                                <?php echo htmlspecialchars($vol); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php else: ?>
                                <input type="hidden" data-volume-select value="<?php echo htmlspecialchars($productVolumes[0] ?? 'standard'); ?>">
                            <?php endif; ?>
                            
                            <?php if ($showFragranceSelector && !empty($productFragrances)): ?>
                                <div class="product-card__field">
                                    <label><?php echo I18N::t('common.fragrance', 'Fragrance'); ?></label>
                                    <select class="product-card__select product-card__select--fragrance" 
                                            data-fragrance-select
                                            data-product-id="<?php echo htmlspecialchars($productId); ?>">
                                        <?php foreach ($productFragrances as $fragCode): ?>
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
        <?php endforeach; ?>
        
        <?php if (empty($categoryProducts)): ?>
            <?php
            // Set variables for generic card
            $genericProductId = $slug . '_product';
            $genericFirstFrag = !empty($categoryDefaultFragrances) ? $categoryDefaultFragrances[0] : null;
            $genericVolumes = getVolumesForCategory($slug);
            $genericDisplayImage = $genericFirstFrag 
                ? getFragranceImage($genericFirstFrag) 
                : $categoryImage;
            ?>
            <!-- Show generic product card for categories without specific products -->
            <article class="product-card" 
                     data-product-card 
                     data-product-id="<?php echo htmlspecialchars($genericProductId); ?>"
                     data-product-name="<?php echo htmlspecialchars($categoryName); ?>"
                     data-category="<?php echo htmlspecialchars($slug); ?>">
                <div class="product-card__inner">
                    <div class="product-card__image">
                        <img src="<?php echo htmlspecialchars($genericDisplayImage); ?>" 
                             alt="<?php echo htmlspecialchars($categoryName); ?>" 
                             class="product-card__image-el"
                             data-product-image
                             data-product-id="<?php echo htmlspecialchars($genericProductId); ?>"
                             data-default-image="<?php echo htmlspecialchars($genericDisplayImage); ?>"
                             onerror="this.src='/img/placeholder.svg'">
                    </div>
                    
                    <div class="product-card__content">
                        <header class="product-card__header">
                            <h2 class="product-card__title"><?php echo htmlspecialchars($categoryName); ?></h2>
                            <p class="product-card__description"><?php echo htmlspecialchars($categoryShort); ?></p>
                        </header>
                        
                        <!-- Favorite Button -->
                        <?php
                        $customerId = getCurrentCustomerId();
                        $isFav = false;
                        if ($customerId) {
                            $favorites = getCustomerFavorites($customerId);
                            $isFav = in_array($genericProductId, $favorites, true);
                        }
                        ?>
                        <button class="favorite-btn <?php echo $isFav ? 'favorite-btn--active' : ''; ?>"
                                data-product-id="<?php echo htmlspecialchars($genericProductId); ?>"
                                type="button"
                                title="<?php echo $isFav ? I18N::t('common.removeFromFavorites', 'Remove from favorites') : I18N::t('common.addToFavorites', 'Add to favorites'); ?>"
                                style="position: absolute; top: 1rem; right: 1rem;">
                            ❤
                        </button>
                        
                        <div class="product-card__selectors">
                            <?php if (!empty($genericVolumes)): ?>
                                <div class="product-card__field">
                                    <label><?php echo I18N::t('common.volume', 'Volume'); ?></label>
                                    <select class="product-card__select product-card__select--volume" data-volume-select>
                                        <?php foreach ($genericVolumes as $vol): ?>
                                            <option value="<?php echo htmlspecialchars($vol); ?>">
                                                <?php echo htmlspecialchars($vol); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($categoryDefaultFragrances)): ?>
                                <div class="product-card__field">
                                    <label><?php echo I18N::t('common.fragrance', 'Fragrance'); ?></label>
                                    <select class="product-card__select product-card__select--fragrance" 
                                            data-fragrance-select
                                            data-product-id="<?php echo htmlspecialchars($genericProductId); ?>">
                                        <?php foreach ($categoryDefaultFragrances as $fragCode): ?>
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
                            <?php endif; ?>
                        </div>
                        
                        <!-- Fragrance Description Block -->
                        <div class="product-card__fragrance-description"
                             data-product-id="<?php echo htmlspecialchars($genericProductId); ?>">
                            <p class="product-card__fragrance-text product-card__fragrance-text--short"></p>
                            <p class="product-card__fragrance-text product-card__fragrance-text--full"></p>
                            <button type="button" class="product-card__fragrance-toggle">
                                <?php echo I18N::t('ui.fragrance.read_more', 'Read more'); ?>
                            </button>
                        </div>
                        
                        <div class="product-card__price-row">
                            <span class="product-card__price-label"><?php echo I18N::t('common.price', 'Price'); ?></span>
                            <span class="product-card__price-value" data-price-display>
                                CHF <?php echo number_format(getPriceByCategory($slug, $genericVolumes[0] ?? ''), 2); ?>
                            </span>
                        </div>
                        
                        <button type="button" class="btn btn--gold product-card__add-to-cart" data-add-to-cart>
                            <?php echo I18N::t('common.addToCart', 'Add to cart'); ?>
                        </button>
                    </div>
                </div>
            </article>
        <?php endif; ?>
    </div>
</section>

<?php
// Add "You might also like" section for non-accessories categories
if ($slug !== 'accessories') {
    // Load all products
    $productsAll = $products; // Already loaded at the top
    
    // Get the main product ID for this category (first product with matching category)
    $mainProductId = null;
    foreach ($productsAll as $pid => $p) {
        if (($p['category'] ?? '') === $slug) {
            $mainProductId = $pid;
            break;
        }
    }
    
    // Exclude the main product of this category from recommendations
    if ($mainProductId !== null) {
        unset($productsAll[$mainProductId]);
    }
    
    // Keep only active products
    $productsAll = array_filter($productsAll, function($p) {
        return !empty($p['active']);
    });
    
    // Shuffle and take first 6
    $keys = array_keys($productsAll);
    shuffle($keys);
    $keys = array_slice($keys, 0, 6);
    
    $recommendedProducts = [];
    foreach ($keys as $k) {
        $recommendedProducts[$k] = $productsAll[$k];
    }
    
    // Load accessories data only if needed (if any recommended products are accessories)
    $accessoriesData = [];
    $hasAccessories = false;
    foreach ($recommendedProducts as $recId => $recProduct) {
        if (($recProduct['category'] ?? '') === 'accessories') {
            $hasAccessories = true;
            break;
        }
    }
    if ($hasAccessories) {
        $accessoriesData = loadJSON('accessories.json');
    }
    ?>
    
    <!-- Recommended Products Section -->
    <section class="category-products category-products--recommended">
        <div class="container">
            <h2 class="section-heading text-center mb-4">
                <?php echo I18N::t('product.recommended', 'You might also like'); ?>
            </h2>
            <div class="products-grid products-grid--recommended">
                <?php foreach ($recommendedProducts as $recId => $recProduct): ?>
                    <?php
                    $recName = I18N::t('product.' . $recId . '.name', $recProduct['name_key'] ?? $recId);
                    $recImage = $recProduct['image'] ?? '';
                    $recCategory = $recProduct['category'] ?? '';
                    $recVariants = $recProduct['variants'] ?? [];
                    $recPrice = !empty($recVariants) ? ($recVariants[0]['priceCHF'] ?? 0) : 0;
                    
                    // Handle accessories which may have price in accessories.json
                    if ($recCategory === 'accessories' && isset($accessoriesData[$recId]['priceCHF'])) {
                        $recPrice = $accessoriesData[$recId]['priceCHF'];
                    }
                    
                    // Determine image path - all images are in /img/ directory
                    $recImgPath = '/img/' . rawurlencode($recImage);
                    $recPlaceholder = '/img/placeholder.svg';
                    
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
    
    <?php
}
?>
</main>

<script>
<?php
$categoryFragranceSet = [];
$pricePayload = [];
foreach ($categoryProducts as $productId => $product) {
    foreach (getProductFragranceOptions($product, $slug) as $fragCode) {
        $categoryFragranceSet[$fragCode] = $fragCode;
    }
    $pricePayload[$productId] = buildProductPriceConfig($product);
}
if (empty($categoryProducts) && !empty($categoryDefaultFragrances)) {
    foreach ($categoryDefaultFragrances as $fragCode) {
        $categoryFragranceSet[$fragCode] = $fragCode;
    }
}
$categoryFragranceCodes = array_values($categoryFragranceSet);
$fragranceDescriptions = [];
foreach ($categoryFragranceCodes as $fragCode) {
    $fragranceDescriptions[$fragCode] = [
        'name' => I18N::t('fragrance.' . $fragCode . '.name', ucfirst(str_replace('_', ' ', $fragCode))),
        'short' => I18N::t('fragrance.' . $fragCode . '.short', ''),
        'full' => I18N::t('fragrance.' . $fragCode . '.full', '')
    ];
}
?>
// Pass fragrance data to JavaScript with correct /img/ paths
window.FRAGRANCES = <?php echo json_encode(array_map(function($code) {
    return [
        'name' => I18N::t('fragrance.' . $code . '.name', ucfirst(str_replace('_', ' ', $code))),
        'short' => I18N::t('fragrance.' . $code . '.short', ''),
        'image' => getFragranceImage($code)
    ];
}, !empty($categoryFragranceCodes) ? array_combine($categoryFragranceCodes, $categoryFragranceCodes) : [])); ?>;

// Pass multilingual fragrance descriptions from i18n
window.FRAGRANCE_DESCRIPTIONS = <?php echo json_encode($fragranceDescriptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP); ?>;

window.PRICES = <?php echo json_encode($pricePayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

// Pass I18N labels for JS
window.I18N_LABELS = {
    fragrance_read_more: <?php echo json_encode(I18N::t('ui.fragrance.read_more', 'Read more')); ?>,
    fragrance_collapse: <?php echo json_encode(I18N::t('ui.fragrance.collapse', 'Collapse')); ?>,
    category_read_more: <?php echo json_encode(I18N::t('ui.category.read_more', 'Read more')); ?>,
    category_collapse: <?php echo json_encode(I18N::t('ui.category.collapse', 'Collapse')); ?>
};
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
