<?php
/**
 * Friendly error pages (403, 404, 500).
 *
 * Deliberately self-contained: it needs only config + helpers, never the
 * database, so it still renders when MySQL is down — which is exactly when
 * a 500 happens.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/helpers.php';

/** URL path of the project folder, e.g. "/uniwallet" or "" — so assets load from any URL depth. */
function app_base_url(): string
{
    $root = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
    $app  = realpath(__DIR__ . '/..') ?: '';

    if ($root !== '' && str_starts_with($app, $root)) {
        return str_replace('\\', '/', substr($app, strlen($root)));
    }
    return '';
}

/**
 * Send the status code and render the page, then stop.
 * In debug mode the exception message is shown in a collapsed block.
 */
function show_error_page(int $code, ?Throwable $e = null): never
{
    $pages = [
        403 => [
            'title' => 'You can’t open this page',
            'lead'  => 'Your account doesn’t have access to it, or it is only meant for the app itself.',
            'note'  => 'Access denied',
        ],
        404 => [
            'title' => 'That page isn’t in the ledger',
            'lead'  => 'The link may be old or mistyped. Head back and try again from there.',
            'note'  => 'Not found',
        ],
        500 => [
            'title' => 'Something went wrong on our side',
            'lead'  => 'It isn’t anything you did. Try again in a moment. If it keeps happening, check that MySQL is running.',
            'note'  => 'Server error',
        ],
    ];
    $page = $pages[$code] ?? $pages[500];
    $code = isset($pages[$code]) ? $code : 500;

    if (!headers_sent()) {
        http_response_code($code);
    }

    $base      = app_base_url();
    // Read the session (if the visitor has one) just to tailor the buttons; no DB needed.
    if (session_status() === PHP_SESSION_NONE && !empty($_COOKIE[session_name()]) && !headers_sent()) {
        session_start(['read_and_close' => true]);
    }
    $loggedIn  = !empty($_SESSION['user_id']);
    $home      = $loggedIn ? $base . '/dashboard.php' : $base . '/index.php';
    $homeLabel = $loggedIn ? 'Go to dashboard' : 'Go to the home page';

    $requested = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    if (mb_strlen($requested) > 40) {
        $requested = mb_substr($requested, 0, 37) . '…';
    }

    $links = [
        ['expenses.php',   'Expenses'],
        ['budget.php',     'Budget'],
        ['goals.php',      'Savings goals'],
        ['insights.php',   'Insights'],
    ];
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<?= theme_boot_script() ?>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= (int) $code ?> · <?= h($page['note']) ?> · <?= h(APP_NAME) ?></title>
<link rel="stylesheet" href="<?= h($base . '/' . asset('assets/css/style.css')) ?>">
<meta name="theme-color" content="#0a2e23">
<link rel="icon" href="<?= h($base . '/' . asset('assets/img/favicon.svg')) ?>" type="image/svg+xml">
</head>
<body>
<div class="auth">

  <aside class="auth-side">
    <a class="logo" href="<?= h($home) ?>">
      <span class="logo-mark" aria-hidden="true"></span>
      <span class="logo-name">Uni<b>Wallet</b></span>
    </a>

    <div class="auth-pitch">
      <div class="err-code" aria-hidden="true"><?= (int) $code ?></div>
      <div class="khata err-khata" aria-hidden="true">
        <?php if ($code === 404): ?>
          <div class="khata-row"><span class="day">Req</span><span class="what"><?= h($requested) ?></span><span class="amt">৳—</span></div>
          <div class="khata-row"><span class="day">Page</span><span class="what">No such entry</span><span class="amt">—</span></div>
          <div class="khata-total"><span>Balance</span><span class="amt">৳0</span></div>
        <?php elseif ($code === 403): ?>
          <div class="khata-row"><span class="day">Req</span><span class="what"><?= h($requested) ?></span><span class="amt">৳—</span></div>
          <div class="khata-row"><span class="day">Page</span><span class="what">Locked</span><span class="amt">—</span></div>
          <div class="khata-total"><span>Balance</span><span class="amt">৳0</span></div>
        <?php else: ?>
          <div class="khata-row"><span class="day">Req</span><span class="what"><?= h($requested) ?></span><span class="amt">৳—</span></div>
          <div class="khata-row"><span class="day">Book</span><span class="what">Page torn, retry</span><span class="amt">—</span></div>
          <div class="khata-total"><span>Balance</span><span class="amt">৳?</span></div>
        <?php endif; ?>
      </div>
    </div>

    <small>Independent student project — not affiliated with United International University.</small>
  </aside>

  <main class="auth-main" id="main">
    <div class="auth-card">
      <a class="logo logo-mobile" href="<?= h($home) ?>">
        <span class="logo-mark" aria-hidden="true"></span>
        <span class="logo-name">Uni<b>Wallet</b></span>
      </a>
      <p class="err-eyebrow"><?= (int) $code ?> · <?= h($page['note']) ?></p>
      <h1 class="auth-title err-title"><?= h($page['title']) ?></h1>
      <p class="auth-tag"><?= h($page['lead']) ?></p>

      <div class="btn-row">
        <a class="btn btn-primary btn-lg" href="<?= h($home) ?>"><?= h($homeLabel) ?></a>
        <?php if ($code === 500): ?>
          <a class="btn btn-ghost btn-lg" href="<?= h($_SERVER['REQUEST_URI'] ?? '') ?>">Try again</a>
        <?php endif; ?>
      </div>

      <?php if ($loggedIn && $code !== 500): ?>
        <p class="err-links-label">Or jump to</p>
        <div class="err-links">
          <?php foreach ($links as [$file, $label]): ?>
            <a class="chip chip-btn" href="<?= h($base . '/' . $file) ?>"><?= h($label) ?></a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if (APP_DEBUG && $e !== null): ?>
        <details class="err-debug">
          <summary>Technical details (visible because APP_DEBUG is on)</summary>
          <pre><?= h(get_class($e) . ': ' . $e->getMessage() . "\n" . basename($e->getFile()) . ':' . $e->getLine()) ?></pre>
        </details>
      <?php endif; ?>
    </div>
  </main>

</div>
</body>
</html>
<?php
    exit;
}
