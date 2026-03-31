<?php
/**
 * Forgot Password Page - Request password reset
 */

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/includes/email/mailer.php';
require_once __DIR__ . '/includes/email/templates.php';

$currentLang = I18N::getLanguage();
$error = '';
$success = '';
$email = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = I18N::t('forgotPassword.error.emptyEmail', 'Please enter your email address.');
    } elseif (!isValidEmail($email)) {
        $error = I18N::t('forgotPassword.error.invalidEmail', 'Please enter a valid email address.');
    } else {
        // Create reset token
        $result = createPasswordResetToken($email);
        
        if ($result['success'] && $result['token'] !== null) {
            // Send reset email
            $token = $result['token'];
            
            // Build reset URL with proper base path detection
            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'];
            $scriptPath = dirname($_SERVER['SCRIPT_NAME']);
            $basePath = ($scriptPath === '/' || $scriptPath === '\\') ? '' : $scriptPath;
            
            $resetUrl = $protocol . '://' . $host . $basePath . '/reset-password.php?token=' . urlencode($token) . '&lang=' . $currentLang;
            
            // Get subject and body based on current language
            $subject = I18N::t('email.passwordReset.subject', 'Reset your password');
            
            $htmlBody = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #2c2c2c; color: #d4af37; padding: 20px; text-align: center; }
        .content { background: #f9f9f9; padding: 30px; }
        .button { display: inline-block; padding: 12px 24px; background: #d4af37; color: #2c2c2c; text-decoration: none; border-radius: 4px; font-weight: bold; margin: 20px 0; }
        .footer { padding: 20px; text-align: center; color: #666; font-size: 14px; }
        .warning { background: #fff3cd; border: 1px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>NicheHome.ch</h1>
        </div>
        <div class="content">
            <h2>' . I18N::t('email.passwordReset.title', 'Password Reset Request') . '</h2>
            <p>' . I18N::t('email.passwordReset.greeting', 'Hello') . ',</p>
            <p>' . I18N::t('email.passwordReset.message', 'We received a request to reset your password. Click the button below to set a new password:') . '</p>
            <p style="text-align: center;">
                <a href="' . htmlspecialchars($resetUrl) . '" class="button">' . I18N::t('email.passwordReset.button', 'Reset Password') . '</a>
            </p>
            <div class="warning">
                <strong>' . I18N::t('email.passwordReset.warningTitle', 'Important:') . '</strong>
                <ul style="margin: 10px 0; padding-left: 20px;">
                    <li>' . I18N::t('email.passwordReset.warning1', 'This link expires in 60 minutes') . '</li>
                    <li>' . I18N::t('email.passwordReset.warning2', 'The link can only be used once') . '</li>
                    <li>' . I18N::t('email.passwordReset.warning3', 'If you didn\'t request this, please ignore this email') . '</li>
                </ul>
            </div>
            <p style="margin-top: 20px; font-size: 14px; color: #666;">
                ' . I18N::t('email.passwordReset.altLink', 'If the button doesn\'t work, copy and paste this link into your browser:') . '<br>
                <a href="' . htmlspecialchars($resetUrl) . '" style="color: #d4af37; word-break: break-all;">' . htmlspecialchars($resetUrl) . '</a>
            </p>
        </div>
        <div class="footer">
            <p>' . I18N::t('email.footer', 'Thank you for shopping with NicheHome.ch') . '</p>
        </div>
    </div>
</body>
</html>';
            
            $textBody = I18N::t('email.passwordReset.title', 'Password Reset Request') . "\n\n"
                      . I18N::t('email.passwordReset.message', 'We received a request to reset your password. Click the link below to set a new password:') . "\n\n"
                      . $resetUrl . "\n\n"
                      . I18N::t('email.passwordReset.warning1', 'This link expires in 60 minutes') . "\n"
                      . I18N::t('email.passwordReset.warning2', 'The link can only be used once') . "\n"
                      . I18N::t('email.passwordReset.warning3', 'If you didn\'t request this, please ignore this email') . "\n\n"
                      . I18N::t('email.footer', 'Thank you for shopping with NicheHome.ch');
            
            $emailResult = sendEmailViaSMTP(
                $email,
                $subject,
                $htmlBody,
                $textBody,
                null,
                'password_reset',
                ['email' => $email, 'language' => $currentLang]
            );
            
            if (!$emailResult['success']) {
                error_log("Failed to send password reset email to $email: " . ($emailResult['error'] ?? 'unknown error'));
            }
        }
        
        // Always show success message (don't reveal if email exists)
        $success = I18N::t('forgotPassword.success', 'If your email address exists in our system, you will receive a password reset link shortly. Please check your inbox and spam folder.');
        $email = ''; // Clear the email field
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="account-page">
    <div class="account-box">
        <h2><?php echo I18N::t('forgotPassword.title', 'Forgot Password'); ?></h2>
        
        <?php if ($success): ?>
            <div class="alert alert--success" style="margin-bottom: 1.5rem; padding: 1rem; background: #e8f5e9; border-radius: 8px; color: #2e7d32;">
                <?php echo htmlspecialchars($success); ?>
            </div>
            <div style="text-align: center; margin-top: 2rem;">
                <a href="account.php?lang=<?php echo $currentLang; ?>" class="btn btn--gold">
                    <?php echo I18N::t('forgotPassword.backToLogin', 'Back to Login'); ?>
                </a>
            </div>
        <?php else: ?>
            <?php if ($error): ?>
                <div class="alert alert--error" style="margin-bottom: 1.5rem; padding: 1rem; background: #ffebee; border-radius: 8px; color: #c62828;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <p style="color: #666; margin-bottom: 2rem;">
                <?php echo I18N::t('forgotPassword.description', 'Enter your email address and we\'ll send you a link to reset your password.'); ?>
            </p>
            
            <form method="post" action="forgot-password.php?lang=<?php echo $currentLang; ?>">
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">
                        <?php echo I18N::t('account.field.email', 'Email'); ?>
                    </label>
                    <input type="email" 
                           name="email" 
                           required 
                           value="<?php echo htmlspecialchars($email); ?>"
                           style="width: 100%; padding: 0.75rem; border: 1px solid var(--color-stone); border-radius: 8px; font-size: 1rem;">
                </div>
                
                <button type="submit" class="btn btn--gold" style="width: 100%;">
                    <?php echo I18N::t('forgotPassword.submitButton', 'Send Reset Link'); ?>
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
