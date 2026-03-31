# Manual Testing Guide

All automated tests have passed. Below are the manual verification steps for visual confirmation.

## TASK 1: Admin Accessories - Optional Fragrance Selector

### Test 1A: Admin Form - Fragrance Selector Disabled
1. Navigate to: `admin/accessories.php`
2. Click "Edit" on any existing accessory OR create new accessory
3. **Uncheck** "Enable fragrance selector for this product"
4. Observe:
   - ✓ Allowed Fragrances select should be disabled (opacity 0.5)
   - ✓ The "*" required indicator should disappear
   - ✓ You can save without selecting any fragrances
5. Click "Save Accessory"
6. Expected: Success message, no validation error about missing fragrances

### Test 1B: Admin Form - Fragrance Selector Enabled
1. Navigate to: `admin/accessories.php`
2. Edit an accessory
3. **Check** "Enable fragrance selector for this product"
4. Observe:
   - ✓ Allowed Fragrances select should be enabled (opacity 1)
   - ✓ The "*" required indicator should appear
5. Try to save WITHOUT selecting fragrances
6. Expected: Validation error: "When fragrance selector is enabled, you must select at least one fragrance."
7. Select at least one fragrance (including "Guggul and Louban" which should be in the list)
8. Click "Save Accessory"
9. Expected: Success message

### Test 1C: Frontend - Fragrance Selector Hidden When Disabled
1. Save an accessory with `has_fragrance_selector = false`
2. Navigate to: `product.php?id=[accessory_id]`
3. Expected: No fragrance dropdown should appear on the page
4. Check page source: should have `<input type="hidden" data-fragrance-select value="none">`

### Test 1D: Frontend - Fragrance Selector Shown When Enabled
1. Save an accessory with `has_fragrance_selector = true` and some allowed fragrances
2. Navigate to: `product.php?id=[accessory_id]`
3. Expected: Fragrance dropdown should appear with selected fragrances listed
4. "Guggul and Louban" should be visible if it's in allowed_fragrances

---

## TASK 2: Gift Sets Page - Full Russian Translation

### Test 2A: Russian Language
1. Navigate to: `gift-sets.php?lang=ru`
2. Verify ALL visible text is in Russian:
   - ✓ Page title: "Создайте свой подарочный набор"
   - ✓ Subtitle: "Комбинируйте ваши любимые продукты со скидкой 5%"
   - ✓ Slot labels: "Слот 1", "Слот 2", "Слот 3"
   - ✓ Select category: "Выберите категорию"
   - ✓ Volume label: "Объём"
   - ✓ Fragrance label: "Аромат"
   - ✓ Total price: "Итоговая цена"
   - ✓ Discount text: "Скидка 5% на подарочный набор"
   - ✓ Button: "Добавить набор в корзину"
3. Expected: **NO English strings visible**

### Test 2B: English Language (Regression)
1. Navigate to: `gift-sets.php?lang=en`
2. Verify all visible text is in English:
   - ✓ Page title: "Create Your Gift Set"
   - ✓ Subtitle: "Combine your favorite products with a 5% discount"
   - ✓ Button: "Add gift set to cart"
3. Expected: No broken translations

### Test 2C: Other Languages
1. Test `gift-sets.php?lang=de` (German)
2. Test `gift-sets.php?lang=fr` (French)
3. Test `gift-sets.php?lang=it` (Italian)
4. Test `gift-sets.php?lang=ukr` (Ukrainian)
5. Expected: All should show proper translations, no English fallbacks

---

## TASK 3: Gift Set Cart Price - No More CHF 0.00 or Duplicates

### Test 3A: Add Gift Set - Correct Price
1. Navigate to: `gift-sets.php`
2. Select products for all 3 slots:
   - Slot 1: Aroma Diffuser, 125ml, any fragrance
   - Slot 2: Scented Candle, 160ml, any fragrance
   - Slot 3: Home Perfume, 10ml, any fragrance
3. Observe the "Total price" calculation (e.g., CHF 59.56 with 5% discount applied)
4. Click "Add gift set to cart" **once**
5. Expected:
   - ✓ Button text changes to "Adding..." briefly
   - ✓ Alert message appears in **correct language**:
     - EN: "Gift set added to cart!"
     - RU: "Подарочный набор добавлен в корзину!"
   - ✓ Redirected to cart.php

### Test 3B: Cart Display - Correct Price
1. On cart.php, check the gift set line item:
   - ✓ Name: "Custom Gift Set"
   - ✓ Price: **Should match the computed price** (e.g., CHF 59.56), NOT CHF 0.00
   - ✓ Quantity: 1
   - ✓ Total: Same as price
2. Check cart subtotal and total
3. Expected: All calculations correct, no CHF 0.00

### Test 3C: No Duplicates
1. On cart.php, verify there is **only ONE** gift set line item
2. If you click "Add gift set to cart" button twice rapidly (before redirect):
   - Expected: Button is disabled after first click, preventing duplicate submission
3. Check cart: Should still have only ONE gift set item

### Test 3D: Checkout Display
1. Proceed to checkout.php
2. Verify:
   - ✓ Gift set appears in order summary
   - ✓ Price is correct (not CHF 0.00)
   - ✓ Subtotal includes gift set price correctly
   - ✓ Grand total is correct

### Test 3E: Gift Set Persistence
1. Add gift set to cart
2. Close browser or clear localStorage
3. Reopen and go to cart.php
4. Expected: Gift set should still be in cart with correct price (server-side persistence)

---

## Summary

### Automated Tests: ✓ ALL PASSED (18/18)

### Manual Tests Required:
- **TASK 1:** 4 test scenarios
- **TASK 2:** 3 test scenarios
- **TASK 3:** 5 test scenarios

**Total:** 12 manual verification steps

---

## Pass/Fail Checklist

After completing manual tests, mark each:

- [ ] Test 1A: Admin fragrance selector disabled
- [ ] Test 1B: Admin fragrance selector enabled
- [ ] Test 1C: Frontend fragrance selector hidden
- [ ] Test 1D: Frontend fragrance selector shown
- [ ] Test 2A: Russian language fully translated
- [ ] Test 2B: English language works (regression)
- [ ] Test 2C: Other languages work
- [ ] Test 3A: Gift set added with correct price
- [ ] Test 3B: Cart displays correct price (not CHF 0.00)
- [ ] Test 3C: No duplicate gift sets
- [ ] Test 3D: Checkout displays correct totals
- [ ] Test 3E: Gift set persists correctly

---

## Known Working Features (No Changes Made)

The following should continue to work as before:
- ✓ Stock management
- ✓ Regular product add to cart
- ✓ Checkout flow for regular products
- ✓ Account functionality
- ✓ Favorites
- ✓ I18N for other pages
- ✓ Admin authentication
- ✓ Volume-based pricing for accessories (unrelated to fragrance selector)
