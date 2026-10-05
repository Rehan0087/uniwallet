<?php
/**
 * Top of every logged-in page: <head>, sidebar nav, flash messages.
 *
 * Set these before including:
 *   $page_title  — shown in the browser tab and page heading
 *   $active_nav  — which sidebar link to highlight
 *   $page_lead   — optional one-line subtitle under the heading
 *   $page_action — optional raw HTML for a button on the right of the heading
 */

require_once __DIR__ . '/auth.php';

$page_title  = $page_title  ?? APP_NAME;
$active_nav  = $active_nav  ?? '';
$page_lead   = $page_lead   ?? '';
$page_action = $page_action ?? '';

$nav_items = [
    ['dashboard', 'dashboard.php', '<svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>', 'Dashboard'],
    ['expenses', 'expenses.php', '<svg viewBox="0 0 24 24"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6"/></svg>', 'Expenses'],
    ['budget', 'budget.php', '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 3v9l6.4 6.4"/></svg>', 'Budget'],
    ['goals', 'goals.php', '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/></svg>', 'Savings goals'],
    ['insights', 'insights.php', '<svg viewBox="0 0 24 24"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg>', 'Insights'],
    ['categories', 'categories.php', '<svg viewBox="0 0 24 24"><path d="M20 12l-8 8-9-9V3h8z"/><circle cx="7.5" cy="7.5" r="1"/></svg>', 'Categories'],
];

$user    = current_user();
$initial = $user ? strtoupper(mb_substr($user['full_name'], 0, 1)) : '?';
$term    = uiu_term();
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= h(theme_choice($user) ?? 'system') ?>">
<head>
<meta charset="UTF-8">
<?= theme_boot_script() ?>
<meta name="csrf" content="<?= h(csrf_token()) ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($tab_title ?? $page_title) ?> · <?= h(APP_NAME) ?></title>
<link rel="stylesheet" href="<?= h(asset('assets/css/style.css')) ?>">
<meta name="theme-color" content="#0a2e23">
<link rel="icon" href="<?= h(asset('assets/img/favicon.svg')) ?>" type="image/svg+xml">
</head>
<body>

<a class="skip-link" href="#main">Skip to content</a>

<div class="app">

  <aside class="sidebar">
    <a class="logo" href="dashboard.php" aria-label="UniWallet home">
      <span class="logo-mark" aria-hidden="true"></span>
      <span class="logo-name">Uni<b>Wallet</b></span>
    </a>

    <nav class="nav" aria-label="Main">
      <?php foreach ($nav_items as [$key, $href, $icon, $label]): ?>
        <a class="nav-link<?= $active_nav === $key ? ' is-active' : '' ?>"
           href="<?= h($href) ?>"
           <?= $active_nav === $key ? 'aria-current="page"' : '' ?>>
          <span class="nav-icon" aria-hidden="true"><?= $icon /* static inline SVG */ ?></span>
          <span><?= h($label) ?></span>
        </a>
      <?php endforeach; ?>
    </nav>

    <div class="term-chip" title="Approximate calendar for UIU's three trimesters">
      <strong><?= h($term['label']) ?> trimester</strong>
      <span><?= (int) $term['days_left'] ?> days left in the term</span>
      <div class="term-track"><i style="width: <?= (int) $term['pct'] ?>%"></i></div>
    </div>

    <div class="sidebar-foot">
      <a class="who<?= $active_nav === 'profile' ? ' is-active' : '' ?>" href="profile.php"
         title="Profile &amp; settings"<?= $active_nav === 'profile' ? ' aria-current="page"' : '' ?>>
        <span class="avatar" aria-hidden="true"><?= h($initial) ?></span>
        <span class="who-text">
          <strong><?= h($user['full_name'] ?? 'Guest') ?></strong>
          <small>Profile &amp; settings</small>
        </span>
      </a>
      <div class="foot-actions">
        <a class="btn btn-ghost btn-block" href="logout.php">Log out</a>
        <button type="button" class="theme-toggle" data-theme-toggle aria-label="Switch between light and dark theme" title="Switch theme">
          <svg class="i-moon" viewBox="0 0 24 24"><path d="M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5z"/></svg>
          <svg class="i-sun" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
        </button>
      </div>
    </div>
  </aside>

  <main class="content" id="main">

    <header class="page-head">
      <div>
        <h1><?= h($page_title) ?></h1>
        <?php if ($page_lead !== ''): ?>
          <p class="lead"><?= h($page_lead) ?></p>
        <?php endif; ?>
      </div>
      <?php if ($page_action !== ''): ?>
        <div class="page-head-action"><?= $page_action ?></div>
      <?php endif; ?>
    </header>

    <?php foreach (take_flashes() as $flash): ?>
      <div class="flash flash-<?= h($flash['type']) ?>" role="status">
        <?= h($flash['message']) ?>
      </div>
    <?php endforeach; ?>
