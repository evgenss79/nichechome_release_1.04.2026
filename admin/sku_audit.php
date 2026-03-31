<?php
/**
 * Admin - SKU Audit Dashboard
 * Shows discrepancies between catalog, stock.json, and branch_stock.json
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/stock/sku_universe.php';

if (!isAdminLoggedIn()) {
    header('Location: login.php');
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
$initPreview = null;

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid security token. Please refresh the page and try again.';
    } else {
        if ($_POST['action'] === 'preview_init') {
            // Dry run preview
            $initPreview = initializeMissingSkuKeys(true);
        } elseif ($_POST['action'] === 'execute_init') {
            // Execute initialization
            $result = initializeMissingSkuKeys(false);
            if ($result['success']) {
                $success = 'Successfully initialized missing SKU keys. Added ' . 
                          count($result['added_to_stock']) . ' SKUs to stock.json and ' .
                          count($result['added_to_branches']) . ' SKUs to branches.';
                
                // Log to audit log
                $logMsg = "SKU Universe Initialization executed: " . 
                         count($result['added_to_stock']) . " SKUs added to stock.json, " .
                         count($result['added_to_branches']) . " SKUs added to branches";
                error_log($logMsg);
                
                // Also log to stock audit log
                $auditLog = __DIR__ . '/../logs/stock_sku_audit.log';
                $timestamp = date('Y-m-d H:i:s');
                file_put_contents($auditLog, "[$timestamp] $logMsg\n", FILE_APPEND);
            } else {
                $error = 'Failed to initialize SKU keys: ' . $result['error'];
            }
        }
    }
}

// Handle export
if (isset($_GET['action']) && $_GET['action'] === 'export') {
    $format = $_GET['format'] ?? 'json';
    $audit = getSkuAuditReport();
    
    if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="sku_audit_report_' . date('Y-m-d_H-i-s') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Header
        fputcsv($output, ['Section', 'SKU', 'Product', 'Category', 'Volume', 'Fragrance', 'In Catalog', 'In Stock.json', 'In Branches']);
        
        // All SKUs
        foreach ($audit['universe'] as $sku => $data) {
            fputcsv($output, [
                'ALL',
                $sku,
                $data['product_name'],
                $data['category'],
                $data['volume'],
                $data['fragrance'],
                $data['in_catalog'] ? 'YES' : 'NO',
                $data['in_stock_json'] ? 'YES' : 'NO',
                $data['in_any_branch_json'] ? 'YES' : 'NO'
            ]);
        }
        
        fclose($output);
        exit;
    } else {
        // JSON export
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="sku_audit_report_' . date('Y-m-d_H-i-s') . '.json"');
        echo json_encode($audit, JSON_PRETTY_PRINT);
        exit;
    }
}

// Get audit report
$audit = getSkuAuditReport();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SKU Audit - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600&family=Manrope:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        .audit-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            border: 1px solid #ddd;
            text-align: center;
        }
        .stat-card__value {
            font-size: 2.5rem;
            font-weight: 600;
            color: var(--color-gold);
            margin-bottom: 0.5rem;
        }
        .stat-card__label {
            color: #666;
            font-size: 0.9rem;
        }
        .discrepancy-section {
            margin-bottom: 2rem;
        }
        .discrepancy-list {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #ddd;
            padding: 1rem;
            background: #f9f9f9;
            border-radius: 4px;
        }
        .discrepancy-item {
            padding: 0.5rem;
            margin-bottom: 0.5rem;
            background: white;
            border-radius: 3px;
            font-family: monospace;
            font-size: 0.9rem;
        }
        .init-preview {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        .init-preview h3 {
            margin-top: 0;
            color: #856404;
        }
        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
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
                <a href="sku_audit.php" class="admin-sidebar__link active">SKU Audit</a>
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
                <h1>SKU Audit Dashboard</h1>
                <p style="color: #666; margin-top: 0.5rem;">
                    Complete analysis of all SKUs from catalog, stock.json, and branch_stock.json
                </p>
            </div>
            
            <?php if ($success): ?>
                <div class="alert alert--success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert--error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($initPreview): ?>
                <div class="init-preview">
                    <h3>🔍 Initialization Preview (Dry Run)</h3>
                    <p><strong>Will add to stock.json:</strong> <?php echo count($initPreview['added_to_stock']); ?> SKUs</p>
                    <?php if (count($initPreview['added_to_stock']) > 0): ?>
                        <div style="max-height: 150px; overflow-y: auto; background: white; padding: 0.5rem; border-radius: 4px; font-family: monospace; font-size: 0.85rem; margin: 0.5rem 0;">
                            <?php foreach (array_slice($initPreview['added_to_stock'], 0, 20) as $sku): ?>
                                <?php echo htmlspecialchars($sku); ?><br>
                            <?php endforeach; ?>
                            <?php if (count($initPreview['added_to_stock']) > 20): ?>
                                ... and <?php echo count($initPreview['added_to_stock']) - 20; ?> more
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <p><strong>Will add to branches:</strong> <?php echo count($initPreview['added_to_branches']); ?> SKUs (across all branches)</p>
                    
                    <p style="color: #856404; margin-top: 1rem;">
                        ⚠️ <strong>Important:</strong> This will ONLY add missing keys with quantity=0. Existing quantities will NOT be changed.
                    </p>
                    
                    <form method="post" action="" style="margin-top: 1rem;">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="action" value="execute_init">
                        <button type="submit" class="btn" style="background: #28a745; color: white;" 
                                onclick="return confirm('Are you sure you want to initialize missing SKU keys? This will add keys with quantity=0.')">
                            ✅ Execute Initialization
                        </button>
                    </form>
                </div>
            <?php endif; ?>
            
            <!-- Statistics -->
            <div class="admin-card" style="margin-bottom: 2rem;">
                <h2>SKU Statistics</h2>
                <div class="audit-stats">
                    <div class="stat-card">
                        <div class="stat-card__value"><?php echo $audit['total_universe']; ?></div>
                        <div class="stat-card__label">Total in Universe</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-card__value"><?php echo $audit['total_catalog']; ?></div>
                        <div class="stat-card__label">In Catalog</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-card__value"><?php echo $audit['total_stock']; ?></div>
                        <div class="stat-card__label">In stock.json</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-card__value"><?php echo $audit['total_branches']; ?></div>
                        <div class="stat-card__label">In Branches</div>
                    </div>
                </div>
            </div>
            
            <!-- Discrepancies -->
            <div class="admin-card">
                <h2>Discrepancy Analysis</h2>
                
                <div class="discrepancy-section">
                    <h3>📦 In Catalog but NOT in stock.json (<?php echo count($audit['in_catalog_not_stock']); ?>)</h3>
                    <?php if (count($audit['in_catalog_not_stock']) > 0): ?>
                        <div class="discrepancy-list">
                            <?php foreach ($audit['in_catalog_not_stock'] as $sku): ?>
                                <div class="discrepancy-item">
                                    <?php 
                                    $data = $audit['universe'][$sku];
                                    echo htmlspecialchars($sku) . ' - ' . htmlspecialchars($data['product_name']) . ' (' . htmlspecialchars($data['volume']) . ', ' . htmlspecialchars($data['fragrance']) . ')';
                                    ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="color: #28a745;">✅ No discrepancies found</p>
                    <?php endif; ?>
                </div>
                
                <div class="discrepancy-section">
                    <h3>🏢 In Catalog but NOT in ANY branch (<?php echo count($audit['in_catalog_not_branches']); ?>)</h3>
                    <?php if (count($audit['in_catalog_not_branches']) > 0): ?>
                        <div class="discrepancy-list">
                            <?php foreach (array_slice($audit['in_catalog_not_branches'], 0, 50) as $sku): ?>
                                <div class="discrepancy-item">
                                    <?php 
                                    $data = $audit['universe'][$sku];
                                    echo htmlspecialchars($sku) . ' - ' . htmlspecialchars($data['product_name']);
                                    ?>
                                </div>
                            <?php endforeach; ?>
                            <?php if (count($audit['in_catalog_not_branches']) > 50): ?>
                                <p style="margin: 0.5rem; color: #666;">... and <?php echo count($audit['in_catalog_not_branches']) - 50; ?> more</p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <p style="color: #28a745;">✅ No discrepancies found</p>
                    <?php endif; ?>
                </div>
                
                <div class="discrepancy-section">
                    <h3>⚠️ In stock.json but NOT in Catalog (<?php echo count($audit['in_stock_not_catalog']); ?>)</h3>
                    <?php if (count($audit['in_stock_not_catalog']) > 0): ?>
                        <div class="discrepancy-list">
                            <?php foreach ($audit['in_stock_not_catalog'] as $sku): ?>
                                <div class="discrepancy-item" style="background: #fff3cd;">
                                    <?php 
                                    $data = $audit['universe'][$sku];
                                    echo htmlspecialchars($sku) . ' - ' . htmlspecialchars($data['product_name']);
                                    ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <p style="color: #856404; margin-top: 0.5rem;">
                            ℹ️ These SKUs exist in stock but not derivable from current catalog. May be legacy or discontinued products.
                        </p>
                    <?php else: ?>
                        <p style="color: #28a745;">✅ No discrepancies found</p>
                    <?php endif; ?>
                </div>
                
                <div class="discrepancy-section">
                    <h3>🔴 In branches but NOT in Catalog (<?php echo count($audit['in_branches_not_catalog']); ?>)</h3>
                    <?php if (count($audit['in_branches_not_catalog']) > 0): ?>
                        <div class="discrepancy-list">
                            <?php foreach ($audit['in_branches_not_catalog'] as $sku): ?>
                                <div class="discrepancy-item" style="background: #f8d7da;">
                                    <?php echo htmlspecialchars($sku); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <p style="color: #721c24; margin-top: 0.5rem;">
                            ⚠️ These SKUs exist in branches but not in catalog. Investigate for data integrity issues.
                        </p>
                    <?php else: ?>
                        <p style="color: #28a745;">✅ No discrepancies found</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Actions -->
            <div class="admin-card" style="margin-top: 2rem;">
                <h2>Actions</h2>
                
                <div class="action-buttons">
                    <a href="?action=export&format=csv" class="btn btn--gold">
                        📥 Download CSV Report
                    </a>
                    <a href="?action=export&format=json" class="btn">
                        📥 Download JSON Report
                    </a>
                </div>
                
                <hr style="margin: 2rem 0;">
                
                <h3>Initialize Missing SKU Keys</h3>
                <p style="color: #666;">
                    Add missing SKUs to stock.json and branch_stock.json with quantity=0.
                    This will NOT modify any existing quantities.
                </p>
                
                <form method="post" action="" style="margin-top: 1rem;">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="action" value="preview_init">
                    <button type="submit" class="btn" style="background: #17a2b8; color: white;">
                        🔍 Preview Initialization (Dry Run)
                    </button>
                </form>
            </div>
            
            <div class="admin-card" style="margin-top: 1.5rem; background: #f0f8ff; border: 1px solid #4a90e2;">
                <h3 style="margin-top: 0; color: #4a90e2;">ℹ️ About SKU Universe</h3>
                <p style="color: #666;">
                    The SKU Universe is the single source of truth for all SKUs in the system. It combines:
                </p>
                <ul style="color: #666;">
                    <li><strong>Catalog SKUs:</strong> All possible SKUs derivable from products.json and accessories.json</li>
                    <li><strong>Stock SKUs:</strong> All keys present in stock.json (global stock)</li>
                    <li><strong>Branch SKUs:</strong> All keys present in branch_stock.json (branch stock)</li>
                </ul>
                <p style="color: #666;">
                    The Stock Management page and CSV export now use the complete SKU Universe, ensuring no SKUs are missed.
                </p>
            </div>
        </main>
    </div>
</body>
</html>
