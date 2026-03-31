<?php
/**
 * Admin - Branches Management
 * 
 * NEW FEATURES:
 * - CSV Export with date selection: Export branch stock as CSV with snapshot support
 * 
 * Query Parameters:
 * - branch_id: View/edit specific branch stock
 * - edit: Edit branch details
 * - filter_category: Filter by category
 * - filter_name: Filter by product name
 * - sort_by: Sort by quantity
 * 
 * CSV Export Endpoint: admin/export_branch_stock_csv.php?branch_id=...&date=YYYY-MM-DD
 */

require_once __DIR__ . '/../init.php';

if (!isAdminLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Prevent browser caching to ensure fresh branch stock data is always displayed
// This is critical because Safari and other browsers may cache the page,
// showing stale branch stock quantities even after orders are placed.
// Without these headers, different browsers/tabs may show different stock levels.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

$products = loadJSON('products.json');
$accessories = loadJSON('accessories.json');
$success = '';
$error = '';

// Get selected branch for stock management
$selectedBranchId = $_GET['branch_id'] ?? '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CRITICAL FIX: Reload branches and branch stock inside POST handler to get the latest data
    // This prevents overwriting stock quantities that were decreased by checkouts
    // between the time the page was loaded and the time the form was submitted.
    $branches = loadBranches();
    $branchStock = loadBranchStock();
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_branch') {
        $branchId = trim($_POST['branch_id'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $active = isset($_POST['active']);
        
        if (empty($branchId)) {
            $error = 'Branch ID is required.';
        } elseif (!preg_match('/^[a-z0-9_]+$/', $branchId)) {
            $error = 'Branch ID must contain only lowercase letters, numbers, and underscores.';
        } elseif (empty($name)) {
            $error = 'Branch name is required.';
        } else {
            $branches[$branchId] = [
                'id' => $branchId,
                'name' => $name,
                'address' => $address,
                'active' => $active
            ];
            
            // Initialize branch stock if not exists
            if (!isset($branchStock[$branchId])) {
                $branchStock[$branchId] = [];
            }
            
            if (saveBranches($branches) && saveBranchStock($branchStock)) {
                $success = 'Branch saved successfully.';
            } else {
                $error = 'Failed to save branch.';
            }
        }
    } elseif ($action === 'delete_branch') {
        $branchId = $_POST['branch_id'] ?? '';
        if (empty($branchId)) {
            $error = 'Branch ID is required for deletion.';
        } else {
            // Use shared delete function from helpers.php
            $result = deleteBranch($branchId);
            
            if ($result['success']) {
                $details = $result['details'];
                $success = "Branch '$branchId' deleted successfully! ";
                $success .= "Removed " . $details['stock_entries_removed'] . " stock entries from branch_stock.json.";
                
                // Reload data
                $branches = loadBranches();
                $branchStock = loadBranchStock();
            } else {
                $error = "Failed to delete branch: " . $result['error'];
            }
        }
    } elseif ($action === 'update_stock') {
        $branchId = $_POST['branch_id'] ?? '';
        $sku = $_POST['sku'] ?? '';
        $quantity = max(0, intval($_POST['quantity'] ?? 0));
        
        // RACE CONDITION PROTECTION: Check if branch_stock.json was modified since page load
        // This prevents admin from unknowingly overwriting stock changes made by checkouts
        $branchStockFilePath = __DIR__ . '/../data/branch_stock.json';
        $currentFileTime = filemtime($branchStockFilePath);
        $pageLoadFileTime = intval($_POST['file_mtime'] ?? 0);
        
        error_log("ADMIN BRANCH STOCK UPDATE: Branch '$branchId', SKU '$sku', New Quantity: $quantity");
        error_log("ADMIN BRANCH STOCK UPDATE: File time check - Page load: $pageLoadFileTime, Current: $currentFileTime");
        
        if ($pageLoadFileTime > 0 && $currentFileTime > $pageLoadFileTime) {
            // File was modified by another process (likely a checkout) since page load
            $oldQty = $branchStock[$branchId][$sku]['quantity'] ?? 0;
            
            error_log("ADMIN BRANCH STOCK UPDATE: WARNING - Branch stock file was modified since page load!");
            error_log("ADMIN BRANCH STOCK UPDATE: Branch '$branchId', SKU '$sku' - Current file has: $oldQty");
            error_log("ADMIN BRANCH STOCK UPDATE: Admin attempted to set: $quantity");
            
            $error = "Warning: Branch stock was modified by another process (likely a customer order) since you loaded this page. " .
                     "The current quantity for branch '$branchId' SKU '$sku' is $oldQty. Please refresh the page and try again if you still want to modify it.";
        } else {
            // Safe to proceed - file hasn't changed
            if ($branchId && $sku) {
                if (!isset($branchStock[$branchId])) {
                    $branchStock[$branchId] = [];
                }
                
                $oldQty = $branchStock[$branchId][$sku]['quantity'] ?? 0;
                $branchStock[$branchId][$sku] = ['quantity' => $quantity];
                
                error_log("ADMIN BRANCH STOCK UPDATE: Branch '$branchId', SKU '$sku' quantity changed from $oldQty to $quantity");
                
                if (saveBranchStock($branchStock)) {
                    $success = 'Stock updated successfully.';
                    error_log("ADMIN BRANCH STOCK UPDATE: Successfully saved branch_stock.json");
                } else {
                    $error = 'Failed to update stock.';
                    error_log("ADMIN BRANCH STOCK UPDATE: ERROR - Failed to save branch_stock.json");
                }
            }
        }
        
        // Stay on same branch page
        $selectedBranchId = $branchId;
    }
}

// Reload data after changes (to display updated values and avoid stale data)
$branches = loadBranches();
$branchStock = loadBranchStock();

// Capture file modification time for race condition detection
// This timestamp will be included in each form, allowing us to detect if the file
// was modified between page load and form submission
$branchStockFilePath = __DIR__ . '/../data/branch_stock.json';
$branchStockFileMtime = file_exists($branchStockFilePath) ? filemtime($branchStockFilePath) : 0;

// Update selected branch reference after reload
$selectedBranch = $selectedBranchId && isset($branches[$selectedBranchId]) ? $branches[$selectedBranchId] : null;

// Build stock items list for selected branch
$stockItems = [];
$filterCategory = $_GET['filter_category'] ?? '';
$filterName = $_GET['filter_name'] ?? '';
$sortBy = $_GET['sort_by'] ?? '';

if ($selectedBranch) {
    foreach ($products as $productId => $product) {
        if (empty($product['active'])) continue;
        
        $category = $product['category'] ?? '';
        $nameKey = $product['name_key'] ?? ('product.' . $productId . '.name');
        $productName = I18N::t($nameKey, ucfirst(str_replace('_', ' ', $productId)));
        $variants = $product['variants'] ?? [];
        
        // Determine allowed fragrances
        $allowedFragrances = [];
        if ($category === 'accessories') {
            if (isset($product['allowed_fragrances'])) {
                $allowedFragrances = $product['allowed_fragrances'];
            } elseif (isset($accessories[$productId]['allowed_fragrances'])) {
                $allowedFragrances = $accessories[$productId]['allowed_fragrances'];
            } else {
                $allowedFragrances = allowedFragrances($category);
            }
        } elseif ($category === 'limited_edition') {
            if (isset($product['fragrance'])) {
                $allowedFragrances = [$product['fragrance']];
            } else {
                $allowedFragrances = allowedFragrances($category);
            }
        } else {
            $allowedFragrances = allowedFragrances($category);
        }
        
        foreach ($variants as $variant) {
            $volume = $variant['volume'] ?? 'standard';
            
            foreach ($allowedFragrances as $fragranceCode) {
                $sku = generateSKU($productId, $volume, $fragranceCode);
                $fragranceName = I18N::t('fragrance.' . $fragranceCode . '.name', ucfirst(str_replace('_', ' ', $fragranceCode)));
                
                $quantity = $branchStock[$selectedBranchId][$sku]['quantity'] ?? 0;
                
                $stockItems[] = [
                    'sku' => $sku,
                    'productId' => $productId,
                    'productName' => $productName,
                    'category' => $category,
                    'volume' => $volume,
                    'fragrance' => $fragranceCode,
                    'fragranceName' => $fragranceName,
                    'quantity' => $quantity
                ];
            }
        }
    }
    
    // Apply filters
    $filteredStockItems = $stockItems;
    
    // Filter by category
    if (!empty($filterCategory)) {
        $filteredStockItems = array_filter($filteredStockItems, function($item) use ($filterCategory) {
            return $item['category'] === $filterCategory;
        });
    }
    
    // Filter by product name
    if (!empty($filterName)) {
        $filteredStockItems = array_filter($filteredStockItems, function($item) use ($filterName) {
            return stripos($item['productName'], $filterName) !== false;
        });
    }
    
    // Sort based on sort parameter
    if ($sortBy === 'quantity_asc') {
        usort($filteredStockItems, function($a, $b) {
            return $a['quantity'] - $b['quantity'];
        });
    } elseif ($sortBy === 'quantity_desc') {
        usort($filteredStockItems, function($a, $b) {
            return $b['quantity'] - $a['quantity'];
        });
    } else {
        // Default sort: by category, then product name
        usort($filteredStockItems, function($a, $b) {
            $catCompare = strcmp($a['category'], $b['category']);
            if ($catCompare !== 0) return $catCompare;
            return strcmp($a['productName'], $b['productName']);
        });
    }
    
    // Get unique categories for filter dropdown
    $categories = array_unique(array_column($stockItems, 'category'));
    sort($categories);
} else {
    $filteredStockItems = [];
    $categories = [];
}

$editingBranchId = $_GET['edit'] ?? '';
$editingBranch = $editingBranchId && isset($branches[$editingBranchId]) ? $branches[$editingBranchId] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Branches - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600&family=Manrope:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        .branch-status { font-weight: 600; }
        .branch-status--active { color: var(--color-success); }
        .branch-status--inactive { color: var(--color-error); }
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
                <a href="branches.php" class="admin-sidebar__link active">Branches</a>
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
                <h1><?php echo $selectedBranch ? 'Branch Stock: ' . htmlspecialchars($selectedBranch['name']) : 'Branches Management'; ?></h1>
                <?php if ($selectedBranch): ?>
                    <a href="branches.php" class="btn btn--ghost">← Back to Branches</a>
                <?php endif; ?>
            </div>
            
            <?php if ($success): ?>
                <div class="alert alert--success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert--error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($selectedBranch): ?>
                <!-- Branch Stock Management -->
                <div class="admin-card" style="margin-bottom: 1.5rem;">
                    <p style="margin-bottom: 1rem; color: #666;">
                        Manage stock quantities for <strong><?php echo htmlspecialchars($selectedBranch['name']); ?></strong>
                        (<?php echo htmlspecialchars($selectedBranch['address']); ?>)
                    </p>
                    <p style="color: #666; margin-bottom: 1rem;">Total items: <?php echo count($stockItems); ?> | Filtered: <?php echo count($filteredStockItems); ?></p>
                    
                    <!-- Filters -->
                    <form method="get" action="" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: end; margin-bottom: 1.5rem; padding: 1rem; background: #f9f9f9; border-radius: 4px;">
                        <input type="hidden" name="branch_id" value="<?php echo htmlspecialchars($selectedBranchId); ?>">
                        
                        <div>
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Filter by Category</label>
                            <select name="filter_category" style="width: 100%; padding: 0.5rem;">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $filterCategory === $cat ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $cat))); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Filter by Product Name</label>
                            <input type="text" name="filter_name" value="<?php echo htmlspecialchars($filterName); ?>" 
                                   placeholder="Search products..." style="width: 100%; padding: 0.5rem;">
                        </div>
                        
                        <div>
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Sort by Quantity</label>
                            <select name="sort_by" style="width: 100%; padding: 0.5rem;">
                                <option value="">Default (Category, Name)</option>
                                <option value="quantity_asc" <?php echo $sortBy === 'quantity_asc' ? 'selected' : ''; ?>>Quantity: Low to High</option>
                                <option value="quantity_desc" <?php echo $sortBy === 'quantity_desc' ? 'selected' : ''; ?>>Quantity: High to Low</option>
                            </select>
                        </div>
                        
                        <div style="display: flex; gap: 0.5rem;">
                            <button type="submit" class="btn btn--gold">Apply Filters</button>
                            <a href="branches.php?branch_id=<?php echo htmlspecialchars($selectedBranchId); ?>" class="btn" style="text-align: center; padding: 0.5rem 1rem; text-decoration: none;">Clear</a>
                        </div>
                    </form>
                </div>
                
                <!-- CSV Export Section -->
                <div class="admin-card" style="margin-bottom: 1.5rem; background: #f0f8ff; border: 1px solid #4a90e2;">
                    <h3 style="margin-top: 0; color: #4a90e2;">📥 Export Branch Stock to CSV</h3>
                    <p style="color: #666; margin-bottom: 1rem;">
                        Export stock data for this branch as a CSV file. You can select a specific date to export a historical snapshot.
                    </p>
                    <p style="color: #999; font-size: 0.9em; margin-bottom: 1rem;">
                        ℹ️ <strong>Note:</strong> Exports are based on available JSON snapshots created during stock updates. 
                        If no snapshot exists for the selected date, current data will be exported with a notice.
                    </p>
                    <form method="get" action="export_branch_stock_csv.php" style="display: flex; gap: 1rem; align-items: end; flex-wrap: wrap;">
                        <input type="hidden" name="branch_id" value="<?php echo htmlspecialchars($selectedBranchId); ?>">
                        
                        <div>
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Export Date</label>
                            <input type="date" 
                                   name="date" 
                                   value="<?php echo date('Y-m-d'); ?>" 
                                   max="<?php echo date('Y-m-d'); ?>"
                                   style="padding: 0.5rem; border: 1px solid #ccc; border-radius: 4px;">
                        </div>
                        
                        <div>
                            <button type="submit" class="btn btn--gold">📥 Export CSV</button>
                        </div>
                    </form>
                </div>
                
                <div class="admin-card">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Product</th>
                                <th>Volume</th>
                                <th>Fragrance</th>
                                <th>SKU</th>
                                <th>Quantity</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($filteredStockItems as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['category']); ?></td>
                                    <td><?php echo htmlspecialchars($item['productName']); ?></td>
                                    <td><?php echo htmlspecialchars($item['volume']); ?></td>
                                    <td><?php echo htmlspecialchars($item['fragranceName']); ?></td>
                                    <td><code><?php echo htmlspecialchars($item['sku']); ?></code></td>
                                    <td>
                                        <form method="post" action="" style="display: flex; gap: 0.5rem; align-items: center;">
                                            <input type="hidden" name="action" value="update_stock">
                                            <input type="hidden" name="branch_id" value="<?php echo htmlspecialchars($selectedBranchId); ?>">
                                            <input type="hidden" name="sku" value="<?php echo htmlspecialchars($item['sku']); ?>">
                                            <!-- File modification time for race condition detection -->
                                            <input type="hidden" name="file_mtime" value="<?php echo $branchStockFileMtime; ?>">
                                            <input type="number" 
                                                   name="quantity" 
                                                   value="<?php echo (int)$item['quantity']; ?>" 
                                                   min="0" 
                                                   style="width: 70px; padding: 0.25rem; text-align: center;">
                                    </td>
                                    <td>
                                            <button type="submit" class="btn btn--text">Save</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <!-- Branches List -->
                <div class="admin-card">
                    <h2>All Branches</h2>
                    <table class="admin-table" style="margin-top: 1rem;">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Address</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($branches)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 2rem; color: #999;">
                                        No branches found. Create one using the form below.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($branches as $id => $branch): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($id); ?></td>
                                        <td><?php echo htmlspecialchars($branch['name'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($branch['address'] ?? ''); ?></td>
                                        <td>
                                            <span class="branch-status <?php echo ($branch['active'] ?? false) ? 'branch-status--active' : 'branch-status--inactive'; ?>">
                                                <?php echo ($branch['active'] ?? false) ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="?edit=<?php echo urlencode($id); ?>" class="btn btn--text">Edit</a>
                                            <a href="?branch_id=<?php echo urlencode($id); ?>" class="btn btn--text">Stock</a>
                                            <form method="post" action="" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this branch?');">
                                                <input type="hidden" name="action" value="delete_branch">
                                                <input type="hidden" name="branch_id" value="<?php echo htmlspecialchars($id); ?>">
                                                <button type="submit" class="btn btn--text" style="color: var(--color-error);">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Add/Edit Branch Form -->
                <div class="admin-card" style="margin-top: 2rem;">
                    <h2><?php echo $editingBranch ? 'Edit Branch' : 'Add New Branch'; ?></h2>
                    <form method="post" action="" style="margin-top: 1rem;">
                        <input type="hidden" name="action" value="save_branch">
                        
                        <div class="form-group">
                            <label for="branch_id">Branch ID *</label>
                            <input type="text" 
                                   id="branch_id" 
                                   name="branch_id" 
                                   required 
                                   pattern="[a-z0-9_]+"
                                   value="<?php echo $editingBranch ? htmlspecialchars($editingBranch['id']) : ''; ?>"
                                   <?php echo $editingBranch ? 'readonly style="background: var(--color-sand);"' : ''; ?>>
                            <small style="color: #666; display: block; margin-top: 0.25rem;">
                                Only lowercase letters, numbers, and underscores (e.g., branch_zurich)
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <label for="name">Branch Name *</label>
                            <input type="text" 
                                   id="name" 
                                   name="name" 
                                   required 
                                   value="<?php echo $editingBranch ? htmlspecialchars($editingBranch['name']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="address">Address</label>
                            <input type="text" 
                                   id="address" 
                                   name="address" 
                                   value="<?php echo $editingBranch ? htmlspecialchars($editingBranch['address']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <input type="checkbox" 
                                       name="active" 
                                       <?php echo ($editingBranch && ($editingBranch['active'] ?? false)) || !$editingBranch ? 'checked' : ''; ?>>
                                Active (available for pickup)
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn--gold">Save Branch</button>
                            <?php if ($editingBranch): ?>
                                <a href="branches.php" class="btn" style="margin-left: 1rem;">Cancel</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
