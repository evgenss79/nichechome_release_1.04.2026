<?php
/**
 * Email Logging Module
 * Handles logging of email events (no sensitive data)
 */

/**
 * Log an email event
 * @param string $event Event type (e.g., 'order_confirmation', 'order_admin', 'support_admin', 'test')
 * @param string $recipient Recipient email address
 * @param bool $success Whether the email was sent successfully
 * @param string $error Error message if failed (default empty)
 * @param array $context Additional context (e.g., customer_email, request_id)
 * @return bool Whether logging was successful
 */
function logEmailEvent(string $event, string $recipient, bool $success, string $error = '', array $context = []): bool {
    $logDir = __DIR__ . '/../../logs';
    $logFile = $logDir . '/email.log';
    
    // Create logs directory if it doesn't exist
    if (!is_dir($logDir)) {
        if (!mkdir($logDir, 0755, true)) {
            error_log("Failed to create email logs directory: $logDir");
            return false;
        }
    }
    
    // Rotate log file if it exceeds 5MB
    if (file_exists($logFile) && filesize($logFile) > 5 * 1024 * 1024) {
        $archiveFile = $logDir . '/email_' . date('Y-m-d_H-i-s') . '.log';
        rename($logFile, $archiveFile);
        
        // Keep only last 10 archived log files
        $archived = glob($logDir . '/email_*.log');
        if (count($archived) > 10) {
            usort($archived, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            // Delete oldest
            foreach (array_slice($archived, 0, count($archived) - 10) as $old) {
                unlink($old);
            }
        }
    }
    
    // Mask any passwords in error messages
    if (!empty($error)) {
        $error = preg_replace('/password["\']?\s*[:=]\s*["\']?[^\s"\']+/i', 'password: ***', $error);
        $error = preg_replace('/(?:PASS|Password)[:\s]+.*/i', 'PASS ***', $error);
    }
    
    // Prepare log entry
    $timestamp = date('Y-m-d H:i:s');
    $status = $success ? 'SUCCESS' : 'FAILED';
    $errorMsg = $success ? '' : " | Error: " . substr($error, 0, 500); // Limit error length
    
    // Add context if available
    $contextStr = '';
    if (!empty($context)) {
        $contextParts = [];
        if (isset($context['customer_email'])) {
            $contextParts[] = "CustomerEmail: " . $context['customer_email'];
        }
        if (isset($context['request_id'])) {
            $contextParts[] = "RequestID: " . $context['request_id'];
        }
        if (isset($context['order_id'])) {
            $contextParts[] = "OrderID: " . $context['order_id'];
        }
        if (isset($context['lang'])) {
            $contextParts[] = "Lang: " . $context['lang'];
        }
        if (!empty($contextParts)) {
            $contextStr = " | " . implode(", ", $contextParts);
        }
    }
    
    $logEntry = "[$timestamp] [$status] Flow: $event | To: $recipient$contextStr$errorMsg\n";
    
    // Append to log file
    $result = file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    
    if ($result === false) {
        error_log("Failed to write to email log file: $logFile");
        return false;
    }
    
    return true;
}

/**
 * Get recent email log entries
 * @param int $limit Maximum number of entries to return (default 100)
 * @return array Array of log entries
 */
function getRecentEmailLogs(int $limit = 100): array {
    $logFile = __DIR__ . '/../../logs/email.log';
    
    if (!file_exists($logFile)) {
        return [];
    }
    
    $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return [];
    }
    
    // Get the last $limit lines
    $lines = array_slice($lines, -$limit);
    
    // Reverse to show most recent first
    return array_reverse($lines);
}
