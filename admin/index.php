<?php
/**
 * Admin Dashboard
 */

require_once __DIR__ . '/../init.php';

// Check authentication
if (!isAdminLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Prevent browser caching to ensure fresh stock data is always displayed
// This is critical because Safari and other browsers may cache the page,
// showing stale stock quantities even after orders are placed.
// Without these headers, different browsers/tabs may show different stock levels.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

$orders = loadJSON('orders.json');
$products = loadJSON('products.json');
$stock = loadJSON('stock.json');

// Count stats
$orderCount = is_array($orders) ? count($orders) : 0;
$productCount = is_array($products) ? count($products) : 0;
$customerCount = getRegisteredCustomersCount();
$lowStockCount = 0;

foreach ($stock as $sku => $item) {
    if (($item['quantity'] ?? 0) <= ($item['lowStockThreshold'] ?? 3)) {
        $lowStockCount++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - NicheHome.ch</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600&family=Manrope:wght@400;500;600&display=swap" rel="stylesheet">
</head>
<body>
    <div class="admin-layout">
        <aside class="admin-sidebar">
            <div class="admin-sidebar__logo">NicheHome Admin</div>
            <nav class="admin-sidebar__nav">
                <a href="index.php" class="admin-sidebar__link active">Dashboard</a>
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
                <a href="diagnostics.php" class="admin-sidebar__link">Diagnostics</a>
                <a href="notifications.php" class="admin-sidebar__link">Notifications</a>
                <a href="email.php" class="admin-sidebar__link">Email Settings</a>
                <a href="logout.php" class="admin-sidebar__link">Logout</a>
            </nav>
        </aside>
        
        <main class="admin-content">
            <div class="admin-header">
                <h1>Dashboard</h1>
                <p>Welcome, <?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?>!</p>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem;">
                <div class="admin-card">
                    <h3>Orders</h3>
                    <p style="font-size: 2.5rem; font-weight: 700; margin: 1rem 0;"><?php echo $orderCount; ?></p>
                    <a href="orders.php" class="btn btn--text">View orders →</a>
                </div>
                
                <div class="admin-card">
                    <h3>Products</h3>
                    <p style="font-size: 2.5rem; font-weight: 700; margin: 1rem 0;"><?php echo $productCount; ?></p>
                    <a href="products.php" class="btn btn--text">Manage products →</a>
                </div>
                
                <div class="admin-card">
                    <h3>Registered Customers</h3>
                    <p style="font-size: 2.5rem; font-weight: 700; margin: 1rem 0;"><?php echo $customerCount; ?></p>
                    <a href="admin_users.php" class="btn btn--text">View customers →</a>
                </div>
                
                <div class="admin-card">
                    <h3>Low Stock</h3>
                    <p style="font-size: 2.5rem; font-weight: 700; margin: 1rem 0; color: <?php echo $lowStockCount > 0 ? 'var(--color-error)' : 'inherit'; ?>;">
                        <?php echo $lowStockCount; ?>
                    </p>
                    <a href="stock.php" class="btn btn--text">Manage stock →</a>
                </div>
            </div>
            
            <?php if (!empty($orders)): ?>
                <div class="admin-card" style="margin-top: 2rem;">
                    <h3>Recent Orders</h3>
                    <table class="admin-table" style="margin-top: 1rem;">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Date</th>
                                <th>Customer</th>
                                <th>Total</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $recentOrders = array_slice(array_reverse($orders), 0, 5);
                            foreach ($recentOrders as $order): 
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($order['id'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($order['date'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars(($order['customer']['first_name'] ?? '') . ' ' . ($order['customer']['last_name'] ?? '')); ?></td>
                                    <td>CHF <?php echo number_format($order['total'] ?? 0, 2); ?></td>
                                    <td><?php echo htmlspecialchars($order['status'] ?? 'new'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
