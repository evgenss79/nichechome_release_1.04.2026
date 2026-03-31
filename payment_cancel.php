<?php
/**
 * Payment Cancel/Failed Page
 * Displayed when payment is cancelled or failed
 */

require_once __DIR__ . '/init.php';

$orderId = $_GET['orderId'] ?? '';
$status = $_GET['status'] ?? 'cancelled';
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
            <div style="font-size: 4rem; color: #ff9800; margin-bottom: 1rem;">⚠</div>
            
            <?php if ($status === 'failed'): ?>
                <h1><?php echo I18N::t('page.payment.failedTitle', 'Payment Failed'); ?></h1>
                <p style="font-size: 1.1rem; margin: 2rem 0; color: #666;">
                    <?php echo I18N::t('page.payment.failedMessage', 'Unfortunately, your payment could not be processed. Please try again or contact our support team.'); ?>
                </p>
            <?php else: ?>
                <h1><?php echo I18N::t('page.payment.cancelledTitle', 'Payment Cancelled'); ?></h1>
                <p style="font-size: 1.1rem; margin: 2rem 0; color: #666;">
                    <?php echo I18N::t('page.payment.cancelledMessage', 'Your payment was cancelled. Your order has not been completed.'); ?>
                </p>
            <?php endif; ?>
            
            <?php if ($order): ?>
                <div style="background: #f5f5f5; padding: 1.5rem; border-radius: 8px; margin: 2rem 0;">
                    <p style="margin: 0; color: #333;">
                        <strong><?php echo I18N::t('page.payment.orderReference', 'Order Reference:'); ?></strong> 
                        <?php echo htmlspecialchars($orderId); ?>
                    </p>
                    <p style="margin: 0.5rem 0 0 0; color: #666;">
                        <strong><?php echo I18N::t('page.checkout.total', 'Total:'); ?></strong> 
                        CHF <?php echo number_format($order['total'] ?? 0, 2); ?>
                    </p>
                </div>
                
                <div style="margin-top: 2rem;">
                    <a href="checkout.php?lang=<?php echo $currentLang; ?>" class="btn btn--gold">
                        <?php echo I18N::t('page.payment.tryAgain', 'Try Again'); ?>
                    </a>
                </div>
            <?php else: ?>
                <div style="margin-top: 2rem;">
                    <a href="cart.php?lang=<?php echo $currentLang; ?>" class="btn btn--gold">
                        <?php echo I18N::t('common.viewCart', 'View Cart'); ?>
                    </a>
                </div>
            <?php endif; ?>
            
            <div style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid #ddd;">
                <p style="color: #666; margin-bottom: 1rem;">
                    <?php echo I18N::t('page.payment.needHelp', 'Need help with your order?'); ?>
                </p>
                <a href="contacts.php?lang=<?php echo $currentLang; ?>" class="btn btn--outline">
                    <?php echo I18N::t('common.contactSupport', 'Contact Support'); ?>
                </a>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
