<?php
/**
 * Support - Contact form page
 */

require_once __DIR__ . '/init.php';

$currentLang = I18N::getLanguage();
$success = false;
$errors = [];
$fieldErrors = [];

// Form values
$firstName = '';
$lastName = '';
$email = '';
$phone = '';
$subject = '';
$message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form values
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    // Validate required fields
    if (empty($firstName)) {
        $errors[] = I18N::t('page.support.firstNameRequired', 'First name is required');
        $fieldErrors['first_name'] = true;
    }
    
    if (empty($lastName)) {
        $errors[] = I18N::t('page.support.lastNameRequired', 'Last name is required');
        $fieldErrors['last_name'] = true;
    }
    
    if (empty($email)) {
        $errors[] = I18N::t('page.support.emailRequired', 'Email is required');
        $fieldErrors['email'] = true;
    } elseif (!isValidEmail($email)) {
        $errors[] = I18N::t('page.support.emailInvalid', 'Please enter a valid email address');
        $fieldErrors['email'] = true;
    }
    
    if (!empty($phone) && !preg_match('/^[\d\s\+\-\(\)]+$/', $phone)) {
        $errors[] = I18N::t('page.support.phoneInvalid', 'Please enter a valid phone number');
        $fieldErrors['phone'] = true;
    }
    
    if (empty($subject)) {
        $errors[] = I18N::t('page.support.subjectRequired', 'Subject is required');
        $fieldErrors['subject'] = true;
    }
    
    if (empty($message)) {
        $errors[] = I18N::t('page.support.messageRequired', 'Message is required');
        $fieldErrors['message'] = true;
    }
    
    // Handle file upload if provided
    $uploadedFile = null;
    if (!empty($_FILES['attachment']['name'])) {
        $file = $_FILES['attachment'];
        $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = I18N::t('page.support.fileUploadError', 'Error uploading file');
        } elseif ($file['size'] > $maxSize) {
            $errors[] = I18N::t('page.support.fileTooLarge', 'File size must not exceed 5 MB');
        } elseif (!in_array($file['type'], $allowedTypes) || !in_array(mime_content_type($file['tmp_name']), $allowedTypes)) {
            $errors[] = I18N::t('page.support.fileTypeInvalid', 'File must be PDF, JPEG, or PNG');
        } else {
            $uploadedFile = $file;
        }
    }
    
    // If no errors, save the support request
    if (empty($errors)) {
        $supportRequests = loadJSON('support_requests.json');
        if (!is_array($supportRequests)) {
            $supportRequests = [];
        }
        
        $requestId = 'SR' . date('YmdHis') . random_int(100, 999);
        
        // Use predefined base URL to prevent Host header injection
        $pageUrl = 'https://nichehome.ch/support.php?lang=' . urlencode($currentLang);
        
        $supportRequest = [
            'id' => $requestId,
            'date' => date('Y-m-d H:i:s'),
            'first_name' => sanitize($firstName),
            'last_name' => sanitize($lastName),
            'email' => sanitize($email),
            'phone' => sanitize($phone),
            'subject' => sanitize($subject),
            'message' => sanitize($message),
            'language' => $currentLang,
            'page_url' => $pageUrl,
            'status' => 'new'
        ];
        
        // Handle file attachment
        if ($uploadedFile) {
            $uploadDir = __DIR__ . '/data/uploads/support/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $fileExtension = pathinfo($uploadedFile['name'], PATHINFO_EXTENSION);
            $fileName = $requestId . '_' . time() . '.' . $fileExtension;
            $targetPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($uploadedFile['tmp_name'], $targetPath)) {
                $supportRequest['attachment'] = 'data/uploads/support/' . $fileName;
            }
        }
        
        $supportRequests[] = $supportRequest;
        
        if (saveJSON('support_requests.json', $supportRequests)) {
            // Send notification emails - MUST succeed for form to show success
            $emailSent = false;
            $emailError = '';
            try {
                $emailSent = sendNewSupportRequestNotification($supportRequest);
                if (!$emailSent) {
                    $emailError = 'Email notification could not be delivered';
                }
            } catch (Exception $e) {
                $emailError = $e->getMessage();
                error_log("Failed to send support notification email: " . $e->getMessage());
            }
            
            if ($emailSent) {
                $success = true;
                // Clear form
                $firstName = '';
                $lastName = '';
                $email = '';
                $phone = '';
                $subject = '';
                $message = '';
            } else {
                // Email send failed - show error to user
                $fallbackEmail = $CONFIG['contact_email'] ?? 'info@nichehome.ch';
                $fallbackPhone = $CONFIG['contact_phone'] ?? '+41 79 725 6259';
                $errors[] = "Your request was saved but the email notification could not be delivered to support right now. Please contact us at {$fallbackEmail} or WhatsApp: {$fallbackPhone}.";
                error_log("Support form email failed: $emailError | Customer: $email | Name: $firstName $lastName");
            }
        } else {
            $errors[] = 'Could not save your request. Please try again.';
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<section class="page-hero">
    <div class="page-hero__content">
        <p class="section-heading__label"><?php echo I18N::t('nav.support', 'Support'); ?></p>
        <h1 class="page-hero__title"><?php echo I18N::t('page.support.title', 'Support'); ?></h1>
        <p class="page-hero__subtitle"><?php echo I18N::t('page.support.subtitle', 'Get in touch with us'); ?></p>
    </div>
</section>

<section class="catalog-section">
    <div class="container" style="max-width: 800px;">
        <?php if ($success): ?>
            <div class="alert alert--success" style="background: #d4edda; color: #155724; padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem;">
                <h3 style="margin-top: 0;"><?php echo I18N::t('page.support.successTitle', 'Thank you!'); ?></h3>
                <p style="margin-bottom: 0;">
                    <?php echo I18N::t('page.support.successMessage', 'Your request has been received. We will contact you as soon as possible.'); ?>
                </p>
            </div>
            
            <?php if ((defined('EMAIL_DEBUG') && EMAIL_DEBUG) || (isset($CONFIG['email_debug']) && $CONFIG['email_debug'])): ?>
                <div class="alert alert--info" style="background: #e3f2fd; color: #01579b; padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem;">
                    <strong>Debug Info (Admin Only):</strong><br>
                    Support notification: <?php echo isset($emailSent) && $emailSent ? '✓ Sent' : '✗ Failed'; ?><br>
                    <?php if (isset($emailError) && !empty($emailError)): ?>
                        Last error: <?php echo htmlspecialchars(substr($emailError, 0, 200)); ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <div class="text-center">
                <a href="catalog.php?lang=<?php echo $currentLang; ?>" class="btn btn--gold">
                    <?php echo I18N::t('common.continueShopping', 'Continue shopping'); ?>
                </a>
            </div>
        <?php else: ?>
            <?php if (!empty($errors)): ?>
                <div class="alert alert--error" style="background: #f8d7da; color: #721c24; padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem;">
                    <ul style="list-style: disc; padding-left: 1.5rem; margin: 0;">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <div class="product-card">
                <form method="post" action="" enctype="multipart/form-data">
                    <div class="form-row">
                        <div class="form-group">
                            <label><?php echo I18N::t('page.support.firstName', 'First name'); ?> *</label>
                            <input type="text" 
                                   name="first_name" 
                                   required 
                                   class="<?php echo isset($fieldErrors['first_name']) ? 'form-control--error' : ''; ?>"
                                   value="<?php echo htmlspecialchars($firstName); ?>">
                        </div>
                        <div class="form-group">
                            <label><?php echo I18N::t('page.support.lastName', 'Last name'); ?> *</label>
                            <input type="text" 
                                   name="last_name" 
                                   required 
                                   class="<?php echo isset($fieldErrors['last_name']) ? 'form-control--error' : ''; ?>"
                                   value="<?php echo htmlspecialchars($lastName); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label><?php echo I18N::t('page.support.email', 'Email'); ?> *</label>
                            <input type="email" 
                                   name="email" 
                                   required 
                                   class="<?php echo isset($fieldErrors['email']) ? 'form-control--error' : ''; ?>"
                                   value="<?php echo htmlspecialchars($email); ?>">
                        </div>
                        <div class="form-group">
                            <label><?php echo I18N::t('page.support.phone', 'Phone number'); ?></label>
                            <input type="tel" 
                                   name="phone" 
                                   class="<?php echo isset($fieldErrors['phone']) ? 'form-control--error' : ''; ?>"
                                   value="<?php echo htmlspecialchars($phone); ?>"
                                   placeholder="<?php echo I18N::t('page.support.phoneOptional', 'Optional'); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label><?php echo I18N::t('page.support.subject', 'Subject'); ?> *</label>
                        <input type="text" 
                               name="subject" 
                               required 
                               class="<?php echo isset($fieldErrors['subject']) ? 'form-control--error' : ''; ?>"
                               value="<?php echo htmlspecialchars($subject); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label><?php echo I18N::t('page.support.message', 'Message'); ?> *</label>
                        <textarea name="message" 
                                  rows="6" 
                                  required 
                                  class="<?php echo isset($fieldErrors['message']) ? 'form-control--error' : ''; ?>"><?php echo htmlspecialchars($message); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label><?php echo I18N::t('page.support.attachment', 'Attachment'); ?></label>
                        <input type="file" 
                               name="attachment" 
                               accept=".pdf,.jpg,.jpeg,.png">
                        <p style="font-size: 0.85rem; color: #666; margin-top: 0.5rem;">
                            <?php echo I18N::t('page.support.attachmentNote', 'Maximum file size: 5 MB. Allowed types: PDF, JPEG, PNG'); ?>
                        </p>
                    </div>
                    
                    <button type="submit" class="btn btn--gold">
                        <?php echo I18N::t('page.support.submit', 'Send Request'); ?>
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
