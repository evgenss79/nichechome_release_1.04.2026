<?php
/**
 * Shared Footer for NICHEHOME.CH
 */

$currentLang = I18N::getLanguage();
$footerCategories = getFooterCategories();
?>
    </main>
    <footer class="site-footer">
        <div class="site-footer__grid">
            <div class="site-footer__column">
                <h4><?php echo I18N::t('footer.catalogTitle', 'Catalog'); ?></h4>
                <ul>
                    <li><a href="catalog.php?lang=<?php echo $currentLang; ?>"><?php echo I18N::t('nav.catalog', 'Catalog'); ?></a></li>
                    <?php foreach ($footerCategories as $slug => $category): ?>
                        <li>
                            <a href="<?php echo htmlspecialchars(getCategoryUrl($slug, $category, $currentLang)); ?>">
                                <?php echo I18N::t('category.' . $slug . '.name', ucfirst(str_replace('_', ' ', $slug))); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                    <li><a href="gift-sets.php?lang=<?php echo $currentLang; ?>"><?php echo I18N::t('nav.giftSets', 'Gift Sets'); ?></a></li>
                </ul>
            </div>
            <div class="site-footer__column">
                <h4><?php echo I18N::t('footer.infoTitle', 'Information'); ?></h4>
                <ul>
                    <li><a href="about.php?lang=<?php echo $currentLang; ?>"><?php echo I18N::t('footer.about', 'About the Brand'); ?></a></li>
                    <li><a href="aroma-marketing.php?lang=<?php echo $currentLang; ?>"><?php echo I18N::t('footer.corporate', 'Corporate Services'); ?></a></li>
                    <li><a href="support.php?lang=<?php echo $currentLang; ?>"><?php echo I18N::t('nav.support', 'Support'); ?></a></li>
                    <li><a href="contacts.php?lang=<?php echo $currentLang; ?>"><?php echo I18N::t('nav.contacts', 'Contact'); ?></a></li>
                </ul>
            </div>
            <div class="site-footer__column">
                <h4><?php echo I18N::t('footer.legalTitle', 'Legal'); ?></h4>
                <ul>
                    <li><a href="privacy-policy.php?lang=<?php echo $currentLang; ?>"><?php echo I18N::t('footer.privacy', 'Privacy Policy'); ?></a></li>
                    <li><a href="terms-and-conditions.php?lang=<?php echo $currentLang; ?>"><?php echo I18N::t('footer.terms', 'Terms & Conditions'); ?></a></li>
                </ul>
            </div>
            <div class="site-footer__column site-footer__column--newsletter">
                <h4><?php echo I18N::t('footer.newsletterTitle', 'Newsletter'); ?></h4>
                <p><?php echo I18N::t('footer.newsletterText', 'Exclusive launches, placement tips and invitations to scent sessions.'); ?></p>
                <form class="newsletter-form" id="newsletterForm">
                    <input type="email" name="newsletter" placeholder="<?php echo I18N::t('footer.newsletterPlaceholder', 'Your email'); ?>" required>
                    <button type="submit" class="btn btn--gold"><?php echo I18N::t('footer.newsletterButton', 'Subscribe'); ?></button>
                </form>
                <p class="site-footer__social"><?php echo I18N::t('footer.socialLabel', 'Follow us'); ?></p>
                <div class="footer-social">
                    <a href="#" target="_blank">Instagram</a>
                    <a href="#" target="_blank">WhatsApp</a>
                    <a href="#" target="_blank">Pinterest</a>
                </div>
            </div>
        </div>
        <div class="site-footer__bottom">
            <span><?php echo I18N::t('footer.paymentTitle', 'We accept Visa, Mastercard, TWINT'); ?></span>
            <p><?php echo I18N::t('footer.copyright', '© NicheHome.ch – Official Swiss Representative of By Velcheva'); ?></p>
        </div>
    </footer>
    <script defer src="/assets/js/app.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/app.js'); ?>"></script>
</body>
</html>
