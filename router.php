<?php
/**
 * Router for PHP's built-in server, so it behaves like the Apache setup:
 *
 *     php -S localhost:8000 router.php
 *
 * Real files are served as usual, includes/config/sql are blocked, and
 * anything else gets the friendly 404 page. XAMPP/Apache does not use this
 * file — it relies on .htaccess instead.
 */

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if (preg_match('#^/(includes|config|sql)(/|$)#', $path)) {
    require __DIR__ . '/includes/error_page.php';
    show_error_page(403);
}

$file = realpath(__DIR__ . $path);
if ($path !== '/' && $file !== false && str_starts_with($file, __DIR__) && is_file($file)) {
    return false; // let the built-in server serve it (PHP files run, assets stream)
}
if ($path === '/') {
    return false; // index.php
}

require __DIR__ . '/includes/error_page.php';
show_error_page(404);
