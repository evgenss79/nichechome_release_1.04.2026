<?php
/**
 * Shared Header for NICHEHOME.CH
 */

$currentLang = I18N::getLanguage();
$cartCount = getCartCount();
$navigationCategories = getNavigationCategories();
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php if (!empty($pageId) && $pageId === 'privacy'): ?>
    <meta name="robots" content="noindex, noarchive, noimageindex">
    <?php endif; ?>
    <title><?php echo I18N::t('site.title', 'NicheHome.ch - Premium Home Fragrances'); ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600&family=Manrope:wght@400;500;600&display=swap" rel="stylesheet">
</head>
<body>
    <header class="site-header">
        <div class="utility-bar">
            <p class="utility-bar__item"><?php echo I18N::t('common.utilityShipping', 'Free shipping from CHF 80'); ?></p>
            <p class="utility-bar__item"><?php echo I18N::t('common.utilityDelivery', 'Delivery within 1-3 business days'); ?></p>
        </div>
        <div class="site-header__main">
            <button class="site-header__burger" aria-label="<?php echo I18N::t('common.menuToggle', 'Toggle menu'); ?>">
                <span></span>
                <span></span>
                <span></span>
            </button>
            <a href="about.php?lang=<?php echo $currentLang; ?>" class="site-header__logo">NicheHome.ch</a>
            <nav class="primary-nav" aria-label="<?php echo I18N::t('common.mainMenu', 'Main menu'); ?>">
                <ul class="primary-nav__list">
                    <li class="primary-nav__item primary-nav__item--mega">
                        <a class="primary-nav__link" href="catalog.php?lang=<?php echo $currentLang; ?>" data-mega-toggle aria-haspopup="true" aria-expanded="false">
                            <?php echo I18N::t('nav.catalog', 'Catalog'); ?>
                        </a>
                        <div class="mega-panel mega-panel--catalog">
                            <ul class="mega-panel__list">
                                <?php foreach ($navigationCategories as $slug => $category): ?>
                                    <li>
                                        <a href="<?php echo htmlspecialchars(getCategoryUrl($slug, $category, $currentLang)); ?>">
                                            <?php echo I18N::t('category.' . $slug . '.name', ucfirst(str_replace('_', ' ', $slug))); ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </li>
                    <li class="primary-nav__item">
                        <a class="primary-nav__link" href="gift-sets.php?lang=<?php echo $currentLang; ?>">
                            <?php echo I18N::t('nav.giftSets', 'Gift Sets'); ?>
                        </a>
                    </li>
                    <li class="primary-nav__item">
                        <a class="primary-nav__link" href="aroma-marketing.php?lang=<?php echo $currentLang; ?>">
                            <?php echo I18N::t('nav.aromaMarketing', 'Aroma Marketing'); ?>
                        </a>
                    </li>
                    <li class="primary-nav__item">
                        <a class="primary-nav__link" href="about.php?lang=<?php echo $currentLang; ?>">
                            <?php echo I18N::t('nav.about', 'About Us'); ?>
                        </a>
                    </li>
                    <li class="primary-nav__item">
                        <a class="primary-nav__link" href="contacts.php?lang=<?php echo $currentLang; ?>">
                            <?php echo I18N::t('nav.contacts', 'Contact'); ?>
                        </a>
                    </li>
                </ul>
            </nav>
            <div class="site-header__actions">
                <?php $customer = getCurrentCustomer(); ?>
                <div class="site-header__account">
                    <?php if ($customer): ?>
                        <?php $initials = getCustomerInitials($customer); ?>
                        <button type="button" class="account-menu__trigger" aria-haspopup="true" aria-expanded="false" aria-label="<?php echo I18N::t('common.myAccount', 'My Account'); ?>">
                            <span class="account-avatar"><?php echo htmlspecialchars($initials); ?></span>
                        </button>
                        <div class="account-menu" hidden>
                            <a href="account.php?tab=profile&lang=<?php echo htmlspecialchars(I18N::getLanguage()); ?>"><?php echo I18N::t('account.tab.profile', 'Profile'); ?></a>
                            <a href="account.php?tab=orders&lang=<?php echo htmlspecialchars(I18N::getLanguage()); ?>"><?php echo I18N::t('account.tab.orders', 'My Orders'); ?></a>
                            <a href="account.php?tab=favorites&lang=<?php echo htmlspecialchars(I18N::getLanguage()); ?>"><?php echo I18N::t('account.tab.favorites', 'My Favorites'); ?></a>
                            <a href="account.php?action=logout&lang=<?php echo htmlspecialchars(I18N::getLanguage()); ?>"><?php echo I18N::t('account.btn.logout', 'Logout'); ?></a>
                        </div>
                    <?php else: ?>
                        <a href="account.php?lang=<?php echo htmlspecialchars(I18N::getLanguage()); ?>" class="account-menu__login-link" aria-label="<?php echo I18N::t('common.myAccount', 'My Account'); ?>">
                            <span class="account-icon">👤</span>
                        </a>
                    <?php endif; ?>
                </div>
                <a href="cart.php?lang=<?php echo $currentLang; ?>" class="site-header__cart" aria-label="<?php echo I18N::t('common.cart', 'Cart'); ?>">
                    <span class="cart-icon">🛒</span>
                    <?php if ($cartCount > 0): ?>
                        <span class="cart-count" data-cart-count><?php echo $cartCount; ?></span>
                    <?php else: ?>
                        <span class="cart-count" data-cart-count style="display: none;">0</span>
                    <?php endif; ?>
                </a>
                <div class="lang-dropdown" data-lang-dropdown>
                    <button type="button" class="lang-dropdown__toggle" data-lang-toggle aria-haspopup="listbox" aria-expanded="false">
                        <span data-current-lang-label><?php echo I18N::getLanguageLabel($currentLang); ?></span>
                        <span class="lang-dropdown__chevron" aria-hidden="true"></span>
                    </button>
                    <ul class="lang-dropdown__list" role="listbox">
                        <?php foreach (I18N::getSupportedLanguages() as $langCode): ?>
                            <li>
                                <button type="button" 
                                        data-lang="<?php echo $langCode; ?>" 
                                        class="lang-dropdown__option <?php echo $langCode === $currentLang ? 'is-active' : ''; ?>">
                                    <?php echo I18N::getLanguageLabel($langCode); ?>
                                </button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </header>
    <main>
