<?php
/**
 * Monthly budgets.
 *
 * A budget is per month ('YYYY-MM'): one whole-month cap plus optional caps
 * per category. Leaving a field blank clears that limit.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/finance.php';

require_login();
$userId = current_user_id();

if (is_post()) {
    require_csrf();

    $month = post('month');
    if (!valid_month($month)) {
        $month = current_month();
    }

    if (post('action') === 'save') {
        // Whole-month cap. Blank clears it.
        $totalRaw = post('total_limit');
        save_overall_limit($userId, $month, $totalRaw === '' ? null : parse_amount($totalRaw));

        // Per-category caps arrive as limits[<category_id>].
        $submitted = $_POST['limits'] ?? [];
        if (is_array($submitted)) {
            foreach ($submitted as $categoryId => $raw) {
                if (!ctype_digit((string) $categoryId)) {
                    continue;
                }
                $raw = trim((string) $raw);
                save_category_limit($userId, $month, (int) $categoryId, $raw === '' ? null : parse_amount($raw));
            }
        }

        flash('ok', 'Budget saved for ' . month_label($month) . '.');
    }

    if (post('action') === 'copy') {
        // Copy every limit from the previous month into this one.
        $prev      = shift_month($month, -1);
        $prevTotal = overall_limit($userId, $prev);
        $prevCats  = category_limits($userId, $prev);

        if ($prevTotal === null && !$prevCats) {
            flash('error', 'There are no limits in ' . month_label($prev) . ' to copy.');
        } else {
            save_overall_limit($userId, $month, $prevTotal);
            foreach ($prevCats as $categoryId => $limit) {
                if (owns_category($userId, (int) $categoryId)) {
                    save_category_limit($userId, $month, (int) $categoryId, $limit);
                }
            }
            flash('ok', 'Copied the limits from ' . month_label($prev) . '. Adjust them and save.');
        }
    }

    redirect('budget.php?month=' . urlencode($month));
}

// --------------------------------------------------------------- load data

$month      = month_param();
$categories = categories_for($userId);
$spendByCat = spend_by_category($userId, $month);
$limits     = category_limits($userId, $month);
$totalLimit = overall_limit($userId, $month);
$spent      = month_spend($userId, $month);
$entries    = month_expense_count($userId, $month);

$remaining = $totalLimit !== null ? $totalLimit - $spent : null;

// "Days left" only means something for the month you are currently in.
$daysInMonth   = (int) date('t', strtotime($month . '-01'));
$isThisMonth   = $month === current_month();
$isPastMonth   = $month < current_month();
$daysRemaining = $isThisMonth ? max(1, $daysInMonth - (int) date('j') + 1) : $daysInMonth;
$perDay        = ($remaining !== null && $remaining > 0) ? $remaining / $daysRemaining : null;

// Categories with either a limit or some spending, biggest spend first.
$tracked = [];
foreach ($categories as $category) {
    $id    = (int) $category['category_id'];
    $rowSpend = $spendByCat[$id]['total'] ?? 0.0;
    $rowLimit = $limits[$id] ?? null;
    if ($rowSpend > 0 || $rowLimit !== null) {
        $tracked[] = ['category' => $category, 'spent' => $rowSpend, 'limit' => $rowLimit];
    }
}
usort($tracked, static fn(array $a, array $b): int => $b['spent'] <=> $a['spent']);

$sumOfCategoryLimits = array_sum($limits);

// Month-end projection at the current pace (only meaningful mid-month).
$dayOfMonth = $isThisMonth ? (int) date('j') : $daysInMonth;
$projected  = $dayOfMonth > 0 ? $spent / $dayOfMonth * $daysInMonth : 0.0;
$showProjection = $isThisMonth && $spent > 0 && $dayOfMonth >= 3;
$prevHasLimits  = overall_limit($userId, shift_month($month, -1)) !== null
               || category_limits($userId, shift_month($month, -1));
$state = $totalLimit !== null ? progress_state($spent, $totalLimit) : '';

$page_title = 'Budget';
$active_nav = 'budget';
$page_lead  = 'Decide what each month can cost, then watch it against what you actually spend.';
$page_scripts = '<script src="assets/js/budget.js"></script>';

require __DIR__ . '/includes/header.php';
?>

<!-- ----------------------------------------------------------------- hero -->
<section class="runway" aria-label="Budget summary">
  <div class="runway-main">
    <div class="runway-kicker"><?= $isThisMonth ? 'Safe to spend today' : h(month_label($month)) ?></div>
    <?php if ($totalLimit === null): ?>
      <p class="runway-sentence">No limit set for <?= h(month_label($month)) ?> yet.</p>
      <p class="runway-sub">Enter a whole-month limit below<?= $prevHasLimits ? ', or copy last month\'s limits' : '' ?>.</p>
    <?php elseif ($month > current_month()): ?>
      <p class="runway-sentence">Planned: <b><?= h(money($totalLimit)) ?></b> for <?= h(month_label($month)) ?>.</p>
      <p class="runway-sub">Nothing is spent yet. You can still change the limits below.</p>
    <?php elseif ($isThisMonth && $perDay !== null): ?>
      <p class="runway-sentence"><b><?= h(money($perDay)) ?></b> a day for the next <?= (int) $daysRemaining ?> day<?= $daysRemaining === 1 ? '' : 's' ?>.</p>
      <p class="runway-sub"><?= h(money($remaining)) ?> left of your <?= h(money($totalLimit)) ?> limit.</p>
    <?php elseif ($remaining !== null && $remaining < 0): ?>
      <p class="runway-sentence">Over the limit by <b><?= h(money(abs($remaining))) ?></b>.</p>
      <p class="runway-sub">You spent <?= h(money($spent)) ?> against a <?= h(money($totalLimit)) ?> limit.</p>
    <?php else: ?>
      <p class="runway-sentence">You spent <b><?= h(money($spent)) ?></b> of <?= h(money($totalLimit)) ?>.</p>
      <p class="runway-sub"><?= h(money(max(0, $remaining))) ?> was left over.</p>
    <?php endif; ?>

    <?php if ($totalLimit !== null): ?>
      <div class="runway-meter">
        <div class="progress" role="progressbar" aria-valuemin="0" aria-valuemax="100"
             aria-valuenow="<?= (int) round(progress_pct($spent, $totalLimit)) ?>" aria-label="Budget used">
          <div class="progress-bar is-<?= h($state) ?>" style="width: <?= round(progress_width($spent, $totalLimit), 2) ?>%"></div>
        </div>
        <div class="runway-meter-label">
          <span><?= h(money($spent)) ?> spent · <?= number_format($entries) ?> entr<?= $entries === 1 ? 'y' : 'ies' ?></span>
          <span><?= round(progress_pct($spent, $totalLimit)) ?>% used</span>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <div class="runway-side">
    <div>
      <div class="mini-label">Status</div>
      <div class="mini-value"><?= $totalLimit === null ? '—' : ($state === 'over' ? 'Over' : ($state === 'warn' ? 'Close' : 'On track')) ?></div>
      <div class="mini-note <?= $state === 'over' ? 'is-over' : ($state === 'ok' ? 'is-ok' : '') ?>">
        <?= $totalLimit === null ? 'No limit' : ($state === 'warn' ? warn_threshold() . '% or more used' : ($state === 'over' ? 'Past the limit' : 'Under ' . warn_threshold() . '% used')) ?>
      </div>
    </div>
    <div>
      <div class="mini-label"><?= $isThisMonth ? 'Days left' : 'Days' ?></div>
      <div class="mini-value"><?= (int) $daysRemaining ?></div>
      <div class="mini-note">of <?= (int) $daysInMonth ?> in the month</div>
    </div>
    <div>
      <div class="mini-label">Average / day</div>
      <div class="mini-value"><?= h(money($spent / max(1, $dayOfMonth))) ?></div>
      <div class="mini-note">so far</div>
    </div>
    <div>
      <div class="mini-label">Month-end pace</div>
      <div class="mini-value"><?= $showProjection ? h(money($projected)) : '—' ?></div>
      <?php if ($showProjection && $totalLimit !== null): ?>
        <div class="mini-note <?= $projected > $totalLimit ? 'is-over' : 'is-ok' ?>">
          <?= $projected > $totalLimit ? h(money($projected - $totalLimit)) . ' over limit' : 'within the limit' ?>
        </div>
      <?php else: ?>
        <div class="mini-note"><?= $isThisMonth ? 'needs a few days of data' : 'this month only' ?></div>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- ------------------------------------------------------- limits + progress -->
<form class="card card-tight" method="post" action="budget.php" id="budget-form"
      data-categories='<?= h(json_encode(array_map(static fn($c) => ['id' => (int) $c['category_id'], 'name' => $c['name']], $categories), JSON_UNESCAPED_UNICODE)) ?>'>
  <?= csrf_field() ?>
  <input type="hidden" name="month" value="<?= h($month) ?>">

  <div class="card-head">
    <div>
      <h2>Limits for <?= h(month_label($month)) ?></h2>
      <p>Set a whole-month limit, and optionally one per category. Leave a field blank for no limit.</p>
    </div>
    <div class="btn-row">
      <?php if ($prevHasLimits): ?>
        <button type="submit" form="copy-form" class="btn btn-ghost btn-sm"
                data-confirm="Replace this month's limits with <?= h(month_label(shift_month($month, -1))) ?>'s?">Copy from <?= h(month_label(shift_month($month, -1))) ?></button>
      <?php endif; ?>
      <button type="button" class="btn btn-ghost btn-sm" id="suggest-split">Suggest a split</button>
    </div>
  </div>

  <div class="limit-row limit-total">
    <div class="limit-name">
      <span class="exp-icon" aria-hidden="true">৳</span>
      <div>
        <label for="total_limit"><strong>Whole month</strong></label>
        <div class="exp-cat">Everything you spend in <?= h(month_label($month)) ?></div>
      </div>
    </div>
    <div class="limit-bar">
      <?php if ($totalLimit !== null): ?>
        <div class="progress"><div class="progress-bar is-<?= h($state) ?>" style="width: <?= round(progress_width($spent, $totalLimit), 2) ?>%"></div></div>
        <div class="limit-figures"><?= h(money($spent)) ?> spent · <?= round(progress_pct($spent, $totalLimit)) ?>%</div>
      <?php else: ?>
        <div class="limit-figures"><?= h(money($spent)) ?> spent · no limit</div>
      <?php endif; ?>
    </div>
    <div class="limit-input">
      <div class="money-input">
        <span><?= h(CURRENCY) ?></span>
        <input type="number" id="total_limit" name="total_limit" step="0.01" min="0" inputmode="decimal"
               placeholder="No limit"
               value="<?= $totalLimit !== null ? h(number_format($totalLimit, 2, '.', '')) : '' ?>">
      </div>
    </div>
  </div>

  <?php foreach ($categories as $category): ?>
    <?php
      $id       = (int) $category['category_id'];
      $rowSpent = $spendByCat[$id]['total'] ?? 0.0;
      $rowLimit = $limits[$id] ?? null;
      $barBase  = $rowLimit ?? ($spent > 0 ? $spent : null);
    ?>
    <div class="limit-row">
      <div class="limit-name">
        <span class="exp-icon" aria-hidden="true"><?= h($category['icon']) ?></span>
        <div>
          <label for="limit-<?= $id ?>"><?= h($category['name']) ?></label>
        </div>
      </div>
      <div class="limit-bar">
        <div class="progress">
          <div class="progress-bar <?= $rowLimit !== null ? 'is-' . h(progress_state($rowSpent, $rowLimit)) : 'is-muted' ?>"
               style="width: <?= round(progress_width($rowSpent, $barBase), 2) ?>%"></div>
        </div>
        <div class="limit-figures">
          <?php if ($rowLimit !== null): ?>
            <?= h(money($rowSpent)) ?> of <?= h(money($rowLimit)) ?> · <?= round(progress_pct($rowSpent, $rowLimit)) ?>%
          <?php else: ?>
            <?= h(money($rowSpent)) ?> spent · no limit
          <?php endif; ?>
        </div>
      </div>
      <div class="limit-input">
        <div class="money-input">
          <span><?= h(CURRENCY) ?></span>
          <input type="number" id="limit-<?= $id ?>" name="limits[<?= $id ?>]" data-cat="<?= $id ?>"
                 step="0.01" min="0" inputmode="decimal" placeholder="None"
                 value="<?= $rowLimit !== null ? h(number_format($rowLimit, 2, '.', '')) : '' ?>">
        </div>
      </div>
    </div>
  <?php endforeach; ?>

  <div class="limit-foot">
    <div class="alloc" id="alloc" data-currency="<?= h(CURRENCY) ?>">
      <div class="alloc-text" id="alloc-text"></div>
      <div class="progress"><div class="progress-bar" id="alloc-bar" style="width: 0"></div></div>
    </div>
    <button type="submit" name="action" value="save" class="btn btn-primary btn-lg">Save budget</button>
  </div>
</form>

<!-- Separate form so pressing Enter in a limit field can never trigger "copy". -->
<form id="copy-form" method="post" action="budget.php" hidden>
  <?= csrf_field() ?>
  <input type="hidden" name="month" value="<?= h($month) ?>">
  <input type="hidden" name="action" value="copy">
</form>

<!-- --------------------------------------------------------------- month nav -->
<div class="card month-card">
  <div class="month-nav">
    <a class="btn btn-ghost btn-sm" href="budget.php?month=<?= h(shift_month($month, -1)) ?>">← <?= h(month_label(shift_month($month, -1))) ?></a>
    <form method="get" action="budget.php">
      <input type="month" name="month" value="<?= h($month) ?>" data-autosubmit aria-label="Choose a month">
      <noscript><button type="submit" class="btn btn-ghost btn-sm">Go</button></noscript>
    </form>
    <a class="btn btn-ghost btn-sm" href="budget.php?month=<?= h(shift_month($month, 1)) ?>"><?= h(month_label(shift_month($month, 1))) ?> →</a>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
