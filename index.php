<?php
/**
 * Index - Redirects to About page
 */

// Preserve language parameter if present
$langParam = isset($_GET['lang']) ? '?lang=' . urlencode($_GET['lang']) : '';
header('Location: about.php' . $langParam);
exit;
