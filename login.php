<?php
/**
 * Log in.
 *
 * On failure the message is deliberately vague ("email or password is
 * incorrect") so the page can't be used to discover which emails exist.
 */

require_once __DIR__ . '/includes/auth.php';

require_guest();

$errors = [];
$email  = '';

if (is_post()) {
    require_csrf();

    $email    = strtolower(post('email'));
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '') {
        $errors['email'] = 'Please enter your email.';
    }
    if ($password === '') {
        $errors['password'] = 'Please enter your password.';
    }

    if (!$errors) {
        $userId = attempt_login($email, $password);
        if ($userId !== null) {
            login_user($userId);
            redirect('dashboard.php');
        }
        $errors['form'] = 'That email or password is incorrect.';
    }
}

$page_title = 'Welcome back';
$page_tag   = 'Log in to see your budget and recent spending.';
require __DIR__ . '/includes/auth_header.php';
?>

<?php if (isset($errors['form'])): ?>
  <div class="flash flash-error"><?= h($errors['form']) ?></div>
<?php endif; ?>

<form method="post" action="login.php" data-validated novalidate>
  <?= csrf_field() ?>

  <div class="field">
    <label for="email">Email</label>
    <input type="email" id="email" name="email" value="<?= h($email) ?>"
           maxlength="190" autocomplete="email"
           data-validate="required email" data-autofocus
           class="<?= isset($errors['email']) ? 'is-invalid' : '' ?>">
    <?php if (isset($errors['email'])): ?>
      <div class="field-error"><?= h($errors['email']) ?></div>
    <?php endif; ?>
  </div>

  <div class="field">
    <div class="label-row">
      <label for="password">Password</label>
      <a class="forgot-link" href="forgot.php">Forgot password?</a>
    </div>
    <input type="password" id="password" name="password"
           autocomplete="current-password"
           data-validate="required" data-toggle-password
           class="<?= isset($errors['password']) ? 'is-invalid' : '' ?>">
    <?php if (isset($errors['password'])): ?>
      <div class="field-error"><?= h($errors['password']) ?></div>
    <?php endif; ?>
  </div>

  <button type="submit" class="btn btn-primary btn-block btn-lg">Log in</button>
</form>

<p class="auth-alt">New here? <a href="register.php">Create an account</a></p>

<div class="demo-hint">
  <span>Just looking around?</span>
  <button type="button" class="btn btn-ghost btn-sm" id="fill-demo"
          data-email="demo@uniwallet.test" data-password="demo1234">Fill in the demo account</button>
</div>

<script>
  // Demo shortcut: fills the fields, the student still presses "Log in".
  document.getElementById('fill-demo').addEventListener('click', function () {
    document.getElementById('email').value = this.dataset.email;
    document.getElementById('password').value = this.dataset.password;
    document.getElementById('password').focus();
  });
</script>

<?php require __DIR__ . '/includes/auth_footer.php'; ?>
