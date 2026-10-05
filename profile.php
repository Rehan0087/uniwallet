<?php
/**
 * Profile & settings — personal details, password, preferences, data export
 * and account deletion.
 *
 * Anything that changes a login credential (email, password) or destroys
 * data asks for the current password again, so a borrowed laptop with an
 * open session can't be used to lock the owner out or wipe their records.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/finance.php';

require_login();
$userId = current_user_id();
$user   = current_user();

/** Check the signed-in user's current password. */
function password_matches(int $userId, string $password): bool
{
    $stmt = db()->prepare('SELECT password_hash FROM users WHERE user_id = ?');
    $stmt->execute([$userId]);
    $hash = $stmt->fetchColumn();
    return $hash !== false && password_verify($password, (string) $hash);
}

$tab = 'profile.php';

if (is_post()) {
    require_csrf();
    $action = post('action');

    // ----------------------------------------------------- personal details
    if ($action === 'details') {
        $name    = post('full_name');
        $email   = strtolower(post('email'));
        $current = (string) ($_POST['current_password'] ?? '');
        $errors  = [];

        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $errors[] = 'Enter your name (2 to 100 characters).';
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
            $errors[] = 'Enter a valid email address.';
        }

        $emailChanged = $email !== strtolower($user['email']);
        if (!$errors && $emailChanged) {
            if (!password_matches($userId, $current)) {
                $errors[] = 'Enter your current password to change your email.';
            } else {
                $dup = db()->prepare('SELECT 1 FROM users WHERE email = ? AND user_id <> ?');
                $dup->execute([$email, $userId]);
                if ($dup->fetchColumn()) {
                    $errors[] = 'That email is already registered to another account.';
                }
            }
        }

        if ($errors) {
            flash('error', implode(' ', $errors));
        } else {
            db()->prepare('UPDATE users SET full_name = ?, email = ? WHERE user_id = ?')
                ->execute([$name, $email, $userId]);
            flash('ok', 'Your details are saved.');
        }
        redirect($tab);
    }

    // ---------------------------------------------------------------- password
    if ($action === 'password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $new     = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');

        if (!password_matches($userId, $current)) {
            flash('error', 'Your current password is incorrect.');
        } elseif (strlen($new) < 8 || strlen($new) > 200) {
            flash('error', 'The new password must be 8 to 200 characters.');
        } elseif ($new !== $confirm) {
            flash('error', 'The two new passwords do not match.');
        } else {
            db()->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?')
                ->execute([password_hash($new, PASSWORD_DEFAULT), $userId]);
            session_regenerate_id(true);   // old session ids stop being useful
            flash('ok', 'Password changed.');
        }
        redirect($tab . '#password');
    }

    // ------------------------------------------------------------- preferences
    if ($action === 'prefs') {
        $warn = (int) post('warn_pct');
        if ($warn < 50 || $warn > 95) {
            flash('error', 'Pick a warning level between 50% and 95%.');
        } else {
            db()->prepare('UPDATE users SET warn_pct = ? WHERE user_id = ?')->execute([$warn, $userId]);
            flash('ok', 'Preferences saved. Budget bars now turn amber at ' . $warn . '%.');
        }
        redirect($tab . '#preferences');
    }

    // ------------------------------------------------------------------- theme
    if ($action === 'theme') {
        $theme = post('theme');
        $ajax  = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';

        if (in_array($theme, ['light', 'dark', 'system'], true)) {
            db()->prepare('UPDATE users SET theme = ? WHERE user_id = ?')->execute([$theme, $userId]);
            if ($ajax) {
                http_response_code(204);   // the page already switched itself
                exit;
            }
            flash('ok', 'Theme saved.');
        } elseif ($ajax) {
            http_response_code(422);
            exit;
        } else {
            flash('error', 'Pick light, dark or system.');
        }
        redirect($tab . '#appearance');
    }

    // ----------------------------------------------------------------- delete
    if ($action === 'delete') {
        $current = (string) ($_POST['current_password'] ?? '');
        if (post('confirm_text') !== 'DELETE') {
            flash('error', 'Type DELETE in capitals to confirm.');
            redirect($tab . '#danger');
        }
        if (!password_matches($userId, $current)) {
            flash('error', 'Your current password is incorrect, so nothing was deleted.');
            redirect($tab . '#danger');
        }

        // Foreign keys cascade: expenses, budgets, goals and categories go with the user.
        db()->prepare('DELETE FROM users WHERE user_id = ?')->execute([$userId]);

        logout_user();
        session_start();
        flash('info', 'Your account and all its data have been deleted.');
        redirect('index.php');
    }

    redirect($tab);
}

// --------------------------------------------------------------- load data

$statsStmt = db()->prepare(
    'SELECT COUNT(*) AS n, COALESCE(SUM(amount), 0) AS total, MIN(spent_on) AS first_day
       FROM expenses WHERE user_id = ?'
);
$statsStmt->execute([$userId]);
$stats = $statsStmt->fetch();

$goalCount = count(goals_for($userId));
$catCount  = count(categories_for($userId));
$since     = nice_date($user['created_at']);
$initial   = strtoupper(mb_substr($user['full_name'], 0, 1));

$page_title = 'Profile & settings';
$active_nav = 'profile';
$page_lead  = 'Your details, password and how UniWallet behaves for you.';

require __DIR__ . '/includes/header.php';
?>

<!-- ----------------------------------------------------------- identity card -->
<section class="profile-hero">
  <span class="avatar avatar-lg" aria-hidden="true"><?= h($initial) ?></span>
  <div class="profile-id">
    <h2><?= h($user['full_name']) ?></h2>
    <p><?= h($user['email']) ?> · Member since <?= h($since) ?></p>
  </div>
  <dl class="profile-stats">
    <div><dt>Expenses logged</dt><dd><?= number_format((int) $stats['n']) ?></dd></div>
    <div><dt>Total tracked</dt><dd><?= h(money($stats['total'])) ?></dd></div>
    <div><dt>Savings goals</dt><dd><?= (int) $goalCount ?></dd></div>
    <div><dt>Categories</dt><dd><?= (int) $catCount ?></dd></div>
  </dl>
</section>

<div class="settings">

  <nav class="settings-nav" aria-label="Settings sections">
    <a href="#details" class="is-active">Profile</a>
    <a href="#appearance">Appearance</a>
    <a href="#alerts">Budget alerts</a>
    <a href="#regional">Regional</a>
    <a href="#password">Password</a>
    <a href="#data">Your data</a>
    <a href="#danger" class="is-danger">Delete account</a>
  </nav>

  <div class="settings-main">

    <!-- ------------------------------------------------------------ details -->
    <section class="card" id="details">
      <div class="card-head"><div><h2>Profile</h2><p>Shown in the greeting on your dashboard.</p></div></div>
      <form method="post" action="profile.php" data-validated novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="details">
        <div class="stack">
          <div class="form-grid">
            <div class="field">
              <label for="full_name">Your name</label>
              <input type="text" id="full_name" name="full_name" maxlength="100" autocomplete="name"
                     value="<?= h($user['full_name']) ?>" data-validate="required">
            </div>
            <div class="field">
              <label for="email">Email</label>
              <input type="email" id="email" name="email" maxlength="190" autocomplete="email"
                     value="<?= h($user['email']) ?>" data-validate="required email">
            </div>
          </div>
          <div class="field" id="email-confirm" hidden>
            <label for="details_password">Current password</label>
            <input type="password" id="details_password" name="current_password" autocomplete="current-password"
                   data-toggle-password>
            <span class="hint">Needed because you are changing your email.</span>
          </div>
          <div><button type="submit" class="btn btn-primary">Save profile</button></div>
        </div>
      </form>
    </section>

    <!-- ---------------------------------------------------------- appearance -->
    <section class="card" id="appearance">
      <div class="card-head">
        <div><h2>Appearance</h2><p>Choose how UniWallet looks. It applies straight away and follows you to other devices.</p></div>
        <span class="save-state" id="theme-state" role="status" aria-live="polite"></span>
      </div>
      <form method="post" action="profile.php" id="theme-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="theme">
        <?php $currentTheme = theme_choice($user) ?? 'system'; ?>
        <div class="theme-options" role="radiogroup" aria-label="Theme">
          <?php foreach (['light' => 'Light', 'dark' => 'Dark', 'system' => 'Match my device'] as $value => $label): ?>
            <label class="theme-option">
              <input type="radio" name="theme" value="<?= $value ?>" <?= $currentTheme === $value ? 'checked' : '' ?>>
              <span class="theme-card">
                <span class="theme-preview theme-preview-<?= $value ?>" aria-hidden="true">
                  <i class="tp-side"></i><i class="tp-bar tp-bar-1"></i><i class="tp-bar tp-bar-2"></i><i class="tp-bar tp-bar-3"></i>
                </span>
                <span class="theme-label"><?= h($label) ?></span>
              </span>
            </label>
          <?php endforeach; ?>
        </div>
        <noscript><div class="mt-2"><button type="submit" class="btn btn-primary">Save theme</button></div></noscript>
      </form>
    </section>

    <!-- -------------------------------------------------------- budget alerts -->
    <section class="card" id="alerts">
      <div class="card-head"><div><h2>Budget alerts</h2><p>When should a budget bar and the dashboard start warning you?</p></div></div>
      <form method="post" action="profile.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="prefs">
        <div class="stack">
          <div class="field">
            <label for="warn_pct">Turn amber at <output id="warn-out"><?= (int) warn_threshold() ?>%</output></label>
            <input type="range" id="warn_pct" name="warn_pct" min="50" max="95" step="5"
                   value="<?= (int) warn_threshold() ?>" class="range-light">
          </div>
          <div class="alert-preview" aria-hidden="true">
            <div class="alert-preview-bar"><i id="ap-ok"></i><i id="ap-warn"></i><i id="ap-over"></i></div>
            <div class="alert-preview-key"><span class="k-ok">On track</span><span class="k-warn">Getting close</span><span class="k-over">Over at 100%</span></div>
          </div>
          <div><button type="submit" class="btn btn-primary">Save alert level</button></div>
        </div>
      </form>
    </section>

    <!-- ----------------------------------------------------------- regional -->
    <section class="card" id="regional">
      <div class="card-head"><div><h2>Regional</h2><p>Set for students in Dhaka. These come from the app’s configuration.</p></div></div>
      <dl class="kv">
        <div><dt>Currency</dt><dd>Bangladeshi taka (<?= h(CURRENCY) ?>)</dd></div>
        <div><dt>Time zone</dt><dd><?= h(APP_TIMEZONE) ?></dd></div>
        <div><dt>Number format</dt><dd>Lakh and crore, e.g. 12,34,567</dd></div>
        <div><dt>Trimesters</dt><dd>Spring, Summer and Fall</dd></div>
      </dl>
    </section>

    <!-- ------------------------------------------------------------ password -->
    <section class="card" id="password">
      <div class="card-head"><div><h2>Password</h2><p>Use at least 8 characters. Changing it signs out any other session.</p></div></div>
      <form method="post" action="profile.php" data-validated novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="password">
        <div class="stack">
          <div class="field">
            <label for="current_password">Current password</label>
            <input type="password" id="current_password" name="current_password" autocomplete="current-password"
                   data-validate="required" data-toggle-password>
          </div>
          <div class="form-grid">
            <div class="field">
              <label for="new_password">New password</label>
              <input type="password" id="new_password" name="new_password" autocomplete="new-password"
                     data-validate="required minlength" data-validate-minlength="8" data-toggle-password data-strength>
            </div>
            <div class="field">
              <label for="confirm_password">Confirm new password</label>
              <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password"
                     data-validate="required matches" data-validate-matches="new_password" data-toggle-password>
            </div>
          </div>
          <div><button type="submit" class="btn btn-primary">Change password</button></div>
        </div>
      </form>
    </section>

    <!-- ----------------------------------------------------------- your data -->
    <section class="card" id="data">
      <div class="card-head"><div><h2>Your data</h2><p>It belongs to you. Take a copy any time.</p></div></div>
      <p class="muted data-copy">
        Download every expense you have logged as a CSV file that opens in Excel or Google Sheets.
        <?php if ($stats['first_day']): ?>Your records start on <?= h(nice_date($stats['first_day'])) ?>.<?php endif; ?>
      </p>
      <div class="btn-row mt-2">
        <a class="btn btn-ghost" href="export.php?from=&amp;to=">Download all expenses (CSV)</a>
        <a class="btn btn-ghost" href="expenses.php">Review expenses</a>
      </div>
    </section>

    <!-- --------------------------------------------------------- danger zone -->
    <section class="card danger-zone" id="danger">
      <div class="card-head">
        <div>
          <h2>Delete account</h2>
          <p>Permanently removes your account, expenses, budgets, goals and categories. This cannot be undone.</p>
        </div>
        <button type="button" class="btn btn-danger-solid btn-sm" id="danger-toggle" aria-expanded="false" aria-controls="danger-form">Delete my account…</button>
      </div>
      <form method="post" action="profile.php" id="danger-form" hidden
            data-confirm="Last check: delete your account and all of your data for good?">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete">
        <div class="form-grid">
          <div class="field">
            <label for="confirm_text">Type DELETE to confirm</label>
            <input type="text" id="confirm_text" name="confirm_text" autocomplete="off" placeholder="DELETE">
          </div>
          <div class="field">
            <label for="del_password">Current password</label>
            <input type="password" id="del_password" name="current_password" autocomplete="current-password" data-toggle-password>
          </div>
          <div class="field">
            <button type="submit" class="btn btn-danger-solid">Delete everything</button>
          </div>
        </div>
      </form>
    </section>

  </div>
</div>

<?php
$page_scripts = '<script src="' . h(asset('assets/js/profile.js')) . '"></script>';
require __DIR__ . '/includes/footer.php';
