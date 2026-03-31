<?php
/**
 * Admin - Orders Management Interface
 * Alternative orders management page with enhanced features
 */

require_once __DIR__ . '/../init.php';

if (!isAdminLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Prevent browser caching to ensure fresh order data is always displayed
// This is critical because Safari and other browsers may cache the page,
// showing stale order information even after new orders are placed.
// Without these headers, different browsers/tabs may show different order states.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

$orders = loadJSON('orders.json');
if (!is_array($orders)) {
    $orders = [];
}

// Validate order integrity for all orders
foreach ($orders as $order) {
    validateOrderIntegrity($order);
}

// Load branches once for performance (used in order details)
$branches = loadJSON('branches.json');

$customers = loadJSON('customers.json');
$success = '';
$error = '';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_status' && isset($_POST['order_id'])) {
        $orderId = $_POST['order_id'];
        $newStatus = $_POST['status'] ?? '';
        
        foreach ($orders as &$order) {
            if (($order['id'] ?? '') === $orderId) {
                $order['status'] = $newStatus;
                $order['updated_at'] = date('Y-m-d H:i:s');
                break;
            }
        }
        
        if (saveJSON('orders.json', $orders)) {
            $success = 'Order status updated successfully.';
        } else {
            $error = 'Failed to update order status.';
        }
    }
}

// Filter orders
$filterStatus = $_GET['status'] ?? '';
$filteredOrders = $orders;

if ($filterStatus) {
    $filteredOrders = array_filter($orders, function($order) use ($filterStatus) {
        return ($order['status'] ?? '') === $filterStatus;
    });
}

// Sort orders by date (newest first)
usort($filteredOrders, function($a, $b) {
    return strtotime($b['date'] ?? '0') - strtotime($a['date'] ?? '0');
});

$statuses = ['new', 'pending_twint', 'processing', 'shipped', 'completed', 'cancelled'];

// Calculate statistics
$stats = [
    'total' => count($orders),
    'new' => 0,
    'processing' => 0,
    'completed' => 0,
    'cancelled' => 0
];

foreach ($orders as $order) {
    $status = $order['status'] ?? 'new';
    if (isset($stats[$status])) {
        $stats[$status]++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders Management - Admin</title>
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
                <a href="categories.php" class="admin-sidebar__link">Categories</a>
                <a href="stock.php" class="admin-sidebar__link">Stock</a>
                <a href="stock_import.php" class="admin-sidebar__link">Stock Import</a>
                <a href="sku_audit.php" class="admin-sidebar__link">SKU Audit</a>
                <a href="orders.php" class="admin-sidebar__link">Orders</a>
                <a href="admin_orders.php" class="admin-sidebar__link active">Orders (Enhanced)</a>
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
                <h1>Orders Management</h1>
                <p>Manage and track all customer orders</p>
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
                    <h4>Total Orders</h4>
                    <p style="font-size: 2rem; font-weight: 700; margin: 0.5rem 0;"><?php echo $stats['total']; ?></p>
                </div>
                <div class="admin-card">
                    <h4>New</h4>
                    <p style="font-size: 2rem; font-weight: 700; margin: 0.5rem 0; color: var(--color-primary);"><?php echo $stats['new']; ?></p>
                </div>
                <div class="admin-card">
                    <h4>Processing</h4>
                    <p style="font-size: 2rem; font-weight: 700; margin: 0.5rem 0; color: var(--color-warning);"><?php echo $stats['processing']; ?></p>
                </div>
                <div class="admin-card">
                    <h4>Completed</h4>
                    <p style="font-size: 2rem; font-weight: 700; margin: 0.5rem 0; color: var(--color-success);"><?php echo $stats['completed']; ?></p>
                </div>
            </div>
            
            <!-- Status Filter -->
            <div class="admin-card">
                <form method="get" style="display: flex; gap: 1rem; align-items: center;">
                    <label><strong>Filter by status:</strong></label>
                    <select name="status" onchange="this.form.submit()">
                        <option value="">All statuses</option>
                        <?php foreach ($statuses as $status): ?>
                            <option value="<?php echo htmlspecialchars($status); ?>" <?php echo $filterStatus === $status ? 'selected' : ''; ?>>
                                <?php echo ucfirst(str_replace('_', ' ', $status)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($filterStatus): ?>
                        <a href="admin_orders.php" class="btn btn--text">Clear filter</a>
                    <?php endif; ?>
                </form>
            </div>
            
            <!-- Orders List -->
            <?php if (empty($filteredOrders)): ?>
                <div class="admin-card">
                    <p class="text-muted">No orders found.</p>
                </div>
            <?php else: ?>
                <div class="admin-card">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Date</th>
                                <th>Customer</th>
                                <th>Email</th>
                                <th>Items</th>
                                <th>Total</th>
                                <th>Delivery</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($filteredOrders as $order): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($order['id'] ?? ''); ?></strong></td>
                                    <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($order['date'] ?? ''))); ?></td>
                                    <td><?php echo htmlspecialchars(($order['customer']['first_name'] ?? '') . ' ' . ($order['customer']['last_name'] ?? '')); ?></td>
                                    <td><?php echo htmlspecialchars($order['customer']['email'] ?? ''); ?></td>
                                    <td><?php echo count($order['items'] ?? []); ?> item(s)</td>
                                    <td><strong>CHF <?php echo number_format($order['total'] ?? 0, 2); ?></strong></td>
                                    <td><?php echo !empty($order['pickup_in_branch']) ? 'Pickup' : 'Delivery'; ?></td>
                                    <td>
                                        <form method="post" action="" style="display: flex; gap: 0.5rem; align-items: center;">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order['id'] ?? ''); ?>">
                                            <select name="status" style="padding: 0.5rem; border-radius: 4px; border: 1px solid var(--color-border);">
                                                <?php foreach ($statuses as $status): ?>
                                                    <option value="<?php echo $status; ?>" <?php echo ($order['status'] ?? '') === $status ? 'selected' : ''; ?>>
                                                        <?php echo ucfirst(str_replace('_', ' ', $status)); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="btn btn--text">Update</button>
                                        </form>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn--text" onclick="toggleOrderDetails('<?php echo htmlspecialchars($order['id'] ?? ''); ?>')">
                                            <span id="btn-text-<?php echo htmlspecialchars($order['id'] ?? ''); ?>">View Details</span>
                                        </button>
                                    </td>
                                </tr>
                                <tr id="details-<?php echo htmlspecialchars($order['id'] ?? ''); ?>" style="display: none;">
                                    <td colspan="9" style="background: var(--color-sand); padding: 2rem;">
                                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                                            <div>
                                                <h4>Customer Information</h4>
                                                <p>
                                                    <strong>Name:</strong> <?php echo htmlspecialchars(($order['customer']['first_name'] ?? '') . ' ' . ($order['customer']['last_name'] ?? '')); ?><br>
                                                    <strong>Email:</strong> <?php echo htmlspecialchars($order['customer']['email'] ?? ''); ?><br>
                                                    <strong>Phone:</strong> <?php echo htmlspecialchars($order['customer']['phone'] ?? ''); ?>
                                                </p>
                                                
                                                <?php if (!empty($order['pickup_in_branch'])): ?>
                                                    <?php
                                                    // Get branch details for pickup (branches already loaded above)
                                                    $branchId = $order['pickup_branch_id'] ?? '';
                                                    $branchName = 'Selected branch';
                                                    $branchAddress = '';
                                                    foreach ($branches as $branch) {
                                                        if (($branch['id'] ?? '') === $branchId) {
                                                            $branchName = $branch['name'] ?? 'Selected branch';
                                                            $branchAddress = $branch['address'] ?? '';
                                                            break;
                                                        }
                                                    }
                                                    ?>
                                                    <h4 style="margin-top: 1.5rem;">Pickup Location</h4>
                                                    <p>
                                                        <strong>Branch:</strong> <?php echo htmlspecialchars($branchName); ?><br>
                                                        <?php if ($branchAddress): ?>
                                                            <strong>Address:</strong> <?php echo htmlspecialchars($branchAddress); ?>
                                                        <?php endif; ?>
                                                    </p>
                                                <?php else: ?>
                                                    <h4 style="margin-top: 1.5rem;">Shipping Address</h4>
                                                    <p>
                                                        <?php echo htmlspecialchars(($order['shipping']['street'] ?? '') . ' ' . ($order['shipping']['house'] ?? '')); ?><br>
                                                        <?php echo htmlspecialchars(($order['shipping']['zip'] ?? '') . ' ' . ($order['shipping']['city'] ?? '')); ?><br>
                                                        <?php echo htmlspecialchars($order['shipping']['country'] ?? 'Switzerland'); ?>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div>
                                                <h4>Order Information</h4>
                                                <p>
                                                    <strong>Payment Method:</strong> <?php echo htmlspecialchars(ucfirst($order['payment_method'] ?? 'Unknown')); ?><br>
                                                    <strong>Delivery Method:</strong> <?php echo !empty($order['pickup_in_branch']) ? 'Pickup in branch' : 'Delivery'; ?><br>
                                                    <strong>Status:</strong> <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $order['status'] ?? 'new'))); ?>
                                                </p>
                                                
                                                <?php if (!empty($order['comment'])): ?>
                                                    <h4 style="margin-top: 1.5rem;">Customer Comment</h4>
                                                    <p><?php echo htmlspecialchars($order['comment']); ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <h4 style="margin-top: 2rem;">Order Items</h4>
                                        <table class="admin-table" style="margin-top: 1rem;">
                                            <thead>
                                                <tr>
                                                    <th>Product</th>
                                                    <th>Volume</th>
                                                    <th>Fragrance</th>
                                                    <th>Quantity</th>
                                                    <th>Price</th>
                                                    <th>Subtotal</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($order['items'] ?? [] as $item): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($item['name'] ?? 'Product'); ?></td>
                                                        <td><?php echo htmlspecialchars($item['volume'] ?? '-'); ?></td>
                                                        <td><?php echo htmlspecialchars($item['fragrance'] ?? '-'); ?></td>
                                                        <td><?php echo (int)($item['quantity'] ?? 1); ?></td>
                                                        <td>CHF <?php echo number_format($item['price'] ?? 0, 2); ?></td>
                                                        <td><strong>CHF <?php echo number_format(($item['price'] ?? 0) * ($item['quantity'] ?? 1), 2); ?></strong></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                <tr style="border-top: 2px solid #ddd;">
                                                    <td colspan="5" style="text-align: right; padding-top: 1rem;"><strong>Subtotal:</strong></td>
                                                    <td style="padding-top: 1rem;"><strong>CHF <?php echo number_format($order['subtotal'] ?? 0, 2); ?></strong></td>
                                                </tr>
                                                <tr>
                                                    <td colspan="5" style="text-align: right;"><strong>Shipping:</strong></td>
                                                    <td><strong>CHF <?php echo number_format($order['shipping_cost'] ?? 0, 2); ?></strong></td>
                                                </tr>
                                                <tr style="border-top: 2px solid #333;">
                                                    <td colspan="5" style="text-align: right; font-size: 1.2em; padding-top: 0.5rem;"><strong>Total:</strong></td>
                                                    <td style="font-size: 1.2em; padding-top: 0.5rem;"><strong>CHF <?php echo number_format($order['total'] ?? 0, 2); ?></strong></td>
                                                </tr>
                                            </tbody>
                                        </table>
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
        function toggleOrderDetails(orderId) {
            const row = document.getElementById('details-' + orderId);
            const btnText = document.getElementById('btn-text-' + orderId);
            if (row) {
                if (row.style.display === 'none') {
                    row.style.display = 'table-row';
                    if (btnText) btnText.textContent = 'Hide Details';
                } else {
                    row.style.display = 'none';
                    if (btnText) btnText.textContent = 'View Details';
                }
            }
        }
    </script>
</body>
</html>
