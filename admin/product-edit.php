<?php
/**
 * Admin - Edit Product
 */

require_once __DIR__ . '/../init.php';

if (!isAdminLoggedIn()) {
    header('Location: login.php');
    exit;
}

$productId = $_GET['id'] ?? '';
$products = loadJSON('products.json');
$categories = loadJSON('categories.json');
$fragrances = loadJSON('fragrances.json');

if (!isset($products[$productId])) {
    header('Location: products.php');
    exit;
}

$product = $products[$productId];
$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update variants with prices
    $variants = [];
    if (isset($_POST['variants']) && is_array($_POST['variants'])) {
        foreach ($_POST['variants'] as $v) {
            if (!empty($v['volume'])) {
                $variants[] = [
                    'volume' => $v['volume'],
                    'priceCHF' => floatval($v['price'] ?? 0)
                ];
            }
        }
    }
    
    $products[$productId]['variants'] = $variants;
    $products[$productId]['category'] = $_POST['category'] ?? $product['category'];
    $products[$productId]['image'] = $_POST['image'] ?? $product['image'];
    $products[$productId]['active'] = isset($_POST['active']);
    
    if (saveJSON('products.json', $products)) {
        // Update catalog version for cache busting
        updateCatalogVersion();
        $catalogVersion = getCatalogVersion();
        $success = 'Product updated successfully. Catalog version: ' . $catalogVersion;
        $product = $products[$productId];
    } else {
        $error = 'Failed to save product.';
    }
}

$productName = I18N::t('product.' . $productId . '.name', $productId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product - Admin</title>
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
                <h1>Edit Product: <?php echo htmlspecialchars($productName); ?></h1>
                <a href="products.php" class="btn btn--ghost">← Back to Products</a>
            </div>
            
            <?php if ($success): ?>
                <div class="alert alert--success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert--error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <div class="admin-card">
                <form method="post" action="" class="admin-form">
                    <div class="form-group">
                        <label>Product ID (read-only)</label>
                        <input type="text" value="<?php echo htmlspecialchars($productId); ?>" readonly style="background: var(--color-sand);">
                    </div>
                    
                    <div class="form-group">
                        <label>Category</label>
                        <select name="category">
                            <?php foreach ($categories as $slug => $cat): ?>
                                <?php if (in_array($slug, ['gift_sets', 'aroma_marketing'])) continue; ?>
                                <option value="<?php echo htmlspecialchars($slug); ?>" <?php echo ($product['category'] ?? '') === $slug ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(I18N::t('category.' . $slug . '.name', ucfirst(str_replace('_', ' ', $slug)))); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Image filename</label>
                        <input type="text" name="image" value="<?php echo htmlspecialchars($product['image'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="active" <?php echo ($product['active'] ?? true) ? 'checked' : ''; ?>>
                            Active
                        </label>
                    </div>
                    
                    <h3 style="margin-top: 2rem;">Price Variants</h3>
                    <p class="text-muted mb-3">Edit prices for each volume variant:</p>
                    
                    <div id="variants-container">
                        <?php foreach ($product['variants'] ?? [] as $index => $variant): ?>
                            <div class="form-row" style="align-items: end;">
                                <div class="form-group">
                                    <label>Volume</label>
                                    <input type="text" name="variants[<?php echo $index; ?>][volume]" value="<?php echo htmlspecialchars($variant['volume'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Price (CHF)</label>
                                    <input type="number" step="0.01" name="variants[<?php echo $index; ?>][price]" value="<?php echo number_format($variant['priceCHF'] ?? 0, 2, '.', ''); ?>">
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if (empty($product['variants'])): ?>
                            <div class="form-row" style="align-items: end;">
                                <div class="form-group">
                                    <label>Volume</label>
                                    <input type="text" name="variants[0][volume]" value="standard">
                                </div>
                                <div class="form-group">
                                    <label>Price (CHF)</label>
                                    <input type="number" step="0.01" name="variants[0][price]" value="0.00">
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <button type="submit" class="btn btn--gold">Save Changes</button>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
