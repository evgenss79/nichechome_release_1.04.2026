<?php
/**
 * Gift Sets - Gift set constructor page
 */

require_once __DIR__ . '/init.php';
include __DIR__ . '/includes/header.php';

$categories = loadJSON('categories.json');
$currentLang = I18N::getLanguage();

// Filter out non-product categories
$productCategories = array_filter($categories, function($cat, $slug) {
    return !in_array($slug, ['gift_sets', 'aroma_marketing']);
}, ARRAY_FILTER_USE_BOTH);
?>

<section class="page-hero">
    <div class="page-hero__content">
        <p class="section-heading__label"><?php echo I18N::t('nav.giftSets', 'Gift Sets'); ?></p>
        <h1 class="page-hero__title"><?php echo I18N::t('page.giftSets.title', 'Create Your Gift Set'); ?></h1>
        <p class="page-hero__subtitle"><?php echo I18N::t('page.giftSets.subtitle', 'Combine your favorite products with a 5% discount'); ?></p>
    </div>
</section>

<section class="gift-sets-section">
    <form data-gift-set-form>
        <div class="gift-slots">
            <?php for ($i = 1; $i <= 3; $i++): ?>
                <div class="gift-slot" data-gift-slot data-giftset-slot="<?php echo $i; ?>">
                    <h3 class="gift-slot__title"><?php echo I18N::t('page.giftSets.slot', 'Slot'); ?> <?php echo $i; ?></h3>
                    
                    <div class="gift-slot__selects">
                        <div class="form-group">
                            <label><?php echo I18N::t('page.giftSets.selectCategory', 'Select category'); ?></label>
                            <select data-gift-category data-giftset-category>
                                <option value=""><?php echo I18N::t('page.giftSets.selectCategory', 'Select category'); ?></option>
                                <?php foreach ($productCategories as $slug => $cat): ?>
                                    <option value="<?php echo htmlspecialchars($slug); ?>">
                                        <?php echo htmlspecialchars(I18N::t('category.' . $slug . '.name', ucfirst(str_replace('_', ' ', $slug)))); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group" data-product-group data-giftset-product-wrap style="display: none;">
                            <label><?php echo I18N::t('common.product', 'Product'); ?></label>
                            <select data-gift-product data-giftset-product>
                                <option value=""><?php echo I18N::t('common.selectProduct', 'Select product'); ?></option>
                            </select>
                        </div>
                        
                        <div class="form-group" data-variant-group data-giftset-size-wrap style="display: none;">
                            <label><?php echo I18N::t('common.variant', 'Size / Pack'); ?></label>
                            <select data-gift-variant data-giftset-size>
                                <option value=""><?php echo I18N::t('common.selectVariant', 'Select size or pack'); ?></option>
                            </select>
                        </div>
                        
                        <div class="form-group" data-fragrance-group data-giftset-fragrance-wrap style="display: none;">
                            <label><?php echo I18N::t('common.fragrance', 'Fragrance'); ?></label>
                            <select data-gift-fragrance data-giftset-fragrance>
                                <option value=""><?php echo I18N::t('common.selectFragrance', 'Select fragrance'); ?></option>
                            </select>
                        </div>
                        
                        <div class="gift-slot__error" data-slot-error style="display: none; color: #d32f2f; font-size: 0.875rem; margin-top: 0.5rem;"></div>
                    </div>
                </div>
            <?php endfor; ?>
        </div>
        
        <div class="gift-total">
            <h3><?php echo I18N::t('page.giftSets.totalPrice', 'Total price'); ?></h3>
            <p class="gift-total__price" data-gift-total><?php echo I18N::t('page.giftSets.selectToSeePrice', 'Select options to see price'); ?></p>
            <p class="gift-total__discount" data-gift-discount style="display: none;"><?php echo I18N::t('page.giftSets.discount', '5% gift set discount'); ?>: -CHF 0.00</p>
            
            <p class="gift-total__message" data-gift-message style="display: none; color: #d32f2f; font-size: 0.875rem; margin: 0.5rem 0;"></p>
            
            <button type="button" class="btn btn--gold" data-add-gift-set disabled>
                <?php echo I18N::t('page.giftSets.addToCart', 'Add gift set to cart'); ?>
            </button>
        </div>
    </form>
</section>

<script>
// Pass I18N labels to JavaScript
window.I18N_LABELS = {
    giftset_added: <?php echo json_encode(I18N::t('page.giftSets.addedAlert', 'Gift set added to cart!')); ?>,
    selectProduct: <?php echo json_encode(I18N::t('common.selectProduct', 'Select product')); ?>,
    selectVariant: <?php echo json_encode(I18N::t('common.selectVariant', 'Select size or pack')); ?>,
    selectFragrance: <?php echo json_encode(I18N::t('common.selectFragrance', 'Select fragrance')); ?>,
    selectToSeePrice: <?php echo json_encode(I18N::t('page.giftSets.selectToSeePrice', 'Select options to see price')); ?>,
    errorProductMissing: <?php echo json_encode(I18N::t('page.giftSets.errorProductMissing', 'Please choose a product for this slot')); ?>,
    errorVariantMissing: <?php echo json_encode(I18N::t('page.giftSets.errorVariantMissing', 'Please choose a size or pack option')); ?>,
    errorFragranceMissing: <?php echo json_encode(I18N::t('page.giftSets.errorFragranceMissing', 'Please choose a fragrance')); ?>,
    errorAddingGiftset: <?php echo json_encode(I18N::t('page.giftSets.errorAddingGiftset', 'Error adding gift set to cart')); ?>,
    errorSelectAtLeastOne: <?php echo json_encode(I18N::t('page.giftSets.errorSelectAtLeastOne', 'Please select at least one product for your gift set.')); ?>,
    errorIncomplete: <?php echo json_encode(I18N::t('page.giftSets.errorIncomplete', 'Complete all 3 slots to add Gift Set (5% discount applies only to 3 items).')); ?>
};
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
