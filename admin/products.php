<?php
/**
 * Admin - Products Management
 */

require_once __DIR__ . '/../init.php';

if (!isAdminLoggedIn()) {
    header('Location: login.php');
    exit;
}

$products = loadJSON('products.json');
$categories = loadJSON('categories.json');

$success = '';
$error = '';

// Handle form submission for editing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // FIX: TASK 3 - Check edit permission for products
    if (!hasPermission('edit_products')) {
        $error = 'You do not have permission to edit products';
    } elseif ($_POST['action'] === 'update_product') {
        $productId = $_POST['product_id'] ?? '';
        
        if (isset($products[$productId])) {
            // Update variants with prices
            $variants = [];
            if (isset($_POST['variants']) && is_array($_POST['variants'])) {
                foreach ($_POST['variants'] as $v) {
                    $variants[] = [
                        'volume' => $v['volume'] ?? '',
                        'priceCHF' => floatval($v['price'] ?? 0)
                    ];
                }
            }
            
            $products[$productId]['variants'] = $variants;
            $products[$productId]['category'] = $_POST['category'] ?? $products[$productId]['category'];
            $products[$productId]['image'] = $_POST['image'] ?? $products[$productId]['image'];
            
            saveJSON('products.json', $products);
            $success = 'Product updated successfully.';
        }
    }
    elseif ($_POST['action'] === 'delete_product') {
        $productId = trim($_POST['product_id'] ?? '');
        if (empty($productId)) {
            $error = 'Product ID is required for deletion.';
        } else {
            // Use shared delete function from helpers.php
            $result = deleteProduct($productId);
            
            if ($result['success']) {
                $details = $result['details'];
                $success = "Product '$productId' deleted successfully! ";
                $success .= "Removed from products.json, ";
                if ($details['accessory_removed']) {
                    $success .= "accessories.json, ";
                }
                $success .= count($details['stock_skus_removed']) . " SKUs from stock.json, ";
                $success .= count($details['branch_skus_removed']) . " entries from branch_stock.json, ";
                $success .= "and " . count($details['i18n_keys_removed']) . " i18n keys.";
                
                // Reload data
                $products = loadJSON('products.json');
            } else {
                $error = "Failed to delete product: " . $result['error'];
            }
        }
    }
}

$filterCategory = $_GET['category'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - Admin</title>
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
                <h1>Products</h1>
            </div>
            
            <?php if ($success): ?>
                <div class="alert alert--success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert--error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <!-- Category Filter -->
            <div class="admin-card">
                <form method="get" style="display: flex; gap: 1rem; align-items: center;">
                    <label>Filter by category:</label>
                    <select name="category" onchange="this.form.submit()">
                        <option value="">All categories</option>
                        <?php foreach ($categories as $slug => $cat): ?>
                            <option value="<?php echo htmlspecialchars($slug); ?>" <?php echo $filterCategory === $slug ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(I18N::t('category.' . $slug . '.name', ucfirst(str_replace('_', ' ', $slug)))); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
            
            <!-- Products Table -->
            <div class="admin-card">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Variants</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $id => $product): ?>
                            <?php 
                            if ($filterCategory && ($product['category'] ?? '') !== $filterCategory) continue;
                            $productName = I18N::t('product.' . $id . '.name', $id);
                            $categoryName = I18N::t('category.' . ($product['category'] ?? '') . '.name', $product['category'] ?? '');
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($id); ?></td>
                                <td><?php echo htmlspecialchars($productName); ?></td>
                                <td><?php echo htmlspecialchars($categoryName); ?></td>
                                <td>
                                    <?php foreach ($product['variants'] ?? [] as $v): ?>
                                        <span style="display: inline-block; background: var(--color-sand); padding: 0.25rem 0.5rem; border-radius: 4px; margin: 0.1rem; font-size: 0.85rem;">
                                            <?php echo htmlspecialchars($v['volume'] ?? ''); ?>: CHF <?php echo number_format($v['priceCHF'] ?? 0, 2); ?>
                                        </span>
                                    <?php endforeach; ?>
                                </td>
                                <td>
                                    <a href="product-edit.php?id=<?php echo urlencode($id); ?>" class="btn btn--text">Edit</a>
                                    <form method="post" action="" style="display: inline;" onsubmit="return confirm('⚠️ PERMANENT DELETION\n\nThis will delete:\n- Product from products.json\n- Accessory config from accessories.json (if exists)\n- All SKUs from stock.json\n- All branch stock entries\n- All i18n translations\n\nBackups will be created.\n\nAre you sure you want to delete <?php echo htmlspecialchars($id); ?>?');">
                                        <input type="hidden" name="action" value="delete_product">
                                        <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($id); ?>">
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
