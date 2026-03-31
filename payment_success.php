<?php
/**
 * Payment Success Page
 * Displayed after successful payment redirect from Payrexx
 */

require_once __DIR__ . '/init.php';

$orderId = $_GET['orderId'] ?? '';
$currentLang = I18N::getLanguage();

// Load the order
$orders = loadOrders();
$order = null;

if (!empty($orderId) && is_array($orders)) {
    foreach ($orders as $id => $ord) {
        if ($id === $orderId || ($ord['id'] ?? '') === $orderId) {
            $order = $ord;
            break;
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<section class="checkout-section">
    <div class="container">
        <div class="text-center" style="max-width: 600px; margin: 0 auto; padding: 4rem 2rem;">
            <?php if ($order): ?>
                <div style="font-size: 4rem; color: var(--color-gold); margin-bottom: 1rem;">✓</div>
                <h1><?php echo I18N::t('page.payment.successTitle', 'Payment Successful!'); ?></h1>
                <p style="font-size: 1.2rem; margin: 2rem 0;">
                    <?php echo str_replace('{orderId}', htmlspecialchars($orderId), I18N::t('page.payment.successMessage', 'Thank you for your payment! Your order #{orderId} has been confirmed.')); ?>
                </p>
                
                <?php 
                $paymentStatus = $order['payment_status'] ?? 'unknown';
                if ($paymentStatus === 'paid'): 
                ?>
                    <div style="background: #f0f9f0; border: 1px solid #4caf50; padding: 1rem; border-radius: 8px; margin: 2rem 0;">
                        <p style="margin: 0; color: #2e7d32;">
                            <strong><?php echo I18N::t('page.payment.confirmed', 'Your payment has been confirmed.'); ?></strong>
                        </p>
                    </div>
                <?php else: ?>
                    <div style="background: #fff8e1; border: 1px solid #ff9800; padding: 1rem; border-radius: 8px; margin: 2rem 0;">
                        <p style="margin: 0; color: #e65100;">
                            <?php echo I18N::t('page.payment.processing', 'Your payment is being processed. You will receive a confirmation email shortly.'); ?>
                        </p>
                    </div>
                <?php endif; ?>
                
                <p style="color: #666; margin-top: 2rem;">
                    <?php echo I18N::t('page.payment.confirmationEmail', 'A confirmation email has been sent to your email address.'); ?>
                </p>
                
                <div style="margin-top: 2rem;">
                    <a href="catalog.php?lang=<?php echo $currentLang; ?>" class="btn btn--gold">
                        <?php echo I18N::t('common.continueShopping', 'Continue Shopping'); ?>
                    </a>
                    <?php if (isCustomerLoggedIn()): ?>
                        <a href="account.php?lang=<?php echo $currentLang; ?>" class="btn btn--outline" style="margin-left: 1rem;">
                            <?php echo I18N::t('common.viewOrders', 'View My Orders'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div style="font-size: 4rem; color: #999; margin-bottom: 1rem;">?</div>
                <h1><?php echo I18N::t('page.payment.orderNotFound', 'Order Not Found'); ?></h1>
                <p style="color: #666; margin: 2rem 0;">
                    <?php echo I18N::t('page.payment.orderNotFoundMessage', 'The order could not be found. Please check your email for order confirmation.'); ?>
                </p>
                <a href="catalog.php?lang=<?php echo $currentLang; ?>" class="btn btn--gold">
                    <?php echo I18N::t('common.continueShopping', 'Continue Shopping'); ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
