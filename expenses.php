<?php
/**
 * Expense tracking — add, edit, delete and filter.
 *
 * Filters live in the query string so a filtered view can be bookmarked and
 * so the CSV export can reuse exactly the same parameters.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/finance.php';
require_once __DIR__ . '/includes/expense_filters.php';

require_login();
$userId = current_user_id();

const PER_PAGE = 20;

// ------------------------------------------------------------ POST actions

if (is_post()) {
    require_csrf();

    $action      = post('action');
    $postFilters = read_filters($_POST);
    $back        = 'expenses.php?' . filters_query($postFilters);

    if ($action === 'delete') {
        $expenseId = (int) post('expense_id');
        // The user_id in the WHERE is what stops one user deleting another's row.
        $stmt = db()->prepare('DELETE FROM expenses WHERE expense_id = ? AND user_id = ?');
        $stmt->execute([$expenseId, $userId]);

        flash($stmt->rowCount() ? 'ok' : 'error',
              $stmt->rowCount() ? 'Expense deleted.' : 'That expense no longer exists.');
        redirect($back);
    }

    if ($action === 'save') {
        $expenseId = (int) post('expense_id');
        $amount    = parse_amount(post('amount'));
        $spentOn   = post('spent_on');
        $note      = post('note');
        $catRaw    = post('category_id');

        $errors = [];
        if ($amount === null) {
            $errors[] = 'Enter an amount greater than zero.';
        }
        if (!valid_date($spentOn)) {
            $errors[] = 'Pick a valid date.';
        }
        if (mb_strlen($note) > 255) {
            $errors[] = 'Note must be 255 characters or fewer.';
        }

        $categoryId = null;
        if ($catRaw !== '' && ctype_digit($catRaw)) {
            if (!owns_category($userId, (int) $catRaw)) {
                $errors[] = 'Pick a category from the list.';
            } else {
                $categoryId = (int) $catRaw;
            }
        }

        if ($errors) {
            flash('error', implode(' ', $errors));
            redirect($back . ($expenseId ? '&edit=' . $expenseId : ''));
        }

        if ($expenseId > 0) {
            $stmt = db()->prepare(
                'UPDATE expenses
                    SET category_id = ?, amount = ?, spent_on = ?, note = ?
                  WHERE expense_id = ? AND user_id = ?'
            );
            $stmt->execute([$categoryId, $amount, $spentOn, $note !== '' ? $note : null, $expenseId, $userId]);
            flash('ok', 'Expense updated.');
        } else {
            $stmt = db()->prepare(
                'INSERT INTO expenses (user_id, category_id, amount, spent_on, note)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$userId, $categoryId, $amount, $spentOn, $note !== '' ? $note : null]);
            flash('ok', 'Expense added.');
        }

        // The dashboard's quick-add form asks to come back to the dashboard.
        redirect(post('return') === 'dashboard' ? 'dashboard.php' : $back);
    }

    redirect('expenses.php');
}

// --------------------------------------------------------------- load data

$filters   = read_filters($_GET);
$categories = categories_for($userId);

// The row being edited, if any.
$editing = null;
$editId  = (int) query('edit');
if ($editId > 0) {
    $stmt = db()->prepare(
        'SELECT expense_id, category_id, amount, spent_on, note
           FROM expenses WHERE expense_id = ? AND user_id = ?'
    );
    $stmt->execute([$editId, $userId]);
    $editing = $stmt->fetch() ?: null;
    if (!$editing) {
        flash('error', 'That expense could not be found.');
    }
}

[$whereSql, $whereParams] = filters_to_sql($filters, $userId);

// Totals for the current filter, used for the summary line and paging.
$summaryStmt = db()->prepare(
    "SELECT COUNT(*) AS n, COALESCE(SUM(e.amount), 0) AS total, COALESCE(MAX(e.amount), 0) AS biggest
       FROM expenses e $whereSql"
);
$summaryStmt->execute($whereParams);
$summary = $summaryStmt->fetch();

$totalRows  = (int) $summary['n'];
$totalSpend = (float) $summary['total'];
$biggest    = (float) $summary['biggest'];
$average    = $totalRows > 0 ? $totalSpend / $totalRows : 0.0;
$totalPages = max(1, (int) ceil($totalRows / PER_PAGE));
$page       = min($filters['page'], $totalPages);
$offset     = ($page - 1) * PER_PAGE;

// LIMIT/OFFSET are cast to int and inlined — they cannot be bound parameters
// while PDO::ATTR_EMULATE_PREPARES is off.
$listStmt = db()->prepare(
    "SELECT e.expense_id, e.amount, e.spent_on, e.note, e.category_id,
            COALESCE(c.name, 'Uncategorised') AS category_name,
            COALESCE(c.icon, '❓') AS category_icon
       FROM expenses e
       LEFT JOIN categories c ON c.category_id = e.category_id
       $whereSql
      ORDER BY e.spent_on DESC, e.expense_id DESC
      LIMIT " . PER_PAGE . " OFFSET " . (int) $offset
);
$listStmt->execute($whereParams);
$rows = $listStmt->fetchAll();

// Whole-day totals for the days shown (a day can span two pages, so sum in SQL).
$dayTotals = [];
if ($rows) {
    $dates = array_values(array_unique(array_column($rows, 'spent_on')));
    $marks = implode(',', array_fill(0, count($dates), '?'));
    $dayStmt = db()->prepare(
        "SELECT e.spent_on, SUM(e.amount) AS total FROM expenses e
          $whereSql AND e.spent_on IN ($marks) GROUP BY e.spent_on"
    );
    $dayStmt->execute(array_merge($whereParams, $dates));
    foreach ($dayStmt->fetchAll() as $d) {
        $dayTotals[$d['spent_on']] = (float) $d['total'];
    }
}

/** "Today", "Yesterday", or "Fri, 2 Oct" for the day headings. */
function day_heading(string $date): string
{
    if ($date === date('Y-m-d')) {
        return 'Today';
    }
    if ($date === date('Y-m-d', strtotime('-1 day'))) {
        return 'Yesterday';
    }
    return date('D, j M', strtotime($date));
}

// Quick-range shortcut links.
[$thisFrom, $thisTo] = month_bounds(current_month());
[$lastFrom, $lastTo] = month_bounds(shift_month(current_month(), -1));
$today = date('Y-m-d');

$ranges = [
    'Today'      => ['from' => $today,    'to' => $today],
    'Last 7 days'=> ['from' => date('Y-m-d', strtotime('-6 days')), 'to' => $today],
    'This month' => ['from' => $thisFrom, 'to' => $thisTo],
    'Last month' => ['from' => $lastFrom, 'to' => $lastTo],
    'All time'   => ['from' => '',        'to' => ''],
];

$page_title  = 'Expenses';
$active_nav  = 'expenses';
$page_lead   = 'Every entry you have logged. Search by note, or filter by date and category.';
$page_action = '<a class="btn btn-ghost" href="export.php?' . h(filters_query($filters, ['page' => 1])) . '">Export CSV</a>';

require __DIR__ . '/includes/header.php';
?>

<!-- --------------------------------------------------------------- summary -->
<div class="grid grid-4">
  <div class="stat">
    <div class="stat-label">Total</div>
    <div class="stat-value"><?= h(money($totalSpend)) ?></div>
    <div class="stat-note"><?= h(filters_range_label(array_merge($filters, ['q' => '']))) ?><?= $filters['q'] !== '' ? ' · filtered' : '' ?></div>
  </div>
  <div class="stat">
    <div class="stat-label">Entries</div>
    <div class="stat-value"><?= number_format($totalRows) ?></div>
    <div class="stat-note"><?= $totalRows === 1 ? 'expense' : 'expenses' ?> match</div>
  </div>
  <div class="stat">
    <div class="stat-label">Average</div>
    <div class="stat-value"><?= h(money($average)) ?></div>
    <div class="stat-note">per expense</div>
  </div>
  <div class="stat">
    <div class="stat-label">Biggest</div>
    <div class="stat-value"><?= h(money($biggest)) ?></div>
    <div class="stat-note">single expense</div>
  </div>
</div>

<!-- ------------------------------------------------------------ add / edit -->
<div class="card">
  <div class="card-head">
    <div>
      <h2><?= $editing ? 'Edit expense' : 'Add an expense' ?></h2>
      <p><?= $editing ? 'Change any field and save.' : 'Amount and date are required; the note is optional.' ?></p>
    </div>
    <?php if ($editing): ?>
      <a class="btn btn-ghost btn-sm" href="expenses.php?<?= h(filters_query($filters)) ?>">Cancel edit</a>
    <?php endif; ?>
  </div>

  <form method="post" action="expenses.php" data-validated novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="expense_id" value="<?= (int) ($editing['expense_id'] ?? 0) ?>">
    <input type="hidden" name="from" value="<?= h($filters['from']) ?>">
    <input type="hidden" name="to"   value="<?= h($filters['to']) ?>">
    <input type="hidden" name="cat"  value="<?= h($filters['cat']) ?>">
    <input type="hidden" name="q"    value="<?= h($filters['q']) ?>">
    <input type="hidden" name="page" value="<?= (int) $page ?>">

    <div class="form-grid">
      <div class="field">
        <label for="amount">Amount (<?= h(CURRENCY) ?>)</label>
        <input type="number" id="amount" name="amount" step="0.01" min="0.01"
               value="<?= $editing ? h($editing['amount']) : '' ?>"
               data-validate="required amount" <?= $editing ? 'data-autofocus' : '' ?>>
      </div>

      <div class="field">
        <label for="category_id">Category</label>
        <select id="category_id" name="category_id">
          <option value="">Uncategorised</option>
          <?php foreach ($categories as $category): ?>
            <option value="<?= (int) $category['category_id'] ?>"
              <?= (int) ($editing['category_id'] ?? 0) === (int) $category['category_id'] ? 'selected' : '' ?>>
              <?= h($category['icon'] . ' ' . $category['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field">
        <label for="spent_on">Date</label>
        <input type="date" id="spent_on" name="spent_on"
               value="<?= h($editing['spent_on'] ?? date('Y-m-d')) ?>"
               data-validate="required">
      </div>

      <div class="field">
        <label for="note">Note</label>
        <input type="text" id="note" name="note" maxlength="255"
               placeholder="e.g. canteen lunch"
               value="<?= h($editing['note'] ?? '') ?>">
      </div>

      <div class="field">
        <button type="submit" class="btn btn-primary">
          <?= $editing ? 'Save changes' : 'Add expense' ?>
        </button>
      </div>
    </div>
  </form>
</div>

<!-- --------------------------------------------------------------- filters -->
<div class="card">
  <div class="card-head">
    <h2>Filter</h2>
    <div class="btn-row">
      <?php foreach ($ranges as $label => $range): ?>
        <?php $isOn = $filters['from'] === $range['from'] && $filters['to'] === $range['to']; ?>
        <a class="btn btn-sm <?= $isOn ? 'btn-primary' : 'btn-ghost' ?>"
           href="expenses.php?<?= h(filters_query($filters, $range + ['page' => 1])) ?>">
          <?= h($label) ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <form method="get" action="expenses.php">
    <div class="form-grid">
      <div class="field filter-search">
        <label for="q">Search notes</label>
        <input type="search" id="q" name="q" value="<?= h($filters['q']) ?>" maxlength="100"
               placeholder="e.g. rickshaw, biryani">
      </div>
      <div class="field">
        <label for="from">From</label>
        <input type="date" id="from" name="from" value="<?= h($filters['from']) ?>">
      </div>
      <div class="field">
        <label for="to">To</label>
        <input type="date" id="to" name="to" value="<?= h($filters['to']) ?>">
      </div>
      <div class="field">
        <label for="cat">Category</label>
        <select id="cat" name="cat">
          <option value="">All categories</option>
          <option value="none" <?= $filters['cat'] === 'none' ? 'selected' : '' ?>>Uncategorised</option>
          <?php foreach ($categories as $category): ?>
            <option value="<?= (int) $category['category_id'] ?>"
              <?= $filters['cat'] === (string) $category['category_id'] ? 'selected' : '' ?>>
              <?= h($category['icon'] . ' ' . $category['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <div class="btn-row">
          <button type="submit" class="btn btn-primary">Apply</button>
          <a class="btn btn-ghost" href="expenses.php?from=&to=">Clear</a>
        </div>
      </div>
    </div>
  </form>
</div>

<!-- ------------------------------------------------------------------ list -->
<div class="card card-tight">
  <div class="card-head">
    <div>
      <h2>Your expenses</h2>
      <p>Newest first, grouped by day with each day's total.</p>
    </div>
  </div>

  <?php if (!$rows): ?>
    <div class="empty">
      <div class="empty-mark" aria-hidden="true">🧾</div>
      <p><?= $filters['q'] !== '' ? 'Nothing matches "' . h($filters['q']) . '" in this range.' : 'No expenses in this range yet.' ?></p>
      <a class="btn btn-ghost btn-sm" href="expenses.php?from=&to=">Show all time</a>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="data expense-table">
        <thead>
          <tr>
            <th>Expense</th>
            <th class="num">Amount</th>
            <th class="actions"><span class="sr-only">Actions</span></th>
          </tr>
        </thead>
        <tbody>
          <?php $lastDate = null; ?>
          <?php foreach ($rows as $row): ?>
            <?php if ($row['spent_on'] !== $lastDate): $lastDate = $row['spent_on']; ?>
              <tr class="day-row">
                <td><?= h(day_heading($row['spent_on'])) ?> <span class="day-date"><?= h(nice_date($row['spent_on'])) ?></span></td>
                <td class="num" colspan="2"><?= h(money($dayTotals[$row['spent_on']] ?? 0)) ?></td>
              </tr>
            <?php endif; ?>
            <tr<?= $editing && (int) $editing['expense_id'] === (int) $row['expense_id'] ? ' class="is-editing"' : '' ?>>
              <td>
                <div class="exp-main">
                  <span class="exp-icon" aria-hidden="true"><?= h($row['category_icon']) ?></span>
                  <div>
                    <div class="exp-note"><?= $row['note'] !== null && $row['note'] !== '' ? h($row['note']) : h($row['category_name']) ?></div>
                    <?php if ($row['note'] !== null && $row['note'] !== ''): ?>
                      <div class="exp-cat"><?= h($row['category_name']) ?></div>
                    <?php endif; ?>
                  </div>
                </div>
              </td>
              <td class="num"><?= h(money($row['amount'])) ?></td>
              <td class="actions">
                <a class="btn btn-ghost btn-sm"
                   href="expenses.php?<?= h(filters_query($filters, ['page' => $page, 'edit' => $row['expense_id']])) ?>#amount">Edit</a>
                <form method="post" action="expenses.php" class="inline-form"
                      data-confirm="Delete this expense? This cannot be undone.">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="expense_id" value="<?= (int) $row['expense_id'] ?>">
                  <input type="hidden" name="from" value="<?= h($filters['from']) ?>">
                  <input type="hidden" name="to"   value="<?= h($filters['to']) ?>">
                  <input type="hidden" name="cat"  value="<?= h($filters['cat']) ?>">
                  <input type="hidden" name="q"    value="<?= h($filters['q']) ?>">
                  <input type="hidden" name="page" value="<?= (int) $page ?>">
                  <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($totalPages > 1): ?>
      <div class="btn-row pager">
        <span class="muted">Page <?= $page ?> of <?= $totalPages ?></span>
        <span class="btn-row">
          <?php if ($page > 1): ?>
            <a class="btn btn-ghost btn-sm"
               href="expenses.php?<?= h(filters_query($filters, ['page' => $page - 1])) ?>">← Previous</a>
          <?php endif; ?>
          <?php if ($page < $totalPages): ?>
            <a class="btn btn-ghost btn-sm"
               href="expenses.php?<?= h(filters_query($filters, ['page' => $page + 1])) ?>">Next →</a>
          <?php endif; ?>
        </span>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
