<?php
/**
 * Session handling, login/logout, and the guard every private page uses.
 *
 * Include this at the very top of a page — before any output — because it
 * starts the session and may redirect.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    // Keep the session cookie away from JavaScript and off cross-site requests.
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/** The logged-in user's row, or null. Cached per request. */
function current_user(): ?array
{
    static $user = null;
    static $loaded = false;

    if ($loaded) {
        return $user;
    }
    $loaded = true;

    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
        return null;
    }

    $stmt = db()->prepare(
        'SELECT user_id, full_name, email, created_at, warn_pct, theme FROM users WHERE user_id = ?'
    );
    $stmt->execute([$userId]);
    $row = $stmt->fetch();

    // Session points at a deleted user — clear it out.
    if (!$row) {
        unset($_SESSION['user_id']);
        return null;
    }

    $user = $row;
    $GLOBALS['UW_WARN_PCT'] = (int) $row['warn_pct'];   // read by progress_state()
    return $user;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

/** The current user's id. Only call this after require_login(). */
function current_user_id(): int
{
    $user = current_user();
    if (!$user) {
        redirect('login.php');
    }
    return (int) $user['user_id'];
}

/** Bounce guests to the login page. Call at the top of every private page. */
function require_login(): void
{
    if (!is_logged_in()) {
        flash('error', 'Please log in to continue.');
        redirect('login.php');
    }
}

/** Send logged-in users away from login/register. */
function require_guest(): void
{
    if (is_logged_in()) {
        redirect('dashboard.php');
    }
}

/** Start a logged-in session for $userId. */
function login_user(int $userId): void
{
    // New session id on privilege change, to block session fixation.
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

/**
 * Look up a user by email and check the password.
 * Returns the user id on success, or null.
 */
function attempt_login(string $email, string $password): ?int
{
    $stmt = db()->prepare('SELECT user_id, password_hash FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $row = $stmt->fetch();

    if (!$row || !password_verify($password, $row['password_hash'])) {
        return null;
    }

    // Re-hash if PHP's default cost/algorithm has moved on since signup.
    if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
        $update = db()->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?');
        $update->execute([password_hash($password, PASSWORD_DEFAULT), $row['user_id']]);
    }

    return (int) $row['user_id'];
}
