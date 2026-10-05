<?php
/**
 * Shell for the logged-out pages (login, register). Expects $page_title.
 * Optional: $page_tag — one line under the heading.
 */
require_once __DIR__ . '/auth.php';

$page_title = $page_title ?? APP_NAME;
$page_tag   = $page_tag ?? '';
$auth_side  = $auth_side ?? 'khata';   // 'khata' (login) or 'welcome' (register)
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<?= theme_boot_script() ?>
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($page_title) ?> · <?= h(APP_NAME) ?></title>
<link rel="stylesheet" href="<?= h(asset('assets/css/style.css')) ?>">
<meta name="theme-color" content="#0a2e23">
<link rel="icon" href="<?= h(asset('assets/img/favicon.svg')) ?>" type="image/svg+xml">
</head>
<body>
<div class="auth">

  <aside class="auth-side">
    <a class="logo" href="index.php">
      <span class="logo-mark" aria-hidden="true"></span>
      <span class="logo-name">Uni<b>Wallet</b></span>
    </a>

    <?php if ($auth_side === 'welcome'): ?>
    <div class="auth-pitch">
      <h2>Your first month, <em>set up in a minute.</em></h2>
      <p>Create an account and you can start logging straight away.</p>

      <ul class="perk-list">
        <li><b>Categories are ready.</b> Tuition, cafeteria, transport, data packs, printing and mess rent are already set up.</li>
        <li><b>Budget what you can spend.</b> Add a monthly cap, and optionally one per category.</li>
        <li><b>Know your daily limit.</b> The dashboard shows what is safe to spend today.</li>
      </ul>

      <div class="cat-strip cat-strip-sm" aria-hidden="true">
        <?php foreach (['🎓 Tuition', '🍛 Cafeteria', '🛺 Transport', '📱 Data packs', '📚 Printing', '🏠 Mess rent'] as $chip): ?>
          <span class="cat-pill"><?= h($chip) ?></span>
        <?php endforeach; ?>
      </div>
    </div>
    <?php else: ?>
    <div class="auth-pitch">
      <h2>Welcome back. <em>Your month is waiting.</em></h2>
      <p>Pick up where you left off and see what is safe to spend today.</p>

      <div class="runway auth-runway" aria-hidden="true">
        <div class="runway-main">
          <div class="runway-kicker">Safe to spend today</div>
          <p class="runway-sentence"><b>৳517</b> a day for the next 19 days.</p>
          <div class="runway-meter">
            <div class="progress"><div class="progress-bar" style="width: 39%"></div></div>
            <div class="runway-meter-label"><span>৳6,170 spent</span><span>39% of ৳16,000</span></div>
          </div>
        </div>
      </div>
      <div class="khata auth-khata" aria-hidden="true">
        <div class="khata-row"><span class="day">Sat</span><span class="what">Rickshaw to campus</span><span class="amt">৳60</span></div>
        <div class="khata-row"><span class="day">Sat</span><span class="what">Tehari + borhani</span><span class="amt">৳140</span></div>
        <div class="khata-total"><span>Today so far</span><span class="amt">৳200</span></div>
      </div>
    </div>
    <?php endif; ?>

    <small>Independent student project — not affiliated with United International University.</small>
  </aside>

  <main class="auth-main" id="main">
    <div class="auth-card">
      <a class="logo logo-mobile" href="index.php">
        <span class="logo-mark" aria-hidden="true"></span>
        <span class="logo-name">Uni<b>Wallet</b></span>
      </a>
      <h1 class="auth-title"><?= h($page_title) ?></h1>
      <?php if ($page_tag !== ''): ?>
        <p class="auth-tag"><?= h($page_tag) ?></p>
      <?php endif; ?>

      <?php foreach (take_flashes() as $flash): ?>
        <div class="flash flash-<?= h($flash['type']) ?>" role="status">
          <?= h($flash['message']) ?>
        </div>
      <?php endforeach; ?>
