<?php
/**
 * Reset password — the page the emailed link opens.
 * The token is validated on every request, including the form submit.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/password_reset.php';

require_guest();

$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$reset = find_valid_reset($token);
$error = '';

if ($reset && is_post()) {
    require_csrf();
    $new     = (string) ($_POST['new_password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    if (strlen($new) < 8 || strlen($new) > 200) {
        $error = 'The new password must be 8 to 200 characters.';
    } elseif ($new !== $confirm) {
        $error = 'The two passwords do not match.';
    } else {
        complete_password_reset($reset, $new);
        flash('ok', 'Your password is changed. Log in with the new one.');
        redirect('login.php');
    }
}

$page_title = $reset ? 'Choose a new password' : 'This link has expired';
$page_tag   = $reset
    ? 'Use at least 8 characters.'
    : 'Reset links work once and expire after ' . RESET_TTL_MINUTES . ' minutes. Ask for a new one below.';
require __DIR__ . '/includes/auth_header.php';
?>

<?php if (!$reset): ?>
  <a class="btn btn-primary btn-block btn-lg" href="forgot.php">Get a new link</a>
  <p class="auth-alt"><a href="login.php">Back to log in</a></p>

<?php else: ?>
  <?php if ($error !== ''): ?>
    <div class="flash flash-error"><?= h($error) ?></div>
  <?php endif; ?>

  <form method="post" action="reset.php" data-validated novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= h($token) ?>">

    <div class="field">
      <label for="new_password">New password</label>
      <input type="password" id="new_password" name="new_password" autocomplete="new-password"
             data-validate="required minlength" data-validate-minlength="8"
             data-toggle-password data-strength data-autofocus>
    </div>
    <div class="field">
      <label for="confirm_password">Confirm new password</label>
      <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password"
             data-validate="required matches" data-validate-matches="new_password" data-toggle-password>
    </div>
    <button type="submit" class="btn btn-primary btn-block btn-lg">Change password</button>
  </form>
<?php endif; ?>

<?php require __DIR__ . '/includes/auth_footer.php'; ?>
