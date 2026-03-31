<?php
/**
 * Admin Login
 */

session_start();

// If already logged in, redirect to dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in']) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../includes/I18N.php';

$lang = $_GET['lang'] ?? $_COOKIE['lang'] ?? 'en';
I18N::setLanguage($lang);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Load users
    $usersFile = __DIR__ . '/../data/users.json';
    $users = [];
    if (file_exists($usersFile)) {
        $users = json_decode(file_get_contents($usersFile), true) ?? [];
    }
    
    $authenticated = false;
    foreach ($users as $username => $user) {
        if ($user['email'] === $email) {
            // For demo purposes, password is 'password' (the hash in users.json)
            if (password_verify($password, $user['password'])) {
                // FIX: TASK 3 - Check if user is active (default to active for backward compatibility)
                $isActive = !isset($user['is_active']) || $user['is_active'];
                
                if (!$isActive) {
                    $error = 'This account has been deactivated.';
                    break;
                }
                
                // FIX: TASK 3 - Map old 'admin' role to 'full_access'
                $role = $user['role'] ?? 'view_only';
                if ($role === 'admin') {
                    $role = 'full_access';
                }
                
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_user'] = $username;
                $_SESSION['admin_email'] = $user['email'];
                $_SESSION['admin_role'] = $role;
                $_SESSION['admin_name'] = $user['name'];
                $authenticated = true;
                break;
            }
        }
    }
    
    if ($authenticated) {
        header('Location: index.php');
        exit;
    } else {
        $error = 'Invalid email or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - NicheHome.ch</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600&family=Manrope:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: var(--color-charcoal);
        }
        .login-box {
            background: #fff;
            padding: 3rem;
            border-radius: var(--radius-card);
            width: 100%;
            max-width: 400px;
            box-shadow: var(--shadow-soft);
        }
        .login-box h1 {
            text-align: center;
            margin-bottom: 2rem;
        }
        .login-box .form-group {
            margin-bottom: 1.5rem;
        }
        .login-box .btn {
            width: 100%;
        }
    </style>
</head>
<body>
    <div class="login-box">
        <h1>Admin Login</h1>
        
        <?php if ($error): ?>
            <div class="alert alert--error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="post" action="">
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" required autofocus>
            </div>
            
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            
            <button type="submit" class="btn btn--gold">Login</button>
        </form>
        
        <p style="margin-top: 2rem; text-align: center; color: var(--color-muted); font-size: 0.85rem;">
            Demo: admin@nichehome.ch / password
        </p>
    </div>
</body>
</html>
