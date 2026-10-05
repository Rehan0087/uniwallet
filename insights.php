<?php
/**
 * Spending insights — the charts and the CSV export entry point.
 *
 * All figures are computed in PHP and handed to Chart.js as JSON, so the
 * page has no API endpoints and works with a single database round trip
 * per chart.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/finance.php';
require_once __DIR__ . '/includes/expense_filters.php';

require_login();
$userId = current_user_id();

$month = month_param();
[$monthStart, $monthEnd] = month_bounds($month);

$spent      = month_spend($userId, $month);
$entries    = month_expense_count($userId, $month);
$byCategory = spend_by_category($userId, $month);
$daily      = daily_spend($userId, $month);
$trend      = monthly_totals($userId, $month, 6);
$totalLimit = overall_limit($userId, $month);

$prevMonth = shift_month($month, -1);
$prevSpend = month_spend($userId, $prevMonth);

// Month-over-month change, guarding against a divide by zero.
$changePct = $prevSpend > 0 ? (($spent - $prevSpend) / $prevSpend) * 100 : null;

$daysInMonth = (int) date('t', strtotime($monthStart));
$isThisMonth = $month === current_month();
// Averaging over the full month before it has finished understates the rate.
$daysElapsed = $isThisMonth ? (int) date('j') : $daysInMonth;
$avgPerDay   = $daysElapsed > 0 ? $spent / $daysElapsed : 0.0;

// Single largest expense of the month.
$biggestStmt = db()->prepare(
    "SELECT e.amount, e.spent_on, e.note,
            COALESCE(c.name, 'Uncategorised') AS category_name
       FROM expenses e
       LEFT JOIN categories c ON c.category_id = e.category_id
      WHERE e.user_id = ? AND e.spent_on BETWEEN ? AND ?
      ORDER BY e.amount DESC
      LIMIT 1"
);
$biggestStmt->execute([$userId, $monthStart, $monthEnd]);
$biggest = $biggestStmt->fetch() ?: null;

$topCategory = $byCategory ? reset($byCategory) : null;

// Top five single expenses of the month.
$topStmt = db()->prepare(
    "SELECT e.amount, e.spent_on, e.note,
            COALESCE(c.name, 'Uncategorised') AS category_name, COALESCE(c.icon, '❓') AS icon
       FROM expenses e
       LEFT JOIN categories c ON c.category_id = e.category_id
      WHERE e.user_id = ? AND e.spent_on BETWEEN ? AND ?
      ORDER BY e.amount DESC, e.expense_id DESC
      LIMIT 5"
);
$topStmt->execute([$userId, $monthStart, $monthEnd]);
$topExpenses = $topStmt->fetchAll();

// Spend by weekday (Mon..Sun). MySQL WEEKDAY(): 0 = Monday.
$wdStmt = db()->prepare(
    'SELECT WEEKDAY(spent_on) AS wd, SUM(amount) AS total
       FROM expenses WHERE user_id = ? AND spent_on BETWEEN ? AND ?
      GROUP BY WEEKDAY(spent_on)'
);
$wdStmt->execute([$userId, $monthStart, $monthEnd]);
$byWeekday = array_fill(0, 7, 0.0);
foreach ($wdStmt->fetchAll() as $r) {
    $byWeekday[(int) $r['wd']] = (float) $r['total'];
}
$wdMax    = max($byWeekday) ?: 1.0;
$wdNames  = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
$peakDay  = $spent > 0 ? array_search(max($byWeekday), $byWeekday, true) : null;
$wdFull   = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

// Plain-language takeaways, built only from numbers we have.
$takeaways = [];
if ($topCategory && $spent > 0) {
    $takeaways[] = '<b>' . h($topCategory['icon'] . ' ' . $topCategory['name']) . '</b> took '
        . round(progress_pct($topCategory['total'], $spent)) . '% of your spending ('
        . h(money($topCategory['total'])) . ').';
}
if ($changePct !== null) {
    $takeaways[] = 'You spent <b>' . h(money(abs($spent - $prevSpend))) . ($spent >= $prevSpend ? ' more' : ' less')
        . '</b> than in ' . h(month_label($prevMonth)) . '.';
}
if ($peakDay !== null && max($byWeekday) > 0) {
    $takeaways[] = '<b>' . $wdFull[$peakDay] . 's</b> are your most expensive day: '
        . h(money($byWeekday[$peakDay])) . ' this month.';
}
if ($totalLimit !== null) {
    $takeaways[] = $spent > $totalLimit
        ? 'You are <b>' . h(money($spent - $totalLimit)) . '</b> over your ' . h(money($totalLimit)) . ' budget.'
        : 'You have used <b>' . round(progress_pct($spent, $totalLimit)) . '%</b> of your ' . h(money($totalLimit)) . ' budget.';
}

// Shared palette so the legend swatches match the chart slices exactly.
$palette = [
    '#1f9a72', '#f2a93b', '#2f80c9', '#d9534f', '#7a5cc2',
    '#27a98b', '#e07a3b', '#8aa05a', '#b4478a', '#6b7d76',
];

// ------------------------------------------------------------- chart data

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

$trendLabels = [];
$trendValues = [];
foreach ($trend as $ym => $total) {
    $trendLabels[] = date('M y', strtotime($ym . '-01'));
    $trendValues[] = round($total, 2);
}

// Cumulative spend day by day. For the current month the line stops at today
// rather than flat-lining to the end of the month.
$lastDay      = $isThisMonth ? (int) date('j') : $daysInMonth;
$dayLabels    = [];
$cumulative   = [];
$runningTotal = 0.0;
for ($day = 1; $day <= $lastDay; $day++) {
    $runningTotal += $daily[$day] ?? 0.0;
    $dayLabels[]   = (string) $day;
    $cumulative[]  = round($runningTotal, 2);
}

/** Encode for embedding inside a <script> block. */
function js(array $value): string
{
    return json_encode(
        $value,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
    );
}

$exportQuery = http_build_query(['from' => $monthStart, 'to' => $monthEnd]);

$page_title  = 'Insights';
$page_lead   = month_label($month) . ' · ' . number_format($entries) . ' ' . ($entries === 1 ? 'entry' : 'entries') . ' logged';
$active_nav  = 'insights';
$page_action = '<div class="page-month">'
    . '<a class="btn btn-ghost btn-sm" href="insights.php?month=' . h(shift_month($month, -1)) . '" aria-label="Previous month">←</a>'
    . '<form method="get" action="insights.php"><input type="month" name="month" value="' . h($month) . '" data-autosubmit aria-label="Choose a month"></form>'
    . '<a class="btn btn-ghost btn-sm" href="insights.php?month=' . h(shift_month($month, 1)) . '" aria-label="Next month">→</a>'
    . '<a class="btn btn-primary" href="export.php?' . h($exportQuery) . '">Export CSV</a></div>';

require __DIR__ . '/includes/header.php';
?>

<!-- ---------------------------------------------------------------- stats -->
<div class="grid grid-4">
  <div class="stat">
    <div class="stat-label">Spent</div>
    <div class="stat-value"><?= h(money($spent)) ?></div>
    <?php if ($changePct === null): ?>
      <div class="stat-note">No spending last month to compare</div>
    <?php else: ?>
      <div class="stat-note <?= $changePct > 0 ? 'is-over' : 'is-ok' ?>">
        <?= $changePct > 0 ? '▲' : '▼' ?> <?= number_format(abs($changePct), 1) ?>% vs <?= h(month_label($prevMonth)) ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="stat">
    <div class="stat-label">Average per day</div>
    <div class="stat-value"><?= h(money($avgPerDay)) ?></div>
    <div class="stat-note">over <?= $daysElapsed ?> day<?= $daysElapsed === 1 ? '' : 's' ?></div>
  </div>

  <div class="stat">
    <div class="stat-label">Top category</div>
    <div class="stat-value" style="font-size: 1.125rem;">
      <?= $topCategory ? h($topCategory['icon'] . ' ' . $topCategory['name']) : '—' ?>
    </div>
    <div class="stat-note">
      <?= $topCategory ? h(money($topCategory['total'])) . ' · ' . round(progress_pct($topCategory['total'], $spent)) . '% of spend' : 'Nothing logged' ?>
    </div>
  </div>

  <div class="stat">
    <div class="stat-label">Biggest single expense</div>
    <div class="stat-value"><?= $biggest ? h(money($biggest['amount'])) : '—' ?></div>
    <div class="stat-note">
      <?= $biggest ? h($biggest['category_name']) . ' · ' . h(nice_date($biggest['spent_on'])) : 'Nothing logged' ?>
    </div>
  </div>
</div>

<?php if ($entries === 0): ?>
  <div class="card">
    <div class="empty">
      <div class="empty-mark" aria-hidden="true">◔</div>
      <p>No expenses in <?= h(month_label($month)) ?>, so there is nothing to chart yet.</p>
      <a class="btn btn-primary btn-sm" href="expenses.php">Add an expense</a>
    </div>
  </div>
<?php else: ?>

<!-- --------------------------------------------------------------- charts -->
<div class="grid grid-2">
  <div class="card">
    <div class="card-head">
      <div>
        <h2>Where it went</h2>
        <p>Share of <?= h(month_label($month)) ?> spending by category.</p>
      </div>
    </div>
    <div class="chart-box">
      <canvas id="categoryChart" aria-label="Spending by category" role="img"></canvas>
    </div>
    <div class="legend">
      <?php foreach ($byCategory as $index => $row): ?>
        <div class="legend-item">
          <span class="legend-dot" style="background: <?= h($palette[$index % count($palette)]) ?>"></span>
          <span><?= h($row['icon'] . ' ' . $row['name']) ?></span>
          <span class="legend-value">
            <?= h(money($row['total'])) ?> · <?= round(progress_pct($row['total'], $spent)) ?>%
          </span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-head">
      <div>
        <h2>Last 6 months</h2>
        <p>Total spent per month, ending with <?= h(month_label($month)) ?>.</p>
      </div>
    </div>
    <div class="chart-box tall">
      <canvas id="trendChart" aria-label="Monthly spending trend" role="img"></canvas>
    </div>
  </div>
</div>

<?php if ($takeaways): ?>
<div class="card">
  <div class="card-head"><div><h2>What stands out</h2><p>The short version of <?= h(month_label($month)) ?>.</p></div></div>
  <ul class="insight-list">
    <?php foreach ($takeaways as $line): ?><li><?= $line /* built above from escaped values */ ?></li><?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<div class="grid grid-2">
  <div class="card">
    <div class="card-head"><div><h2>Which days cost the most</h2><p>Total spent by day of the week.</p></div></div>
    <div class="weekdays">
      <?php foreach ($wdNames as $i => $name): ?>
        <div class="weekday <?= $peakDay === $i ? 'is-peak' : '' ?>" title="<?= h($wdFull[$i] . ': ' . money($byWeekday[$i])) ?>">
          <span style="height: <?= round($byWeekday[$i] / $wdMax * 82, 2) ?>%"></span>
          <em><?= $name ?></em>
          <small><?= $byWeekday[$i] > 0 ? h(money($byWeekday[$i])) : '–' ?></small>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><div><h2>Biggest expenses</h2><p>The five largest of the month.</p></div></div>
    <ol class="top-list">
      <?php foreach ($topExpenses as $n => $row): ?>
        <li>
          <span class="rank"><?= $n + 1 ?></span>
          <div class="what">
            <div><?= h($row['note'] !== null && $row['note'] !== '' ? $row['note'] : $row['category_name']) ?></div>
            <small><?= h($row['icon'] . ' ' . $row['category_name']) ?> · <?= h(nice_date($row['spent_on'])) ?></small>
          </div>
          <span class="amt"><?= h(money($row['amount'])) ?></span>
        </li>
      <?php endforeach; ?>
    </ol>
  </div>
</div>

<div class="card">
  <div class="card-head">
    <div>
      <h2>Spending pace</h2>
      <p>
        Running total through <?= h(month_label($month)) ?>.
        <?= $totalLimit !== null ? 'The dashed line is your ' . h(money($totalLimit)) . ' budget.' : 'Set a budget to see it plotted here.' ?>
      </p>
    </div>
  </div>
  <div class="chart-box tall">
    <canvas id="paceChart" aria-label="Cumulative spending through the month" role="img"></canvas>
  </div>
</div>

<?php endif; ?>

<?php
// The chart bundle is only loaded on pages that actually draw charts.
// Figures travel as a JSON <script> block rather than inline JS, so no user
// text is ever interpolated into executable code.
$page_scripts = '';

if ($entries > 0) {
    $chartConfig = [
        'currency'    => CURRENCY,
        'catLabels'   => $catLabels,
        'catValues'   => $catValues,
        'catColors'   => $catColors,
        'trendLabels' => $trendLabels,
        'trendValues' => $trendValues,
        'dayLabels'   => $dayLabels,
        'cumulative'  => $cumulative,
        'limit'       => $totalLimit,
        'accent'      => $palette[0],
    ];

    $page_scripts = '<script src="assets/vendor/chart.umd.min.js"></script>'
        . '<script id="chart-data" type="application/json">' . js($chartConfig) . '</script>'
        . '<script src="' . h(asset('assets/js/charts.js')) . '"></script>';
}

require __DIR__ . '/includes/footer.php';
