<?php
/**
 * UniWallet — application configuration.
 *
 * The defaults below match a stock XAMPP install (MySQL user "root" with an
 * empty password). Change them here if your setup differs.
 */

// ---------------------------------------------------------------- database
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'uniwallet_app');   // its own name, so it never clashes with another project's database
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// ------------------------------------------------------------------- app
define('APP_NAME', 'UniWallet');

// Currency symbol shown throughout the UI. Change this one line to switch
// (e.g. '₹', '€', '£', '৳').
define('CURRENCY', '৳');

// Used for "today" and for deciding which month the dashboard opens on.
// Set this to your own zone, e.g. 'Asia/Dhaka', 'Asia/Kolkata', 'Europe/London'.
define('APP_TIMEZONE', 'Asia/Dhaka');

// Show PHP errors on screen. Set to false before submitting/deploying.
define('APP_DEBUG', true);

// -------------------------------------------------------------- runtime
if (APP_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

date_default_timezone_set(APP_TIMEZONE);

// Any uncaught exception (a failed query, a bug) becomes the friendly 500 page
// instead of a blank screen. With APP_DEBUG on, the message is shown too.
set_exception_handler(static function (Throwable $e): void {
    error_log('UniWallet: ' . $e);
    require_once __DIR__ . '/../includes/error_page.php';
    show_error_page(500, $e);
});
