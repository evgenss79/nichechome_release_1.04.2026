<?php
/**
 * Cart - Shopping cart page
 */

require_once __DIR__ . '/init.php';
include __DIR__ . '/includes/header.php';

$currentLang = I18N::getLanguage();
$cart = getCart();
$cartTotal = getCartTotal();
$freeShippingThreshold = $CONFIG['free_shipping_threshold'] ?? 80;
$amountToFreeShipping = max(0, $freeShippingThreshold - $cartTotal);
$shippingCost = calculateShippingForTotal($cartTotal);
?>

<section class="cart-section">
    <div class="container">
        <h1 class="text-center mb-4"><?php echo I18N::t('page.cart.title', 'Shopping Cart'); ?></h1>
        
        <?php if (empty($cart)): ?>
            <div class="cart-empty">
                <p class="cart-empty__text">
                    <?php echo I18N::t('page.cart.empty', 'Your cart is empty'); ?>
                </p>
                <a href="catalog.php?lang=<?php echo htmlspecialchars($currentLang); ?>" class="btn btn--gold cart-empty__button">
                    <?php echo I18N::t('common.continueShopping', 'Continue shopping'); ?>
                </a>
            </div>
        <?php else: ?>
            <table class="cart-table">
                <thead>
                    <tr>
                        <th><?php echo I18N::t('page.cart.item', 'Item'); ?></th>
                        <th><?php echo I18N::t('page.cart.price', 'Price'); ?></th>
                        <th><?php echo I18N::t('page.cart.quantity', 'Qty'); ?></th>
                        <th><?php echo I18N::t('page.cart.total', 'Total'); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cart as $item): ?>
                        <?php
                        $itemTotal = ($item['price'] ?? 0) * ($item['quantity'] ?? 1);
                        $fragranceName = '';
                        if (!empty($item['fragrance']) && $item['fragrance'] !== 'none') {
                            $fragranceName = I18N::t('fragrance.' . $item['fragrance'] . '.name', ucfirst(str_replace('_', ' ', $item['fragrance'])));
                        }
                        // For gift sets, stock checking is more complex (multiple SKUs), so use high max
                        // Stock will be validated at checkout
                        $isGiftSet = ($item['category'] ?? '') === 'gift_sets' || ($item['isGiftSet'] ?? false);
                        $maxQty = $isGiftSet ? 99 : getStockQuantity($item['sku'] ?? '');
                        ?>
                        <tr>
                            <td data-label="<?php echo I18N::t('page.cart.item', 'Item'); ?>">
                                <div class="cart-item__name"><?php echo htmlspecialchars($item['name'] ?? 'Product'); ?></div>
                                <?php if ($isGiftSet): ?>
                                    <?php 
                                    // Display gift set contents breakdown
                                    // Try stored breakdown first, then compute from items
                                    $contentsText = $item['breakdown'] ?? $item['meta']['breakdown'] ?? '';
                                    if (empty($contentsText)) {
                                        $giftSetItems = $item['meta']['gift_set_items'] ?? $item['items'] ?? [];
                                        if (!empty($giftSetItems)) {
                                            $contentsText = formatGiftSetContents($giftSetItems, $currentLang);
                                        }
                                    }
                                    if ($contentsText) {
                                        echo '<div class="cart-item__meta giftset-contents">' . htmlspecialchars($contentsText) . '</div>';
                                    }
                                    ?>
                                <?php else: ?>
                                    <div class="cart-item__details">
                                        <?php if (!empty($item['volume']) && $item['volume'] !== 'standard'): ?>
                                            <?php echo htmlspecialchars($item['volume']); ?>
                                        <?php endif; ?>
                                        <?php if ($fragranceName): ?>
                                            • <?php echo htmlspecialchars($fragranceName); ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td data-label="<?php echo I18N::t('page.cart.price', 'Price'); ?>">CHF <?php echo number_format($item['price'] ?? 0, 2); ?></td>
                            <td data-label="<?php echo I18N::t('page.cart.quantity', 'Qty'); ?>">
                                <input type="number" 
                                       class="cart-item__quantity" 
                                       value="<?php echo (int)($item['quantity'] ?? 1); ?>" 
                                       min="1" 
                                       max="<?php echo (int)$maxQty; ?>"
                                       onchange="updateCartQuantity('<?php echo htmlspecialchars($item['sku']); ?>', this.value); window.location.reload();">
                            </td>
                            <td data-label="<?php echo I18N::t('page.cart.total', 'Total'); ?>">CHF <?php echo number_format($itemTotal, 2); ?></td>
                            <td data-label="">
                                <button class="cart-item__remove" onclick="removeFromCart('<?php echo htmlspecialchars($item['sku']); ?>')">
                                    ✕ <?php echo I18N::t('common.remove', 'Remove'); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="cart-summary">
                <div class="summary-row">
                    <span class="summary-label"><?php echo I18N::t('common.subtotal', 'Subtotal'); ?></span>
                    <span class="summary-value">CHF <?php echo number_format($cartTotal, 2); ?></span>
                </div>
                <div class="summary-row">
                    <span class="summary-label"><?php echo I18N::t('common.shipping', 'Shipping'); ?></span>
                    <span class="summary-value">
                        <?php if ($shippingCost > 0): ?>
                            CHF <?php echo number_format($shippingCost, 2); ?>
                        <?php else: ?>
                            <?php echo I18N::t('common.freeShipping', 'Free'); ?>
                        <?php endif; ?>
                    </span>
                </div>
                <?php if ($shippingCost > 0 && $amountToFreeShipping > 0): ?>
                    <div class="summary-note">
                        <small>(<?php echo str_replace('{amount}', number_format($amountToFreeShipping, 2), I18N::t('common.increaseOrderForFreeShipping', 'increase order for CHF {amount} for a FREE shipping')); ?>)</small>
                    </div>
                <?php endif; ?>
                <div class="summary-row summary-row--total">
                    <span class="summary-label"><?php echo I18N::t('common.total', 'Total'); ?></span>
                    <span class="summary-value">CHF <?php echo number_format($cartTotal + $shippingCost, 2); ?></span>
                </div>
                
                <div style="margin-top: 1.5rem;">
                    <a href="checkout.php?lang=<?php echo $currentLang; ?>" class="btn btn--gold" style="width: 100%;">
                        <?php echo I18N::t('common.proceedToCheckout', 'Proceed to checkout'); ?>
                    </a>
                </div>
                
                <div style="margin-top: 1rem; text-align: center;">
                    <a href="catalog.php?lang=<?php echo $currentLang; ?>" class="btn btn--text">
                        <?php echo I18N::t('common.continueShopping', 'Continue shopping'); ?>
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
