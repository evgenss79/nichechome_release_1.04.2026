<?php
/**
 * Reset Password Page - Set new password with token
 */

require_once __DIR__ . '/init.php';

$currentLang = I18N::getLanguage();
$token = $_GET['token'] ?? '';
$error = '';
$success = '';

// Validate token on page load (cached for this page view)
$tokenValidation = null;
if (!empty($token) && !isset($_SESSION['_token_validation_' . substr($token, 0, 8)])) {
    $tokenValidation = validatePasswordResetToken($token);
    // Cache validation result for this token during the session to avoid repeated checks
    $_SESSION['_token_validation_' . substr($token, 0, 8)] = $tokenValidation;
} elseif (!empty($token)) {
    $tokenValidation = $_SESSION['_token_validation_' . substr($token, 0, 8)] ?? null;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = trim($_POST['token'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($token)) {
        $error = I18N::t('resetPassword.error.invalidToken', 'Invalid reset link.');
    } elseif (empty($newPassword) || empty($confirmPassword)) {
        $error = I18N::t('resetPassword.error.emptyFields', 'Please fill in all fields.');
    } elseif (strlen($newPassword) < 6) {
        $error = I18N::t('resetPassword.error.passwordTooShort', 'Password must be at least 6 characters.');
    } elseif ($newPassword !== $confirmPassword) {
        $error = I18N::t('resetPassword.error.passwordMismatch', 'Passwords do not match.');
    } else {
        // Reset password
        $result = resetPasswordWithToken($token, $newPassword);
        
        if ($result['success']) {
            $success = I18N::t('resetPassword.success', 'Your password has been reset successfully. You can now log in with your new password.');
        } else {
            $error = $result['error'] ?? I18N::t('resetPassword.error.failed', 'Failed to reset password. Please try again.');
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="account-page">
    <div class="account-box">
        <h2><?php echo I18N::t('resetPassword.title', 'Reset Password'); ?></h2>
        
        <?php if ($success): ?>
            <div class="alert alert--success" style="margin-bottom: 1.5rem; padding: 1rem; background: #e8f5e9; border-radius: 8px; color: #2e7d32;">
                <?php echo htmlspecialchars($success); ?>
            </div>
            <div style="text-align: center; margin-top: 2rem;">
                <a href="account.php?lang=<?php echo $currentLang; ?>" class="btn btn--gold">
                    <?php echo I18N::t('common.login', 'Log In'); ?>
                </a>
            </div>
        <?php elseif (empty($token) || ($tokenValidation && !$tokenValidation['valid'])): ?>
            <div class="alert alert--error" style="margin-bottom: 1.5rem; padding: 1rem; background: #ffebee; border-radius: 8px; color: #c62828;">
                <?php 
                if ($tokenValidation && $tokenValidation['error']) {
                    echo htmlspecialchars($tokenValidation['error']);
                } else {
                    echo htmlspecialchars(I18N::t('resetPassword.error.invalidToken', 'Invalid or expired reset link.'));
                }
                ?>
            </div>
            <div style="text-align: center; margin-top: 2rem;">
                <a href="forgot-password.php?lang=<?php echo $currentLang; ?>" class="btn btn--gold">
                    <?php echo I18N::t('resetPassword.requestNew', 'Request New Reset Link'); ?>
                </a>
            </div>
        <?php else: ?>
            <?php if ($error): ?>
                <div class="alert alert--error" style="margin-bottom: 1.5rem; padding: 1rem; background: #ffebee; border-radius: 8px; color: #c62828;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <p style="color: #666; margin-bottom: 2rem;">
                <?php echo I18N::t('resetPassword.description', 'Please enter your new password below.'); ?>
            </p>
            
            <form method="post" action="reset-password.php?lang=<?php echo $currentLang; ?>">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">
                        <?php echo I18N::t('resetPassword.newPassword', 'New Password'); ?>
                    </label>
                    <input type="password" 
                           name="new_password" 
                           required 
                           minlength="6"
                           style="width: 100%; padding: 0.75rem; border: 1px solid var(--color-stone); border-radius: 8px; font-size: 1rem;">
                    <small style="color: #666; font-size: 0.9rem; margin-top: 0.25rem; display: block;">
                        <?php echo I18N::t('resetPassword.passwordHint', 'At least 6 characters'); ?>
                    </small>
                </div>
                
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">
                        <?php echo I18N::t('resetPassword.confirmPassword', 'Confirm New Password'); ?>
                    </label>
                    <input type="password" 
                           name="confirm_password" 
                           required 
                           minlength="6"
                           style="width: 100%; padding: 0.75rem; border: 1px solid var(--color-stone); border-radius: 8px; font-size: 1rem;">
                </div>
                
                <button type="submit" class="btn btn--gold" style="width: 100%;">
                    <?php echo I18N::t('resetPassword.submitButton', 'Reset Password'); ?>
                </button>
            </form>
            
            <div style="text-align: center; margin-top: 2rem; padding-top: 2rem; border-top: 1px solid #ddd;">
                <a href="account.php?lang=<?php echo $currentLang; ?>" style="color: var(--color-gold); text-decoration: none;">
                    ← <?php echo I18N::t('forgotPassword.backToLogin', 'Back to Login'); ?>
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
