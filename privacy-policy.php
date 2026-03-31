<?php
/**
 * Privacy Policy Page
 * IMPORTANT: This page should NOT be indexed by search engines
 */

require_once __DIR__ . '/init.php';

// Set page identifier for noindex meta tag
$pageId = 'privacy';

include __DIR__ . '/includes/header.php';

$currentLang = I18N::getLanguage();
?>

<section class="page-hero">
    <div class="page-hero__content">
        <p class="section-heading__label"><?php echo I18N::t('nav.about', 'Legal'); ?></p>
        <h1 class="page-hero__title"><?php echo I18N::t('page.privacy.title', 'Privacy Policy'); ?></h1>
        <p class="page-hero__subtitle"><?php echo I18N::t('page.privacy.subtitle', 'Data Protection and Privacy Information'); ?></p>
    </div>
</section>

<section class="catalog-section">
    <div class="container" style="max-width: 900px;">
        <div class="product-card mb-4">
            <h3><?php echo I18N::t('page.privacy.section_1_title', '1. Controller and Contact'); ?></h3>
            <p class="text-muted" style="white-space: pre-line;">
                <?php echo I18N::t('page.privacy.section_1_body', ''); ?>
            </p>
        </div>
        
        <div class="product-card mb-4">
            <h3><?php echo I18N::t('page.privacy.section_2_title', '2. Scope and Purpose'); ?></h3>
            <p class="text-muted" style="white-space: pre-line;">
                <?php echo I18N::t('page.privacy.section_2_body', ''); ?>
            </p>
        </div>
        
        <div class="product-card mb-4">
            <h3><?php echo I18N::t('page.privacy.section_3_title', '3. Data Processing'); ?></h3>
            <p class="text-muted" style="white-space: pre-line;">
                <?php echo I18N::t('page.privacy.section_3_body', ''); ?>
            </p>
        </div>
        
        <div class="product-card mb-4">
            <h3><?php echo I18N::t('page.privacy.section_4_title', '4. Legal Basis'); ?></h3>
            <p class="text-muted" style="white-space: pre-line;">
                <?php echo I18N::t('page.privacy.section_4_body', ''); ?>
            </p>
        </div>
        
        <div class="product-card mb-4">
            <h3><?php echo I18N::t('page.privacy.section_5_title', '5. Data Retention'); ?></h3>
            <p class="text-muted" style="white-space: pre-line;">
                <?php echo I18N::t('page.privacy.section_5_body', ''); ?>
            </p>
        </div>
        
        <div class="product-card mb-4">
            <h3><?php echo I18N::t('page.privacy.section_6_title', '6. Your Rights'); ?></h3>
            <p class="text-muted" style="white-space: pre-line;">
                <?php echo I18N::t('page.privacy.section_6_body', ''); ?>
            </p>
        </div>
        
        <div class="product-card mb-4">
            <h3><?php echo I18N::t('page.privacy.section_7_title', '7. Data Security'); ?></h3>
            <p class="text-muted" style="white-space: pre-line;">
                <?php echo I18N::t('page.privacy.section_7_body', ''); ?>
            </p>
        </div>
        
        <div class="product-card mb-4">
            <h3><?php echo I18N::t('page.privacy.section_8_title', '8. Third-Party Services'); ?></h3>
            <p class="text-muted" style="white-space: pre-line;">
                <?php echo I18N::t('page.privacy.section_8_body', ''); ?>
            </p>
        </div>
        
        <div class="product-card mb-4">
            <h3><?php echo I18N::t('page.privacy.section_9_title', '9. Cookies and Tracking'); ?></h3>
            <p class="text-muted" style="white-space: pre-line;">
                <?php echo I18N::t('page.privacy.section_9_body', ''); ?>
            </p>
        </div>
        
        <div class="product-card mb-4">
            <h3><?php echo I18N::t('page.privacy.section_10_title', '10. Changes to Policy'); ?></h3>
            <p class="text-muted" style="white-space: pre-line;">
                <?php echo I18N::t('page.privacy.section_10_body', ''); ?>
            </p>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
