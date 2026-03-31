<?php
/**
 * Terms and Conditions Page
 */

require_once __DIR__ . '/init.php';
include __DIR__ . '/includes/header.php';

$currentLang = I18N::getLanguage();
?>

<section class="page-hero">
    <div class="page-hero__content">
        <p class="section-heading__label"><?php echo I18N::t('nav.about', 'Legal'); ?></p>
        <h1 class="page-hero__title"><?php echo I18N::t('page.terms.title', 'Terms and Conditions'); ?></h1>
        <p class="page-hero__subtitle"><?php echo I18N::t('page.terms.subtitle', 'Complete Terms and Conditions for the NicheHome.ch online store'); ?></p>
    </div>
</section>

<section class="catalog-section">
    <div class="container" style="max-width: 900px;">
        <div class="product-card mb-4">
            <h3><?php echo I18N::t('page.terms.section_1_title', '1. Scope of Application'); ?></h3>
            <p class="text-muted" style="white-space: pre-line;">
                <?php echo I18N::t('page.terms.section_1_body', ''); ?>
            </p>
        </div>
        
        <div class="product-card mb-4">
            <h3><?php echo I18N::t('page.terms.section_2_title', '2. Products and Availability'); ?></h3>
            <p class="text-muted" style="white-space: pre-line;">
                <?php echo I18N::t('page.terms.section_2_body', ''); ?>
            </p>
        </div>
        
        <div class="product-card mb-4">
            <h3><?php echo I18N::t('page.terms.section_3_title', '3. Prices and Taxes'); ?></h3>
            <p class="text-muted" style="white-space: pre-line;">
                <?php echo I18N::t('page.terms.section_3_body', ''); ?>
            </p>
        </div>
        
        <div class="product-card mb-4">
            <h3><?php echo I18N::t('page.terms.section_4_title', '4. Delivery and Pickup'); ?></h3>
            <p class="text-muted" style="white-space: pre-line;">
                <?php echo I18N::t('page.terms.section_4_body', ''); ?>
            </p>
        </div>
        
        <div class="product-card mb-4">
            <h3><?php echo I18N::t('page.terms.section_5_title', '5. Transfer of Risk'); ?></h3>
            <p class="text-muted" style="white-space: pre-line;">
                <?php echo I18N::t('page.terms.section_5_body', ''); ?>
            </p>
        </div>
        
        <div class="product-card mb-4">
            <h3><?php echo I18N::t('page.terms.section_6_title', '6. Order Process'); ?></h3>
            <p class="text-muted" style="white-space: pre-line;">
                <?php echo I18N::t('page.terms.section_6_body', ''); ?>
            </p>
        </div>
        
        <div class="product-card mb-4">
            <h3><?php echo I18N::t('page.terms.section_7_title', '7. Payment Methods'); ?></h3>
            <p class="text-muted" style="white-space: pre-line;">
                <?php echo I18N::t('page.terms.section_7_body', ''); ?>
            </p>
        </div>
        
        <div class="product-card mb-4">
            <h3><?php echo I18N::t('page.terms.section_8_title', '8. Returns and Exchanges'); ?></h3>
            <p class="text-muted" style="white-space: pre-line;">
                <?php echo I18N::t('page.terms.section_8_body', ''); ?>
            </p>
        </div>
        
        <div class="product-card mb-4">
            <h3><?php echo I18N::t('page.terms.section_9_title', '9. Warranty'); ?></h3>
            <p class="text-muted" style="white-space: pre-line;">
                <?php echo I18N::t('page.terms.section_9_body', ''); ?>
            </p>
        </div>
        
        <div class="product-card mb-4">
            <h3><?php echo I18N::t('page.terms.section_10_title', '10. Limitation of Liability'); ?></h3>
            <p class="text-muted" style="white-space: pre-line;">
                <?php echo I18N::t('page.terms.section_10_body', ''); ?>
            </p>
        </div>
        
        <div class="product-card mb-4">
            <h3><?php echo I18N::t('page.terms.section_11_title', '11. Privacy'); ?></h3>
            <p class="text-muted" style="white-space: pre-line;">
                <?php echo I18N::t('page.terms.section_11_body', ''); ?>
            </p>
        </div>
        
        <div class="product-card mb-4">
            <h3><?php echo I18N::t('page.terms.section_12_title', '12. Intellectual Property'); ?></h3>
            <p class="text-muted" style="white-space: pre-line;">
                <?php echo I18N::t('page.terms.section_12_body', ''); ?>
            </p>
        </div>
        
        <div class="product-card mb-4">
            <h3><?php echo I18N::t('page.terms.section_13_title', '13. Applicable Law'); ?></h3>
            <p class="text-muted" style="white-space: pre-line;">
                <?php echo I18N::t('page.terms.section_13_body', ''); ?>
            </p>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
