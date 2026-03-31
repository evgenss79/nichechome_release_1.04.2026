Email and PHP Error Logs Directory
====================================

This directory contains:
- php_errors.log: PHP errors, warnings, and fatal errors
- email.log: Email sending attempts (success and failure)
- email_*.log: Archived email logs (rotated when exceeding 5MB)

Access:
- These logs are protected from public web access via .htaccess
- Access logs through Admin Panel: Email → Logs tab
- Or via SSH/FTP access to the server

Security:
- No passwords are logged (all passwords are masked)
- Logs are automatically rotated to prevent disk space issues
- Maximum 10 archived email log files are kept
