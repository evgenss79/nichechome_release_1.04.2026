<?php
/**
 * Admin - Diagnostics & Data Reconciliation
 * 
 * This page identifies and helps fix:
 * - Products in products.json missing from accessories.json (orphans)
 * - Unknown branch IDs in branch_stock.json not in branches.json
 * - SKU format violations
 * - Orphan SKUs in stock files
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/stock/sku_universe.php';

if (!isAdminLoggedIn()) {
    header('Location: login.php');
    exit;
}

$success = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'delete_orphan_product') {
        $productId = trim($_POST['product_id'] ?? '');
        if (empty($productId)) {
            $error = 'Product ID is required.';
        } else {
            $result = deleteProduct($productId);
            if ($result['success']) {
                $details = $result['details'];
                $success = "Orphan product '$productId' deleted successfully! ";
                $success .= "Removed from products.json, ";
                if ($details['accessory_removed']) {
                    $success .= "accessories.json, ";
                }
                $success .= count($details['stock_skus_removed']) . " SKUs from stock.json, ";
                $success .= count($details['branch_skus_removed']) . " entries from branch_stock.json.";
            } else {
                $error = "Failed to delete product: " . $result['error'];
            }
        }
    }
    elseif ($action === 'delete_orphan_branch') {
        $branchId = trim($_POST['branch_id'] ?? '');
        if (empty($branchId)) {
            $error = 'Branch ID is required.';
        } else {
            $result = deleteBranch($branchId);
            if ($result['success']) {
                $details = $result['details'];
                $success = "Orphan branch '$branchId' deleted successfully! ";
                $success .= "Removed " . $details['stock_entries_removed'] . " stock entries from branch_stock.json.";
            } else {
                $error = "Failed to delete branch: " . $result['error'];
            }
        }
    }
    elseif ($action === 'sync_orphan_to_accessories') {
        $productId = trim($_POST['product_id'] ?? '');
        if (empty($productId)) {
            $error = 'Product ID is required.';
        } else {
            // Load data
            $products = loadJSON('products.json');
            $accessories = loadJSON('accessories.json');
            
            if (!isset($products[$productId])) {
                $error = "Product '$productId' not found in products.json.";
            } elseif (($products[$productId]['category'] ?? '') !== 'accessories') {
                $error = "Product '$productId' is not an accessory.";
            } else {
                // Create minimal config in accessories.json
                $accessories[$productId] = [
                    'id' => $productId,
                    'name_key' => 'product.' . $productId . '.name',
                    'desc_key' => 'product.' . $productId . '.desc',
                    'images' => !empty($products[$productId]['image']) ? [$products[$productId]['image']] : [],
                    'priceCHF' => !empty($products[$productId]['variants']) ? ($products[$productId]['variants'][0]['priceCHF'] ?? 0) : 0,
                    'active' => true,
                    'has_fragrance_selector' => false,
                    'allowed_fragrances' => [],
                    'has_volume_selector' => false,
                    'volumes' => [],
                    'volume_prices' => []
                ];
                
                if (saveJSON('accessories.json', $accessories)) {
                    $success = "Accessory config created for '$productId'. You can now edit it in the Accessories page.";
                } else {
                    $error = 'Failed to save accessories.json';
                }
            }
        }
    }
}

// Load current data
$products = loadJSON('products.json');
$accessories = loadJSON('accessories.json');
$stock = loadJSON('stock.json');
$branchStock = loadBranchStock();
$branches = loadBranches();
$universe = loadSkuUniverse();

// Diagnostic 1: Find orphan accessories (in products.json with category=accessories but not in accessories.json)
$orphanAccessories = [];
foreach ($products as $productId => $product) {
    if (($product['category'] ?? '') === 'accessories' && !isset($accessories[$productId])) {
        $orphanAccessories[$productId] = [
            'id' => $productId,
            'name' => I18N::t('product.' . $productId . '.name', $productId),
            'variants' => $product['variants'] ?? [],
            'active' => $product['active'] ?? false
        ];
    }
}

// Diagnostic 2: Find orphan branches (in branch_stock.json but not in branches.json)
$orphanBranches = [];
foreach ($branchStock as $branchId => $skus) {
    if (!isset($branches[$branchId])) {
        $orphanBranches[$branchId] = [
            'id' => $branchId,
            'sku_count' => count($skus)
        ];
    }
}

// Diagnostic 3: Find products with unknown/missing config
$productsWithIssues = [];
foreach ($products as $productId => $product) {
    $issues = [];
    
    // Check if product has variants
    if (empty($product['variants'])) {
        $issues[] = 'No variants defined';
    }
    
    // Check if product has name in i18n
    $hasName = false;
    $langs = ['en', 'de', 'fr', 'it', 'ru', 'ukr'];
    foreach ($langs as $lang) {
        $i18nPath = __DIR__ . '/../data/i18n/ui_' . $lang . '.json';
        if (file_exists($i18nPath)) {
            $i18nData = json_decode(file_get_contents($i18nPath), true);
            if (isset($i18nData['product'][$productId]['name'])) {
                $hasName = true;
                break;
            }
        }
    }
    if (!$hasName) {
        $issues[] = 'No i18n name defined';
    }
    
    if (!empty($issues)) {
        $productsWithIssues[$productId] = [
            'id' => $productId,
            'category' => $product['category'] ?? 'unknown',
            'issues' => $issues
        ];
    }
}

// Diagnostic 4: Find SKU format violations
$skuFormatViolations = [];
foreach ($universe as $sku => $data) {
    $parts = explode('-', $sku);
    if (count($parts) !== 3) {
        $skuFormatViolations[] = [
            'sku' => $sku,
            'parts' => count($parts),
            'productId' => $data['productId'] ?? 'unknown'
        ];
    }
}

// Diagnostic 5: Find orphan SKUs (in stock files but not in catalog)
$orphanSkusInStock = [];
$orphanSkusInBranchStock = [];

foreach ($stock as $sku => $stockData) {
    if (!isset($universe[$sku]) || !$universe[$sku]['in_catalog']) {
        $orphanSkusInStock[] = [
            'sku' => $sku,
            'productId' => $stockData['productId'] ?? 'unknown',
            'quantity' => $stockData['quantity'] ?? 0
        ];
    }
}

// Count orphan SKUs in branch stock
$branchSkusSeen = [];
foreach ($branchStock as $branchId => $skus) {
    foreach ($skus as $sku => $data) {
        if (!isset($universe[$sku]) || !$universe[$sku]['in_catalog']) {
            if (!isset($branchSkusSeen[$sku])) {
                $branchSkusSeen[$sku] = true;
                $orphanSkusInBranchStock[] = [
                    'sku' => $sku,
                    'branches' => 1
                ];
            } else {
                // Find and update count
                foreach ($orphanSkusInBranchStock as &$item) {
                    if ($item['sku'] === $sku) {
                        $item['branches']++;
                        break;
                    }
                }
            }
        }
    }
}

// Calculate summary statistics
$totalIssues = count($orphanAccessories) + count($orphanBranches) + count($skuFormatViolations) + 
               count($orphanSkusInStock) + count($orphanSkusInBranchStock);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnostics - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600&family=Manrope:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        .diagnostic-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .diagnostic-card {
            background: white;
            border: 2px solid #ddd;
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
        }
        .diagnostic-card--ok {
            border-color: #28a745;
            background: #f0fff4;
        }
        .diagnostic-card--warning {
            border-color: #ffc107;
            background: #fffbf0;
        }
        .diagnostic-card--error {
            border-color: #dc3545;
            background: #fff5f5;
        }
        .diagnostic-card__number {
            font-size: 2.5rem;
            font-weight: bold;
            margin: 0.5rem 0;
        }
        .diagnostic-card__number--ok { color: #28a745; }
        .diagnostic-card__number--warning { color: #ffc107; }
        .diagnostic-card__number--error { color: #dc3545; }
        .diagnostic-card__label {
            font-size: 0.9rem;
            color: #666;
        }
        .issue-table {
            width: 100%;
            margin-top: 1rem;
        }
        .issue-table th {
            background: #f5f5f5;
            padding: 0.75rem;
            text-align: left;
            font-weight: 600;
        }
        .issue-table td {
            padding: 0.75rem;
            border-bottom: 1px solid #eee;
        }
        .issue-tag {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            background: #ffc107;
            color: #000;
            border-radius: 4px;
            font-size: 0.85rem;
            margin: 0.1rem;
        }
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
                <a href="diagnostics.php" class="admin-sidebar__link active">Diagnostics</a>
                <a href="notifications.php" class="admin-sidebar__link">Notifications</a>
                <a href="email.php" class="admin-sidebar__link">Email Settings</a>
                <a href="logout.php" class="admin-sidebar__link">Logout</a>
            </nav>
        </aside>
        
        <main class="admin-content">
            <div class="admin-header">
                <h1>🔍 Data Diagnostics & Reconciliation</h1>
                <p style="color: #666; margin-top: 0.5rem;">
                    Identify and fix data inconsistencies across products, accessories, stock, and branches
                </p>
            </div>
            
            <?php if ($success): ?>
                <div class="alert alert--success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert--error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <!-- Summary Cards -->
            <div class="diagnostic-summary">
                <div class="diagnostic-card <?php echo $totalIssues === 0 ? 'diagnostic-card--ok' : 'diagnostic-card--warning'; ?>">
                    <div class="diagnostic-card__label">Total Issues</div>
                    <div class="diagnostic-card__number <?php echo $totalIssues === 0 ? 'diagnostic-card__number--ok' : 'diagnostic-card__number--warning'; ?>">
                        <?php echo $totalIssues; ?>
                    </div>
                </div>
                
                <div class="diagnostic-card <?php echo count($orphanAccessories) === 0 ? 'diagnostic-card--ok' : 'diagnostic-card--warning'; ?>">
                    <div class="diagnostic-card__label">Orphan Accessories</div>
                    <div class="diagnostic-card__number <?php echo count($orphanAccessories) === 0 ? 'diagnostic-card__number--ok' : 'diagnostic-card__number--warning'; ?>">
                        <?php echo count($orphanAccessories); ?>
                    </div>
                </div>
                
                <div class="diagnostic-card <?php echo count($orphanBranches) === 0 ? 'diagnostic-card--ok' : 'diagnostic-card--warning'; ?>">
                    <div class="diagnostic-card__label">Unknown Branches</div>
                    <div class="diagnostic-card__number <?php echo count($orphanBranches) === 0 ? 'diagnostic-card__number--ok' : 'diagnostic-card__number--warning'; ?>">
                        <?php echo count($orphanBranches); ?>
                    </div>
                </div>
                
                <div class="diagnostic-card <?php echo count($skuFormatViolations) === 0 ? 'diagnostic-card--ok' : 'diagnostic-card--error'; ?>">
                    <div class="diagnostic-card__label">SKU Format Issues</div>
                    <div class="diagnostic-card__number <?php echo count($skuFormatViolations) === 0 ? 'diagnostic-card__number--ok' : 'diagnostic-card__number--error'; ?>">
                        <?php echo count($skuFormatViolations); ?>
                    </div>
                </div>
                
                <div class="diagnostic-card <?php echo count($orphanSkusInStock) === 0 ? 'diagnostic-card--ok' : 'diagnostic-card--warning'; ?>">
                    <div class="diagnostic-card__label">Orphan SKUs (stock.json)</div>
                    <div class="diagnostic-card__number <?php echo count($orphanSkusInStock) === 0 ? 'diagnostic-card__number--ok' : 'diagnostic-card__number--warning'; ?>">
                        <?php echo count($orphanSkusInStock); ?>
                    </div>
                </div>
            </div>
            
            <!-- Orphan Accessories -->
            <?php if (!empty($orphanAccessories)): ?>
                <div class="admin-card" style="margin-bottom: 2rem;">
                    <h2>⚠️ Orphan Accessories (<?php echo count($orphanAccessories); ?>)</h2>
                    <p style="color: #666; margin-bottom: 1rem;">
                        These products have <code>category="accessories"</code> in products.json but no configuration in accessories.json.
                        They appear in Stock/CSV but cannot be managed in the Accessories admin page.
                    </p>
                    <table class="issue-table">
                        <thead>
                            <tr>
                                <th>Product ID</th>
                                <th>Name</th>
                                <th>Variants</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orphanAccessories as $id => $item): ?>
                                <tr>
                                    <td><code><?php echo htmlspecialchars($id); ?></code></td>
                                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                                    <td>
                                        <?php foreach ($item['variants'] as $v): ?>
                                            <span class="issue-tag"><?php echo htmlspecialchars($v['volume'] ?? 'standard'); ?></span>
                                        <?php endforeach; ?>
                                    </td>
                                    <td><?php echo $item['active'] ? '✅ Active' : '❌ Inactive'; ?></td>
                                    <td>
                                        <form method="post" style="display: inline;" onsubmit="return confirm('Create accessory configuration for <?php echo htmlspecialchars($id); ?>?');">
                                            <input type="hidden" name="action" value="sync_orphan_to_accessories">
                                            <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($id); ?>">
                                            <button type="submit" class="btn btn--text" style="color: #28a745;">
                                                ➕ Create Config
                                            </button>
                                        </form>
                                        <form method="post" style="display: inline; margin-left: 0.5rem;" onsubmit="return confirm('⚠️ PERMANENT DELETION\n\nDelete <?php echo htmlspecialchars($id); ?> from all files?\n\nThis will remove:\n- Product from products.json\n- All SKUs from stock.json\n- All branch stock entries\n- All i18n translations\n\nBackups will be created.\n\nContinue?');">
                                            <input type="hidden" name="action" value="delete_orphan_product">
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
            <?php endif; ?>
            
            <!-- Unknown Branches -->
            <?php if (!empty($orphanBranches)): ?>
                <div class="admin-card" style="margin-bottom: 2rem;">
                    <h2>⚠️ Unknown Branches (<?php echo count($orphanBranches); ?>)</h2>
                    <p style="color: #666; margin-bottom: 1rem;">
                        These branch IDs exist in branch_stock.json but are not defined in branches.json.
                        They may be from old imports or deleted branches that weren't properly cleaned up.
                    </p>
                    <table class="issue-table">
                        <thead>
                            <tr>
                                <th>Branch ID</th>
                                <th>SKU Count</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orphanBranches as $branchId => $item): ?>
                                <tr>
                                    <td><code><?php echo htmlspecialchars($branchId); ?></code></td>
                                    <td><?php echo $item['sku_count']; ?> SKUs</td>
                                    <td>
                                        <form method="post" style="display: inline;" onsubmit="return confirm('⚠️ PERMANENT DELETION\n\nDelete branch <?php echo htmlspecialchars($branchId); ?> and remove all <?php echo $item['sku_count']; ?> stock entries?\n\nBackups will be created.\n\nContinue?');">
                                            <input type="hidden" name="action" value="delete_orphan_branch">
                                            <input type="hidden" name="branch_id" value="<?php echo htmlspecialchars($branchId); ?>">
                                            <button type="submit" class="btn btn--text" style="color: var(--color-error);">
                                                🗑️ Remove Branch
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            
            <!-- SKU Format Violations -->
            <?php if (!empty($skuFormatViolations)): ?>
                <div class="admin-card" style="margin-bottom: 2rem;">
                    <h2>❌ SKU Format Violations (<?php echo count($skuFormatViolations); ?>)</h2>
                    <p style="color: #666; margin-bottom: 1rem;">
                        These SKUs do not follow the required 3-part format: <code>PREFIX-VOLUME-FRAGRANCE</code>
                    </p>
                    <table class="issue-table">
                        <thead>
                            <tr>
                                <th>SKU</th>
                                <th>Part Count</th>
                                <th>Product ID</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($skuFormatViolations, 0, 20) as $item): ?>
                                <tr>
                                    <td><code><?php echo htmlspecialchars($item['sku']); ?></code></td>
                                    <td><?php echo $item['parts']; ?> parts (expected 3)</td>
                                    <td><?php echo htmlspecialchars($item['productId']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (count($skuFormatViolations) > 20): ?>
                                <tr>
                                    <td colspan="3" style="text-align: center; color: #666;">
                                        ... and <?php echo count($skuFormatViolations) - 20; ?> more violations
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            
            <!-- Orphan SKUs in Stock -->
            <?php if (!empty($orphanSkusInStock)): ?>
                <div class="admin-card" style="margin-bottom: 2rem;">
                    <h2>⚠️ Orphan SKUs in stock.json (<?php echo count($orphanSkusInStock); ?>)</h2>
                    <p style="color: #666; margin-bottom: 1rem;">
                        These SKUs exist in stock.json but are not generated from the current catalog (products.json + accessories.json).
                        They may be from deleted products or old configurations.
                    </p>
                    <table class="issue-table">
                        <thead>
                            <tr>
                                <th>SKU</th>
                                <th>Product ID</th>
                                <th>Quantity</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($orphanSkusInStock, 0, 20) as $item): ?>
                                <tr>
                                    <td><code><?php echo htmlspecialchars($item['sku']); ?></code></td>
                                    <td><?php echo htmlspecialchars($item['productId']); ?></td>
                                    <td><?php echo $item['quantity']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (count($orphanSkusInStock) > 20): ?>
                                <tr>
                                    <td colspan="3" style="text-align: center; color: #666;">
                                        ... and <?php echo count($orphanSkusInStock) - 20; ?> more orphan SKUs
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <p style="margin-top: 1rem; padding: 1rem; background: #fff3cd; border-radius: 4px; color: #856404;">
                        <strong>Note:</strong> To clean up orphan SKUs, delete the corresponding products or manually edit stock.json.
                    </p>
                </div>
            <?php endif; ?>
            
            <!-- All Clear Message -->
            <?php if ($totalIssues === 0): ?>
                <div class="admin-card" style="text-align: center; padding: 3rem; background: #f0fff4;">
                    <h2 style="color: #28a745; margin-bottom: 1rem;">✅ All Clear!</h2>
                    <p style="color: #666; font-size: 1.1rem;">
                        No data inconsistencies detected. Your catalog, stock, and branches are in good shape.
                    </p>
                    <p style="color: #666; margin-top: 1rem;">
                        <a href="stock.php" class="btn btn--gold">Go to Stock Management</a>
                    </p>
                </div>
            <?php endif; ?>
            
            <!-- Tools & Links -->
            <div class="admin-card" style="margin-top: 2rem; background: #f9f9f9;">
                <h3>Additional Tools</h3>
                <ul style="list-style: none; padding: 0; margin-top: 1rem;">
                    <li style="margin-bottom: 0.5rem;">
                        <a href="stock.php?debug=1" class="btn btn--text" style="color: #4a90e2;">
                            🔍 View SKU Universe Diagnostics
                        </a>
                    </li>
                    <li style="margin-bottom: 0.5rem;">
                        <a href="accessories.php" class="btn btn--text" style="color: #4a90e2;">
                            📦 Manage Accessories
                        </a>
                    </li>
                    <li style="margin-bottom: 0.5rem;">
                        <a href="branches.php" class="btn btn--text" style="color: #4a90e2;">
                            🏢 Manage Branches
                        </a>
                    </li>
                </ul>
                <p style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid #ddd; color: #666; font-size: 0.9rem;">
                    <strong>CLI Validation:</strong> Run <code>php tools/validate_integrity.php</code> from the command line for a detailed integrity check.
                </p>
            </div>
        </main>
    </div>
</body>
</html>
