<?php
/**
 * Shared data access for categories, spending totals, budgets and goals.
 *
 * Anything that more than one page needs to calculate lives here so the
 * dashboard, budget page and insights page can't drift apart.
 *
 * Every function takes the user id explicitly and every query filters on it,
 * so one user can never read or write another user's rows.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

/** The starter set of categories created for each new account. */
const DEFAULT_CATEGORIES = [
    ['Tuition & Fees',   '🎓'],
    ['Food & Cafeteria', '🍛'],
    ['Transport',        '🛺'],
    ['Mobile & Internet','📱'],
    ['Books & Printing', '📚'],
    ['Rent & Mess',      '🏠'],
    ['Hangout & Fun',    '🎬'],
    ['Other',            '📦'],
];

// -------------------------------------------------------------- categories

function seed_default_categories(int $userId): void
{
    $stmt = db()->prepare(
        'INSERT INTO categories (user_id, name, icon, is_default) VALUES (?, ?, ?, 1)'
    );
    foreach (DEFAULT_CATEGORIES as [$name, $icon]) {
        $stmt->execute([$userId, $name, $icon]);
    }
}

/** All of a user's categories, alphabetical. */
function categories_for(int $userId): array
{
    $stmt = db()->prepare(
        'SELECT category_id, name, icon, is_default
           FROM categories
          WHERE user_id = ?
          ORDER BY name'
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/** Guard used before any write that references a category id. */
function owns_category(int $userId, int $categoryId): bool
{
    $stmt = db()->prepare(
        'SELECT 1 FROM categories WHERE category_id = ? AND user_id = ?'
    );
    $stmt->execute([$categoryId, $userId]);
    return (bool) $stmt->fetchColumn();
}

// ---------------------------------------------------------------- spending

/** Total spent in a 'YYYY-MM' month. */
function month_spend(int $userId, string $month): float
{
    [$start, $end] = month_bounds($month);
    $stmt = db()->prepare(
        'SELECT COALESCE(SUM(amount), 0)
           FROM expenses
          WHERE user_id = ? AND spent_on BETWEEN ? AND ?'
    );
    $stmt->execute([$userId, $start, $end]);
    return (float) $stmt->fetchColumn();
}

/** Number of expense entries in a month. */
function month_expense_count(int $userId, string $month): int
{
    [$start, $end] = month_bounds($month);
    $stmt = db()->prepare(
        'SELECT COUNT(*)
           FROM expenses
          WHERE user_id = ? AND spent_on BETWEEN ? AND ?'
    );
    $stmt->execute([$userId, $start, $end]);
    return (int) $stmt->fetchColumn();
}

/**
 * Spend per category for a month, biggest first.
 * Expenses whose category was deleted are grouped under id 0.
 */
function spend_by_category(int $userId, string $month): array
{
    [$start, $end] = month_bounds($month);
    // SQL string literals are single-quoted so the query still works if the
    // server runs with ANSI_QUOTES in its sql_mode.
    $stmt = db()->prepare(
        "SELECT COALESCE(e.category_id, 0) AS category_id,
                COALESCE(c.name, 'Uncategorised') AS name,
                COALESCE(c.icon, '❓') AS icon,
                SUM(e.amount) AS total
           FROM expenses e
           LEFT JOIN categories c ON c.category_id = e.category_id
          WHERE e.user_id = ? AND e.spent_on BETWEEN ? AND ?
          GROUP BY COALESCE(e.category_id, 0), c.name, c.icon
          ORDER BY total DESC"
    );
    $stmt->execute([$userId, $start, $end]);

    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $row['total'] = (float) $row['total'];
        $out[(int) $row['category_id']] = $row;
    }
    return $out;
}

/** Spend per day of the month, keyed by day number (1..31). */
function daily_spend(int $userId, string $month): array
{
    [$start, $end] = month_bounds($month);
    $stmt = db()->prepare(
        'SELECT DAY(spent_on) AS day, SUM(amount) AS total
           FROM expenses
          WHERE user_id = ? AND spent_on BETWEEN ? AND ?
          GROUP BY DAY(spent_on)
          ORDER BY day'
    );
    $stmt->execute([$userId, $start, $end]);

    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $out[(int) $row['day']] = (float) $row['total'];
    }
    return $out;
}

/**
 * Totals for the $count months ending with $month, oldest first.
 * Months with no spending are included as 0 so charts don't have gaps.
 */
function monthly_totals(int $userId, string $month, int $count = 6): array
{
    $oldest = shift_month($month, -($count - 1));
    [$start] = month_bounds($oldest);
    [, $end]  = month_bounds($month);

    $stmt = db()->prepare(
        "SELECT DATE_FORMAT(spent_on, '%Y-%m') AS ym, SUM(amount) AS total
           FROM expenses
          WHERE user_id = ? AND spent_on BETWEEN ? AND ?
          GROUP BY ym"
    );
    $stmt->execute([$userId, $start, $end]);

    $found = [];
    foreach ($stmt->fetchAll() as $row) {
        $found[$row['ym']] = (float) $row['total'];
    }

    $out = [];
    for ($i = $count - 1; $i >= 0; $i--) {
        $ym = shift_month($month, -$i);
        $out[$ym] = $found[$ym] ?? 0.0;
    }
    return $out;
}

/** The most recent expenses, newest first, joined to their category. */
function recent_expenses(int $userId, int $limit = 5): array
{
    // $limit is cast to int and inlined: LIMIT can't take a bound parameter
    // when emulated prepares are off.
    $limit = max(1, min(50, $limit));
    $stmt = db()->prepare(
        "SELECT e.expense_id, e.amount, e.spent_on, e.note,
                COALESCE(c.name, 'Uncategorised') AS category_name,
                COALESCE(c.icon, '❓') AS category_icon
           FROM expenses e
           LEFT JOIN categories c ON c.category_id = e.category_id
          WHERE e.user_id = ?
          ORDER BY e.spent_on DESC, e.expense_id DESC
          LIMIT " . $limit
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

// ----------------------------------------------------------------- budgets

/** The whole-month spending cap, or null if none is set. */
function overall_limit(int $userId, string $month): ?float
{
    $stmt = db()->prepare(
        'SELECT total_limit
           FROM budgets
          WHERE user_id = ? AND month_year = ? AND category_id IS NULL
          LIMIT 1'
    );
    $stmt->execute([$userId, $month]);
    $value = $stmt->fetchColumn();
    return $value === false || $value === null ? null : (float) $value;
}

/** Per-category caps for a month, keyed by category_id. */
function category_limits(int $userId, string $month): array
{
    $stmt = db()->prepare(
        'SELECT category_id, category_limit
           FROM budgets
          WHERE user_id = ? AND month_year = ? AND category_id IS NOT NULL'
    );
    $stmt->execute([$userId, $month]);

    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $out[(int) $row['category_id']] = (float) $row['category_limit'];
    }
    return $out;
}

/** Set (or clear, with null) the whole-month cap. */
function save_overall_limit(int $userId, string $month, ?float $limit): void
{
    $find = db()->prepare(
        'SELECT budget_id
           FROM budgets
          WHERE user_id = ? AND month_year = ? AND category_id IS NULL
          LIMIT 1'
    );
    $find->execute([$userId, $month]);
    $budgetId = $find->fetchColumn();

    if ($limit === null) {
        if ($budgetId) {
            db()->prepare('DELETE FROM budgets WHERE budget_id = ? AND user_id = ?')
                ->execute([$budgetId, $userId]);
        }
        return;
    }

    if ($budgetId) {
        db()->prepare('UPDATE budgets SET total_limit = ? WHERE budget_id = ? AND user_id = ?')
            ->execute([$limit, $budgetId, $userId]);
    } else {
        db()->prepare(
            'INSERT INTO budgets (user_id, month_year, category_id, total_limit)
             VALUES (?, ?, NULL, ?)'
        )->execute([$userId, $month, $limit]);
    }
}

/** Set (or clear, with null) the cap for one category in a month. */
function save_category_limit(int $userId, string $month, int $categoryId, ?float $limit): void
{
    if (!owns_category($userId, $categoryId)) {
        return;
    }

    $find = db()->prepare(
        'SELECT budget_id
           FROM budgets
          WHERE user_id = ? AND month_year = ? AND category_id = ?
          LIMIT 1'
    );
    $find->execute([$userId, $month, $categoryId]);
    $budgetId = $find->fetchColumn();

    if ($limit === null) {
        if ($budgetId) {
            db()->prepare('DELETE FROM budgets WHERE budget_id = ? AND user_id = ?')
                ->execute([$budgetId, $userId]);
        }
        return;
    }

    if ($budgetId) {
        db()->prepare('UPDATE budgets SET category_limit = ? WHERE budget_id = ? AND user_id = ?')
            ->execute([$limit, $budgetId, $userId]);
    } else {
        db()->prepare(
            'INSERT INTO budgets (user_id, month_year, category_id, category_limit)
             VALUES (?, ?, ?, ?)'
        )->execute([$userId, $month, $categoryId, $limit]);
    }
}

// ------------------------------------------------------------------- goals

/** Savings goals, unfinished ones first, then by nearest deadline. */
function goals_for(int $userId): array
{
    $stmt = db()->prepare(
        'SELECT goal_id, title, target_amount, saved_amount, deadline
           FROM savings_goals
          WHERE user_id = ?
          ORDER BY (saved_amount >= target_amount) ASC,
                   deadline IS NULL ASC,
                   deadline ASC,
                   goal_id DESC'
    );
    $stmt->execute([$userId]);

    return array_map(static function (array $row): array {
        $row['target_amount'] = (float) $row['target_amount'];
        $row['saved_amount']  = (float) $row['saved_amount'];
        return $row;
    }, $stmt->fetchAll());
}
