<?php
/**
 * CSV export of the expense list.
 *
 * Takes the same query-string filters as expenses.php, so "Export CSV"
 * downloads exactly the rows currently on screen.
 *
 * Uses PHP's built-in fputcsv() — quoting and escaping come free, so there
 * is no library to add here.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/finance.php';
require_once __DIR__ . '/includes/expense_filters.php';

require_login();
$userId = current_user_id();
$user   = current_user();

// This response is a file, not a web page: a PHP notice printed mid-stream
// would land inside the CSV and corrupt the download. Force error display
// off here whatever APP_DEBUG says. Errors still reach the PHP error log.
ini_set('display_errors', '0');

/**
 * Write one row of the CSV.
 *
 * $escape is passed explicitly because leaving it implicit is deprecated as
 * of PHP 8.4, and an empty string selects standard RFC 4180 quoting instead
 * of PHP's legacy backslash escaping.
 */
function csv_row($handle, array $fields): void
{
    fputcsv($handle, $fields, ',', '"', '');
}

$filters = read_filters($_GET);
[$whereSql, $whereParams] = filters_to_sql($filters, $userId);

$stmt = db()->prepare(
    "SELECT e.spent_on, e.amount, e.note,
            COALESCE(c.name, 'Uncategorised') AS category_name
       FROM expenses e
       LEFT JOIN categories c ON c.category_id = e.category_id
       $whereSql
      ORDER BY e.spent_on ASC, e.expense_id ASC"
);
$stmt->execute($whereParams);

$filename = 'uniwallet-expenses-' . date('Y-m-d') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store, no-cache');
header('Pragma: no-cache');

$out = fopen('php://output', 'w');

// UTF-8 BOM so Excel opens the ₹/€/£ symbols and any non-ASCII notes correctly.
fwrite($out, "\xEF\xBB\xBF");

// A short context block above the table — handy when the file is attached
// to a report or handed to someone else.
csv_row($out, [APP_NAME . ' — expense export']);
csv_row($out, ['Account', $user['full_name'] . ' <' . $user['email'] . '>']);
csv_row($out, ['Range', filters_range_label($filters)]);
csv_row($out, ['Generated', date('Y-m-d H:i')]);
csv_row($out, []);

csv_row($out, ['Date', 'Category', 'Note', 'Amount (' . CURRENCY . ')']);

$total = 0.0;
$count = 0;

while ($row = $stmt->fetch()) {
    $total += (float) $row['amount'];
    $count++;
    csv_row($out, [
        $row['spent_on'],
        $row['category_name'],
        $row['note'] ?? '',
        // Raw number, not money(): spreadsheets should see a value they can sum.
        number_format((float) $row['amount'], 2, '.', ''),
    ]);
}

csv_row($out, []);
csv_row($out, ['', '', $count . ' expense(s)', number_format($total, 2, '.', '')]);

fclose($out);
exit;
