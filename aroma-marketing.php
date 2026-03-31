<?php
/**
 * Aroma Marketing - B2B services page
 */

require_once __DIR__ . '/init.php';
include __DIR__ . '/includes/header.php';

$currentLang = I18N::getLanguage();
$success = false;
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company = sanitize($_POST['company'] ?? '');
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $message = sanitize($_POST['message'] ?? '');
    
    if (empty($company) || empty($name) || empty($email) || empty($message)) {
        $error = 'All fields are required.';
    } elseif (!isValidEmail($email)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Save request
        $requests = loadJSON('aroma_marketing_requests.json');
        if (!is_array($requests)) {
            $requests = [];
        }
        
        $requests[] = [
            'id' => 'AMR-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 4)),
            'company' => $company,
            'name' => $name,
            'email' => $email,
            'message' => $message,
            'date' => date('Y-m-d H:i:s'),
            'lang' => $currentLang
        ];
        
        if (saveJSON('aroma_marketing_requests.json', $requests)) {
            $success = true;
        } else {
            $error = 'Could not save your request. Please try again.';
        }
    }
}
?>

<section class="page-hero">
    <div class="page-hero__content">
        <p class="section-heading__label"><?php echo I18N::t('nav.aromaMarketing', 'Aroma Marketing'); ?></p>
        <h1 class="page-hero__title"><?php echo I18N::t('page.aromaMarketing.title', 'Aroma Marketing'); ?></h1>
        <p class="page-hero__subtitle"><?php echo I18N::t('page.aromaMarketing.subtitle', 'Professional scenting solutions for your business'); ?></p>
    </div>
</section>

<section class="contact-section">
    <div class="contact-grid">
        <div class="contact-info">
            <p style="white-space: pre-line; line-height: 1.8;">
                <?php echo nl2br(htmlspecialchars(I18N::t('page.aromaMarketing.content', 'Transform your business environment with our professional aroma marketing services. We offer customized scenting solutions for hotels, retail stores, offices, and other commercial spaces.

Our team works with you to create a unique olfactory identity that enhances customer experience and brand recognition.'))); ?>
            </p>
            
            <h3 class="mt-4"><?php echo I18N::t('page.aromaMarketing.servicesTitle', 'Our Services'); ?></h3>
            <ul style="list-style: disc; padding-left: 1.5rem; margin-top: 1rem;">
                <li><?php echo I18N::t('page.aromaMarketing.service1', 'Custom scent development'); ?></li>
                <li><?php echo I18N::t('page.aromaMarketing.service2', 'Professional installation'); ?></li>
                <li><?php echo I18N::t('page.aromaMarketing.service3', 'Regular maintenance'); ?></li>
                <li><?php echo I18N::t('page.aromaMarketing.service4', 'Training and consultation'); ?></li>
            </ul>
        </div>
        
        <div class="contact-form">
            <h3><?php echo I18N::t('page.aromaMarketing.contactTitle', 'Request Information'); ?></h3>
            
            <?php if ($success): ?>
                <div class="alert alert--success">
                    <?php echo I18N::t('page.aromaMarketing.formSuccess', 'Thank you for your inquiry. Our team will contact you within 24 hours.'); ?>
                </div>
            <?php else: ?>
                <?php if ($error): ?>
                    <div class="alert alert--error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <form method="post" action="">
                    <div class="form-group">
                        <label><?php echo I18N::t('page.aromaMarketing.formCompany', 'Company name'); ?></label>
                        <input type="text" name="company" required>
                    </div>
                    
                    <div class="form-group">
                        <label><?php echo I18N::t('page.aromaMarketing.formName', 'Contact person'); ?></label>
                        <input type="text" name="name" required>
                    </div>
                    
                    <div class="form-group">
                        <label><?php echo I18N::t('page.aromaMarketing.formEmail', 'Email address'); ?></label>
                        <input type="email" name="email" required>
                    </div>
                    
                    <div class="form-group">
                        <label><?php echo I18N::t('page.aromaMarketing.formMessage', 'Tell us about your needs'); ?></label>
                        <textarea name="message" rows="4" required></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn--gold">
                        <?php echo I18N::t('page.aromaMarketing.formSubmit', 'Send request'); ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
