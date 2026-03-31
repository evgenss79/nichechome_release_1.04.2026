<?php
/**
 * Admin - Products Management Interface
 * Enhanced products management page with detailed product information
 */

require_once __DIR__ . '/../init.php';

if (!isAdminLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Prevent browser caching to ensure fresh product and stock data is always displayed
// This is critical because Safari and other browsers may cache the page,
// showing stale product information and stock quantities even after changes are made.
// Without these headers, different browsers/tabs may show different data.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

$products = loadJSON('products.json');
$categories = loadJSON('categories.json');
$stock = loadJSON('stock.json');
$success = '';
$error = '';

// Handle product updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_product') {
        $productId = $_POST['product_id'] ?? '';
        
        if (isset($products[$productId])) {
            // Update basic info
            $products[$productId]['active'] = isset($_POST['active']) ? true : false;
            $products[$productId]['category'] = $_POST['category'] ?? $products[$productId]['category'];
            $products[$productId]['image'] = $_POST['image'] ?? $products[$productId]['image'];
            
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
            
            // Update English description if provided
            if (isset($_POST['description_en'])) {
                $products[$productId]['description_en'] = trim($_POST['description_en']);
            }
            
            if (saveJSON('products.json', $products)) {
                $success = 'Product updated successfully.';
            } else {
                $error = 'Failed to update product.';
            }
        }
    }
}

// Filter products
$filterCategory = $_GET['category'] ?? '';
$filterActive = $_GET['active'] ?? '';
$filteredProducts = $products;

if ($filterCategory) {
    $filteredProducts = array_filter($products, function($product) use ($filterCategory) {
        return ($product['category'] ?? '') === $filterCategory;
    });
}

if ($filterActive !== '') {
    $isActive = $filterActive === '1';
    $filteredProducts = array_filter($filteredProducts, function($product) use ($isActive) {
        return ($product['active'] ?? false) === $isActive;
    });
}

// Calculate statistics
$stats = [
    'total' => count($products),
    'active' => 0,
    'inactive' => 0,
    'variants' => 0
];

foreach ($products as $product) {
    if ($product['active'] ?? false) {
        $stats['active']++;
    } else {
        $stats['inactive']++;
    }
    $stats['variants'] += count($product['variants'] ?? []);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products Management - Admin</title>
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
                <a href="admin_products.php" class="admin-sidebar__link active">Products (Enhanced)</a>
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
                <h1>Products Management</h1>
                <p>Manage product catalog, prices, and descriptions</p>
            </div>
            
            <?php if ($success): ?>
                <div class="alert alert--success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert--error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <!-- Statistics -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
                <div class="admin-card">
                    <h4>Total Products</h4>
                    <p style="font-size: 2rem; font-weight: 700; margin: 0.5rem 0;"><?php echo $stats['total']; ?></p>
                </div>
                <div class="admin-card">
                    <h4>Active</h4>
                    <p style="font-size: 2rem; font-weight: 700; margin: 0.5rem 0; color: var(--color-success);"><?php echo $stats['active']; ?></p>
                </div>
                <div class="admin-card">
                    <h4>Inactive</h4>
                    <p style="font-size: 2rem; font-weight: 700; margin: 0.5rem 0; color: var(--color-error);"><?php echo $stats['inactive']; ?></p>
                </div>
                <div class="admin-card">
                    <h4>Total Variants</h4>
                    <p style="font-size: 2rem; font-weight: 700; margin: 0.5rem 0;"><?php echo $stats['variants']; ?></p>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="admin-card">
                <form method="get" style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
                    <label><strong>Filter by category:</strong></label>
                    <select name="category" onchange="this.form.submit()">
                        <option value="">All categories</option>
                        <?php foreach ($categories as $slug => $cat): ?>
                            <option value="<?php echo htmlspecialchars($slug); ?>" <?php echo $filterCategory === $slug ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(I18N::t('category.' . $slug . '.name', ucfirst(str_replace('_', ' ', $slug)))); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <label style="margin-left: 1rem;"><strong>Status:</strong></label>
                    <select name="active" onchange="this.form.submit()">
                        <option value="">All</option>
                        <option value="1" <?php echo $filterActive === '1' ? 'selected' : ''; ?>>Active only</option>
                        <option value="0" <?php echo $filterActive === '0' ? 'selected' : ''; ?>>Inactive only</option>
                    </select>
                    
                    <?php if ($filterCategory || $filterActive !== ''): ?>
                        <a href="admin_products.php" class="btn btn--text">Clear filters</a>
                    <?php endif; ?>
                </form>
            </div>
            
            <!-- Products List -->
            <?php if (empty($filteredProducts)): ?>
                <div class="admin-card">
                    <p class="text-muted">No products found.</p>
                </div>
            <?php else: ?>
                <div class="admin-card">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Variants</th>
                                <th>Status</th>
                                <th>Stock</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($filteredProducts as $id => $product): ?>
                                <?php 
                                $productName = I18N::t('product.' . $id . '.name', $id);
                                $categoryName = I18N::t('category.' . ($product['category'] ?? '') . '.name', $product['category'] ?? '');
                                $isActive = $product['active'] ?? false;
                                
                                // Count stock for this product
                                $totalStock = 0;
                                foreach ($stock as $sku => $stockItem) {
                                    if (strpos($sku, strtoupper(substr($id, 0, 2))) !== false) {
                                        $totalStock += $stockItem['quantity'] ?? 0;
                                    }
                                }
                                ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($id); ?></strong></td>
                                    <td><?php echo htmlspecialchars($productName); ?></td>
                                    <td><?php echo htmlspecialchars($categoryName); ?></td>
                                    <td><?php echo count($product['variants'] ?? []); ?> variant(s)</td>
                                    <td>
                                        <span style="display: inline-block; padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.85rem; font-weight: 600; background: <?php echo $isActive ? 'var(--color-success)' : 'var(--color-error)'; ?>; color: white;">
                                            <?php echo $isActive ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $totalStock; ?> units</td>
                                    <td>
                                        <button type="button" class="btn btn--text" onclick="toggleProductEdit('<?php echo htmlspecialchars($id); ?>')">
                                            <span id="btn-text-<?php echo htmlspecialchars($id); ?>">Edit</span>
                                        </button>
                                    </td>
                                </tr>
                                <tr id="edit-<?php echo htmlspecialchars($id); ?>" style="display: none;">
                                    <td colspan="7" style="background: var(--color-sand); padding: 2rem;">
                                        <form method="post" action="">
                                            <input type="hidden" name="action" value="update_product">
                                            <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($id); ?>">
                                            
                                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                                                <div>
                                                    <h4>Product Information</h4>
                                                    
                                                    <div style="margin-bottom: 1rem;">
                                                        <label style="display: block; margin-bottom: 0.5rem;"><strong>Product ID:</strong></label>
                                                        <input type="text" value="<?php echo htmlspecialchars($id); ?>" disabled style="width: 100%; padding: 0.5rem; border: 1px solid var(--color-border); border-radius: 4px; background: #f5f5f5;">
                                                    </div>
                                                    
                                                    <div style="margin-bottom: 1rem;">
                                                        <label style="display: block; margin-bottom: 0.5rem;"><strong>Category:</strong></label>
                                                        <select name="category" style="width: 100%; padding: 0.5rem; border: 1px solid var(--color-border); border-radius: 4px;">
                                                            <?php foreach ($categories as $slug => $cat): ?>
                                                                <option value="<?php echo htmlspecialchars($slug); ?>" <?php echo ($product['category'] ?? '') === $slug ? 'selected' : ''; ?>>
                                                                    <?php echo htmlspecialchars(I18N::t('category.' . $slug . '.name', ucfirst(str_replace('_', ' ', $slug)))); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    
                                                    <div style="margin-bottom: 1rem;">
                                                        <label style="display: block; margin-bottom: 0.5rem;"><strong>Image:</strong></label>
                                                        <input type="text" name="image" value="<?php echo htmlspecialchars($product['image'] ?? ''); ?>" style="width: 100%; padding: 0.5rem; border: 1px solid var(--color-border); border-radius: 4px;">
                                                    </div>
                                                    
                                                    <div style="margin-bottom: 1rem;">
                                                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                                            <input type="checkbox" name="active" <?php echo $isActive ? 'checked' : ''; ?>>
                                                            <strong>Product is active</strong>
                                                        </label>
                                                    </div>
                                                </div>
                                                
                                                <div>
                                                    <h4>English Description (SEO)</h4>
                                                    <textarea name="description_en" rows="8" style="width: 100%; padding: 0.5rem; border: 1px solid var(--color-border); border-radius: 4px; font-family: inherit;"><?php echo htmlspecialchars($product['description_en'] ?? ''); ?></textarea>
                                                    <p style="font-size: 0.85rem; color: #666; margin-top: 0.5rem;">This description is used for SEO and internal purposes.</p>
                                                </div>
                                            </div>
                                            
                                            <h4 style="margin-top: 2rem;">Product Variants</h4>
                                            <table class="admin-table" style="margin-top: 1rem;">
                                                <thead>
                                                    <tr>
                                                        <th>Volume</th>
                                                        <th>Price (CHF)</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($product['variants'] ?? [] as $idx => $variant): ?>
                                                        <tr>
                                                            <td>
                                                                <input type="text" name="variants[<?php echo $idx; ?>][volume]" value="<?php echo htmlspecialchars($variant['volume'] ?? ''); ?>" style="width: 100%; padding: 0.5rem; border: 1px solid var(--color-border); border-radius: 4px;">
                                                            </td>
                                                            <td>
                                                                <input type="number" step="0.1" name="variants[<?php echo $idx; ?>][price]" value="<?php echo htmlspecialchars($variant['priceCHF'] ?? ''); ?>" style="width: 100%; padding: 0.5rem; border: 1px solid var(--color-border); border-radius: 4px;">
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                            
                                            <div style="margin-top: 2rem; display: flex; gap: 1rem;">
                                                <button type="submit" class="btn btn--primary">Save Changes</button>
                                                <button type="button" class="btn btn--text" onclick="toggleProductEdit('<?php echo htmlspecialchars($id); ?>')">Cancel</button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </main>
    </div>
    
    <script>
        function toggleProductEdit(productId) {
            const row = document.getElementById('edit-' + productId);
            const btnText = document.getElementById('btn-text-' + productId);
            if (row) {
                if (row.style.display === 'none') {
                    row.style.display = 'table-row';
                    if (btnText) btnText.textContent = 'Cancel';
                } else {
                    row.style.display = 'none';
                    if (btnText) btnText.textContent = 'Edit';
                }
            }
        }
    </script>
</body>
</html>
