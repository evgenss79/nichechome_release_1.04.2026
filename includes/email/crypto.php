<?php
/**
 * Email Encryption Module
 * Handles encryption and decryption of sensitive email data (SMTP password)
 */

/**
 * Get encryption secret key
 * @return string
 * @throws Exception if key cannot be loaded or is invalid
 */
function getEmailEncryptionKey(): string {
    static $key = null;
    
    if ($key === null) {
        $keyFile = __DIR__ . '/../../config/email_secret.php';
        
        // Check if key file exists
        if (!file_exists($keyFile)) {
            throw new Exception('Email encryption key file not found at: ' . $keyFile);
        }
        
        // Load the key from the file
        $key = require $keyFile;
        
        // If key is empty, this is first-time setup - generate and save key
        if (empty($key)) {
            $key = generateAndSaveEmailEncryptionKey($keyFile);
        }
        
        if (!is_string($key)) {
            throw new Exception('Email encryption key must be a string.');
        }
        
        // Require minimum 32 characters for adequate security
        // Note: Base64-encoded 32 bytes = 44 characters, but we accept 32+ for flexibility
        if (strlen($key) < 32) {
            throw new Exception('Email encryption key is too short (minimum 32 characters). Please generate a secure key.');
        }
        
        // Basic entropy check: reject keys that are too simple (all same character, sequential, etc.)
        // This catches obviously weak keys like 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'
        $uniqueChars = count(array_unique(str_split($key)));
        if ($uniqueChars < 8) {
            throw new Exception('Email encryption key has insufficient entropy. Please generate a cryptographically secure random key.');
        }
        
        // Log key source (but not the key itself) for diagnostics
        error_log("Email encryption key loaded successfully from: $keyFile");
    }
    
    return $key;
}

/**
 * Generate a new encryption key and save it to email_secret.php
 * This is called only on first-time setup when no key exists
 * @param string $keyFile Path to email_secret.php file
 * @return string The generated key
 * @throws Exception if key cannot be saved
 */
function generateAndSaveEmailEncryptionKey(string $keyFile): string {
    // Generate a cryptographically secure 32-byte key
    $newKey = base64_encode(random_bytes(32));
    
    // Read the current file content
    $fileContent = file_get_contents($keyFile);
    if ($fileContent === false) {
        throw new Exception("Cannot read key file: $keyFile");
    }
    
    // Check if file already has a key defined (shouldn't happen, but be defensive)
    if (strpos($fileContent, "define('EMAIL_ENCRYPTION_KEY'") !== false) {
        // Key already exists in file, just reload it
        // This handles race condition where another process already generated the key
        $result = require $keyFile;
        if (!empty($result) && is_string($result)) {
            error_log("Email encryption key already exists in file, using existing key");
            return $result;
        }
    }
    
    // Prepare the new file content with the key
    // Use string concatenation to avoid variable interpolation issues
    $generatedDate = date('Y-m-d H:i:s');
    $newContent = '<?php' . "\n";
    $newContent .= '/**' . "\n";
    $newContent .= ' * Email encryption secret key' . "\n";
    $newContent .= ' * This file should NOT be committed to version control with real key' . "\n";
    $newContent .= ' * ' . "\n";
    $newContent .= ' * IMPORTANT: The encryption key is stored directly in this file as a constant.' . "\n";
    $newContent .= ' * DO NOT delete or modify the EMAIL_ENCRYPTION_KEY constant once it\'s generated,' . "\n";
    $newContent .= ' * or you won\'t be able to decrypt previously encrypted passwords.' . "\n";
    $newContent .= ' * ' . "\n";
    $newContent .= ' * Auto-generated on: ' . $generatedDate . "\n";
    $newContent .= ' */' . "\n";
    $newContent .= "\n";
    $newContent .= '// The encryption key - DO NOT MODIFY after generation' . "\n";
    $newContent .= 'define(\'EMAIL_ENCRYPTION_KEY\', \'' . addslashes($newKey) . '\');' . "\n";
    $newContent .= "\n";
    $newContent .= '// Check for environment variable first (for containerized deployments)' . "\n";
    $newContent .= 'if (!empty($_ENV[\'EMAIL_ENCRYPTION_KEY\'])) {' . "\n";
    $newContent .= '    return $_ENV[\'EMAIL_ENCRYPTION_KEY\'];' . "\n";
    $newContent .= '}' . "\n";
    $newContent .= 'if (!empty(getenv(\'EMAIL_ENCRYPTION_KEY\'))) {' . "\n";
    $newContent .= '    return getenv(\'EMAIL_ENCRYPTION_KEY\');' . "\n";
    $newContent .= '}' . "\n";
    $newContent .= "\n";
    $newContent .= '// Return the key constant' . "\n";
    $newContent .= 'return EMAIL_ENCRYPTION_KEY;' . "\n";
    
    // Try to save the file with secure permissions
    $tempFile = $keyFile . '.tmp';
    if (@file_put_contents($tempFile, $newContent, LOCK_EX) === false) {
        throw new Exception("Cannot write to key file: $keyFile. Please check file permissions.");
    }
    
    // Set secure permissions on temp file before moving
    @chmod($tempFile, 0600);
    
    // Atomically replace the old file
    if (!@rename($tempFile, $keyFile)) {
        @unlink($tempFile);
        throw new Exception("Cannot update key file: $keyFile. Please check file permissions.");
    }
    
    // Set secure permissions again (in case rename changed them)
    $chmodResult = @chmod($keyFile, 0600);
    if (!$chmodResult) {
        error_log("WARNING: Failed to set secure permissions on $keyFile. Please run: chmod 600 $keyFile");
    }
    
    error_log("Email encryption key generated and saved to: $keyFile");
    
    return $newKey;
}

/**
 * Encrypt a string using AES-256-CBC
 * @param string $plain Plain text to encrypt
 * @return string Base64 encoded encrypted string
 */
function encryptEmailPassword(string $plain): string {
    if (empty($plain)) {
        return '';
    }
    
    $key = getEmailEncryptionKey();
    $method = 'AES-256-CBC';
    
    // Generate a random IV using cryptographically secure random_bytes()
    $ivLength = openssl_cipher_iv_length($method);
    $iv = random_bytes($ivLength);
    
    // Encrypt the data
    $encrypted = openssl_encrypt($plain, $method, $key, 0, $iv);
    
    if ($encrypted === false) {
        throw new Exception('Encryption failed');
    }
    
    // Combine IV and encrypted data, then base64 encode
    return base64_encode($iv . $encrypted);
}

/**
 * Decrypt a string using AES-256-CBC
 * @param string $cipher Base64 encoded encrypted string
 * @return string Decrypted plain text
 * @throws Exception if decryption fails
 */
function decryptEmailPassword(string $cipher): string {
    if (empty($cipher)) {
        return '';
    }
    
    $key = getEmailEncryptionKey();
    $method = 'AES-256-CBC';
    
    // Decode the base64 string
    $data = base64_decode($cipher, true);
    if ($data === false) {
        throw new Exception('Invalid encrypted data format: base64 decode failed');
    }
    
    // Validate data format before proceeding
    $ivLength = openssl_cipher_iv_length($method);
    if (strlen($data) < $ivLength + 1) {
        throw new Exception('Encrypted data is too short: expected at least ' . ($ivLength + 1) . ' bytes, got ' . strlen($data));
    }
    
    // Extract IV and encrypted data
    $iv = substr($data, 0, $ivLength);
    $encrypted = substr($data, $ivLength);
    
    // Additional validation: IV should be binary data of correct length
    if (strlen($iv) !== $ivLength) {
        throw new Exception('Invalid IV length: expected ' . $ivLength . ' bytes');
    }
    
    // Validate encrypted data is not empty
    if (empty($encrypted)) {
        throw new Exception('Encrypted data payload is empty');
    }
    
    // Decrypt the data
    $decrypted = openssl_decrypt($encrypted, $method, $key, 0, $iv);
    
    if ($decrypted === false) {
        // Log the failure (without exposing sensitive data)
        error_log("Email password decryption failed. This usually means the encryption key has changed. Password needs to be re-entered.");
        throw new Exception('Password decryption failed. Please re-enter your SMTP password in Email Settings.');
    }
    
    return $decrypted;
}

/**
 * Check if stored password can be decrypted with current key
 * @param string $cipher Base64 encoded encrypted string
 * @return bool True if decryption succeeds, false otherwise
 */
function canDecryptEmailPassword(string $cipher): bool {
    if (empty($cipher)) {
        return false;
    }
    
    try {
        decryptEmailPassword($cipher);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Check if legacy .email_encryption_key file exists
 * @return bool True if legacy key file exists
 */
function hasLegacyEmailEncryptionKey(): bool {
    $legacyKeyFile = __DIR__ . '/../../config/.email_encryption_key';
    return file_exists($legacyKeyFile);
}
