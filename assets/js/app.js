/**
 * NICHEHOME.CH - Main JavaScript
 * Handles: Navigation, Cart, Selectors, Price Updates
 */

(function() {
    'use strict';

    // ========================================
    // NAVIGATION
    // ========================================
    
    const burger = document.querySelector('.site-header__burger');
    const header = document.querySelector('.site-header');
    const nav = document.querySelector('.primary-nav');
    
    if (burger) {
        burger.addEventListener('click', () => {
            header.classList.toggle('nav-open');
        });
    }

    // Close nav when clicking outside
    document.addEventListener('click', (e) => {
        if (header && !header.contains(e.target)) {
            header.classList.remove('nav-open');
        }
    });

    // Mega menu handling
    const megaItems = document.querySelectorAll('.primary-nav__item--mega');
    megaItems.forEach(item => {
        const toggle = item.querySelector('[data-mega-toggle]');
        if (toggle) {
            toggle.addEventListener('click', (e) => {
                if (window.innerWidth <= 1100) {
                    e.preventDefault();
                    item.classList.toggle('is-open');
                }
            });
        }
    });

    // ========================================
    // LANGUAGE SWITCHER
    // ========================================
    
    const langDropdown = document.querySelector('[data-lang-dropdown]');
    const langToggle = document.querySelector('[data-lang-toggle]');
    
    if (langToggle && langDropdown) {
        langToggle.addEventListener('click', () => {
            langDropdown.classList.toggle('is-open');
        });

        // Language option click
        const langOptions = langDropdown.querySelectorAll('[data-lang]');
        langOptions.forEach(option => {
            option.addEventListener('click', () => {
                const lang = option.dataset.lang;
                const url = new URL(window.location.href);
                url.searchParams.set('lang', lang);
                window.location.href = url.toString();
            });
        });

        // Close when clicking outside
        document.addEventListener('click', (e) => {
            if (!langDropdown.contains(e.target)) {
                langDropdown.classList.remove('is-open');
            }
        });
    }

    // ========================================
    // PRICE CONFIGURATION
    // ========================================
    
    // CRITICAL FIX: Single source of truth for prices
    // Prices MUST come from window.PRICES which is populated from products.json by PHP
    // This ensures storefront always shows the same prices as cart after admin updates
    // 
    // Legacy DEFAULT_PRICES removed to prevent drift between products.json and JavaScript
    // If window.PRICES is not set, log error and use empty object (safer than stale data)
    
    const hasProductCards = document.querySelector('[data-product-card]') !== null;
    if (hasProductCards && !window.PRICES) {
        console.error('PRICE ERROR: window.PRICES not set. Product pages must pass pricing data from products.json');
    }
    
    // Use window.PRICES directly - populated from products.json variants by category.php/product.php
    const PRICES = window.PRICES || {};

    // ========================================
    // PRODUCT SELECTORS
    // ========================================
    
    function initProductSelectors() {
        const productCards = document.querySelectorAll('[data-product-card]');
        
        productCards.forEach(card => {
            const productId = card.dataset.productId;
            const volumeSelect = card.querySelector('[data-volume-select]');
            const fragranceSelect = card.querySelector('[data-fragrance-select]');
            const addToCartBtn = card.querySelector('[data-add-to-cart]');
            const category = card.dataset.category;

            // Volume change handler
            if (volumeSelect) {
                volumeSelect.addEventListener('change', () => {
                    updatePrice(card, category, productId);
                });
            }

            // Fragrance change handler
            if (fragranceSelect) {
                fragranceSelect.addEventListener('change', () => {
                    updatePrice(card, category, productId);
                    updateFragranceInfo(card, fragranceSelect.value);
                });
            }

            // Add to cart handler
            if (addToCartBtn) {
                addToCartBtn.addEventListener('click', async () => {
                    await addToCart(card);
                });
            }

            updatePrice(card, category, productId);
            if (fragranceSelect) {
                updateFragranceInfo(card, fragranceSelect.value);
            }
        });
    }

    function getPriceConfig(card, category, productId) {
        if (productId && Object.prototype.hasOwnProperty.call(PRICES, productId)) {
            return PRICES[productId];
        }
        if (category && Object.prototype.hasOwnProperty.call(PRICES, category)) {
            return PRICES[category];
        }
        return null;
    }

    function isNestedPriceConfig(config) {
        if (!config || typeof config !== 'object' || Array.isArray(config)) {
            return false;
        }
        return Object.values(config).some(value => value && typeof value === 'object' && !Array.isArray(value));
    }

    function resolveProductSelection(card, category, productId) {
        const volumeSelect = card.querySelector('[data-volume-select]');
        const fragranceSelect = card.querySelector('[data-fragrance-select]');
        const config = getPriceConfig(card, category, productId);
        let volume = volumeSelect ? volumeSelect.value : 'standard';
        let fragrance = fragranceSelect ? fragranceSelect.value : 'none';
        let price = 0;

        if (config && typeof config === 'object') {
            if (isNestedPriceConfig(config)) {
                const availableVolumes = Object.keys(config);
                if (!config[volume] && availableVolumes.length > 0) {
                    volume = availableVolumes[0];
                    if (volumeSelect) {
                        volumeSelect.value = volume;
                    }
                }

                const fragranceMap = config[volume] || {};
                const availableFragrances = Object.keys(fragranceMap);
                if (!fragranceMap[fragrance] && availableFragrances.length > 0) {
                    fragrance = availableFragrances[0];
                    if (fragranceSelect) {
                        fragranceSelect.value = fragrance;
                    }
                }

                price = fragranceMap[fragrance] || 0;
            } else {
                if (!Object.prototype.hasOwnProperty.call(config, volume)) {
                    const availableVolumes = Object.keys(config);
                    if (availableVolumes.length > 0) {
                        volume = availableVolumes[0];
                        if (volumeSelect) {
                            volumeSelect.value = volume;
                        }
                    }
                }
                price = config[volume] || config.standard || 0;
            }
        } else if (typeof config === 'number') {
            price = config;
        }

        return { price, volume, fragrance };
    }

    function updatePrice(card, category, productId) {
        const priceDisplay = card.querySelector('[data-price-display]');
        
        if (!priceDisplay) return;

        const selection = resolveProductSelection(card, category, productId);
        const price = selection.price || 0;
        priceDisplay.textContent = 'CHF ' + price.toFixed(2);
    }

    function updateFragranceInfo(card, fragranceCode) {
        // Update the main product image when fragrance changes
        const productImage = card.querySelector('[data-product-image]');
        const fragranceSelect = card.querySelector('[data-fragrance-select]');
        const allowFragranceImage = !productImage || productImage.dataset.allowFragranceImage !== 'false';
        
        if (productImage && fragranceSelect && fragranceCode && fragranceCode !== 'none') {
            const defaultImage = productImage.dataset.defaultImage || '/img/placeholder.svg';
            if (!allowFragranceImage) {
                productImage.src = defaultImage;
            } else {
            // Get the image path from the data attribute on the selected option
                const selectedOption = fragranceSelect.querySelector('option:checked');
                if (selectedOption && selectedOption.dataset.image && !selectedOption.dataset.image.endsWith('/placeholder.svg')) {
                    // Use the data-image directly - it already contains the full /img/ path
                    productImage.src = selectedOption.dataset.image;
                } else if (window.FRAGRANCES && window.FRAGRANCES[fragranceCode] && window.FRAGRANCES[fragranceCode].image) {
                    const fragranceImage = window.FRAGRANCES[fragranceCode].image;
                    productImage.src = fragranceImage && !fragranceImage.endsWith('/placeholder.svg') ? fragranceImage : defaultImage;
                } else {
                    productImage.src = defaultImage;
                }
            }
        } else if (productImage && productImage.dataset.defaultImage) {
            productImage.src = productImage.dataset.defaultImage;
        }
        
        // Update fragrance description from FRAGRANCE_DESCRIPTIONS
        updateFragranceDescription(card, fragranceCode);
        
        // Legacy fragrance info display (if the element exists)
        const fragranceInfo = card.querySelector('[data-fragrance-info]');
        if (!fragranceInfo || !fragranceCode || fragranceCode === 'none') {
            if (fragranceInfo) {
                fragranceInfo.style.display = 'none';
            }
            return;
        }

        // Get fragrance data (this would ideally come from an API)
        const fragranceData = window.FRAGRANCES ? window.FRAGRANCES[fragranceCode] : null;
        
        if (fragranceData) {
            const nameEl = fragranceInfo.querySelector('[data-fragrance-name]');
            const descEl = fragranceInfo.querySelector('[data-fragrance-desc]');
            const imgEl = fragranceInfo.querySelector('[data-fragrance-image]');

            if (nameEl) nameEl.textContent = fragranceData.name || '';
            if (descEl) descEl.textContent = fragranceData.short || '';
            if (imgEl && fragranceData.image) {
                // Use the image path directly - it already contains the full /img/ path
                imgEl.src = fragranceData.image;
                imgEl.alt = fragranceData.name || '';
            }

            fragranceInfo.style.display = 'block';
        }
    }

    // ========================================
    // FRAGRANCE & CATEGORY DESCRIPTION TOGGLE
    // ========================================

    /**
     * Get first N lines from text
     */
    function getShortText(fullText, maxLines) {
        const lines = fullText.split(/\r?\n/).filter(l => l.trim() !== '');
        const shortLines = lines.slice(0, maxLines);
        return shortLines.join('\n');
    }

    /**
     * Update fragrance description in product card
     * Uses 'short' directly from FRAGRANCE_DESCRIPTIONS (from i18n)
     */
    function updateFragranceDescription(card, fragranceCode) {
        const descData = window.FRAGRANCE_DESCRIPTIONS || {};
        const info = descData[fragranceCode];
        const descBlock = card.querySelector('.product-card__fragrance-description');
        
        if (!descBlock) return;
        
        const shortEl = descBlock.querySelector('.product-card__fragrance-text--short');
        const fullEl = descBlock.querySelector('.product-card__fragrance-text--full');
        const toggleBtn = descBlock.querySelector('.product-card__fragrance-toggle');

        if (!shortEl || !fullEl || !toggleBtn) return;

        if (!info || (!info.short && !info.full)) {
            shortEl.textContent = '';
            fullEl.textContent = '';
            toggleBtn.style.display = 'none';
            descBlock.style.display = 'none';
            return;
        }

        // Use 'short' from i18n directly (already translated)
        const short = info.short || '';
        const full = info.full || '';

        shortEl.textContent = short;
        fullEl.textContent = full;
        fullEl.style.display = 'none';
        shortEl.style.display = 'block';
        descBlock.style.display = 'block';

        toggleBtn.style.display = 'inline-block';
        toggleBtn.textContent = getI18NLabel('fragrance_read_more');
        toggleBtn.dataset.expanded = 'false';
    }

    /**
     * Handle fragrance description toggle click
     */
    function onFragranceToggleClick(event) {
        const btn = event.target.closest('.product-card__fragrance-toggle');
        if (!btn) return;

        const card = btn.closest('.product-card');
        if (!card) return;

        const descBlock = card.querySelector('.product-card__fragrance-description');
        if (!descBlock) return;

        const shortEl = descBlock.querySelector('.product-card__fragrance-text--short');
        const fullEl = descBlock.querySelector('.product-card__fragrance-text--full');
        if (!shortEl || !fullEl) return;

        const expanded = btn.dataset.expanded === 'true';

        if (expanded) {
            fullEl.style.display = 'none';
            shortEl.style.display = 'block';
            btn.textContent = getI18NLabel('fragrance_read_more');
            btn.dataset.expanded = 'false';
            descBlock.classList.remove('expanded');
            descBlock.classList.remove('product-card__fragrance-description--expanded');
        } else {
            fullEl.style.display = 'block';
            shortEl.style.display = 'none';
            btn.textContent = getI18NLabel('fragrance_collapse');
            btn.dataset.expanded = 'true';
            descBlock.classList.add('expanded');
            descBlock.classList.add('product-card__fragrance-description--expanded');
        }
    }

    /**
     * Initialize category descriptions with toggle
     */
    function initCategoryDescriptions() {
        document.querySelectorAll('.category-hero__description-block').forEach(block => {
            const full = block.dataset.fullDescription || '';
            const shortEl = block.querySelector('.category-hero__description-short');
            const fullEl = block.querySelector('.category-hero__description-full');
            const toggleBtn = block.querySelector('.category-hero__description-toggle');
            
            if (!shortEl || !fullEl || !toggleBtn || !full) {
                if (toggleBtn) toggleBtn.style.display = 'none';
                return;
            }

            const short = getShortText(full, 3); // first 3 lines for category

            shortEl.textContent = short;
            fullEl.textContent = full;
            fullEl.style.display = 'none';
            shortEl.style.display = 'block';

            toggleBtn.dataset.expanded = 'false';
            toggleBtn.textContent = getI18NLabel('category_read_more');
        });
    }

    /**
     * Handle category description toggle click
     */
    function onCategoryDescriptionToggleClick(event) {
        const btn = event.target.closest('.category-hero__description-toggle');
        if (!btn) return;

        const block = btn.closest('.category-hero__description-block');
        if (!block) return;

        const shortEl = block.querySelector('.category-hero__description-short');
        const fullEl = block.querySelector('.category-hero__description-full');
        if (!shortEl || !fullEl) return;

        const expanded = btn.dataset.expanded === 'true';

        if (expanded) {
            fullEl.style.display = 'none';
            shortEl.style.display = 'block';
            btn.textContent = getI18NLabel('category_read_more');
            btn.dataset.expanded = 'false';
        } else {
            fullEl.style.display = 'block';
            shortEl.style.display = 'none';
            btn.textContent = getI18NLabel('category_collapse');
            btn.dataset.expanded = 'true';
        }
    }

    /**
     * Get I18N label with fallback
     */
    function getI18NLabel(key) {
        const labels = window.I18N_LABELS || {};
        const defaults = {
            fragrance_read_more: 'Read more',
            fragrance_collapse: 'Collapse',
            category_read_more: 'Read more',
            category_collapse: 'Collapse'
        };
        return labels[key] || defaults[key] || key;
    }

    /**
     * Initialize fragrance descriptions on page load
     */
    function initFragranceDescriptions() {
        const productCards = document.querySelectorAll('[data-product-card]');
        productCards.forEach(card => {
            const fragranceSelect = card.querySelector('[data-fragrance-select]');
            if (fragranceSelect && fragranceSelect.value) {
                updateFragranceDescription(card, fragranceSelect.value);
            }
        });
    }

    // Add event listeners for toggle clicks
    document.addEventListener('click', onFragranceToggleClick);
    document.addEventListener('click', onCategoryDescriptionToggleClick);
    
    // Delegated event listener for fragrance select changes
    document.addEventListener('change', function(event) {
        if (!event.target.classList.contains('product-card__select--fragrance')) return;
        
        const select = event.target;
        const productId = select.dataset.productId;
        const option = select.options[select.selectedIndex];
        const imagePath = option.dataset.image;
        const fragranceCode = option.value;
        
        const card = select.closest('.product-card');
        if (!card) return;
        
        // 1) Update image
        const img = card.querySelector('.product-card__image-el[data-product-id="' + productId + '"]');
        if (img && imagePath) {
            img.src = imagePath;
        }
        
        // 2) Update fragrance description (short/full)
        if (typeof updateFragranceDescription === 'function') {
            updateFragranceDescription(card, fragranceCode);
        }
    });

    // ========================================
    // CART FUNCTIONALITY
    // ========================================
    
    function getCart() {
        const cartData = localStorage.getItem('nichehome_cart');
        return cartData ? JSON.parse(cartData) : [];
    }

    async function saveCart(cart) {
        localStorage.setItem('nichehome_cart', JSON.stringify(cart));
        updateCartCount();
        // Sync to PHP session
        await syncCartToServer(cart);
    }
    
    /**
     * Sync cart to PHP session
     */
    function syncCartToServer(cart) {
        return fetch('add_to_cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'sync',
                cart: cart
            })
        }).catch(function(error) {
            console.error('Cart sync error:', error);
        });
    }
    
    /**
     * Add item to server cart
     */
    function addItemToServer(item) {
        fetch('add_to_cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'add',
                item: item
            })
        }).catch(function(error) {
            console.error('Add to cart error:', error);
        });
    }

    /**
     * Update cart count display elements
     */
    function updateCartCountDisplay(count) {
        const cartCountElements = document.querySelectorAll('[data-cart-count]');
        cartCountElements.forEach(el => {
            el.textContent = count;
            // Show/hide based on count
            if (count > 0) {
                el.style.display = '';
            } else {
                el.style.display = 'none';
            }
        });
    }
    
    function updateCartCount() {
        const cart = getCart();
        let count = 0;
        cart.forEach(item => {
            count += item.quantity || 1;
        });
        updateCartCountDisplay(count);
    }

    async function addToCart(card) {
        const productId = card.dataset.productId;
        const productName = card.dataset.productName;
        const category = card.dataset.category;
        const selection = resolveProductSelection(card, category, productId);
        const volume = selection.volume;
        const fragrance = selection.fragrance;
        const price = selection.price;

        // Generate SKU
        const sku = generateSKU(productId, volume, fragrance);

        const item = {
            sku: sku,
            productId: productId,
            name: productName,
            category: category,
            volume: volume,
            fragrance: fragrance,
            price: price,
            quantity: 1
        };

        const cart = getCart();
        
        // Check if item already exists
        const existingIndex = cart.findIndex(i => i.sku === sku);
        if (existingIndex > -1) {
            cart[existingIndex].quantity += 1;
        } else {
            cart.push(item);
        }

        await saveCart(cart);
        showAddToCartFeedback(card);
    }

    function generateSKU(productId, volume, fragrance) {
        // Map productId to 2-3 character prefix
        const prefixMap = {
            'diffuser_classic': 'DF',
            'candle_classic': 'CD',
            'home_spray': 'HP',
            'car_clip': 'CP',
            'textile_spray': 'TP',
            'limited_new_york': 'LE',
            'limited_abu_dhabi': 'LE',
            'limited_palermo': 'LE',
            'aroma_sashe': 'ARO',
            'christ_toy': 'CHR',
            'refill_125': 'REF',
            'sticks': 'STI'
        };

        const fragranceSuffixMap = {
            'salty_water': 'SW',
            'salted_caramel': 'SC'
        };

        const prefix = prefixMap[productId] || productId.substring(0, 3).toUpperCase();
        let vol = volume.replace('ml', '');
        // Handle "standard" volume for accessories
        if (vol === 'standard' || !vol) {
            vol = 'STA';
        }
        // Long descriptive accessory volumes (e.g. "5 guggul + 5 louban")
        // must be compacted to the same sanitized 3-char code that PHP uses.
        if (vol.length > 10) {
            vol = vol.replace(/[^0-9a-z]/gi, '').substring(0, 3).toUpperCase();
        } else {
            vol = vol.toUpperCase();
        }

        const normalizedFragrance = (fragrance || '').trim();
        let frag;
        if (!normalizedFragrance || normalizedFragrance === 'none' || normalizedFragrance === 'null' || normalizedFragrance === 'NA') {
            frag = 'NA';
        } else if (fragranceSuffixMap[normalizedFragrance]) {
            frag = fragranceSuffixMap[normalizedFragrance];
        } else {
            frag = normalizedFragrance.substring(0, 3).toUpperCase();
        }

        return prefix + '-' + vol + '-' + frag;
    }

    function showAddToCartFeedback(card) {
        const btn = card.querySelector('[data-add-to-cart]');
        if (btn) {
            const originalText = btn.textContent;
            btn.textContent = '✓ Added!';
            btn.disabled = true;
            
            setTimeout(() => {
                btn.textContent = originalText;
                btn.disabled = false;
            }, 1500);
        }
    }

    // Remove from cart
    window.removeFromCart = async function(sku) {
        let cart = getCart();
        cart = cart.filter(item => item.sku !== sku);
        await saveCart(cart);
        
        // Also send remove to server
        fetch('add_to_cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'remove',
                sku: sku
            })
        }).then(function() {
            // Reload cart page if on cart page
            if (window.location.pathname.includes('cart.php')) {
                window.location.reload();
            }
        }).catch(function(error) {
            console.error('Remove from cart error:', error);
            // Still reload even on error
            if (window.location.pathname.includes('cart.php')) {
                window.location.reload();
            }
        });
    };

    // Update cart quantity
    window.updateCartQuantity = async function(sku, quantity) {
        const cart = getCart();
        const item = cart.find(i => i.sku === sku);
        
        if (item) {
            if (quantity <= 0) {
                await removeFromCart(sku);
            } else {
                item.quantity = parseInt(quantity);
                await saveCart(cart);
                
                // Also update on server
                fetch('add_to_cart.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'update',
                        sku: sku,
                        quantity: parseInt(quantity)
                    })
                }).catch(function(error) {
                    console.error('Update quantity error:', error);
                });
            }
        }
    };
    
    /**
     * Sync cart on page load - get cart from server and update localStorage
     */
    async function syncCartOnLoad() {
        try {
            // First, get the current cart from the server
            const response = await fetch('add_to_cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'get'
                })
            });
            
            if (response.ok) {
                const data = await response.json();
                if (data.success && data.cart) {
                    const serverCart = data.cart;
                    
                    // Server is the source of truth
                    if (serverCart.length > 0) {
                        // Server has items - update localStorage to match
                        localStorage.setItem('nichehome_cart', JSON.stringify(serverCart));
                        updateCartCountDisplay(data.cartCount);
                    } else {
                        // Server is empty - clear localStorage and reset count
                        localStorage.setItem('nichehome_cart', JSON.stringify([]));
                        updateCartCountDisplay(0);
                    }
                }
            }
        } catch (error) {
            console.error('Cart sync on load error:', error);
            // If server is unreachable, just update display from localStorage
            // Do NOT sync local cart to server to prevent reviving cleared carts
            updateCartCount();
        }
    }

    // ========================================
    // GIFT SET FUNCTIONALITY
    // ========================================
    
    // Debug mode detection
    window.__GIFTSET_DEBUG__ = new URLSearchParams(location.search).has('debug');
    
    // Debug overlay state
    const debugState = {
        jsLoaded: false,
        rootFound: false,
        slotsFound: 0,
        lastEvent: '',
        lastError: ''
    };
    
    /**
     * Update debug overlay
     */
    function updateDebugOverlay() {
        if (!window.__GIFTSET_DEBUG__) return;
        
        let overlay = document.getElementById('giftset-debug-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'giftset-debug-overlay';
            overlay.style.cssText = 'position: fixed; top: 10px; right: 10px; background: rgba(0,0,0,0.9); color: #0f0; padding: 15px; border-radius: 5px; font-family: monospace; font-size: 12px; z-index: 10000; max-width: 300px;';
            document.body.appendChild(overlay);
        }
        
        overlay.innerHTML = `
            <div><strong>Gift Sets Debug</strong></div>
            <div>JS Loaded: ${debugState.jsLoaded ? 'YES' : 'NO'}</div>
            <div>Root Found: ${debugState.rootFound ? 'YES' : 'NO'}</div>
            <div>Slots Found: ${debugState.slotsFound}</div>
            <div>Last Event: ${debugState.lastEvent || 'none'}</div>
            <div style="color: #f00;">Last Error: ${debugState.lastError || 'none'}</div>
        `;
    }
    
    /**
     * Log debug event
     */
    function logDebugEvent(message) {
        if (!window.__GIFTSET_DEBUG__) return;
        debugState.lastEvent = message;
        updateDebugOverlay();
    }
    
    /**
     * Log debug error
     */
    function logDebugError(error) {
        if (!window.__GIFTSET_DEBUG__) return;
        debugState.lastError = String(error);
        updateDebugOverlay();
    }
    
    // Store for loaded product data per category
    const giftSetProductsCache = {};
    
    /**
     * Initialize Gift Set functionality with event delegation
     */
    function initGiftSet() {
        try {
            debugState.jsLoaded = true;
            
            const giftSetForm = document.querySelector('[data-gift-set-form]');
            if (!giftSetForm) {
                debugState.rootFound = false;
                logDebugEvent('Gift set form not found');
                updateDebugOverlay();
                return;
            }
            
            debugState.rootFound = true;
            const slots = giftSetForm.querySelectorAll('[data-gift-slot]');
            debugState.slotsFound = slots.length;
            logDebugEvent('Init complete');
            updateDebugOverlay();
            
            // Use event delegation for all selectors
            // This ensures handlers work even if DOM is re-rendered
            document.addEventListener('change', handleGiftSetChange);
            
            // Add gift set to cart
            const addGiftSetBtn = giftSetForm.querySelector('[data-add-gift-set]');
            if (addGiftSetBtn) {
                addGiftSetBtn.addEventListener('click', () => {
                    logDebugEvent('Add to cart clicked');
                    addGiftSetToCart(giftSetForm);
                });
            }
        } catch (error) {
            logDebugError(error);
            console.error('Gift Set initialization error:', error);
        }
    }
    
    /**
     * Delegated change handler for all gift set selects
     */
    function handleGiftSetChange(e) {
        try {
            const target = e.target;
            
            // Category selector
            if (target.matches('[data-giftset-category]') || target.matches('[data-gift-category]')) {
                const slot = target.closest('[data-gift-slot]');
                const slotNum = slot ? slot.dataset.giftsetSlot || 'unknown' : 'unknown';
                logDebugEvent(`Category changed slot ${slotNum} value=${target.value}`);
                if (slot) handleCategoryChange(slot);
                return;
            }
            
            // Product selector
            if (target.matches('[data-giftset-product]') || target.matches('[data-gift-product]')) {
                const slot = target.closest('[data-gift-slot]');
                const slotNum = slot ? slot.dataset.giftsetSlot || 'unknown' : 'unknown';
                logDebugEvent(`Product changed slot ${slotNum} value=${target.value}`);
                if (slot) handleProductChange(slot);
                return;
            }
            
            // Variant/Size selector
            if (target.matches('[data-giftset-size]') || target.matches('[data-gift-variant]')) {
                const slot = target.closest('[data-gift-slot]');
                const slotNum = slot ? slot.dataset.giftsetSlot || 'unknown' : 'unknown';
                logDebugEvent(`Variant changed slot ${slotNum} value=${target.value}`);
                if (slot) handleVariantChange(slot);
                return;
            }
            
            // Fragrance selector
            if (target.matches('[data-giftset-fragrance]') || target.matches('[data-gift-fragrance]')) {
                const slot = target.closest('[data-gift-slot]');
                const slotNum = slot ? slot.dataset.giftsetSlot || 'unknown' : 'unknown';
                logDebugEvent(`Fragrance changed slot ${slotNum} value=${target.value}`);
                if (slot) validateSlotAndUpdateTotal(slot);
                return;
            }
        } catch (error) {
            logDebugError(error);
            console.error('Gift Set change handler error:', error);
        }
    }

    function handleCategoryChange(slot) {
        try {
            const categorySelect = slot.querySelector('[data-gift-category]');
            const category = categorySelect ? categorySelect.value : '';
            
            // Reset product, variant, and fragrance
            resetProductSelector(slot);
            resetVariantSelector(slot);
            resetFragranceSelector(slot);
            clearSlotError(slot);
            
            if (!category) {
                validateSlotAndUpdateTotal(slot);
                return;
            }
            
            // Load products for this category
            loadProductsForCategory(category, slot);
        } catch (error) {
            logDebugError('handleCategoryChange: ' + error);
            console.error('Category change error:', error);
        }
    }

    function loadProductsForCategory(category, slot) {
        try {
            // Check cache first
            if (giftSetProductsCache[category]) {
                populateProductSelector(slot, giftSetProductsCache[category]);
                return;
            }
            
            // Fetch from server
            fetch('ajax/get_products.php?category=' + encodeURIComponent(category))
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.products) {
                        giftSetProductsCache[category] = data.products;
                        populateProductSelector(slot, data.products);
                        logDebugEvent('Loaded ' + data.products.length + ' products for ' + category);
                    } else {
                        logDebugError('Failed to load products for ' + category);
                        console.error('Failed to load products for category:', category);
                        showSlotError(slot, 'Failed to load products');
                    }
                })
                .catch(error => {
                    logDebugError('Product load error: ' + error);
                    console.error('Error loading products:', error);
                    showSlotError(slot, 'Error loading products');
                });
        } catch (error) {
            logDebugError('loadProductsForCategory: ' + error);
            console.error('Load products error:', error);
        }
    }

    function populateProductSelector(slot, products) {
        const productSelect = slot.querySelector('[data-gift-product]');
        const productGroup = slot.querySelector('[data-product-group]');
        
        if (!productSelect || !productGroup) return;
        
        const selectLabel = (window.I18N_LABELS && window.I18N_LABELS.selectProduct) || 'Select product';
        productSelect.innerHTML = '<option value="">' + selectLabel + '</option>';
        
        products.forEach(product => {
            const option = document.createElement('option');
            option.value = product.id;
            option.textContent = product.name;
            option.dataset.productData = JSON.stringify(product);
            productSelect.appendChild(option);
        });
        
        productGroup.style.display = 'block';
        validateSlotAndUpdateTotal(slot);
    }

    function handleProductChange(slot) {
        const productSelect = slot.querySelector('[data-gift-product]');
        const selectedOption = productSelect ? productSelect.selectedOptions[0] : null;
        
        // Reset variant and fragrance
        resetVariantSelector(slot);
        resetFragranceSelector(slot);
        clearSlotError(slot);
        
        if (!selectedOption || !selectedOption.value) {
            validateSlotAndUpdateTotal(slot);
            return;
        }
        
        const productData = JSON.parse(selectedOption.dataset.productData || '{}');
        
        // Show variant selector if product has multiple variants
        if (productData.variants && productData.variants.length > 0) {
            populateVariantSelector(slot, productData.variants);
        }
        
        // Show fragrance selector if product requires fragrance
        if (productData.requiresFragrance && productData.allowedFragrances && productData.allowedFragrances.length > 0) {
            populateFragranceSelector(slot, productData.allowedFragrances);
        } else {
            // No fragrance needed - set to 'NA' internally
            const fragranceSelect = slot.querySelector('[data-gift-fragrance]');
            if (fragranceSelect) {
                fragranceSelect.value = 'NA';
            }
        }
        
        validateSlotAndUpdateTotal(slot);
    }

    function populateVariantSelector(slot, variants) {
        const variantSelect = slot.querySelector('[data-gift-variant]');
        const variantGroup = slot.querySelector('[data-variant-group]');
        
        if (!variantSelect || !variantGroup) return;
        
        const selectLabel = (window.I18N_LABELS && window.I18N_LABELS.selectVariant) || 'Select size or pack';
        variantSelect.innerHTML = '<option value="">' + selectLabel + '</option>';
        
        variants.forEach(variant => {
            const option = document.createElement('option');
            option.value = variant.volume;
            option.textContent = variant.volume;
            option.dataset.price = variant.price;
            variantSelect.appendChild(option);
        });
        
        variantGroup.style.display = 'block';
    }

    function handleVariantChange(slot) {
        clearSlotError(slot);
        validateSlotAndUpdateTotal(slot);
    }

    function populateFragranceSelector(slot, allowedFragrances) {
        const fragranceSelect = slot.querySelector('[data-gift-fragrance]');
        const fragranceGroup = slot.querySelector('[data-fragrance-group]');
        
        if (!fragranceSelect || !fragranceGroup) return;
        
        const selectLabel = (window.I18N_LABELS && window.I18N_LABELS.selectFragrance) || 'Select fragrance';
        fragranceSelect.innerHTML = '<option value="">' + selectLabel + '</option>';
        
        allowedFragrances.forEach(frag => {
            const option = document.createElement('option');
            option.value = frag.code;
            option.textContent = frag.name;
            fragranceSelect.appendChild(option);
        });
        
        fragranceGroup.style.display = 'block';
    }

    function resetProductSelector(slot) {
        const productSelect = slot.querySelector('[data-gift-product]');
        const productGroup = slot.querySelector('[data-product-group]');
        if (productSelect) productSelect.value = '';
        if (productGroup) productGroup.style.display = 'none';
    }

    function resetVariantSelector(slot) {
        const variantSelect = slot.querySelector('[data-gift-variant]');
        const variantGroup = slot.querySelector('[data-variant-group]');
        if (variantSelect) variantSelect.value = '';
        if (variantGroup) variantGroup.style.display = 'none';
    }

    function resetFragranceSelector(slot) {
        const fragranceSelect = slot.querySelector('[data-gift-fragrance]');
        const fragranceGroup = slot.querySelector('[data-fragrance-group]');
        if (fragranceSelect) fragranceSelect.value = '';
        if (fragranceGroup) fragranceGroup.style.display = 'none';
    }

    function validateSlot(slot) {
        const categorySelect = slot.querySelector('[data-gift-category]');
        const productSelect = slot.querySelector('[data-gift-product]');
        const variantSelect = slot.querySelector('[data-gift-variant]');
        const fragranceSelect = slot.querySelector('[data-gift-fragrance]');
        const variantGroup = slot.querySelector('[data-variant-group]');
        const fragranceGroup = slot.querySelector('[data-fragrance-group]');
        
        const category = categorySelect ? categorySelect.value : '';
        const product = productSelect ? productSelect.value : '';
        const variant = variantSelect ? variantSelect.value : '';
        const fragrance = fragranceSelect ? fragranceSelect.value : '';
        
        // Empty slot is valid (not all slots required)
        if (!category && !product) {
            return { valid: true, isEmpty: true, error: null };
        }
        
        // Category selected but no product
        if (category && !product) {
            return { 
                valid: false, 
                isEmpty: false,
                error: (window.I18N_LABELS && window.I18N_LABELS.errorProductMissing) || 'Please choose a product for this slot'
            };
        }
        
        // Product selected but variant required and missing
        if (product && variantGroup && variantGroup.style.display !== 'none' && !variant) {
            return { 
                valid: false, 
                isEmpty: false,
                error: (window.I18N_LABELS && window.I18N_LABELS.errorVariantMissing) || 'Please choose a size or pack option'
            };
        }
        
        // Product selected but fragrance required and missing
        if (product && fragranceGroup && fragranceGroup.style.display !== 'none' && !fragrance) {
            return { 
                valid: false, 
                isEmpty: false,
                error: (window.I18N_LABELS && window.I18N_LABELS.errorFragranceMissing) || 'Please choose a fragrance'
            };
        }
        
        return { valid: true, isEmpty: false, error: null };
    }

    function getSlotPrice(slot) {
        const variantSelect = slot.querySelector('[data-gift-variant]');
        const selectedOption = variantSelect ? variantSelect.selectedOptions[0] : null;
        
        if (selectedOption && selectedOption.dataset.price) {
            return parseFloat(selectedOption.dataset.price) || 0;
        }
        
        return 0;
    }

    function validateSlotAndUpdateTotal(slot) {
        const form = slot.closest('[data-gift-set-form]');
        if (!form) return;
        
        const validation = validateSlot(slot);
        
        if (!validation.valid && !validation.isEmpty) {
            showSlotError(slot, validation.error);
            slot.classList.add('gift-slot--invalid');
        } else {
            clearSlotError(slot);
            slot.classList.remove('gift-slot--invalid');
        }
        
        updateGiftTotal(form);
    }

    function showSlotError(slot, message) {
        const errorDiv = slot.querySelector('[data-slot-error]');
        if (errorDiv && message) {
            errorDiv.textContent = message;
            errorDiv.style.display = 'block';
        }
    }

    function clearSlotError(slot) {
        const errorDiv = slot.querySelector('[data-slot-error]');
        if (errorDiv) {
            errorDiv.textContent = '';
            errorDiv.style.display = 'none';
        }
    }

    function isGiftsetComplete(form) {
        const slots = form.querySelectorAll('[data-gift-slot]');
        let validSlotsCount = 0;
        
        slots.forEach(slot => {
            const validation = validateSlot(slot);
            if (!validation.isEmpty && validation.valid) {
                validSlotsCount++;
            }
        });
        
        return validSlotsCount === 3;
    }

    function updateGiftTotal(form) {
        const slots = form.querySelectorAll('[data-gift-slot]');
        const totalDisplay = form.querySelector('[data-gift-total]');
        const discountDisplay = form.querySelector('[data-gift-discount]');
        const addBtn = form.querySelector('[data-add-gift-set]');
        const messageDisplay = form.querySelector('[data-gift-message]');
        
        let total = 0;
        let validSlotsCount = 0;
        let hasAnySelection = false;
        let hasAnyInvalidSlot = false;
        
        slots.forEach(slot => {
            const validation = validateSlot(slot);
            
            if (!validation.isEmpty) {
                hasAnySelection = true;
                
                if (!validation.valid) {
                    hasAnyInvalidSlot = true;
                } else {
                    validSlotsCount++;
                    total += getSlotPrice(slot);
                }
            }
        });
        
        // Check if gift set is complete (3 valid slots)
        const isComplete = isGiftsetComplete(form);
        
        if (!isComplete) {
            // Gift set is not complete - disable button and show message
            const selectLabel = (window.I18N_LABELS && window.I18N_LABELS.selectToSeePrice) || 'Select options to see price';
            if (totalDisplay) totalDisplay.textContent = selectLabel;
            if (discountDisplay) discountDisplay.style.display = 'none';
            if (addBtn) addBtn.disabled = true;
            
            // Show incomplete message if user has started filling but hasn't completed all 3 slots
            if (hasAnySelection && !hasAnyInvalidSlot && messageDisplay) {
                const incompleteMsg = (window.I18N_LABELS && window.I18N_LABELS.errorIncomplete) || 'Complete all 3 slots to add Gift Set (5% discount applies only to 3 items).';
                messageDisplay.textContent = incompleteMsg;
                messageDisplay.style.display = 'block';
            } else if (messageDisplay) {
                messageDisplay.style.display = 'none';
            }
        } else {
            // All 3 slots are valid - calculate and show price with discount
            const discount = total * 0.05;
            const finalTotal = total - discount;
            
            if (totalDisplay) totalDisplay.textContent = 'CHF ' + finalTotal.toFixed(2);
            if (discountDisplay) {
                discountDisplay.textContent = '-CHF ' + discount.toFixed(2);
                discountDisplay.style.display = 'block';
            }
            if (addBtn) addBtn.disabled = false;
            if (messageDisplay) messageDisplay.style.display = 'none';
        }
    }

    function addGiftSetToCart(form) {
        const addBtn = form.querySelector('[data-add-gift-set]');
        
        // Disable button to prevent duplicates
        if (addBtn.disabled) return;
        addBtn.disabled = true;
        const originalText = addBtn.textContent;
        addBtn.textContent = 'Adding...';
        
        logDebugEvent('Starting add to cart');
        
        const slots = form.querySelectorAll('[data-gift-slot]');
        const items = [];
        let total = 0;
        let hasErrors = false;

        slots.forEach((slot, index) => {
            const validation = validateSlot(slot);
            
            if (!validation.isEmpty) {
                if (!validation.valid) {
                    showSlotError(slot, validation.error);
                    hasErrors = true;
                    return;
                }
                
                const categorySelect = slot.querySelector('[data-gift-category]');
                const productSelect = slot.querySelector('[data-gift-product]');
                const variantSelect = slot.querySelector('[data-gift-variant]');
                const fragranceSelect = slot.querySelector('[data-gift-fragrance]');
                
                const category = categorySelect ? categorySelect.value : '';
                const productId = productSelect ? productSelect.value : '';
                const variant = variantSelect ? variantSelect.value : 'standard';
                const fragrance = fragranceSelect ? fragranceSelect.value || 'NA' : 'NA';
                const price = getSlotPrice(slot);
                
                // Get product name
                const selectedProductOption = productSelect ? productSelect.selectedOptions[0] : null;
                const productName = selectedProductOption ? selectedProductOption.textContent : '';
                
                items.push({
                    slot: index + 1,
                    category: category,
                    productId: productId,
                    productName: productName,
                    variant: variant,
                    fragrance: fragrance,
                    price: price,
                    qty: 1
                });
                total += price;
            }
        });

        if (hasErrors) {
            logDebugError('Validation errors found');
            addBtn.disabled = false;
            addBtn.textContent = originalText;
            return;
        }

        // Enforce 3-item rule
        if (items.length !== 3) {
            const errorMsg = (window.I18N_LABELS && window.I18N_LABELS.errorIncomplete) || 'Complete all 3 slots to add Gift Set (5% discount applies only to 3 items).';
            logDebugError('Incomplete: ' + items.length + ' items');
            alert(errorMsg);
            addBtn.disabled = false;
            addBtn.textContent = originalText;
            return;
        }

        const discount = total * 0.05;
        const finalTotal = total - discount;

        // Send gift set configuration to server
        // Server will generate unique SKU based on configuration (giftset:<hash>)
        // This ensures different configurations don't merge, identical ones do
        const giftSetItem = {
            sku: 'giftset-temp', // Temporary placeholder - server generates unique SKU
            productId: 'gift_set',
            name: 'Custom Gift Set',
            category: 'gift_sets',  // Must be 'gift_sets' for proper cart display
            items: items,
            gift_set_items: items,
            price: finalTotal,
            quantity: 1,
            isGiftSet: true
        };

        logDebugEvent('Sending to server');

        // Sync with server via add_to_cart.php
        fetch('add_to_cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'add',
                item: giftSetItem
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                logDebugEvent('Added successfully');
                // Update cart count if displayed
                updateCartCount();
                
                // Get localized message
                const message = (window.I18N_LABELS && window.I18N_LABELS.giftset_added) || 'Gift set added to cart!';
                alert(message);
                window.location.href = 'cart.php';
            } else {
                logDebugError('Server error: ' + (data.error || 'unknown'));
                // Handle structured validation errors from server
                if (data.validationErrors && Array.isArray(data.validationErrors)) {
                    // Display each validation error under the correct slot
                    data.validationErrors.forEach(error => {
                        const slotIndex = error.slot - 1;
                        if (slotIndex >= 0 && slotIndex < slots.length) {
                            const slot = slots[slotIndex];
                            showSlotError(slot, error.message);
                        }
                    });
                    addBtn.disabled = false;
                    addBtn.textContent = originalText;
                } else {
                    // Generic error
                    const errorMsg = (window.I18N_LABELS && window.I18N_LABELS.errorAddingGiftset) || 'Error adding gift set to cart';
                    alert(errorMsg + ': ' + (data.error || 'Unknown error'));
                    addBtn.disabled = false;
                    addBtn.textContent = originalText;
                }
            }
        })
        .catch(error => {
            logDebugError('Network error: ' + error);
            console.error('Error adding gift set to cart:', error);
            const errorMsg = (window.I18N_LABELS && window.I18N_LABELS.errorAddingGiftset) || 'Error adding gift set to cart';
            alert(errorMsg + '. Please try again.');
            addBtn.disabled = false;
            addBtn.textContent = originalText;
        });
    }
    
    // Global error handlers for debug mode
    if (window.__GIFTSET_DEBUG__) {
        window.addEventListener('error', function(event) {
            logDebugError('Global error: ' + event.message);
        });
        
        window.addEventListener('unhandledrejection', function(event) {
            logDebugError('Unhandled promise: ' + event.reason);
        });
    }

    // ========================================
    // CHECKOUT FORM
    // ========================================
    
    function initCheckoutForm() {
        const checkoutForm = document.querySelector('[data-checkout-form]');
        if (!checkoutForm) return;

        const sameAsShippingCheckbox = checkoutForm.querySelector('[data-same-as-shipping]');
        const billingSection = checkoutForm.querySelector('[data-billing-section]');

        if (sameAsShippingCheckbox && billingSection) {
            sameAsShippingCheckbox.addEventListener('change', () => {
                billingSection.style.display = sameAsShippingCheckbox.checked ? 'none' : 'block';
            });
        }

        // Form submission
        checkoutForm.addEventListener('submit', (e) => {
            // Form validation handled by PHP
        });
    }

    // ========================================
    // BACK IN STOCK NOTIFICATION
    // ========================================
    
    function initBackInStock() {
        const notifyForms = document.querySelectorAll('[data-notify-form]');
        
        notifyForms.forEach(form => {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                
                const emailInput = form.querySelector('input[type="email"]');
                const skuInput = form.querySelector('input[name="sku"]');
                
                if (!emailInput || !skuInput) return;

                const email = emailInput.value;
                const sku = skuInput.value;

                try {
                    const response = await fetch('ajax/notify.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ email, sku })
                    });

                    if (response.ok) {
                        form.innerHTML = '<p class="text-success">Thank you! We\'ll notify you when this item is back in stock.</p>';
                    }
                } catch (error) {
                    console.error('Error:', error);
                }
            });
        });
    }

    // ========================================
    // NEWSLETTER FORM
    // ========================================
    
    function initNewsletter() {
        const newsletterForm = document.querySelector('#newsletterForm');
        if (!newsletterForm) return;

        newsletterForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const emailInput = newsletterForm.querySelector('input[type="email"]');
            if (!emailInput) return;

            const email = emailInput.value;

            // Simple validation
            if (!email || !email.includes('@')) {
                alert('Please enter a valid email address.');
                return;
            }

            // In production, this would send to a server
            alert('Thank you for subscribing!');
            emailInput.value = '';
        });
    }

    // ========================================
    // PRODUCT IMAGE GALLERY/SLIDER
    // ========================================
    
    function initProductGallery() {
        const gallery = document.querySelector('[data-product-gallery]');
        if (!gallery) return;
        
        const images = gallery.querySelectorAll('[data-gallery-image]');
        const thumbs = gallery.querySelectorAll('[data-gallery-thumb]');
        const prevBtn = gallery.querySelector('[data-gallery-prev]');
        const nextBtn = gallery.querySelector('[data-gallery-next]');
        
        if (images.length <= 1) return; // No need for gallery with single image
        
        let currentIndex = 0;
        
        function showImage(index) {
            // Ensure index is within bounds
            if (index < 0) index = images.length - 1;
            if (index >= images.length) index = 0;
            
            currentIndex = index;
            
            // Update active image
            images.forEach((img, i) => {
                if (i === index) {
                    img.classList.add('is-active');
                } else {
                    img.classList.remove('is-active');
                }
            });
            
            // Update active thumbnail
            thumbs.forEach((thumb, i) => {
                if (i === index) {
                    thumb.classList.add('is-active');
                } else {
                    thumb.classList.remove('is-active');
                }
            });
        }
        
        // Previous button
        if (prevBtn) {
            prevBtn.addEventListener('click', () => {
                showImage(currentIndex - 1);
            });
        }
        
        // Next button
        if (nextBtn) {
            nextBtn.addEventListener('click', () => {
                showImage(currentIndex + 1);
            });
        }
        
        // Thumbnail clicks
        thumbs.forEach((thumb, index) => {
            thumb.addEventListener('click', () => {
                showImage(index);
            });
        });
        
        // Keyboard navigation - only when not in input fields
        document.addEventListener('keydown', (e) => {
            // Don't trigger if user is typing in an input, textarea, or select
            const activeElement = document.activeElement;
            if (activeElement && (
                activeElement.tagName === 'INPUT' ||
                activeElement.tagName === 'TEXTAREA' ||
                activeElement.tagName === 'SELECT'
            )) {
                return;
            }
            
            if (e.key === 'ArrowLeft') {
                showImage(currentIndex - 1);
            } else if (e.key === 'ArrowRight') {
                showImage(currentIndex + 1);
            }
        });
    }

    // ========================================
    // INITIALIZATION
    // ========================================
    
    document.addEventListener('DOMContentLoaded', () => {
        initProductSelectors();
        initGiftSet();
        initCheckoutForm();
        initBackInStock();
        initNewsletter();
        initProductGallery(); // Initialize image gallery/slider
        // Initialize category and fragrance descriptions
        initCategoryDescriptions();
        initFragranceDescriptions();
        // Sync cart from server session (this also updates the cart count display)
        syncCartOnLoad();
        // Initialize favorites functionality
        initFavorites();
        // Initialize account dropdown menu
        initAccountMenu();
    });

    // ========================================
    // FAVORITES FUNCTIONALITY
    // ========================================
    
    function initFavorites() {
        document.addEventListener('click', handleFavoriteClick);
    }
    
    async function handleFavoriteClick(e) {
        const btn = e.target.closest('.favorite-btn');
        if (!btn) return;
        
        e.preventDefault();
        e.stopPropagation();
        
        const productId = btn.dataset.productId;
        if (!productId) return;
        
        const url = '/ajax/favorites.php';
        
        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'toggle', productId: productId })
            });
            
            const data = await res.json();
            
            // Not logged in - redirect to account page
            if (data.error === 'not_logged_in') {
                const currentLang = getCurrentLanguage();
                window.location.href = '/account.php?from=favorites&lang=' + currentLang;
                return;
            }
            
            // Success - update UI
            if (data.success) {
                const isFavorite = data.isInFavorites;
                
                // Update all buttons for this product
                const allButtons = document.querySelectorAll(`.favorite-btn[data-product-id="${productId}"]`);
                allButtons.forEach(button => {
                    if (isFavorite) {
                        button.classList.add('favorite-btn--active');
                    } else {
                        button.classList.remove('favorite-btn--active');
                    }
                });
                
                // If on account page, remove card from DOM when unfavorited
                if (window.location.pathname.includes('account.php') && !isFavorite) {
                    const card = btn.closest('.catalog-card');
                    if (card) {
                        card.style.opacity = '0';
                        card.style.transform = 'scale(0.9)';
                        setTimeout(() => {
                            card.remove();
                            
                            // Check if favorites grid is now empty
                            const grid = document.querySelector('.favorites-grid');
                            if (grid && grid.children.length === 0) {
                                location.reload();
                            }
                        }, 300);
                    }
                }
            }
        } catch (err) {
            console.error('Favorites error:', err);
        }
    }
    
    function getCurrentLanguage() {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('lang') || 'en';
    }

    // ========================================
    // ACCOUNT DROPDOWN MENU
    // ========================================
    
    function initAccountMenu() {
        const trigger = document.querySelector('.account-menu__trigger');
        const menu = document.querySelector('.account-menu');
        
        if (!trigger || !menu) return;
        
        // Toggle menu on trigger click
        trigger.addEventListener('click', (e) => {
            e.stopPropagation();
            const isHidden = menu.hasAttribute('hidden');
            
            if (isHidden) {
                menu.removeAttribute('hidden');
                trigger.setAttribute('aria-expanded', 'true');
            } else {
                menu.setAttribute('hidden', 'hidden');
                trigger.setAttribute('aria-expanded', 'false');
            }
        });
        
        // Close menu when clicking outside
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.site-header__account')) {
                menu.setAttribute('hidden', 'hidden');
                trigger.setAttribute('aria-expanded', 'false');
            }
        });
        
        // Close menu when pressing Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !menu.hasAttribute('hidden')) {
                menu.setAttribute('hidden', 'hidden');
                trigger.setAttribute('aria-expanded', 'false');
            }
        });
    }

})();
