<?php
/**
 * Forgot password — ask for an email, issue a one-time reset link.
 *
 * The answer is identical whether or not the email is registered, so this
 * page can't be used to discover which emails have accounts.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/password_reset.php';

require_guest();

$sent  = false;
$link  = null;
$email = '';
$error = '';

if (is_post()) {
    require_csrf();
    $email = strtolower(post('email'));

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter the email address you registered with.';
    } else {
        $link = start_password_reset($email);
        $sent = true;
    }
}

$page_title = $sent ? 'Check your email' : 'Forgot your password?';
$page_tag   = $sent
    ? 'If that email has an account, a reset link is on its way.'
    : 'Enter your email and we will send you a link to choose a new password.';
require __DIR__ . '/includes/auth_header.php';
?>

<?php if ($sent): ?>
  <p class="muted">The link works once and expires in <?= (int) RESET_TTL_MINUTES ?> minutes.
     Nothing arrived? Check your spam folder, or ask again in a minute.</p>

  <?php if (RESET_SHOW_LINK && $link !== null): ?>
    <div class="dev-note">
      <strong>Demo mode</strong>
      <p>No mail server is set up here, so the link is shown instead of emailed:</p>
      <a class="dev-link" href="<?= h($link) ?>"><?= h($link) ?></a>
    </div>
  <?php endif; ?>

  <p class="auth-alt"><a href="login.php">Back to log in</a></p>

<?php else: ?>
  <?php if ($error !== ''): ?>
    <div class="flash flash-error"><?= h($error) ?></div>
  <?php endif; ?>

  <form method="post" action="forgot.php" data-validated novalidate>
    <?= csrf_field() ?>
    <div class="field">
      <label for="email">Email</label>
      <input type="email" id="email" name="email" value="<?= h($email) ?>" maxlength="190"
             autocomplete="email" data-validate="required email" data-autofocus>
    </div>
    <button type="submit" class="btn btn-primary btn-block btn-lg">Send reset link</button>
  </form>

  <p class="auth-alt">Remembered it? <a href="login.php">Log in</a></p>
<?php endif; ?>

<?php require __DIR__ . '/includes/auth_footer.php'; ?>
