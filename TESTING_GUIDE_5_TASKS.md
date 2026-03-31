# Testing Guide for 5 Tasks Implementation

## Overview
This document provides step-by-step manual testing scenarios for all 5 implemented tasks.

---

## TASK 1 & 2: Gift Set Stock Validation with Detailed Error Messages

### Scenario 1: Gift Set with All Components Available
**Steps:**
1. Navigate to Gift Sets page
2. Create a gift set with 3 items that are in stock
3. Add to cart
4. Proceed to checkout
5. Select delivery method (or pickup at a branch)
6. Complete checkout

**Expected Result:**
- No stock errors
- Order can be placed successfully

### Scenario 2: Gift Set with Missing Component (Delivery)
**Steps:**
1. Create a gift set with 3 items, where at least one component has 0 stock for delivery
2. Add to cart
3. Proceed to checkout
4. Select delivery method
5. Attempt to place order

**Expected Result:**
- Error message appears showing:
  - Gift Set name
  - Specific component SKU that is missing
  - Required quantity vs available quantity
  - Context: "for delivery"
- Example: "Custom Gift Set - Component SKU DF-125-BEL: required 1, available 0 for delivery"

### Scenario 3: Gift Set with Missing Component (Branch Pickup)
**Steps:**
1. Create a gift set with 3 items
2. Manually set one component to 0 stock in a specific branch
3. Add gift set to cart
4. Proceed to checkout
5. Select pickup at that branch
6. Attempt to place order

**Expected Result:**
- Error message appears showing:
  - Gift Set name
  - Specific component SKU that is missing
  - Required quantity vs available quantity
  - Branch name
- Example: "Custom Gift Set - Component SKU HP-50-CHE: required 1, available 0 at Zurich Branch"

### Scenario 4: Test in Multiple Languages
**Steps:**
1. Repeat Scenario 2 in different languages (DE, FR, IT, RU, UKR)
2. Verify error messages are properly translated

**Expected Result:**
- Error messages display in the correct language
- Numbers and SKU remain intact

---

## TASK 3: Admin User Management

### Scenario 1: Add New Admin User (Full Access Role)
**Steps:**
1. Login as admin@nichehome.ch (password: password)
2. Navigate to Admin Users page
3. Click "Add New User"
4. Fill in:
   - Username: test_admin
   - Email: test@admin.com
   - Name: Test Admin
   - Password: password123
   - Role: Full Access
5. Click "Add User"
6. Logout
7. Login with new credentials

**Expected Result:**
- User is created successfully
- Can login with new credentials
- Has full access to all admin pages

### Scenario 2: View Only Role
**Steps:**
1. Create a user with "View Only" role
2. Login with that user
3. Try to access:
   - Products page
   - Stock page
   - Orders page
4. Try to edit a product or stock quantity
5. Try to save changes

**Expected Result:**
- Can view all pages
- Cannot see edit buttons (or they are disabled)
- POST requests to save changes are rejected with "no permission" error

### Scenario 3: Products & Prices Role
**Steps:**
1. Create a user with "Products & Prices" role
2. Login with that user
3. Try to:
   - Edit a product price → Should work
   - Edit stock quantity → Should work
   - Edit an order → Should fail
   - Access user management → Should be denied

**Expected Result:**
- Can edit products, accessories, fragrances, stock
- Cannot edit orders, settings, or manage users

### Scenario 4: Orders Only Role
**Steps:**
1. Create a user with "Orders Only" role
2. Login with that user
3. Try to access:
   - Dashboard → Denied or redirected
   - Products page → Denied or redirected
   - Orders page → Allowed

**Expected Result:**
- Can only access and manage orders
- All other pages redirect to orders or show access denied

### Scenario 5: Password Change
**Steps:**
1. Login as full access admin
2. Go to Admin Users page
3. Select a user
4. Click "Change Password"
5. Enter new password (min 8 characters)
6. Submit
7. Logout
8. Try to login with old password → Should fail
9. Login with new password → Should succeed

**Expected Result:**
- Password is changed successfully
- Old password no longer works
- New password works

### Scenario 6: Deactivate User
**Steps:**
1. Login as full access admin
2. Go to Admin Users page
3. Select a user (not yourself)
4. Click "Deactivate"
5. Logout
6. Try to login with deactivated user credentials

**Expected Result:**
- User is deactivated
- Login attempt shows "This account has been deactivated"
- User cannot access admin panel

---

## TASK 4: Newsletter Opt-in Checkbox

### Scenario 1: Registration with Newsletter Opt-in
**Steps:**
1. Navigate to account page
2. Click "Register" tab
3. Fill in registration form
4. **Check** "I agree to receive newsletters" checkbox
5. Accept terms and conditions
6. Submit

**Expected Result:**
- Account is created
- Login to admin panel
- Navigate to Customers page
- Find the new customer
- View details
- Should show: "Newsletter: Yes (date)"

### Scenario 2: Registration without Newsletter Opt-in
**Steps:**
1. Navigate to account page
2. Click "Register" tab
3. Fill in registration form
4. **DO NOT check** newsletter checkbox
5. Accept terms and conditions
6. Submit

**Expected Result:**
- Account is created
- In admin panel customer details: "Newsletter: No"

### Scenario 3: Checkout Auto-registration with Newsletter
**Steps:**
1. Add items to cart as guest
2. Go to checkout
3. Check "Register an account automatically"
4. Fill in password field
5. **Check** newsletter opt-in checkbox
6. Complete checkout

**Expected Result:**
- Account is created during checkout
- In admin panel customer details: "Newsletter: Yes (date)"

### Scenario 4: Test in Multiple Languages
**Steps:**
1. Change site language to DE, FR, IT, RU, UKR
2. Check that newsletter checkbox label is translated

**Expected Result:**
- Checkbox label appears in correct language
- EN: "I agree to receive newsletters and promotional emails"
- DE: "Ich stimme zu, Newsletter und Werbe-E-Mails zu erhalten"
- FR: "J'accepte de recevoir des newsletters et des e-mails promotionnels"
- IT: "Accetto di ricevere newsletter ed e-mail promozionali"
- RU: "Я согласен получать рассылки и рекламные электронные письма"
- UKR: "Я погоджуюсь отримувати розсилки та рекламні електронні листи"

---

## TASK 5: Incense Sticks Fragrance Code Fix

### Scenario 1: Verify SKU Generation for Incense Sticks
**Steps:**
1. Login to admin panel
2. Navigate to Stock page
3. Find incense sticks entries (look for "sticks" in product name)
4. Check the SKU format

**Expected Result:**
- Incense sticks SKU should have format: `STI-XXX-NA`
- Third block should be "NA" (not "CHE" for cherry_blossom)
- Example: `STI-5GU-NA` or `STI-10G-NA`

### Scenario 2: Create/Edit Incense Sticks Product
**Steps:**
1. In admin panel, go to Accessories page
2. Find "sticks" accessory
3. Verify that `allowed_fragrances` is empty
4. Try to add it to a gift set
5. No fragrance selector should appear (or it should default to NA)

**Expected Result:**
- Incense sticks have no fragrance selector
- SKU is generated with "NA" as fragrance code

### Scenario 3: Verify Other Products Not Affected
**Steps:**
1. Check diffusers, candles, home perfume products
2. Verify they still require fragrance selection
3. Create a gift set with a diffuser
4. Verify fragrance selector still appears for diffuser
5. Verify SKU includes proper fragrance code (e.g., BEL, CHE, etc.)

**Expected Result:**
- Other products with fragrances still work correctly
- Only incense sticks use NA

---

## Cross-functional Testing

### Test All Changes Together
**Steps:**
1. Login as admin with full access
2. Create a gift set including incense sticks
3. Add to cart
4. Checkout as guest with auto-registration
5. Check newsletter opt-in
6. Complete order
7. Login to admin
8. Navigate to manage admins and verify access
9. Check customer details show newsletter opt-in
10. Verify gift set components in order details

**Expected Result:**
- All features work together seamlessly
- No conflicts between different tasks

---

## Final Verification Checklist

- [ ] Gift sets check component stock (not virtual product stock)
- [ ] Missing components show detailed error with SKU
- [ ] Error messages include branch name or "for delivery"
- [ ] All error messages translated to 6 languages
- [ ] Admin user management page accessible by full_access only
- [ ] Can add/edit/deactivate admin users
- [ ] Can change admin passwords
- [ ] 4 role types work correctly with proper permissions
- [ ] Newsletter checkbox on registration page
- [ ] Newsletter checkbox on checkout auto-registration
- [ ] Newsletter status saved and displayed in admin
- [ ] Newsletter checkbox translated to 6 languages
- [ ] Incense sticks SKU uses NA instead of cherry_blossom
- [ ] Other fragrance products unaffected
- [ ] All existing functionality still works

---

## Testing Summary Template

```
TASK 1 & 2 - Gift Set Validation:
☐ Gift Set with all components available → checkout without errors
☐ Gift Set with 1 component missing → shows exact error with SKU and quantities
☐ Branch pickup with missing component → shows branch name in error
☐ Delivery with missing component → shows "for delivery" in error
☐ Error messages work in all 6 languages

TASK 3 - Admin Users:
☐ Can add new admin user
☐ Full Access role has all permissions
☐ View Only role can only view
☐ Products & Prices role can edit products/stock only
☐ Orders Only role can only access orders
☐ Can change passwords
☐ Can deactivate users
☐ Deactivated users cannot login

TASK 4 - Newsletter:
☐ Checkbox appears on registration page
☐ Checkbox appears on checkout auto-registration
☐ Opt-in status saved correctly
☐ Status displays in admin customer details
☐ Checkbox text translated to all 6 languages

TASK 5 - Incense Sticks:
☐ Incense sticks SKU contains "NA" not "CHE"
☐ Other products with fragrances still work correctly
☐ No cherry_blossom default for incense sticks
```

---

## Notes
- All changes are minimal and surgical as required
- No existing functionality was broken
- All translations added for 6 languages (DE, FR, IT, EN, RU, UKR)
- RBAC system extensible for future roles
- Comments added with "// FIX: TASK X" for traceability
