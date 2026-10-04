<?php
/**
 * Dashboard — one screen answering "how am I doing this month?".
 *
 * Read-only: every figure here links through to the page that owns it.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/finance.php';

require_login();
$userId = current_user_id();
$user   = current_user();

$month = current_month();

$spent      = month_spend($userId, $month);
$entries    = month_expense_count($userId, $month);
$totalLimit = overall_limit($userId, $month);
$byCategory = spend_by_category($userId, $month);
$limits     = category_limits($userId, $month);
$recent     = recent_expenses($userId, 6);
$goals      = goals_for($userId);
$byDay      = daily_spend($userId, $month);
$categories = categories_for($userId);

$prevMonth = shift_month($month, -1);
$prevSpend = month_spend($userId, $prevMonth);
$changePct = $prevSpend > 0 ? (($spent - $prevSpend) / $prevSpend) * 100 : null;

$remaining     = $totalLimit !== null ? $totalLimit - $spent : null;
$daysInMonth   = (int) date('t');
$daysRemaining = max(1, $daysInMonth - (int) date('j') + 1);
$perDay        = ($remaining !== null && $remaining > 0) ? $remaining / $daysRemaining : null;

$totalSaved  = array_sum(array_column($goals, 'saved_amount'));
$totalTarget = array_sum(array_column($goals, 'target_amount'));

// Top five categories, so the card stays readable with many categories.
$topCategories = array_slice($byCategory, 0, 5, true);

$palette = [
    '#1f9a72', '#f2a93b', '#2f80c9', '#d9534f', '#7a5cc2',
    '#27a98b', '#e07a3b', '#8aa05a', '#b4478a', '#6b7d76',
];

$catLabels = [];
$catValues = [];
$catColors = [];
$i = 0;
foreach ($byCategory as $row) {
    $catLabels[] = $row['icon'] . ' ' . $row['name'];
    $catValues[] = round($row['total'], 2);
    $catColors[] = $palette[$i % count($palette)];
    $i++;
}

$firstName = trim(explode(' ', trim($user['full_name']))[0]);

// Time-of-day greeting (APP_TIMEZONE is set in config).
$hour     = (int) date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');

// Heads-up: categories that have used the warning percentage of their limit.
$alerts = [];
foreach ($byCategory as $categoryId => $row) {
    $cap = $limits[$categoryId] ?? null;
    if ($cap !== null && $cap > 0 && $row['total'] >= $cap * warn_threshold() / 100) {
        $over = $row['total'] > $cap;
        $alerts[] = [
            'state' => $row['total'] >= $cap ? 'over' : 'warn',
            'text'  => $row['icon'] . ' ' . $row['name'] . ($over
                ? ' is ' . money($row['total'] - $cap) . ' over its ' . money($cap) . ' limit.'
                : ($row['total'] == $cap
                    ? ' has used all of its ' . money($cap) . '.'
                    : ' has used ' . round($row['total'] / $cap * 100) . '% of its ' . money($cap) . '.')),
        ];
    }
}

// Day-by-day bars: one per day of the month, today highlighted.
$today        = (int) date('j');
$dailyBudget  = $totalLimit !== null ? $totalLimit / $daysInMonth : null;
// Scale to the budget pace so one big day (rent, tuition) doesn't flatten the rest;
// anything taller than the chart is clipped and marked.
$barMax       = $dailyBudget !== null ? max(1.0, $dailyBudget * 2)
                                      : max(array_merge([1.0], array_values($byDay)));
$weekSpent    = 0.0;
for ($d = max(1, $today - 6); $d <= $today; $d++) {
    $weekSpent += $byDay[$d] ?? 0;
}

$page_title  = $greeting . ', ' . $firstName;
$tab_title   = 'Dashboard';
$active_nav  = 'dashboard';
$page_lead   = date('l, j F Y') . ' · ' . uiu_term()['name'] . ' trimester';
$page_action = '<a class="btn btn-ghost" href="export.php">Export CSV</a>';

require __DIR__ . '/includes/header.php';
?>

<!-- ------------------------------------------------------ safe to spend -->
<?php $barState = $totalLimit !== null ? progress_state($spent, $totalLimit) : ''; ?>
<section class="runway" aria-label="Safe to spend">
  <div class="runway-main">
    <div class="runway-kicker">Safe to spend today</div>
    <?php if ($totalLimit === null): ?>
      <p class="runway-sentence">Set a monthly budget and I'll work out your daily limit.</p>
      <p class="runway-sub"><a href="budget.php">Set this month's budget</a> to get started.</p>
    <?php elseif ($perDay === null): ?>
      <p class="runway-sentence">You have used up this month's budget.</p>
      <p class="runway-sub">You are <?= h(money(abs($remaining))) ?> over. <a href="expenses.php">Review your expenses</a> or <a href="budget.php">adjust the budget</a>.</p>
    <?php else: ?>
      <p class="runway-sentence"><b><?= h(money($perDay)) ?></b> a day for the next <?= (int) $daysRemaining ?> day<?= $daysRemaining === 1 ? '' : 's' ?>.</p>
      <p class="runway-sub"><?= h(money($remaining)) ?> left of your <?= h(money($totalLimit)) ?> budget.</p>
    <?php endif; ?>

    <?php if ($totalLimit !== null): ?>
      <div class="runway-meter">
        <div class="progress" role="progressbar" aria-valuemin="0" aria-valuemax="100"
             aria-valuenow="<?= (int) round(progress_pct($spent, $totalLimit)) ?>" aria-label="Budget used">
          <div class="progress-bar is-<?= h($barState) ?>"
               style="width: <?= round(progress_width($spent, $totalLimit), 2) ?>%"></div>
        </div>
        <div class="runway-meter-label">
          <span><?= h(money($spent)) ?> spent</span>
          <span><?= round(progress_pct($spent, $totalLimit)) ?>% used</span>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <div class="runway-side">
    <div>
      <div class="mini-label">Spent this month</div>
      <div class="mini-value"><?= h(money($spent)) ?></div>
      <?php if ($changePct === null): ?>
        <div class="mini-note"><?= number_format($entries) ?> entr<?= $entries === 1 ? 'y' : 'ies' ?></div>
      <?php else: ?>
        <div class="mini-note <?= $changePct > 0 ? 'is-over' : 'is-ok' ?>">
          <?= $changePct > 0 ? '▲' : '▼' ?> <?= number_format(abs($changePct), 1) ?>% vs last month
        </div>
      <?php endif; ?>
    </div>
    <div>
      <div class="mini-label"><?= ($remaining !== null && $remaining < 0) ? 'Over budget by' : 'Budget left' ?></div>
      <div class="mini-value"><?= $remaining !== null ? h(money(abs($remaining))) : '—' ?></div>
      <div class="mini-note <?= ($remaining !== null && $remaining < 0) ? 'is-over' : '' ?>">
        <?= $totalLimit !== null ? 'of ' . h(money($totalLimit)) : 'No budget set' ?>
      </div>
    </div>
    <div>
      <div class="mini-label">Saved for goals</div>
      <div class="mini-value"><?= h(money($totalSaved)) ?></div>
      <div class="mini-note">
        <?= $goals ? 'of ' . h(money($totalTarget)) : 'No goals yet' ?>
      </div>
    </div>
    <div>
      <div class="mini-label">Trimester</div>
      <div class="mini-value"><?= h(uiu_term()['name']) ?></div>
      <div class="mini-note"><?= (int) uiu_term()['days_left'] ?> days left</div>
    </div>
  </div>
</section>

<!-- ----------------------------------------------------------- quick add -->
<form class="card quick-add" method="post" action="expenses.php" data-validated novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="save">
  <input type="hidden" name="expense_id" value="0">
  <input type="hidden" name="return" value="dashboard">
  <input type="hidden" name="spent_on" value="<?= h(date('Y-m-d')) ?>">

  <div class="quick-title">
    <h2>Just spent something?</h2>
    <p>Log it now, while you remember the amount.</p>
  </div>
  <div class="quick-fields">
    <div class="field">
      <label for="q_amount">Amount (<?= h(CURRENCY) ?>)</label>
      <input type="number" id="q_amount" name="amount" step="0.01" min="0.01" inputmode="decimal"
             placeholder="e.g. 60" data-validate="required amount">
    </div>
    <div class="field">
      <label for="q_category">Category</label>
      <select id="q_category" name="category_id">
        <option value="">Uncategorised</option>
        <?php foreach ($categories as $category): ?>
          <option value="<?= (int) $category['category_id'] ?>"><?= h($category['icon'] . ' ' . $category['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field quick-note">
      <label for="q_note">Note</label>
      <input type="text" id="q_note" name="note" maxlength="255" placeholder="e.g. rickshaw to campus">
    </div>
    <button type="submit" class="btn btn-primary">Add expense</button>
  </div>
</form>

<?php if ($alerts): ?>
  <div class="alerts" role="status">
    <?php foreach ($alerts as $alert): ?>
      <div class="alert alert-<?= h($alert['state']) ?>"><?= h($alert['text']) ?></div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- ------------------------------------------------- budget + category mix -->
<div class="grid grid-2">

  <div class="card">
    <div class="card-head">
      <div>
        <h2>Budget progress</h2>
        <p><?= h(month_label($month)) ?></p>
      </div>
      <a class="btn btn-ghost btn-sm" href="budget.php">Manage</a>
    </div>

    <?php if ($totalLimit === null && !$topCategories): ?>
      <div class="empty">
        <div class="empty-mark" aria-hidden="true">◑</div>
        <p>Nothing to show yet. Log an expense or set a budget.</p>
        <a class="btn btn-primary btn-sm" href="budget.php">Set a budget</a>
      </div>
    <?php else: ?>

      <?php if ($totalLimit !== null): ?>
        <div class="progress-row">
          <div class="progress-meta">
            <span class="name">Whole month</span>
            <span class="figures"><?= h(money($spent)) ?> of <?= h(money($totalLimit)) ?>
              (<?= round(progress_pct($spent, $totalLimit)) ?>%)</span>
          </div>
          <div class="progress">
            <div class="progress-bar is-<?= h(progress_state($spent, $totalLimit)) ?>"
                 style="width: <?= round(progress_width($spent, $totalLimit), 2) ?>%"></div>
          </div>
        </div>
      <?php endif; ?>

      <?php foreach ($topCategories as $categoryId => $row): ?>
        <?php
          $rowLimit = $limits[$categoryId] ?? null;
          $barBase  = $rowLimit ?? ($spent > 0 ? $spent : null);
        ?>
        <div class="progress-row">
          <div class="progress-meta">
            <span class="name"><?= h($row['icon']) ?> <?= h($row['name']) ?></span>
            <span class="figures">
              <?php if ($rowLimit !== null): ?>
                <?= h(money($row['total'])) ?> of <?= h(money($rowLimit)) ?>
                (<?= round(progress_pct($row['total'], $rowLimit)) ?>%)
              <?php else: ?>
                <?= h(money($row['total'])) ?>
              <?php endif; ?>
            </span>
          </div>
          <div class="progress">
            <div class="progress-bar <?= $rowLimit !== null ? 'is-' . h(progress_state($row['total'], $rowLimit)) : '' ?>"
                 style="width: <?= round(progress_width($row['total'], $barBase), 2) ?>%"></div>
          </div>
        </div>
      <?php endforeach; ?>

    <?php endif; ?>
  </div>

  <div class="card">
    <div class="card-head">
      <div>
        <h2>Category mix</h2>
        <p>Share of this month's spending.</p>
      </div>
      <a class="btn btn-ghost btn-sm" href="insights.php">Insights</a>
    </div>

    <?php if (!$byCategory): ?>
      <div class="empty">
        <div class="empty-mark" aria-hidden="true">◔</div>
        <p>No spending logged this month yet.</p>
        <a class="btn btn-primary btn-sm" href="expenses.php">Add your first expense</a>
      </div>
    <?php else: ?>
      <div class="mix">
      <div class="chart-box compact">
        <canvas id="categoryChart" aria-label="Spending by category" role="img"></canvas>
      </div>
      <div class="legend">
        <?php $index = 0; foreach ($topCategories as $row): ?>
          <div class="legend-item">
            <span class="legend-dot" style="background: <?= h($palette[$index % count($palette)]) ?>"></span>
            <span><?= h($row['icon'] . ' ' . $row['name']) ?></span>
            <span class="legend-value"><?= h(money($row['total'])) ?></span>
          </div>
        <?php $index++; endforeach; ?>
        <?php if (count($byCategory) > count($topCategories)): ?>
          <div class="legend-item muted">
            <span class="legend-dot" style="background: #cbd5c8"></span>
            <span>+<?= count($byCategory) - count($topCategories) ?> more</span>
            <span class="legend-value"><a href="insights.php">See all</a></span>
          </div>
        <?php endif; ?>
      </div>
      </div>
    <?php endif; ?>
  </div>

</div>

<!-- ----------------------------------------------------------- day by day -->
<div class="card">
  <div class="card-head">
    <div>
      <h2>Day by day</h2>
      <p>
        <?= h(money($weekSpent)) ?> in the last 7 days
        <?php if ($dailyBudget !== null): ?>
          · budget pace <?= h(money($dailyBudget)) ?> a day
        <?php endif; ?>
      </p>
    </div>
    <a class="btn btn-ghost btn-sm" href="insights.php">More charts</a>
  </div>

  <?php if (!$byDay): ?>
    <div class="empty">
      <div class="empty-mark" aria-hidden="true">▤</div>
      <p>Your daily spending will show up here.</p>
    </div>
  <?php else: ?>
    <div class="daybars" style="--days: <?= (int) $daysInMonth ?>">
      <?php if ($dailyBudget !== null): ?>
        <div class="daybars-line" style="bottom: <?= round($dailyBudget / $barMax * 100, 2) ?>%"
             title="Budget pace: <?= h(money($dailyBudget)) ?> a day"></div>
      <?php endif; ?>
      <?php for ($d = 1; $d <= $daysInMonth; $d++): ?>
        <?php
          $val   = $byDay[$d] ?? 0;
          $state = $d > $today ? 'is-future'
                 : ($dailyBudget !== null && $val > $dailyBudget ? 'is-over' : '');
          $clipped = $val > $barMax;
          $label = date('j M', strtotime($month . '-' . sprintf('%02d', $d)));
        ?>
        <div class="daybar <?= $state ?> <?= $d === $today ? 'is-today' : '' ?> <?= $clipped ? 'is-clipped' : '' ?>"
             title="<?= h($label . ': ' . money($val)) ?>">
          <span style="height: <?= round(min($val, $barMax) / $barMax * 100, 2) ?>%"></span>
        </div>
      <?php endfor; ?>
    </div>
    <div class="daybars-axis"><span>1</span><span><?= (int) $daysInMonth ?></span></div>
  <?php endif; ?>
</div>

<!-- ------------------------------------------------- recent + goals -->
<div class="grid grid-2">

  <div class="card card-tight">
    <div class="card-head">
      <div>
        <h2>Recent expenses</h2>
        <p>Your latest entries.</p>
      </div>
      <a class="btn btn-ghost btn-sm" href="expenses.php">See all</a>
    </div>

    <?php if (!$recent): ?>
      <div class="empty">
        <div class="empty-mark" aria-hidden="true">🧾</div>
        <p>Nothing logged yet.</p>
        <a class="btn btn-primary btn-sm" href="expenses.php">Add an expense</a>
      </div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="data">
          <thead>
            <tr>
              <th>Date</th>
              <th>Category</th>
              <th class="num">Amount</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recent as $row): ?>
              <tr>
                <td class="num"><?= h(nice_date($row['spent_on'])) ?></td>
                <td>
                  <span class="chip"><?= h($row['category_icon']) ?> <?= h($row['category_name']) ?></span>
                  <?php if ($row['note']): ?>
                    <div class="muted" style="font-size: .8125rem; margin-top: 3px;"><?= h($row['note']) ?></div>
                  <?php endif; ?>
                </td>
                <td class="num"><?= h(money($row['amount'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="card-head">
      <div>
        <h2>Savings goals</h2>
        <p><?= $goals ? count($goals) . ' active' : 'None yet' ?></p>
      </div>
      <a class="btn btn-ghost btn-sm" href="goals.php">Manage</a>
    </div>

    <?php if (!$goals): ?>
      <div class="empty">
        <div class="empty-mark" aria-hidden="true">◆</div>
        <p>Saving for something? Track it here.</p>
        <a class="btn btn-primary btn-sm" href="goals.php">Create a goal</a>
      </div>
    <?php else: ?>
      <?php foreach (array_slice($goals, 0, 4) as $goal): ?>
        <?php $isDone = $goal['saved_amount'] >= $goal['target_amount']; ?>
        <div class="progress-row">
          <div class="progress-meta">
            <span class="name"><?= h($goal['title']) ?></span>
            <span class="figures">
              <?= h(money($goal['saved_amount'])) ?> / <?= h(money($goal['target_amount'])) ?>
              (<?= round(progress_pct($goal['saved_amount'], $goal['target_amount'])) ?>%)
            </span>
          </div>
          <div class="progress">
            <div class="progress-bar <?= $isDone ? 'is-ok' : '' ?>"
                 style="width: <?= round(progress_width($goal['saved_amount'], $goal['target_amount']), 2) ?>%"></div>
          </div>
        </div>
      <?php endforeach; ?>
      <?php if (count($goals) > 4): ?>
        <p class="muted mb-0" style="font-size: .875rem;">
          +<?= count($goals) - 4 ?> more on the <a href="goals.php">goals page</a>.
        </p>
      <?php endif; ?>
    <?php endif; ?>
  </div>

</div>

<?php
// Only the doughnut is drawn here; charts.js skips canvases it can't find.
$page_scripts = '';

if ($byCategory) {
    $chartConfig = [
        'currency'    => CURRENCY,
        'catLabels'   => $catLabels,
        'catValues'   => $catValues,
        'catColors'   => $catColors,
        'trendLabels' => [],
        'trendValues' => [],
        'dayLabels'   => [],
        'cumulative'  => [],
        'limit'       => null,
        'accent'      => $palette[0],
    ];

    $json = json_encode(
        $chartConfig,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
    );

    $page_scripts = '<script src="assets/vendor/chart.umd.min.js"></script>'
        . '<script id="chart-data" type="application/json">' . $json . '</script>'
        . '<script src="assets/js/charts.js"></script>';
}

require __DIR__ . '/includes/footer.php';
