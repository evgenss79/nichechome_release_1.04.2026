<?php
/**
 * Admin - Fragrances Management
 */

require_once __DIR__ . '/../init.php';

if (!isAdminLoggedIn()) {
    header('Location: login.php');
    exit;
}

$fragrances = loadJSON('fragrances.json');
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fragrance_code'])) {
    $code = $_POST['fragrance_code'];
    
    if (isset($fragrances[$code])) {
        $fragrances[$code]['image'] = $_POST['image'] ?? $fragrances[$code]['image'];
        
        if (saveJSON('fragrances.json', $fragrances)) {
            $success = 'Fragrance updated successfully.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fragrances - Admin</title>
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
                <a href="fragrances.php" class="admin-sidebar__link active">Fragrances</a>
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
                <h1>Fragrances</h1>
            </div>
            
            <?php if ($success): ?>
                <div class="alert alert--success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <div class="admin-card">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Image</th>
                            <th>Top Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fragrances as $code => $fragrance): ?>
                            <?php
                            $name = I18N::t('fragrance.' . $code . '.name', ucfirst(str_replace('_', ' ', $code)));
                            $topNotes = implode(', ', $fragrance['top_notes'] ?? []);
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($code); ?></td>
                                <td><?php echo htmlspecialchars($name); ?></td>
                                <td>
                                    <?php if (!empty($fragrance['image'])): ?>
                                        <img src="../assets/img/fragrances/<?php echo htmlspecialchars($fragrance['image']); ?>" 
                                             alt="<?php echo htmlspecialchars($name); ?>" 
                                             style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px;"
                                             onerror="this.style.display='none'">
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($topNotes); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <p class="text-muted mt-4">
                Note: Fragrance names and descriptions are managed through the i18n JSON files in /data/i18n/.
            </p>
        </main>
    </div>
</body>
</html>
