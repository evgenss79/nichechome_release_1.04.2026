<?php
/**
 * Admin - Users Management and Statistics
 */

require_once __DIR__ . '/../init.php';

// Check authentication
if (!isAdminLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Load data
$customers = getCustomers();
$orders = loadOrders();
$branches = loadJSON('branches.json');

/**
 * Get customer statistics
 */
function getCustomerStatistics($customerId, $orders, $branches) {
    $stats = [
        'total_orders' => 0,
        'completed_orders' => 0,
        'total_sum' => 0.0,
        'delivery_count' => 0,
        'pickup_count' => 0,
        'pickup_branches' => [],
        'products' => [],
        'top_orders' => []
    ];
    
    $customerOrders = [];
    
    foreach ($orders as $orderId => $order) {
        if (($order['customer_id'] ?? '') === $customerId) {
            $customerOrders[$orderId] = $order;
            $stats['total_orders']++;
            
            // Count completed orders
            if (($order['status'] ?? '') === 'completed') {
                $stats['completed_orders']++;
            }
            
            // Calculate total sum (excluding shipping)
            $stats['total_sum'] += ($order['subtotal'] ?? 0);
            
            // Count delivery vs pickup
            if (!empty($order['pickup_in_branch'])) {
                $stats['pickup_count']++;
                $branchId = $order['pickup_branch_id'] ?? '';
                if ($branchId) {
                    if (!isset($stats['pickup_branches'][$branchId])) {
                        $stats['pickup_branches'][$branchId] = 0;
                    }
                    $stats['pickup_branches'][$branchId]++;
                }
            } else {
                $stats['delivery_count']++;
            }
            
            // Aggregate products
            foreach ($order['items'] ?? [] as $item) {
                $productKey = ($item['name'] ?? 'Unknown') . ' - ' . 
                              ($item['volume'] ?? 'N/A') . ' - ' . 
                              ($item['fragrance'] ?? 'N/A');
                
                if (!isset($stats['products'][$productKey])) {
                    $stats['products'][$productKey] = [
                        'name' => $item['name'] ?? 'Unknown',
                        'volume' => $item['volume'] ?? 'N/A',
                        'fragrance' => $item['fragrance'] ?? 'N/A',
                        'quantity' => 0
                    ];
                }
                $stats['products'][$productKey]['quantity'] += ($item['quantity'] ?? 1);
            }
        }
    }
    
    // Sort products by quantity DESC
    uasort($stats['products'], function($a, $b) {
        return $b['quantity'] - $a['quantity'];
    });
    
    // Get top 3 largest orders
    $ordersByTotal = [];
    foreach ($customerOrders as $orderId => $order) {
        $ordersByTotal[$orderId] = $order['total'] ?? 0;
    }
    arsort($ordersByTotal);
    $stats['top_orders'] = array_slice(array_keys($ordersByTotal), 0, 3, true);
    
    return $stats;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registered Customers - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600&family=Manrope:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        .user-details {
            display: none;
            background: var(--color-sand);
            padding: 1.5rem;
            margin-top: 1rem;
        }
        .user-details.active {
            display: block;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin: 1rem 0;
        }
        .stat-box {
            background: white;
            padding: 1rem;
            border-radius: 4px;
        }
        .stat-box strong {
            display: block;
            color: var(--color-charcoal);
            margin-bottom: 0.5rem;
        }
        .product-list {
            max-height: 300px;
            overflow-y: auto;
            margin: 1rem 0;
        }
        .product-item {
            padding: 0.5rem;
            border-bottom: 1px solid #ddd;
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
                <a href="admin_users.php" class="admin-sidebar__link active">User Management</a>
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
                <h1>Registered Customers</h1>
                <p>Total: <?php echo count($customers); ?> customers</p>
            </div>
            
            <?php if (empty($customers)): ?>
                <div class="admin-card">
                    <p class="text-muted">No registered customers yet.</p>
                </div>
            <?php else: ?>
                <div class="admin-card">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Registration Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($customers as $email => $customer): ?>
                                <?php 
                                $customerId = $customer['id'] ?? '';
                                $stats = getCustomerStatistics($customerId, $orders, $branches);
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars(trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''))); ?></td>
                                    <td><?php echo htmlspecialchars($email); ?></td>
                                    <td><?php echo htmlspecialchars($customer['phone'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php 
                                        if (!empty($customer['created_at'])) {
                                            echo htmlspecialchars(date('Y-m-d', strtotime($customer['created_at'])));
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn--text" onclick="toggleUserDetails('<?php echo htmlspecialchars($customerId); ?>')">
                                            View Details
                                        </button>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="5" style="padding: 0;">
                                        <div id="details-<?php echo htmlspecialchars($customerId); ?>" class="user-details">
                                            <h3>Customer Details</h3>
                                            
                                            <div style="margin: 1rem 0;">
                                                <h4>Contact Information</h4>
                                                <p>
                                                    <strong>Name:</strong> 
                                                    <?php 
                                                    $nameParts = array_filter([
                                                        $customer['salutation'] ?? '',
                                                        $customer['first_name'] ?? '',
                                                        $customer['last_name'] ?? ''
                                                    ], function($val) { return trim($val) !== ''; });
                                                    echo htmlspecialchars(!empty($nameParts) ? implode(' ', $nameParts) : 'N/A');
                                                    ?><br>
                                                    <strong>Email:</strong> <?php echo htmlspecialchars($email); ?><br>
                                                    <strong>Phone:</strong> <?php echo htmlspecialchars($customer['phone'] ?? 'N/A'); ?><br>
                                                    <!-- FIX: TASK 4 - Display newsletter opt-in status -->
                                                    <strong>Newsletter:</strong> 
                                                    <?php 
                                                    $newsletterOptIn = $customer['newsletter_opt_in'] ?? 0;
                                                    $newsletterOptInAt = $customer['newsletter_opt_in_at'] ?? null;
                                                    if ($newsletterOptIn) {
                                                        echo 'Yes';
                                                        if ($newsletterOptInAt) {
                                                            echo ' (' . htmlspecialchars(date('Y-m-d', strtotime($newsletterOptInAt))) . ')';
                                                        }
                                                    } else {
                                                        echo 'No';
                                                    }
                                                    ?><br>
                                                    <strong>Shipping Address:</strong> 
                                                    <?php 
                                                    $addr = $customer['shipping_address'] ?? [];
                                                    $addrParts = array_filter([
                                                        trim(($addr['street'] ?? '') . ' ' . ($addr['house_number'] ?? '')),
                                                        trim(($addr['zip'] ?? '') . ' ' . ($addr['city'] ?? '')),
                                                        $addr['country'] ?? ''
                                                    ], function($val) { return trim($val) !== ''; });
                                                    echo htmlspecialchars(!empty($addrParts) ? implode(', ', $addrParts) : 'N/A');
                                                    ?>
                                                </p>
                                            </div>
                                            
                                            <div class="stats-grid">
                                                <div class="stat-box">
                                                    <strong>Total Orders</strong>
                                                    <span style="font-size: 2rem; font-weight: 700;"><?php echo $stats['total_orders']; ?></span>
                                                </div>
                                                <div class="stat-box">
                                                    <strong>Completed Orders</strong>
                                                    <span style="font-size: 2rem; font-weight: 700;"><?php echo $stats['completed_orders']; ?></span>
                                                </div>
                                                <div class="stat-box">
                                                    <strong>Total Sum (excl. shipping)</strong>
                                                    <span style="font-size: 1.5rem; font-weight: 700;">CHF <?php echo number_format($stats['total_sum'], 2); ?></span>
                                                </div>
                                                <div class="stat-box">
                                                    <strong>Delivery Orders</strong>
                                                    <span style="font-size: 2rem; font-weight: 700;"><?php echo $stats['delivery_count']; ?></span>
                                                </div>
                                                <div class="stat-box">
                                                    <strong>Pickup Orders</strong>
                                                    <span style="font-size: 2rem; font-weight: 700;"><?php echo $stats['pickup_count']; ?></span>
                                                </div>
                                            </div>
                                            
                                            <?php if (!empty($stats['pickup_branches'])): ?>
                                                <div style="margin: 1.5rem 0;">
                                                    <h4>Pickup Branches</h4>
                                                    <ul style="list-style: disc; padding-left: 2rem;">
                                                        <?php foreach ($stats['pickup_branches'] as $branchId => $count): ?>
                                                            <li>
                                                                <?php 
                                                                $branchName = $branches[$branchId]['name'] ?? $branchId;
                                                                echo htmlspecialchars($branchName) . ': ' . $count . ' order' . ($count > 1 ? 's' : '');
                                                                ?>
                                                            </li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($stats['products'])): ?>
                                                <div style="margin: 1.5rem 0;">
                                                    <h4>Ordered Products (by quantity)</h4>
                                                    <div class="product-list">
                                                        <table class="admin-table" style="margin: 0;">
                                                            <thead>
                                                                <tr>
                                                                    <th>Product</th>
                                                                    <th>Volume</th>
                                                                    <th>Fragrance</th>
                                                                    <th>Total Qty</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php foreach ($stats['products'] as $product): ?>
                                                                    <tr>
                                                                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                                                                        <td><?php echo htmlspecialchars($product['volume']); ?></td>
                                                                        <td><?php echo htmlspecialchars($product['fragrance']); ?></td>
                                                                        <td><strong><?php echo $product['quantity']; ?></strong></td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($stats['top_orders'])): ?>
                                                <div style="margin: 1.5rem 0;">
                                                    <h4>Top 3 Largest Orders</h4>
                                                    <ul style="list-style: none; padding: 0;">
                                                        <?php foreach ($stats['top_orders'] as $orderId): ?>
                                                            <?php 
                                                            $order = $orders[$orderId] ?? null;
                                                            if ($order):
                                                            ?>
                                                                <li style="padding: 0.5rem 0; border-bottom: 1px solid #ddd;">
                                                                    <a href="orders.php#order-<?php echo htmlspecialchars($orderId); ?>" 
                                                                       style="color: var(--color-primary); text-decoration: none; font-weight: 600;">
                                                                        Order #<?php echo htmlspecialchars($orderId); ?>
                                                                    </a>
                                                                    - CHF <?php echo number_format($order['total'] ?? 0, 2); ?>
                                                                    (<?php echo htmlspecialchars($order['date'] ?? 'N/A'); ?>)
                                                                </li>
                                                            <?php endif; ?>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                </div>
                                            <?php endif; ?>
                                        </div>
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
        function toggleUserDetails(userId) {
            const details = document.getElementById('details-' + userId);
            if (details) {
                details.classList.toggle('active');
            }
        }
    </script>
</body>
</html>
