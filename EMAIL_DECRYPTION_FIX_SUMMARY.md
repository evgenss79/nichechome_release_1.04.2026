# Email System Decryption Fix - Implementation Summary

## Problem Statement

The NicheHome.ch email system was experiencing "Decryption failed: invalid key or corrupted data" errors that prevented:
- Testing SMTP connections
- Sending test emails
- Sometimes causing HTTP 500 errors on `/admin/email.php`

## Root Cause

The encryption key in `/config/email_secret.php` contained buggy code that generated a **random new key on each request**:

```php
// OLD BUGGY CODE (lines 15-18):
if (!defined('EMAIL_SECRET_KEY')) {
    define('EMAIL_SECRET_KEY', base64_encode(random_bytes(32)));
    return EMAIL_SECRET_KEY;
}
```

This meant:
1. Password encrypted with key A during save
2. Next request uses key B (randomly generated)
3. Decryption fails because key B ≠ key A
4. Error: "Decryption failed: invalid key or corrupted data"

## Solution Implemented

### 1. Stable Encryption Key (Phase 1)

**File: `config/email_secret.php`**

The encryption key is now persistent and stable across requests:

```php
// NEW CODE: Persistent key storage
$keyStorageFile = __DIR__ . '/.email_encryption_key';

// 1. Try to load existing key from file
if (file_exists($keyStorageFile)) {
    return trim(file_get_contents($keyStorageFile));
}

// 2. Try environment variable
if (!empty(getenv('EMAIL_ENCRYPTION_KEY'))) {
    return getenv('EMAIL_ENCRYPTION_KEY');
}

// 3. Generate new key only once and save it
if (!file_exists($keyStorageFile)) {
    $newKey = base64_encode(random_bytes(32));
    file_put_contents($keyStorageFile, $newKey, LOCK_EX);
    chmod($keyStorageFile, 0600); // Secure permissions
    return $newKey;
}
```

**Benefits:**
- ✓ Key generated once and persists in `config/.email_encryption_key`
- ✓ Secure file permissions (0600 - readable only by owner)
- ✓ Supports environment variable for Docker/containers
- ✓ Automatic generation on first use
- ✓ No manual setup required

### 2. Graceful Recovery (Phase 2)

**Files: `includes/email/crypto.php`, `admin/email.php`**

Added detection and recovery when password cannot be decrypted:

**New function: `canDecryptEmailPassword()`**
```php
function canDecryptEmailPassword(string $cipher): bool {
    try {
        decryptEmailPassword($cipher);
        return true;
    } catch (Exception $e) {
        return false;
    }
}
```

**Admin UI changes:**
- On page load, checks if stored password can be decrypted
- If not, shows prominent **red warning** at top of page
- Password field highlighted with **red border**
- Placeholder text: "Password cannot be decrypted - enter new password"
- User simply re-enters password and saves → problem fixed

### 3. Improved Error Handling (Phase 3)

**File: `includes/email/mailer.php`**

Enhanced error handling in all email functions:

```php
// In sendEmailViaSMTP()
try {
    $password = decryptEmailPassword($smtp['password_encrypted']);
} catch (\Exception $e) {
    error_log("Email decryption failure: " . $e->getMessage());
    logEmailEvent($eventType, $to, false, 'Password decryption failed');
    return [
        'success' => false, 
        'error' => 'Failed to decrypt SMTP password. Please re-enter your SMTP password.',
        'needs_password_reset' => true
    ];
}
```

**Benefits:**
- ✓ Catches all exceptions (not just PHPMailer exceptions)
- ✓ Returns structured error response instead of crashing
- ✓ Logs errors server-side without exposing secrets
- ✓ Provides clear user-facing error messages
- ✓ No HTTP 500 errors - always graceful degradation

### 4. Enhanced Logging (Phase 4)

**File: `includes/email/log.php`**

Logging system already had:
- ✓ Automatic log rotation (5MB limit)
- ✓ Password masking in all logs
- ✓ Creates `/logs/email.log` automatically
- ✓ Keeps last 10 archived log files

**Verified:**
- All passwords appear as `***` in logs
- Decryption failures are logged
- Log directory created automatically if missing

### 5. Documentation (Phase 5)

**File: `README_EMAIL.md`**

Added comprehensive documentation section:

- **Encryption Key Management** - How the system works
- **Key Storage Locations** - Priority order and options
- **Recovery Procedures** - What to do if key is lost
- **Manual Key Generation** - For advanced users
- **Environment Variables** - For Docker/containers
- **Troubleshooting** - Decryption error solutions

## Testing Results

### Automated Tests ✓

All tests pass:

```
✓ Key generation and persistence
✓ Encryption/decryption cycle with multiple passwords
✓ Key change detection and recovery
✓ canDecryptEmailPassword() helper function
✓ Error handling in sendEmailViaSMTP()
✓ Error handling in testSMTPConnection()
✓ Password masking in logs
```

### Integration Verification ✓

Verified that email failures don't block core functionality:

- **Checkout flow** (`checkout.php` lines 495-507): Has try-catch around email sending
- **Support form** (`support.php` lines 125-129): Has try-catch around email sending
- **Admin panel** (`admin/email.php`): Wrapped in comprehensive try-catch with friendly error pages

## Files Modified

1. **config/email_secret.php** - Fixed encryption key stability
2. **includes/email/crypto.php** - Added validation and `canDecryptEmailPassword()`
3. **includes/email/mailer.php** - Improved exception handling
4. **admin/email.php** - Added password reset detection and UI warnings
5. **.gitignore** - Added `config/.email_encryption_key`
6. **README_EMAIL.md** - Added encryption key management documentation

## Files Created

1. **config/.email_encryption_key** - Persistent encryption key (auto-generated, excluded from Git)

## Security Improvements

✓ **Stable encryption** - Passwords can now be reliably decrypted  
✓ **Secure key storage** - File permissions 0600 (owner read/write only)  
✓ **No secrets in Git** - `.email_encryption_key` excluded via `.gitignore`  
✓ **Password masking** - All logs mask passwords as `***`  
✓ **Graceful failures** - No crashes, no 500 errors, clear recovery path  
✓ **Detailed logging** - Server-side error tracking without exposing secrets

## Migration Path

### For Existing Installations

If you have an existing installation with encrypted passwords that cannot be decrypted:

1. Navigate to **Admin → Email Settings**
2. You'll see a **red warning** about password decryption failure
3. Re-enter your SMTP password in the highlighted field
4. Click **Save SMTP Settings**
5. Run **Test Connection** to verify
6. Done! Password is now encrypted with the new stable key

### For New Installations

No action needed:
- Key is auto-generated on first use
- Saved to `config/.email_encryption_key`
- Works automatically

## Acceptance Criteria Met

✓ **Fixed root cause** - Encryption key is now stable and persistent  
✓ **Safe recovery path** - UI detects and prompts for password re-entry  
✓ **No HTTP 500 errors** - All errors caught and handled gracefully  
✓ **Improved logging** - `/logs/email.log` with proper masking and rotation  
✓ **Debug mode** - Already exists, tested and working  
✓ **Security maintained** - CSRF protection, XSS-safe, no password leaks  
✓ **SMTP support** - Port 587 TLS and 465 SSL fully supported  
✓ **Email failures don't block checkout** - Verified try-catch in place  
✓ **Documentation** - Comprehensive guide in README_EMAIL.md

## Testing Commands

To verify the fix works:

```bash
# Test encryption/decryption
php /tmp/test_encryption.php

# Test key change scenario
php /tmp/test_key_change.php

# Test admin UI error handling
php /tmp/test_admin_ui.php

# Check log file
tail -f /path/to/nichehome/logs/email.log
```

## Summary

The email decryption issue is **completely resolved**. The system now:

1. ✅ Uses a stable, persistent encryption key
2. ✅ Automatically detects when password needs re-entry
3. ✅ Provides clear recovery instructions in the UI
4. ✅ Never crashes or returns HTTP 500 errors
5. ✅ Logs all failures securely without exposing secrets
6. ✅ Works seamlessly across all email workflows

**Status:** Production-ready ✓

---

**Last Updated:** December 17, 2024  
**Version:** 1.0  
**Tested:** Yes - All automated and integration tests pass
