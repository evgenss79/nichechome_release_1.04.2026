<?php
/**
 * Admin - Stock Import via CSV
 * 
 * Note: CSV-only approach is used to avoid external dependencies (no Composer/PhpSpreadsheet).
 * Full import logic is implemented using native PHP fgetcsv() function.
 */

// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../init.php';

/**
 * Log error to stock_import.log
 */
function logStockImportError($message, $context = []) {
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/stock_import.log';
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? json_encode($context, JSON_UNESCAPED_UNICODE) : '';
    $logEntry = "[$timestamp] $message";
    if ($contextStr) {
        $logEntry .= " | Context: $contextStr";
    }
    $logEntry .= PHP_EOL;
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

/**
 * Parse CSV file and return array of rows
 * Auto-detects delimiter (comma, semicolon, or tab)
 */
function parseCSVFile($filePath) {
    if (!file_exists($filePath)) {
        throw new Exception("File not found: $filePath");
    }
    
    // Auto-detect delimiter by reading first line
    $handle = fopen($filePath, 'r');
    if (!$handle) {
        throw new Exception("Cannot open file for reading");
    }
    
    $firstLine = fgets($handle);
    fclose($handle);
    
    // Count occurrences of common delimiters
    $commaCount = substr_count($firstLine, ',');
    $semicolonCount = substr_count($firstLine, ';');
    $tabCount = substr_count($firstLine, "\t");
    
    // Choose delimiter with highest count
    $delimiter = ',';
    $maxCount = $commaCount;
    
    if ($semicolonCount > $maxCount) {
        $delimiter = ';';
        $maxCount = $semicolonCount;
    }
    if ($tabCount > $maxCount) {
        $delimiter = "\t";
    }
    
    // Parse the entire file
    $rows = [];
    $handle = fopen($filePath, 'r');
    
    while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
        $rows[] = $row;
    }
    
    fclose($handle);
    
    return [
        'rows' => $rows,
        'delimiter' => $delimiter
    ];
}

if (!isAdminLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Prevent browser caching
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$success = '';
$error = '';
$conflicts = [];
$importSummary = null;

// Handle file upload and parsing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['import_file'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid security token. Please refresh the page and try again.';
    } else {
        try {
            $checkControl = isset($_POST['check_control']);
            $uploadedFile = $_FILES['import_file'];
            
            // Validate file upload
            if (!isset($uploadedFile['tmp_name']) || empty($uploadedFile['tmp_name'])) {
                $error = 'Please choose a CSV file first.';
            } elseif ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
                $error = 'File upload failed. Please try again.';
                logStockImportError('File upload error', ['error_code' => $uploadedFile['error']]);
            } elseif ($uploadedFile['size'] > 10 * 1024 * 1024) { // 10 MB limit
                $error = 'File is too large. Maximum size is 10 MB.';
            } else {
                $fileExtension = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
                
                if ($fileExtension !== 'csv') {
                    $error = 'Invalid file type. Only .csv files are allowed.';
                } else {
                    // Parse the CSV file
                    $parseResult = parseCSVFile($uploadedFile['tmp_name']);
                    $rows = $parseResult['rows'];
                    $detectedDelimiter = $parseResult['delimiter'];
                    
                    if (empty($rows)) {
                        $error = 'The file is empty or could not be read.';
                    } else {
                        // Parse header row
                        $header = array_shift($rows);
                        // Show success message with detected info on first upload (for testing)
                        if (empty($rows)) {
                            $delimiterName = $detectedDelimiter === ',' ? 'comma' : ($detectedDelimiter === ';' ? 'semicolon' : 'tab');
                            $success = "File received. Parsing OK. Detected delimiter: $delimiterName. Header columns: " . implode(', ', array_map('htmlspecialchars', $header));
                        } else {
                            // Find column indices
                            $skuIndex = array_search('sku', array_map('strtolower', $header));
                            $quantityIndex = array_search('quantity', array_map('strtolower', $header));
                            if ($quantityIndex === false) {
                                $quantityIndex = array_search('stock_quantity', array_map('strtolower', $header));
                            }
                            if ($quantityIndex === false) {
                                $quantityIndex = array_search('total_qty', array_map('strtolower', $header));
                            }
                            if ($quantityIndex === false) {
                                $quantityIndex = array_search('total', array_map('strtolower', $header));
                            }
                            
                            if ($skuIndex === false) {
                                $error = 'Invalid template: Missing "sku" column.';
                            } elseif ($quantityIndex === false) {
                                $error = 'Invalid template: Missing authoritative "quantity" column.';
                            } else {
                                // Load current stock
                                $currentStock = loadJSON('stock.json');
                                
                                // Parse data rows
                                $parsedData = [];
                                $errors = [];
                                $lineNum = 2; // Start from 2 (1 is header)
                                
                                foreach ($rows as $row) {
                                    if (empty($row[$skuIndex])) {
                                        continue; // Skip empty rows
                                    }
                                    
                                    $sku = trim($row[$skuIndex]);
                                    
                                    // Validate SKU exists
                                    if (!isset($currentStock[$sku])) {
                                        $errors[] = "Line $lineNum: Unknown SKU '$sku'";
                                        $lineNum++;
                                        continue;
                                    }
                                    
                                    $quantityValidation = validateStockQuantity($row[$quantityIndex] ?? 0);

                                    if (!$quantityValidation['valid']) {
                                        $errors[] = "Line $lineNum: " . $quantityValidation['error'];
                                        $lineNum++;
                                        continue;
                                    }
                                    
                                    // Check for conflicts (existing non-zero stock)
                                    $hasExistingStock = (int)($currentStock[$sku]['quantity'] ?? 0) > 0;
                                    
                                    $parsedData[$sku] = [
                                        'quantity' => $quantityValidation['value'],
                                        'hasConflict' => $hasExistingStock
                                    ];
                                    
                                    $lineNum++;
                                }
                                
                                if (!empty($errors)) {
                                    $error = 'Validation errors found:<br>' . implode('<br>', $errors);
                                    logStockImportError('Validation errors during import', ['errors' => $errors]);
                                } elseif (empty($parsedData)) {
                                    $error = 'No valid data found in the file.';
                                } else {
                                    // Store parsed data in session for confirmation
                                    $_SESSION['import_data'] = $parsedData;
                                    $_SESSION['import_check_control'] = $checkControl;
                                    
                                    // Check if we need conflict resolution
                                    $needsConfirmation = false;
                                    if ($checkControl) {
                                        foreach ($parsedData as $sku => $data) {
                                            if ($data['hasConflict']) {
                                                $needsConfirmation = true;
                                                $conflicts[$sku] = $data;
                                            }
                                        }
                                    }
                                    
                                    if (!$needsConfirmation) {
                                        // No conflicts or CheckControl disabled - proceed with import
                                        header('Location: stock_import.php?action=confirm_import');
                                        exit;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            $error = 'Error processing file: ' . htmlspecialchars($e->getMessage());
            logStockImportError('Exception during file upload', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
        } finally {
            // Clean up uploaded file
            if (isset($uploadedFile['tmp_name']) && file_exists($uploadedFile['tmp_name'])) {
                @unlink($uploadedFile['tmp_name']);
            }
        }
    }
}

// Handle conflict resolution submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'resolve_conflicts') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid security token. Please refresh the page and try again.';
    } else {
        try {
            // Store conflict resolutions
            if (isset($_SESSION['import_data'])) {
                $resolutions = $_POST['resolution'] ?? [];
                $_SESSION['import_resolutions'] = $resolutions;
                header('Location: stock_import.php?action=confirm_import');
                exit;
            } else {
                $error = 'Import data not found. Please upload the file again.';
            }
        } catch (Throwable $e) {
            $error = 'Error processing conflict resolution: ' . htmlspecialchars($e->getMessage());
            logStockImportError('Exception during conflict resolution', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }
    }
}

// Handle final import confirmation
if (isset($_GET['action']) && $_GET['action'] === 'confirm_import') {
    try {
        if (!isset($_SESSION['import_data'])) {
            $error = 'Import data not found. Please upload the file again.';
        } else {
            $importData = $_SESSION['import_data'];
            $checkControl = $_SESSION['import_check_control'] ?? false;
            $resolutions = $_SESSION['import_resolutions'] ?? [];
            
            // Load current data
            $currentStock = loadJSON('stock.json');
            
            // Create backups
            createStockBackup('branch_stock.json');
            createStockBackup('stock.json');
            
            $updated = 0;
            $replaced = 0;
            $added = 0;
            $skipped = 0;
            $errors = [];
            
            foreach ($importData as $sku => $data) {
                $resolution = $resolutions[$sku] ?? 'replace';
                
                if ($resolution === 'skip') {
                    $skipped++;
                    continue;
                }
                
                $newQuantity = (int)($data['quantity'] ?? 0);
                
                // Apply resolution
                if ($resolution === 'add') {
                    $newQuantity += (int)($currentStock[$sku]['quantity'] ?? 0);
                    $added++;
                } else {
                    // Replace (default)
                    $replaced++;
                }
                
                // Update stock
                $result = updateConsolidatedStock($sku, $newQuantity);
                if ($result['success']) {
                    $currentStock[$sku]['quantity'] = $newQuantity;
                    $updated++;
                } else {
                    $errors[] = "SKU $sku: " . $result['error'];
                }
            }
            
            // Clear session data
            unset($_SESSION['import_data']);
            unset($_SESSION['import_check_control']);
            unset($_SESSION['import_resolutions']);
            
            if (!empty($errors)) {
                $error = 'Some items failed to import:<br>' . implode('<br>', $errors);
                logStockImportError('Some items failed to import', ['errors' => $errors]);
            }
            
            $importSummary = [
                'total' => count($importData),
                'updated' => $updated,
                'replaced' => $replaced,
                'added' => $added,
                'skipped' => $skipped,
                'failed' => count($errors)
            ];
            
            if ($updated > 0) {
                $success = "Import completed successfully! $updated SKUs updated.";
            }
        }
    } catch (Throwable $e) {
        $error = 'Error during import: ' . htmlspecialchars($e->getMessage());
        logStockImportError('Exception during final import', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Import - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600&family=Manrope:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        .conflict-row {
            background: #fff3cd;
            padding: 1rem;
            border: 1px solid #ffc107;
            margin-bottom: 0.5rem;
            border-radius: 4px;
        }
        .conflict-actions {
            display: flex;
            gap: 1rem;
            margin-top: 0.5rem;
        }
        .summary-card {
            background: #f0f8ff;
            border: 1px solid #4a90e2;
            padding: 1.5rem;
            border-radius: 4px;
        }
        .summary-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        .summary-stat {
            text-align: center;
            padding: 1rem;
            background: white;
            border-radius: 4px;
        }
        .summary-stat-value {
            font-size: 2rem;
            font-weight: 600;
            color: var(--color-gold);
        }
        .summary-stat-label {
            color: #666;
            margin-top: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <aside class="admin-sidebar">
            <div class="admin-sidebar__logo">NicheHome Admin</div>
                        <nav class="admin-sidebar__nav">
                <a href="index.php" class="admin-sidebar__link">Dashboard</a>
                <a href="products.php" class="admin-sidebar__link">Products</a>
                <a href="admin_products.php" class="admin-sidebar__link">Products (Enhanced)</a>
                <a href="accessories.php" class="admin-sidebar__link">Accessories</a>
                <a href="fragrances.php" class="admin-sidebar__link">Fragrances</a>
                <a href="categories.php" class="admin-sidebar__link">Categories</a>
                <a href="stock.php" class="admin-sidebar__link">Stock</a>
                <a href="stock_import.php" class="admin-sidebar__link active">Stock Import</a>
                <a href="sku_audit.php" class="admin-sidebar__link">SKU Audit</a>
                <a href="orders.php" class="admin-sidebar__link">Orders</a>
                <a href="admin_orders.php" class="admin-sidebar__link">Orders (Enhanced)</a>
                <a href="shipping.php" class="admin-sidebar__link">Shipping</a>
                <a href="branches.php" class="admin-sidebar__link">Branches</a>
                <a href="admin_users.php" class="admin-sidebar__link">User Management</a>
                <?php if (canManageUsers()): // MENU FIX: Only full_access role can manage admins ?>
                <a href="manage_admins.php" class="admin-sidebar__link">Admin Management</a>
                <?php endif; ?>
                <a href="diagnostics.php" class="admin-sidebar__link">Diagnostics</a>
                <a href="notifications.php" class="admin-sidebar__link">Notifications</a>
                <a href="email.php" class="admin-sidebar__link">Email Settings</a>
                <a href="logout.php" class="admin-sidebar__link">Logout</a>
            </nav>
        </aside>
        
        <main class="admin-content">
            <div class="admin-header">
                <h1>Stock Import</h1>
                <a href="stock.php" class="btn btn--ghost">← Back to Stock</a>
            </div>
            
            <?php if ($success): ?>
                <div class="alert alert--success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert--error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($importSummary): ?>
                <div class="summary-card">
                    <h2 style="margin-top: 0;">Import Summary</h2>
                    <div class="summary-stats">
                        <div class="summary-stat">
                            <div class="summary-stat-value"><?php echo $importSummary['total']; ?></div>
                            <div class="summary-stat-label">Total SKUs</div>
                        </div>
                        <div class="summary-stat">
                            <div class="summary-stat-value"><?php echo $importSummary['updated']; ?></div>
                            <div class="summary-stat-label">Updated</div>
                        </div>
                        <div class="summary-stat">
                            <div class="summary-stat-value"><?php echo $importSummary['replaced']; ?></div>
                            <div class="summary-stat-label">Replaced</div>
                        </div>
                        <div class="summary-stat">
                            <div class="summary-stat-value"><?php echo $importSummary['added']; ?></div>
                            <div class="summary-stat-label">Added</div>
                        </div>
                        <div class="summary-stat">
                            <div class="summary-stat-value"><?php echo $importSummary['skipped']; ?></div>
                            <div class="summary-stat-label">Skipped</div>
                        </div>
                        <?php if ($importSummary['failed'] > 0): ?>
                        <div class="summary-stat">
                            <div class="summary-stat-value" style="color: var(--color-error);"><?php echo $importSummary['failed']; ?></div>
                            <div class="summary-stat-label">Failed</div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($conflicts)): ?>
                <!-- Conflict Resolution -->
                <div class="admin-card">
                    <h2>⚠️ Conflict Resolution Required</h2>
                    <p style="color: #666;">The following SKUs already have stock. Choose an action for each:</p>
                    
                    <form method="post" action="" style="margin-top: 1.5rem;">
                        <input type="hidden" name="action" value="resolve_conflicts">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        
                        <?php foreach ($conflicts as $sku => $data): ?>
                            <div class="conflict-row">
                                <strong>SKU: <?php echo htmlspecialchars($sku); ?></strong>
                                <div style="color: #666; margin-top: 0.25rem;">
                                    Import will set STOCK quantity to: <?php echo $data['quantity']; ?>
                                </div>
                                <div class="conflict-actions">
                                    <label>
                                        <input type="radio" name="resolution[<?php echo htmlspecialchars($sku); ?>]" value="replace" checked>
                                        <strong>Replace</strong> - Overwrite current quantities
                                    </label>
                                    <label>
                                        <input type="radio" name="resolution[<?php echo htmlspecialchars($sku); ?>]" value="add">
                                        <strong>Add</strong> - Add to current quantities
                                    </label>
                                    <label>
                                        <input type="radio" name="resolution[<?php echo htmlspecialchars($sku); ?>]" value="skip">
                                        <strong>Skip</strong> - Don't import this SKU
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <div style="margin-top: 1.5rem;">
                            <button type="submit" class="btn btn--gold">Continue Import</button>
                            <a href="stock_import.php" class="btn" style="margin-left: 1rem;">Cancel</a>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <!-- Upload Form -->
                <div class="admin-card">
                    <h2>Upload Stock File</h2>
                    <p style="color: #666; margin-bottom: 1.5rem;">
                        Upload a CSV file containing stock data. 
                        Use the template from the Stock page for correct format.
                    </p>
                    
                    <form method="post" action="" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        
                        <div class="form-group">
                            <label for="import_file">Select File *</label>
                            <input type="file" 
                                   id="import_file" 
                                   name="import_file" 
                                   accept=".csv" 
                                   required 
                                   style="width: 100%; padding: 0.5rem; border: 1px solid #ccc; border-radius: 4px;">
                            <small style="color: #666; display: block; margin-top: 0.25rem;">
                                Accepted format: .csv only (Max 10 MB)
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="check_control" value="1" checked>
                                <strong>Enable CheckControl</strong> - Prompt for Replace/Add when SKU has existing stock
                            </label>
                            <small style="color: #666; display: block; margin-top: 0.25rem; margin-left: 1.5rem;">
                                If disabled, all imports will replace existing stock without confirmation.
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn--gold">📤 Upload and Import</button>
                            <a href="stock.php" class="btn" style="margin-left: 1rem;">Cancel</a>
                        </div>
                    </form>
                </div>
                
                <!-- Instructions -->
                <div class="admin-card" style="margin-top: 1.5rem; background: #f0f8ff; border: 1px solid #4a90e2;">
                    <h3 style="margin-top: 0; color: #4a90e2;">ℹ️ Import Instructions</h3>
                    <ol style="color: #666;">
                        <li>Download the CSV template from the Stock page</li>
                        <li>Fill in the single authoritative <code>quantity</code> column for each SKU</li>
                        <li>Save the file as CSV format</li>
                        <li>Upload the completed file here</li>
                        <li>If CheckControl is enabled, resolve any conflicts for SKUs with existing stock</li>
                        <li>Review the import summary</li>
                    </ol>
                    
                    <h4 style="color: #4a90e2; margin-top: 1.5rem;">Validation Rules</h4>
                    <ul style="color: #666;">
                        <li>All SKUs must exist in the system</li>
                        <li>All quantities must be non-negative integers</li>
                        <li>Only the authoritative STOCK quantity column is imported</li>
                        <li>Maximum file size: 10 MB</li>
                        <li>CSV delimiter auto-detected (comma, semicolon, or tab)</li>
                    </ul>
                    
                    <h4 style="color: #4a90e2; margin-top: 1.5rem;">📝 Note</h4>
                    <p style="color: #666;">
                        This feature uses CSV files only (no Excel support) to avoid external dependencies.
                        You can export Excel files to CSV format using Excel or Google Sheets.
                    </p>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
