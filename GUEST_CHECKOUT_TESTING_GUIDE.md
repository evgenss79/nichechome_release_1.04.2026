# Guest Checkout with Optional Registration - Testing Guide

## Overview
This feature allows guest users to complete checkout with minimal required fields for pickup orders, and optionally create an account during checkout.

## Test Scenarios

### Scenario 1: Guest + Pickup + No Registration
**Expected Behavior:** Order placed successfully without creating an account.

**Steps:**
1. Add a product to cart as a guest (not logged in)
2. Go to checkout
3. Check "Pickup in branch" checkbox
4. Select a branch
5. Fill in ONLY:
   - First name
   - Last name
   - Phone number
6. Leave "Register an account automatically" checkbox **UNCHECKED**
7. Check "I agree to Terms and Conditions" checkbox
8. Click "Place Order"

**Expected Result:**
- Order is placed successfully
- NO user account is created
- User remains as guest after order completion
- Order confirmation shown

---

### Scenario 2: Guest + Pickup + Registration Checked
**Expected Behavior:** Order placed successfully AND user account is created and logged in.

**Steps:**
1. Add a product to cart as a guest (not logged in)
2. Go to checkout
3. Check "Pickup in branch" checkbox
4. Select a branch
5. **Check "Register an account automatically" checkbox**
6. Verify password field appears
7. Fill in ALL required fields:
   - First name
   - Last name
   - Email (must be valid and unique)
   - Phone number
   - Street
   - House number
   - ZIP code
   - City
   - Country
   - Password (minimum 8 characters)
8. Check "I agree to Terms and Conditions" checkbox
9. Click "Place Order"

**Expected Result:**
- Order is placed successfully
- User account is created with the provided information
- User is automatically logged in
- Order is linked to the new user account
- Order confirmation shown
- After completion, user icon in header shows logged-in state

---

### Scenario 3: Guest + Delivery + No Registration
**Expected Behavior:** Current behavior preserved - requires email and address fields.

**Steps:**
1. Add a product to cart as a guest (not logged in)
2. Go to checkout
3. Do NOT check "Pickup in branch" checkbox
4. Leave "Register an account automatically" checkbox **UNCHECKED**
5. Fill in required fields:
   - First name
   - Last name
   - Email
   - Phone number
   - Full address fields (street, house number, ZIP, city, country)
6. Check "I agree to Terms and Conditions" checkbox
7. Click "Place Order"

**Expected Result:**
- Order is placed successfully
- NO user account is created
- User remains as guest
- Order confirmation shown

---

### Scenario 4: Guest + Delivery + Registration Checked
**Expected Behavior:** Order placed and account created with all information.

**Steps:**
1. Add a product to cart as a guest (not logged in)
2. Go to checkout
3. Do NOT check "Pickup in branch" checkbox
4. **Check "Register an account automatically" checkbox**
5. Verify password field appears
6. Fill in ALL required fields:
   - First name
   - Last name
   - Email (must be valid and unique)
   - Phone number
   - Full address fields
   - Password (minimum 8 characters)
7. Check "I agree to Terms and Conditions" checkbox
8. Click "Place Order"

**Expected Result:**
- Order is placed successfully
- User account is created
- User is automatically logged in
- Order is linked to the user account

---

### Scenario 5: Registration with Existing Email
**Expected Behavior:** Error shown, order NOT placed.

**Steps:**
1. Ensure a user with email "test@example.com" already exists
2. Add a product to cart as a guest
3. Go to checkout
4. Check "Register an account automatically" checkbox
5. Fill in all required fields
6. Use email: test@example.com
7. Check "I agree to Terms and Conditions" checkbox
8. Click "Place Order"

**Expected Result:**
- Error message: "An account with this email already exists"
- Order is NOT placed
- Form remains filled (except password)
- User can correct email and try again

---

### Scenario 6: Registration with Short Password
**Expected Behavior:** Error shown, order NOT placed.

**Steps:**
1. Add a product to cart as a guest
2. Go to checkout
3. Check "Register an account automatically" checkbox
4. Fill in all required fields
5. Enter password with less than 8 characters (e.g., "pass123")
6. Check "I agree to Terms and Conditions" checkbox
7. Click "Place Order"

**Expected Result:**
- Error message: "Password must be at least 8 characters"
- Order is NOT placed
- User can correct password and try again

---

### Scenario 7: Logged-in User Checkout
**Expected Behavior:** Registration checkbox is NOT shown.

**Steps:**
1. Log in as an existing user
2. Add a product to cart
3. Go to checkout

**Expected Result:**
- Registration checkbox "Register an account automatically" is NOT visible
- Checkout form is pre-filled with user information
- Normal checkout flow for logged-in users

---

### Scenario 8: Dynamic Field Requirements - JavaScript
**Expected Behavior:** Field requirements change dynamically based on user selections.

**Steps:**
1. Add a product to cart as a guest
2. Go to checkout
3. Initially (without pickup checked):
   - Email field should have `required` attribute
   - Address fields should have `required` attribute
4. Check "Pickup in branch" checkbox:
   - Email field `required` attribute should be removed
   - Address fields `required` attribute should be removed
5. Now check "Register an account automatically":
   - Email field `required` attribute should be added back
   - Address fields `required` attribute should be added back
   - Password field should appear with `required` attribute
6. Uncheck "Register an account automatically":
   - Password field should disappear
   - Email and address fields `required` attribute should be removed again

**Expected Result:**
- Field requirements update dynamically without page reload
- Browser validation respects the dynamic requirements

---

## UI Verification Checklist

- [ ] Registration checkbox is visible for guests
- [ ] Registration checkbox is hidden for logged-in users
- [ ] Registration checkbox is unchecked by default
- [ ] Password field appears when registration checkbox is checked
- [ ] Password field disappears when registration checkbox is unchecked
- [ ] Password field shows hint "(Password must be at least 8 characters)"
- [ ] All text is properly translated in all supported languages
- [ ] Form styling is consistent with existing design
- [ ] No layout shifts or visual glitches when toggling checkbox

---

## Server-Side Validation Checklist

- [ ] Guest + Pickup + No Registration: Only validates name and phone
- [ ] Guest + Pickup + Registration: Validates all fields including email, address, password
- [ ] Guest + Delivery: Always validates email and address
- [ ] Email format validation works
- [ ] Email uniqueness check works
- [ ] Password minimum length (8 chars) validation works
- [ ] Terms and Conditions checkbox validation works
- [ ] Validation errors are displayed clearly

---

## Security Checklist

- [ ] Password is hashed using `password_hash()` before storage
- [ ] Password is never stored in plain text
- [ ] Password is not included in order data
- [ ] Password is not logged
- [ ] Email addresses are not logged (only customer IDs)
- [ ] All user inputs are sanitized
- [ ] CSRF protection is maintained (if present)
- [ ] Session handling is secure

---

## Data Integrity Checklist

- [ ] Order is created correctly without account
- [ ] Order is created correctly with account
- [ ] Order is linked to customer_id when account is created
- [ ] Customer account is created with all provided information
- [ ] Customer account has secure password hash
- [ ] User is logged in after account creation
- [ ] Cart is cleared after successful order

---

## Modified Files

1. **checkout.php** - Main implementation
   - Server-side validation logic
   - Account creation logic
   - UI with registration checkbox and password field
   - JavaScript for dynamic field requirements

2. **data/i18n/ui_en.json** - English translations
3. **data/i18n/ui_de.json** - German translations
4. **data/i18n/ui_fr.json** - French translations
5. **data/i18n/ui_it.json** - Italian translations
6. **data/i18n/ui_ru.json** - Russian translations
7. **data/i18n/ui_ukr.json** - Ukrainian translations

---

## Debug Mode (Optional)

To enable debug logging and see which validation path was used, you can add `?debug=1` to the checkout URL (if implemented). This will show:
- Which fields are being validated
- Whether pickup mode is active
- Whether registration mode is active
- Validation results for each field

---

## Known Limitations

1. The pickup checkbox may not be visible if there are no active branches configured in the system
2. Address fields for pickup orders without registration will be empty in the customer record
3. Client-side validation is for UX only - server-side validation is authoritative

---

## Rollback Instructions

If issues are found and rollback is needed:

1. Revert the commit with the feature changes
2. No database migrations or schema changes were made
3. No existing data will be affected
4. Existing orders and customers remain unchanged
