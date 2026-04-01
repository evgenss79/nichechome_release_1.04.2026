<?php
/**
 * Admin - Consolidated Stock Management
 * Shows all SKUs with branch quantities and totals in one view
 * 
 * NEW FEATURES:
 * - Multi-criteria filtering: category, product, fragrance, size, branch
 * - Sorting: quantity ascending/descending
 * 
 * Query Parameters:
 * - category: Filter by category (e.g., aroma_diffusers)
 * - product_q: Search by product ID or name (substring match)
 * - fragrance: Filter by fragrance code (e.g., bellini)
 * - size: Filter by volume/size (e.g., 125ml)
 * - branch: Filter by branch ID (e.g., branch_001)
 * - sort: Sort by quantity (qty_desc or qty_asc)
 * - filter_name: Legacy product search parameter (kept for compatibility)
 * - sort_by: Legacy sort parameter (kept for compatibility)
 * - debug: Show SKU Universe diagnostics (debug=1)
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/stock/sku_universe.php';

if (!isAdminLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Handle debug mode - show SKU Universe diagnostics
if (isset($_GET['debug']) && $_GET['debug'] === '1') {
    $diagnostics = getSkuUniverseDiagnostics();
    
    // Render debug panel
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>SKU Universe Diagnostics</title>
        <link rel="stylesheet" href="../assets/css/style.css">
        <style>
            body { font-family: monospace; padding: 2rem; background: #f5f5f5; }
            .diag-container { max-width: 1200px; margin: 0 auto; background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
            .diag-header { margin-bottom: 2rem; padding-bottom: 1rem; border-bottom: 3px solid #ddd; }
            .diag-section { margin: 2rem 0; padding: 1rem; border-left: 4px solid #4a90e2; background: #f9f9f9; }
            .diag-section h2 { margin-top: 0; color: #333; }
            .diag-stat { display: inline-block; margin: 0.5rem 1rem 0.5rem 0; padding: 0.5rem 1rem; background: #fff; border: 1px solid #ddd; border-radius: 4px; }
            .diag-stat strong { color: #4a90e2; }
            .diag-list { max-height: 300px; overflow-y: auto; background: #fff; padding: 1rem; border: 1px solid #ddd; margin-top: 0.5rem; }
            .diag-list code { display: block; padding: 0.25rem; }
            .status-pass { color: #28a745; font-weight: bold; font-size: 1.5rem; }
            .status-fail { color: #dc3545; font-weight: bold; font-size: 1.5rem; }
            .btn { display: inline-block; padding: 0.5rem 1rem; background: #4a90e2; color: white; text-decoration: none; border-radius: 4px; margin-top: 1rem; }
            .btn:hover { background: #357abd; }
        </style>
    </head>
    <body>
        <div class="diag-container">
            <div class="diag-header">
                <h1>🔍 SKU Universe Diagnostics</h1>
                <p style="color: #666; margin-top: 0.5rem;">
                    Real-time diagnostic report of SKU consistency across all sources
                </p>
                <div style="margin-top: 1rem;">
                    <?php if ($diagnostics['passed']): ?>
                        <span class="status-pass">✅ PASS - All checks passed!</span>
                    <?php else: ?>
                        <span class="status-fail">❌ FAIL - Issues detected (see below)</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="diag-section">
                <h2>📊 Overview Statistics</h2>
                <div class="diag-stat"><strong>Universe SKUs:</strong> <?php echo $diagnostics['universe_count']; ?></div>
                <div class="diag-stat"><strong>stock.json keys:</strong> <?php echo $diagnostics['stock_keys_count']; ?></div>
                <div class="diag-stat"><strong>branch_stock.json mirror keys:</strong> <?php echo $diagnostics['branch_stock_total_keys_count']; ?></div>
                <div class="diag-stat"><strong>Branches:</strong> <?php echo $diagnostics['branches_count']; ?></div>
            </div>
            
            <div class="diag-section">
                <h2>⚠️ Missing in stock.json</h2>
                <p>SKUs present in Universe but missing in stock.json (<?php echo count($diagnostics['missing_in_stock_json']); ?>):</p>
                <?php if (empty($diagnostics['missing_in_stock_json'])): ?>
                    <p style="color: #28a745;">✅ None - All Universe SKUs present in stock.json</p>
                <?php else: ?>
                    <div class="diag-list">
                        <?php foreach (array_slice($diagnostics['missing_in_stock_json'], 0, 100) as $sku): ?>
                            <code><?php echo htmlspecialchars($sku); ?></code>
                        <?php endforeach; ?>
                        <?php if (count($diagnostics['missing_in_stock_json']) > 100): ?>
                            <p>... and <?php echo count($diagnostics['missing_in_stock_json']) - 100; ?> more</p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="diag-section">
                <h2>⚠️ Missing in branch_stock.json mirror</h2>
                <p>SKUs present in Universe but missing in the compatibility branch_stock.json mirror (<?php echo count($diagnostics['missing_in_branch_stock_json']); ?>):</p>
                <?php if (empty($diagnostics['missing_in_branch_stock_json'])): ?>
                    <p style="color: #28a745;">✅ None - The compatibility mirror is aligned with stock.json</p>
                <?php else: ?>
                    <div class="diag-list">
                        <?php foreach (array_slice($diagnostics['missing_in_branch_stock_json'], 0, 100) as $sku): ?>
                            <code><?php echo htmlspecialchars($sku); ?></code>
                        <?php endforeach; ?>
                        <?php if (count($diagnostics['missing_in_branch_stock_json']) > 100): ?>
                            <p>... and <?php echo count($diagnostics['missing_in_branch_stock_json']) - 100; ?> more</p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="diag-section">
                <h2>🔴 Extra in stock.json</h2>
                <p>SKUs in stock.json but NOT in Universe (<?php echo count($diagnostics['extra_in_stock_json']); ?>):</p>
                <?php if (empty($diagnostics['extra_in_stock_json'])): ?>
                    <p style="color: #28a745;">✅ None - No orphaned SKUs in stock.json</p>
                <?php else: ?>
                    <div class="diag-list">
                        <?php foreach ($diagnostics['extra_in_stock_json'] as $sku): ?>
                            <code><?php echo htmlspecialchars($sku); ?></code>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="diag-section">
                <h2>🔴 Extra in branch_stock.json mirror</h2>
                <p>SKUs in the compatibility branch_stock.json mirror but NOT in Universe (<?php echo count($diagnostics['extra_in_branch_stock_json']); ?>):</p>
                <?php if (empty($diagnostics['extra_in_branch_stock_json'])): ?>
                    <p style="color: #28a745;">✅ None - No orphaned SKUs in the compatibility mirror</p>
                <?php else: ?>
                    <div class="diag-list">
                        <?php foreach ($diagnostics['extra_in_branch_stock_json'] as $sku): ?>
                            <code><?php echo htmlspecialchars($sku); ?></code>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="diag-section">
                <h2>🚫 Format Violations</h2>
                <p>SKUs that don't follow 3-part format (PREFIX-VOLUME-FRAGRANCE) (<?php echo count($diagnostics['format_violations']); ?>):</p>
                <?php if (empty($diagnostics['format_violations'])): ?>
                    <p style="color: #28a745;">✅ None - All SKUs follow 3-part format</p>
                <?php else: ?>
                    <div class="diag-list">
                        <?php foreach ($diagnostics['format_violations'] as $sku): ?>
                            <code style="color: red;"><?php echo htmlspecialchars($sku); ?></code>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="diag-section">
                <h2>🏷️ NA Fragrance SKUs</h2>
                <p>SKUs using fragrance=NA (no fragrance selector) (<?php echo count($diagnostics['na_sku_list']); ?>):</p>
                <?php if (empty($diagnostics['na_sku_list'])): ?>
                    <p style="color: #666;">None found</p>
                <?php else: ?>
                    <div class="diag-list">
                        <?php foreach ($diagnostics['na_sku_list'] as $sku): ?>
                            <code><?php echo htmlspecialchars($sku); ?></code>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div style="margin-top: 2rem; padding-top: 1rem; border-top: 2px solid #ddd;">
                <a href="stock.php" class="btn">« Back to Stock Management</a>
                <a href="?debug=1" class="btn" style="background: #6c757d; margin-left: 0.5rem;">🔄 Refresh Diagnostics</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Prevent browser caching
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$success = '';
$error = '';
$warnings = [];

// Get branches for display
$branches = getAllBranches();

// Handle SKU Universe Sync action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'sync_universe') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid security token. Please refresh the page and try again.';
    } else {
        // Run the sync (not dry run)
        $result = initializeMissingSkuKeys(false);
        
        if ($result['success']) {
            $addedStockCount = count($result['added_to_stock']);
            $addedBranchCount = count($result['added_to_branches']);
            $totalBranchEntries = 0;
            foreach ($result['added_to_branches'] as $branches) {
                $totalBranchEntries += count($branches);
            }
            
            $success = "✅ SKU Universe synchronized successfully! ";
            $success .= "Added {$addedStockCount} SKUs to stock.json and refreshed the compatibility branch_stock.json mirror for {$addedBranchCount} SKUs ({$totalBranchEntries} branch mirror entries). ";
            $success .= "All new STOCK entries initialized with qty=0. Backups created in data/backups/.";
        } else {
            $error = "Failed to synchronize Universe: " . $result['error'];
        }
    }
}

// Handle form submission - Update stock
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_stock') {
    // FIX: TASK 3 - Check edit permission for stock
    if (!hasPermission('edit_stock')) {
        $error = 'You do not have permission to edit stock';
    } elseif (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        // Verify CSRF token
        $error = 'Invalid security token. Please refresh the page and try again.';
    } else {
        $sku = $_POST['sku'] ?? '';
        $submittedBranchQuantities = $_POST['branch_quantity'] ?? [];

        $result = updateConsolidatedStock($sku, $submittedBranchQuantities);
        
        if ($result['success']) {
            $success = "Stock updated successfully for SKU: $sku (Total: {$result['oldTotal']} → {$result['newTotal']})";
        } else {
            $error = "Failed to update stock: " . $result['error'];
        }
    }
}

// Handle template download - redirect to dynamic CSV export
if (isset($_GET['action']) && $_GET['action'] === 'download_template') {
    // Redirect to dynamic CSV export that generates from Universe
    header('Location: export_stock_csv.php');
    exit;
}

// Load consolidated stock view from SKU Universe (complete list)
$consolidatedStock = getConsolidatedStockViewFromUniverse();

// Get filter parameters (support both new and legacy parameter names)
$filterCategory = $_GET['category'] ?? $_GET['filter_category'] ?? '';
$filterProductQuery = $_GET['product_q'] ?? $_GET['filter_name'] ?? '';
$filterFragrance = $_GET['fragrance'] ?? '';
$filterSize = $_GET['size'] ?? '';
$filterBranch = $_GET['branch'] ?? '';
$sortParam = $_GET['sort'] ?? $_GET['sort_by'] ?? '';

// Extract unique values for filter dropdowns from full dataset
$allCategories = [];
$allFragrances = [];
$allSizes = [];
foreach ($consolidatedStock as $item) {
    if (!empty($item['category'])) {
        $allCategories[$item['category']] = true;
    }
    if (!empty($item['fragrance'])) {
        $allFragrances[$item['fragrance']] = true;
    }
    if (!empty($item['volume'])) {
        $allSizes[$item['volume']] = true;
    }
}
$allCategories = array_keys($allCategories);
$allFragrances = array_keys($allFragrances);
$allSizes = array_keys($allSizes);
sort($allCategories);
sort($allFragrances);
// Sort sizes numerically when possible
usort($allSizes, function($a, $b) {
    // Extract numeric values using regex
    preg_match('/\d+/', $a, $matchesA);
    preg_match('/\d+/', $b, $matchesB);
    $numA = isset($matchesA[0]) ? (int)$matchesA[0] : 0;
    $numB = isset($matchesB[0]) ? (int)$matchesB[0] : 0;
    
    if ($numA > 0 && $numB > 0) {
        return $numA - $numB;
    }
    return strcmp($a, $b);
});

// Apply filters
$filteredStock = $consolidatedStock;

// Filter by category
if (!empty($filterCategory)) {
    $filteredStock = array_filter($filteredStock, function($item) use ($filterCategory) {
        return $item['category'] === $filterCategory;
    });
}

// Filter by product (search in productId and product_name)
if (!empty($filterProductQuery)) {
    $filteredStock = array_filter($filteredStock, function($item) use ($filterProductQuery) {
        return stripos($item['product_name'], $filterProductQuery) !== false || 
               stripos($item['productId'], $filterProductQuery) !== false ||
               stripos($item['sku'], $filterProductQuery) !== false;
    });
}

// Filter by fragrance
if (!empty($filterFragrance)) {
    $filteredStock = array_filter($filteredStock, function($item) use ($filterFragrance) {
        return strcasecmp($item['fragrance'], $filterFragrance) === 0;
    });
}

// Filter by size/volume
if (!empty($filterSize)) {
    $filteredStock = array_filter($filteredStock, function($item) use ($filterSize) {
        return $item['volume'] === $filterSize;
    });
}

// Filter by branch (if branch is selected, only show items with that branch and use branch qty for sorting)
$quantityField = 'total'; // Default: use total quantity
if (!empty($filterBranch)) {
    // Verify branch exists
    if (isset($branches[$filterBranch])) {
        // Keep all items but will use branch quantity for sorting
        $quantityField = 'branch:' . $filterBranch;
    }
}

// Sort
if ($sortParam === 'qty_asc' || $sortParam === 'total_asc') {
    uasort($filteredStock, function($a, $b) use ($quantityField, $filterBranch, $branches) {
        if (strpos($quantityField, 'branch:') === 0 && !empty($filterBranch) && isset($branches[$filterBranch])) {
            $qtyA = $a['branches'][$filterBranch] ?? 0;
            $qtyB = $b['branches'][$filterBranch] ?? 0;
        } else {
            $qtyA = $a['total'];
            $qtyB = $b['total'];
        }
        return $qtyA - $qtyB;
    });
} elseif ($sortParam === 'qty_desc' || $sortParam === 'total_desc') {
    uasort($filteredStock, function($a, $b) use ($quantityField, $filterBranch, $branches) {
        if (strpos($quantityField, 'branch:') === 0 && !empty($filterBranch) && isset($branches[$filterBranch])) {
            $qtyA = $a['branches'][$filterBranch] ?? 0;
            $qtyB = $b['branches'][$filterBranch] ?? 0;
        } else {
            $qtyA = $a['total'];
            $qtyB = $b['total'];
        }
        return $qtyB - $qtyA;
    });
} elseif ($sortParam === 'sku') {
    ksort($filteredStock);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consolidated Stock - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600&family=Manrope:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        .stock-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }
        .stock-table th,
        .stock-table td {
            padding: 0.75rem 0.5rem;
            border: 1px solid #ddd;
            text-align: left;
        }
        .stock-table th {
            background: var(--color-gold);
            color: white;
            font-weight: 600;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .stock-table tbody tr:hover {
            background: #f9f9f9;
        }
        .stock-table input[type="number"] {
            width: 70px;
            padding: 0.25rem;
            text-align: center;
            border: 1px solid #ccc;
            border-radius: 3px;
        }
        .stock-table .total-cell {
            background: #f0f0f0;
            font-weight: 600;
        }
        .save-btn {
            padding: 0.25rem 0.75rem;
            font-size: 0.85rem;
        }
        .stock-table .read-only-branch {
            background: #faf7f0;
            color: #6a6258;
            font-weight: 600;
            text-align: center;
        }
        .stock-table .branch-input {
            width: 70px;
            padding: 0.25rem;
            text-align: center;
            border: 1px solid #ccc;
            border-radius: 3px;
        }
        .stock-table .total-display {
            background: #f5f5f5;
            color: #333;
            font-weight: 600;
        }
        .import-section {
            background: #f9f9f9;
            padding: 1.5rem;
            border-radius: 4px;
            margin-bottom: 1.5rem;
        }
        .template-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }
        .inline-row-form {
            display: contents;
        }
    </style>
    <script>
        'use strict';
        
        function resolveRowScope(contextElement) {
            if (!contextElement) {
                return null;
            }

            if (contextElement.matches && contextElement.matches('tr')) {
                return contextElement;
            }

            return contextElement.closest ? contextElement.closest('tr') : null;
        }

        function calculateRowTotal(contextElement, strictMode) {
            const rowElement = resolveRowScope(contextElement);
            const scopeElement = rowElement || contextElement;
            const branchInputs = Array.from(scopeElement.querySelectorAll('input[data-branch-quantity="1"]'));
            let total = 0;

            for (const input of branchInputs) {
                const quantity = parseInt(input.value, 10);
                if (Number.isNaN(quantity) || quantity < 0) {
                    if (strictMode) {
                        alert('Branch stock quantities must be non-negative whole numbers.');
                        input.focus();
                        return null;
                    }
                    return null;
                }
                total += quantity;
            }

            const totalInput = scopeElement.querySelector('input[data-total-display="1"]');
            if (totalInput) {
                totalInput.value = total;
            }

            return total;
        }

        function validateRow(contextElement) {
            return calculateRowTotal(contextElement, true) !== null;
        }

        function updateRowTotal(contextElement) {
            if (!contextElement) {
                return;
            }

            const rowElement = resolveRowScope(contextElement);
            const total = calculateRowTotal(rowElement || contextElement, false);
            if (total === null) {
                const scopeElement = rowElement || contextElement;
                const totalInput = scopeElement.querySelector('input[data-total-display="1"]');
                if (totalInput) {
                    totalInput.value = '';
                }
            }
        }

        document.addEventListener('input', function(event) {
            if (event.target.matches('input[data-branch-quantity="1"]')) {
                const row = event.target.closest('tr');
                if (row) {
                    updateRowTotal(row);
                }
            }
        });
    </script>
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
                <a href="stock.php" class="admin-sidebar__link active">Stock</a>
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
                <h1>Consolidated Stock Management</h1>
                <p style="color: #666; margin-top: 0.5rem;">
                    Manage branch quantities in one place. Each branch stays independently editable and TOTAL is calculated automatically from the branch values.
                    Total items: <?php echo count($consolidatedStock); ?> | 
                    Displayed: <?php echo count($filteredStock); ?>
                    <span style="margin-left: 1rem;">|</span>
                    <a href="?debug=1" style="margin-left: 1rem; color: #4a90e2; text-decoration: none; font-weight: 600;">
                        🔍 View SKU Universe Diagnostics
                    </a>
                </p>
            </div>
            
            <?php if ($success): ?>
                <div class="alert alert--success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert--error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php foreach ($warnings as $warning): ?>
                <div class="alert" style="background: #fff3cd; border-color: #ffc107; color: #856404;">
                    <?php echo htmlspecialchars($warning); ?>
                </div>
            <?php endforeach; ?>
            
            <!-- CSV Import and Universe Sync Section -->
            <div class="import-section">
                <h2 style="margin-top: 0;">SKU Universe & Data Management</h2>
                <p style="color: #666;">Synchronize stock files with product catalog and manage CSV imports.</p>
                
                <div class="template-buttons">
                    <form method="post" action="" style="display: inline;">
                        <input type="hidden" name="action" value="sync_universe">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <button type="submit" class="btn btn--gold" 
                                onclick="return confirm('This will add missing SKUs from Universe to stock files with qty=0. Backups will be created. Continue?')">
                            🔄 Sync SKU Universe
                        </button>
                    </form>
                    <a href="?action=download_template&format=csv" class="btn">
                        📥 Download CSV Template
                    </a>
                    <a href="stock_import.php" class="btn">
                        📤 Upload Stock File
                    </a>
                </div>
                <p style="color: #999; font-size: 0.9em; margin-top: 0.5rem;">
                    <strong>Sync Universe:</strong> Adds missing SKUs from product catalog to stock.json (qty=0) and initializes missing branch_stock.json entries.<br>
                    <strong>CSV:</strong> Import/export uses per-branch quantities plus a computed TOTAL column (no Excel support). You can convert Excel files to CSV using Excel or Google Sheets.
                </p>
            </div>
            
            <!-- Filters -->
            <div class="admin-card" style="margin-bottom: 1.5rem;">
                <h3 style="margin-top: 0; margin-bottom: 1rem;">Filter & Sort Stock</h3>
                <form method="get" action="" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; align-items: end;">
                    <!-- Preserve debug parameter if present -->
                    <?php if (isset($_GET['debug'])): ?>
                        <input type="hidden" name="debug" value="<?php echo htmlspecialchars($_GET['debug']); ?>">
                    <?php endif; ?>
                    
                    <div>
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Category</label>
                        <select name="category" style="width: 100%; padding: 0.5rem;">
                            <option value="">All Categories</option>
                            <?php foreach ($allCategories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $filterCategory === $cat ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $cat))); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Product Search</label>
                        <input type="text" name="product_q" value="<?php echo htmlspecialchars($filterProductQuery); ?>" 
                               placeholder="Product name/ID/SKU..." style="width: 100%; padding: 0.5rem;">
                    </div>
                    
                    <div>
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Fragrance</label>
                        <select name="fragrance" style="width: 100%; padding: 0.5rem;">
                            <option value="">All Fragrances</option>
                            <?php foreach ($allFragrances as $frag): ?>
                                <option value="<?php echo htmlspecialchars($frag); ?>" <?php echo $filterFragrance === $frag ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $frag))); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Size/Pack</label>
                        <select name="size" style="width: 100%; padding: 0.5rem;">
                            <option value="">All Sizes</option>
                            <?php foreach ($allSizes as $sz): ?>
                                <option value="<?php echo htmlspecialchars($sz); ?>" <?php echo $filterSize === $sz ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($sz); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Branch</label>
                        <select name="branch" style="width: 100%; padding: 0.5rem;">
                            <option value="">All Branches</option>
                            <?php foreach ($branches as $branchId => $branchName): ?>
                                <option value="<?php echo htmlspecialchars($branchId); ?>" <?php echo $filterBranch === $branchId ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($branchName); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Sort By</label>
                        <select name="sort" style="width: 100%; padding: 0.5rem;">
                            <option value="">Default (SKU)</option>
                            <option value="sku" <?php echo $sortParam === 'sku' ? 'selected' : ''; ?>>SKU (A-Z)</option>
                            <option value="qty_asc" <?php echo $sortParam === 'qty_asc' || $sortParam === 'total_asc' ? 'selected' : ''; ?>>Quantity: Low → High</option>
                            <option value="qty_desc" <?php echo $sortParam === 'qty_desc' || $sortParam === 'total_desc' ? 'selected' : ''; ?>>Quantity: High → Low</option>
                        </select>
                    </div>
                    
                    <div style="display: flex; gap: 0.5rem;">
                        <button type="submit" class="btn btn--gold">Apply Filters</button>
                        <a href="stock.php" class="btn" style="text-align: center; padding: 0.5rem 1rem; text-decoration: none;">Reset</a>
                    </div>
                </form>
            </div>
            
            <!-- Stock Table -->
            <div class="admin-card" style="overflow-x: auto;">
                <table class="stock-table">
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Product</th>
                            <th>Volume</th>
                            <th>Fragrance</th>
                            <?php foreach ($branches as $branchId => $branchName): ?>
                                <th><?php echo htmlspecialchars($branchName); ?></th>
                            <?php endforeach; ?>
                            <th class="total-cell">TOTAL</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($filteredStock)): ?>
                            <tr>
                                <td colspan="<?php echo 5 + count($branches); ?>" style="text-align: center; padding: 2rem; color: #999;">
                                    No stock items found. Adjust your filters or add products first.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($filteredStock as $sku => $item): ?>
                                <tr>
                                    <td><code><?php echo htmlspecialchars($sku); ?></code></td>
                                    <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                    <td><?php echo htmlspecialchars($item['volume']); ?></td>
                                    <td><?php echo htmlspecialchars($item['fragrance']); ?></td>
                                    
                                    <form method="post" action="" onsubmit="return validateRow(this)" data-sku="<?php echo htmlspecialchars($sku); ?>" class="inline-row-form">
                                        <input type="hidden" name="action" value="update_stock">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <input type="hidden" name="sku" value="<?php echo htmlspecialchars($sku); ?>">
                                        
                                        <?php foreach ($branches as $branchId => $branchName): ?>
                                            <td>
                                                <input type="number"
                                                       class="branch-input"
                                                       name="branch_quantity[<?php echo htmlspecialchars($branchId); ?>]"
                                                       value="<?php echo (int)($item['branches'][$branchId] ?? 0); ?>"
                                                       min="0"
                                                       oninput="updateRowTotal(this)"
                                                       data-branch-quantity="1">
                                            </td>
                                        <?php endforeach; ?>
                                        
                                        <td class="total-cell">
                                            <input type="number" 
                                                   class="total-display"
                                                   value="<?php echo (int)$item['total']; ?>" 
                                                   readonly
                                                   tabindex="-1"
                                                   data-total-display="1">
                                        </td>
                                        
                                        <td>
                                            <button type="submit" class="btn btn--text save-btn">💾 Save</button>
                                        </td>
                                    </form>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="admin-card" style="margin-top: 1.5rem; background: #f0f8ff; border: 1px solid #4a90e2;">
                <h3 style="margin-top: 0; color: #4a90e2;">ℹ️ Usage Instructions</h3>
                <ul style="color: #666;">
                    <li><strong>Filtering:</strong> Use filter dropdowns to narrow down stock items by category, product, fragrance, size, or branch</li>
                    <li><strong>Multi-criteria:</strong> Filters work together (AND logic) - combine multiple filters for precise results</li>
                    <li><strong>Product search:</strong> Search by product name, product ID, or SKU (partial match supported)</li>
                    <li><strong>Branch filter:</strong> Select a branch to focus on specific location stock levels</li>
                    <li><strong>Sorting:</strong> Sort by quantity (low→high or high→low) - when branch is selected, sorts by that branch's quantity</li>
                    <li><strong>Edit quantities:</strong> Change branch quantities independently, then click Save</li>
                    <li><strong>Validation:</strong> Branch quantities must be non-negative integers</li>
                    <li><strong>TOTAL:</strong> TOTAL is read-only and always equals the sum of branch quantities</li>
                    <li><strong>CSV Import:</strong> Use template above to bulk import stock data</li>
                    <li><strong>Backups:</strong> All changes create timestamped backups in data/backups/</li>
                    <li><strong>Logs:</strong> All changes are logged to logs/stock.log</li>
                </ul>
            </div>
        </main>
    </div>
</body>
</html>
