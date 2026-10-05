<?php
/**
 * Entry point — signed-in students go to the dashboard; everyone else
 * sees the landing page.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/finance.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

$term = uiu_term();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<?= theme_boot_script() ?>
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h(APP_NAME) ?> · Budget tracker for UIU students</title>
<meta name="description" content="Track every taka of your allowance, set a monthly budget and save for what matters. Built for UIU students in Dhaka.">
<meta name="theme-color" content="#0a2e23">
<link rel="stylesheet" href="<?= h(asset('assets/css/style.css')) ?>">
<link rel="icon" href="<?= h(asset('assets/img/favicon.svg')) ?>" type="image/svg+xml">
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>

<?php foreach (take_flashes() as $flash): ?>
  <div class="site-flash site-flash-<?= h($flash['type']) ?>" role="status"><?= h($flash['message']) ?></div>
<?php endforeach; ?>

<div class="site-top">
  <div class="site-wrap">
    <header class="site-nav">
      <a class="logo" href="index.php">
        <span class="logo-mark" aria-hidden="true"></span>
        <span class="logo-name">Uni<b>Wallet</b></span>
      </a>
      <nav class="site-nav-links" aria-label="Main">
        <a href="#features">Features</a>
        <a href="#calculator">Calculator</a>
        <a href="#how">How it works</a>
        <a href="#faq">FAQ</a>
        <a href="login.php">Log in</a>
        <button type="button" class="theme-toggle" data-theme-toggle aria-label="Switch between light and dark theme" title="Switch theme">
          <svg class="i-moon" viewBox="0 0 24 24"><path d="M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5z"/></svg>
          <svg class="i-sun" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
        </button>
        <a class="btn btn-primary btn-sm" href="register.php">Create account</a>
      </nav>
    </header>
  </div>
</div>

<div class="site-wrap">
  <main id="main">
    <section class="hero">
      <div>
        <span class="eyebrow"><i></i> Made for UIU students · <?= h($term['label']) ?></span>
        <h1>Make your allowance last the <span>whole trimester.</span></h1>
        <p class="sub">Log rickshaw fares, cafeteria lunches and data packs in taka.
          Set a monthly budget and always know what is safe to spend today.</p>
        <div class="btn-row">
          <a class="btn btn-primary btn-lg" href="register.php">Create a free account</a>
          <a class="btn btn-ghost btn-lg" href="login.php">Try the demo</a>
        </div>
        <ul class="trust">
          <li>Free to use</li>
          <li>Everything in ৳</li>
          <li>No bank or bKash login</li>
        </ul>
        <p class="hero-bn"><b>খরচের হিসাব</b> — your khata, without the pencil and the eraser.</p>
      </div>

      <div class="hero-card" aria-hidden="true">
        <div class="khata">
          <div class="khata-head">This week · Bashundhara</div>
          <div class="khata-row"><span class="day">Sat</span><span class="what">Rickshaw to campus</span><span class="amt">৳60</span></div>
          <div class="khata-row"><span class="day">Sat</span><span class="what">Tehari + borhani</span><span class="amt">৳140</span></div>
          <div class="khata-row"><span class="day">Sun</span><span class="what">Photocopy, lab manual</span><span class="amt">৳85</span></div>
          <div class="khata-row"><span class="day">Mon</span><span class="what">CNG to Badda</span><span class="amt">৳120</span></div>
          <div class="khata-row"><span class="day">Mon</span><span class="what">Grameenphone 7-day pack</span><span class="amt">৳199</span></div>
          <div class="khata-row"><span class="day">Tue</span><span class="what">Cha + singara, 3 friends</span><span class="amt">৳90</span></div>
          <div class="khata-total"><span>Total</span><span class="amt">৳694</span></div>
        </div>
        <div class="hero-badge">
          <small>Safe to spend today</small>
          <strong>৳532</strong>
          <span>of ৳16,000 this month</span>
        </div>
      </div>
    </section>

    <!-- ------------------------------------------------------------ features -->
    <section class="section" id="features">
      <h2 class="section-title">Everything for one trimester of money, nothing more.</h2>
      <p class="section-lead">No bank connection, no ads, no setup call. Add what you spend and the numbers do the rest.</p>

      <div class="feature-grid">
        <article class="feature">
          <div class="feature-icon"><svg viewBox="0 0 24 24"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6"/></svg></div>
          <h3>Log an expense in seconds</h3>
          <p>Amount, category, a short note. Filter by date or category later, and download everything as a CSV for your own records.</p>
        </article>
        <article class="feature">
          <div class="feature-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 3v9l6.4 6.4"/></svg></div>
          <h3>Budgets that warn you early</h3>
          <p>Cap the whole month and each category. Bars turn amber at 80% and red when you cross the line, before the month is over.</p>
        </article>
        <article class="feature">
          <div class="feature-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/></svg></div>
          <h3>Savings goals with a pace</h3>
          <p>Saving for next trimester's tuition or a laptop? Set a target and a deadline and see how much to put aside each month.</p>
        </article>
        <article class="feature">
          <div class="feature-icon"><svg viewBox="0 0 24 24"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg></div>
          <h3>See where it went</h3>
          <p>A category breakdown, a six-month trend and a pace line against your budget show which habit is eating the allowance.</p>
        </article>
        <article class="feature">
          <div class="feature-icon"><svg viewBox="0 0 24 24"><path d="M20 12l-8 8-9-9V3h8z"/><circle cx="7.5" cy="7.5" r="1"/></svg></div>
          <h3>Categories for UIU life</h3>
          <p>Tuition, cafeteria, rickshaw and CNG, data packs, printing, mess rent. Rename them or add your own.</p>
        </article>
        <article class="feature">
          <div class="feature-icon"><svg viewBox="0 0 24 24"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg></div>
          <h3>Your numbers stay yours</h3>
          <p>Passwords are hashed and every query is limited to your own account. Nobody else can see your hisab.</p>
        </article>
      </div>
    </section>

    <!-- -------------------------------------------------------------- preview -->
    <section class="section preview" aria-labelledby="preview-title">
      <div class="preview-copy">
        <h2 class="section-title" id="preview-title">One screen answers: can I afford this today?</h2>
        <p class="section-lead">Your dashboard turns what is left of the month into a daily limit. Spend under it and you finish the month with money still in your wallet.</p>
        <a class="btn btn-ghost" href="login.php">Open the demo account</a>
      </div>

      <div class="preview-frame" aria-hidden="true">
        <div class="runway">
          <div class="runway-main">
            <div class="runway-kicker">Safe to spend today</div>
            <p class="runway-sentence"><b>৳517</b> a day for the next 19 days.</p>
            <p class="runway-sub">৳9,830 left of your ৳16,000 budget.</p>
            <div class="runway-meter">
              <div class="progress"><div class="progress-bar" style="width: 39%"></div></div>
              <div class="runway-meter-label"><span>৳6,170 spent</span><span>39% used</span></div>
            </div>
          </div>
          <div class="runway-side">
            <div><div class="mini-label">Rent &amp; Mess</div><div class="mini-value">৳7,000</div><div class="mini-note">paid</div></div>
            <div><div class="mini-label">Saved for goals</div><div class="mini-value">৳50,000</div><div class="mini-note is-ok">on track</div></div>
          </div>
        </div>
        <div class="card preview-bars">
          <div class="progress-row">
            <div class="progress-meta"><span class="name">🍛 Food &amp; Cafeteria</span><span class="figures">৳3,420 of ৳4,500 (76%)</span></div>
            <div class="progress"><div class="progress-bar" style="width: 76%"></div></div>
          </div>
          <div class="progress-row">
            <div class="progress-meta"><span class="name">🛺 Transport</span><span class="figures">৳2,310 of ৳2,500 (92%)</span></div>
            <div class="progress"><div class="progress-bar is-warn" style="width: 92%"></div></div>
          </div>
          <div class="progress-row">
            <div class="progress-meta"><span class="name">🎬 Hangout &amp; Fun</span><span class="figures">৳1,150 of ৳1,000 (115%)</span></div>
            <div class="progress"><div class="progress-bar is-over" style="width: 100%"></div></div>
          </div>
        </div>
      </div>
    </section>
  </main>
</div>

<!-- ------------------------------------------------------------ calculator -->
<section class="band" id="calculator">
  <div class="site-wrap calc">
    <div>
      <h2>How far does your allowance go?</h2>
      <p class="section-lead">Drag to your monthly allowance. This is a starting split for a student living near campus; you set your own limits once you sign up.</p>

      <div class="calc-input">
        <label for="allowance">Monthly allowance</label>
        <div class="calc-amount" id="allowance-out" aria-live="polite">৳16,000</div>
        <input type="range" id="allowance" min="5000" max="50000" step="500" value="16000">
        <div class="calc-scale"><span>৳5,000</span><span>৳50,000</span></div>
      </div>

      <div class="calc-days">
        <div><small>Per day</small><strong id="calc-day">৳533</strong></div>
        <div><small>Per week</small><strong id="calc-week">৳3,733</strong></div>
      </div>
    </div>

    <div class="calc-split" id="calc-split" aria-live="polite">
      <!-- filled in by assets/js/landing.js -->
    </div>
  </div>
  <div class="site-wrap">
    <div class="cat-strip">
      <?php foreach (DEFAULT_CATEGORIES as [$name, $icon]): ?>
        <span class="cat-pill"><span aria-hidden="true"><?= $icon ?></span> <?= h($name) ?></span>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<div class="site-wrap">
  <!-- ----------------------------------------------------------- how it works -->
  <section class="section" id="how">
    <h2 class="section-title">Set up once, then it takes a minute a day.</h2>
    <ol class="steps">
      <li>
        <span class="step-n">1</span>
        <h3>Set this month's budget</h3>
        <p>Enter what you can spend this month, and optionally a cap for each category.</p>
      </li>
      <li>
        <span class="step-n">2</span>
        <h3>Log as you spend</h3>
        <p>Add the rickshaw fare or the tehari right after you pay, while you still remember the amount.</p>
      </li>
      <li>
        <span class="step-n">3</span>
        <h3>Check your daily limit</h3>
        <p>The dashboard shows what is left and what is safe to spend each day until the month ends.</p>
      </li>
    </ol>
  </section>

  <!-- ------------------------------------------------------------------- FAQ -->
  <section class="section faq" id="faq">
    <h2 class="section-title">Questions students ask</h2>
    <div class="faq-list">
      <details>
        <summary>Is UniWallet free?</summary>
        <p>Yes. There is nothing to pay and no ads.</p>
      </details>
      <details>
        <summary>Does it connect to bKash, Nagad or my bank?</summary>
        <p>No. You add expenses yourself, so nobody needs your mobile banking or bank login.</p>
      </details>
      <details>
        <summary>Who can see my spending?</summary>
        <p>Only you. Every record belongs to your account, and passwords are stored hashed, never as plain text.</p>
      </details>
      <details>
        <summary>Can I keep my records outside the app?</summary>
        <p>Yes. Export any date range as a CSV file and open it in Excel or Google Sheets.</p>
      </details>
      <details>
        <summary>Is this an official UIU service?</summary>
        <p>No. UniWallet is an independent student project and is not affiliated with United International University.</p>
      </details>
    </div>
  </section>

  <section class="cta">
    <div>
      <h2>Start this month with a number, not a guess.</h2>
      <p>Free, and your data stays in your own account.</p>
    </div>
    <a class="btn btn-gold btn-lg" href="register.php">Create an account</a>
  </section>

  <footer class="site-foot">
    <div class="site-foot-grid">
      <div class="site-foot-about">
        <a class="logo" href="index.php">
          <span class="logo-mark" aria-hidden="true"></span>
          <span class="logo-name">Uni<b>Wallet</b></span>
        </a>
        <p>A budget tracker for UIU students. Log what you spend in taka and make your allowance last the whole trimester.</p>
      </div>
      <nav aria-label="Explore">
        <h3>Explore</h3>
        <a href="#features">Features</a>
        <a href="#calculator">Allowance calculator</a>
        <a href="#how">How it works</a>
        <a href="#faq">FAQ</a>
      </nav>
      <nav aria-label="Account">
        <h3>Account</h3>
        <a href="register.php">Create an account</a>
        <a href="login.php">Log in</a>
        <a href="login.php">Try the demo</a>
      </nav>
    </div>
    <div class="site-foot-base">
      <span>© <?= date('Y') ?> <?= h(APP_NAME) ?> · Made for students in Dhaka</span>
      <span>An independent student project, not affiliated with United International University.</span>
    </div>
  </footer>
</div>

<script src="<?= h(asset('assets/js/theme.js')) ?>"></script>
<script src="<?= h(asset('assets/js/landing.js')) ?>"></script>
</body>
</html>
