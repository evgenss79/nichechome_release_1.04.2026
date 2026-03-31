<?php
/**
 * Support - FAQ and support page
 */

require_once __DIR__ . '/init.php';
include __DIR__ . '/includes/header.php';

$currentLang = I18N::getLanguage();
?>

<section class="page-hero">
    <div class="page-hero__content">
        <p class="section-heading__label"><?php echo I18N::t('nav.support', 'Support'); ?></p>
        <h1 class="page-hero__title"><?php echo I18N::t('page.support.title', 'Support & FAQ'); ?></h1>
        <p class="page-hero__subtitle"><?php echo I18N::t('page.support.subtitle', 'Frequently asked questions'); ?></p>
    </div>
</section>

<section class="catalog-section">
    <div class="container" style="max-width: 900px;">
        <!-- Shipping -->
        <div class="product-card mb-4" id="shipping">
            <h3><?php echo I18N::t('page.support.shippingTitle', 'Shipping & Delivery'); ?></h3>
            <p class="text-muted">
                <?php echo I18N::t('page.support.shippingContent', 'We offer free shipping on all orders over CHF 80. Standard delivery takes 1-3 business days within Switzerland.'); ?>
            </p>
        </div>
        
        <!-- Returns -->
        <div class="product-card mb-4" id="returns">
            <h3><?php echo I18N::t('page.support.returnsTitle', 'Returns & Refunds'); ?></h3>
            <p class="text-muted">
                <?php echo I18N::t('page.support.returnsContent', 'We accept returns within 14 days of delivery. Items must be unused and in original packaging.'); ?>
            </p>
        </div>
        
        <!-- Payment -->
        <div class="product-card mb-4" id="payment">
            <h3><?php echo I18N::t('page.support.paymentTitle', 'Payment Methods'); ?></h3>
            <p class="text-muted">
                <?php echo I18N::t('page.support.paymentContent', 'We currently accept TWINT for payments. Credit card and PayPal coming soon.'); ?>
            </p>
        </div>
        
        <!-- Privacy -->
        <div class="product-card mb-4" id="privacy">
            <h3><?php echo I18N::t('footer.privacy', 'Privacy Policy'); ?></h3>
            <p class="text-muted">
                <?php echo I18N::t('support.privacyContent', 'We are committed to protecting your privacy. Your personal information is used only to process your order and improve your shopping experience. We do not share your data with third parties except as necessary to fulfill your order.'); ?>
            </p>
        </div>
        
        <!-- Terms -->
        <div class="product-card mb-4" id="terms">
            <h3><?php echo I18N::t('footer.terms', 'Terms & Conditions'); ?></h3>
            <p class="text-muted">
                <?php echo I18N::t('support.termsContent', 'By placing an order on NicheHome.ch, you agree to our terms and conditions. All products are subject to availability. Prices are in Swiss Francs (CHF) and include VAT.'); ?>
            </p>
        </div>
        
        <div class="text-center mt-4">
            <p class="text-muted mb-3"><?php echo I18N::t('support.moreQuestions', 'Have more questions?'); ?></p>
            <a href="contacts.php?lang=<?php echo $currentLang; ?>" class="btn btn--gold">
                <?php echo I18N::t('nav.contacts', 'Contact Us'); ?>
            </a>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>