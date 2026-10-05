<?php
/**
 * "Forgot password" logic: issue a one-time token, deliver the link, and
 * redeem the token.
 *
 * Security notes
 *  - The emailed token is 256 random bits; only its SHA-256 hash is stored.
 *  - A token works once, expires after RESET_TTL_MINUTES, and requesting a
 *    new one cancels the old ones.
 *  - Callers must give the same answer whether or not the email exists, so
 *    the form can't be used to find out who has an account.
 *  - Expiry is compared in SQL (NOW()) so PHP and MySQL time zones can't disagree.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/error_page.php';   // app_base_url()

/** Absolute URL of the reset page for a token. */
function reset_link(string $token): string
{
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    // The Host header is user-controlled and ends up inside an email — keep it boring.
    if (!preg_match('/^[A-Za-z0-9.\-]+(:\d{1,5})?$/', $host)) {
        $host = 'localhost';
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $scheme . '://' . $host . app_base_url() . '/reset.php?token=' . $token;
}

/**
 * Start a reset for $email. Returns the link when one was issued, or null when
 * nothing was (unknown email, or a link was issued in the last minute).
 */
function start_password_reset(string $email): ?string
{
    $stmt = db()->prepare('SELECT user_id, full_name FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if (!$user) {
        return null;
    }

    // Throttle: at most one link per minute per account.
    $recent = db()->prepare(
        'SELECT 1 FROM password_resets
          WHERE user_id = ? AND created_at > (NOW() - INTERVAL 1 MINUTE) LIMIT 1'
    );
    $recent->execute([$user['user_id']]);
    if ($recent->fetchColumn()) {
        return null;
    }

    // Cancel older links, then issue a fresh one.
    db()->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$user['user_id']]);

    $token = bin2hex(random_bytes(32));
    db()->prepare(
        'INSERT INTO password_resets (user_id, token_hash, expires_at)
         VALUES (?, ?, NOW() + INTERVAL ' . (int) RESET_TTL_MINUTES . ' MINUTE)'
    )->execute([$user['user_id'], hash('sha256', $token)]);

    $link = reset_link($token);
    deliver_reset_email($email, (string) $user['full_name'], $link);
    return $link;
}

/** Send the email (best effort) and always append it to storage/outbox.log. */
function deliver_reset_email(string $to, string $name, string $link): void
{
    $subject = 'Reset your ' . APP_NAME . ' password';
    $body = "Hi " . $name . ",\n\n"
          . "Someone asked to reset the password for your " . APP_NAME . " account.\n"
          . "Open this link to choose a new one (valid for " . RESET_TTL_MINUTES . " minutes):\n\n"
          . $link . "\n\n"
          . "If you did not ask for this, ignore this email — your password stays the same.\n";

    // Header injection guard: the address came from the database, but be strict anyway.
    $headers = 'From: ' . APP_NAME . ' <no-reply@localhost>' . "\r\n"
             . 'Content-Type: text/plain; charset=UTF-8';
    if (!preg_match('/[\r\n]/', $to)) {
        @mail($to, $subject, $body, $headers);
    }

    $log = __DIR__ . '/../storage/outbox.log';
    $entry = '[' . date('Y-m-d H:i:s') . "] To: $to\nSubject: $subject\n$body\n" . str_repeat('-', 60) . "\n";
    @file_put_contents($log, $entry, FILE_APPEND | LOCK_EX);
}

/** The reset row for a token if it is unused and not expired, else null. */
function find_valid_reset(string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }
    $stmt = db()->prepare(
        'SELECT reset_id, user_id FROM password_resets
          WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()'
    );
    $stmt->execute([hash('sha256', $token)]);
    return $stmt->fetch() ?: null;
}

/** Set the new password and burn every reset link for that user. */
function complete_password_reset(array $reset, string $newPassword): void
{
    $pdo = db();
    $pdo->beginTransaction();
    $pdo->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?')
        ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $reset['user_id']]);
    $pdo->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$reset['user_id']]);
    $pdo->commit();
}
