<?php
/**
 * About Us - Homepage
 */

require_once __DIR__ . '/init.php';
include __DIR__ . '/includes/header.php';

$pageTitle = I18N::t('page.about.title', 'NicheHome.ch – Premium Home Fragrances in Switzerland');
$pageSubtitle = I18N::t('page.about.subtitle', 'Official Swiss destination for By Velcheva');
$pageContent = I18N::t('page.about.content', 'Welcome to NicheHome.ch, the official Swiss destination for premium home fragrances by the boutique brand By Velcheva.
We specialize in elegant aroma diffusers, scented candles, interior perfumes, textile sprays, and luxury car fragrances, designed to transform any living space into a refined sensory experience.

<strong><em>Our philosophy is simple:</em></strong>
A home fragrance is not just a scent — it is atmosphere, emotion, and interior design in its purest form.

<strong><em>About the Brand – By Velcheva</em></strong>

By Velcheva is an international premium brand known for its sophisticated aromatic compositions and stylish aesthetic. Each product is crafted with high-quality fragrance oils, clean ingredients, and long-lasting diffusion technology.
The brand\'s collection ranges from warm, cozy notes to fresh botanical accords and vibrant fruity signatures, making it suitable for every home and lifestyle.

Every candle, diffuser, and spray is created with meticulous attention to detail — from the formula to the minimalistic packaging — reflecting the brand\'s core values: elegance, purity, and sensory harmony.

<strong><em>Our Mission in Switzerland</em></strong>

At NicheHome.ch, we bring the world of luxury home perfumery to Switzerland. We provide fast delivery, exclusive limited editions, and premium customer service for:

• Private clients
• Interior designers
• Boutique hotels
• Wellness and beauty studios
• Corporate and retail spaces

Whether you\'re scenting a home, office, showroom, or gifting something special, we offer premium solutions tailored to elevate every environment.

<strong><em>Why Choose NicheHome.ch</em></strong>

✔ Official Swiss platform for By Velcheva
✔ Long-lasting and high-quality fragrances
✔ Clean, aesthetic, interior-friendly design
✔ Fast shipping within Switzerland
✔ Premium gift options for any occasion

Our products suit those who appreciate beautiful interiors, refined atmospheres, and high-end sensory experiences.');
?>

<main class="about-page about-page--premium">
    <section class="about-page__section">
        <div class="about-banner">
            <img src="/img/about_banner.jpg" alt="About NicheHome Banner">
        </div>
        <div class="about-page__inner">
            <p class="section-heading__label"><?php echo I18N::t('nav.about', 'About Us'); ?></p>
            <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
            <p><em><?php echo htmlspecialchars($pageSubtitle); ?></em></p>
            
            <p class="about-page__content">
                <?php 
                // Note: Content comes from controlled translation files (data/i18n/*.json)
                // strip_tags allows only safe formatting tags: <strong>, <em>, <br>
                echo nl2br(strip_tags($pageContent, '<strong><em><br>')); 
                ?>
            </p>
            
            <div class="about-page__cta">
                <a href="catalog.php?lang=<?php echo I18N::getLanguage(); ?>" class="btn btn--gold">
                    <?php echo I18N::t('nav.catalog', 'View Catalog'); ?>
                </a>
            </div>
        </div>
    </section>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>