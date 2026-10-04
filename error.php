<?php
/**
 * Shown for 403 / 404 / 500. The web server points here (see .htaccess and
 * router.php); the code arrives as ?code= or in REDIRECT_STATUS.
 */

require_once __DIR__ . '/includes/error_page.php';

$code = (int) ($_GET['code'] ?? $_SERVER['REDIRECT_STATUS'] ?? 404);
show_error_page(in_array($code, [403, 404, 500], true) ? $code : 404);
