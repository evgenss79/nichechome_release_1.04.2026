<?php
/**
 * Payrexx Webhook Entry Point
 * 
 * This file acts as a clean entry point for Payrexx webhooks to prevent
 * HTTP 301 redirects that can occur due to server configuration.
 * 
 * IMPORTANT: Use this URL in your Payrexx dashboard webhook settings:
 * https://nichehome.ch/webhook.php
 * 
 * Configuration in Payrexx:
 * - URL: https://nichehome.ch/webhook.php (use HTTPS, no trailing slash)
 * - Type: JSON
 * - Events: Transaction
 * - Retry on failure: Enabled
 * 
 * This wrapper simply forwards all requests to the actual webhook handler
 * (webhook_payrexx.php) without introducing any redirects or additional headers.
 */

require __DIR__ . '/webhook_payrexx.php';
