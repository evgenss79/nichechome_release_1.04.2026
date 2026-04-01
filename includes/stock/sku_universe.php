<?php
/**
 * SKU Universe - Single Source of Truth for all SKUs
 * 
 * This module provides a unified view of ALL SKUs from ALL sources:
 * - Catalog-derived SKUs (products.json + accessories.json)
 * - Global stock (stock.json)
 * - Derived branch compatibility mirror (branch_stock.json)
 * 
 * Purpose: Ensure every UI, export, and stock operation sees the complete SKU list
 */

/**
 * Load the complete SKU Universe
 * 
 * Returns array of SKU records with metadata:
 * [
 *   'SKU-123' => [
 *     'sku' => 'SKU-123',
 *     'productId' => 'diffuser_classic',
 *     'product_name' => 'Classic Diffuser',
 *     'category' => 'aroma_diffusers',
 *     'volume' => '125ml',
 *     'fragrance' => 'bellini',
 *     'in_catalog' => true,
 *     'in_stock_json' => true,
 *     'in_any_branch_json' => false,
 *     'stock_data' => [...] // from stock.json if exists
 *   ]
 * ]
 * 
 * @return array Associative array of SKU => metadata
 */
function loadSkuUniverse(): array {
    // Load all data sources
    $stock = loadJSON('stock.json');
    $branchStock = loadBranchStock();
    $products = loadJSON('products.json');
    $accessories = loadJSON('accessories.json');
    $fragrances = loadJSON('fragrances.json');
    
    $universe = [];
    
    // 1. Add all catalog-derived SKUs
    $catalogSkus = generateCatalogSkus($products, $accessories, $fragrances);
    foreach ($catalogSkus as $sku => $metadata) {
        $universe[$sku] = array_merge($metadata, [
            'in_catalog' => true,
            'in_stock_json' => false,
            'in_any_branch_json' => false,
            'stock_data' => null
        ]);
    }
    
    // 2. Add/merge SKUs from stock.json
    // CRITICAL: When SKU exists in both catalog and stock.json, catalog metadata is AUTHORITATIVE
    // We ONLY update flags and stock_data, NEVER overwrite product_name, category, or other metadata
    foreach ($stock as $sku => $stockData) {
        $sku = trim($sku);
        if (!isset($universe[$sku])) {
            // SKU exists in stock but not in catalog (orphan SKU)
            $universe[$sku] = [
                'sku' => $sku,
                'productId' => $stockData['productId'] ?? '',
                'product_name' => getProductNameFromId($stockData['productId'] ?? ''),
                'category' => 'unknown',
                'volume' => $stockData['volume'] ?? '',
                'fragrance' => $stockData['fragrance'] ?? '',
                'in_catalog' => false,
                'in_stock_json' => true,
                'in_any_branch_json' => false,
                'stock_data' => $stockData
            ];
        } else {
            // SKU already exists in catalog - ONLY update flags and stock_data
            // NEVER overwrite catalog metadata (product_name, category, etc.)
            $universe[$sku]['in_stock_json'] = true;
            $universe[$sku]['stock_data'] = $stockData;
            
            // Defensive check: If stock.json has different productId, log warning
            // but DO NOT change the catalog productId
            if (isset($stockData['productId']) && 
                !empty($stockData['productId']) && 
                $stockData['productId'] !== $universe[$sku]['productId']) {
                error_log("WARNING: SKU $sku has different productId in stock.json ({$stockData['productId']}) vs catalog ({$universe[$sku]['productId']}). Using catalog value.");
            }
        }
    }
    
    // 3. Add/merge SKUs from branch_stock.json
    $branchSkus = [];
    foreach ($branchStock as $branchId => $skus) {
        foreach (array_keys($skus) as $sku) {
            $branchSkus[$sku] = true;
        }
    }
    
    foreach (array_keys($branchSkus) as $sku) {
        $sku = trim($sku);
        if (!isset($universe[$sku])) {
            // SKU exists in branch stock but nowhere else
            $universe[$sku] = [
                'sku' => $sku,
                'productId' => '',
                'product_name' => 'Unknown Product',
                'category' => 'unknown',
                'volume' => '',
                'fragrance' => '',
                'in_catalog' => false,
                'in_stock_json' => false,
                'in_any_branch_json' => true,
                'stock_data' => null
            ];
        } else {
            // SKU exists - just update flag
            $universe[$sku]['in_any_branch_json'] = true;
        }
    }
    
    // 4. Sort the universe: category, product_name, volume, fragrance, sku
    uasort($universe, function($a, $b) {
        // Primary: category
        $catCmp = strcmp($a['category'] ?? '', $b['category'] ?? '');
        if ($catCmp !== 0) return $catCmp;
        
        // Secondary: product_name
        $nameCmp = strcmp($a['product_name'] ?? '', $b['product_name'] ?? '');
        if ($nameCmp !== 0) return $nameCmp;
        
        // Tertiary: volume (numeric sort when possible)
        $volA = preg_replace('/[^0-9]/', '', $a['volume'] ?? '');
        $volB = preg_replace('/[^0-9]/', '', $b['volume'] ?? '');
        if ($volA !== '' && $volB !== '') {
            $volCmp = intval($volA) - intval($volB);
            if ($volCmp !== 0) return $volCmp;
        }
        $volCmp = strcmp($a['volume'] ?? '', $b['volume'] ?? '');
        if ($volCmp !== 0) return $volCmp;
        
        // Quaternary: fragrance
        $fragCmp = strcmp($a['fragrance'] ?? '', $b['fragrance'] ?? '');
        if ($fragCmp !== 0) return $fragCmp;
        
        // Final: SKU
        return strcmp($a['sku'] ?? '', $b['sku'] ?? '');
    });
    
    return $universe;
}

/**
 * Generate all possible SKUs from catalog (products + accessories)
 * 
 * This function generates SKUs ONLY for products that actually exist in the catalog.
 * It respects product definitions and does NOT expand fragrances unless explicitly allowed.
 * 
 * Key Rules:
 * 1. For products with allowed_fragrances field: use ONLY those fragrances
 * 2. For products with fragrance field (limited edition): use ONLY that fragrance
 * 3. For accessories with has_fragrance_selector=false and allowed_fragrances=[single]: generate ONLY one SKU
 * 4. For accessories with no fragrance info: generate one SKU with default fragrance (for SKU generation only)
 * 5. NEVER expand to all global fragrances unless explicitly specified by category
 * 
 * @param array $products Products from products.json
 * @param array $accessories Accessories from accessories.json
 * @param array $fragrances Fragrances from fragrances.json
 * @return array SKU => metadata
 */
function generateCatalogSkus(array $products, array $accessories, array $fragrances): array {
    $catalogSkus = [];
    
    // Process products from products.json
    foreach ($products as $productId => $product) {
        $category = $product['category'] ?? '';
        $variants = getNormalizedProductVariants($product);
        
        // Get product name
        $productName = getProductNameFromId($productId);
        $productFragranceOptions = getProductFragranceOptions($product, $category, $accessories[$productId] ?? null);
        $hasExplicitVariantFragrances = false;
        foreach ($variants as $variant) {
            if ($variant['fragrance'] !== '') {
                $hasExplicitVariantFragrances = true;
                break;
            }
        }
         
        // For limited edition, fragrance is fixed to one specific fragrance
        if ($category === 'limited_edition' || !empty($product['fragrance'])) {
            $fragrance = normalizeVariantFragrance((string)($product['fragrance'] ?? ''));
            foreach ($variants as $variant) {
                $volume = $variant['volume'] ?? '';
                $sku = generateSKU($productId, $volume, $fragrance);
                $catalogSkus[$sku] = [
                    'sku' => $sku,
                    'productId' => $productId,
                    'product_name' => $productName,
                    'category' => $category,
                    'volume' => $volume,
                    'fragrance' => $fragrance
                ];
            }
        }
        elseif ($hasExplicitVariantFragrances) {
            foreach ($variants as $variant) {
                $volume = $variant['volume'] ?? '';
                $fragrance = $variant['fragrance'] ?? '';
                $sku = generateSKU($productId, $volume, $fragrance);
                $catalogSkus[$sku] = [
                    'sku' => $sku,
                    'productId' => $productId,
                    'product_name' => $productName,
                    'category' => $category,
                    'volume' => $volume,
                    'fragrance' => $fragrance === '' ? 'NA' : $fragrance
                ];
            }
        }
        // For accessories in products.json: ALWAYS process from products.json as authoritative
        elseif ($category === 'accessories') {
            $allowedFragrancesInProduct = $product['allowed_fragrances'] ?? null;
            
            if ($allowedFragrancesInProduct !== null && !empty($allowedFragrancesInProduct)) {
                // Product explicitly defines allowed fragrances - use ONLY those
                foreach ($variants as $variant) {
                    $volume = $variant['volume'] ?? '';
                    foreach ($allowedFragrancesInProduct as $fragrance) {
                        $sku = generateSKU($productId, $volume, $fragrance);
                        $catalogSkus[$sku] = [
                            'sku' => $sku,
                            'productId' => $productId,
                            'product_name' => $productName,
                            'category' => $category,
                            'volume' => $volume,
                            'fragrance' => $fragrance
                        ];
                    }
                }
            } else {
                // No allowed_fragrances in products.json
                // Check accessories.json for fragrance info, but mark as processed from products.json
                // to ensure accessories.json doesn't create duplicate/different SKUs later
                $accessoryData = $accessories[$productId] ?? null;
                $accessoryAllowedFragrances = $accessoryData['allowed_fragrances'] ?? [];
                $hasFragranceSelector = $accessoryData['has_fragrance_selector'] ?? false;
                
                if (!empty($accessoryAllowedFragrances)) {
                    // Use fragrances from accessories.json
                    if (!$hasFragranceSelector && count($accessoryAllowedFragrances) === 1) {
                        // Single fragrance, no selector: one SKU only
                        $singleFragrance = $accessoryAllowedFragrances[0];
                        foreach ($variants as $variant) {
                            $volume = $variant['volume'] ?? '';
                            $sku = generateSKU($productId, $volume, $singleFragrance);
                            $catalogSkus[$sku] = [
                                'sku' => $sku,
                                'productId' => $productId,
                                'product_name' => $productName,
                                'category' => $category,
                                'volume' => $volume,
                                'fragrance' => $singleFragrance
                            ];
                        }
                    } else {
                        // Multiple fragrances or selector enabled
                        foreach ($variants as $variant) {
                            $volume = $variant['volume'] ?? '';
                            foreach ($accessoryAllowedFragrances as $fragrance) {
                                $sku = generateSKU($productId, $volume, $fragrance);
                                $catalogSkus[$sku] = [
                                    'sku' => $sku,
                                    'productId' => $productId,
                                    'product_name' => $productName,
                                    'category' => $category,
                                    'volume' => $volume,
                                    'fragrance' => $fragrance
                                ];
                            }
                        }
                    }
                } else {
                    // No fragrance info anywhere - non-fragrance accessory
                    // Use 'NA' (No fragrance) instead of arbitrary default
                    $defaultFragrance = 'NA';
                    foreach ($variants as $variant) {
                        $volume = $variant['volume'] ?? '';
                        $sku = generateSKU($productId, $volume, $defaultFragrance);
                        $catalogSkus[$sku] = [
                            'sku' => $sku,
                            'productId' => $productId,
                            'product_name' => $productName,
                            'category' => $category,
                            'volume' => $volume,
                            'fragrance' => $defaultFragrance
                        ];
                    }
                }
            }
        }
        else {
            // For regular products (not accessories, not limited edition):
            // Check if product has explicit allowed_fragrances override
            $allowedFragrancesInProduct = $product['allowed_fragrances'] ?? null;
            
            if ($allowedFragrancesInProduct !== null && !empty($allowedFragrancesInProduct)) {
                // Product explicitly defines allowed fragrances - use ONLY those
                $productFragrances = $allowedFragrancesInProduct;
            } else {
                // Use category default fragrances
                $productFragrances = $productFragranceOptions;
            }

            if (empty($productFragrances)) {
                foreach ($variants as $variant) {
                    $volume = $variant['volume'] ?? '';
                    $sku = generateSKU($productId, $volume, 'NA');
                    $catalogSkus[$sku] = [
                        'sku' => $sku,
                        'productId' => $productId,
                        'product_name' => $productName,
                        'category' => $category,
                        'volume' => $volume,
                        'fragrance' => 'NA'
                    ];
                }
                continue;
            }
            
            foreach ($variants as $variant) {
                $volume = $variant['volume'] ?? '';
                foreach ($productFragrances as $fragrance) {
                    $sku = generateSKU($productId, $volume, $fragrance);
                    $catalogSkus[$sku] = [
                        'sku' => $sku,
                        'productId' => $productId,
                        'product_name' => $productName,
                        'category' => $category,
                        'volume' => $volume,
                        'fragrance' => $fragrance
                    ];
                }
            }
        }
    }
    
    // Process accessories from accessories.json
    // Only process if NOT already processed from products.json
    foreach ($accessories as $accessoryId => $accessory) {
        $hasFragranceSelector = $accessory['has_fragrance_selector'] ?? false;
        $hasVolumeSelector = $accessory['has_volume_selector'] ?? false;
        $allowedFragrances = $accessory['allowed_fragrances'] ?? [];
        $volumes = $accessory['volumes'] ?? ['standard'];
        
        if (empty($volumes)) {
            $volumes = ['standard'];
        }
        
        $productName = getProductNameFromId($accessoryId);
        
        // Check if this accessory was already processed from products.json
        // by checking if any SKU with this productId already exists
        $alreadyProcessed = false;
        foreach ($catalogSkus as $existingSku => $existingData) {
            if ($existingData['productId'] === $accessoryId) {
                $alreadyProcessed = true;
                break;
            }
        }
        
        if ($alreadyProcessed) {
            // Skip - already processed from products.json with authoritative data
            continue;
        }
        
        // Determine how to generate SKUs based on fragrance configuration
        if (!empty($allowedFragrances)) {
            // Has allowed_fragrances list - use it
            // If has_fragrance_selector is false AND only 1 fragrance, generate only 1 SKU
            // Otherwise generate SKU for each fragrance
            
            if (!$hasFragranceSelector && count($allowedFragrances) === 1) {
                // Single fragrance, no selector: treat as fixed fragrance product
                $singleFragrance = $allowedFragrances[0];
                foreach ($volumes as $volume) {
                    $sku = generateSKU($accessoryId, $volume, $singleFragrance);
                    $catalogSkus[$sku] = [
                        'sku' => $sku,
                        'productId' => $accessoryId,
                        'product_name' => $productName,
                        'category' => 'accessories',
                        'volume' => $volume,
                        'fragrance' => $singleFragrance
                    ];
                }
            } else {
                // Multiple fragrances OR fragrance selector enabled: generate for each
                foreach ($volumes as $volume) {
                    foreach ($allowedFragrances as $fragrance) {
                        $sku = generateSKU($accessoryId, $volume, $fragrance);
                        $catalogSkus[$sku] = [
                            'sku' => $sku,
                            'productId' => $accessoryId,
                            'product_name' => $productName,
                            'category' => 'accessories',
                            'volume' => $volume,
                            'fragrance' => $fragrance
                        ];
                    }
                }
            }
        } else {
            // No allowed_fragrances defined - this is a non-fragrance accessory
            // Use 'NA' (No fragrance) for SKU generation
            $defaultFragrance = 'NA';
            foreach ($volumes as $volume) {
                $sku = generateSKU($accessoryId, $volume, $defaultFragrance);
                $catalogSkus[$sku] = [
                    'sku' => $sku,
                    'productId' => $accessoryId,
                    'product_name' => $productName,
                    'category' => 'accessories',
                    'volume' => $volume,
                    'fragrance' => $defaultFragrance
                ];
            }
        }
    }
    
    return $catalogSkus;
}

/**
 * Get product name from product ID
 * 
 * @param string $productId
 * @return string Human-readable product name
 */
function getProductNameFromId(string $productId): string {
    if (empty($productId)) {
        return 'Unknown Product';
    }
    
    $products = loadJSON('products.json');
    if (isset($products[$productId])) {
        $nameKey = $products[$productId]['name_key'] ?? ('product.' . $productId . '.name');
        return I18N::t($nameKey, ucfirst(str_replace('_', ' ', $productId)));
    }
    
    // Fallback: format the ID nicely
    return ucfirst(str_replace('_', ' ', $productId));
}

/**
 * Get SKU audit report
 * 
 * Returns discrepancy analysis:
 * [
 *   'total_catalog' => 191,
 *   'total_stock' => 216,
 *   'total_branches' => 10,
 *   'total_universe' => 220,
 *   'in_catalog_not_stock' => ['SKU-1', ...],
 *   'in_catalog_not_branches' => ['SKU-2', ...],
 *   'in_stock_not_catalog' => ['SKU-3', ...],
 *   'in_branches_not_catalog' => ['SKU-4', ...]
 * ]
 * 
 * @return array Audit report data
 */
function getSkuAuditReport(): array {
    $universe = loadSkuUniverse();
    
    $inCatalog = [];
    $inStock = [];
    $inBranches = [];
    
    foreach ($universe as $sku => $data) {
        if ($data['in_catalog']) {
            $inCatalog[] = $sku;
        }
        if ($data['in_stock_json']) {
            $inStock[] = $sku;
        }
        if ($data['in_any_branch_json']) {
            $inBranches[] = $sku;
        }
    }
    
    return [
        'total_catalog' => count($inCatalog),
        'total_stock' => count($inStock),
        'total_branches' => count($inBranches),
        'total_universe' => count($universe),
        'in_catalog_not_stock' => array_diff($inCatalog, $inStock),
        'in_catalog_not_branches' => array_diff($inCatalog, $inBranches),
        'in_stock_not_catalog' => array_diff($inStock, $inCatalog),
        'in_branches_not_catalog' => array_diff($inBranches, $inCatalog),
        'universe' => $universe
    ];
}

/**
 * Initialize missing SKU keys in stock files
 * 
 * Adds missing SKUs to stock.json with quantity=0 and refreshes the
 * compatibility branch_stock.json mirror derived from stock.json.
 * ONLY adds missing STOCK keys, NEVER modifies existing stock quantities.
 * 
 * @param bool $dryRun If true, returns what would be changed without saving
 * @return array ['success' => bool, 'added_to_stock' => [...], 'added_to_branches' => [...], 'error' => string]
 */
function initializeMissingSkuKeys(bool $dryRun = true): array {
    $universe = loadSkuUniverse();
    $stock = loadJSON('stock.json');
    $branches = getAllBranches();
    
    $addedToStock = [];
    $addedToBranches = [];
    
    // Find SKUs missing in stock.json
    foreach ($universe as $sku => $data) {
        if (!isset($stock[$sku])) {
            $addedToStock[] = $sku;
            if (!$dryRun) {
                $stock[$sku] = [
                    'productId' => $data['productId'],
                    'volume' => $data['volume'],
                    'fragrance' => $data['fragrance'],
                    'quantity' => 0,
                    'lowStockThreshold' => 3
                ];
            }
        }
    }
    
    // Record branch mirror coverage for compatibility reporting.
    foreach ($universe as $sku => $data) {
        if (!$data['in_catalog'] && !$data['in_stock_json']) {
            continue;
        }

        if (!isset($addedToBranches[$sku])) {
            $addedToBranches[$sku] = [];
        }

        foreach ($branches as $branchId => $branchName) {
            $addedToBranches[$sku][] = $branchId;
        }
    }
    
    // Save changes if not dry run
    if (!$dryRun) {
        // Create backups first
        if (!createStockBackup('stock.json')) {
            return [
                'success' => false,
                'error' => 'Failed to create stock.json backup',
                'added_to_stock' => [],
                'added_to_branches' => []
            ];
        }
        if (!createStockBackup('branch_stock.json')) {
            return [
                'success' => false,
                'error' => 'Failed to create branch_stock.json backup',
                'added_to_stock' => [],
                'added_to_branches' => []
            ];
        }
        
        // Save updated files
        if (!saveJSON('stock.json', $stock)) {
            return [
                'success' => false,
                'error' => 'Failed to save stock.json',
                'added_to_stock' => [],
                'added_to_branches' => []
            ];
        }
        if (!saveBranchStock()) {
            return [
                'success' => false,
                'error' => 'Failed to save branch_stock.json',
                'added_to_stock' => [],
                'added_to_branches' => []
            ];
        }
        
        // Log the initialization
        logStockChange("SKU Universe Initialization: Added " . count($addedToStock) . " SKUs to stock.json and refreshed compatibility branch_stock.json mirror");
    }
    
    return [
        'success' => true,
        'error' => '',
        'added_to_stock' => $addedToStock,
        'added_to_branches' => $addedToBranches
    ];
}

/**
 * Check if a SKU exists in the universe
 * 
 * @param string $sku SKU to check
 * @return bool True if SKU exists
 */
function skuExists(string $sku): bool {
    $universe = loadSkuUniverse();
    return isset($universe[$sku]);
}

/**
 * Generate a unique SKU by adding suffix if collision detected
 * 
 * This function is used when creating admin products to ensure no SKU collisions.
 * If the base SKU already exists, it appends -A, -B, -C, etc. until a unique SKU is found.
 * 
 * @param string $baseSku The desired SKU
 * @return array ['sku' => string, 'collision' => bool, 'original' => string]
 */
function generateUniqueSku(string $baseSku): array {
    $original = $baseSku;
    
    if (!skuExists($baseSku)) {
        // No collision - use base SKU
        return [
            'sku' => $baseSku,
            'collision' => false,
            'original' => $original
        ];
    }
    
    // Collision detected - generate unique variant
    $suffix = 'A';
    $attempts = 0;
    $maxAttempts = 26; // A-Z
    
    while ($attempts < $maxAttempts) {
        $candidateSku = $baseSku . '-' . $suffix;
        
        if (!skuExists($candidateSku)) {
            return [
                'sku' => $candidateSku,
                'collision' => true,
                'original' => $original
            ];
        }
        
        // Try next letter
        $suffix++;
        $attempts++;
    }
    
    // Fallback: use timestamp suffix if all letters exhausted
    $timestamp = date('His');
    return [
        'sku' => $baseSku . '-' . $timestamp,
        'collision' => true,
        'original' => $original
    ];
}

/**
 * Validate that admin-added product won't overwrite catalog SKU
 * 
 * @param string $productId Product ID to add
 * @param string $volume Volume
 * @param string $fragrance Fragrance (or default)
 * @return array ['valid' => bool, 'error' => string, 'suggested_sku' => string]
 */
function validateAdminProductSku(string $productId, string $volume, string $fragrance): array {
    $baseSku = generateSKU($productId, $volume, $fragrance);
    $universe = loadSkuUniverse();
    
    if (!isset($universe[$baseSku])) {
        // No collision
        return [
            'valid' => true,
            'error' => '',
            'sku' => $baseSku
        ];
    }
    
    // SKU exists - check if it's a catalog SKU
    $existingData = $universe[$baseSku];
    
    if ($existingData['in_catalog']) {
        // CRITICAL: Attempting to overwrite catalog SKU
        $uniqueResult = generateUniqueSku($baseSku);
        
        return [
            'valid' => false,
            'error' => "SKU $baseSku already exists as catalog product: {$existingData['product_name']}. Cannot overwrite catalog SKUs.",
            'sku' => $uniqueResult['sku'],
            'collision' => true,
            'existing_product' => $existingData['product_name']
        ];
    } else {
        // SKU exists but not in catalog (orphan or admin-added)
        // Still generate unique to avoid confusion
        $uniqueResult = generateUniqueSku($baseSku);
        
        return [
            'valid' => false,
            'error' => "SKU $baseSku already exists (non-catalog). Using unique SKU instead.",
            'sku' => $uniqueResult['sku'],
            'collision' => true
        ];
    }
}

/**
 * Get SKU Universe diagnostics for debug panel
 * 
 * @return array Comprehensive diagnostic data
 */
function getSkuUniverseDiagnostics(): array {
    $universe = loadSkuUniverse();
    $stock = loadJSON('stock.json');
    $branchStock = loadBranchStock();
    $branches = getAllBranches();
    
    // Collect all SKUs from each source
    $universeSkus = array_keys($universe);
    $stockSkus = array_keys($stock);
    
    $branchSkus = [];
    foreach ($branchStock as $branchId => $skus) {
        foreach (array_keys($skus) as $sku) {
            $branchSkus[$sku] = true;
        }
    }
    $branchSkusList = array_keys($branchSkus);
    
    // Calculate missing and extra SKUs
    $missingInStock = array_diff($universeSkus, $stockSkus);
    $missingInBranchStock = array_diff($universeSkus, $branchSkusList);
    $extraInStock = array_diff($stockSkus, $universeSkus);
    $extraInBranchStock = array_diff($branchSkusList, $universeSkus);
    
    // Detect format violations (non-3-part SKUs)
    $formatViolations = [];
    $naSkuList = [];
    
    foreach ($universeSkus as $sku) {
        // Check 3-part format: PREFIX-VOLUME-FRAGRANCE
        $parts = explode('-', $sku);
        if (count($parts) !== 3) {
            $formatViolations[] = $sku;
        }
        
        // Check for NA fragrance
        if (isset($universe[$sku]['fragrance']) && 
            (strtoupper($universe[$sku]['fragrance']) === 'NA' || $universe[$sku]['fragrance'] === 'NA')) {
            $naSkuList[] = $sku;
        }
    }
    
    // Check stock.json for format violations
    foreach ($stockSkus as $sku) {
        if (!in_array($sku, $universeSkus)) {
            $parts = explode('-', $sku);
            if (count($parts) !== 3 && !in_array($sku, $formatViolations)) {
                $formatViolations[] = $sku;
            }
        }
    }
    
    // Check branch_stock.json for format violations
    foreach ($branchSkusList as $sku) {
        if (!in_array($sku, $universeSkus)) {
            $parts = explode('-', $sku);
            if (count($parts) !== 3 && !in_array($sku, $formatViolations)) {
                $formatViolations[] = $sku;
            }
        }
    }
    
    // Calculate PASS/FAIL status
    $passed = empty($missingInStock) && 
              empty($missingInBranchStock) && 
              empty($formatViolations);
    
    return [
        'universe_count' => count($universeSkus),
        'stock_keys_count' => count($stockSkus),
        'branch_stock_total_keys_count' => count($branchSkusList),
        'branches_count' => count($branches),
        'missing_in_stock_json' => array_values($missingInStock),
        'missing_in_branch_stock_json' => array_values($missingInBranchStock),
        'extra_in_stock_json' => array_values($extraInStock),
        'extra_in_branch_stock_json' => array_values($extraInBranchStock),
        'format_violations' => array_values($formatViolations),
        'na_sku_list' => array_values($naSkuList),
        'passed' => $passed,
        'universe' => $universe
    ];
}
