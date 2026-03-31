<?php
/**
 * FIX: TASK 3 - Admin User Management
 * Manage admin users: add, edit, delete, change password, assign roles
 */

require_once __DIR__ . '/../init.php';

// Check authentication and permission
if (!isAdminLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Only full_access role can manage users
if (!canManageUsers()) {
    header('Location: index.php?error=access_denied');
    exit;
}

$currentAdminUser = $_SESSION['admin_user'] ?? '';
$error = '';
$success = '';

// Load admin users
$usersFile = __DIR__ . '/../data/users.json';
$users = [];
if (file_exists($usersFile)) {
    $users = json_decode(file_get_contents($usersFile), true) ?? [];
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? '';
        
        if (empty($username) || empty($email) || empty($name) || empty($password) || empty($role)) {
            $error = 'All fields are required';
        } elseif (isset($users[$username])) {
            $error = 'Username already exists';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters';
        } else {
            $users[$username] = [
                'email' => $email,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'role' => $role,
                'name' => $name,
                'is_active' => 1,
                'created_at' => date('c'),
                'created_by' => $currentAdminUser
            ];
            
            if (file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT))) {
                $success = 'User added successfully';
            } else {
                $error = 'Failed to save user';
            }
        }
    } elseif ($action === 'edit') {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $role = $_POST['role'] ?? '';
        
        if (empty($username) || empty($email) || empty($name) || empty($role)) {
            $error = 'All fields are required';
        } elseif (!isset($users[$username])) {
            $error = 'User not found';
        } else {
            $users[$username]['email'] = $email;
            $users[$username]['name'] = $name;
            $users[$username]['role'] = $role;
            $users[$username]['updated_at'] = date('c');
            $users[$username]['updated_by'] = $currentAdminUser;
            
            if (file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT))) {
                $success = 'User updated successfully';
            } else {
                $error = 'Failed to update user';
            }
        }
    } elseif ($action === 'change_password') {
        $username = trim($_POST['username'] ?? '');
        $newPassword = $_POST['new_password'] ?? '';
        
        if (empty($username) || empty($newPassword)) {
            $error = 'Username and password are required';
        } elseif (!isset($users[$username])) {
            $error = 'User not found';
        } elseif (strlen($newPassword) < 8) {
            $error = 'Password must be at least 8 characters';
        } else {
            $users[$username]['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
            $users[$username]['password_changed_at'] = date('c');
            $users[$username]['password_changed_by'] = $currentAdminUser;
            
            if (file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT))) {
                $success = 'Password changed successfully';
            } else {
                $error = 'Failed to change password';
            }
        }
    } elseif ($action === 'toggle_active') {
        $username = trim($_POST['username'] ?? '');
        
        if (empty($username)) {
            $error = 'Username is required';
        } elseif (!isset($users[$username])) {
            $error = 'User not found';
        } elseif ($username === $currentAdminUser) {
            $error = 'Cannot deactivate your own account';
        } else {
            $users[$username]['is_active'] = empty($users[$username]['is_active']) ? 1 : 0;
            $users[$username]['updated_at'] = date('c');
            $users[$username]['updated_by'] = $currentAdminUser;
            
            if (file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT))) {
                $success = 'User status updated';
            } else {
                $error = 'Failed to update user status';
            }
        }
    }
}

// Role definitions
$roles = [
    'full_access' => 'Full Access - Can do everything',
    'view_only' => 'View Only - Read-only access',
    'products_prices' => 'Products & Prices - Can edit products/prices only',
    'orders_only' => 'Orders Only - Can view and manage orders only'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Admin Users - NicheHome.ch</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600&family=Manrope:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        .user-card {
            background: white;
            padding: 1.5rem;
            margin-bottom: 1rem;
            border-radius: 8px;
            border: 1px solid var(--color-stone);
        }
        .user-card.inactive {
            opacity: 0.6;
            background: #f5f5f5;
        }
        .user-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        .user-role {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .role-full_access { background: #ffd700; color: #333; }
        .role-view_only { background: #e0e0e0; color: #333; }
        .role-products_prices { background: #90caf9; color: #333; }
        .role-orders_only { background: #a5d6a7; color: #333; }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }
        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        .btn-group {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
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
                <a href="manage_admins.php" class="admin-sidebar__link active">Admin Management</a>
                <?php endif; ?>
                <a href="diagnostics.php" class="admin-sidebar__link">Diagnostics</a>
                <a href="notifications.php" class="admin-sidebar__link">Notifications</a>
                <a href="email.php" class="admin-sidebar__link">Email Settings</a>
                <a href="logout.php" class="admin-sidebar__link">Logout</a>
            </nav>
        </aside>
        
        <main class="admin-content">
            <div class="admin-header">
                <h1>Manage Admin Users</h1>
                <button type="button" class="btn btn--gold" onclick="openAddModal()">
                    Add New User
                </button>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert--error" style="margin-bottom: 1.5rem;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert--success" style="margin-bottom: 1.5rem;">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <div class="admin-card">
                <h3 style="margin-bottom: 1rem;">Current Admin Users</h3>
                
                <?php foreach ($users as $username => $user): ?>
                    <?php 
                    $isActive = !isset($user['is_active']) || $user['is_active'];
                    $isCurrent = $username === $currentAdminUser;
                    ?>
                    <div class="user-card <?php echo !$isActive ? 'inactive' : ''; ?>">
                        <div class="user-header">
                            <div>
                                <h4 style="margin: 0;">
                                    <?php echo htmlspecialchars($user['name'] ?? $username); ?>
                                    <?php if ($isCurrent): ?>
                                        <span style="color: var(--color-gold); font-size: 0.9rem;">(You)</span>
                                    <?php endif; ?>
                                    <?php if (!$isActive): ?>
                                        <span style="color: #999; font-size: 0.9rem;">(Inactive)</span>
                                    <?php endif; ?>
                                </h4>
                                <p style="margin: 0.25rem 0; color: #666;">
                                    <?php echo htmlspecialchars($username); ?> • <?php echo htmlspecialchars($user['email'] ?? ''); ?>
                                </p>
                            </div>
                            <span class="user-role role-<?php echo htmlspecialchars($user['role'] ?? 'view_only'); ?>">
                                <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $user['role'] ?? 'view_only'))); ?>
                            </span>
                        </div>
                        
                        <div class="btn-group">
                            <button type="button" class="btn btn--text" 
                                    onclick="openEditModal('<?php echo htmlspecialchars($username); ?>', '<?php echo htmlspecialchars($user['email'] ?? ''); ?>', '<?php echo htmlspecialchars($user['name'] ?? ''); ?>', '<?php echo htmlspecialchars($user['role'] ?? 'view_only'); ?>')">
                                Edit
                            </button>
                            <button type="button" class="btn btn--text" 
                                    onclick="openPasswordModal('<?php echo htmlspecialchars($username); ?>')">
                                Change Password
                            </button>
                            <?php if (!$isCurrent): ?>
                                <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure?')">
                                    <input type="hidden" name="action" value="toggle_active">
                                    <input type="hidden" name="username" value="<?php echo htmlspecialchars($username); ?>">
                                    <button type="submit" class="btn btn--text">
                                        <?php echo $isActive ? 'Deactivate' : 'Activate'; ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </main>
    </div>
    
    <!-- Add User Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <h2>Add New Admin User</h2>
            <form method="post">
                <input type="hidden" name="action" value="add">
                
                <div class="form-group">
                    <label>Username *</label>
                    <input type="text" name="username" required>
                </div>
                
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="name" required>
                </div>
                
                <div class="form-group">
                    <label>Password * (minimum 8 characters)</label>
                    <input type="password" name="password" required minlength="8">
                </div>
                
                <div class="form-group">
                    <label>Role *</label>
                    <select name="role" required>
                        <?php foreach ($roles as $roleKey => $roleDesc): ?>
                            <option value="<?php echo htmlspecialchars($roleKey); ?>">
                                <?php echo htmlspecialchars($roleDesc); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn btn--gold">Add User</button>
                    <button type="button" class="btn" onclick="closeModal('addModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit User Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <h2>Edit Admin User</h2>
            <form method="post">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="username" id="edit_username">
                
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" id="edit_email" required>
                </div>
                
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="name" id="edit_name" required>
                </div>
                
                <div class="form-group">
                    <label>Role *</label>
                    <select name="role" id="edit_role" required>
                        <?php foreach ($roles as $roleKey => $roleDesc): ?>
                            <option value="<?php echo htmlspecialchars($roleKey); ?>">
                                <?php echo htmlspecialchars($roleDesc); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn btn--gold">Update User</button>
                    <button type="button" class="btn" onclick="closeModal('editModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Change Password Modal -->
    <div id="passwordModal" class="modal">
        <div class="modal-content">
            <h2>Change Password</h2>
            <form method="post">
                <input type="hidden" name="action" value="change_password">
                <input type="hidden" name="username" id="password_username">
                
                <div class="form-group">
                    <label>New Password * (minimum 8 characters)</label>
                    <input type="password" name="new_password" required minlength="8">
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn btn--gold">Change Password</button>
                    <button type="button" class="btn" onclick="closeModal('passwordModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function openAddModal() {
            document.getElementById('addModal').classList.add('active');
        }
        
        function openEditModal(username, email, name, role) {
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_role').value = role;
            document.getElementById('editModal').classList.add('active');
        }
        
        function openPasswordModal(username) {
            document.getElementById('password_username').value = username;
            document.getElementById('passwordModal').classList.add('active');
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }
        
        // Close modal on outside click
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    closeModal(modal.id);
                }
            });
        });
    </script>
</body>
</html>
