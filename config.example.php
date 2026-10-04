<?php
// Copy to config.php and fill in the shared remote database details.
// Get credentials privately from Samir. Never commit config.php.
define('DB_HOST', 'your-remote-db-host');
define('DB_PORT', 3306);
define('DB_NAME', 'your-shared-db-name');
define('DB_USER', 'your-db-user');
define('DB_PASS', 'your-db-password');
// AI settings are separate: use ai.example.php as the template for ai.local.php.
define('APP_BASE_URL', '/ksij-connect');
// Local development only: show the OTP instead of sending email.
define('OTP_TEST_MODE', true);
// Presentation only. Disable before deployment; no money is collected.
define('DEMO_MEMBERSHIP_PAYMENTS', true);
