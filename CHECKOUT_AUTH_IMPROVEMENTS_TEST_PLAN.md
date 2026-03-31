# Checkout & Authentication Improvements - Test Plan

## Overview
This document outlines the implementation and testing procedures for three major improvements to the NicheHome checkout and authentication system:
- **Task A**: Dynamic checkout field requirements based on guest/auto-registration mode
- **Task B**: Forgot password flow with email verification
- **Task C**: Registration consent checkbox

## Implementation Summary

### Files Modified
1. **checkout.php** - Dynamic field requirements and asterisk display
2. **account.php** - Forgot password flow and registration consent
3. **includes/helpers.php** - Password reset helper functions
4. **data/i18n/ui_*.json** (en, de, fr, it, ru, ukr) - All new translations

### New Functions Added to helpers.php
- `generateVerificationCode()` - Generates 6-digit codes
- `createPasswordResetRequest($email)` - Creates reset request with rate limiting
- `verifyAndResetPassword($email, $code, $newPassword)` - Verifies code and updates password

---

## TASK A: Dynamic Checkout Field Requirements

### What Changed
1. **Required field asterisks (*) are now dynamic**:
   - Guest checkout with pickup: Only name, phone, and payment required
   - Guest checkout with delivery OR auto-registration: Email and address also required
   
2. **JavaScript automatically shows/hides asterisks** based on:
   - Pickup checkbox state
   - Register account checkbox state
   
3. **Backend validation matches frontend requirements**

### Testing Steps

#### Test 1: Guest Checkout with Pickup (Minimal Fields)
1. Add items to cart
2. Go to checkout page
3. Check "Pickup in branch" checkbox
4. **Verify**: Email and address fields show NO asterisk (*)
5. Fill only: First name, Last name, Phone
6. Select a pickup branch
7. Select payment method (Cash)
8. Check consent checkbox
9. Click "Place Order"
10. **Expected**: Order succeeds without requiring email/address

#### Test 2: Guest Checkout with Delivery (Full Address Required)
1. Add items to cart
2. Go to checkout
3. **Verify**: Email and address fields show asterisk (*)
4. Try to submit with only name and phone
5. **Expected**: Validation errors for email and address
6. Fill all required fields including email and address
7. Check consent checkbox
8. Click "Place Order"
9. **Expected**: Order succeeds

#### Test 3: Guest Checkout with Pickup + Auto-Registration
1. Add items to cart
2. Go to checkout
3. Check "Pickup in branch" checkbox
4. **Verify**: Initially email/address have NO asterisk
5. Check "Register an account automatically" checkbox
6. **Verify**: Asterisks appear on email/address fields
7. Password field appears
8. Try submitting with only name, phone
9. **Expected**: Validation errors for email, address, password
10. Fill all fields including password (min 8 chars)
11. Check consent checkbox
12. Click "Place Order"
13. **Expected**: Order succeeds AND account is created

#### Test 4: Auto-Registration Email Already Exists
1. Go to checkout as guest
2. Check "Register an account automatically"
3. Enter email that already has an account
4. Fill other required fields
5. Click "Place Order"
6. **Expected**: Error message "An account with this email already exists"

---

## TASK B: Forgot Password Flow

### What Changed
1. **"Forgot password?" link added to login form**
2. **Two-step reset process**:
   - Step 1: Enter email, receive 6-digit code via email
   - Step 2: Enter code + new password to reset
3. **Security features**:
   - Rate limiting: 60 seconds between code requests
   - Code expiry: 15 minutes
   - Attempt limit: 5 attempts per code
   - Codes are hashed in storage

### Testing Steps

#### Test 1: Request Reset Code (Valid Email)
1. Go to account.php
2. Click "Forgot password?" link
3. Enter a valid customer email
4. Click "Send Reset Code"
5. **Expected**: 
   - Success message: "A verification code has been sent to your email address"
   - Check email inbox for 6-digit code
   - Form changes to password reset form

#### Test 2: Request Reset Code (Invalid Email)
1. Go to account.php
2. Click "Forgot password?"
3. Enter email that doesn't exist
4. Click "Send Reset Code"
5. **Expected**: Error message "Invalid email address"

#### Test 3: Rate Limiting
1. Request reset code for an email
2. Immediately try to request another code for same email
3. **Expected**: Error "A code was sent recently. Please wait before requesting another."
4. Wait 60+ seconds
5. Try again
6. **Expected**: New code sent successfully

#### Test 4: Reset Password with Valid Code
1. Request reset code
2. Get code from email (check data/email_debug.json if emails not configured)
3. Enter the 6-digit code
4. Enter new password (min 6 characters)
5. Confirm new password
6. Click "Reset Password"
7. **Expected**: Success message "Your password has been reset successfully"
8. Try logging in with new password
9. **Expected**: Login succeeds

#### Test 5: Reset Password with Invalid Code
1. Request reset code
2. Enter wrong 6-digit code (e.g., 000000)
3. Enter new password
4. Click "Reset Password"
5. **Expected**: Error "Invalid or expired verification code"

#### Test 6: Reset Password with Expired Code
1. Request reset code
2. Wait 16+ minutes
3. Enter the code (use correct code from email)
4. **Expected**: Error "Invalid or expired verification code"

#### Test 7: Too Many Attempts
1. Request reset code
2. Enter wrong code 5 times
3. **Expected**: After 5th attempt, error "Too many attempts. Please request a new code"

#### Test 8: Back to Login Navigation
1. Click "Forgot password?" from login form
2. Click "Back to Login" link
3. **Expected**: Returns to login form

---

## TASK C: Registration Consent Checkbox

### What Changed
1. **Consent checkbox added to registration form** with:
   - Links to Terms & Conditions
   - Links to Privacy Policy
   - Required checkbox (server-side validation)
   - Custom validation message

2. **Server-side validation**: Registration fails if checkbox not checked

### Testing Steps

#### Test 1: Register Without Consent
1. Go to account.php
2. Click "Register" tab
3. Fill email and passwords
4. Do NOT check consent checkbox
5. Try to submit
6. **Expected**: Browser validation message appears (custom tooltip)

#### Test 2: Register With Consent
1. Go to account.php
2. Click "Register" tab
3. Fill email and passwords
4. Check consent checkbox
5. Click "Register"
6. **Expected**: Account created successfully, auto-logged in

#### Test 3: Terms & Privacy Links Work
1. Go to registration form
2. Click "Terms & Conditions" link
3. **Expected**: Opens terms-and-conditions.php in new tab
4. Go back, click "Privacy Policy" link
5. **Expected**: Opens privacy-policy.php in new tab

#### Test 4: Server-Side Validation
1. Use browser dev tools to remove "required" attribute from checkbox
2. Try to submit form without checking
3. **Expected**: Server-side validation catches it with error message

---

## Multi-Language Testing

All features must work in all supported languages:
- English (en)
- German (de)
- French (fr)
- Italian (it)
- Russian (ru)
- Ukrainian (ukr)

### Test Multi-Language
1. For each language, change site language
2. Verify all new text strings are translated:
   - Forgot password flow messages
   - Consent checkbox text
   - Form labels and buttons
   - Error messages
   - Email content (password reset email)

---

## Email Testing

### If Email System is Configured
1. Check inbox for password reset emails
2. Verify email contains 6-digit code
3. Verify email is properly formatted (HTML and text versions)
4. Verify email subject is translated

### If Email System is NOT Configured
1. Check `data/email_debug.json` for logged emails
2. Extract verification code from debug log
3. Use code to test reset flow

---

## Security Verification

### Checklist
- [x] Passwords hashed with `password_hash()` (PASSWORD_DEFAULT)
- [x] Verification codes hashed in storage (not plain text)
- [x] Rate limiting implemented (60 seconds between requests)
- [x] Code expiry implemented (15 minutes)
- [x] Attempt limiting implemented (5 attempts)
- [x] Server-side validation for all forms
- [x] Email validation using `isValidEmail()` function
- [x] Consent checkbox validated server-side
- [x] No user enumeration (failed login doesn't reveal if email exists)

---

## Mobile/Tablet Testing

All features must work on:
- Desktop browsers (Chrome, Firefox, Safari, Edge)
- Mobile Safari (iOS)
- Mobile Chrome (Android)
- Tablet devices

### Mobile-Specific Tests
1. Test dynamic asterisks visibility on small screens
2. Test forgot password form usability
3. Test consent checkbox tap area is adequate
4. Verify all links open correctly
5. Test form validation messages are readable

---

## Edge Cases

### Test Edge Cases
1. **Long email addresses**: Test with 50+ character emails
2. **Special characters in passwords**: Test with symbols, unicode
3. **Concurrent requests**: Two users resetting same account simultaneously
4. **Session timeout**: Request code, logout, try to reset
5. **Browser refresh**: Refresh during checkout, verify state preserved
6. **Back button**: Use back button during forgot password flow

---

## Rollback Plan

If issues are discovered:
1. All changes are in a feature branch
2. Can revert individual commits
3. No database schema changes (uses JSON storage)
4. No breaking changes to existing functionality

---

## Known Limitations

1. **Consent checkbox**: Only added to registration form, not to profile update (as per requirements)
2. **Password strength**: Minimum 6 characters for existing accounts, 8 for new via checkout (can be standardized if needed)
3. **Email delivery**: Depends on email system configuration; fallback to mail() function
4. **Verification codes**: No SMS option, email only

---

## Post-Deployment Monitoring

After deployment, monitor:
1. Password reset request frequency
2. Failed verification code attempts
3. Email delivery success rate
4. Account creation success rate
5. Checkout abandonment at consent checkbox step

---

## Summary

All three tasks have been implemented with:
- ✅ Full multilingual support (6 languages)
- ✅ Security best practices (hashing, rate limiting, validation)
- ✅ User-friendly error messages
- ✅ Mobile-responsive design
- ✅ Server-side and client-side validation
- ✅ No breaking changes to existing functionality

The implementation is minimal, focused, and follows existing code conventions.
