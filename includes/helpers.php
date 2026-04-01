<?php
/**
 * Helper Functions for NICHEHOME.CH
 */

/**
 * Get allowed fragrances for a category
 */
function allowedFragrances(string $category): array {
    $categories = loadJSON('categories.json');
    $categoryData = $categories[$category] ?? [];
    if (isset($categoryData['allowed_fragrances']) && is_array($categoryData['allowed_fragrances'])) {
        return array_values(array_filter($categoryData['allowed_fragrances'], 'is_string'));
    }
    if (array_key_exists('has_fragrance', $categoryData) && !$categoryData['has_fragrance']) {
        return [];
    }

    $all = [
        'cherry_blossom', 'bellini', 'eden', 'rosso',
        'salted_caramel', 'santal', 'lime_basil', 'bamboo',
        'tobacco_vanilla', 'salty_water', 'christmas_tree',
        'fleur', 'blanc', 'green_mango', 'carolina',
        'sugar', 'dubai', 'africa', 'dune',
        'valencia', 'etna', 'new_york', 'abu_dhabi', 'palermo'
    ];

    $exclude = ['new_york', 'abu_dhabi', 'palermo'];  // never used except Limited Edition

    if ($category === 'scented_candles') {
        $exclude = array_merge($exclude, ['etna', 'valencia']);
    }
    if ($category === 'textile_perfume') {
        $exclude = array_merge($exclude, [
            'salted_caramel', 'cherry_blossom', 'dubai',
            'salty_water', 'rosso', 'christmas_tree'
        ]);
    }
    if ($category === 'limited_edition') {
        // Limited edition ONLY uses new_york, abu_dhabi, palermo
        return ['new_york', 'abu_dhabi', 'palermo'];
    }

    return array_values(array_diff($all, $exclude));
}

/**
 * Get price for diffuser by volume
 */
function diffuserPriceByVolume(string $volume): float {
    switch ($volume) {
        case '125ml':
        case '125':
            return 20.90;
        case '250ml':
        case '250':
            return 29.90;
        case '500ml':
        case '500':
            return 50.90;
        default:
            return 0.0;
    }
}

/**
 * Get price for candle by volume
 */
function candlePriceByVolume(string $volume): float {
    switch ($volume) {
        case '160ml':
        case '160':
            return 24.90;
        case '500ml':
        case '500':
            return 59.90;
        default:
            return 0.0;
    }
}

/**
 * Get price for home perfume by volume
 */
function homePerfumePriceByVolume(string $volume): float {
    switch ($volume) {
        case '10ml':
        case '10':
            return 9.90;
        case '50ml':
        case '50':
            return 19.90;
        default:
            return 0.0;
    }
}

/**
 * Get price for car perfume (fixed)
 */
function carPerfumePrice(): float {
    return 14.90;
}

/**
 * Get price for textile perfume (fixed)
 */
function textilePerfumePrice(): float {
    return 19.90;
}

/**
 * Get price for limited edition candles (fixed)
 */
function limitedEditionPrice(): float {
    return 39.90;
}

/**
 * Get price by category and volume
 */
function getPriceByCategory(string $category, string $volume = ''): float {
    switch ($category) {
        case 'aroma_diffusers':
            return diffuserPriceByVolume($volume);
        case 'scented_candles':
            return candlePriceByVolume($volume);
        case 'home_perfume':
            return homePerfumePriceByVolume($volume);
        case 'car_perfume':
            return carPerfumePrice();
        case 'textile_perfume':
            return textilePerfumePrice();
        case 'limited_edition':
            return limitedEditionPrice();
        default:
            return 0.0;
    }
}

/**
 * Get volumes for a category
 */
function getVolumesForCategory(string $category): array {
    $categories = loadJSON('categories.json');
    $categoryData = $categories[$category] ?? [];
    if (isset($categoryData['volumes']) && is_array($categoryData['volumes'])) {
        return array_values($categoryData['volumes']);
    }

    switch ($category) {
        case 'aroma_diffusers':
            return ['125ml', '250ml', '500ml'];
        case 'scented_candles':
            return ['160ml', '500ml'];
        case 'home_perfume':
            return ['10ml', '50ml'];
        default:
            return [];
    }
}

/**
 * Normalize volume labels for selector and price lookups.
 */
function normalizeVariantVolume(string $volume = 'standard'): string {
    $volume = trim($volume);
    return $volume !== '' ? $volume : 'standard';
}

/**
 * Normalize fragrance value for selector and SKU logic.
 */
function normalizeVariantFragrance(string $fragrance = ''): string {
    $fragrance = trim($fragrance);
    if ($fragrance === '' || strtolower($fragrance) === 'na' || strtolower($fragrance) === 'none' || strtolower($fragrance) === 'null') {
        return '';
    }
    return $fragrance;
}

/**
 * Build normalized product variants for storefront and pricing logic.
 */
function getNormalizedProductVariants(array $product, ?array $accessoryData = null): array {
    $variants = [];
    foreach (($product['variants'] ?? []) as $variant) {
        $price = (float)($variant['priceCHF'] ?? 0);
        if ($price < 0) {
            $price = 0;
        }
        $variants[] = [
            'volume' => normalizeVariantVolume((string)($variant['volume'] ?? 'standard')),
            'fragrance' => normalizeVariantFragrance((string)($variant['fragrance'] ?? '')),
            'priceCHF' => $price
        ];
    }

    if (!empty($variants)) {
        return $variants;
    }

    if ($accessoryData) {
        $volumes = !empty($accessoryData['volumes']) && is_array($accessoryData['volumes'])
            ? $accessoryData['volumes']
            : ['standard'];
        $volumePrices = is_array($accessoryData['volume_prices'] ?? null) ? $accessoryData['volume_prices'] : [];
        foreach ($volumes as $volume) {
            $variants[] = [
                'volume' => normalizeVariantVolume((string)$volume),
                'fragrance' => '',
                'priceCHF' => (float)($volumePrices[$volume] ?? $accessoryData['priceCHF'] ?? 0)
            ];
        }
    }

    return $variants;
}

/**
 * Build product price configuration for storefront selectors.
 */
function buildProductPriceConfig(array $product, ?array $accessoryData = null): array {
    $config = [];
    foreach (getNormalizedProductVariants($product, $accessoryData) as $variant) {
        $volume = $variant['volume'];
        $fragrance = $variant['fragrance'];
        $price = (float)$variant['priceCHF'];

        if ($fragrance !== '') {
            if (!isset($config[$volume]) || !is_array($config[$volume])) {
                $config[$volume] = [];
            }
            $config[$volume][$fragrance] = $price;
        } else {
            $config[$volume] = $price;
        }
    }
    return $config;
}

/**
 * Get available product volume options.
 */
function getProductVolumeOptions(array $product, string $category = '', ?array $accessoryData = null): array {
    $volumes = [];
    foreach (getNormalizedProductVariants($product, $accessoryData) as $variant) {
        $volumes[] = $variant['volume'];
    }

    if (empty($volumes)) {
        $volumes = getVolumesForCategory($category);
    }

    $volumes = array_values(array_unique(array_filter($volumes, 'strlen')));
    return empty($volumes) ? ['standard'] : $volumes;
}

/**
 * Get available product fragrance options.
 */
function getProductFragranceOptions(array $product, string $category = '', ?array $accessoryData = null): array {
    if (isset($product['allowed_fragrances']) && is_array($product['allowed_fragrances'])) {
        return array_values(array_filter($product['allowed_fragrances'], 'strlen'));
    }
    if (!empty($product['fragrance'])) {
        return [trim((string)$product['fragrance'])];
    }
    if ($accessoryData && isset($accessoryData['allowed_fragrances']) && is_array($accessoryData['allowed_fragrances'])) {
        return array_values(array_filter($accessoryData['allowed_fragrances'], 'strlen'));
    }

    $variantFragrances = [];
    foreach (getNormalizedProductVariants($product, $accessoryData) as $variant) {
        if ($variant['fragrance'] !== '') {
            $variantFragrances[] = $variant['fragrance'];
        }
    }
    $variantFragrances = array_values(array_unique($variantFragrances));
    if (!empty($variantFragrances)) {
        return $variantFragrances;
    }

    return allowedFragrances($category);
}

/**
 * Determine whether a product should expose a fragrance selector.
 */
function productHasFragranceSelector(array $product, string $category = '', ?array $accessoryData = null): bool {
    if (array_key_exists('has_fragrance_selector', $product)) {
        return !empty($product['has_fragrance_selector']);
    }
    if ($accessoryData && array_key_exists('has_fragrance_selector', $accessoryData)) {
        return !empty($accessoryData['has_fragrance_selector']);
    }
    if (!empty($product['fragrance'])) {
        return false;
    }

    return count(getProductFragranceOptions($product, $category, $accessoryData)) > 1;
}

/**
 * Get the canonical image directory.
 */
function getCanonicalImageDirectory(): string {
    static $directory = null;
    if ($directory === null) {
        $directory = realpath(__DIR__ . '/../img') ?: (__DIR__ . '/../img');
    }

    return $directory;
}

/**
 * Normalize a stored/admin image reference to the canonical img/ filename format.
 */
function normalizeImageFilename(string $image, bool $requireExistingFile = false, ?string &$error = null): string {
    static $existingFileCache = [];
    $error = null;
    $image = trim(rawurldecode($image));
    if ($image === '') {
        return '';
    }

    $image = preg_replace('/[?#].*$/', '', $image) ?? $image;
    $image = str_replace('\\', '/', $image);

    if (preg_match('#^(?:https?:)?//#i', $image)) {
        $error = 'Only local image files from img/ are allowed.';
        return '';
    }

    $filename = basename($image);
    if ($filename === '' || $filename === '.' || $filename === '..') {
        $error = 'Only image filenames from img/ are allowed.';
        return '';
    }

    if (!preg_match('/\.(?:jpe?g|png|gif|svg|webp)$/i', $filename)) {
        $error = 'Only image files from img/ are allowed.';
        return '';
    }

    if ($requireExistingFile) {
        if (!array_key_exists($filename, $existingFileCache)) {
            $existingFileCache[$filename] = is_file(getCanonicalImageDirectory() . '/' . $filename);
        }
        if (!$existingFileCache[$filename]) {
            $error = "Image file '$filename' was not found in img/.";
            return '';
        }
    }

    return $filename;
}

/**
 * Normalize image filename lists from JSON/admin input.
 */
function normalizeImageFilenameList($images, bool $requireExistingFiles = false, array &$invalidImages = []): array {
    $invalidImages = [];
    if (is_string($images)) {
        $images = preg_split('/[\r\n,]+/', $images) ?: [];
    }

    if (!is_array($images)) {
        return [];
    }

    $normalized = [];
    foreach ($images as $image) {
        $rawImage = trim((string)$image);
        if ($rawImage === '') {
            continue;
        }

        $error = null;
        $canonicalImage = normalizeImageFilename($rawImage, $requireExistingFiles, $error);
        if ($canonicalImage === '') {
            if ($error !== null) {
                $invalidImages[$rawImage] = $error;
            }
            continue;
        }

        $normalized[] = $canonicalImage;
    }

    return array_values(array_unique($normalized));
}

/**
 * Build a canonical absolute /img/... URL.
 */
function getCanonicalImageUrl(string $image, string $fallback = 'placeholder.svg'): string {
    $filename = normalizeImageFilename($image, true);
    if ($filename === '') {
        $filename = normalizeImageFilename($fallback, true) ?: 'placeholder.svg';
    }

    return '/img/' . rawurlencode($filename);
}

/**
 * Build product image list.
 */
function getProductImageList(array $product, ?array $accessoryData = null): array {
    $images = [];
    if ($accessoryData && !empty($accessoryData['images']) && is_array($accessoryData['images'])) {
        $images = $accessoryData['images'];
    } elseif (!empty($product['images']) && is_array($product['images'])) {
        $images = $product['images'];
    } elseif (!empty($product['image'])) {
        $images = [$product['image']];
    }

    $images = normalizeImageFilenameList($images, true);
    return $images;
}

/**
 * Build category image list.
 */
function getCategoryImageList(string $categorySlug, ?array $categoryData = null): array {
    $categories = $categoryData === null ? loadJSON('categories.json') : [];
    $categoryData = $categoryData ?? ($categories[$categorySlug] ?? []);

    if (!empty($categoryData['use_custom_image'])) {
        $customImages = normalizeImageFilenameList($categoryData['images'] ?? [], true);
        if (empty($customImages) && !empty($categoryData['image'])) {
            $customImages = normalizeImageFilenameList([$categoryData['image']], true);
        }
        if (!empty($customImages)) {
            return $customImages;
        }
    }

    $fallbackPath = getCategoryImage($categorySlug);
    $fallbackFilename = normalizeImageFilename($fallbackPath, true);
    if ($fallbackFilename !== '') {
        return [$fallbackFilename];
    }

    return ['placeholder.svg'];
}

/**
 * Load categories sorted by sort_order.
 */
function getSortedCategories(bool $activeOnly = true): array {
    $categories = loadJSON('categories.json');
    if ($activeOnly) {
        $categories = array_filter($categories, function ($category) {
            return !array_key_exists('active', $category) || !empty($category['active']);
        });
    }
    uasort($categories, function ($a, $b) {
        return (int)($a['sort_order'] ?? 999) <=> (int)($b['sort_order'] ?? 999);
    });
    return $categories;
}

/**
 * Build category URL.
 */
function getCategoryUrl(string $slug, array $category, string $lang): string {
    if (!empty($category['redirect'])) {
        return $category['redirect'] . '?lang=' . urlencode($lang);
    }
    return 'category.php?slug=' . urlencode($slug) . '&lang=' . urlencode($lang);
}

/**
 * Categories for storefront catalog page.
 */
function getCatalogCategories(): array {
    $categories = getSortedCategories();
    return array_filter($categories, function ($category) {
        return !array_key_exists('show_in_catalog', $category) || !empty($category['show_in_catalog']);
    });
}

/**
 * Categories for the catalog mega menu.
 */
function getNavigationCategories(): array {
    $categories = getSortedCategories();
    return array_filter($categories, function ($category) {
        return !array_key_exists('show_in_navigation', $category) || !empty($category['show_in_navigation']);
    });
}

/**
 * Categories for footer catalog section.
 */
function getFooterCategories(): array {
    $categories = getSortedCategories();
    return array_filter($categories, function ($category) {
        return !array_key_exists('show_in_footer', $category) || !empty($category['show_in_footer']);
    });
}

/**
 * Load translatable entity content from ui_*.json files.
 */
function loadEntityTranslations(string $entityType, string $entityId, array $fields): array {
    $result = [];
    foreach (I18N::getSupportedLanguages() as $lang) {
        $path = __DIR__ . '/../data/i18n/ui_' . $lang . '.json';
        $result[$lang] = [];
        if (!file_exists($path)) {
            continue;
        }

        $data = json_decode((string)file_get_contents($path), true);
        if (!is_array($data)) {
            continue;
        }

        foreach ($fields as $field) {
            $result[$lang][$field] = (string)($data[$entityType][$entityId][$field] ?? '');
        }
    }
    return $result;
}

/**
 * Persist translatable entity content into ui_*.json files.
 */
function saveEntityTranslations(string $entityType, string $entityId, array $translations): bool {
    foreach (I18N::getSupportedLanguages() as $lang) {
        $path = __DIR__ . '/../data/i18n/ui_' . $lang . '.json';
        if (!file_exists($path)) {
            continue;
        }

        $data = json_decode((string)file_get_contents($path), true);
        if (!is_array($data)) {
            $data = [];
        }
        if (!isset($data[$entityType]) || !is_array($data[$entityType])) {
            $data[$entityType] = [];
        }
        if (!isset($data[$entityType][$entityId]) || !is_array($data[$entityType][$entityId])) {
            $data[$entityType][$entityId] = [];
        }

        foreach (($translations[$lang] ?? []) as $field => $value) {
            $value = trim((string)$value);
            if ($value === '') {
                unset($data[$entityType][$entityId][$field]);
            } else {
                $data[$entityType][$entityId][$field] = $value;
            }
        }

        if (empty($data[$entityType][$entityId])) {
            unset($data[$entityType][$entityId]);
        }

        if (file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
            return false;
        }
    }

    I18N::setLanguage(I18N::getLanguage());
    return true;
}

/**
 * Format price with currency
 */
function formatPrice(float $price, string $currency = 'CHF'): string {
    return $currency . ' ' . number_format($price, 2);
}

/**
 * Generate SKU (ALWAYS 3-part: PREFIX-VOLUME-FRAGRANCE)
 * 
 * CRITICAL RULE: SKU must ALWAYS have 3 parts separated by dashes.
 * For products without fragrance selector, fragrance code is 'NA'.
 * 
 * @param string $productId Product identifier
 * @param string $volume Volume/size (e.g., '125ml', 'standard')
 * @param string $fragrance Fragrance code (empty/null -> 'NA')
 * @return string 3-part SKU (e.g., 'DF-125-BEL' or 'ARO-STA-NA')
 */
function generateSKU(string $productId, string $volume, string $fragrance): string {
    // Map productId to 2-3 character prefix
    $prefixMap = [
        'diffuser_classic' => 'DF',
        'candle_classic' => 'CD',
        'home_spray' => 'HP',
        'car_clip' => 'CP',
        'textile_spray' => 'TP',
        'limited_new_york' => 'LE',
        'limited_abu_dhabi' => 'LE',
        'limited_palermo' => 'LE',
        'aroma_sashe' => 'ARO',
        'christ_toy' => 'CHR',
        'refill_125' => 'REF',
        'sticks' => 'STI'
    ];
    
    $prefix = $prefixMap[$productId] ?? strtoupper(substr($productId, 0, 3));
    
    // Process volume: remove 'ml' suffix, handle 'standard'
    $vol = str_replace('ml', '', $volume);
    if ($vol === 'standard' || empty($vol)) {
        $vol = 'STA';
    }
    // For complex volumes like "5 guggul + 5 louban", use first few chars
    if (strlen($vol) > 10) {
        $vol = strtoupper(substr(preg_replace('/[^0-9a-z]/i', '', $vol), 0, 3));
    }
    $vol = strtoupper($vol);
    
    // CRITICAL: Handle empty/null fragrance -> use 'NA'
    // Note: trim() converts null to empty string, empty() catches that
    // Also check for string 'none' and string 'null' (literal strings, not null type)
    $fragrance = trim($fragrance);
    if (empty($fragrance) || $fragrance === 'none' || $fragrance === 'null') {
        $frag = 'NA';
    } else {
        // Check for custom SKU suffix in fragrances.json (with static cache)
        static $fragrances = null;
        if ($fragrances === null) {
            $fragrances = loadJSON('fragrances.json');
        }
        
        if (isset($fragrances[$fragrance]['sku_suffix'])) {
            $frag = strtoupper($fragrances[$fragrance]['sku_suffix']);
        } else {
            // Default: first 3 characters uppercase
            $frag = strtoupper(substr($fragrance, 0, 3));
        }
    }
    
    // GUARANTEE 3-part format
    return $prefix . '-' . $vol . '-' . $frag;
}

/**
 * Load JSON file
 */
function loadJSON(string $filename): array {
    $path = __DIR__ . '/../data/' . $filename;
    if (file_exists($path)) {
        $content = file_get_contents($path);
        return json_decode($content, true) ?? [];
    }
    return [];
}

/**
 * Save JSON file with locking
 */
function saveJSON(string $filename, array $data): bool {
    $path = __DIR__ . '/../data/' . $filename;
    
    // ENHANCED LOGGING: Log save attempt details
    error_log("saveJSON: Attempting to save file: $filename (full path: $path)");
    
    // Check file permissions before attempting write
    if (file_exists($path)) {
        $perms = substr(sprintf('%o', fileperms($path)), -4);
        $isWritable = is_writable($path);
        error_log("saveJSON: File exists - Permissions: $perms, Writable: " . ($isWritable ? 'YES' : 'NO'));
    } else {
        $dirPerms = substr(sprintf('%o', fileperms(dirname($path))), -4);
        error_log("saveJSON: File does not exist - Directory permissions: $dirPerms");
    }
    
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
    if ($json === false) {
        error_log('saveJSON: ERROR - JSON encode error for ' . $filename . ': ' . json_last_error_msg());
        return false;
    }
    
    $jsonSize = strlen($json);
    error_log("saveJSON: JSON encoded successfully - Size: $jsonSize bytes");
    
    $fp = fopen($path, 'w');
    if (!$fp) {
        $error = error_get_last();
        error_log('saveJSON: ERROR - Failed to open file for writing: ' . $path);
        if ($error) {
            error_log('saveJSON: System error: ' . $error['message']);
        }
        return false;
    }
    
    error_log("saveJSON: File opened successfully, attempting to acquire lock...");
    
    if (flock($fp, LOCK_EX)) {
        error_log("saveJSON: Lock acquired successfully, writing data...");
        $bytesWritten = fwrite($fp, $json);
        error_log("saveJSON: Wrote $bytesWritten bytes to file");
        fflush($fp);
        error_log("saveJSON: Data flushed to disk");
        flock($fp, LOCK_UN);
        error_log("saveJSON: Lock released");
        fclose($fp);
        error_log("saveJSON: File closed - Save completed successfully for $filename");
        return true;
    }
    
    fclose($fp);
    error_log('saveJSON: ERROR - Failed to acquire file lock: ' . $path);
    return false;
}

/**
 * Build a normalized branch stock snapshot while preserving existing per-branch quantities.
 * Passing null causes the latest stock/branch data to be loaded from disk.
 */
function buildBranchStockCompatibilitySnapshot(?array $stock = null, ?array $branches = null, ?array $branchStock = null): array {
    $stock = $stock ?? loadJSON('stock.json');
    $branches = $branches ?? loadBranches();
    $branchStock = $branchStock ?? loadJSON('branch_stock.json');

    $snapshot = [];
    $branchIds = [];

    if (is_array($branchStock)) {
        $branchIds = array_keys($branchStock);
    }

    if (is_array($branches)) {
        foreach ($branches as $branchId => $branchData) {
            if (is_string($branchId)) {
                $branchIds[] = $branchId;
            } elseif (is_array($branchData) && isset($branchData['id'])) {
                $branchIds[] = (string)$branchData['id'];
            }
        }
    }

    $branchIds = array_values(array_unique(array_filter($branchIds, 'strlen')));

    foreach ($branchIds as $branchId) {
        $existingBranchData = $branchStock[$branchId] ?? [];
        $snapshot[$branchId] = is_array($existingBranchData) ? $existingBranchData : [];

        foreach ($snapshot[$branchId] as $sku => $stockEntry) {
            if (!is_array($stockEntry)) {
                $snapshot[$branchId][$sku] = ['quantity' => (int)$stockEntry];
                continue;
            }

            $snapshot[$branchId][$sku]['quantity'] = (int)($stockEntry['quantity'] ?? 0);
        }

        foreach ($stock as $sku => $stockData) {
            if (!isset($snapshot[$branchId][$sku])) {
                $snapshot[$branchId][$sku] = ['quantity' => 0];
            }
        }
    }

    return $snapshot;
}

/**
 * Persist normalized branch stock data while preserving branch-level quantities.
 */
function syncBranchStockCompatibilityFile(?array $stock = null, ?array $branches = null, ?array $branchStock = null): bool {
    return saveJSON('branch_stock.json', buildBranchStockCompatibilitySnapshot($stock, $branches, $branchStock));
}

/**
 * Calculate the total branch quantity for a SKU.
 */
function getBranchStockTotal(string $sku, ?array $branchStock = null): int {
    $branchStock = $branchStock ?? loadBranchStock();
    $total = 0;

    foreach ($branchStock as $branchItems) {
        $total += (int)($branchItems[$sku]['quantity'] ?? 0);
    }

    return $total;
}

/**
 * Get stock for SKU
 */
function getStock(string $sku): int {
    $stock = loadJSON('stock.json');
    return $stock[$sku]['quantity'] ?? 0;
}

/**
 * Update stock
 */
function updateStock(string $sku, int $quantity): bool {
    $stock = loadJSON('stock.json');
    if (isset($stock[$sku])) {
        $stock[$sku]['quantity'] = $quantity;
        $stock[$sku]['total_qty'] = $quantity;
        if (!saveJSON('stock.json', $stock)) {
            return false;
        }

        return syncBranchStockCompatibilityFile($stock);
    }
    return false;
}

/**
 * Decrease stock
 */
function decreaseStock(string $sku, int $amount = 1): bool {
    // ENHANCED LOGGING: Log incoming parameters
    error_log("=== decreaseStock START ===");
    error_log("decreaseStock: PARAMS - SKU: '$sku', Amount to decrease: $amount");
    
    $stock = loadJSON('stock.json');
    
    // Check if SKU exists in stock
    if (!isset($stock[$sku])) {
        error_log("decreaseStock: ERROR - SKU '$sku' not found in stock.json");
        error_log("=== decreaseStock END (FAILED - SKU not found) ===");
        return false;
    }
    
    // Check if sufficient quantity available
    if (!isset($stock[$sku]['quantity'])) {
        error_log("decreaseStock: ERROR - SKU '$sku' has no quantity field in stock.json - data integrity issue");
        error_log("=== decreaseStock END (FAILED - no quantity field) ===");
        return false;
    }
    
    $currentQty = $stock[$sku]['quantity'];
    error_log("decreaseStock: BEFORE - SKU '$sku' current quantity: $currentQty");
    
    if ($currentQty < $amount) {
        error_log("decreaseStock: ERROR - Insufficient stock for SKU '$sku' - Requested: $amount, Available: $currentQty");
        error_log("=== decreaseStock END (FAILED - insufficient stock) ===");
        return false;
    }
    
    // Decrease stock
    $stock[$sku]['quantity'] -= $amount;
    $newQty = $stock[$sku]['quantity'];
    error_log("decreaseStock: AFTER calculation - SKU '$sku' new quantity: $newQty");
    
    // Save and verify
    $saveResult = saveJSON('stock.json', $stock);
    error_log("decreaseStock: saveJSON() returned: " . ($saveResult ? 'TRUE (success)' : 'FALSE (failed)'));
    
    if (!$saveResult) {
        error_log("decreaseStock: ERROR - Failed to save stock.json after decreasing SKU '$sku'");
        error_log("=== decreaseStock END (FAILED - save error) ===");
        return false;
    }
    
    if (!syncBranchStockCompatibilityFile($stock)) {
        error_log("decreaseStock: ERROR - Failed to refresh compatibility branch_stock.json mirror");
        error_log("=== decreaseStock END (FAILED - compatibility sync error) ===");
        return false;
    }

    // Verify the save by reading back
    $verifyStock = loadJSON('stock.json');
    $verifiedQty = $verifyStock[$sku]['quantity'] ?? 'NOT_FOUND';
    error_log("decreaseStock: VERIFICATION - Re-read stock.json, SKU '$sku' quantity now: $verifiedQty");
    
    if ($verifiedQty !== $newQty) {
        error_log("decreaseStock: WARNING - Quantity mismatch after save! Expected: $newQty, Got: $verifiedQty");
    }
    
    error_log("=== decreaseStock END (SUCCESS) ===");
    return $saveResult;
}

/**
 * Load stock data
 */
function loadStock(): array {
    return loadJSON('stock.json');
}

/**
 * Get stock quantity for a SKU
 */
function getStockQuantity(string $sku): int {
    $stock = loadStock();
    return (int)($stock[$sku]['quantity'] ?? 0);
}

/**
 * Get product price from products.json variants
 */
function getProductPrice(string $productId, string $volume = 'standard', string $fragrance = ''): float {
    // Try products.json first
    $products = loadJSON('products.json');
    if (isset($products[$productId])) {
        $product = $products[$productId];
        $variants = getNormalizedProductVariants($product);
        $normalizedVolume = normalizeVariantVolume($volume);
        $normalizedFragrance = normalizeVariantFragrance($fragrance);
        if ($normalizedFragrance === '') {
            if (!empty($product['fragrance'])) {
                $normalizedFragrance = normalizeVariantFragrance((string)$product['fragrance']);
            } else {
                $productFragrances = getProductFragranceOptions($product, (string)($product['category'] ?? ''));
                if (count($productFragrances) === 1) {
                    $normalizedFragrance = normalizeVariantFragrance((string)$productFragrances[0]);
                }
            }
        }
        $volumeFallback = null;
        $firstVariantPrice = null;

        foreach ($variants as $variant) {
            $variantVolume = $variant['volume'];
            $variantFragrance = $variant['fragrance'];
            $variantPrice = (float)($variant['priceCHF'] ?? 0.0);

            if ($firstVariantPrice === null) {
                $firstVariantPrice = $variantPrice;
            }

            if ($variantVolume !== $normalizedVolume) {
                continue;
            }

            if ($variantFragrance !== '') {
                if ($variantFragrance === $normalizedFragrance) {
                    return $variantPrice;
                }
                continue;
            }

            $volumeFallback = $variantPrice;
        }

        if ($volumeFallback !== null) {
            return $volumeFallback;
        }

        if ($firstVariantPrice !== null && count($variants) === 1) {
            return $firstVariantPrice;
        }
    }
    
    // Try accessories.json if not found in products
    $accessories = loadJSON('accessories.json');
    if (isset($accessories[$productId])) {
        $accessory = $accessories[$productId];
        
        // Check if has volume-based pricing
        if (!empty($accessory['has_volume_selector']) && !empty($accessory['volume_prices'])) {
            $volumePrices = $accessory['volume_prices'];
            if (isset($volumePrices[$volume])) {
                return (float)$volumePrices[$volume];
            }
        }
        
        // Use standard price for accessories
        if ($volume === 'standard') {
            return (float)($accessory['priceCHF'] ?? 0.0);
        }
    }
    
    // No matching product/variant found - return 0
    // This prevents fallback to incorrect pricing
    return 0.0;
}

/**
 * Get variant price (alias for getProductPrice for clarity)
 * This function exists for semantic clarity in code that deals with variants
 * 
 * @param string $productId Product identifier
 * @param string $volume Volume variant (e.g., '125ml', 'standard')
 * @param string $fragrance Fragrance code (optional)
 * @return float Price in CHF
 */
function getVariantPrice(string $productId, string $volume, string $fragrance = 'none'): float {
    return getProductPrice($productId, $volume, $fragrance);
}

/**
 * Get default displayed price for a product (first variant)
 * Used for initial product card display before user selects options
 * 
 * @param string $productId Product identifier
 * @return float Default price in CHF
 */
function getDefaultDisplayedPrice(string $productId): float {
    $products = loadJSON('products.json');
    if (!isset($products[$productId])) {
        return 0.0;
    }
    $product = $products[$productId];
    $variants = $product['variants'] ?? [];
    
    // Return price of first variant
    if (!empty($variants) && isset($variants[0]['priceCHF'])) {
        return (float)($variants[0]['priceCHF']);
    }
    
    return 0.0;
}

/**
 * Get catalog version for cache busting
 * Returns timestamp from catalog_version.json or current time
 * 
 * @return int Version timestamp
 */
function getCatalogVersion(): int {
    $versionFile = __DIR__ . '/../data/catalog_version.json';
    
    if (file_exists($versionFile)) {
        $data = json_decode(file_get_contents($versionFile), true);
        if ($data && isset($data['version'])) {
            return (int)$data['version'];
        }
    }
    
    // Fallback to current timestamp if version file doesn't exist
    return time();
}

/**
 * Update catalog version after products.json changes
 * Increments version to bust caches
 * 
 * @return bool Success status
 */
function updateCatalogVersion(): bool {
    $versionFile = __DIR__ . '/../data/catalog_version.json';
    $currentVersion = 0;
    if (file_exists($versionFile)) {
        $currentData = json_decode((string)file_get_contents($versionFile), true);
        $currentVersion = (int)($currentData['version'] ?? 0);
    }
    $version = max(time(), $currentVersion + 1);
    $data = [
        'version' => $version,
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    return file_put_contents($versionFile, $json) !== false;
}

/**
 * Get cart from session
 */
function getCart(): array {
    return $_SESSION['cart'] ?? [];
}

/**
 * Add to cart
 */
function addToCart(array $item): void {
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    $sku = $item['sku'] ?? '';
    $found = false;
    
    foreach ($_SESSION['cart'] as &$cartItem) {
        if ($cartItem['sku'] === $sku) {
            $cartItem['quantity'] += $item['quantity'] ?? 1;
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        $item['quantity'] = $item['quantity'] ?? 1;
        $_SESSION['cart'][] = $item;
    }
}

/**
 * Update cart item quantity
 */
function updateCartQuantity(string $sku, int $quantity): void {
    if (!isset($_SESSION['cart'])) return;
    
    foreach ($_SESSION['cart'] as $key => &$item) {
        if ($item['sku'] === $sku) {
            if ($quantity <= 0) {
                unset($_SESSION['cart'][$key]);
                $_SESSION['cart'] = array_values($_SESSION['cart']);
            } else {
                $item['quantity'] = $quantity;
            }
            break;
        }
    }
}

/**
 * Remove from cart
 */
function removeFromCart(string $sku): void {
    if (!isset($_SESSION['cart'])) return;
    
    foreach ($_SESSION['cart'] as $key => $item) {
        if ($item['sku'] === $sku) {
            unset($_SESSION['cart'][$key]);
            $_SESSION['cart'] = array_values($_SESSION['cart']);
            break;
        }
    }
}

/**
 * Clear cart
 */
function clearCart(): void {
    $_SESSION['cart'] = [];
}

/**
 * Get cart total
 */
function getCartTotal(): float {
    $total = 0;
    foreach (getCart() as $item) {
        $total += ($item['price'] ?? 0) * ($item['quantity'] ?? 1);
    }
    return $total;
}

/**
 * Get cart item count
 */
function getCartCount(): int {
    $count = 0;
    foreach (getCart() as $item) {
        $count += $item['quantity'] ?? 1;
    }
    return $count;
}

/**
 * Sanitize input
 */
function sanitize(string $input): string {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email
 */
function isValidEmail(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Generate order ID
 */
function generateOrderId(): string {
    return 'ORD-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
}

/**
 * Get fragrance image path - uses /img/ folder
 */
function getFragranceImage(string $fragranceCode): string {
    static $fragrances = null;
    if ($fragrances === null) {
        $fragrances = loadJSON('fragrances.json');
    }

    $storedFilename = normalizeImageFilename((string)($fragrances[$fragranceCode]['image'] ?? ''), true);
    if ($storedFilename !== '') {
        return getCanonicalImageUrl($storedFilename);
    }

    $imageMap = [
        'cherry_blossom' => 'Cherry-Blossom.jpg',
        'bellini' => 'Bellini.jpg',
        'eden' => 'Eden.jpg',
        'rosso' => 'Rosso.jpg',
        'salted_caramel' => 'Salted caramel.jpg',
        'santal' => 'Santal 2.jpg',
        'lime_basil' => 'Lime Basil.jpg',
        'bamboo' => 'Bamboo.jpg',
        'tobacco_vanilla' => 'Tob Van.jpg',
        'salty_water' => 'Salty Water.jpg',
        'christmas_tree' => 'Christmas Tree.jpg',
        'fleur' => 'Fleur.jpg',
        'blanc' => 'Blanc.jpg',
        'green_mango' => 'Green Mango 2.jpg',
        'carolina' => 'Carolina-2.jpg',
        'sugar' => 'Sugar.jpg',
        'dubai' => 'Dubai.jpg',
        'africa' => 'Africa.jpg',
        'dune' => 'Dune.jpg',
        'valencia' => 'Valencia.jpg',
        'etna' => 'Etna.jpg',
        'new_york' => 'New-York.jpg',
        'abu_dhabi' => 'abu-dhabi.jpg',
        'palermo' => 'Palermo.jpg'
    ];
    
    $filename = $imageMap[$fragranceCode] ?? '';
    if ($filename !== '') {
        return getCanonicalImageUrl($filename);
    }
    return getCanonicalImageUrl('placeholder.svg');
}

/**
 * Get fragrance image path with file existence check
 * Used for data-image attributes in select options
 */
function getFragranceImagePath(string $fragranceCode): string {
    return getFragranceImage($fragranceCode);
}

/**
 * Get product image path - uses /img/ folder
 */
function getProductImagePath(string $productId): string {
    // Products don't have specific images, use placeholder
    // or fall back to fragrance images based on product context
    return '/img/placeholder.svg';
}

/**
 * Get category image path - uses /img/ folder
 */
function getCategoryImage(string $category): string {
    $categories = loadJSON('categories.json');
    $categoryData = $categories[$category] ?? [];
    if (!empty($categoryData['use_custom_image'])) {
        $customImages = normalizeImageFilenameList($categoryData['images'] ?? [], true);
        if (empty($customImages) && !empty($categoryData['image'])) {
            $customImages = normalizeImageFilenameList([$categoryData['image']], true);
        }
        if (!empty($customImages)) {
            return getCanonicalImageUrl($customImages[0]);
        }
    }

    $imageMap = [
        'aroma_diffusers' => 'Aroma diffusers_category.jpg',
        'scented_candles' => 'Candels category.jpg',
        'home_perfume' => 'home pefume.jpg',
        'car_perfume' => 'AutoParf.jpg',
        'textile_perfume' => 'Textile-hero.jpg',
        'limited_edition' => '3 velas.jpg',
        'gift_sets' => 'ETSY-foto.jpg',
        'accessories' => 'ETSY-foto.jpg',
        'aroma_marketing' => 'ETSY-foto.jpg'
    ];
    
    $filename = $imageMap[$category] ?? '';
    if ($filename !== '') {
        return getCanonicalImageUrl($filename);
    }
    $storedFilename = normalizeImageFilename((string)($categoryData['image'] ?? ''), true);
    if ($storedFilename !== '') {
        return getCanonicalImageUrl($storedFilename);
    }
    return getCanonicalImageUrl('placeholder.svg');
}

/**
 * Get category image path helper (alias for getCategoryImage)
 */
function getCategoryImagePath(string $categorySlug): string {
    return getCategoryImage($categorySlug);
}

/**
 * Get absolute asset URL
 * 
 * Generates consistent absolute URLs for assets regardless of current route.
 * Always returns paths starting with '/' for use in href/src attributes.
 * 
 * @param string $path Relative path to asset (e.g., 'css/style.css', 'img/logo.png')
 * @return string Absolute path (e.g., '/assets/css/style.css', '/img/logo.png')
 */
function asset_url(string $path): string {
    // Normalize path: remove leading slash if present
    $path = ltrim($path, '/');
    
    // Determine directory based on path
    if (strpos($path, 'img/') === 0) {
        // Image paths go to /img/
        return '/' . $path;
    } elseif (strpos($path, 'assets/') === 0) {
        // Asset paths already include assets/ prefix
        return '/' . $path;
    } elseif (preg_match('/\.(css|js|woff|woff2|ttf|eot)$/i', $path)) {
        // CSS/JS/fonts go to /assets/
        return '/assets/' . $path;
    } else {
        // Default: assume it's in root or img/
        return '/' . $path;
    }
}

/**
 * Check if user is logged in as admin
 */
function isAdminLoggedIn(): bool {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

/**
 * Check if user has admin role
 * FIX: TASK 3 - Updated to use 'full_access' role
 */
function isAdmin(): bool {
    return isAdminLoggedIn() && in_array($_SESSION['admin_role'] ?? '', ['admin', 'full_access']);
}

/**
 * Check if user has manager role
 * FIX: TASK 3 - Updated role check
 */
function isManager(): bool {
    return isAdminLoggedIn() && in_array($_SESSION['admin_role'] ?? '', ['admin', 'manager', 'full_access', 'products_prices']);
}

/**
 * FIX: TASK 3 - Get current admin user role
 */
function getAdminRole(): string {
    return $_SESSION['admin_role'] ?? '';
}

/**
 * FIX: TASK 3 - Check if user has specific permission
 * Role definitions:
 * - full_access: Full access to everything
 * - view_only: Read-only access to everything
 * - products_prices: View all + edit products/prices only
 * - orders_only: View orders only
 */
function hasPermission(string $permission): bool {
    if (!isAdminLoggedIn()) {
        return false;
    }
    
    $role = getAdminRole();
    
    // Backward compatibility: 'admin' has full access
    if ($role === 'admin') {
        $role = 'full_access';
    }
    
    // Full access can do everything
    if ($role === 'full_access') {
        return true;
    }
    
    // Permission mappings by role
    $rolePermissions = [
        'view_only' => [
            'view_dashboard', 'view_products', 'view_accessories', 'view_fragrances',
            'view_categories', 'view_stock', 'view_orders', 'view_shipping',
            'view_branches', 'view_customers', 'view_notifications', 'view_email'
        ],
        'products_prices' => [
            'view_dashboard', 'view_products', 'view_accessories', 'view_fragrances',
            'view_categories', 'view_stock', 'view_orders', 'view_shipping',
            'view_branches', 'view_customers', 'view_notifications', 'view_email',
            'edit_products', 'edit_accessories', 'edit_fragrances', 
            'edit_stock', 'edit_categories'
        ],
        'orders_only' => [
            'view_orders', 'edit_orders'
        ]
    ];
    
    $permissions = $rolePermissions[$role] ?? [];
    return in_array($permission, $permissions);
}

/**
 * FIX: TASK 3 - Require specific permission or redirect
 */
function requirePermission(string $permission, string $redirectUrl = 'index.php'): void {
    if (!hasPermission($permission)) {
        header('Location: ' . $redirectUrl . '?error=access_denied');
        exit;
    }
}

/**
 * FIX: TASK 3 - Check if current user can manage admin users
 */
function canManageUsers(): bool {
    return hasPermission('manage_users') || getAdminRole() === 'full_access';
}

/**
 * Redirect with language parameter
 */
function redirectWithLang(string $url): void {
    $lang = I18N::getLanguage();
    $separator = strpos($url, '?') !== false ? '&' : '?';
    header('Location: ' . $url . $separator . 'lang=' . $lang);
    exit;
}

// ========================================
// CUSTOMER ACCOUNT FUNCTIONS
// ========================================

/**
 * Get customers from storage
 */
function getCustomers(): array {
    return loadJSON('customers.json');
}

/**
 * Save customers to storage
 */
function saveCustomers(array $data): bool {
    return saveJSON('customers.json', $data);
}

/**
 * Get count of registered customers
 */
function getRegisteredCustomersCount(): int {
    $customers = getCustomers();
    return is_array($customers) ? count($customers) : 0;
}

/**
 * Get current logged-in customer data
 */
function getCurrentCustomer(): ?array {
    return $_SESSION['customer'] ?? null;
}

/**
 * Get current logged-in customer ID
 */
function getCurrentCustomerId(): ?string {
    return $_SESSION['customer']['id'] ?? null;
}

/**
 * Check if customer is logged in
 */
function isCustomerLoggedIn(): bool {
    return !empty($_SESSION['customer']);
}

/**
 * Get customer initials for avatar display
 */
function getCustomerInitials(array $customer): string {
    $firstName = $customer['first_name'] ?? '';
    $lastName = $customer['last_name'] ?? '';
    $initials = '';
    
    if ($firstName !== '') {
        $initials .= mb_substr($firstName, 0, 1);
    }
    if ($lastName !== '') {
        $initials .= mb_substr($lastName, 0, 1);
    }
    
    // Fallback to email first letter if no name
    if ($initials === '') {
        $email = $customer['email'] ?? '';
        if ($email !== '') {
            $initials = mb_substr($email, 0, 1);
        } else {
            $initials = 'ME';
        }
    }
    
    return mb_strtoupper($initials);
}

// ========================================
// FAVORITES FUNCTIONS
// ========================================

/**
 * Load favorites from storage
 */
function loadFavorites(): array {
    return loadJSON('favorites.json');
}

/**
 * Save favorites to storage
 */
function saveFavorites(array $data): bool {
    return saveJSON('favorites.json', $data);
}

/**
 * Get customer's favorite products
 */
function getCustomerFavorites(string $customerId): array {
    $all = loadFavorites();
    return $all[$customerId] ?? [];
}

/**
 * Toggle favorite status for a product
 */
function toggleFavorite(string $customerId, string $productId): array {
    $all = loadFavorites();
    $current = $all[$customerId] ?? [];
    
    if (in_array($productId, $current, true)) {
        // Remove from favorites
        $current = array_values(array_diff($current, [$productId]));
    } else {
        // Add to favorites
        $current[] = $productId;
    }
    
    $all[$customerId] = $current;
    saveFavorites($all);
    
    return $current;
}

// ========================================
// ORDERS FUNCTIONS
// ========================================

/**
 * Load orders from storage
 */
function loadOrders(): array {
    return loadJSON('orders.json');
}

/**
 * Save orders to storage
 */
function saveOrders(array $data): bool {
    return saveJSON('orders.json', $data);
}

/**
 * Get customer's orders
 */
function getCustomerOrders(string $customerId): array {
    $all = loadOrders();
    $customerOrders = [];
    
    foreach ($all as $orderId => $order) {
        if (($order['customer_id'] ?? '') === $customerId) {
            $customerOrders[$orderId] = $order;
        }
    }
    
    // Sort by created_at descending (newest first)
    uasort($customerOrders, function($a, $b) {
        return strtotime($b['created_at'] ?? '0') - strtotime($a['created_at'] ?? '0');
    });
    
    return $customerOrders;
}

/**
 * Get specific order by ID
 */
function getOrder(string $orderId): ?array {
    $orders = loadOrders();
    return $orders[$orderId] ?? null;
}

/**
 * Update customer profile
 * @param string $email Customer email
 * @param array $data Profile data to update
 * @return true|string Returns true on success, or error message string on failure
 */
function updateCustomerProfile(string $email, array $data) {
    $customers = getCustomers();
    
    if (!is_array($customers)) {
        error_log('updateCustomerProfile: getCustomers() did not return an array');
        return 'Failed to load customer data from storage. Please contact support.';
    }
    
    if (!isset($customers[$email])) {
        error_log('updateCustomerProfile: Customer not found with email: ' . $email);
        return 'Customer account not found in the system. Please log out and log in again.';
    }
    
    // Update allowed fields only
    $allowedFields = ['salutation', 'first_name', 'last_name', 'phone', 'shipping_address', 'billing_address'];
    
    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $customers[$email][$field] = $data[$field];
        }
    }
    
    $customers[$email]['updated_at'] = date('c');
    
    // Update session data as well
    if (isset($_SESSION['customer']) && $_SESSION['customer']['email'] === $email) {
        $_SESSION['customer'] = $customers[$email];
    }
    
    $result = saveCustomers($customers);
    if (!$result) {
        error_log('updateCustomerProfile: saveCustomers() returned false for email: ' . $email);
        return 'Failed to save profile data to storage. The data file may be locked or permissions may be incorrect. Please try again in a moment.';
    }
    
    return true;
}

/**
 * Load favorite products for display
 */
function loadFavoriteProducts(string $customerId): array {
    $favoriteIds = getCustomerFavorites($customerId);
    $favoriteProducts = [];
    
    if (empty($favoriteIds)) {
        return $favoriteProducts;
    }
    
    $products = loadJSON('products.json');
    $accessories = loadJSON('accessories.json');
    
    foreach ($favoriteIds as $productId) {
        if (isset($products[$productId])) {
            $product = $products[$productId];
            $product['id'] = $productId;
            
            // Get product name and image
            $product['display_name'] = I18N::t('product.' . $productId . '.name', $product['name_key'] ?? $productId);
            
            // Get image URL - all images are in img/ folder
            $isAccessory = ($product['category'] ?? '') === 'accessories';
            if ($isAccessory && isset($accessories[$productId]) && !empty($accessories[$productId]['images'])) {
                // Accessory with multiple images - use first image
                $productImages = getProductImageList($product, $accessories[$productId]);
                $product['image_url'] = !empty($productImages)
                    ? getCanonicalImageUrl($productImages[0])
                    : getCanonicalImageUrl('placeholder.svg');
            } elseif (!empty($product['image'])) {
                // Regular product - use image from products.json
                $productImages = getProductImageList($product);
                $product['image_url'] = !empty($productImages)
                    ? getCanonicalImageUrl($productImages[0])
                    : getCanonicalImageUrl('placeholder.svg');
            } else {
                // Fallback to fragrance image when available, otherwise placeholder
                $fragrances = getProductFragranceOptions($product, (string)($product['category'] ?? ''), $accessories[$productId] ?? null);
                $product['image_url'] = !empty($fragrances[0])
                    ? getFragranceImage($fragrances[0])
                    : getCanonicalImageUrl('placeholder.svg');
            }
            
            $favoriteProducts[] = $product;
        }
    }
    
    return $favoriteProducts;
}

/**
 * Get list of European countries for address forms
 */
function getEuropeanCountries(): array {
    return [
        'Austria', 'Belgium', 'Bulgaria', 'Croatia', 'Cyprus', 'Czech Republic',
        'Denmark', 'Estonia', 'Finland', 'France', 'Germany', 'Greece',
        'Hungary', 'Ireland', 'Italy', 'Latvia', 'Lithuania', 'Luxembourg',
        'Malta', 'Netherlands', 'Poland', 'Portugal', 'Romania', 'Slovakia',
        'Slovenia', 'Spain', 'Sweden', 'United Kingdom', 'Switzerland',
        'Norway', 'Iceland', 'Liechtenstein', 'Monaco', 'Andorra', 'San Marino',
        'Vatican City', 'Albania', 'Bosnia and Herzegovina', 'Kosovo', 'Macedonia',
        'Moldova', 'Montenegro', 'Serbia', 'Ukraine', 'Belarus'
    ];
}

/**
 * Validate order totals integrity and log discrepancies
 * 
 * @param array $order Order data
 * @return bool True if totals are valid, false otherwise
 */
function validateOrderIntegrity(array $order): bool {
    $orderId = $order['id'] ?? 'UNKNOWN';
    $subtotal = (float)($order['subtotal'] ?? 0);
    $shippingCost = (float)($order['shipping_cost'] ?? 0);
    $total = (float)($order['total'] ?? 0);
    
    // Calculate expected total with proper rounding
    $expectedTotal = round($subtotal + $shippingCost, 2);
    $actualTotal = round($total, 2);
    
    // Allow for tiny floating point differences
    $difference = abs($expectedTotal - $actualTotal);
    $isValid = $difference < 0.01;
    
    if (!$isValid) {
        $logDir = __DIR__ . '/../logs';
        $logFile = $logDir . '/orders_integrity.log';
        
        // Ensure logs directory exists
        if (!file_exists($logDir)) {
            $dirCreated = mkdir($logDir, 0755, true);
            if (!$dirCreated) {
                error_log("ORDER INTEGRITY: Failed to create logs directory: $logDir");
                // Continue execution - log to error_log only
            }
        }
        
        // Prepare log entry
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = sprintf(
            "[%s] ORDER INTEGRITY WARNING: Order %s - Subtotal: %.2f + Shipping: %.2f = Expected: %.2f, Actual: %.2f, Difference: %.2f\n",
            $timestamp,
            $orderId,
            $subtotal,
            $shippingCost,
            $expectedTotal,
            $actualTotal,
            $difference
        );
        
        // Append to log file (do not block if fails)
        if (file_exists($logDir) && is_writable($logDir)) {
            $writeResult = file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
            if ($writeResult === false) {
                error_log("ORDER INTEGRITY: Failed to write to log file: $logFile");
            }
        }
        
        // Always log to PHP error log as fallback
        error_log("ORDER INTEGRITY: Order $orderId has invalid totals - Expected: $expectedTotal, Actual: $actualTotal");
    }
    
    return $isValid;
}

// ========================================
// SHIPPING RULES FUNCTIONS
// ========================================

/**
 * Load shipping rules from storage
 */
function loadShippingRules(): array {
    return loadJSON('shipping_rules.json');
}

/**
 * Save shipping rules to storage
 */
function saveShippingRules(array $data): bool {
    return saveJSON('shipping_rules.json', $data);
}

/**
 * Calculate shipping cost based on order total
 */
function calculateShippingForTotal(float $orderTotal): float {
    $rules = loadShippingRules();
    // Sort rules by minTotal to ensure proper precedence
    usort($rules, function($a, $b) {
        return ($a['minTotal'] ?? 0) <=> ($b['minTotal'] ?? 0);
    });
    
    foreach ($rules as $rule) {
        $min = (float)($rule['minTotal'] ?? 0);
        $max = (float)($rule['maxTotal'] ?? PHP_FLOAT_MAX);
        // Use <= for maxTotal to handle boundary values properly
        if ($orderTotal >= $min && $orderTotal <= $max) {
            return (float)($rule['shippingCost'] ?? 0);
        }
    }
    return 0.0;
}

// ========================================
// BRANCHES FUNCTIONS
// ========================================

/**
 * Load branches from storage
 */
function loadBranches(): array {
    return loadJSON('branches.json');
}

/**
 * Save branches to storage
 */
function saveBranches(array $data): bool {
    return saveJSON('branches.json', $data);
}

/**
 * Get active branches only
 */
function getActiveBranches(): array {
    $branches = loadBranches();
    return array_filter($branches, function($branch) {
        return !empty($branch['active']);
    });
}

/**
 * Load branch stock from storage
 */
function loadBranchStock(): array {
    return buildBranchStockCompatibilitySnapshot();
}

/**
 * Save normalized branch stock data while preserving branch-level quantities.
 */
function saveBranchStock(array $data = []): bool {
    $stock = loadJSON('stock.json');
    $branches = loadBranches();
    $branchStock = !empty($data) ? $data : loadJSON('branch_stock.json');

    return syncBranchStockCompatibilityFile($stock, $branches, $branchStock);
}

/**
 * Get stock quantity for a SKU in a specific branch
 */
function getBranchStockQuantity(string $branchId, string $sku): int {
    $branchStock = loadBranchStock();
    if (!isset($branchStock[$branchId])) {
        return 0;
    }

    return (int)($branchStock[$branchId][$sku]['quantity'] ?? 0);
}

/**
 * Decrease stock for a specific branch
 */
function decreaseBranchStock(string $branchId, string $sku, int $amount = 1): bool {
    // ENHANCED LOGGING: Log incoming parameters
    error_log("=== decreaseBranchStock START ===");
    error_log("decreaseBranchStock: PARAMS - Branch: '$branchId', SKU: '$sku', Amount to decrease: $amount");
    
    $branches = getAllBranches();

    // Check if branch exists
    if (!isset($branches[$branchId])) {
        error_log("decreaseBranchStock: ERROR - Branch '$branchId' not found in branches.json");
        error_log("=== decreaseBranchStock END (FAILED - branch not found) ===");
        return false;
    }

    $branchStock = loadBranchStock();
    $stock = loadJSON('stock.json');

    if (!isset($stock[$sku])) {
        error_log("decreaseBranchStock: ERROR - SKU '$sku' not found in stock.json");
        error_log("=== decreaseBranchStock END (FAILED - SKU not found) ===");
        return false;
    }

    $currentQty = (int)($branchStock[$branchId][$sku]['quantity'] ?? 0);
    error_log("decreaseBranchStock: BEFORE - Branch '$branchId', SKU '$sku' current quantity: $currentQty");
    
    if ($currentQty < $amount) {
        error_log("decreaseBranchStock: ERROR - Insufficient branch stock for SKU '$sku' at branch '$branchId' - Requested: $amount, Available: $currentQty");
        error_log("=== decreaseBranchStock END (FAILED - insufficient stock) ===");
        return false;
    }

    $originalBranchStock = $branchStock;
    $originalStock = $stock;

    $branchStock[$branchId][$sku]['quantity'] = $currentQty - $amount;
    $newTotal = getBranchStockTotal($sku, $branchStock);
    $stock[$sku]['quantity'] = $newTotal;
    $stock[$sku]['total_qty'] = $newTotal;

    if (!saveBranchStock($branchStock)) {
        error_log("decreaseBranchStock: ERROR - Failed to save branch_stock.json");
        error_log("=== decreaseBranchStock END (FAILED - branch_stock save failed) ===");
        return false;
    }

    if (!saveJSON('stock.json', $stock)) {
        error_log("decreaseBranchStock: ERROR - Failed to save stock.json, attempting rollback");
        if (!saveBranchStock($originalBranchStock)) {
            error_log("decreaseBranchStock: ROLLBACK FAILED - Could not restore branch_stock.json");
        }
        if (!saveJSON('stock.json', $originalStock)) {
            error_log("decreaseBranchStock: ROLLBACK FAILED - Could not restore stock.json");
        }
        error_log("=== decreaseBranchStock END (FAILED - stock.json update failed) ===");
        return false;
    }

    $verifiedQty = getBranchStockQuantity($branchId, $sku);
    error_log("decreaseBranchStock: VERIFICATION - Branch quantity now: $verifiedQty");
    error_log("decreaseBranchStock: VERIFICATION - Stock total now: $newTotal");
    error_log("=== decreaseBranchStock END (SUCCESS) ===");
    return true;
}

/**
 * Decrease stock for every sellable SKU contained in an order.
 *
 * @return array{success:bool,errors:array,applied:array,is_pickup:bool,branch_id:string}
 */
function decreaseOrderStock(array $order): array {
    $cart = is_array($order['items'] ?? null) ? $order['items'] : [];
    $isPickup = !empty($order['pickup_in_branch']);
    $pickupBranchId = trim((string)($order['pickup_branch_id'] ?? ''));
    $errors = [];
    $applied = [];

    if ($isPickup && $pickupBranchId === '') {
        $errors[] = 'Pickup order is missing pickup_branch_id.';
        return [
            'success' => false,
            'errors' => $errors,
            'applied' => $applied,
            'is_pickup' => $isPickup,
            'branch_id' => $pickupBranchId
        ];
    }

    foreach ($cart as $item) {
        $category = $item['category'] ?? '';
        $skuMap = [];

        if ($category === 'gift_sets') {
            $giftSetItems = $item['meta']['gift_set_items'] ?? $item['items'] ?? [];
            if (empty($giftSetItems)) {
                $errors[] = 'Gift set item is missing gift_set_items metadata.';
                break;
            }
            $skuMap = expandGiftSetToSkuMap($giftSetItems);
        } else {
            $sku = trim((string)($item['sku'] ?? ''));
            $quantity = max(1, (int)($item['quantity'] ?? 1));
            if ($sku === '') {
                $errors[] = 'Order item is missing SKU.';
                break;
            }
            $skuMap[$sku] = $quantity;
        }

        foreach ($skuMap as $orderSku => $quantity) {
            $decreased = $isPickup
                ? decreaseBranchStock($pickupBranchId, $orderSku, $quantity)
                : decreaseStock($orderSku, $quantity);

            if (!$decreased) {
                $errors[] = ($isPickup ? "Failed to decrease branch stock for branch $pickupBranchId" : 'Failed to decrease stock')
                    . " for SKU $orderSku (quantity $quantity).";
                break 2;
            }

            $applied[] = [
                'sku' => $orderSku,
                'quantity' => $quantity,
                'branch_id' => $isPickup ? $pickupBranchId : ''
            ];
        }
    }

    return [
        'success' => empty($errors),
        'errors' => $errors,
        'applied' => $applied,
        'is_pickup' => $isPickup,
        'branch_id' => $pickupBranchId
    ];
}

/**
 * Check if all items in cart are available in a specific branch
 * FIX: TASK 1 - Added gift set support
 * FIX: TASK 2 UPDATE - Pass product names for customer-friendly error messages
 */
function checkBranchStockForCart(string $branchId, array $cart): array {
    $errors = [];
    foreach ($cart as $item) {
        $sku = $item['sku'] ?? '';
        $qty = $item['quantity'] ?? 1;
        $productName = $item['name'] ?? $sku;
        $category = $item['category'] ?? '';
        
        // FIX: TASK 1 - Special handling for gift sets
        if ($category === 'gift_sets') {
            // Get gift set items from meta or items field
            $giftSetItems = $item['meta']['gift_set_items'] ?? $item['items'] ?? [];
            
            if (!empty($giftSetItems)) {
                // Evaluate gift set stock for this branch
                $giftSetStock = evaluateGiftSetStockForBranch($branchId, $giftSetItems);
                
                if (!$giftSetStock['ok']) {
                    // FIX: TASK 2 UPDATE - Add detailed error with product name for each missing component
                    foreach ($giftSetStock['missing'] as $missingSku => $missingQty) {
                        $requiredQty = $giftSetStock['required'][$missingSku] ?? 0;
                        $availableQty = $giftSetStock['available'][$missingSku] ?? 0;
                        $componentName = $giftSetStock['product_names'][$missingSku] ?? $missingSku;
                        
                        $errors[] = [
                            'sku' => $missingSku,
                            'name' => $productName,
                            'component_name' => $componentName, // FIX: TASK 2 UPDATE - Add component product name
                            'requested' => $requiredQty,
                            'available' => $availableQty,
                            'is_gift_set_component' => true,
                            'component_sku' => $missingSku
                        ];
                    }
                }
            } else {
                // Gift set without items data
                $errors[] = [
                    'sku' => $sku,
                    'name' => $productName,
                    'requested' => $qty,
                    'available' => 0,
                    'is_gift_set_component' => false
                ];
            }
        } else {
            // Regular product
            // FIX: TASK 2 UPDATE - Build detailed product description for all products
            $available = getBranchStockQuantity($branchId, $sku);
            
            if ($available < $qty) {
                // Get stock data to build detailed description
                $stock = loadJSON('stock.json');
                $detailedProductName = buildProductDescription($sku, $stock[$sku] ?? null);
                
                $errors[] = [
                    'sku' => $sku,
                    'name' => $productName,
                    'detailed_name' => $detailedProductName, // FIX: TASK 2 UPDATE - Add detailed product name
                    'requested' => $qty,
                    'available' => $available,
                    'is_gift_set_component' => false
                ];
            }
        }
    }
    return $errors;
}

/**
 * Build full product description from SKU data
 * FIX: TASK 2 UPDATE - Helper function to create detailed product descriptions for error messages
 * 
 * @param string $sku Product SKU
 * @param array $stockData Stock data from stock.json (optional, will load if not provided)
 * @return string Full product description: "Product Name Volume, Fragrance"
 */
function buildProductDescription(string $sku, array $stockData = null): string {
    // Load stock data if not provided
    if ($stockData === null) {
        $stock = loadJSON('stock.json');
        $stockData = $stock[$sku] ?? [];
    }
    
    // Load sku_universe helper if available
    if (file_exists(__DIR__ . '/stock/sku_universe.php')) {
        require_once __DIR__ . '/stock/sku_universe.php';
    }
    
    // Load fragrances for name translation
    $fragrancesData = loadJSON('fragrances.json');
    
    // Get product ID from stock data
    $productId = $stockData['productId'] ?? '';
    $volume = $stockData['volume'] ?? '';
    $fragrance = $stockData['fragrance'] ?? '';
    
    // Get base product name
    $productName = '';
    if (!empty($productId)) {
        if (function_exists('getProductNameFromId')) {
            $productName = getProductNameFromId($productId);
        } else {
            $productName = ucfirst(str_replace('_', ' ', $productId));
        }
    } else {
        // Fallback to SKU if no product ID
        return $sku;
    }
    
    // Add volume to description
    $volumeDisplay = '';
    if (!empty($volume) && $volume !== 'standard') {
        $volumeDisplay = $volume;
    }
    
    // Add fragrance name to description
    $fragranceDisplay = '';
    if (!empty($fragrance) && $fragrance !== 'none' && $fragrance !== 'NA') {
        // Try to get translated fragrance name
        if (isset($fragrancesData[$fragrance]['name'])) {
            $fragranceDisplay = $fragrancesData[$fragrance]['name'];
        } else {
            // Fallback to formatted fragrance code
            $fragranceDisplay = ucfirst(str_replace('_', ' ', $fragrance));
        }
    }
    
    // Build full description: "Product Name Volume, Fragrance"
    $fullDescription = $productName;
    if (!empty($volumeDisplay)) {
        $fullDescription .= ' ' . $volumeDisplay;
    }
    if (!empty($fragranceDisplay)) {
        $fullDescription .= ', ' . $fragranceDisplay;
    }
    
    return $fullDescription;
}

/**
 * Evaluate gift set stock availability for a specific branch
 * FIX: TASK 1 - Branch-aware version of evaluateGiftSetStock
 * FIX: TASK 2 UPDATE - Added full product description (name + volume + fragrance) for customer clarity
 * 
 * @param string $branchId Branch ID to check stock for
 * @param array $giftSetItems Array of gift set items with category, volume, fragrance
 * @return array ['ok' => bool, 'required' => [sku => qty], 'available' => [sku => qty], 'missing' => [sku => qty], 'product_names' => [sku => full_description]]
 */
function evaluateGiftSetStockForBranch(string $branchId, array $giftSetItems): array {
    $required = [];
    $available = [];
    $missing = [];
    $productNames = []; // FIX: TASK 2 UPDATE - Store full product descriptions for each SKU
    
    // STOCK is the authoritative inventory source for both delivery and pickup.
    $stock = loadJSON('stock.json'); // For quantity and productId lookup
    
    // Load sku_universe helper if available
    if (file_exists(__DIR__ . '/stock/sku_universe.php')) {
        require_once __DIR__ . '/stock/sku_universe.php';
    }
    
    // Load fragrances for name translation
    $fragrancesData = loadJSON('fragrances.json');
    
    // Map category to productId for SKU generation
    $categoryToProductId = [
        'aroma_diffusers' => 'diffuser_classic',
        'scented_candles' => 'candle_classic',
        'home_perfume' => 'home_spray',
        'car_perfume' => 'car_clip',
        'textile_perfume' => 'textile_spray',
        'limited_edition' => 'limited_new_york', // default, should be determined by fragrance
        'accessories' => 'aroma_sashe' // default
    ];
    
    // Process each gift set item
    foreach ($giftSetItems as $item) {
        $category = $item['category'] ?? '';
        $volume = $item['volume'] ?? $item['variant'] ?? 'standard';
        $fragrance = $item['fragrance'] ?? 'none';
        $qty = (int)($item['qty'] ?? 1);
        
        if (empty($category)) {
            continue;
        }
        
        // Map category to productId
        $productId = $item['productId'] ?? ($categoryToProductId[$category] ?? '');
        
        // Special case for limited edition - productId depends on fragrance
        if ($category === 'limited_edition') {
            if ($fragrance === 'new_york') {
                $productId = 'limited_new_york';
            } elseif ($fragrance === 'abu_dhabi') {
                $productId = 'limited_abu_dhabi';
            } elseif ($fragrance === 'palermo') {
                $productId = 'limited_palermo';
            }
        }
        
        // Generate SKU
        $sku = generateSKU($productId, $volume, $fragrance);
        
        // FIX: TASK 2 UPDATE - Build full product description: "Product Name Volume, Fragrance"
        $productName = '';
        if (function_exists('getProductNameFromId')) {
            $productName = getProductNameFromId($productId);
        } else {
            // Fallback
            if (isset($stock[$sku]['productId'])) {
                $productName = ucfirst(str_replace('_', ' ', $stock[$sku]['productId']));
            } else {
                $productName = ucfirst(str_replace('_', ' ', $productId));
            }
        }
        
        // Add volume to description (clean up "ml" suffix if present)
        $volumeDisplay = $volume;
        if ($volume !== 'standard' && !empty($volume)) {
            // Keep volume as-is for display
            $volumeDisplay = $volume;
        } else {
            $volumeDisplay = ''; // Don't show "standard"
        }
        
        // Add fragrance name to description
        $fragranceDisplay = '';
        if ($fragrance !== 'none' && $fragrance !== 'NA' && !empty($fragrance)) {
            // Try to get translated fragrance name
            if (isset($fragrancesData[$fragrance]['name'])) {
                $fragranceDisplay = $fragrancesData[$fragrance]['name'];
            } else {
                // Fallback to formatted fragrance code
                $fragranceDisplay = ucfirst(str_replace('_', ' ', $fragrance));
            }
        }
        
        // Build full description: "Product Name Volume, Fragrance"
        $fullDescription = $productName;
        if (!empty($volumeDisplay)) {
            $fullDescription .= ' ' . $volumeDisplay;
        }
        if (!empty($fragranceDisplay)) {
            $fullDescription .= ', ' . $fragranceDisplay;
        }
        
        $productNames[$sku] = $fullDescription;
        
        // Sum required quantities per SKU
        if (!isset($required[$sku])) {
            $required[$sku] = 0;
        }
        $required[$sku] += $qty;
    }
    
    // Check available stock for each required SKU in this branch
    foreach ($required as $sku => $requiredQty) {
        $availableQty = (int)($stock[$sku]['quantity'] ?? 0);
        $available[$sku] = $availableQty;
        
        if ($availableQty < $requiredQty) {
            $missing[$sku] = $requiredQty - $availableQty;
        }
    }
    
    $ok = empty($missing);
    
    return [
        'ok' => $ok,
        'required' => $required,
        'available' => $available,
        'missing' => $missing,
        'product_names' => $productNames // FIX: TASK 2 UPDATE - Return product names
    ];
}

/**
 * Send email using configured settings
 * 
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $body Email body (HTML)
 * @param array $options Additional options (from_name, from_email, etc.)
 * @return bool Success status
 */
function sendEmail(string $to, string $subject, string $body, array $options = []): bool {
    // DEPRECATED: This function is kept for backwards compatibility
    // New code should use sendEmailViaSMTP from includes/email/mailer.php
    
    // Load new email system
    require_once __DIR__ . '/email/mailer.php';
    
    // Convert HTML body to text if not provided
    $text = $options['text'] ?? strip_tags($body);
    $replyTo = $options['reply_to'] ?? null;
    $eventType = $options['event_type'] ?? 'general';
    
    $result = sendEmailViaSMTP($to, $subject, $body, $text, $replyTo, $eventType);
    
    return $result['success'];
}

/**
 * Send order confirmation email to customer
 * 
 * @param array $order Order data
 * @return bool Success status
 */
function sendOrderConfirmationEmail(array $order): bool {
    require_once __DIR__ . '/email/mailer.php';
    require_once __DIR__ . '/email/templates.php';
    
    $settings = loadEmailSettings();
    
    if (empty($settings['enabled'])) {
        error_log("Order confirmation email not sent: email system disabled");
        return false;
    }
    
    $customerEmail = $order['customer']['email'] ?? '';
    if (empty($customerEmail)) {
        error_log("Order confirmation email not sent: no customer email");
        return false;
    }
    
    // Get language from order, fallback to 'en'
    $lang = $order['language'] ?? 'en';
    
    // Prepare template variables with language
    $vars = prepareOrderTemplateVars($order, $lang);
    
    // Build translated subject and body
    $subject = email_t('email.order.subject', $lang, ['order_id' => $order['id'] ?? 'N/A']);
    $html = buildLocalizedOrderCustomerHtml($vars, $lang);
    $text = buildLocalizedOrderCustomerText($vars, $lang);
    
    // Send email
    $replyTo = $settings['routing']['reply_to_email'] ?? null;
    $result = sendEmailViaSMTP(
        $customerEmail, 
        $subject, 
        $html, 
        $text,
        $replyTo,
        'order_customer',
        ['lang' => $lang]
    );
    
    if (!$result['success']) {
        error_log("Failed to send order confirmation email: " . $result['error']);
    }
    
    return $result['success'];
}

/**
 * Build localized order confirmation email HTML for customer
 * @param array $vars Template variables
 * @param string $lang Language code
 * @return string HTML email body
 */
function buildLocalizedOrderCustomerHtml(array $vars, string $lang): string {
    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 700px; margin: 0 auto; padding: 20px; }
        .header { background: #d4af37; color: white; padding: 30px; text-align: center; }
        .content { padding: 30px; background: #f9f9f9; }
        .info-box { background: white; padding: 20px; margin: 20px 0; }
        .footer { text-align: center; padding: 20px; color: #666; font-size: 0.9em; }
        .items-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .items-table th, .items-table td { padding: 10px; border-bottom: 1px solid #ddd; }
        .items-table th { background: #f5f5f5; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>' . email_t('email.order.title', $lang) . '</h1>
            <p>' . email_t('email.order.orderNumber', $lang) . ' #' . htmlspecialchars($vars['order_id']) . '</p>
        </div>
        <div class="content">
            <p>' . email_t('email.order.greeting', $lang, ['customer_name' => htmlspecialchars($vars['customer_name'])]) . '</p>
            <p>' . email_t('email.order.intro', $lang) . '</p>
            
            <div class="info-box">
                <h3>' . email_t('email.order.orderDetails', $lang) . '</h3>
                <p><strong>' . email_t('email.order.orderNumber', $lang) . ':</strong> ' . htmlspecialchars($vars['order_id']) . '</p>
                <p><strong>' . email_t('email.order.orderDate', $lang) . ':</strong> ' . htmlspecialchars($vars['order_date']) . '</p>
                <p><strong>' . email_t('email.order.paymentMethod', $lang) . ':</strong> ' . htmlspecialchars($vars['payment_method']) . '</p>
            </div>
            
            ' . $vars['items_table'] . '
            
            <div class="info-box">
                <p><strong>' . email_t('email.order.subtotal', $lang) . ':</strong> CHF ' . htmlspecialchars($vars['subtotal']) . '</p>
                <p><strong>' . email_t('email.order.shipping', $lang) . ':</strong> CHF ' . htmlspecialchars($vars['shipping']) . '</p>
                <h3 style="border-top: 2px solid #333; padding-top: 10px;">' . email_t('email.order.total', $lang) . ': CHF ' . htmlspecialchars($vars['total']) . '</h3>
            </div>
            
            ' . $vars['pickup_branch'] . '
        </div>
        <div class="footer">
            <p>' . email_t('email.order.footer.thanks', $lang) . '</p>
            <p>' . email_t('email.order.footer.questions', $lang) . '</p>
            <p style="font-size: 0.85em; color: #999; margin-top: 1rem;">' . email_t('email.footer.auto', $lang) . '</p>
        </div>
    </div>
</body>
</html>';
    
    return $html;
}

/**
 * Build localized order confirmation email plain text for customer
 * @param array $vars Template variables
 * @param string $lang Language code
 * @return string Plain text email body
 */
function buildLocalizedOrderCustomerText(array $vars, string $lang): string {
    $text = strtoupper(email_t('email.order.title', $lang)) . "\n";
    $text .= email_t('email.order.orderNumber', $lang) . ": #" . $vars['order_id'] . "\n\n";
    
    $text .= email_t('email.order.greeting', $lang, ['customer_name' => $vars['customer_name']]) . "\n\n";
    $text .= email_t('email.order.intro', $lang) . "\n\n";
    
    $text .= email_t('email.order.orderDetails', $lang) . ":\n";
    $text .= "- " . email_t('email.order.orderNumber', $lang) . ": " . $vars['order_id'] . "\n";
    $text .= "- " . email_t('email.order.orderDate', $lang) . ": " . $vars['order_date'] . "\n";
    $text .= "- " . email_t('email.order.paymentMethod', $lang) . ": " . $vars['payment_method'] . "\n\n";
    
    $text .= $vars['items_list'] . "\n\n";
    
    $text .= email_t('email.order.subtotal', $lang) . ": CHF " . $vars['subtotal'] . "\n";
    $text .= email_t('email.order.shipping', $lang) . ": CHF " . $vars['shipping'] . "\n";
    $text .= email_t('email.order.total', $lang) . ": CHF " . $vars['total'] . "\n\n";
    
    $text .= $vars['shipping_address_block'] . "\n\n";
    
    $text .= email_t('email.order.footer.questions', $lang) . "\n\n";
    $text .= "---\n";
    $text .= email_t('email.footer.auto', $lang) . "\n";
    $text .= "NicheHome.ch";
    
    return $text;
}

/**
 * Prepare template variables for order emails
 * 
 * @param array $order Order data
 * @param string $lang Language code for translations (default: 'en')
 * @return array Template variables
 */
function prepareOrderTemplateVars(array $order, string $lang = 'en'): array {
    require_once __DIR__ . '/email/templates.php';
    
    $orderId = $order['id'] ?? 'N/A';
    $orderDate = $order['date'] ?? date('Y-m-d H:i:s');
    $customerName = trim(($order['customer']['first_name'] ?? '') . ' ' . ($order['customer']['last_name'] ?? ''));
    $customerEmail = $order['customer']['email'] ?? '';
    $customerPhone = $order['customer']['phone'] ?? '';
    $subtotal = number_format($order['subtotal'] ?? 0, 2);
    $shipping = number_format($order['shipping_cost'] ?? 0, 2);
    $total = number_format($order['total'] ?? 0, 2);
    $paymentMethod = ucfirst($order['payment_method'] ?? 'Unknown');
    
    // Map payment method to display name using translations
    $paymentMethodLower = strtolower($order['payment_method'] ?? '');
    if ($paymentMethodLower === 'twint') {
        $paymentMethodDisplay = email_t('email.payment.twint', $lang);
    } elseif ($paymentMethodLower === 'card') {
        $paymentMethodDisplay = email_t('email.payment.card', $lang);
    } elseif ($paymentMethodLower === 'cash') {
        $paymentMethodDisplay = email_t('email.payment.cash', $lang);
    } elseif ($paymentMethodLower === 'paypal') {
        $paymentMethodDisplay = email_t('email.payment.paypal', $lang);
    } else {
        $paymentMethodDisplay = $paymentMethod;
    }
    
    // Determine order status
    $orderStatus = $order['status'] ?? 'pending';
    // Translate order status
    $statusKey = 'email.status.' . $orderStatus;
    $orderStatusDisplay = email_t($statusKey, $lang, []);
    // If translation key not found, use fallback
    if ($orderStatusDisplay === $statusKey) {
        $orderStatusDisplay = ucfirst(str_replace('_', ' ', $orderStatus));
    }
    
    // Create status badge HTML with structured status mapping
    $statusClass = 'status-pending';
    $paidStatuses = ['paid', 'completed', 'fulfilled'];
    $awaitingStatuses = ['awaiting_cash_pickup', 'awaiting_payment', 'awaiting_confirmation'];
    
    if (in_array($orderStatus, $paidStatuses, true)) {
        $statusClass = 'status-paid';
    } elseif (in_array($orderStatus, $awaitingStatuses, true)) {
        $statusClass = 'status-awaiting';
    }
    $orderStatusBadge = '<span class="status-badge ' . $statusClass . '">' . htmlspecialchars($orderStatusDisplay) . '</span>';
    
    // Determine payment status with structured mapping
    $paymentStatus = $order['payment_status'] ?? 'pending';
    if (in_array($orderStatus, $paidStatuses, true) || $paymentStatus === 'paid') {
        $paymentStatusDisplay = email_t('email.status.paid', $lang);
    } elseif ($paymentMethodLower === 'cash') {
        $paymentStatusDisplay = email_t('email.status.cash_on_pickup', $lang);
    } else {
        $statusPaymentKey = 'email.status.' . $paymentStatus;
        $paymentStatusDisplay = email_t($statusPaymentKey, $lang);
        if ($paymentStatusDisplay === $statusPaymentKey) {
            $paymentStatusDisplay = ucfirst($paymentStatus);
        }
    }
    
    // Transaction reference
    $transactionId = $order['transaction_id'] ?? '';
    $transactionReference = '';
    $transactionReferenceText = '';
    if (!empty($transactionId)) {
        $transactionReference = '<div class="info-row"><span class="info-label">Transaction ID:</span><span class="info-value">' . htmlspecialchars($transactionId) . '</span></div>';
        $transactionReferenceText = 'Transaction ID: ' . $transactionId;
    }
    
    // Build items table (HTML)
    $itemsTable = buildOrderItemsTableHtml($order['items'] ?? [], $lang);
    
    // Build items list (text)
    $itemsList = buildOrderItemsListText($order['items'] ?? [], $lang);
    
    // Fulfillment details (pickup or delivery)
    $fulfillmentDetails = '';
    $fulfillmentText = '';
    $pickupBranch = '';
    $shippingAddressBlock = '';
    
    if (!empty($order['pickup_in_branch'])) {
        $branchId = $order['pickup_branch_id'] ?? '';
        $branches = loadJSON('branches.json');
        $branchName = 'Selected branch';
        $branchAddress = '';
        
        foreach ($branches as $branch) {
            if (($branch['id'] ?? '') === $branchId) {
                $branchName = $branch['name'] ?? 'Selected branch';
                $branchAddress = $branch['address'] ?? '';
                break;
            }
        }
        
        // HTML version for admin
        $fulfillmentDetails = '<div class="info-box">';
        $fulfillmentDetails .= '<div class="info-row"><span class="info-label">' . email_t('email.order.deliveryMethod', $lang) . ':</span><span class="info-value"><strong>' . email_t('email.delivery.pickup', $lang) . '</strong></span></div>';
        $fulfillmentDetails .= '<div class="info-row"><span class="info-label">' . email_t('email.order.branch', $lang) . ':</span><span class="info-value">' . htmlspecialchars($branchName) . '</span></div>';
        if ($branchAddress) {
            $fulfillmentDetails .= '<div class="info-row"><span class="info-label">' . email_t('email.order.address', $lang) . ':</span><span class="info-value">' . htmlspecialchars($branchAddress) . '</span></div>';
        }
        $fulfillmentDetails .= '</div>';
        
        // Text version
        $fulfillmentText = email_t('email.order.deliveryMethod', $lang) . ": " . email_t('email.delivery.pickup', $lang) . "\n" . email_t('email.order.branch', $lang) . ": " . $branchName;
        if ($branchAddress) {
            $fulfillmentText .= "\n" . email_t('email.order.address', $lang) . ": " . $branchAddress;
        }
        
        // Customer-facing pickup message (for backward compatibility)
        $pickupBranch = '<div class="info-box"><h3>' . email_t('email.order.pickupBranch', $lang) . '</h3><p>' . email_t('email.order.pickupBranch', $lang) . ': <strong>' . htmlspecialchars($branchName) . '</strong>';
        if ($branchAddress) {
            $pickupBranch .= '<br>' . htmlspecialchars($branchAddress);
        }
        $pickupBranch .= '</p><p style="color: #666; font-size: 0.9em; margin-top: 0.5rem;">' . email_t('email.order.shipping', $lang) . ': ' . email_t('email.delivery.free', $lang) . ' (' . email_t('email.delivery.pickupInBranch', $lang) . ')</p></div>';
        
        // For plain text version (backward compatibility)
        $shippingAddressBlock = email_t('email.order.pickupBranch', $lang) . ":\n  " . email_t('email.order.branch', $lang) . ": " . $branchName;
        if ($branchAddress) {
            $shippingAddressBlock .= "\n  " . email_t('email.order.address', $lang) . ": " . $branchAddress;
        }
        $shippingAddressBlock .= "\n  " . email_t('email.order.shipping', $lang) . ": " . email_t('email.delivery.free', $lang) . " (" . email_t('email.delivery.pickupInBranch', $lang) . ")";
    } else {
        $shippingAddr = $order['shipping'] ?? [];
        $street = htmlspecialchars($shippingAddr['street'] ?? 'N/A');
        $house = htmlspecialchars($shippingAddr['house'] ?? '');
        $zip = htmlspecialchars($shippingAddr['zip'] ?? 'N/A');
        $city = htmlspecialchars($shippingAddr['city'] ?? 'N/A');
        $country = htmlspecialchars($shippingAddr['country'] ?? 'N/A');
        
        // HTML version for admin
        $fulfillmentDetails = '<div class="info-box">';
        $fulfillmentDetails .= '<div class="info-row"><span class="info-label">' . email_t('email.order.deliveryMethod', $lang) . ':</span><span class="info-value"><strong>' . email_t('email.delivery.delivery', $lang) . '</strong></span></div>';
        $fulfillmentDetails .= '<div class="info-row"><span class="info-label">' . email_t('email.order.street', $lang) . ':</span><span class="info-value">' . $street . ' ' . $house . '</span></div>';
        $fulfillmentDetails .= '<div class="info-row"><span class="info-label">' . email_t('email.order.city', $lang) . ':</span><span class="info-value">' . $zip . ' ' . $city . '</span></div>';
        $fulfillmentDetails .= '<div class="info-row"><span class="info-label">' . email_t('email.order.country', $lang) . ':</span><span class="info-value">' . $country . '</span></div>';
        $fulfillmentDetails .= '</div>';
        
        // Text version
        $fulfillmentText = email_t('email.order.deliveryMethod', $lang) . ": " . email_t('email.delivery.delivery', $lang) . "\n" . email_t('email.order.street', $lang) . ": " . ($shippingAddr['street'] ?? 'N/A') . ' ' . ($shippingAddr['house'] ?? '');
        $fulfillmentText .= "\n" . email_t('email.order.city', $lang) . ": " . ($shippingAddr['zip'] ?? 'N/A') . ' ' . ($shippingAddr['city'] ?? 'N/A');
        $fulfillmentText .= "\n" . email_t('email.order.country', $lang) . ": " . ($shippingAddr['country'] ?? 'N/A');
        
        // Customer-facing shipping address (for backward compatibility)
        $address = $street . ' ' . $house . '<br>' . $zip . ' ' . $city . '<br>' . $country;
        $pickupBranch = '<div class="info-box"><h3>' . email_t('email.order.shippingAddress', $lang) . '</h3><p>' . $address . '</p></div>';
        
        // For plain text version (backward compatibility)
        $shippingAddressBlock = email_t('email.order.shippingAddress', $lang) . ":\n  " . 
            ($shippingAddr['street'] ?? '') . ' ' . ($shippingAddr['house'] ?? '') . "\n  " .
            ($shippingAddr['zip'] ?? '') . ' ' . ($shippingAddr['city'] ?? '') . "\n  " .
            ($shippingAddr['country'] ?? '');
    }
    
    return [
        'order_id' => $orderId,
        'order_date' => $orderDate,
        'order_status' => $orderStatusDisplay,
        'order_status_badge' => $orderStatusBadge,
        'customer_name' => $customerName,
        'customer_email' => $customerEmail,
        'customer_phone' => $customerPhone,
        'payment_method' => $paymentMethodDisplay,
        'payment_status' => $paymentStatusDisplay,
        'transaction_reference' => $transactionReference,
        'transaction_reference_text' => $transactionReferenceText,
        'fulfillment_details' => $fulfillmentDetails,
        'fulfillment_text' => $fulfillmentText,
        'items_table' => $itemsTable,
        'items_list' => $itemsList,
        'subtotal' => $subtotal,
        'shipping' => $shipping,
        'total' => $total,
        'pickup_branch' => $pickupBranch,
        'shipping_address_block' => $shippingAddressBlock
    ];
}

/**
 * Prepare template variables for support emails
 * 
 * @param array $request Support request data
 * @param string $lang Language code for translations (default: 'en')
 * @return array Template variables
 */
function prepareSupportTemplateVars(array $request, string $lang = 'en'): array {
    require_once __DIR__ . '/email/templates.php';
    
    $name = trim(($request['first_name'] ?? '') . ' ' . ($request['last_name'] ?? ''));
    $email = $request['email'] ?? '';
    $phone = $request['phone'] ?? '';
    $subject = $request['subject'] ?? '';
    $message = $request['message'] ?? '';
    $date = $request['date'] ?? date('Y-m-d H:i:s');
    $requestId = $request['id'] ?? 'N/A';
    $language = $request['language'] ?? 'N/A';
    $pageUrl = $request['page_url'] ?? 'N/A';
    
    return [
        'name' => $name,
        'email' => $email,
        'phone' => !empty($phone) ? $phone : 'Not provided',
        'support_subject' => $subject,
        'support_message' => nl2br(htmlspecialchars($message)),
        'date' => $date,
        'request_id' => $requestId,
        'language' => $language,
        'page_url' => $pageUrl
    ];
}

/**
 * Build order confirmation email HTML body
 * 
 * @param array $order Order data
 * @return string HTML email body
 */
function buildOrderConfirmationEmailBody(array $order): string {
    $orderId = $order['id'] ?? 'N/A';
    $orderDate = $order['date'] ?? date('Y-m-d H:i:s');
    $customerName = ($order['customer']['first_name'] ?? '') . ' ' . ($order['customer']['last_name'] ?? '');
    $subtotal = $order['subtotal'] ?? 0;
    $shippingCost = $order['shipping_cost'] ?? 0;
    $total = $order['total'] ?? 0;
    
    $html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #2c3e50; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background: #f9f9f9; }
        .order-details { background: white; padding: 15px; margin: 20px 0; }
        .order-items { margin: 20px 0; }
        .order-item { padding: 10px; border-bottom: 1px solid #ddd; }
        .order-total { font-size: 1.2em; font-weight: bold; margin-top: 20px; }
        .footer { text-align: center; padding: 20px; color: #666; font-size: 0.9em; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Order Confirmation</h1>
            <p>Thank you for your order!</p>
        </div>
        <div class="content">
            <p>Dear ' . htmlspecialchars($customerName) . ',</p>
            <p>Your order has been successfully placed and is being processed.</p>
            
            <div class="order-details">
                <h2>Order Details</h2>
                <p><strong>Order Number:</strong> ' . htmlspecialchars($orderId) . '</p>
                <p><strong>Order Date:</strong> ' . htmlspecialchars($orderDate) . '</p>
            </div>
            
            <div class="order-items">
                <h3>Items Ordered:</h3>';
    
    foreach ($order['items'] ?? [] as $item) {
        $itemName = $item['name'] ?? 'Product';
        $itemQty = $item['quantity'] ?? 1;
        $itemPrice = $item['price'] ?? 0;
        $itemTotal = $itemPrice * $itemQty;
        
        $html .= '
                <div class="order-item">
                    <strong>' . htmlspecialchars($itemName) . '</strong><br>
                    Quantity: ' . (int)$itemQty . ' × CHF ' . number_format($itemPrice, 2) . ' = CHF ' . number_format($itemTotal, 2) . '
                </div>';
    }
    
    $html .= '
            </div>
            
            <div class="order-total">
                <p>Subtotal: CHF ' . number_format($subtotal, 2) . '</p>
                <p>Shipping: ' . ($shippingCost > 0 ? 'CHF ' . number_format($shippingCost, 2) : 'Free') . '</p>
                <p style="border-top: 2px solid #333; padding-top: 10px;">Total: CHF ' . number_format($total, 2) . '</p>
            </div>';
    
    if (!empty($order['pickup_in_branch'])) {
        $html .= '
            <div class="order-details">
                <h3>Pickup Information</h3>
                <p>Your order will be ready for pickup. We will notify you when it is ready.</p>
            </div>';
    } else {
        $shipping = $order['shipping'] ?? [];
        $html .= '
            <div class="order-details">
                <h3>Shipping Address</h3>
                <p>
                    ' . htmlspecialchars($shipping['street'] ?? '') . ' ' . htmlspecialchars($shipping['house'] ?? '') . '<br>
                    ' . htmlspecialchars($shipping['zip'] ?? '') . ' ' . htmlspecialchars($shipping['city'] ?? '') . '<br>
                    ' . htmlspecialchars($shipping['country'] ?? '') . '
                </p>
            </div>';
    }
    
    $html .= '
        </div>
        <div class="footer">
            <p>Thank you for shopping with NicheHome.ch!</p>
            <p>If you have any questions, please contact us at info@nichehome.ch</p>
        </div>
    </div>
</body>
</html>';
    
    return $html;
}

/**
 * Send notification email for new order
 * 
 * @param array $order Order data
 * @return bool Success status
 */
function sendNewOrderNotification(array $order): bool {
    require_once __DIR__ . '/email/mailer.php';
    require_once __DIR__ . '/email/templates.php';
    
    $settings = loadEmailSettings();
    
    if (empty($settings['enabled'])) {
        error_log("Order admin notification not sent: email system disabled");
        return false;
    }
    
    $adminEmail = $settings['routing']['admin_orders_email'] ?? '';
    if (empty($adminEmail)) {
        error_log("Order admin notification not sent: no admin email configured");
        return false;
    }
    
    // Prepare template variables
    $vars = prepareOrderTemplateVars($order);
    
    // Render template
    $rendered = renderEmailTemplate('order_admin', $vars);
    
    // Send email
    $result = sendEmailViaSMTP(
        $adminEmail, 
        $rendered['subject'], 
        $rendered['html'], 
        $rendered['text'],
        null,
        'order_admin'
    );
    
    if (!$result['success']) {
        error_log("Failed to send order admin notification: " . $result['error']);
    }
    
    return $result['success'];
}

/**
 * Build localized support auto-reply email HTML for customer
 * @param array $vars Template variables
 * @param string $lang Language code
 * @return string HTML email body
 */
function buildLocalizedSupportCustomerHtml(array $vars, string $lang): string {
    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 700px; margin: 0 auto; padding: 20px; }
        .header { background: #27ae60; color: white; padding: 30px; text-align: center; }
        .content { padding: 30px; background: #f9f9f9; }
        .info-box { background: white; padding: 20px; margin: 20px 0; }
        .footer { text-align: center; padding: 20px; color: #666; font-size: 0.9em; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>✓ ' . email_t('email.support.title', $lang) . '</h1>
        </div>
        <div class="content">
            <p>' . email_t('email.support.greeting', $lang, ['name' => htmlspecialchars($vars['name'])]) . '</p>
            <p>' . email_t('email.support.intro', $lang) . '</p>
            
            <div class="info-box">
                <h3>' . email_t('email.support.yourRequest', $lang) . ':</h3>
                <p><strong>' . email_t('email.support.subject_label', $lang) . ':</strong> ' . htmlspecialchars($vars['support_subject']) . '</p>
                <p><strong>' . email_t('email.support.message_label', $lang) . ':</strong></p>
                <p style="background: #f5f5f5; padding: 15px; border-left: 3px solid #27ae60;">' . $vars['support_message'] . '</p>
            </div>
            
            <p>' . email_t('email.support.responseTime', $lang) . '</p>
        </div>
        <div class="footer">
            <p>' . email_t('email.support.footer.thanks', $lang) . '</p>
            <p>' . email_t('email.support.footer.urgent', $lang) . '</p>
            <p style="font-size: 0.85em; color: #999; margin-top: 1rem;">' . email_t('email.footer.auto', $lang) . '</p>
        </div>
    </div>
</body>
</html>';
    
    return $html;
}

/**
 * Build localized support auto-reply email plain text for customer
 * @param array $vars Template variables
 * @param string $lang Language code
 * @return string Plain text email body
 */
function buildLocalizedSupportCustomerText(array $vars, string $lang): string {
    $text = strtoupper(email_t('email.support.title', $lang)) . "\n\n";
    
    $text .= email_t('email.support.greeting', $lang, ['name' => $vars['name']]) . "\n\n";
    $text .= email_t('email.support.intro', $lang) . "\n\n";
    
    $text .= email_t('email.support.yourRequest', $lang) . ":\n";
    $text .= "- " . email_t('email.support.subject_label', $lang) . ": " . $vars['support_subject'] . "\n";
    $text .= "- " . email_t('email.support.message_label', $lang) . ": " . strip_tags($vars['support_message']) . "\n\n";
    
    $text .= email_t('email.support.responseTime', $lang) . "\n\n";
    
    $text .= email_t('email.support.footer.thanks', $lang) . "\n";
    $text .= email_t('email.support.footer.urgent', $lang) . "\n\n";
    
    $text .= "---\n";
    $text .= email_t('email.footer.auto', $lang) . "\n";
    $text .= "NicheHome.ch";
    
    return $text;
}

/**
 * Send notification email for new support request
 * 
 * @param array $request Support request data
 * @return bool Success status
 */
function sendNewSupportRequestNotification(array $request): bool {
    require_once __DIR__ . '/email/mailer.php';
    require_once __DIR__ . '/email/templates.php';
    
    $settings = loadEmailSettings();
    
    if (empty($settings['enabled'])) {
        error_log("Support admin notification not sent: email system disabled");
        return false;
    }
    
    $supportEmail = $settings['routing']['support_email'] ?? '';
    if (empty($supportEmail)) {
        error_log("Support admin notification not sent: no support email configured");
        return false;
    }
    
    // Get language from request (admin notification stays in English)
    $lang = $request['language'] ?? 'en';
    
    // Prepare template variables (admin notification in English)
    $vars = prepareSupportTemplateVars($request, 'en');
    
    // Render admin template (in English)
    $rendered = renderEmailTemplate('support_admin', $vars);
    
    // Override subject to match requirements
    $customerName = trim(($request['first_name'] ?? '') . ' ' . ($request['last_name'] ?? ''));
    $subject = "New support request — {$customerName} — NicheHome.ch";
    
    // Send email to admin
    $result = sendEmailViaSMTP(
        $supportEmail, 
        $subject, 
        $rendered['html'], 
        $rendered['text'],
        $request['email'] ?? null,  // Reply-to customer
        'support_admin',
        ['customer_email' => $request['email'] ?? '', 'request_id' => $request['id'] ?? '', 'lang' => 'en']
    );
    
    if (!$result['success']) {
        error_log("Failed to send support admin notification: " . $result['error']);
        return false;
    }
    
    // Send auto-reply to customer in their language
    $customerEmail = $request['email'] ?? '';
    if (!empty($customerEmail)) {
        // Prepare variables in customer's language
        $varsCustomer = prepareSupportTemplateVars($request, $lang);
        
        // Build localized customer auto-reply
        $customerName = $varsCustomer['name'];
        $subject = email_t('email.support.subject', $lang);
        $html = buildLocalizedSupportCustomerHtml($varsCustomer, $lang);
        $text = buildLocalizedSupportCustomerText($varsCustomer, $lang);
        
        $replyTo = $settings['routing']['reply_to_email'] ?? null;
        
        $resultCustomer = sendEmailViaSMTP(
            $customerEmail, 
            $subject, 
            $html, 
            $text,
            $replyTo,
            'support_customer',
            ['customer_email' => $customerEmail, 'request_id' => $request['id'] ?? '', 'lang' => $lang]
        );
        
        if (!$resultCustomer['success']) {
            error_log("Failed to send support customer auto-reply: " . $resultCustomer['error']);
        }
    }
    
    return $result['success'];
}

/**
 * Evaluate gift set stock availability
 * FIX: TASK 2 UPDATE - Added full product description (name + volume + fragrance) for customer clarity
 * 
 * @param array $giftSetItems Array of gift set items with category, volume, fragrance
 * @return array ['ok' => bool, 'required' => [sku => qty], 'available' => [sku => qty], 'missing' => [sku => qty], 'product_names' => [sku => full_description]]
 */
function evaluateGiftSetStock(array $giftSetItems): array {
    $required = [];
    $available = [];
    $missing = [];
    $productNames = []; // FIX: TASK 2 UPDATE - Store full product descriptions for each SKU
    
    // Load stock once
    $stock = loadJSON('stock.json');
    
    // Load sku_universe helper if available
    if (file_exists(__DIR__ . '/stock/sku_universe.php')) {
        require_once __DIR__ . '/stock/sku_universe.php';
    }
    
    // Load fragrances for name translation
    $fragrancesData = loadJSON('fragrances.json');
    
    // Map category to productId for SKU generation
    $categoryToProductId = [
        'aroma_diffusers' => 'diffuser_classic',
        'scented_candles' => 'candle_classic',
        'home_perfume' => 'home_spray',
        'car_perfume' => 'car_clip',
        'textile_perfume' => 'textile_spray',
        'limited_edition' => 'limited_new_york', // default, should be determined by fragrance
        'accessories' => 'aroma_sashe' // default
    ];
    
    // Process each gift set item
    foreach ($giftSetItems as $item) {
        $category = $item['category'] ?? '';
        $volume = $item['volume'] ?? 'standard';
        $fragrance = $item['fragrance'] ?? 'none';
        $qty = (int)($item['qty'] ?? 1);
        
        if (empty($category)) {
            continue;
        }
        
        // Map category to productId
        $productId = $item['productId'] ?? ($categoryToProductId[$category] ?? '');
        
        // Special case for limited edition - productId depends on fragrance
        if ($category === 'limited_edition') {
            if ($fragrance === 'new_york') {
                $productId = 'limited_new_york';
            } elseif ($fragrance === 'abu_dhabi') {
                $productId = 'limited_abu_dhabi';
            } elseif ($fragrance === 'palermo') {
                $productId = 'limited_palermo';
            }
        }
        
        // Generate SKU
        $sku = generateSKU($productId, $volume, $fragrance);
        
        // FIX: TASK 2 UPDATE - Build full product description: "Product Name Volume, Fragrance"
        $productName = '';
        if (function_exists('getProductNameFromId')) {
            $productName = getProductNameFromId($productId);
        } else {
            // Fallback
            if (isset($stock[$sku]['productId'])) {
                $productName = ucfirst(str_replace('_', ' ', $stock[$sku]['productId']));
            } else {
                $productName = ucfirst(str_replace('_', ' ', $productId));
            }
        }
        
        // Add volume to description (clean up "ml" suffix if present)
        $volumeDisplay = $volume;
        if ($volume !== 'standard' && !empty($volume)) {
            // Keep volume as-is for display
            $volumeDisplay = $volume;
        } else {
            $volumeDisplay = ''; // Don't show "standard"
        }
        
        // Add fragrance name to description
        $fragranceDisplay = '';
        if ($fragrance !== 'none' && $fragrance !== 'NA' && !empty($fragrance)) {
            // Try to get translated fragrance name
            if (isset($fragrancesData[$fragrance]['name'])) {
                $fragranceDisplay = $fragrancesData[$fragrance]['name'];
            } else {
                // Fallback to formatted fragrance code
                $fragranceDisplay = ucfirst(str_replace('_', ' ', $fragrance));
            }
        }
        
        // Build full description: "Product Name Volume, Fragrance"
        $fullDescription = $productName;
        if (!empty($volumeDisplay)) {
            $fullDescription .= ' ' . $volumeDisplay;
        }
        if (!empty($fragranceDisplay)) {
            $fullDescription .= ', ' . $fragranceDisplay;
        }
        
        $productNames[$sku] = $fullDescription;
        
        // Sum required quantities per SKU
        if (!isset($required[$sku])) {
            $required[$sku] = 0;
        }
        $required[$sku] += $qty;
    }
    
    // Check available stock for each required SKU
    foreach ($required as $sku => $requiredQty) {
        $availableQty = (int)($stock[$sku]['quantity'] ?? 0);
        $available[$sku] = $availableQty;
        
        if ($availableQty < $requiredQty) {
            $missing[$sku] = $requiredQty - $availableQty;
        }
    }
    
    $ok = empty($missing);
    
    return [
        'ok' => $ok,
        'required' => $required,
        'available' => $available,
        'missing' => $missing,
        'product_names' => $productNames // FIX: TASK 2 UPDATE - Return product names
    ];
}

/**
 * Expand gift set items to a map of SKU => total quantity needed
 * Reuses the same SKU generation logic as evaluateGiftSetStock
 * 
 * @param array $giftSetItems Array of gift set items with category, volume, fragrance, qty
 * @return array Map of SKU => total quantity required (e.g., ['DF-125-CHE' => 3])
 */
function expandGiftSetToSkuMap(array $giftSetItems): array {
    $skuMap = [];
    
    // Map category to productId for SKU generation (same as in evaluateGiftSetStock)
    $categoryToProductId = [
        'aroma_diffusers' => 'diffuser_classic',
        'scented_candles' => 'candle_classic',
        'home_perfume' => 'home_spray',
        'car_perfume' => 'car_clip',
        'textile_perfume' => 'textile_spray',
        'limited_edition' => 'limited_new_york', // default, should be determined by fragrance
        'accessories' => 'aroma_sashe' // default
    ];
    
    // Process each gift set item
    foreach ($giftSetItems as $item) {
        $category = $item['category'] ?? '';
        // Support both 'variant' (used in cart) and 'volume' (legacy) field names
        $volume = $item['variant'] ?? $item['volume'] ?? 'standard';
        $fragrance = $item['fragrance'] ?? 'none';
        $qty = (int)($item['qty'] ?? 1);
        
        if (empty($category)) {
            continue;
        }
        
        // Map category to productId
        $productId = $item['productId'] ?? ($categoryToProductId[$category] ?? '');
        
        // Special case for limited edition - productId depends on fragrance
        if ($category === 'limited_edition') {
            if ($fragrance === 'new_york') {
                $productId = 'limited_new_york';
            } elseif ($fragrance === 'abu_dhabi') {
                $productId = 'limited_abu_dhabi';
            } elseif ($fragrance === 'palermo') {
                $productId = 'limited_palermo';
            }
        }
        
        // Generate SKU using existing function
        $sku = generateSKU($productId, $volume, $fragrance);
        
        // Sum quantities per SKU
        if (!isset($skuMap[$sku])) {
            $skuMap[$sku] = 0;
        }
        $skuMap[$sku] += $qty;
    }
    
    return $skuMap;
}

/**
 * Format gift set contents for display
 * 
 * @param array $giftSetItems Array of gift set items
 * @param string $lang Language code
 * @return string Formatted contents string
 */
function formatGiftSetContents(array $giftSetItems, string $lang = 'en'): string {
    if (empty($giftSetItems)) {
        return '';
    }
    
    // Load product and accessory catalogs for name lookups
    $products = loadJSON('products.json');
    $accessories = loadJSON('accessories.json');
    
    // Build formatted items list (no grouping - show each slot explicitly)
    $formattedItems = [];
    foreach ($giftSetItems as $item) {
        $productId = $item['productId'] ?? '';
        $category = $item['category'] ?? '';
        $volume = $item['volume'] ?? $item['variant'] ?? 'standard';
        $fragrance = $item['fragrance'] ?? 'none';
        $qty = (int)($item['qty'] ?? 1);
        
        // Get product name from catalog
        $productName = '';
        if ($category === 'accessories' && isset($accessories[$productId])) {
            $nameKey = $accessories[$productId]['name_key'] ?? '';
            $productName = !empty($nameKey) ? I18N::t($nameKey, ucfirst(str_replace('_', ' ', $productId))) : ucfirst(str_replace('_', ' ', $productId));
        } elseif (isset($products[$productId])) {
            $nameKey = $products[$productId]['name_key'] ?? '';
            $productName = !empty($nameKey) ? I18N::t($nameKey, ucfirst(str_replace('_', ' ', $productId))) : ucfirst(str_replace('_', ' ', $productId));
        } else {
            // Fallback: use productName from item if available, or category name
            $productName = $item['productName'] ?? I18N::t('category.' . $category . '.name', ucfirst(str_replace('_', ' ', $category)));
        }
        
        // Build item description
        $parts = [$productName];
        
        // Add volume/variant if not standard
        if ($volume !== 'standard' && $volume !== 'none' && !empty($volume)) {
            $parts[] = $volume;
        }
        
        // Add fragrance if not none/NA
        if ($fragrance !== 'none' && $fragrance !== 'NA' && !empty($fragrance)) {
            $fragranceName = I18N::t('fragrance.' . $fragrance . '.name', ucfirst(str_replace('_', ' ', $fragrance)));
            $parts[] = $fragranceName;
        }
        
        $description = implode(' ', $parts);
        
        // Add count prefix
        $formattedItems[] = $qty . '× ' . $description;
    }
    
    // Join all items
    return '(' . implode('; ', $formattedItems) . ')';
}

/**
 * Generate a deterministic unique key for a gift set configuration
 * 
 * This ensures that identical gift set configurations merge in cart (increase qty),
 * while different configurations remain as separate line items.
 * 
 * @param array $giftSetItems Array of gift set items with category, productId, variant, fragrance
 * @return string Hash key (e.g., "giftset:9c2a3b4d...")
 */
function generateGiftSetConfigKey(array $giftSetItems): string {
    // Normalize the configuration: extract only canonical fields
    $normalized = [];
    
    foreach ($giftSetItems as $index => $item) {
        $slot = [
            'slot' => $index + 1, // Keep slot position fixed (1, 2, 3)
            'category' => sanitize($item['category'] ?? ''),
            'productId' => sanitize($item['productId'] ?? ''),
            'variant' => sanitize($item['variant'] ?? $item['volume'] ?? 'standard'),
            'fragrance' => sanitize($item['fragrance'] ?? 'none'),
        ];
        
        // Add SKU if present (for accessories or products with specific SKUs)
        if (!empty($item['sku'])) {
            $slot['sku'] = sanitize($item['sku']);
        }
        
        // Sort keys within slot for consistency (but maintain slot order)
        ksort($slot);
        
        $normalized[] = $slot;
    }
    
    // Serialize deterministically
    $json = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
    // Generate hash
    $hash = sha1($json);
    
    // Return prefixed key
    return 'giftset:' . $hash;
}

/**
 * Stock Admin Helper Functions
 */

/**
 * Create a timestamped backup of a JSON file
 * @param string $filename The filename (e.g., 'stock.json', 'branch_stock.json')
 * @return bool Success status
 */
function createStockBackup(string $filename): bool {
    $sourcePath = __DIR__ . '/../data/' . $filename;
    if (!file_exists($sourcePath)) {
        error_log("STOCK BACKUP: Source file not found: $sourcePath");
        return false;
    }
    
    $backupDir = __DIR__ . '/../data/backups';
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
    
    $timestamp = date('Ymd-His');
    $backupFilename = pathinfo($filename, PATHINFO_FILENAME) . '.' . $timestamp . '.' . pathinfo($filename, PATHINFO_EXTENSION);
    $backupPath = $backupDir . '/' . $backupFilename;
    
    if (copy($sourcePath, $backupPath)) {
        error_log("STOCK BACKUP: Created backup: $backupFilename");
        return true;
    }
    
    error_log("STOCK BACKUP: Failed to create backup: $backupFilename");
    return false;
}

/**
 * Log stock changes to logs/stock.log
 * @param string $message Log message
 */
function logStockChange(string $message): void {
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/stock.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

/**
 * Validate stock quantity (must be non-negative integer)
 * @param mixed $value The value to validate
 * @return array ['valid' => bool, 'value' => int, 'error' => string]
 */
function validateStockQuantity($value): array {
    if (!is_numeric($value)) {
        return ['valid' => false, 'value' => 0, 'error' => 'Quantity must be a number'];
    }
    
    $intValue = intval($value);
    if ($intValue != $value) {
        return ['valid' => false, 'value' => $intValue, 'error' => 'Quantity must be a whole number'];
    }
    
    if ($intValue < 0) {
        return ['valid' => false, 'value' => 0, 'error' => 'Quantity cannot be negative'];
    }
    
    return ['valid' => true, 'value' => $intValue, 'error' => ''];
}

/**
 * Get all branch IDs and names from branches data
 * @return array Associative array of branch_id => branch_name
 */
function getAllBranches(): array {
    // Load branch stock to get actual branch IDs in use
    $branchStock = loadBranchStock();
    $result = [];
    
    // Get branch IDs from branch_stock.json (source of truth for stock management)
    foreach (array_keys($branchStock) as $branchId) {
        // Default to using branch ID as name
        $branchName = $branchId;
        
        // Try to get name from branches.json
        $branchesData = loadBranches();
        
        // branches.json might be array or associative array
        if (is_array($branchesData)) {
            // Check if it's an indexed array of branch objects
            foreach ($branchesData as $branch) {
                if (is_array($branch) && isset($branch['id']) && $branch['id'] === $branchId) {
                    $branchName = $branch['name'] ?? $branchId;
                    break;
                }
            }
            
            // Check if it's an associative array with branch IDs as keys
            if (isset($branchesData[$branchId])) {
                $branchName = $branchesData[$branchId]['name'] ?? $branchId;
            }
        }
        
        // Format display name
        $result[$branchId] = $branchName;
    }
    
    return $result;
}

/**
 * Get consolidated stock view: SKU -> [product_name, volume, fragrance, branch quantities, total]
 * DEPRECATED: This function only shows SKUs from stock.json
 * Use getConsolidatedStockViewFromUniverse() for complete SKU list
 * 
 * @return array Array of stock items with all branch quantities
 */
function getConsolidatedStockView(): array {
    $stock = loadJSON('stock.json');
    $branchStock = loadBranchStock();
    $branches = getAllBranches();
    $products = loadJSON('products.json');
    $accessories = loadJSON('accessories.json');
    
    $consolidated = [];
    
    foreach ($stock as $sku => $stockData) {
        $productId = $stockData['productId'] ?? '';
        $volume = $stockData['volume'] ?? '';
        $fragrance = $stockData['fragrance'] ?? '';
        
        // Get product name
        $productName = '';
        if (isset($products[$productId])) {
            $nameKey = $products[$productId]['name_key'] ?? ('product.' . $productId . '.name');
            $productName = I18N::t($nameKey, ucfirst(str_replace('_', ' ', $productId)));
        }
        
        // Get branch quantities
        $branchQuantities = [];
        $total = 0;
        foreach ($branches as $branchId => $branchName) {
            $qty = (int)($branchStock[$branchId][$sku]['quantity'] ?? 0);
            $branchQuantities[$branchId] = (int)$qty;
            $total += $qty;
        }
        
        $consolidated[$sku] = [
            'sku' => $sku,
            'productId' => $productId,
            'product_name' => $productName,
            'volume' => $volume,
            'fragrance' => $fragrance,
            'branches' => $branchQuantities,
            'total' => $total
        ];
    }
    
    return $consolidated;
}

/**
 * Get consolidated stock view from SKU Universe (COMPLETE SKU LIST)
 * This includes ALL SKUs from catalog, stock.json, and branch_stock.json
 * 
 * @return array Array of ALL stock items with branch quantities and totals
 */
function getConsolidatedStockViewFromUniverse(): array {
    require_once __DIR__ . '/stock/sku_universe.php';
    
    $universe = loadSkuUniverse();
    $stock = loadJSON('stock.json');
    $branchStock = loadBranchStock();
    $branches = getAllBranches();
    
    $consolidated = [];
    
    foreach ($universe as $sku => $data) {
        // Get branch quantities
        $branchQuantities = [];
        $total = 0;
        foreach ($branches as $branchId => $branchName) {
            $qty = (int)($branchStock[$branchId][$sku]['quantity'] ?? 0);
            $branchQuantities[$branchId] = (int)$qty;
            $total += $qty;
        }
        
        $consolidated[$sku] = [
            'sku' => $sku,
            'productId' => $data['productId'],
            'product_name' => $data['product_name'],
            'volume' => $data['volume'],
            'fragrance' => $data['fragrance'],
            'category' => $data['category'],
            'branches' => $branchQuantities,
            'total' => $total,
            'in_catalog' => $data['in_catalog'],
            'in_stock_json' => $data['in_stock_json'],
            'in_any_branch_json' => $data['in_any_branch_json']
        ];
    }
    
    return $consolidated;
}

/**
 * Build branch stock rows from the canonical SKU universe.
 *
 * This keeps the branch stock page aligned with the consolidated stock page
 * and ensures non-fragrance / legacy SKUs remain visible when they exist in
 * stock sources.
 *
 * @param string $branchId
 * @return array
 */
function getBranchStockItemsFromUniverse(string $branchId): array {
    require_once __DIR__ . '/stock/sku_universe.php';

    $consolidated = getConsolidatedStockViewFromUniverse();
    $items = [];

    foreach ($consolidated as $sku => $data) {
        $fragrance = (string)($data['fragrance'] ?? '');
        if ($fragrance === '' || strtoupper($fragrance) === 'NA' || strtolower($fragrance) === 'none') {
            $fragranceName = 'No fragrance / Device';
        } else {
            $fragranceName = I18N::t(
                'fragrance.' . $fragrance . '.name',
                ucfirst(str_replace('_', ' ', $fragrance))
            );
        }

        $items[] = [
            'sku' => $sku,
            'productId' => $data['productId'] ?? '',
            'productName' => $data['product_name'] ?? ($data['productId'] ?? $sku),
            'category' => $data['category'] ?? '',
            'volume' => $data['volume'] ?? '',
            'fragrance' => $fragrance,
            'fragranceName' => $fragranceName,
            'quantity' => (int)($data['branches'][$branchId] ?? 0),
            'in_catalog' => !empty($data['in_catalog'])
        ];
    }

    return $items;
}

/**
 * Normalize a regular cart selection to the canonical server SKU.
 *
 * Cart requests may contain legacy or browser-generated placeholder fragrance
 * values such as "none". This helper preserves display-friendly cart metadata
 * while always deriving the persisted SKU from the PHP source of truth.
 *
 * @param string $productId
 * @param string $volume
 * @param string $fragrance
 * @return array{sku:string,volume:string,fragrance:string}
 */
function normalizeCartSelection(string $productId, string $volume = 'standard', string $fragrance = 'none'): array {
    $normalizedVolume = trim($volume) !== '' ? trim($volume) : 'standard';
    $normalizedFragrance = trim($fragrance);
    if ($normalizedFragrance === '' || strtolower($normalizedFragrance) === 'na' || strtolower($normalizedFragrance) === 'null') {
        $normalizedFragrance = 'none';
    }

    return [
        'sku' => generateSKU($productId, $normalizedVolume, $normalizedFragrance),
        'volume' => $normalizedVolume,
        'fragrance' => $normalizedFragrance
    ];
}

/**
 * Update branch stock for a SKU and keep stock.json total aligned with the branch sum.
 * @param string $sku The SKU to update
 * @param mixed $branchQuantities Associative array of branch quantities keyed by branch ID
 * @return array ['success' => bool, 'error' => string, 'oldTotal' => int, 'newTotal' => int]
 */
function updateConsolidatedStock(string $sku, $branchQuantities): array {
    if (!is_array($branchQuantities)) {
        return ['success' => false, 'error' => 'Branch quantities are required for this update', 'oldTotal' => 0, 'newTotal' => 0];
    }

    if (!createStockBackup('stock.json')) {
        return ['success' => false, 'error' => 'Failed to create stock backup', 'oldTotal' => 0, 'newTotal' => 0];
    }

    if (!createStockBackup('branch_stock.json')) {
        return ['success' => false, 'error' => 'Failed to create branch stock backup', 'oldTotal' => 0, 'newTotal' => 0];
    }

    $stock = loadJSON('stock.json');
    $branchStock = loadBranchStock();
    $branches = getAllBranches();
    if (!isset($stock[$sku])) {
        return ['success' => false, 'error' => "SKU '$sku' not found in stock.json", 'oldTotal' => 0, 'newTotal' => 0];
    }

    $normalizedBranchQuantities = [];
    $newTotal = 0;

    foreach ($branches as $branchId => $branchName) {
        $rawValue = $branchQuantities[$branchId] ?? ($branchStock[$branchId][$sku]['quantity'] ?? 0);
        $validation = validateStockQuantity($rawValue);
        if (!$validation['valid']) {
            return ['success' => false, 'error' => "Branch $branchId: " . $validation['error'], 'oldTotal' => 0, 'newTotal' => 0];
        }

        $normalizedBranchQuantities[$branchId] = $validation['value'];
        $newTotal += $validation['value'];
    }

    $oldTotal = getBranchStockTotal($sku, $branchStock);

    foreach ($normalizedBranchQuantities as $branchId => $quantity) {
        $branchStock[$branchId][$sku] = ['quantity' => $quantity];
    }

    $stock[$sku]['quantity'] = $newTotal;
    $stock[$sku]['total_qty'] = $newTotal;

    if (!saveBranchStock($branchStock)) {
        return ['success' => false, 'error' => 'Failed to save branch_stock.json', 'oldTotal' => $oldTotal, 'newTotal' => $newTotal];
    }

    if (!saveJSON('stock.json', $stock)) {
        return ['success' => false, 'error' => 'Failed to save stock.json', 'oldTotal' => $oldTotal, 'newTotal' => $newTotal];
    }

    logStockChange("SKU: $sku | branch quantities updated | total: $oldTotal → {$newTotal}");

    return ['success' => true, 'error' => '', 'oldTotal' => $oldTotal, 'newTotal' => $newTotal];
}

/**
 * Permanently delete a product and all its SKUs from the system
 * 
 * This function performs a cascading delete:
 * 1. Removes product from products.json
 * 2. Removes product from accessories.json (if exists)
 * 3. Removes all SKUs for this product from stock.json
 * 4. Removes all SKUs for this product from branch_stock.json
 * 5. Removes i18n keys from all language files
 * 
 * Creates timestamped backups before any modifications.
 * Does NOT delete images - only removes references.
 * 
 * @param string $productId The product ID to delete
 * @return array ['success' => bool, 'error' => string, 'details' => array]
 */
function deleteProduct(string $productId): array {
    if (empty($productId)) {
        return [
            'success' => false,
            'error' => 'Product ID cannot be empty',
            'details' => []
        ];
    }
    
    $details = [
        'product_removed' => false,
        'accessory_removed' => false,
        'stock_skus_removed' => [],
        'branch_skus_removed' => [],
        'i18n_keys_removed' => []
    ];
    
    // Step 1: Load all data files
    $products = loadJSON('products.json');
    $accessories = loadJSON('accessories.json');
    $stock = loadJSON('stock.json');
    $branchStock = loadBranchStock();
    
    // Check if product exists
    if (!isset($products[$productId])) {
        return [
            'success' => false,
            'error' => "Product '$productId' not found in products.json",
            'details' => $details
        ];
    }
    
    // Step 2: Create backups for all files that will be modified
    $backupFiles = ['products.json', 'stock.json', 'branch_stock.json'];
    if (isset($accessories[$productId])) {
        $backupFiles[] = 'accessories.json';
    }
    
    foreach ($backupFiles as $file) {
        if (!createStockBackup($file)) {
            return [
                'success' => false,
                'error' => "Failed to create backup for $file. Deletion aborted.",
                'details' => $details
            ];
        }
    }
    
    // Backup i18n files
    $langs = ['en', 'de', 'fr', 'it', 'ru', 'ukr'];
    foreach ($langs as $lang) {
        $i18nFile = "i18n/ui_$lang.json";
        $i18nPath = __DIR__ . '/../data/' . $i18nFile;
        if (file_exists($i18nPath)) {
            if (!createStockBackup($i18nFile)) {
                error_log("Warning: Failed to backup $i18nFile, continuing anyway");
            }
        }
    }
    
    // Step 3: Identify all SKUs for this product
    $skusToDelete = [];
    foreach ($stock as $sku => $data) {
        if (isset($data['productId']) && $data['productId'] === $productId) {
            $skusToDelete[] = $sku;
        }
    }
    
    // Also check SKU Universe for complete list
    require_once __DIR__ . '/stock/sku_universe.php';
    $universe = loadSkuUniverse();
    foreach ($universe as $sku => $data) {
        if ($data['productId'] === $productId && !in_array($sku, $skusToDelete)) {
            $skusToDelete[] = $sku;
        }
    }
    
    // Step 4: Remove from products.json
    unset($products[$productId]);
    $details['product_removed'] = true;
    
    // Step 5: Remove from accessories.json if exists
    if (isset($accessories[$productId])) {
        unset($accessories[$productId]);
        $details['accessory_removed'] = true;
    }
    
    // Step 6: Remove all SKUs from stock.json
    foreach ($skusToDelete as $sku) {
        if (isset($stock[$sku])) {
            unset($stock[$sku]);
            $details['stock_skus_removed'][] = $sku;
        }
    }
    
    // Step 7: Remove all SKUs from branch_stock.json
    foreach ($branchStock as $branchId => &$branchData) {
        foreach ($skusToDelete as $sku) {
            if (isset($branchData[$sku])) {
                unset($branchData[$sku]);
                $details['branch_skus_removed'][] = "$branchId:$sku";
            }
        }
    }
    unset($branchData);
    
    // Step 8: Remove i18n keys from all language files
    foreach ($langs as $lang) {
        $i18nPath = __DIR__ . '/../data/i18n/ui_' . $lang . '.json';
        if (!file_exists($i18nPath)) {
            continue;
        }
        
        $i18nData = json_decode(file_get_contents($i18nPath), true);
        if (!is_array($i18nData)) {
            continue;
        }
        
        // Remove product keys (e.g., product.productId.name, product.productId.desc)
        if (isset($i18nData['product'][$productId])) {
            unset($i18nData['product'][$productId]);
            $details['i18n_keys_removed'][] = "ui_$lang.json:product.$productId";
            
            // Save modified i18n file
            file_put_contents(
                $i18nPath,
                json_encode($i18nData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );
        }
    }
    
    // Step 9: Save all modified files
    if (!saveJSON('products.json', $products)) {
        return [
            'success' => false,
            'error' => 'Failed to save products.json',
            'details' => $details
        ];
    }
    
    if ($details['accessory_removed']) {
        if (!saveJSON('accessories.json', $accessories)) {
            return [
                'success' => false,
                'error' => 'Failed to save accessories.json',
                'details' => $details
            ];
        }
    }
    
    if (!saveJSON('stock.json', $stock)) {
        return [
            'success' => false,
            'error' => 'Failed to save stock.json',
            'details' => $details
        ];
    }
    
    if (!saveBranchStock($branchStock)) {
        return [
            'success' => false,
            'error' => 'Failed to save branch_stock.json',
            'details' => $details
        ];
    }
    
    // Step 10: Log the deletion
    logStockChange("PRODUCT DELETED: $productId | SKUs removed: " . count($skusToDelete) . 
                   " | Stock SKUs: " . count($details['stock_skus_removed']) . 
                   " | Branch entries: " . count($details['branch_skus_removed']));
    
    return [
        'success' => true,
        'error' => '',
        'details' => $details
    ];
}

/**
 * Returns products.json entries whose category field matches the given slug.
 *
 * @param string $categoryId Category ID / slug
 * @return array<string, array>
 */
function getProductsInCategory(string $categoryId): array {
    if ($categoryId === '') {
        return [];
    }

    $products = loadJSON('products.json');
    return array_filter($products, function ($product) use ($categoryId) {
        return ($product['category'] ?? '') === $categoryId;
    });
}

/**
 * Permanently delete an empty category and its translations.
 *
 * The category delete rule is intentionally conservative:
 * categories with assigned products are blocked until those products are
 * reassigned or deleted explicitly.
 *
 * @param string $categoryId The category ID to delete
 * @return array ['success' => bool, 'error' => string, 'details' => array]
 */
function deleteCategory(string $categoryId): array {
    if (empty($categoryId)) {
        return [
            'success' => false,
            'error' => 'Category ID cannot be empty',
            'details' => []
        ];
    }

    $details = [
        'category_removed' => false,
        'blocked_products' => [],
        'i18n_keys_removed' => []
    ];

    $categories = loadJSON('categories.json');
    if (!isset($categories[$categoryId])) {
        return [
            'success' => false,
            'error' => "Category '$categoryId' not found in categories.json",
            'details' => $details
        ];
    }

    $productsInCategory = getProductsInCategory($categoryId);
    if (!empty($productsInCategory)) {
        $details['blocked_products'] = array_keys($productsInCategory);
        return [
            'success' => false,
            'error' => "Category '$categoryId' still has " . count($productsInCategory) . " assigned product(s). Reassign or delete those products first.",
            'details' => $details
        ];
    }

    if (!createStockBackup('categories.json')) {
        return [
            'success' => false,
            'error' => 'Failed to create backup for categories.json. Deletion aborted.',
            'details' => $details
        ];
    }

    foreach (I18N::getSupportedLanguages() as $lang) {
        foreach (["i18n/ui_$lang.json", "i18n/categories_$lang.json"] as $i18nFile) {
            $i18nPath = __DIR__ . '/../data/' . $i18nFile;
            if (!file_exists($i18nPath)) {
                continue;
            }
            if (!createStockBackup($i18nFile)) {
                return [
                    'success' => false,
                    'error' => "Failed to create backup for $i18nFile. Deletion aborted.",
                    'details' => $details
                ];
            }
        }
    }

    unset($categories[$categoryId]);
    $details['category_removed'] = true;

    foreach (I18N::getSupportedLanguages() as $lang) {
        foreach (["ui_$lang.json", "categories_$lang.json"] as $filename) {
            $i18nPath = __DIR__ . '/../data/i18n/' . $filename;
            if (!file_exists($i18nPath)) {
                continue;
            }

            $i18nData = json_decode((string)file_get_contents($i18nPath), true);
            if (!is_array($i18nData)) {
                continue;
            }

            if (isset($i18nData['category'][$categoryId])) {
                unset($i18nData['category'][$categoryId]);
                $details['i18n_keys_removed'][] = $filename . ':category.' . $categoryId;

                file_put_contents(
                    $i18nPath,
                    json_encode($i18nData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                );
            }
        }
    }

    if (!saveJSON('categories.json', $categories)) {
        return [
            'success' => false,
            'error' => 'Failed to save categories.json',
            'details' => $details
        ];
    }

    updateCatalogVersion();
    logStockChange("CATEGORY DELETED: $categoryId | Translation groups removed: " . count($details['i18n_keys_removed']));

    return [
        'success' => true,
        'error' => '',
        'details' => $details
    ];
}

/**
 * Permanently delete a branch and all its stock entries
 * 
 * This function:
 * 1. Removes branch from branches.json
 * 2. Removes all branch stock entries from branch_stock.json
 * 3. Ensures CSV export will not include this branch
 * 
 * Creates timestamped backups before modifications.
 * 
 * @param string $branchId The branch ID to delete
 * @return array ['success' => bool, 'error' => string, 'details' => array]
 */
function deleteBranch(string $branchId): array {
    if (empty($branchId)) {
        return [
            'success' => false,
            'error' => 'Branch ID cannot be empty',
            'details' => []
        ];
    }
    
    $details = [
        'branch_removed' => false,
        'stock_entries_removed' => 0
    ];
    
    // Step 1: Load data files
    $branches = loadBranches();
    $branchStock = loadBranchStock();
    
    // Check if branch exists
    if (!isset($branches[$branchId])) {
        return [
            'success' => false,
            'error' => "Branch '$branchId' not found in branches.json",
            'details' => $details
        ];
    }
    
    // Step 2: Create backups
    if (!createStockBackup('branches.json')) {
        return [
            'success' => false,
            'error' => 'Failed to create backup for branches.json. Deletion aborted.',
            'details' => $details
        ];
    }
    
    if (!createStockBackup('branch_stock.json')) {
        return [
            'success' => false,
            'error' => 'Failed to create backup for branch_stock.json. Deletion aborted.',
            'details' => $details
        ];
    }
    
    // Step 3: Count stock entries to be removed
    if (isset($branchStock[$branchId])) {
        $details['stock_entries_removed'] = count($branchStock[$branchId]);
    }
    
    // Step 4: Remove from branches.json
    unset($branches[$branchId]);
    $details['branch_removed'] = true;
    
    // Step 5: Remove from branch_stock.json
    if (isset($branchStock[$branchId])) {
        unset($branchStock[$branchId]);
    }
    
    // Step 6: Save modified files
    if (!saveBranches($branches)) {
        return [
            'success' => false,
            'error' => 'Failed to save branches.json',
            'details' => $details
        ];
    }
    
    if (!saveBranchStock($branchStock)) {
        return [
            'success' => false,
            'error' => 'Failed to save branch_stock.json',
            'details' => $details
        ];
    }
    
    // Step 7: Log the deletion
    logStockChange("BRANCH DELETED: $branchId | Stock entries removed: " . $details['stock_entries_removed']);
    
    return [
        'success' => true,
        'error' => '',
        'details' => $details
    ];
}

/**
 * Generate a 6-digit verification code
 */
function generateVerificationCode(): string {
    return str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
}

/**
 * Create password reset request for customer
 * @param string $email Customer email
 * @return array ['success' => bool, 'error' => string]
 */
function createPasswordResetRequest(string $email): array {
    $customers = getCustomers();
    
    if (!isset($customers[$email])) {
        return ['success' => false, 'error' => 'email_not_found'];
    }
    
    // Check rate limiting (60 seconds between requests)
    if (isset($customers[$email]['password_reset']['last_sent_at'])) {
        $lastSent = strtotime($customers[$email]['password_reset']['last_sent_at']);
        $now = time();
        if (($now - $lastSent) < 60) {
            return ['success' => false, 'error' => 'rate_limit'];
        }
    }
    
    // Generate new verification code
    $code = generateVerificationCode();
    $codeHash = password_hash($code, PASSWORD_DEFAULT);
    
    // Store reset data
    $customers[$email]['password_reset'] = [
        'code_hash' => $codeHash,
        'expires_at' => date('c', strtotime('+15 minutes')),
        'created_at' => date('c'),
        'attempts' => 0,
        'last_sent_at' => date('c')
    ];
    
    if (!saveCustomers($customers)) {
        return ['success' => false, 'error' => 'save_failed'];
    }
    
    // Send email with code
    require_once __DIR__ . '/email/mailer.php';
    $settings = loadEmailSettings();
    
    if (empty($settings['enabled'])) {
        error_log("Password reset email not sent: email system disabled");
        return ['success' => false, 'error' => 'email_disabled'];
    }
    
    $lang = I18N::getLanguage();
    $subject = I18N::t('email.passwordReset.subject', 'Password Reset Code');
    
    $html = '<html><body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">';
    $html .= '<h2>' . htmlspecialchars($subject) . '</h2>';
    $html .= '<p>' . I18N::t('email.passwordReset.greeting', 'Hello') . ',</p>';
    $html .= '<p>' . I18N::t('email.passwordReset.body', 'You requested to reset your password. Your verification code is:') . '</p>';
    $html .= '<div style="background: #f5f5f5; padding: 20px; text-align: center; font-size: 24px; font-weight: bold; letter-spacing: 5px; margin: 20px 0;">' . htmlspecialchars($code) . '</div>';
    $html .= '<p>' . I18N::t('email.passwordReset.expiry', 'This code will expire in 15 minutes.') . '</p>';
    $html .= '<p>' . I18N::t('email.passwordReset.ignore', 'If you did not request this, please ignore this email.') . '</p>';
    $html .= '</body></html>';
    
    $text = I18N::t('email.passwordReset.greeting', 'Hello') . "\n\n";
    $text .= I18N::t('email.passwordReset.body', 'You requested to reset your password. Your verification code is:') . "\n\n";
    $text .= $code . "\n\n";
    $text .= I18N::t('email.passwordReset.expiry', 'This code will expire in 15 minutes.') . "\n\n";
    $text .= I18N::t('email.passwordReset.ignore', 'If you did not request this, please ignore this email.');
    
    $result = sendEmailViaSMTP($email, $subject, $html, $text, null, 'password_reset', ['customer_email' => $email]);
    
    if (!$result['success']) {
        error_log("Failed to send password reset email to $email: " . $result['error']);
        return ['success' => false, 'error' => 'email_send_failed'];
    }
    
    return ['success' => true, 'error' => ''];
}

/**
 * Verify password reset code and update password
 * @param string $email Customer email
 * @param string $code Verification code
 * @param string $newPassword New password
 * @return array ['success' => bool, 'error' => string]
 */
function verifyAndResetPassword(string $email, string $code, string $newPassword): array {
    $customers = getCustomers();
    
    if (!isset($customers[$email])) {
        return ['success' => false, 'error' => 'email_not_found'];
    }
    
    $resetData = $customers[$email]['password_reset'] ?? null;
    if (!$resetData || empty($resetData['code_hash'])) {
        return ['success' => false, 'error' => 'no_reset_request'];
    }
    
    // Check if code has expired
    $expiresAt = strtotime($resetData['expires_at']);
    if (time() > $expiresAt) {
        return ['success' => false, 'error' => 'code_expired'];
    }
    
    // Check attempt limit
    $attempts = $resetData['attempts'] ?? 0;
    if ($attempts >= 5) {
        return ['success' => false, 'error' => 'too_many_attempts'];
    }
    
    // Verify code
    if (!password_verify($code, $resetData['code_hash'])) {
        // Increment attempts
        $customers[$email]['password_reset']['attempts'] = $attempts + 1;
        saveCustomers($customers);
        return ['success' => false, 'error' => 'invalid_code'];
    }
    
    // Update password
    $customers[$email]['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // Clear reset data
    unset($customers[$email]['password_reset']);
    
    if (!saveCustomers($customers)) {
        return ['success' => false, 'error' => 'save_failed'];
    }
    
    return ['success' => true, 'error' => ''];
}
