<?php
/**
 * Admin - Accessories Management
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/stock/sku_universe.php';

if (!isAdminLoggedIn()) {
    header('Location: login.php');
    exit;
}

/**
 * Load accessory names and descriptions from i18n JSON files
 */
function loadAccessoryI18N(string $productId): array {
    $langs = ['en', 'de', 'fr', 'it', 'ru', 'ukr'];
    $result = ['names' => [], 'descriptions' => []];
    foreach ($langs as $lang) {
        $path = __DIR__ . '/../data/i18n/ui_' . $lang . '.json';
        if (!file_exists($path)) continue;
        
        $content = file_get_contents($path);
        if ($content === false) continue;
        
        $data = json_decode($content, true);
        if (!is_array($data)) continue;
        
        if (isset($data['product'][$productId]['name'])) {
            $result['names'][$lang] = $data['product'][$productId]['name'];
        }
        if (isset($data['product'][$productId]['desc'])) {
            $result['descriptions'][$lang] = $data['product'][$productId]['desc'];
        }
    }
    return $result;
}

/**
 * Load accessory descriptions from i18n JSON files
 */
function loadAccessoryDescriptions(string $productId): array {
    $i18n = loadAccessoryI18N($productId);
    return $i18n['descriptions'];
}

/**
 * Save accessory names and descriptions to i18n JSON files
 */
function saveAccessoryI18N(string $slug, array $names, array $descriptions): void {
    $langs = ['en', 'de', 'fr', 'it', 'ru', 'ukr'];

    foreach ($langs as $lang) {
        $path = __DIR__ . '/../data/i18n/ui_' . $lang . '.json';
        if (!file_exists($path)) {
            continue;
        }

        $data = json_decode(file_get_contents($path), true);
        if (!is_array($data)) {
            $data = [];
        }
        if (!isset($data['product']) || !is_array($data['product'])) {
            $data['product'] = [];
        }
        if (!isset($data['product'][$slug]) || !is_array($data['product'][$slug])) {
            $data['product'][$slug] = [];
        }

        $name = $names[$lang] ?? '';
        $desc = $descriptions[$lang] ?? '';

        if ($name !== '') {
            $data['product'][$slug]['name'] = $name;
        }
        if ($desc !== '') {
            $data['product'][$slug]['desc'] = $desc;
        }

        file_put_contents(
            $path,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }
}

/**
 * Sync accessory to products.json for product.php
 */
function syncAccessoryToProducts(string $slug, string $category, string $nameKey, string $descKey, array $images, float $priceCHF, bool $hasVolumeSelector = false, array $volumes = [], array $volumePrices = []): void {
    $path = __DIR__ . '/../data/products.json';
    $products = [];

    if (file_exists($path)) {
        $products = json_decode(file_get_contents($path), true);
        if (!is_array($products)) {
            $products = [];
        }
    }

    $mainImage = !empty($images) ? $images[0] : '';

    // Build variants based on volume selector
    $variants = [];
    if ($hasVolumeSelector && !empty($volumes)) {
        // Multi-volume: create variant for each volume with its price
        foreach ($volumes as $vol) {
            $price = $volumePrices[$vol] ?? 0.0;
            $variants[] = [
                'volume' => $vol,
                'priceCHF' => (float)$price
            ];
        }
    } else {
        // Single volume: standard variant with base price
        $variants[] = [
            'volume' => 'standard',
            'priceCHF' => (float)$priceCHF
        ];
    }

    // Build product entry - DO NOT include priceCHF directly for accessories
    // Only variants[] contains pricing information
    $products[$slug] = [
        'id' => $slug,
        'category' => $category,
        'name_key' => $nameKey,
        'desc_key' => $descKey,
        'image' => $mainImage,
        'variants' => $variants,
        'active' => true
    ];

    file_put_contents(
        $path,
        json_encode($products, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );
}

// PHASE 1 FIX: Load from products.json as canonical source
// accessories.json contains only fragrance/volume configuration
$products = loadJSON('products.json');
$accessories = loadJSON('accessories.json');
$fragrances = loadJSON('fragrances.json');

// Build complete accessories list: products with category="accessories"
// Each accessory can have config in accessories.json or be "orphan" (no config yet)
$accessoriesList = [];
foreach ($products as $productId => $product) {
    if (($product['category'] ?? '') === 'accessories') {
        $hasConfig = isset($accessories[$productId]);
        $accessoriesList[$productId] = [
            'id' => $productId,
            'name_key' => $product['name_key'] ?? 'product.' . $productId . '.name',
            'desc_key' => $product['desc_key'] ?? 'product.' . $productId . '.desc',
            'images' => !empty($product['image']) ? [$product['image']] : [],
            'priceCHF' => 0, // Will be calculated from variants
            'active' => $product['active'] ?? true,
            'has_config' => $hasConfig,
            'is_orphan' => !$hasConfig,
            // Merge with accessories.json config if exists
            'has_fragrance_selector' => $hasConfig ? ($accessories[$productId]['has_fragrance_selector'] ?? false) : false,
            'allowed_fragrances' => $hasConfig ? ($accessories[$productId]['allowed_fragrances'] ?? []) : [],
            'has_volume_selector' => $hasConfig ? ($accessories[$productId]['has_volume_selector'] ?? false) : false,
            'volumes' => $hasConfig ? ($accessories[$productId]['volumes'] ?? []) : [],
            'volume_prices' => $hasConfig ? ($accessories[$productId]['volume_prices'] ?? []) : []
        ];
        
        // Calculate base price from first variant
        if (!empty($product['variants'])) {
            $accessoriesList[$productId]['priceCHF'] = $product['variants'][0]['priceCHF'] ?? 0;
        }
        
        // Merge images from accessories.json if available
        if ($hasConfig && !empty($accessories[$productId]['images'])) {
            $accessoriesList[$productId]['images'] = $accessories[$productId]['images'];
        }
    }
}

// Get all fragrances except excluded ones (new_york, abu_dhabi, palermo)
$fragranceCodes = array_keys($fragrances);
$excluded = ['new_york', 'abu_dhabi', 'palermo'];
$availableFragrances = array_values(array_diff($fragranceCodes, $excluded));

$success = '';
$error = '';
$editingId = $_GET['edit'] ?? '';
$editingItem = $editingId && isset($accessoriesList[$editingId]) ? $accessoriesList[$editingId] : null;
$currentNames = [];
$currentDescriptions = [];
$currentVolumes = [];
$currentVolumePrices = [];
if ($editingId) {
    $i18n = loadAccessoryI18N($editingId);
    $currentNames = $i18n['names'];
    $currentDescriptions = $i18n['descriptions'];
    $currentVolumes = $editingItem['volumes'] ?? [];
    $currentVolumePrices = $editingItem['volume_prices'] ?? [];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Action: Create accessory config for orphan
    if ($_POST['action'] === 'create_config') {
        $id = trim($_POST['id'] ?? '');
        if (empty($id)) {
            $error = 'Product ID is required.';
        } elseif (!isset($products[$id]) || ($products[$id]['category'] ?? '') !== 'accessories') {
            $error = 'Product not found or is not an accessory.';
        } else {
            // Create minimal config in accessories.json
            $accessories[$id] = [
                'id' => $id,
                'name_key' => 'product.' . $id . '.name',
                'desc_key' => 'product.' . $id . '.desc',
                'images' => !empty($products[$id]['image']) ? [$products[$id]['image']] : [],
                'priceCHF' => !empty($products[$id]['variants']) ? ($products[$id]['variants'][0]['priceCHF'] ?? 0) : 0,
                'active' => true,
                'has_fragrance_selector' => false,
                'allowed_fragrances' => [],
                'has_volume_selector' => false,
                'volumes' => [],
                'volume_prices' => []
            ];
            
            if (saveJSON('accessories.json', $accessories)) {
                $success = "Accessory config created for '$id'. You can now edit it below.";
                // Reload to show in list
                $accessories = loadJSON('accessories.json');
            } else {
                $error = 'Failed to save accessories.json';
            }
        }
    }
    
    // Action: Delete accessory permanently
    elseif ($_POST['action'] === 'delete_accessory') {
        $id = trim($_POST['id'] ?? '');
        if (empty($id)) {
            $error = 'Product ID is required for deletion.';
        } else {
            // Use shared delete function from helpers.php
            $result = deleteProduct($id);
            
            if ($result['success']) {
                $details = $result['details'];
                $success = "Product '$id' deleted successfully! ";
                $success .= "Removed from products.json, ";
                if ($details['accessory_removed']) {
                    $success .= "accessories.json, ";
                }
                $success .= count($details['stock_skus_removed']) . " SKUs from stock.json, ";
                $success .= count($details['branch_skus_removed']) . " entries from branch_stock.json, ";
                $success .= "and " . count($details['i18n_keys_removed']) . " i18n keys.";
                
                // Reload data
                $products = loadJSON('products.json');
                $accessories = loadJSON('accessories.json');
            } else {
                $error = "Failed to delete product: " . $result['error'];
            }
        }
    }
    
    // Action: Save accessory
    elseif ($_POST['action'] === 'save_accessory') {
        $id = trim($_POST['id'] ?? '');
        $priceCHF = floatval($_POST['priceCHF'] ?? 0);
        $active = isset($_POST['active']) ? true : false;
        
        // Auto-generate name_key and desc_key from slug
        $nameKey = 'product.' . $id . '.name';
        $descKey = 'product.' . $id . '.desc';
        
        // Validate ID (only lowercase letters, numbers, underscores)
        if (!preg_match('/^[a-z0-9_]+$/', $id)) {
            $error = 'ID must contain only lowercase letters, numbers, and underscores.';
        } else {
            // Collect names from all language inputs
            $names = [
                'en'  => trim($_POST['name_en'] ?? ''),
                'de'  => trim($_POST['name_de'] ?? ''),
                'fr'  => trim($_POST['name_fr'] ?? ''),
                'it'  => trim($_POST['name_it'] ?? ''),
                'ru'  => trim($_POST['name_ru'] ?? ''),
                'ukr' => trim($_POST['name_ukr'] ?? ''),
            ];
            
            // Collect descriptions from all language inputs
            $descriptions = [
                'en'  => trim($_POST['description_en'] ?? ''),
                'de'  => trim($_POST['description_de'] ?? ''),
                'fr'  => trim($_POST['description_fr'] ?? ''),
                'it'  => trim($_POST['description_it'] ?? ''),
                'ru'  => trim($_POST['description_ru'] ?? ''),
                'ukr' => trim($_POST['description_ukr'] ?? ''),
            ];
            
            // Process images - can be textarea with one per line or multiple inputs
            $images = [];
            if (!empty($_POST['images'])) {
                if (is_array($_POST['images'])) {
                    // Multiple text inputs
                    foreach ($_POST['images'] as $img) {
                        $img = trim($img);
                        if ($img !== '') {
                            $images[] = $img;
                        }
                    }
                } else {
                    // Textarea - split by newlines
                    $lines = explode("\n", $_POST['images']);
                    foreach ($lines as $line) {
                        $line = trim($line);
                        if ($line !== '') {
                            $images[] = $line;
                        }
                    }
                }
            }
            
            // Process fragrance selector (optional)
            $hasFragranceSelector = !empty($_POST['enable_fragrance_selector']);
            
            // Process allowed_fragrances
            $allowed_fragrances = $_POST['allowed_fragrances'] ?? [];
            if (!is_array($allowed_fragrances)) {
                $allowed_fragrances = [];
            }
            // If fragrance selector is disabled, clear fragrances
            if (!$hasFragranceSelector) {
                $allowed_fragrances = [];
            }
            
            // Process volume selector (optional)
            $hasVolumeSelector = !empty($_POST['enable_volume_selector']);
            $volumes = isset($_POST['volumes']) && is_array($_POST['volumes']) 
                ? array_values(array_unique(array_filter($_POST['volumes']))) 
                : [];
            
            // Process volume prices
            $volumePricesPost = $_POST['volume_prices'] ?? [];
            $volumePrices = [];
            if ($hasVolumeSelector && !empty($volumes)) {
                foreach ($volumes as $vol) {
                    $raw = $volumePricesPost[$vol] ?? '';
                    $volumePrices[$vol] = $raw !== '' ? (float)$raw : 0.0;
                }
            }
            
            // Validation
            if (empty($images)) {
                $error = 'At least one image is required.';
            } elseif ($hasVolumeSelector && empty($volumes)) {
                $error = 'When volume selector is enabled, you must select at least one volume.';
            } elseif ($hasVolumeSelector && !empty($volumes)) {
                // Validate that all volume prices are filled
                $missingPrices = false;
                foreach ($volumes as $vol) {
                    if (!isset($volumePrices[$vol]) || $volumePrices[$vol] <= 0) {
                        $missingPrices = true;
                        break;
                    }
                }
                if ($missingPrices) {
                    $error = 'When volume selector is enabled, all volume prices must be greater than 0.';
                }
            } elseif ($hasFragranceSelector && empty($allowed_fragrances)) {
                // Only require fragrances when fragrance selector is enabled
                $error = 'When fragrance selector is enabled, you must select at least one fragrance.';
            }
            
            // SKU Collision Check - CRITICAL SAFETY CHECK
            // Check if this is a NEW accessory (not editing existing)
            $isNewAccessory = !isset($accessories[$id]);
            
            if (!$error && $isNewAccessory) {
                // Determine what SKUs would be generated for this accessory
                $testVolumes = $hasVolumeSelector && !empty($volumes) ? $volumes : ['standard'];
                $testFragrances = [];
                
                if ($hasFragranceSelector && !empty($allowed_fragrances)) {
                    $testFragrances = $allowed_fragrances;
                } else {
                    // PHASE 2 FIX: No fragrance selector - use NA, not cherry_blossom
                    // This prevents false collision warnings for non-fragrance devices
                    $testFragrances = ['NA'];
                }
                
                // Check each potential SKU for collision using canonical Universe validation
                $collisions = [];
                foreach ($testVolumes as $vol) {
                    foreach ($testFragrances as $frag) {
                        $validation = validateAdminProductSku($id, $vol, $frag);
                        if (!$validation['valid']) {
                            $collisions[] = [
                                'sku' => $validation['sku'],
                                'error' => $validation['error'],
                                'existing_product' => $validation['existing_product'] ?? 'Unknown'
                            ];
                        }
                    }
                }
                
                if (!empty($collisions)) {
                    // SKU collision detected
                    $error = "⚠️ <strong>SKU Collision Detected!</strong><br><br>";
                    $error .= "The following SKUs would be generated for this accessory, but they already exist in the catalog:<br><ul>";
                    foreach (array_slice($collisions, 0, 5) as $collision) {
                        $error .= "<li><code>{$collision['sku']}</code> - conflicts with: <strong>{$collision['existing_product']}</strong></li>";
                    }
                    if (count($collisions) > 5) {
                        $error .= "<li>... and " . (count($collisions) - 5) . " more collisions</li>";
                    }
                    $error .= "</ul>";
                    $error .= "<br><strong>Action Required:</strong> Please choose a different product ID (slug) to avoid overwriting existing catalog products.";
                }
            }
            
            if (!$error) {
                // Create or update accessory
                // Note: When volume selector is enabled, priceCHF is not used
                $accessories[$id] = [
                    'id' => $id,
                    'name_key' => $nameKey,
                    'desc_key' => $descKey,
                    'images' => $images,
                    'priceCHF' => $hasVolumeSelector ? 0 : $priceCHF, // Set to 0 when volume selector is enabled
                    'active' => $active,
                    'has_fragrance_selector' => $hasFragranceSelector,
                    'allowed_fragrances' => $allowed_fragrances,
                    'has_volume_selector' => $hasVolumeSelector,
                    'volumes' => $hasVolumeSelector ? $volumes : [],
                    'volume_prices' => $hasVolumeSelector ? $volumePrices : []
                ];
                
                if (saveJSON('accessories.json', $accessories)) {
                    // Save names and descriptions to i18n files
                    saveAccessoryI18N($id, $names, $descriptions);
                    
                    // Sync to products.json with volume pricing
                    syncAccessoryToProducts($id, 'accessories', $nameKey, $descKey, $images, $priceCHF, $hasVolumeSelector, $volumes, $volumePrices);
                    
                    // Update catalog version for cache busting
                    updateCatalogVersion();
                    
                    // Auto-initialize stock for new accessory
                    // This ensures the new accessory appears immediately in stock lists
                    $syncResult = initializeMissingSkuKeys(false);
                    
                    if ($syncResult['success']) {
                        $addedStockCount = count($syncResult['added_to_stock']);
                        $addedBranchCount = count($syncResult['added_to_branches']);
                        
                        $catalogVersion = getCatalogVersion();
                        $success = 'Accessory saved successfully! Catalog version: ' . $catalogVersion . '. ';
                        if ($addedStockCount > 0 || $addedBranchCount > 0) {
                            $success .= "New SKUs initialized in stock ({$addedStockCount} in stock.json, {$addedBranchCount} in branch_stock.json).";
                        }
                    } else {
                        $success = 'Accessory saved successfully! (Note: Stock sync failed - run manual sync from Stock page)';
                    }
                    
                    $editingId = '';
                    $editingItem = null;
                    $currentNames = [];
                    $currentDescriptions = [];
                    // Reload data
                    $products = loadJSON('products.json');
                    $accessories = loadJSON('accessories.json');
                } else {
                    $error = 'Failed to save accessories.json file.';
                }
            }
        }
    }
}

// Rebuild accessoriesList after any data modifications
$accessoriesList = [];
foreach ($products as $productId => $product) {
    if (($product['category'] ?? '') === 'accessories') {
        $hasConfig = isset($accessories[$productId]);
        $accessoriesList[$productId] = [
            'id' => $productId,
            'name_key' => $product['name_key'] ?? 'product.' . $productId . '.name',
            'desc_key' => $product['desc_key'] ?? 'product.' . $productId . '.desc',
            'images' => !empty($product['image']) ? [$product['image']] : [],
            'priceCHF' => 0,
            'active' => $product['active'] ?? true,
            'has_config' => $hasConfig,
            'is_orphan' => !$hasConfig,
            'has_fragrance_selector' => $hasConfig ? ($accessories[$productId]['has_fragrance_selector'] ?? false) : false,
            'allowed_fragrances' => $hasConfig ? ($accessories[$productId]['allowed_fragrances'] ?? []) : [],
            'has_volume_selector' => $hasConfig ? ($accessories[$productId]['has_volume_selector'] ?? false) : false,
            'volumes' => $hasConfig ? ($accessories[$productId]['volumes'] ?? []) : [],
            'volume_prices' => $hasConfig ? ($accessories[$productId]['volume_prices'] ?? []) : []
        ];
        
        if (!empty($product['variants'])) {
            $accessoriesList[$productId]['priceCHF'] = $product['variants'][0]['priceCHF'] ?? 0;
        }
        
        if ($hasConfig && !empty($accessories[$productId]['images'])) {
            $accessoriesList[$productId]['images'] = $accessories[$productId]['images'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accessories - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600&family=Manrope:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; }
        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.95rem;
        }
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        .form-group select[multiple] {
            min-height: 200px;
        }
        .form-group .help-text {
            font-size: 0.85rem;
            color: #666;
            margin-top: 0.25rem;
        }
        .image-inputs .image-input-row {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }
        .image-inputs .image-input-row input {
            flex: 1;
        }
        .btn-add-image {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            background: var(--color-sand);
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
        }
        .alert { padding: 1rem; margin-bottom: 1rem; border-radius: 4px; }
        .alert--success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert--error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
    <div class="admin-layout">
        <aside class="admin-sidebar">
            <div class="admin-sidebar__logo">NicheHome Admin</div>
                        <nav class="admin-sidebar__nav">
                <a href="index.php" class="admin-sidebar__link">Dashboard</a>
                <a href="products.php" class="admin-sidebar__link">Products</a>
                <a href="admin_products.php" class="admin-sidebar__link">Products (Enhanced)</a>
                <a href="accessories.php" class="admin-sidebar__link active">Accessories</a>
                <a href="fragrances.php" class="admin-sidebar__link">Fragrances</a>
                <a href="categories.php" class="admin-sidebar__link">Categories</a>
                <a href="stock.php" class="admin-sidebar__link">Stock</a>
                <a href="stock_import.php" class="admin-sidebar__link">Stock Import</a>
                <a href="sku_audit.php" class="admin-sidebar__link">SKU Audit</a>
                <a href="orders.php" class="admin-sidebar__link">Orders</a>
                <a href="admin_orders.php" class="admin-sidebar__link">Orders (Enhanced)</a>
                <a href="shipping.php" class="admin-sidebar__link">Shipping</a>
                <a href="branches.php" class="admin-sidebar__link">Branches</a>
                <a href="admin_users.php" class="admin-sidebar__link">User Management</a>
                <?php if (canManageUsers()): // MENU FIX: Only full_access role can manage admins ?>
                <a href="manage_admins.php" class="admin-sidebar__link">Admin Management</a>
                <?php endif; ?>
                <a href="diagnostics.php" class="admin-sidebar__link">Diagnostics</a>
                <a href="notifications.php" class="admin-sidebar__link">Notifications</a>
                <a href="email.php" class="admin-sidebar__link">Email Settings</a>
                <a href="logout.php" class="admin-sidebar__link">Logout</a>
            </nav>
        </aside>
        
        <main class="admin-content">
            <div class="admin-header">
                <h1>Accessories Management</h1>
            </div>
            
            <?php if ($success): ?>
                <div class="alert alert--success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert--error"><?php echo $error; // Allow HTML in error messages for SKU collision details ?></div>
            <?php endif; ?>
            
            <!-- Accessories Table -->
            <div class="admin-card">
                <h2>All Accessories</h2>
                <p style="color: #666; margin-bottom: 1rem;">
                    Showing products from products.json with category="accessories". 
                    "Orphan" indicates products without configuration in accessories.json.
                </p>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Status</th>
                            <th>Images</th>
                            <th>Price</th>
                            <th>Active</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($accessoriesList)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 2rem; color: #999;">
                                    No accessories found. Create one using the form below.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($accessoriesList as $id => $item): ?>
                                <?php 
                                $itemName = I18N::t($item['name_key'] ?? '', $id);
                                ?>
                                <tr style="<?php echo $item['is_orphan'] ? 'background: #fff3cd;' : ''; ?>">
                                    <td><?php echo htmlspecialchars($id); ?></td>
                                    <td><?php echo htmlspecialchars($itemName); ?></td>
                                    <td>
                                        <?php if ($item['is_orphan']): ?>
                                            <span style="background: #ffc107; color: #000; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.85rem; font-weight: 600;">
                                                ⚠️ Orphan (no config)
                                            </span>
                                        <?php else: ?>
                                            <span style="background: #28a745; color: #fff; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.85rem;">
                                                ✓ Configured
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($item['images'])): ?>
                                            <?php foreach ($item['images'] as $img): ?>
                                                <span style="display: inline-block; background: var(--color-sand); padding: 0.25rem 0.5rem; border-radius: 4px; margin: 0.1rem; font-size: 0.85rem;">
                                                    <?php echo htmlspecialchars($img); ?>
                                                </span>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>CHF <?php echo number_format($item['priceCHF'] ?? 0, 2); ?></td>
                                    <td><?php echo ($item['active'] ?? false) ? 'Yes' : 'No'; ?></td>
                                    <td>
                                        <?php if ($item['is_orphan']): ?>
                                            <form method="post" action="" style="display: inline;">
                                                <input type="hidden" name="action" value="create_config">
                                                <input type="hidden" name="id" value="<?php echo htmlspecialchars($id); ?>">
                                                <button type="submit" class="btn btn--text" style="color: #ffc107;">
                                                    ➕ Create Config
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <a href="?edit=<?php echo urlencode($id); ?>" class="btn btn--text">Edit</a>
                                        <?php endif; ?>
                                        <form method="post" action="" style="display: inline;" onsubmit="return confirm('⚠️ PERMANENT DELETION\n\nThis will delete:\n- Product from products.json\n- Accessory config from accessories.json\n- All SKUs from stock.json\n- All branch stock entries\n- All i18n translations\n\nBackups will be created.\n\nAre you sure?');">
                                            <input type="hidden" name="action" value="delete_accessory">
                                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($id); ?>">
                                            <button type="submit" class="btn btn--text" style="color: var(--color-error);">
                                                🗑️ Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Add/Edit Form -->
            <div class="admin-card" style="margin-top: 2rem;">
                <h2><?php echo $editingItem ? 'Edit Accessory' : 'Add New Accessory'; ?></h2>
                <form method="post" action="">
                    <input type="hidden" name="action" value="save_accessory">
                    
                    <div class="form-group">
                        <label for="id">ID (slug) *</label>
                        <input type="text" 
                               id="id" 
                               name="id" 
                               required 
                               pattern="[a-z0-9_]+"
                               value="<?php echo $editingItem ? htmlspecialchars($editingItem['id']) : ''; ?>"
                               <?php echo $editingItem ? 'readonly' : ''; ?>>
                        <div class="help-text">Only lowercase letters, numbers, and underscores. Cannot be changed after creation.</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="name_key">Name Key *</label>
                        <input type="text" 
                               id="name_key" 
                               name="name_key" 
                               readonly
                               value="<?php 
                                   $displaySlug = $editingItem ? $editingItem['id'] : '[slug]';
                                   echo htmlspecialchars('product.' . $displaySlug . '.name'); 
                               ?>">
                        <div class="help-text">Auto-generated as product.&lt;slug&gt;.name</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="desc_key">Description Key *</label>
                        <input type="text" 
                               id="desc_key" 
                               name="desc_key" 
                               readonly
                               value="<?php 
                                   $displaySlug = $editingItem ? $editingItem['id'] : '[slug]';
                                   echo htmlspecialchars('product.' . $displaySlug . '.desc'); 
                               ?>">
                        <div class="help-text">Auto-generated as product.&lt;slug&gt;.desc</div>
                    </div>
                    
                    <hr style="margin: 2rem 0; border: none; border-top: 1px solid #ddd;">
                    <h3 style="margin-bottom: 1rem;">Product Names (by Language)</h3>
                    
                    <!-- Name Fields for all languages -->
                    <div class="form-group">
                        <label for="name_en">Name (EN) *</label>
                        <input type="text" 
                               id="name_en" 
                               name="name_en" 
                               required
                               value="<?php echo htmlspecialchars($currentNames['en'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="name_de">Name (DE)</label>
                        <input type="text" 
                               id="name_de" 
                               name="name_de" 
                               value="<?php echo htmlspecialchars($currentNames['de'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="name_fr">Name (FR)</label>
                        <input type="text" 
                               id="name_fr" 
                               name="name_fr" 
                               value="<?php echo htmlspecialchars($currentNames['fr'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="name_it">Name (IT)</label>
                        <input type="text" 
                               id="name_it" 
                               name="name_it" 
                               value="<?php echo htmlspecialchars($currentNames['it'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="name_ru">Name (RU)</label>
                        <input type="text" 
                               id="name_ru" 
                               name="name_ru" 
                               value="<?php echo htmlspecialchars($currentNames['ru'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="name_ukr">Name (UKR)</label>
                        <input type="text" 
                               id="name_ukr" 
                               name="name_ukr" 
                               value="<?php echo htmlspecialchars($currentNames['ukr'] ?? ''); ?>">
                    </div>
                    
                    <hr style="margin: 2rem 0; border: none; border-top: 1px solid #ddd;">
                    <h3 style="margin-bottom: 1rem;">Product Descriptions (by Language)</h3>
                    
                    <!-- Description Fields for all languages -->
                    <div class="form-group">
                        <label for="description_en">Description (EN)</label>
                        <textarea id="description_en" 
                                  name="description_en" 
                                  rows="4"><?php echo htmlspecialchars($currentDescriptions['en'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="description_de">Description (DE)</label>
                        <textarea id="description_de" 
                                  name="description_de" 
                                  rows="4"><?php echo htmlspecialchars($currentDescriptions['de'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="description_fr">Description (FR)</label>
                        <textarea id="description_fr" 
                                  name="description_fr" 
                                  rows="4"><?php echo htmlspecialchars($currentDescriptions['fr'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="description_it">Description (IT)</label>
                        <textarea id="description_it" 
                                  name="description_it" 
                                  rows="4"><?php echo htmlspecialchars($currentDescriptions['it'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="description_ru">Description (RU)</label>
                        <textarea id="description_ru" 
                                  name="description_ru" 
                                  rows="4"><?php echo htmlspecialchars($currentDescriptions['ru'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="description_ukr">Description (UKR)</label>
                        <textarea id="description_ukr" 
                                  name="description_ukr" 
                                  rows="4"><?php echo htmlspecialchars($currentDescriptions['ukr'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="images">Images *</label>
                        <textarea id="images" 
                                  name="images" 
                                  required><?php 
                            if ($editingItem && !empty($editingItem['images'])) {
                                echo htmlspecialchars(implode("\n", $editingItem['images']));
                            }
                        ?></textarea>
                        <div class="help-text">Enter one image filename per line (e.g., 2-Sashe.jpg). First image will be the main image.</div>
                    </div>
                    
                    <div class="form-group" id="price-chf-container">
                        <label for="priceCHF">Price (CHF) <span id="price-required-indicator">*</span></label>
                        <input type="number" 
                               id="priceCHF" 
                               name="priceCHF" 
                               step="0.01" 
                               min="0" 
                               value="<?php echo $editingItem ? htmlspecialchars($editingItem['priceCHF']) : '0.00'; ?>">
                        <div class="help-text">Only used when volume selector is disabled. Ignored when volume selector is enabled.</div>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" 
                                   id="enable-fragrance-selector"
                                   name="enable_fragrance_selector" 
                                   value="1" 
                                   <?php echo !empty($editingItem['has_fragrance_selector']) ? 'checked' : ''; ?>>
                            Enable fragrance selector for this product
                        </label>
                        <div class="help-text">When disabled, customers will not be able to select fragrances for this accessory and no fragrance will be required.</div>
                    </div>
                    
                    <div class="form-group" id="fragrances-container">
                        <label for="allowed_fragrances">Allowed Fragrances <span id="fragrances-required-indicator">*</span></label>
                        <select id="allowed_fragrances" 
                                name="allowed_fragrances[]" 
                                multiple>
                            <?php foreach ($availableFragrances as $fragCode): ?>
                                <?php
                                $fragName = I18N::t('fragrance.' . $fragCode . '.name', ucfirst(str_replace('_', ' ', $fragCode)));
                                $isSelected = $editingItem && in_array($fragCode, $editingItem['allowed_fragrances'] ?? []);
                                ?>
                                <option value="<?php echo htmlspecialchars($fragCode); ?>" <?php echo $isSelected ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($fragName); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="help-text">Hold Ctrl/Cmd to select multiple. Excluded: new_york, abu_dhabi, palermo.</div>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" 
                                   id="enable-volume-selector"
                                   name="enable_volume_selector" 
                                   value="1" 
                                   <?php echo !empty($editingItem['has_volume_selector']) ? 'checked' : ''; ?>>
                            Enable volume selector for this product
                        </label>
                        <div class="help-text">When enabled, Price (CHF) is ignored and volume-specific prices are used instead.</div>
                    </div>
                    
                    <div class="form-group">
                        <label>Available Volumes / Formats</label>
                        <select name="volumes[]" multiple size="3" id="volumes-selector">
                            <option value="125ml" <?php echo in_array('125ml', $currentVolumes) ? 'selected' : ''; ?>>125ml</option>
                            <option value="5 guggul + 5 louban" <?php echo in_array('5 guggul + 5 louban', $currentVolumes) ? 'selected' : ''; ?>>5 guggul + 5 louban</option>
                            <option value="10 guggul + 10 louban" <?php echo in_array('10 guggul + 10 louban', $currentVolumes) ? 'selected' : ''; ?>>10 guggul + 10 louban</option>
                        </select>
                        <small class="help-text">Optional. Leave empty if product does not require volume selector.</small>
                    </div>
                    
                    <div class="form-group" id="volume-prices-container" style="<?php echo (!empty($currentVolumes) && !empty($editingItem['has_volume_selector'])) ? '' : 'display: none;'; ?>">
                        <label>Volume Prices (CHF)</label>
                        <div id="volume-prices-fields">
                            <?php if (!empty($currentVolumes)): ?>
                                <?php foreach ($currentVolumes as $vol): ?>
                                    <div class="volume-price-row" style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem; align-items: center;">
                                        <span style="min-width: 200px; font-weight: 500;"><?php echo htmlspecialchars($vol); ?></span>
                                        <input type="number" 
                                               name="volume_prices[<?php echo htmlspecialchars($vol); ?>]" 
                                               value="<?php echo htmlspecialchars($currentVolumePrices[$vol] ?? '0.00'); ?>"
                                               placeholder="Price CHF"
                                               step="0.01"
                                               min="0"
                                               style="flex: 1; max-width: 150px;">
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <small class="help-text">Set individual prices for each selected volume.</small>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" 
                                   name="active" 
                                   <?php echo ($editingItem && ($editingItem['active'] ?? false)) || !$editingItem ? 'checked' : ''; ?>>
                            Active (visible on website)
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn--gold">Save Accessory</button>
                        <?php if ($editingItem): ?>
                            <a href="accessories.php" class="btn" style="margin-left: 1rem;">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </main>
    </div>
    
    <script>
    // Auto-update name_key and desc_key when ID changes
    document.addEventListener('DOMContentLoaded', function() {
        const idInput = document.getElementById('id');
        const nameKeyInput = document.getElementById('name_key');
        const descKeyInput = document.getElementById('desc_key');
        
        if (idInput && !idInput.hasAttribute('readonly')) {
            idInput.addEventListener('input', function() {
                const slug = this.value.toLowerCase().replace(/[^a-z0-9_]/g, '');
                if (slug) {
                    nameKeyInput.value = 'product.' + slug + '.name';
                    descKeyInput.value = 'product.' + slug + '.desc';
                } else {
                    nameKeyInput.value = 'product.[slug].name';
                    descKeyInput.value = 'product.[slug].desc';
                }
            });
        }
        
        // Handle fragrance selector toggle
        const enableFragranceCheckbox = document.querySelector('input[name="enable_fragrance_selector"]');
        const fragrancesContainer = document.getElementById('fragrances-container');
        const fragrancesSelect = document.getElementById('allowed_fragrances');
        const fragrancesRequiredIndicator = document.getElementById('fragrances-required-indicator');
        
        function updateFragranceFields() {
            const isEnabled = enableFragranceCheckbox && enableFragranceCheckbox.checked;
            
            if (isEnabled) {
                // Fragrance selector enabled: show selector, make required
                fragrancesSelect.setAttribute('required', 'required');
                fragrancesSelect.disabled = false;
                fragrancesContainer.style.opacity = '1';
                fragrancesRequiredIndicator.style.display = '';
            } else {
                // Fragrance selector disabled: hide selector, not required
                fragrancesSelect.removeAttribute('required');
                fragrancesSelect.disabled = true;
                fragrancesContainer.style.opacity = '0.5';
                fragrancesRequiredIndicator.style.display = 'none';
            }
        }
        
        if (enableFragranceCheckbox) {
            enableFragranceCheckbox.addEventListener('change', updateFragranceFields);
            // Initialize on page load
            updateFragranceFields();
        }
        
        // Handle volume selector and volume prices
        const enableVolumeCheckbox = document.querySelector('input[name="enable_volume_selector"]');
        const volumesSelector = document.getElementById('volumes-selector');
        const volumePricesContainer = document.getElementById('volume-prices-container');
        const volumePricesFields = document.getElementById('volume-prices-fields');
        const priceChfContainer = document.getElementById('price-chf-container');
        const priceChfInput = document.getElementById('priceCHF');
        const priceRequiredIndicator = document.getElementById('price-required-indicator');
        
        // Store existing prices
        let existingPrices = {};
        
        function updateVolumePricesFields() {
            const isEnabled = enableVolumeCheckbox && enableVolumeCheckbox.checked;
            const selectedVolumes = Array.from(volumesSelector.selectedOptions).map(opt => opt.value);
            
            // Update Price (CHF) field visibility and requirement
            if (isEnabled && selectedVolumes.length > 0) {
                // Volume selector enabled: hide/disable Price (CHF), show volume prices
                priceChfInput.removeAttribute('required');
                priceChfInput.disabled = true;
                priceChfContainer.style.opacity = '0.5';
                priceRequiredIndicator.style.display = 'none';
                
                volumePricesContainer.style.display = '';
                
                // Build the fields HTML
                let html = '';
                selectedVolumes.forEach(function(vol) {
                    const price = escapeHtml(existingPrices[vol] || '0.00');
                    html += '<div class="volume-price-row" style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem; align-items: center;">';
                    html += '<span style="min-width: 200px; font-weight: 500;">' + escapeHtml(vol) + '</span>';
                    html += '<input type="number" name="volume_prices[' + escapeHtml(vol) + ']" value="' + price + '" placeholder="Price CHF" step="0.01" min="0" required style="flex: 1; max-width: 150px;">';
                    html += '</div>';
                });
                volumePricesFields.innerHTML = html;
            } else {
                // Volume selector disabled: show Price (CHF), hide volume prices
                priceChfInput.setAttribute('required', 'required');
                priceChfInput.disabled = false;
                priceChfContainer.style.opacity = '1';
                priceRequiredIndicator.style.display = '';
                
                volumePricesContainer.style.display = 'none';
                volumePricesFields.innerHTML = '';
            }
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Capture existing prices before updating
        function captureExistingPrices() {
            const priceInputs = volumePricesFields.querySelectorAll('input[type="number"]');
            priceInputs.forEach(function(input) {
                const match = input.name.match(/volume_prices\[(.*?)\]/);
                if (match) {
                    existingPrices[match[1]] = input.value;
                }
            });
        }
        
        if (enableVolumeCheckbox && volumesSelector) {
            enableVolumeCheckbox.addEventListener('change', updateVolumePricesFields);
            volumesSelector.addEventListener('change', function() {
                captureExistingPrices();
                updateVolumePricesFields();
            });
            
            // Initialize on page load
            captureExistingPrices();
            updateVolumePricesFields();
        }
    });
    </script>
</body>
</html>
