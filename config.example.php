<?php
<<<<<<< Updated upstream
// Copy this file to config.php on your own machine and fill in local values.
// Do not commit config.php.

define('DB_HOST', 'localhost');
define('DB_NAME', 'ksij_platform');
define('DB_USER', 'root');
define('DB_PASS', '');

define('GEMINI_API_KEY', 'put-your-api-key-here');
=======
// Copy to config.php and fill in the shared remote database details.
// Get credentials privately from Samir. Never commit config.php.
define('DB_HOST', 'your-remote-db-host');
define('DB_PORT', 3306);
define('DB_NAME', 'your-shared-db-name');
define('DB_USER', 'your-db-user');
define('DB_PASS', 'your-db-password');
define('GEMINI_API_KEY', '');
define('APP_BASE_URL', '/ksij-connect');
// Local development only: show the OTP instead of sending email.
define('OTP_TEST_MODE', true);
>>>>>>> Stashed changes
