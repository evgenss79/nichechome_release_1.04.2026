<?php
/**
 * Customer Account Page - Login, Registration, Profile, Orders, Favorites
 */

require_once __DIR__ . '/init.php';

$currentLang = I18N::getLanguage();
$error = '';
$success = '';
$showMessage = '';

// Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    unset($_SESSION['customer']);
    header('Location: account.php?lang=' . $currentLang);
    exit;
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = I18N::t('account.error.emptyFields', 'Please fill in all fields.');
    } elseif (!isValidEmail($email)) {
        $error = I18N::t('account.error.invalidEmail', 'Invalid email address.');
    } else {
        $customers = getCustomers();
        
        if (isset($customers[$email]) && password_verify($password, $customers[$email]['password_hash'])) {
            $_SESSION['customer'] = $customers[$email];
            header('Location: account.php?lang=' . $currentLang);
            exit;
        } else {
            $error = I18N::t('account.error.invalidCredentials', 'Invalid email or password.');
        }
    }
}

// Handle registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $acceptTerms = !empty($_POST['accept_terms']);
    // FIX: TASK 4 - Newsletter opt-in checkbox
    $newsletterOptIn = !empty($_POST['newsletter_opt_in']);
    
    if (empty($email) || empty($password) || empty($confirmPassword)) {
        $error = I18N::t('account.error.emptyFields', 'Please fill in all fields.');
    } elseif (!$acceptTerms) {
        $error = I18N::t('account.mustAcceptTerms', 'You must accept the Terms & Conditions and Privacy Policy to register');
    } elseif (!isValidEmail($email)) {
        $error = I18N::t('account.error.invalidEmail', 'Invalid email address.');
    } elseif (strlen($password) < 6) {
        $error = I18N::t('account.error.passwordTooShort', 'Password must be at least 6 characters.');
    } elseif ($password !== $confirmPassword) {
        $error = I18N::t('account.error.passwordMismatch', 'Passwords do not match.');
    } else {
        $customers = getCustomers();
        
        if (isset($customers[$email])) {
            $error = I18N::t('account.error.emailExists', 'An account with this email already exists.');
        } else {
            // Create new customer
            $customerId = uniqid('cust_', true);
            // FIX: TASK 4 - Add newsletter opt-in fields
            $customers[$email] = [
                'id' => $customerId,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'salutation' => '',
                'first_name' => '',
                'last_name' => '',
                'phone' => '',
                'shipping_address' => [
                    'street' => '',
                    'house_number' => '',
                    'zip' => '',
                    'city' => '',
                    'country' => ''
                ],
                'billing_address' => [
                    'street' => '',
                    'house_number' => '',
                    'zip' => '',
                    'city' => '',
                    'country' => ''
                ],
                'newsletter_opt_in' => $newsletterOptIn ? 1 : 0,
                'newsletter_opt_in_at' => $newsletterOptIn ? date('c') : null,
                'created_at' => date('c')
            ];
            
            if (saveCustomers($customers)) {
                // Auto-login after registration
                $_SESSION['customer'] = $customers[$email];
                header('Location: account.php?lang=' . $currentLang . '&registered=1');
                exit;
            } else {
                $error = I18N::t('account.error.registrationFailed', 'Registration failed. Please try again.');
            }
        }
    }
}

// Handle forgot password - request code
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'forgot_password') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = I18N::t('account.error.emptyFields', 'Please fill in all fields.');
    } elseif (!isValidEmail($email)) {
        $error = I18N::t('account.error.invalidEmail', 'Invalid email address.');
    } else {
        $result = createPasswordResetRequest($email);
        
        if ($result['success']) {
            $success = I18N::t('account.codeEmailSent', 'A verification code has been sent to your email address.');
            // Store email in session for reset form
            $_SESSION['password_reset_email'] = $email;
        } else {
            switch ($result['error']) {
                case 'email_not_found':
                    $error = I18N::t('account.error.invalidEmail', 'Invalid email address.');
                    break;
                case 'rate_limit':
                    $error = I18N::t('account.codeSentRecently', 'A code was sent recently. Please wait before requesting another.');
                    break;
                default:
                    $error = I18N::t('account.error.registrationFailed', 'An error occurred. Please try again.');
                    break;
            }
        }
    }
}

// Handle password reset with code
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_password') {
    $email = trim($_POST['email'] ?? $_SESSION['password_reset_email'] ?? '');
    $code = trim($_POST['code'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($email) || empty($code) || empty($newPassword) || empty($confirmPassword)) {
        $error = I18N::t('account.error.emptyFields', 'Please fill in all fields.');
    } elseif (strlen($newPassword) < 6) {
        $error = I18N::t('account.error.passwordTooShort', 'Password must be at least 6 characters.');
    } elseif ($newPassword !== $confirmPassword) {
        $error = I18N::t('account.error.passwordMismatch', 'Passwords do not match.');
    } else {
        $result = verifyAndResetPassword($email, $code, $newPassword);
        
        if ($result['success']) {
            $success = I18N::t('account.passwordResetSuccess', 'Your password has been reset successfully. You can now log in with your new password.');
            // Clear session data
            unset($_SESSION['password_reset_email']);
        } else {
            switch ($result['error']) {
                case 'invalid_code':
                    $error = I18N::t('account.invalidCode', 'Invalid or expired verification code.');
                    break;
                case 'code_expired':
                    $error = I18N::t('account.invalidCode', 'Invalid or expired verification code.');
                    break;
                case 'too_many_attempts':
                    $error = I18N::t('account.tooManyAttempts', 'Too many attempts. Please request a new code.');
                    break;
                default:
                    $error = I18N::t('account.error.registrationFailed', 'An error occurred. Please try again.');
                    break;
            }
        }
    }
}


// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    if (!isCustomerLoggedIn()) {
        $error = I18N::t('account.error.notLoggedIn', 'You must be logged in to update your profile.');
    } elseif (empty($_POST['accept_terms'])) {
        $error = I18N::t('account.error.mustAcceptTerms', 'You must accept the Privacy Policy and Terms & Conditions before saving your profile.');
    } else {
        $customer = getCurrentCustomer();
        
        if (!$customer || !isset($customer['email'])) {
            error_log('Profile update failed: No customer or email in session');
            $error = I18N::t('account.error.noEmail', 'Cannot update profile: your session is missing email information. Please log out and log in again.');
        } else {
            // Build shipping address
            $shipping = [
                'street' => trim($_POST['shipping_street'] ?? ''),
                'house_number' => trim($_POST['shipping_house_number'] ?? ''),
                'zip' => trim($_POST['shipping_zip'] ?? ''),
                'city' => trim($_POST['shipping_city'] ?? ''),
                'country' => trim($_POST['shipping_country'] ?? '')
            ];
            
            // Build billing address
            $billing = [
                'street' => trim($_POST['billing_street'] ?? ''),
                'house_number' => trim($_POST['billing_house_number'] ?? ''),
                'zip' => trim($_POST['billing_zip'] ?? ''),
                'city' => trim($_POST['billing_city'] ?? ''),
                'country' => trim($_POST['billing_country'] ?? '')
            ];
            
            $data = [
                'salutation' => trim($_POST['salutation'] ?? ''),
                'first_name' => trim($_POST['first_name'] ?? ''),
                'last_name' => trim($_POST['last_name'] ?? ''),
                'phone' => trim($_POST['phone'] ?? ''),
                'shipping_address' => $shipping,
                'billing_address' => $billing,
            ];
            
            $result = updateCustomerProfile($customer['email'], $data);
            
            if ($result === true) {
                $success = I18N::t('account.success.profileUpdated', 'Profile updated successfully!');
                // Reload customer data from session to reflect the updates in the form
                $customer = getCurrentCustomer();
            } else {
                // $result contains specific error message string
                $error = $result;
            }
        }
    }
}

// Check for success messages
if (isset($_GET['registered']) && $_GET['registered'] === '1') {
    $success = I18N::t('account.success.registered', 'Account created successfully! Welcome!');
}

if (isset($_GET['from']) && $_GET['from'] === 'favorites' && !isCustomerLoggedIn()) {
    $showMessage = I18N::t('account.info.loginForFavorites', 'Please login or register to use favorites.');
}

$isLoggedIn = isCustomerLoggedIn();
$customer = getCurrentCustomer();

// Determine active tab
$activeTab = $_GET['tab'] ?? 'favorites';
if (!$isLoggedIn) {
    $activeTab = 'login';
}

include __DIR__ . '/includes/header.php';
?>

<div class="account-page">
    <?php if (!$isLoggedIn): ?>
        <!-- Login/Registration Forms -->
        <div class="account-box">
            <h2><?php echo I18N::t('account.title', 'My Account'); ?></h2>
            
            <?php if ($showMessage): ?>
                <div class="alert alert--info" style="margin-bottom: 1.5rem; padding: 1rem; background: #e3f2fd; border-radius: 8px; color: #1976d2;">
                    <?php echo htmlspecialchars($showMessage); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert--error" style="margin-bottom: 1.5rem; padding: 1rem; background: #ffebee; border-radius: 8px; color: #c62828;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <div class="account-tabs">
                <button type="button" class="account-tabs__btn account-tabs__btn--active" data-tab="login">
                    <?php echo I18N::t('account.tab.login', 'Login'); ?>
                </button>
                <button type="button" class="account-tabs__btn" data-tab="register">
                    <?php echo I18N::t('account.tab.register', 'Register'); ?>
                </button>
            </div>
            
            <!-- Login Form -->
            <div class="account-tab-content account-tab-content--active" data-tab-content="login">
                <form method="post" action="account.php?lang=<?php echo $currentLang; ?>">
                    <input type="hidden" name="action" value="login">
                    
                    <div class="form-group" style="margin-bottom: 1.5rem;">
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">
                            <?php echo I18N::t('account.field.email', 'Email'); ?>
                        </label>
                        <input type="email" 
                               name="email" 
                               required 
                               style="width: 100%; padding: 0.75rem; border: 1px solid var(--color-stone); border-radius: 8px; font-size: 1rem;">
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 1.5rem;">
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">
                            <?php echo I18N::t('account.field.password', 'Password'); ?>
                        </label>
                        <input type="password" 
                               name="password" 
                               required 
                               style="width: 100%; padding: 0.75rem; border: 1px solid var(--color-stone); border-radius: 8px; font-size: 1rem;">
                    </div>
                    
                    <div style="margin-bottom: 1rem; text-align: right;">
                        <a href="#" onclick="showForgotPasswordForm(); return false;" style="color: var(--color-gold); text-decoration: underline; font-size: 0.9rem;">
                            <?php echo I18N::t('account.forgotPassword', 'Forgot password?'); ?>
                        </a>
                    </div>
                    
                    <button type="submit" class="btn btn--gold" style="width: 100%;">
                        <?php echo I18N::t('account.btn.login', 'Login'); ?>
                    </button>
                </form>
            </div>
            
            <!-- Forgot Password Form -->
            <div class="account-tab-content" data-tab-content="forgot-password" id="forgot-password-content">
                <?php if (!empty($_SESSION['password_reset_email'])): ?>
                    <!-- Reset password with code -->
                    <form method="post" action="account.php?lang=<?php echo $currentLang; ?>">
                        <input type="hidden" name="action" value="reset_password">
                        <input type="hidden" name="email" value="<?php echo htmlspecialchars($_SESSION['password_reset_email']); ?>">
                        
                        <h3 style="margin-bottom: 1rem;"><?php echo I18N::t('account.resetPassword', 'Reset Password'); ?></h3>
                        <p style="margin-bottom: 1.5rem; color: #666;">
                            <?php echo I18N::t('account.enterCodeInstructions', 'Enter the verification code we sent to your email, along with your new password.'); ?>
                        </p>
                        
                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">
                                <?php echo I18N::t('account.verificationCode', 'Verification Code'); ?>
                            </label>
                            <input type="text" 
                                   name="code" 
                                   required 
                                   maxlength="6"
                                   pattern="[0-9]{6}"
                                   placeholder="123456"
                                   style="width: 100%; padding: 0.75rem; border: 1px solid var(--color-stone); border-radius: 8px; font-size: 1rem;">
                        </div>
                        
                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">
                                <?php echo I18N::t('account.newPassword', 'New Password'); ?>
                            </label>
                            <input type="password" 
                                   name="new_password" 
                                   required 
                                   minlength="6"
                                   style="width: 100%; padding: 0.75rem; border: 1px solid var(--color-stone); border-radius: 8px; font-size: 1rem;">
                        </div>
                        
                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">
                                <?php echo I18N::t('account.confirmNewPassword', 'Confirm New Password'); ?>
                            </label>
                            <input type="password" 
                                   name="confirm_password" 
                                   required 
                                   minlength="6"
                                   style="width: 100%; padding: 0.75rem; border: 1px solid var(--color-stone); border-radius: 8px; font-size: 1rem;">
                        </div>
                        
                        <button type="submit" class="btn btn--gold" style="width: 100%; margin-bottom: 1rem;">
                            <?php echo I18N::t('account.resetPassword', 'Reset Password'); ?>
                        </button>
                        
                        <div style="text-align: center;">
                            <a href="#" onclick="showLoginForm(); return false;" style="color: var(--color-gold); text-decoration: underline;">
                                <?php echo I18N::t('account.backToLogin', 'Back to Login'); ?>
                            </a>
                        </div>
                    </form>
                <?php else: ?>
                    <!-- Request reset code -->
                    <form method="post" action="account.php?lang=<?php echo $currentLang; ?>">
                        <input type="hidden" name="action" value="forgot_password">
                        
                        <h3 style="margin-bottom: 1rem;"><?php echo I18N::t('account.forgotPassword', 'Forgot password?'); ?></h3>
                        <p style="margin-bottom: 1.5rem; color: #666;">
                            <?php echo I18N::t('account.resetPasswordInstructions', 'Enter your email address and we will send you a verification code to reset your password.'); ?>
                        </p>
                        
                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">
                                <?php echo I18N::t('account.field.email', 'Email'); ?>
                            </label>
                            <input type="email" 
                                   name="email" 
                                   required 
                                   style="width: 100%; padding: 0.75rem; border: 1px solid var(--color-stone); border-radius: 8px; font-size: 1rem;">
                        </div>
                        
                        <button type="submit" class="btn btn--gold" style="width: 100%; margin-bottom: 1rem;">
                            <?php echo I18N::t('account.sendResetCode', 'Send Reset Code'); ?>
                        </button>
                        
                        <div style="text-align: center;">
                            <a href="#" onclick="showLoginForm(); return false;" style="color: var(--color-gold); text-decoration: underline;">
                                <?php echo I18N::t('account.backToLogin', 'Back to Login'); ?>
                            </a>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
            
            <!-- Register Form -->
            <div class="account-tab-content" data-tab-content="register">
                <form method="post" action="account.php?lang=<?php echo $currentLang; ?>">
                    <input type="hidden" name="action" value="register">
                    
                    <div class="form-group" style="margin-bottom: 1.5rem;">
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">
                            <?php echo I18N::t('account.field.email', 'Email'); ?>
                        </label>
                        <input type="email" 
                               name="email" 
                               required 
                               style="width: 100%; padding: 0.75rem; border: 1px solid var(--color-stone); border-radius: 8px; font-size: 1rem;">
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 1.5rem;">
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">
                            <?php echo I18N::t('account.field.password', 'Password'); ?>
                        </label>
                        <input type="password" 
                               name="password" 
                               required 
                               minlength="6"
                               style="width: 100%; padding: 0.75rem; border: 1px solid var(--color-stone); border-radius: 8px; font-size: 1rem;">
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 1.5rem;">
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">
                            <?php echo I18N::t('account.field.confirmPassword', 'Confirm Password'); ?>
                        </label>
                        <input type="password" 
                               name="confirm_password" 
                               required 
                               minlength="6"
                               style="width: 100%; padding: 0.75rem; border: 1px solid var(--color-stone); border-radius: 8px; font-size: 1rem;">
                    </div>
                    
                    <!-- FIX: TASK 4 - Newsletter opt-in checkbox -->
                    <div class="form-group" style="margin-bottom: 1.5rem;">
                        <label style="display: flex; align-items: flex-start; cursor: pointer;">
                            <input type="checkbox" 
                                   name="newsletter_opt_in" 
                                   id="register_newsletter_opt_in"
                                   value="1"
                                   style="margin-right: 0.5rem; margin-top: 0.25rem; flex-shrink: 0;">
                            <span style="font-size: 0.95rem; line-height: 1.6;">
                                <?php echo I18N::t('account.newsletterOptIn', 'I agree to receive newsletters and promotional emails'); ?>
                            </span>
                        </label>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 1.5rem;">
                        <label style="display: flex; align-items: flex-start; cursor: pointer;">
                            <input type="checkbox" 
                                   name="accept_terms" 
                                   id="register_accept_terms"
                                   value="1"
                                   required
                                   style="margin-right: 0.5rem; margin-top: 0.25rem; flex-shrink: 0;">
                            <span style="font-size: 0.95rem; line-height: 1.6;">
                                <?php
                                $termsLink = '<a href="terms-and-conditions.php?lang=' . $currentLang . '" target="_blank" style="color: var(--color-gold); text-decoration: underline;">' . I18N::t('account.termsAndConditions', 'Terms & Conditions') . '</a>';
                                $privacyLink = '<a href="privacy-policy.php?lang=' . $currentLang . '" target="_blank" style="color: var(--color-gold); text-decoration: underline;">' . I18N::t('account.privacyPolicy', 'Privacy Policy') . '</a>';
                                
                                $consentText = I18N::t('account.acceptTermsAndPrivacy', 'I agree to the {terms} and {privacy}');
                                $consentText = str_replace('{terms}', $termsLink, $consentText);
                                $consentText = str_replace('{privacy}', $privacyLink, $consentText);
                                
                                echo $consentText;
                                ?>
                            </span>
                        </label>
                    </div>
                    
                    <button type="submit" class="btn btn--gold" id="register-submit-btn" style="width: 100%;">
                        <?php echo I18N::t('account.btn.register', 'Register'); ?>
                    </button>
                </form>
            </div>
        </div>
    <?php else: ?>
        <!-- Logged in View with Tabs -->
        <div class="account-box">
            <?php if ($success): ?>
                <div class="alert alert--success" style="margin-bottom: 1.5rem; padding: 1rem; background: #e8f5e9; border-radius: 8px; color: #2e7d32;">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert--error" style="margin-bottom: 1.5rem; padding: 1rem; background: #ffebee; border-radius: 8px; color: #c62828;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                <div>
                    <h2 style="margin-bottom: 0.5rem;">
                        <?php
                        $salutation = $customer['salutation'] ?? '';
                        $firstName = trim($customer['first_name'] ?? '');
                        $lastName = trim($customer['last_name'] ?? '');
                        $displayName = trim($firstName . ' ' . $lastName);
                        
                        if ($salutation === 'Mr') {
                            $salutationLabel = I18N::t('account.salutation.mr', 'Mr');
                        } elseif ($salutation === 'Mrs') {
                            $salutationLabel = I18N::t('account.salutation.mrs', 'Mrs');
                        } else {
                            $salutationLabel = '';
                        }
                        
                        $welcomeText = I18N::t('account.welcome', 'Welcome');
                        
                        echo htmlspecialchars($welcomeText) . ', ';
                        
                        if ($salutationLabel !== '' || $displayName !== '') {
                            echo htmlspecialchars(trim($salutationLabel . ' ' . $displayName));
                        } else {
                            echo htmlspecialchars($customer['email']);
                        }
                        ?>
                    </h2>
                </div>
            </div>
            
            <!-- Account Tabs -->
            <div class="account-tabs">
                <a href="account.php?tab=profile&lang=<?php echo $currentLang; ?>" 
                   class="account-tabs__btn <?php echo $activeTab === 'profile' ? 'account-tabs__btn--active' : ''; ?>">
                    <?php echo I18N::t('account.tab.profile', 'Profile'); ?>
                </a>
                <a href="account.php?tab=orders&lang=<?php echo $currentLang; ?>" 
                   class="account-tabs__btn <?php echo $activeTab === 'orders' ? 'account-tabs__btn--active' : ''; ?>">
                    <?php echo I18N::t('account.tab.orders', 'My Orders'); ?>
                </a>
                <a href="account.php?tab=favorites&lang=<?php echo $currentLang; ?>" 
                   class="account-tabs__btn <?php echo $activeTab === 'favorites' ? 'account-tabs__btn--active' : ''; ?>">
                    <?php echo I18N::t('account.tab.favorites', 'My Favorites'); ?>
                </a>
            </div>
            
            <!-- Profile Tab -->
            <?php if ($activeTab === 'profile'): ?>
                <div class="account-tab-content account-tab-content--active">
                    <form method="post" action="account.php?tab=profile&lang=<?php echo $currentLang; ?>" style="margin-top: 2rem;">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">
                                <?php echo I18N::t('account.field.salutation', 'Salutation'); ?>
                            </label>
                            <select name="salutation" 
                                    style="width: 100%; padding: 0.75rem; border: 1px solid var(--color-stone); border-radius: 8px; font-size: 1rem; background: white;">
                                <option value=""><?php echo I18N::t('account.salutation.none', 'Please select'); ?></option>
                                <option value="Mr" <?php echo ($customer['salutation'] ?? '') === 'Mr' ? 'selected' : ''; ?>>
                                    <?php echo I18N::t('account.salutation.mr', 'Mr'); ?>
                                </option>
                                <option value="Mrs" <?php echo ($customer['salutation'] ?? '') === 'Mrs' ? 'selected' : ''; ?>>
                                    <?php echo I18N::t('account.salutation.mrs', 'Mrs'); ?>
                                </option>
                            </select>
                        </div>
                        
                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">
                                <?php echo I18N::t('account.field.firstName', 'First Name'); ?>
                            </label>
                            <input type="text" 
                                   name="first_name" 
                                   value="<?php echo htmlspecialchars($customer['first_name'] ?? ''); ?>"
                                   style="width: 100%; padding: 0.75rem; border: 1px solid var(--color-stone); border-radius: 8px; font-size: 1rem;">
                        </div>
                        
                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">
                                <?php echo I18N::t('account.field.lastName', 'Last Name'); ?>
                            </label>
                            <input type="text" 
                                   name="last_name" 
                                   value="<?php echo htmlspecialchars($customer['last_name'] ?? ''); ?>"
                                   style="width: 100%; padding: 0.75rem; border: 1px solid var(--color-stone); border-radius: 8px; font-size: 1rem;">
                        </div>
                        
                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">
                                <?php echo I18N::t('account.field.email', 'Email'); ?>
                            </label>
                            <input type="email" 
                                   value="<?php echo htmlspecialchars($customer['email']); ?>"
                                   readonly
                                   style="width: 100%; padding: 0.75rem; border: 1px solid var(--color-stone); border-radius: 8px; font-size: 1rem; background: #f5f5f5;">
                            <small style="color: var(--color-muted);"><?php echo I18N::t('account.field.emailReadonly', 'Email cannot be changed'); ?></small>
                        </div>
                        
                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">
                                <?php echo I18N::t('account.field.phone', 'Phone'); ?>
                            </label>
                            <input type="tel" 
                                   name="phone" 
                                   value="<?php echo htmlspecialchars($customer['phone'] ?? ''); ?>"
                                   style="width: 100%; padding: 0.75rem; border: 1px solid var(--color-stone); border-radius: 8px; font-size: 1rem;">
                        </div>
                        
                        <?php 
                        // Get shipping and billing addresses - handle both old and new format
                        $shippingAddr = $customer['shipping_address'] ?? [];
                        if (is_string($shippingAddr)) {
                            // Old format - convert to new format
                            $shippingAddr = [
                                'street' => '',
                                'house_number' => '',
                                'zip' => '',
                                'city' => '',
                                'country' => ''
                            ];
                        }
                        
                        $billingAddr = $customer['billing_address'] ?? [];
                        if (is_string($billingAddr)) {
                            // Old format - convert to new format
                            $billingAddr = [
                                'street' => '',
                                'house_number' => '',
                                'zip' => '',
                                'city' => '',
                                'country' => ''
                            ];
                        }
                        
                        $countries = getEuropeanCountries();
                        ?>
                        
                        <!-- Shipping Address -->
                        <h3 style="margin: 2rem 0 1rem; color: var(--color-charcoal);">
                            <?php echo I18N::t('account.field.shippingAddress', 'Shipping Address'); ?>
                        </h3>
                        
                        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                            <div class="form-group">
                                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">
                                    <?php echo I18N::t('account.field.street', 'Street'); ?>
                                </label>
                                <input type="text" 
                                       name="shipping_street" 
                                       value="<?php echo htmlspecialchars($shippingAddr['street'] ?? ''); ?>"
                                       placeholder="<?php echo I18N::t('account.placeholder.street', 'Street name'); ?>"
                                       style="width: 100%; padding: 0.75rem; border: 1px solid var(--color-stone); border-radius: 8px; font-size: 1rem;">
                            </div>
                            
                            <div class="form-group">
                                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">
                                    <?php echo I18N::t('account.field.houseNumber', 'House Number'); ?>
                                </label>
                                <input type="text" 
                                       name="shipping_house_number" 
                                       value="<?php echo htmlspecialchars($shippingAddr['house_number'] ?? ''); ?>"
                                       placeholder="<?php echo I18N::t('account.placeholder.houseNumber', '12A'); ?>"
                                       style="width: 100%; padding: 0.75rem; border: 1px solid var(--color-stone); border-radius: 8px; font-size: 1rem;">
                            </div>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 1rem; margin-bottom: 1rem;">
                            <div class="form-group">
                                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">
                                    <?php echo I18N::t('account.field.zip', 'ZIP Code'); ?>
                                </label>
                                <input type="text" 
                                       name="shipping_zip" 
                                       value="<?php echo htmlspecialchars($shippingAddr['zip'] ?? ''); ?>"
                                       placeholder="<?php echo I18N::t('account.placeholder.zip', '8000'); ?>"
                                       style="width: 100%; padding: 0.75rem; border: 1px solid var(--color-stone); border-radius: 8px; font-size: 1rem;">
                            </div>
                            
                            <div class="form-group">
                                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">
                                    <?php echo I18N::t('account.field.city', 'City'); ?>
                                </label>
                                <input type="text" 
                                       name="shipping_city" 
                                       value="<?php echo htmlspecialchars($shippingAddr['city'] ?? ''); ?>"
                                       placeholder="<?php echo I18N::t('account.placeholder.city', 'City name'); ?>"
                                       style="width: 100%; padding: 0.75rem; border: 1px solid var(--color-stone); border-radius: 8px; font-size: 1rem;">
                            </div>
                        </div>
                        
                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">
                                <?php echo I18N::t('account.field.country', 'Country'); ?>
                            </label>
                            <select name="shipping_country"
                                    style="width: 100%; padding: 0.75rem; border: 1px solid var(--color-stone); border-radius: 8px; font-size: 1rem; background: white;">
                                <option value=""><?php echo I18N::t('account.placeholder.selectCountry', 'Select a country'); ?></option>
                                <?php foreach ($countries as $country): ?>
                                    <option value="<?php echo htmlspecialchars($country); ?>" 
                                            <?php echo ($shippingAddr['country'] ?? '') === $country ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($country); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Billing Address -->
                        <h3 style="margin: 2rem 0 1rem; color: var(--color-charcoal);">
                            <?php echo I18N::t('account.field.billingAddress', 'Billing Address'); ?>
                        </h3>
                        <small style="color: var(--color-muted); display: block; margin-bottom: 1rem;">
                            <?php echo I18N::t('account.field.billingHint', 'Leave empty if same as shipping'); ?>
                        </small>
                        
                        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                            <div class="form-group">
                                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">
                                    <?php echo I18N::t('account.field.street', 'Street'); ?>
                                </label>
                                <input type="text" 
                                       name="billing_street" 
                                       value="<?php echo htmlspecialchars($billingAddr['street'] ?? ''); ?>"
                                       placeholder="<?php echo I18N::t('account.placeholder.street', 'Street name'); ?>"
                                       style="width: 100%; padding: 0.75rem; border: 1px solid var(--color-stone); border-radius: 8px; font-size: 1rem;">
                            </div>
                            
                            <div class="form-group">
                                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">
                                    <?php echo I18N::t('account.field.houseNumber', 'House Number'); ?>
                                </label>
                                <input type="text" 
                                       name="billing_house_number" 
                                       value="<?php echo htmlspecialchars($billingAddr['house_number'] ?? ''); ?>"
                                       placeholder="<?php echo I18N::t('account.placeholder.houseNumber', '12A'); ?>"
                                       style="width: 100%; padding: 0.75rem; border: 1px solid var(--color-stone); border-radius: 8px; font-size: 1rem;">
                            </div>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 1rem; margin-bottom: 1rem;">
                            <div class="form-group">
                                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">
                                    <?php echo I18N::t('account.field.zip', 'ZIP Code'); ?>
                                </label>
                                <input type="text" 
                                       name="billing_zip" 
                                       value="<?php echo htmlspecialchars($billingAddr['zip'] ?? ''); ?>"
                                       placeholder="<?php echo I18N::t('account.placeholder.zip', '8000'); ?>"
                                       style="width: 100%; padding: 0.75rem; border: 1px solid var(--color-stone); border-radius: 8px; font-size: 1rem;">
                            </div>
                            
                            <div class="form-group">
                                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">
                                    <?php echo I18N::t('account.field.city', 'City'); ?>
                                </label>
                                <input type="text" 
                                       name="billing_city" 
                                       value="<?php echo htmlspecialchars($billingAddr['city'] ?? ''); ?>"
                                       placeholder="<?php echo I18N::t('account.placeholder.city', 'City name'); ?>"
                                       style="width: 100%; padding: 0.75rem; border: 1px solid var(--color-stone); border-radius: 8px; font-size: 1rem;">
                            </div>
                        </div>
                        
                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">
                                <?php echo I18N::t('account.field.country', 'Country'); ?>
                            </label>
                            <select name="billing_country"
                                    style="width: 100%; padding: 0.75rem; border: 1px solid var(--color-stone); border-radius: 8px; font-size: 1rem; background: white;">
                                <option value=""><?php echo I18N::t('account.placeholder.selectCountry', 'Select a country'); ?></option>
                                <?php foreach ($countries as $country): ?>
                                    <option value="<?php echo htmlspecialchars($country); ?>" 
                                            <?php echo ($billingAddr['country'] ?? '') === $country ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($country); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label style="display: flex; align-items: flex-start; gap: 0.5rem;">
                                <input type="checkbox" 
                                       name="accept_terms" 
                                       value="1" 
                                       required
                                       style="margin-top: 0.25rem;">
                                <span>
                                    <?php
                                    $termsLink = '<a href="terms-and-conditions.php?lang=' . $currentLang . '" target="_blank" rel="noopener" style="color: var(--color-gold); text-decoration: underline;">' . I18N::t('account.consentTermsLink', 'Terms and Conditions') . '</a>';
                                    $privacyLink = '<a href="privacy-policy.php?lang=' . $currentLang . '" target="_blank" rel="noopener" style="color: var(--color-gold); text-decoration: underline;">' . I18N::t('account.consentPrivacyLink', 'Privacy Policy') . '</a>';
                                    
                                    $consentText = I18N::t('account.consentLabel', 'I have read and agree to the {terms} and {privacy}, and consent to the processing of my personal data for profile management purposes.');
                                    $consentText = str_replace('{terms}', $termsLink, $consentText);
                                    $consentText = str_replace('{privacy}', $privacyLink, $consentText);
                                    
                                    echo $consentText;
                                    ?>
                                </span>
                            </label>
                        </div>
                        
                        <button type="submit" class="btn btn--gold" style="width: 100%;">
                            <?php echo I18N::t('account.btn.save', 'Save Profile'); ?>
                        </button>
                    </form>
                </div>
            <?php endif; ?>
            
            <!-- Orders Tab -->
            <?php if ($activeTab === 'orders'): ?>
                <div class="account-tab-content account-tab-content--active">
                    <?php
                    $orders = getCustomerOrders($customer['id']);
                    $orderId = $_GET['order_id'] ?? null;
                    
                    if ($orderId && isset($orders[$orderId])):
                        $order = $orders[$orderId];
                    ?>
                        <!-- Order Detail View -->
                        <div style="margin-top: 2rem;">
                            <a href="account.php?tab=orders&lang=<?php echo $currentLang; ?>" style="color: var(--color-gold); text-decoration: none; margin-bottom: 1rem; display: inline-block;">
                                ← <?php echo I18N::t('account.backToOrders', 'Back to orders'); ?>
                            </a>
                            
                            <h3><?php echo I18N::t('account.orderDetails', 'Order Details'); ?></h3>
                            
                            <div style="background: #f9f9f9; padding: 1rem; border-radius: 8px; margin: 1rem 0;">
                                <p><strong><?php echo I18N::t('account.orderId', 'Order ID'); ?>:</strong> <?php echo htmlspecialchars($order['id']); ?></p>
                                <p><strong><?php echo I18N::t('account.orderDate', 'Date'); ?>:</strong> <?php echo date('Y-m-d H:i', strtotime($order['created_at'])); ?></p>
                                <p><strong><?php echo I18N::t('account.orderStatus', 'Status'); ?>:</strong> <?php echo htmlspecialchars($order['status'] ?? 'pending'); ?></p>
                                <p><strong><?php echo I18N::t('account.orderTotal', 'Total'); ?>:</strong> CHF <?php echo number_format($order['total'] ?? 0, 2); ?></p>
                            </div>
                            
                            <?php if (!empty($order['items'])): ?>
                                <h4><?php echo I18N::t('account.orderItems', 'Items'); ?></h4>
                                <table style="width: 100%; border-collapse: collapse; margin-top: 1rem;">
                                    <thead>
                                        <tr style="border-bottom: 2px solid var(--color-sand);">
                                            <th style="text-align: left; padding: 0.75rem;"><?php echo I18N::t('common.product', 'Product'); ?></th>
                                            <th style="text-align: right; padding: 0.75rem;"><?php echo I18N::t('common.quantity', 'Quantity'); ?></th>
                                            <th style="text-align: right; padding: 0.75rem;"><?php echo I18N::t('common.price', 'Price'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($order['items'] as $item): ?>
                                            <tr style="border-bottom: 1px solid var(--color-sand);">
                                                <td style="padding: 0.75rem;">
                                                    <?php echo htmlspecialchars($item['name'] ?? $item['product_id']); ?>
                                                    <?php if (!empty($item['volume'])): ?>
                                                        <br><small><?php echo htmlspecialchars($item['volume']); ?></small>
                                                    <?php endif; ?>
                                                    <?php if (!empty($item['fragrance']) && $item['fragrance'] !== 'none'): ?>
                                                        <br><small><?php echo htmlspecialchars($item['fragrance']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="text-align: right; padding: 0.75rem;"><?php echo (int)($item['qty'] ?? $item['quantity'] ?? 1); ?></td>
                                                <td style="text-align: right; padding: 0.75rem;">CHF <?php echo number_format($item['price'] ?? 0, 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <!-- Orders List View -->
                        <div style="margin-top: 2rem;">
                            <?php if (empty($orders)): ?>
                                <div class="favorites-empty">
                                    <h3><?php echo I18N::t('account.orders.empty', 'No orders yet'); ?></h3>
                                    <p><?php echo I18N::t('account.orders.emptyDesc', 'Your order history will appear here.'); ?></p>
                                    <a href="catalog.php?lang=<?php echo $currentLang; ?>" class="btn btn--gold" style="margin-top: 1rem;">
                                        <?php echo I18N::t('account.orders.browseCatalog', 'Browse Catalog'); ?>
                                    </a>
                                </div>
                            <?php else: ?>
                                <div style="display: flex; flex-direction: column; gap: 1rem;">
                                    <?php foreach ($orders as $order): ?>
                                        <a href="account.php?tab=orders&order_id=<?php echo htmlspecialchars($order['id']); ?>&lang=<?php echo $currentLang; ?>" 
                                           style="display: block; padding: 1.5rem; background: #f9f9f9; border-radius: 8px; text-decoration: none; color: inherit; transition: all 0.2s;">
                                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                                <strong><?php echo htmlspecialchars($order['id']); ?></strong>
                                                <span style="padding: 0.25rem 0.75rem; background: var(--color-gold); color: var(--color-charcoal); border-radius: 4px; font-size: 0.85rem;">
                                                    <?php echo htmlspecialchars($order['status'] ?? 'pending'); ?>
                                                </span>
                                            </div>
                                            <div style="color: var(--color-muted); font-size: 0.9rem;">
                                                <?php echo date('F j, Y', strtotime($order['created_at'])); ?> • 
                                                CHF <?php echo number_format($order['total'] ?? 0, 2); ?>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- Favorites Tab -->
            <?php if ($activeTab === 'favorites'): ?>
                <div class="account-tab-content account-tab-content--active">
                    <?php
                    $favoriteProducts = loadFavoriteProducts($customer['id']);
                    ?>
                    
                    <?php if (empty($favoriteProducts)): ?>
                        <div class="favorites-empty">
                            <h3><?php echo I18N::t('account.favorites.empty', 'No favorites yet'); ?></h3>
                            <p><?php echo I18N::t('account.favorites.emptyDesc', 'Start adding products to your favorites by clicking the heart icon.'); ?></p>
                            <a href="catalog.php?lang=<?php echo $currentLang; ?>" class="btn btn--gold" style="margin-top: 1rem;">
                                <?php echo I18N::t('account.favorites.browseCatalog', 'Browse Catalog'); ?>
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="account-favorites-container">
                            <div class="favorites-grid">
                                <?php foreach ($favoriteProducts as $product): ?>
                                    <?php
                                    // Get category slug for linking
                                    $categorySlug = $product['category'] ?? 'aroma_diffusers';
                                    // Accessories should link to product.php, other categories to category.php
                                    $isAccessory = $categorySlug === 'accessories';
                                    $productUrl = $isAccessory 
                                        ? "product.php?id=" . htmlspecialchars($product['id']) . "&lang=" . $currentLang
                                        : "category.php?slug=" . htmlspecialchars($categorySlug) . "&lang=" . $currentLang;
                                    ?>
                                    <article class="catalog-card favorites-card" data-product-id="<?php echo htmlspecialchars($product['id']); ?>">
                                        <a href="<?php echo $productUrl; ?>" 
                                           style="text-decoration: none; color: inherit;">
                                            <div class="catalog-card-image-wrapper favorites-card__image-wrapper">
                                                <img src="/<?php echo htmlspecialchars($product['image_url']); ?>" 
                                                     alt="<?php echo htmlspecialchars($product['display_name']); ?>"
                                                     class="catalog-card__image favorites-card__image"
                                                     onerror="this.src='/img/placeholder.svg'">
                                            </div>
                                            <div class="catalog-card__title-bar favorites-card__body">
                                                <?php echo htmlspecialchars($product['display_name']); ?>
                                            </div>
                                        </a>
                                        <button class="favorite-btn favorite-btn--active" 
                                                data-product-id="<?php echo htmlspecialchars($product['id']); ?>"
                                                type="button"
                                                title="<?php echo I18N::t('common.removeFromFavorites', 'Remove from favorites'); ?>">
                                            ❤
                                        </button>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
// Function to show forgot password form
function showForgotPasswordForm() {
    const tabContents = document.querySelectorAll('.account-tab-content[data-tab-content]');
    tabContents.forEach(content => {
        content.classList.remove('account-tab-content--active');
    });
    
    const forgotPasswordContent = document.querySelector('[data-tab-content="forgot-password"]');
    if (forgotPasswordContent) {
        forgotPasswordContent.classList.add('account-tab-content--active');
    }
    
    // Remove active state from tab buttons
    const tabButtons = document.querySelectorAll('.account-tabs__btn');
    tabButtons.forEach(btn => btn.classList.remove('account-tabs__btn--active'));
}

// Function to show login form
function showLoginForm() {
    const tabContents = document.querySelectorAll('.account-tab-content[data-tab-content]');
    tabContents.forEach(content => {
        content.classList.remove('account-tab-content--active');
    });
    
    const loginContent = document.querySelector('[data-tab-content="login"]');
    if (loginContent) {
        loginContent.classList.add('account-tab-content--active');
    }
    
    // Set login tab as active
    const loginButton = document.querySelector('.account-tabs__btn[data-tab="login"]');
    if (loginButton) {
        const tabButtons = document.querySelectorAll('.account-tabs__btn');
        tabButtons.forEach(btn => btn.classList.remove('account-tabs__btn--active'));
        loginButton.classList.add('account-tabs__btn--active');
    }
}

// Tab switching functionality for login/register only
document.addEventListener('DOMContentLoaded', function() {
    const tabButtons = document.querySelectorAll('.account-tabs__btn[data-tab]');
    const tabContents = document.querySelectorAll('.account-tab-content[data-tab-content]');
    
    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            const targetTab = this.dataset.tab;
            
            // Update button states
            tabButtons.forEach(btn => btn.classList.remove('account-tabs__btn--active'));
            this.classList.add('account-tabs__btn--active');
            
            // Update content visibility
            tabContents.forEach(content => {
                if (content.dataset.tabContent === targetTab) {
                    content.classList.add('account-tab-content--active');
                } else {
                    content.classList.remove('account-tab-content--active');
                }
            });
        });
    });
    
    // Set custom validation message for consent checkbox
    const consentCheckbox = document.querySelector('input[name="accept_terms"]');
    if (consentCheckbox) {
        const validationMessage = <?php echo json_encode(I18N::t('validation.acceptTermsTooltip', 'To continue, please check this checkbox.')); ?>;
        
        consentCheckbox.addEventListener('invalid', function() {
            this.setCustomValidity(validationMessage);
        });
        
        consentCheckbox.addEventListener('change', function() {
            this.setCustomValidity('');
        });
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
