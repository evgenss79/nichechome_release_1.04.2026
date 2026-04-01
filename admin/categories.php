<?php
/**
 * Admin - Categories Management
 */

require_once __DIR__ . '/../init.php';

if (!isAdminLoggedIn()) {
    header('Location: login.php');
    exit;
}

$categories = loadJSON('categories.json');
$products = loadJSON('products.json');
$fragrances = loadJSON('fragrances.json');
$success = '';
$error = '';
$editingId = $_GET['edit'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_category') {
    $originalId = trim($_POST['original_id'] ?? '');
    $categoryId = trim($_POST['category_id'] ?? '');
    $isEdit = $originalId !== '' && isset($categories[$originalId]);

    if ($categoryId === '') {
        $error = 'Category ID is required.';
    } elseif (!preg_match('/^[a-z0-9_]+$/', $categoryId)) {
        $error = 'Category ID must contain only lowercase letters, numbers, and underscores.';
    } elseif (!$isEdit && isset($categories[$categoryId])) {
        $error = 'Category ID already exists.';
    } else {
        $existing = $isEdit ? $categories[$originalId] : [];
        if ($isEdit && $originalId !== $categoryId) {
            unset($categories[$originalId]);
        }

        $volumesRaw = (string)($_POST['volumes'] ?? '');
        $volumeParts = preg_split('/[\r\n,]+/', $volumesRaw) ?: [];
        $volumes = array_values(array_unique(array_filter(array_map('trim', $volumeParts), 'strlen')));

        $hasFragrance = isset($_POST['has_fragrance']);
        $allowedFragrances = $hasFragrance && isset($_POST['allowed_fragrances']) && is_array($_POST['allowed_fragrances'])
            ? array_values(array_filter($_POST['allowed_fragrances'], 'strlen'))
            : [];
        $primaryImageInput = (string)($_POST['image'] ?? '');
        $primaryImageError = null;
        $primaryImage = normalizeImageFilename($primaryImageInput, true, $primaryImageError);
        $invalidCategoryImages = [];
        $categoryImages = normalizeImageFilenameList((string)($_POST['images'] ?? ''), true, $invalidCategoryImages);
        if (trim($primaryImageInput) !== '' && $primaryImage === '') {
            $error = $primaryImageError ?? 'Category image must reference an existing file from img/.';
        } elseif (!empty($invalidCategoryImages)) {
            $error = 'Slider images must reference existing files from img/: ' . implode(', ', array_keys($invalidCategoryImages));
        } elseif ($primaryImage !== '') {
            array_unshift($categoryImages, $primaryImage);
            $categoryImages = normalizeImageFilenameList($categoryImages);
        }
        if ($error === '' && $primaryImage === '' && !empty($categoryImages)) {
            $primaryImage = $categoryImages[0];
        }

        if ($error === '') {
            $category = $existing;
            $category['id'] = $categoryId;
            $category['name_key'] = 'category.' . $categoryId . '.name';
            $category['short_key'] = 'category.' . $categoryId . '.short';
            $category['long_key'] = 'category.' . $categoryId . '.long';
            $category['image'] = $primaryImage;
            $category['images'] = $categoryImages;
            $category['use_custom_image'] = isset($_POST['use_custom_image']) || !$isEdit;
            $category['sort_order'] = (int)($_POST['sort_order'] ?? 999);
            $category['active'] = isset($_POST['active']);
            $category['show_in_catalog'] = isset($_POST['show_in_catalog']);
            $category['show_in_navigation'] = isset($_POST['show_in_navigation']);
            $category['show_in_footer'] = isset($_POST['show_in_footer']);
            $category['has_fragrance'] = $hasFragrance;
            $category['volumes'] = $volumes;

            if (!empty($allowedFragrances)) {
                $category['allowed_fragrances'] = $allowedFragrances;
            } else {
                unset($category['allowed_fragrances']);
            }

            $redirect = trim((string)($_POST['redirect'] ?? ''));
            if ($redirect !== '') {
                $category['redirect'] = $redirect;
            } else {
                unset($category['redirect']);
            }

            $translations = [];
            foreach (I18N::getSupportedLanguages() as $lang) {
                $translations[$lang] = [
                    'name' => $_POST['name_' . $lang] ?? '',
                    'short' => $_POST['short_' . $lang] ?? '',
                    'long' => $_POST['long_' . $lang] ?? ''
                ];
            }

            $categories[$categoryId] = $category;
            uasort($categories, function ($a, $b) {
                return (int)($a['sort_order'] ?? 999) <=> (int)($b['sort_order'] ?? 999);
            });

            if (!saveJSON('categories.json', $categories)) {
                $error = 'Failed to save categories.json.';
            } elseif (!saveEntityTranslations('category', $categoryId, $translations)) {
                $error = 'Category saved, but translations could not be written.';
            } else {
                updateCatalogVersion();
                $success = $isEdit ? 'Category updated successfully.' : 'Category created successfully.';
                $categories = loadJSON('categories.json');
                $editingId = $categoryId;
            }
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_category') {
    $categoryId = trim($_POST['category_id'] ?? '');

    if ($categoryId === '') {
        $error = 'Category ID is required for deletion.';
    } else {
        $result = deleteCategory($categoryId);

        if ($result['success']) {
            $details = $result['details'];
            $success = "Category '$categoryId' deleted successfully! ";
            $success .= 'Removed from categories.json and ';
            $success .= count($details['i18n_keys_removed']) . ' translation group(s).';
            $categories = loadJSON('categories.json');
            $products = loadJSON('products.json');
            if ($editingId === $categoryId) {
                $editingId = '';
            }
        } else {
            $blockedProducts = $result['details']['blocked_products'] ?? [];
            $error = 'Failed to delete category: ' . $result['error'];
            if (!empty($blockedProducts)) {
                $error .= ' Blocking products: ' . implode(', ', $blockedProducts) . '.';
            }
        }
    }
}

if ($editingId && !isset($categories[$editingId])) {
    $editingId = '';
}

$defaultCategory = [
    'id' => '',
    'image' => '',
    'images' => [],
    'sort_order' => !empty($categories) ? (max(array_map(function ($category) {
        return (int)($category['sort_order'] ?? 0);
    }, $categories)) + 1) : 1,
    'active' => true,
    'show_in_catalog' => true,
    'show_in_navigation' => true,
    'show_in_footer' => true,
    'use_custom_image' => true,
    'has_fragrance' => false,
    'volumes' => [],
    'allowed_fragrances' => [],
    'redirect' => ''
];
$productCounts = [];
foreach ($products as $product) {
    $categoryId = (string)($product['category'] ?? '');
    if ($categoryId === '') {
        continue;
    }
    $productCounts[$categoryId] = ($productCounts[$categoryId] ?? 0) + 1;
}
$editingCategory = $editingId ? array_merge($defaultCategory, $categories[$editingId]) : $defaultCategory;
$translations = $editingId ? loadEntityTranslations('category', $editingId, ['name', 'short', 'long']) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600&family=Manrope:wght@400;500;600&display=swap" rel="stylesheet">
</head>
<body>
    <div class="admin-layout">
        <aside class="admin-sidebar">
            <div class="admin-sidebar__logo">NicheHome Admin</div>
            <nav class="admin-sidebar__nav">
                <a href="index.php" class="admin-sidebar__link">Dashboard</a>
                <a href="products.php" class="admin-sidebar__link">Products</a>
                <a href="admin_products.php" class="admin-sidebar__link">Products (Enhanced)</a>
                <a href="accessories.php" class="admin-sidebar__link">Accessories</a>
                <a href="fragrances.php" class="admin-sidebar__link">Fragrances</a>
                <a href="categories.php" class="admin-sidebar__link active">Categories</a>
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
                <h1><?php echo $editingId ? 'Edit Category' : 'Create Category'; ?></h1>
                <?php if ($editingId): ?>
                    <a href="categories.php" class="btn btn--ghost">Create new</a>
                <?php endif; ?>
            </div>
            
            <?php if ($success): ?>
                <div class="alert alert--success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert--error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <div class="admin-card">
                <form method="post" class="admin-form">
                    <input type="hidden" name="action" value="save_category">
                    <input type="hidden" name="original_id" value="<?php echo htmlspecialchars($editingId); ?>">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Category ID</label>
                            <input type="text" name="category_id" value="<?php echo htmlspecialchars($editingCategory['id']); ?>" <?php echo $editingId ? 'readonly style="background: var(--color-sand);"' : ''; ?> required>
                        </div>
                        <div class="form-group">
                            <label>Image filename (img/)</label>
                            <?php
                            $displayCategoryImage = normalizeImageFilename((string)$editingCategory['image'], true);
                            if ($displayCategoryImage === '') {
                                $displayCategoryImage = normalizeImageFilename((string)$editingCategory['image']);
                            }
                            if ($displayCategoryImage === '') {
                                $displayCategoryImage = trim((string)$editingCategory['image']);
                            }
                            ?>
                            <input type="text" name="image" value="<?php echo htmlspecialchars($displayCategoryImage); ?>">
                        </div>
                        <div class="form-group">
                            <label>Slider images (one filename per line or comma separated)</label>
                            <textarea name="images" rows="3"><?php echo htmlspecialchars(implode("\n", normalizeImageFilenameList($editingCategory['images'] ?: ($editingCategory['image'] ? [$editingCategory['image']] : [])))); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Sort order</label>
                            <input type="number" name="sort_order" value="<?php echo htmlspecialchars((string)$editingCategory['sort_order']); ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Default volumes (comma or line separated)</label>
                            <textarea name="volumes" rows="3"><?php echo htmlspecialchars(implode("\n", $editingCategory['volumes'])); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Redirect target (optional)</label>
                            <input type="text" name="redirect" value="<?php echo htmlspecialchars((string)$editingCategory['redirect']); ?>" placeholder="gift-sets.php">
                        </div>
                        <div class="form-group">
                            <label>Allowed fragrances</label>
                            <select name="allowed_fragrances[]" multiple size="8">
                                <?php foreach ($fragrances as $code => $fragrance): ?>
                                    <option value="<?php echo htmlspecialchars($code); ?>" <?php echo in_array($code, $editingCategory['allowed_fragrances'], true) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars(I18N::t('fragrance.' . $code . '.name', ucfirst(str_replace('_', ' ', $code)))); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row" style="grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1rem;">
                        <label><input type="checkbox" name="active" <?php echo !empty($editingCategory['active']) ? 'checked' : ''; ?>> Active</label>
                        <label><input type="checkbox" name="show_in_catalog" <?php echo !empty($editingCategory['show_in_catalog']) ? 'checked' : ''; ?>> Show in catalog</label>
                        <label><input type="checkbox" name="show_in_navigation" <?php echo !empty($editingCategory['show_in_navigation']) ? 'checked' : ''; ?>> Show in navigation</label>
                        <label><input type="checkbox" name="show_in_footer" <?php echo !empty($editingCategory['show_in_footer']) ? 'checked' : ''; ?>> Show in footer</label>
                        <label><input type="checkbox" name="use_custom_image" <?php echo !empty($editingCategory['use_custom_image']) ? 'checked' : ''; ?>> Use custom image</label>
                        <label><input type="checkbox" name="has_fragrance" <?php echo !empty($editingCategory['has_fragrance']) ? 'checked' : ''; ?>> Category supports fragrances</label>
                    </div>

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
                                    <label>Short description</label>
                                    <textarea name="short_<?php echo htmlspecialchars($lang); ?>" rows="3"><?php echo htmlspecialchars($translations[$lang]['short'] ?? ''); ?></textarea>
                                </div>
                                <div class="form-group">
                                    <label>Long description</label>
                                    <textarea name="long_<?php echo htmlspecialchars($lang); ?>" rows="4"><?php echo htmlspecialchars($translations[$lang]['long'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <button type="submit" class="btn btn--gold"><?php echo $editingId ? 'Save Category' : 'Create Category'; ?></button>
                </form>
            </div>

            <div class="admin-card">
                <h2>Existing categories</h2>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Image</th>
                            <th>Sort</th>
                            <th>Products</th>
                            <th>Visibility</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (getSortedCategories(false) as $id => $category): ?>
                            <?php
                            $deleteConfirmMessage = sprintf(
                                "⚠️ PERMANENT DELETION\n\nThis will delete:\n- Category from categories.json\n- Category translations from ui/category language files\n- Catalog, menu, footer, and category-page availability\n\nSafe delete rule:\n- Deletion is blocked if products still belong to this category\n- Reassign or delete products first\n\nBackups will be created.\n\nAre you sure you want to delete %s?",
                                $id
                            );
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($id); ?></td>
                                <td><?php echo htmlspecialchars(I18N::t('category.' . $id . '.name', ucfirst(str_replace('_', ' ', $id)))); ?></td>
                                <td><?php echo htmlspecialchars((string)($category['image'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars((string)($category['sort_order'] ?? '')); ?></td>
                                <td><?php echo (int)($productCounts[$id] ?? 0); ?></td>
                                <td>
                                    C: <?php echo !empty($category['show_in_catalog']) ? 'Y' : 'N'; ?> /
                                    N: <?php echo !empty($category['show_in_navigation']) ? 'Y' : 'N'; ?> /
                                    F: <?php echo !empty($category['show_in_footer']) ? 'Y' : 'N'; ?>
                                </td>
                                <td>
                                    <a href="categories.php?edit=<?php echo urlencode($id); ?>" class="btn btn--text">Edit</a>
                                    <form method="post" action="" style="display: inline;" onsubmit="return confirm(<?php echo json_encode($deleteConfirmMessage, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>);">
                                        <input type="hidden" name="action" value="delete_category">
                                        <input type="hidden" name="category_id" value="<?php echo htmlspecialchars($id); ?>">
                                        <button type="submit" class="btn btn--text" style="color: var(--color-error);">
                                            🗑️ Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>
