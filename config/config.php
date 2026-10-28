<?php
// Application and database settings. Edit these if your XAMPP setup differs.
define('APP_NAME', 'M-Garage');
define('BASE_URL', '/m-garage');          // folder name under htdocs
define('DB_HOST', 'localhost');
define('DB_NAME', 'm_garage');
define('DB_USER', 'root');                // XAMPP default
define('DB_PASS', '');                    // XAMPP default is empty
define('APP_DEBUG', getenv('M_GARAGE_DEBUG') === '1'); // opt in only for local diagnostics
date_default_timezone_set('Asia/Manila');
