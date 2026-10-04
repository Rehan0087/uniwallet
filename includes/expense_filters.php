<?php
/**
 * Expense filtering, shared by the expenses list and the CSV export so the
 * file you download always matches the rows you were looking at.
 */

require_once __DIR__ . '/helpers.php';

/**
 * Read filters from an array (GET when browsing, POST when saving a row).
 *
 * Keys: from (date), to (date), cat ('' = all, 'none' = uncategorised,
 * or a category id), q (search text for the note), page (int).
 */
function read_filters(array $source): array
{
    $from = trim((string) ($source['from'] ?? ''));
    $to   = trim((string) ($source['to']   ?? ''));
    $cat  = trim((string) ($source['cat']  ?? ''));
    $q    = mb_substr(trim((string) ($source['q'] ?? '')), 0, 100);

    // No date keys at all means "first visit" — default to this month.
    if (!array_key_exists('from', $source) && !array_key_exists('to', $source)) {
        [$from, $to] = month_bounds(current_month());
    }

    if ($from !== '' && !valid_date($from)) {
        $from = '';
    }
    if ($to !== '' && !valid_date($to)) {
        $to = '';
    }
    // A backwards range returns nothing, which looks like a bug. Swap instead.
    if ($from !== '' && $to !== '' && $from > $to) {
        [$from, $to] = [$to, $from];
    }

    if ($cat !== 'none' && $cat !== '' && !ctype_digit($cat)) {
        $cat = '';
    }

    $page = (int) ($source['page'] ?? 1);

    return ['from' => $from, 'to' => $to, 'cat' => $cat, 'q' => $q, 'page' => max(1, $page)];
}

/** Rebuild the query string for links and redirects. */
function filters_query(array $filters, array $overrides = []): string
{
    $params = array_merge([
        'from' => $filters['from'],
        'to'   => $filters['to'],
        'cat'  => $filters['cat'],
        'q'    => $filters['q'] ?? '',
        'page' => $filters['page'],
    ], $overrides);

    if ((int) ($params['page'] ?? 1) <= 1) {
        unset($params['page']);
    }
    return http_build_query($params);
}

/**
 * Turn filters into a WHERE fragment plus the values to bind.
 * The expenses table must be aliased `e`.
 */
function filters_to_sql(array $filters, int $userId): array
{
    $where  = ['e.user_id = ?'];
    $params = [$userId];

    if ($filters['from'] !== '') {
        $where[]  = 'e.spent_on >= ?';
        $params[] = $filters['from'];
    }
    if ($filters['to'] !== '') {
        $where[]  = 'e.spent_on <= ?';
        $params[] = $filters['to'];
    }
    if ($filters['cat'] === 'none') {
        $where[] = 'e.category_id IS NULL';
    } elseif ($filters['cat'] !== '') {
        $where[]  = 'e.category_id = ?';
        $params[] = (int) $filters['cat'];
    }

    if (($filters['q'] ?? '') !== '') {
        // Escape LIKE wildcards so "50%" searches for the text, not a pattern.
        $where[]  = 'e.note LIKE ?';
        $params[] = '%' . addcslashes($filters['q'], '%_\\') . '%';
    }

    return ['WHERE ' . implode(' AND ', $where), $params];
}

/** Human-readable description of the active range, used in the CSV header. */
function filters_range_label(array $filters): string
{
    $search = ($filters['q'] ?? '') !== '' ? ' (note contains "' . $filters['q'] . '")' : '';
    if ($filters['from'] === '' && $filters['to'] === '') {
        return 'All time' . $search;
    }
    $from = $filters['from'] !== '' ? nice_date($filters['from']) : 'the beginning';
    $to   = $filters['to']   !== '' ? nice_date($filters['to'])   : 'today';
    return $from . ' to ' . $to . $search;
}
