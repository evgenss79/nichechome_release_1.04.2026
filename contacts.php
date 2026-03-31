<?php
/**
 * Contacts - Contact page
 */

require_once __DIR__ . '/init.php';
include __DIR__ . '/includes/header.php';

$currentLang = I18N::getLanguage();
$success = false;
$error = '';

// Load active branches for display
$activeBranches = getActiveBranches();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $message = sanitize($_POST['message'] ?? '');
    $phone = sanitize($_POST['phone'] ?? ''); // Optional phone field
    
    if (empty($name) || empty($email) || empty($message)) {
        $error = I18N::t('page.contacts.errorRequired', 'All fields are required.');
    } elseif (!isValidEmail($email)) {
        $error = I18N::t('page.contacts.errorInvalidEmail', 'Please enter a valid email address.');
    } else {
        // Send email notification to support
        require_once __DIR__ . '/includes/email/mailer.php';
        require_once __DIR__ . '/includes/email/templates.php';
        
        $settings = loadEmailSettings();
        $supportEmail = $settings['routing']['support_email'] ?? 'support@nichehome.ch';
        
        // Prepare email content
        $requestId = 'CR' . date('YmdHis') . random_int(100, 999);
        $timestamp = date('Y-m-d H:i:s');
        // Use predefined base URL to prevent Host header injection
        $pageUrl = 'https://nichehome.ch/contacts.php?lang=' . urlencode($currentLang);
        
        $vars = [
            'name' => $name,
            'email' => $email,
            'phone' => !empty($phone) ? $phone : 'Not provided',
            'support_subject' => 'Contact Form Submission',
            'support_message' => nl2br(htmlspecialchars($message)),
            'date' => $timestamp,
            'request_id' => $requestId,
            'page_url' => $pageUrl,
            'language' => $currentLang
        ];
        
        $rendered = renderEmailTemplate('support_admin', $vars);
        
        // Override subject to match requirements
        $subject = "New support request — {$name} — NicheHome.ch";
        
        // Send email with customer email as Reply-To
        $result = sendEmailViaSMTP(
            $supportEmail,
            $subject,
            $rendered['html'],
            $rendered['text'],
            $email, // Reply-To: customer email
            'contact_form',
            ['customer_email' => $email, 'request_id' => $requestId] // Context for logging
        );
        
        if ($result['success']) {
            $success = true;
            
            // Optional: Send auto-reply to customer (don't block on failure)
            if (!empty($email)) {
                $renderedCustomer = renderEmailTemplate('support_customer', $vars);
                $replyTo = $settings['routing']['reply_to_email'] ?? null;
                
                $resultCustomer = sendEmailViaSMTP(
                    $email,
                    $renderedCustomer['subject'],
                    $renderedCustomer['html'],
                    $renderedCustomer['text'],
                    $replyTo,
                    'contact_form_autoreply',
                    ['customer_email' => $email, 'request_id' => $requestId]
                );
                
                if (!$resultCustomer['success']) {
                    error_log("Failed to send contact form auto-reply: " . $resultCustomer['error']);
                }
            }
            
            // Clear form fields on success
            $name = '';
            $email = '';
            $phone = '';
            $message = '';
        } else {
            // Email send failed - show error to user
            $fallbackEmail = $CONFIG['contact_email'] ?? 'info@nichehome.ch';
            $fallbackPhone = $CONFIG['contact_phone'] ?? '+41 79 725 6259';
            $error = I18N::t('page.contacts.emailFailed', 
                "Your message could not be delivered to support right now. Please contact us at {$fallbackEmail} or WhatsApp: {$fallbackPhone}.");
            error_log("Contact form email failed: " . $result['error'] . " | Customer: $email | Name: $name");
        }
    }
}
?>

<section class="page-hero">
    <div class="page-hero__content">
        <p class="section-heading__label"><?php echo I18N::t('nav.contacts', 'Contact'); ?></p>
        <h1 class="page-hero__title"><?php echo I18N::t('page.contacts.title', 'Contact Us'); ?></h1>
        <p class="page-hero__subtitle"><?php echo I18N::t('page.contacts.subtitle', 'Get in touch with our team'); ?></p>
    </div>
</section>

<section class="contact-section">
    <div class="contact-grid">
        <div class="contact-info">
            <div class="contact-info__item">
                <div class="contact-info__icon">📧</div>
                <div>
                    <strong><?php echo I18N::t('page.contacts.emailLabel', 'Email'); ?></strong><br>
                    <a href="mailto:<?php echo htmlspecialchars($CONFIG['contact_email'] ?? 'info@nichehome.ch'); ?>">
                        <?php echo htmlspecialchars($CONFIG['contact_email'] ?? 'info@nichehome.ch'); ?>
                    </a>
                </div>
            </div>
            
            <div class="contact-info__item">
                <div class="contact-info__icon">📱</div>
                <div>
                    <strong><?php echo I18N::t('page.contacts.whatsappLabel', 'WhatsApp'); ?></strong><br>
                    <a href="<?php echo htmlspecialchars($CONFIG['whatsapp_link'] ?? 'https://wa.me/41797256259'); ?>" target="_blank" rel="noopener">
                        <?php echo htmlspecialchars($CONFIG['contact_phone'] ?? '+41 79 725 6259'); ?> (WhatsApp)
                    </a>
                </div>
            </div>
            
            <?php if (!empty($activeBranches)): ?>
                <div class="contact-info__item contact-info__item--branches">
                    <div>
                        <strong><?php echo I18N::t('page.contacts.branchesTitle', 'Our Branches'); ?></strong>
                    </div>
                    <?php foreach ($activeBranches as $branchId => $branch): ?>
                        <div class="contact-info__branch">
                            <strong><?php echo htmlspecialchars($branch['name']); ?></strong><br>
                            <?php echo nl2br(htmlspecialchars($branch['address'])); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="contact-form">
            <?php if ($success): ?>
                <div class="alert alert--success">
                    <?php echo I18N::t('page.contacts.formSuccess', 'Thank you for your message. We will get back to you soon.'); ?>
                </div>
                
                <?php if ((defined('EMAIL_DEBUG') && EMAIL_DEBUG) || (isset($CONFIG['email_debug']) && $CONFIG['email_debug'])): ?>
                    <div class="alert alert--info" style="background: #e3f2fd; color: #01579b; margin-top: 1rem;">
                        <strong>Debug Info (Admin Only):</strong><br>
                        Support notification: <?php echo isset($result) && $result['success'] ? '✓ Sent' : '✗ Failed'; ?><br>
                        <?php if (isset($result) && !empty($result['error'])): ?>
                            Last error: <?php echo htmlspecialchars(substr($result['error'], 0, 200)); ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <?php if ($error): ?>
                    <div class="alert alert--error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <form method="post" action="">
                    <div class="form-group mb-3">
                        <label><?php echo I18N::t('page.contacts.formName', 'Your name'); ?></label>
                        <input type="text" name="name" value="<?php echo isset($name) ? htmlspecialchars($name) : ''; ?>" required>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label><?php echo I18N::t('page.contacts.formEmail', 'Email address'); ?></label>
                        <input type="email" name="email" value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>" required>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label><?php echo I18N::t('page.contacts.formPhone', 'Phone number (optional)'); ?></label>
                        <input type="tel" name="phone" value="<?php echo isset($phone) ? htmlspecialchars($phone) : ''; ?>">
                    </div>
                    
                    <div class="form-group mb-3">
                        <label><?php echo I18N::t('page.contacts.formMessage', 'Your message'); ?></label>
                        <textarea name="message" rows="5" required><?php echo isset($message) ? htmlspecialchars($message) : ''; ?></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn--gold">
                        <?php echo I18N::t('page.contacts.formSubmit', 'Send message'); ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>