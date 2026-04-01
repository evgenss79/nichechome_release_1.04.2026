<?php
/**
 * Admin - Create/Edit Product
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/stock/sku_universe.php';

if (!isAdminLoggedIn()) {
    header('Location: login.php');
    exit;
}

$productId = $_GET['id'] ?? '';
$products = loadJSON('products.json');
$categories = getSortedCategories(false);
$fragrances = loadJSON('fragrances.json');
$success = '';
$error = '';
$isEdit = $productId !== '' && isset($products[$productId]);

$defaultProduct = [
    'id' => '',
    'category' => '',
    'name_key' => '',
    'desc_key' => '',
    'image' => '',
    'images' => [],
    'variants' => [
        ['volume' => 'standard', 'fragrance' => '', 'priceCHF' => 0]
    ],
    'active' => true,
    'has_fragrance_selector' => false,
    'allowed_fragrances' => []
];

if (!$isEdit && $productId !== '') {
    header('Location: products.php');
    exit;
}

$product = $isEdit ? array_merge($defaultProduct, $products[$productId]) : $defaultProduct;
$translations = $isEdit ? loadEntityTranslations('product', $productId, ['name', 'desc']) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedId = trim($_POST['product_id'] ?? '');
    $originalId = trim($_POST['original_id'] ?? '');
    $isEdit = $originalId !== '' && isset($products[$originalId]);

    if ($postedId === '') {
        $error = 'Product ID is required.';
    } elseif (!preg_match('/^[a-z0-9_]+$/', $postedId)) {
        $error = 'Product ID must contain only lowercase letters, numbers, and underscores.';
    } elseif (!$isEdit && isset($products[$postedId])) {
        $error = 'Product ID already exists.';
    } else {
        $category = trim((string)($_POST['category'] ?? ''));
        if ($category === '' || !isset($categories[$category])) {
            $error = 'Please choose a valid category.';
        } else {
            $imagesRaw = preg_split('/[\r\n]+/', (string)($_POST['images'] ?? '')) ?: [];
            $images = array_values(array_unique(array_filter(array_map('trim', $imagesRaw), 'strlen')));

            $variants = [];
            foreach (($_POST['variants'] ?? []) as $variant) {
                $volume = normalizeVariantVolume((string)($variant['volume'] ?? 'standard'));
                $fragrance = normalizeVariantFragrance((string)($variant['fragrance'] ?? ''));
                if ($volume === '') {
                    continue;
                }
                $variants[] = [
                    'volume' => $volume,
                    'fragrance' => $fragrance,
                    'priceCHF' => max(0, (float)($variant['price'] ?? 0))
                ];
            }

            if (empty($variants)) {
                $error = 'At least one priced variant is required.';
            } elseif (empty($images)) {
                $error = 'At least one product image filename is required.';
            } else {
                $fragranceMode = $_POST['fragrance_mode'] ?? 'category_default';
                $candidate = $isEdit ? $products[$originalId] : [];
                $fixedFragranceValue = normalizeVariantFragrance((string)($_POST['fixed_fragrance'] ?? ''));
                $candidate['id'] = $postedId;
                $candidate['category'] = $category;
                $candidate['name_key'] = 'product.' . $postedId . '.name';
                $candidate['desc_key'] = 'product.' . $postedId . '.desc';
                $candidate['image'] = $images[0];
                $candidate['images'] = $images;
                $candidate['variants'] = array_map(function ($variant) use ($fragranceMode, $fixedFragranceValue) {
                    if ($fragranceMode === 'no_fragrance') {
                        unset($variant['fragrance']);
                    } elseif ($fragranceMode === 'fixed_fragrance' && empty($variant['fragrance']) && $fixedFragranceValue !== '') {
                        $variant['fragrance'] = $fixedFragranceValue;
                    } elseif (empty($variant['fragrance'])) {
                        unset($variant['fragrance']);
                    }
                    return $variant;
                }, $variants);
                $candidate['active'] = isset($_POST['active']);

                unset($candidate['allowed_fragrances'], $candidate['fragrance'], $candidate['has_fragrance_selector']);
                if ($fragranceMode === 'selectable_fragrances') {
                    $allowedFragrances = isset($_POST['allowed_fragrances']) && is_array($_POST['allowed_fragrances'])
                        ? array_values(array_filter($_POST['allowed_fragrances'], 'strlen'))
                        : [];
                    if (empty($allowedFragrances)) {
                        $error = 'Choose at least one fragrance or switch to no-fragrance mode.';
                    } else {
                        $candidate['allowed_fragrances'] = $allowedFragrances;
                        $candidate['has_fragrance_selector'] = true;
                    }
                } elseif ($fragranceMode === 'fixed_fragrance') {
                    if ($fixedFragranceValue === '') {
                        $error = 'Choose the fixed fragrance.';
                    } else {
                        $candidate['fragrance'] = $fixedFragranceValue;
                        $candidate['has_fragrance_selector'] = false;
                    }
                } elseif ($fragranceMode === 'no_fragrance') {
                    $candidate['has_fragrance_selector'] = false;
                }

                if ($error === '') {
                    $previewProducts = [$postedId => $candidate];
                    $previewUniverse = generateCatalogSkus($previewProducts, [], $fragrances);
                    $existingUniverse = loadSkuUniverse();
                    foreach (array_keys($previewUniverse) as $sku) {
                        if (!isset($existingUniverse[$sku])) {
                            continue;
                        }
                        $existingProductId = $existingUniverse[$sku]['productId'] ?? '';
                        if ($existingProductId !== $originalId && $existingProductId !== $postedId) {
                            $error = "Generated SKU collision: $sku already belongs to $existingProductId.";
                            break;
                        }
                    }
                }

                if ($error === '') {
                    if ($isEdit && $originalId !== $postedId) {
                        unset($products[$originalId]);
                    }
                    $products[$postedId] = $candidate;

                    $translationPayload = [];
                    foreach (I18N::getSupportedLanguages() as $lang) {
                        $translationPayload[$lang] = [
                            'name' => $_POST['name_' . $lang] ?? '',
                            'desc' => $_POST['description_' . $lang] ?? ''
                        ];
                    }

                    if (!saveJSON('products.json', $products)) {
                        $error = 'Failed to save products.json.';
                    } elseif (!saveEntityTranslations('product', $postedId, $translationPayload)) {
                        $error = 'Product saved, but translations could not be written.';
                    } else {
                        initializeMissingSkuKeys(false);
                        updateCatalogVersion();
                        $success = $isEdit ? 'Product updated successfully.' : 'Product created successfully.';
                        $productId = $postedId;
                        $products = loadJSON('products.json');
                        $product = array_merge($defaultProduct, $products[$productId]);
                        $translations = loadEntityTranslations('product', $productId, ['name', 'desc']);
                        $isEdit = true;
                    }
                }
            }
        }
    }
}

$productName = $productId !== '' ? I18N::t('product.' . $productId . '.name', $productId) : 'New Product';
$productImages = !empty($product['images']) ? $product['images'] : array_filter([$product['image'] ?? '']);
$fragranceMode = 'category_default';
if (!empty($product['fragrance'])) {
    $fragranceMode = 'fixed_fragrance';
} elseif (isset($product['allowed_fragrances']) && is_array($product['allowed_fragrances']) && !empty($product['allowed_fragrances'])) {
    $fragranceMode = !empty($product['has_fragrance_selector']) ? 'selectable_fragrances' : 'fixed_fragrance';
} elseif (array_key_exists('has_fragrance_selector', $product) && !$product['has_fragrance_selector']) {
    $fragranceMode = 'no_fragrance';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $productId ? 'Edit Product' : 'Create Product'; ?> - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600&family=Manrope:wght@400;500;600&display=swap" rel="stylesheet">
</head>
<body>
    <div class="admin-layout">
        <aside class="admin-sidebar">
            <div class="admin-sidebar__logo">NicheHome Admin</div>
            <nav class="admin-sidebar__nav">
                <a href="index.php" class="admin-sidebar__link">Dashboard</a>
                <a href="products.php" class="admin-sidebar__link active">Products</a>
                <a href="admin_products.php" class="admin-sidebar__link">Products (Enhanced)</a>
                <a href="accessories.php" class="admin-sidebar__link">Accessories</a>
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
                <?php if (canManageUsers()): ?>
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
                <h1><?php echo $productId ? 'Edit Product: ' . htmlspecialchars($productName) : 'Create Product'; ?></h1>
                <a href="products.php" class="btn btn--ghost">← Back to Products</a>
            </div>
            
            <?php if ($success): ?>
                <div class="alert alert--success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert--error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <div class="admin-card">
                <form method="post" class="admin-form" id="product-form">
                    <input type="hidden" name="original_id" value="<?php echo htmlspecialchars($productId); ?>">

                    <div class="form-row">
                        <div class="form-group">
                            <label>Product ID</label>
                            <input type="text" name="product_id" value="<?php echo htmlspecialchars($productId); ?>" <?php echo $productId ? 'readonly style="background: var(--color-sand);"' : ''; ?> required>
                        </div>
                        <div class="form-group">
                            <label>Category</label>
                            <select name="category" required>
                                <option value="">Choose category</option>
                                <?php foreach ($categories as $slug => $category): ?>
                                    <option value="<?php echo htmlspecialchars($slug); ?>" <?php echo ($product['category'] ?? '') === $slug ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars(I18N::t('category.' . $slug . '.name', ucfirst(str_replace('_', ' ', $slug)))); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><input type="checkbox" name="active" <?php echo !empty($product['active']) ? 'checked' : ''; ?>> Active</label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Product images (one filename per line, img/ folder)</label>
                        <textarea name="images" rows="4"><?php echo htmlspecialchars(implode("\n", $productImages)); ?></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Fragrance mode</label>
                            <select name="fragrance_mode" id="fragrance-mode">
                                <option value="category_default" <?php echo $fragranceMode === 'category_default' ? 'selected' : ''; ?>>Use category default</option>
                                <option value="selectable_fragrances" <?php echo $fragranceMode === 'selectable_fragrances' ? 'selected' : ''; ?>>Selectable fragrances</option>
                                <option value="fixed_fragrance" <?php echo $fragranceMode === 'fixed_fragrance' ? 'selected' : ''; ?>>Fixed fragrance</option>
                                <option value="no_fragrance" <?php echo $fragranceMode === 'no_fragrance' ? 'selected' : ''; ?>>No fragrance</option>
                            </select>
                        </div>
                        <div class="form-group" id="fixed-fragrance-wrap">
                            <label>Fixed fragrance</label>
                            <select name="fixed_fragrance">
                                <option value="">Choose fragrance</option>
                                <?php foreach ($fragrances as $code => $fragrance): ?>
                                    <option value="<?php echo htmlspecialchars($code); ?>" <?php echo (!empty($product['fragrance']) && $product['fragrance'] === $code) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars(I18N::t('fragrance.' . $code . '.name', ucfirst(str_replace('_', ' ', $code)))); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" id="allowed-fragrances-wrap">
                            <label>Selectable fragrances</label>
                            <select name="allowed_fragrances[]" multiple size="8">
                                <?php foreach ($fragrances as $code => $fragrance): ?>
                                    <option value="<?php echo htmlspecialchars($code); ?>" <?php echo in_array($code, $product['allowed_fragrances'] ?? [], true) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars(I18N::t('fragrance.' . $code . '.name', ucfirst(str_replace('_', ' ', $code)))); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <h3 style="margin-top: 2rem;">Sellable variants</h3>
                    <p class="text-muted">Add one row per sellable option. Leave fragrance empty to reuse the category or product fragrance mode.</p>
                    <div id="variants-container">
                        <?php foreach (($product['variants'] ?? []) as $index => $variant): ?>
                            <div class="form-row variant-row" style="grid-template-columns: 1fr 1fr 1fr auto; align-items: end;">
                                <div class="form-group">
                                    <label>Volume / pack</label>
                                    <input type="text" name="variants[<?php echo $index; ?>][volume]" value="<?php echo htmlspecialchars((string)($variant['volume'] ?? 'standard')); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Variant fragrance (optional)</label>
                                    <select name="variants[<?php echo $index; ?>][fragrance]">
                                        <option value="">Use mode default</option>
                                        <?php foreach ($fragrances as $code => $fragrance): ?>
                                            <option value="<?php echo htmlspecialchars($code); ?>" <?php echo normalizeVariantFragrance((string)($variant['fragrance'] ?? '')) === $code ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars(I18N::t('fragrance.' . $code . '.name', ucfirst(str_replace('_', ' ', $code)))); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Price (CHF)</label>
                                    <input type="number" step="0.01" name="variants[<?php echo $index; ?>][price]" value="<?php echo htmlspecialchars(number_format((float)($variant['priceCHF'] ?? 0), 2, '.', '')); ?>">
                                </div>
                                <button type="button" class="btn btn--text remove-variant">Remove</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="btn btn--ghost" id="add-variant">Add variant</button>

                    <h3 style="margin-top: 2rem;">Multilingual content</h3>
                    <?php foreach (I18N::getSupportedLanguages() as $lang): ?>
                        <div class="admin-card" style="margin: 1rem 0; background: var(--color-sand);">
                            <h4 style="margin-top: 0;"><?php echo htmlspecialchars(strtoupper($lang)); ?></h4>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Name</label>
                                    <input type="text" name="name_<?php echo htmlspecialchars($lang); ?>" value="<?php echo htmlspecialchars($translations[$lang]['name'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Description</label>
                                    <textarea name="description_<?php echo htmlspecialchars($lang); ?>" rows="4"><?php echo htmlspecialchars($translations[$lang]['desc'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <button type="submit" class="btn btn--gold"><?php echo $productId ? 'Save Product' : 'Create Product'; ?></button>
                </form>
            </div>
        </main>
    </div>

    <script>
        (function () {
            const container = document.getElementById('variants-container');
            const addButton = document.getElementById('add-variant');
            const fragranceMode = document.getElementById('fragrance-mode');
            const fixedWrap = document.getElementById('fixed-fragrance-wrap');
            const allowedWrap = document.getElementById('allowed-fragrances-wrap');
            let index = <?php echo count($product['variants'] ?? []); ?>;

            function toggleFragranceFields() {
                const mode = fragranceMode.value;
                fixedWrap.style.display = mode === 'fixed_fragrance' ? '' : 'none';
                allowedWrap.style.display = mode === 'selectable_fragrances' ? '' : 'none';
            }

            function createVariantRow() {
                const row = document.createElement('div');
                row.className = 'form-row variant-row';
                row.style.gridTemplateColumns = '1fr 1fr 1fr auto';
                row.style.alignItems = 'end';
                row.innerHTML = `
                    <div class="form-group">
                        <label>Volume / pack</label>
                        <input type="text" name="variants[${index}][volume]" value="standard">
                    </div>
                    <div class="form-group">
                        <label>Variant fragrance (optional)</label>
                        <select name="variants[${index}][fragrance]">
                            <option value="">Use mode default</option>
                            <?php foreach ($fragrances as $code => $fragrance): ?>
                                <option value="<?php echo htmlspecialchars($code); ?>"><?php echo htmlspecialchars(I18N::t('fragrance.' . $code . '.name', ucfirst(str_replace('_', ' ', $code)))); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Price (CHF)</label>
                        <input type="number" step="0.01" name="variants[${index}][price]" value="0.00">
                    </div>
                    <button type="button" class="btn btn--text remove-variant">Remove</button>
                `;
                index += 1;
                container.appendChild(row);
            }

            addButton.addEventListener('click', createVariantRow);
            container.addEventListener('click', (event) => {
                const button = event.target.closest('.remove-variant');
                if (!button) return;
                const rows = container.querySelectorAll('.variant-row');
                if (rows.length > 1) {
                    button.closest('.variant-row').remove();
                }
            });
            fragranceMode.addEventListener('change', toggleFragranceFields);
            toggleFragranceFields();
        }());
    </script>
</body>
</html>
