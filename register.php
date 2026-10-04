<?php
/**
 * Create an account. New users get the default category set seeded so the
 * expense form is usable immediately.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/finance.php';

require_guest();

$errors = [];
$name   = '';
$email  = '';

if (is_post()) {
    require_csrf();

    $name    = post('full_name');
    $email   = strtolower(post('email'));
    $password = (string) ($_POST['password'] ?? '');
    $confirm  = (string) ($_POST['password_confirm'] ?? '');

    if ($name === '' || mb_strlen($name) < 2) {
        $errors['full_name'] = 'Please enter your name (at least 2 characters).';
    } elseif (mb_strlen($name) > 100) {
        $errors['full_name'] = 'Name must be 100 characters or fewer.';
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    } elseif (mb_strlen($email) > 190) {
        $errors['email'] = 'Email must be 190 characters or fewer.';
    }

    if (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters.';
    } elseif (strlen($password) > 200) {
        $errors['password'] = 'Password must be 200 characters or fewer.';
    }

    if ($password !== $confirm) {
        $errors['password_confirm'] = 'The two passwords do not match.';
    }

    // Friendly duplicate check. The UNIQUE index below is what actually
    // guarantees it under concurrent signups.
    if (!isset($errors['email'])) {
        $check = db()->prepare('SELECT 1 FROM users WHERE email = ?');
        $check->execute([$email]);
        if ($check->fetchColumn()) {
            $errors['email'] = 'That email is already registered.';
        }
    }

    if (!$errors) {
        $pdo = db();
        try {
            $pdo->beginTransaction();

            $insert = $pdo->prepare(
                'INSERT INTO users (full_name, email, password_hash) VALUES (?, ?, ?)'
            );
            $insert->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
            $userId = (int) $pdo->lastInsertId();

            seed_default_categories($userId);

            $pdo->commit();

            login_user($userId);
            flash('ok', 'Welcome to ' . APP_NAME . ', ' . $name . '. Add your first expense to get started.');
            redirect('dashboard.php');
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            // 23000 = integrity constraint, i.e. the email was taken between
            // our check above and the insert.
            if ($e->getCode() === '23000') {
                $errors['email'] = 'That email is already registered.';
            } else {
                $errors['form'] = APP_DEBUG
                    ? 'Could not create the account: ' . $e->getMessage()
                    : 'Could not create the account. Please try again.';
            }
        }
    }
}

$page_title = 'Create account';
$page_tag   = 'Free, and it takes a minute. No bank or bKash login needed.';
$auth_side  = 'welcome';
require __DIR__ . '/includes/auth_header.php';
?>

<?php if (isset($errors['form'])): ?>
  <div class="flash flash-error"><?= h($errors['form']) ?></div>
<?php endif; ?>

<form method="post" action="register.php" data-validated novalidate>
  <?= csrf_field() ?>

  <div class="field">
    <label for="full_name">Your name</label>
    <input type="text" id="full_name" name="full_name" value="<?= h($name) ?>"
           maxlength="100" autocomplete="name"
           data-validate="required" data-autofocus
           class="<?= isset($errors['full_name']) ? 'is-invalid' : '' ?>">
    <span class="hint">Shown on your dashboard greeting.</span>
    <?php if (isset($errors['full_name'])): ?>
      <div class="field-error"><?= h($errors['full_name']) ?></div>
    <?php endif; ?>
  </div>

  <div class="field">
    <label for="email">Email</label>
    <input type="email" id="email" name="email" value="<?= h($email) ?>"
           maxlength="190" autocomplete="email"
           data-validate="required email"
           class="<?= isset($errors['email']) ? 'is-invalid' : '' ?>">
    <?php if (isset($errors['email'])): ?>
      <div class="field-error"><?= h($errors['email']) ?></div>
    <?php endif; ?>
  </div>

  <div class="field">
    <label for="password">Password</label>
    <input type="password" id="password" name="password"
           autocomplete="new-password"
           data-validate="required minlength" data-validate-minlength="8"
           data-toggle-password data-strength
           class="<?= isset($errors['password']) ? 'is-invalid' : '' ?>">
    <?php if (isset($errors['password'])): ?>
      <div class="field-error"><?= h($errors['password']) ?></div>
    <?php endif; ?>
  </div>

  <div class="field">
    <label for="password_confirm">Confirm password</label>
    <input type="password" id="password_confirm" name="password_confirm"
           autocomplete="new-password"
           data-validate="required matches" data-validate-matches="password"
           data-toggle-password
           class="<?= isset($errors['password_confirm']) ? 'is-invalid' : '' ?>">
    <?php if (isset($errors['password_confirm'])): ?>
      <div class="field-error"><?= h($errors['password_confirm']) ?></div>
    <?php endif; ?>
  </div>

  <button type="submit" class="btn btn-primary btn-block btn-lg">Create my account</button>
  <p class="form-note">Your spending is private to your account. Passwords are stored hashed.</p>
</form>

<p class="auth-alt">Already have an account? <a href="login.php">Log in</a></p>

<?php require __DIR__ . '/includes/auth_footer.php'; ?>
