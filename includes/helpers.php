<?php
/**
 * Small helpers used across every page: output escaping, CSRF, flash
 * messages, and input validation.
 */

require_once __DIR__ . '/../config/config.php';

// ------------------------------------------------------------------ output

/** Escape a value for safe printing inside HTML. Use on ALL user data. */
function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Format a number as taka using Bangladeshi digit grouping (lakh/crore),
 * e.g. 1234567.5 -> "৳12,34,567.50". Whole amounts drop the ".00".
 */
function money($amount): string
{
    $amount   = (float) $amount;
    $negative = $amount < 0;
    $fixed    = number_format(abs($amount), 2, '.', '');
    [$whole, $frac] = explode('.', $fixed);

    if (strlen($whole) > 3) {
        $head  = substr($whole, 0, -3);
        $whole = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $head) . ',' . substr($whole, -3);
    }

    return ($negative ? '-' : '') . CURRENCY . $whole . ($frac === '00' ? '' : '.' . $frac);
}

/** Format a DATE string for display, e.g. "2026-08-07" -> "07 Aug 2026". */
function nice_date(?string $date): string
{
    if (!$date) {
        return '—';
    }
    $ts = strtotime($date);
    return $ts ? date('d M Y', $ts) : '—';
}

/** Turn 'YYYY-MM' into 'August 2026'. */
function month_label(string $month): string
{
    $ts = strtotime($month . '-01');
    return $ts ? date('F Y', $ts) : $month;
}

function redirect(string $to): void
{
    header('Location: ' . $to);
    exit;
}

// ------------------------------------------------------------------- input

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/** Read a trimmed string from POST. */
function post(string $key, string $default = ''): string
{
    return trim((string) ($_POST[$key] ?? $default));
}

/** Read a trimmed string from the query string. */
function query(string $key, string $default = ''): string
{
    return trim((string) ($_GET[$key] ?? $default));
}

/** Parse a money input. Returns null if it isn't a usable positive amount. */
function parse_amount(string $raw): ?float
{
    $raw = str_replace([',', ' '], '', $raw);
    if ($raw === '' || !is_numeric($raw)) {
        return null;
    }
    $value = round((float) $raw, 2);
    // DECIMAL(10,2) tops out just under 100 million.
    if ($value <= 0 || $value >= 99999999.99) {
        return null;
    }
    return $value;
}

/** True if $date is a real calendar date in YYYY-MM-DD form. */
function valid_date(string $date): bool
{
    $parts = explode('-', $date);
    if (count($parts) !== 3) {
        return false;
    }
    [$y, $m, $d] = $parts;
    return ctype_digit($y) && ctype_digit($m) && ctype_digit($d)
        && checkdate((int) $m, (int) $d, (int) $y);
}

/** True if $month looks like 'YYYY-MM'. */
function valid_month(string $month): bool
{
    return (bool) preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month);
}

/** The month to show when the user hasn't picked one. */
function current_month(): string
{
    return date('Y-m');
}

/**
 * Read a month from the query string, falling back to the current month
 * when it's missing or malformed.
 */
function month_param(string $key = 'month'): string
{
    $month = query($key);
    return valid_month($month) ? $month : current_month();
}

/** First and last day of a 'YYYY-MM' month, as YYYY-MM-DD strings. */
function month_bounds(string $month): array
{
    $start = $month . '-01';
    return [$start, date('Y-m-t', strtotime($start))];
}

/** Shift a 'YYYY-MM' month by $delta months. */
function shift_month(string $month, int $delta): string
{
    return date('Y-m', strtotime($month . '-01 ' . $delta . ' month'));
}

// -------------------------------------------------------------------- CSRF

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Hidden input to drop inside every POST form. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}

/** Abort the request if the submitted CSRF token doesn't match the session. */
function require_csrf(): void
{
    $sent = (string) ($_POST['csrf'] ?? '');
    if (!hash_equals(csrf_token(), $sent)) {
        http_response_code(400);
        exit('Invalid or expired form token. Please go back and try again.');
    }
}

// ------------------------------------------------------------------ flashes

/** Queue a one-off message for the next page render. $type: ok | error | info */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/** Pull queued messages and clear them. */
function take_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

// ------------------------------------------------------------ progress bars

/** Percentage of $limit used by $spent, capped at 100 for bar width. */
function progress_width(float $spent, ?float $limit): float
{
    if (!$limit || $limit <= 0) {
        return 0.0;
    }
    return min(100.0, ($spent / $limit) * 100);
}

/** Raw percentage (can exceed 100), for the label next to a bar. */
function progress_pct(float $spent, ?float $limit): float
{
    if (!$limit || $limit <= 0) {
        return 0.0;
    }
    return ($spent / $limit) * 100;
}

/** The signed-in user's "turn amber at" percentage (Profile & settings). Defaults to 80. */
function warn_threshold(): int
{
    $value = (int) ($GLOBALS['UW_WARN_PCT'] ?? 80);
    return ($value >= 50 && $value <= 95) ? $value : 80;
}

/** Bar colour band: ok below the warning %, warn below 100%, over at/above 100%. */
function progress_state(float $spent, ?float $limit): string
{
    $pct = progress_pct($spent, $limit);
    if ($pct >= 100) {
        return 'over';
    }
    return $pct >= warn_threshold() ? 'warn' : 'ok';
}

// ------------------------------------------------------------- trimester

/**
 * UIU runs three trimesters a year. This is a calendar approximation
 * (Spring Jan–Apr, Summer May–Aug, Fall Sep–Dec), good enough to show
 * "where you are in the year" — the real exam dates come from the university.
 *
 * @return array{name:string, label:string, days_left:int, pct:int}
 */
function uiu_term(?int $now = null): array
{
    $now   = $now ?? time();
    $month = (int) date('n', $now);
    $year  = (int) date('Y', $now);

    if ($month <= 4)      { $name = 'Spring'; $start = 1; $end = 4; }
    elseif ($month <= 8)  { $name = 'Summer'; $start = 5; $end = 8; }
    else                  { $name = 'Fall';   $start = 9; $end = 12; }

    $startTs = strtotime(sprintf('%d-%02d-01', $year, $start));
    $endTs   = strtotime(date('Y-m-t', strtotime(sprintf('%d-%02d-01', $year, $end))));
    $total   = max(1, (int) round(($endTs - $startTs) / 86400) + 1);
    $elapsed = (int) floor(($now - $startTs) / 86400) + 1;

    return [
        'name'      => $name,
        'label'     => $name . ' ' . $year,
        'days_left' => max(0, $total - $elapsed),
        'pct'       => (int) max(0, min(100, round($elapsed / $total * 100))),
    ];
}


// ------------------------------------------------------------------ theme

/** The signed-in user's saved theme: 'light', 'dark' or 'system'. */
function theme_choice(?array $user): ?string
{
    $t = $user['theme'] ?? null;
    return in_array($t, ['light', 'dark', 'system'], true) ? $t : null;
}

/**
 * Inline script for <head>: resolves the theme before first paint so dark
 * mode never flashes white. A saved account choice wins; otherwise the
 * browser's own choice (localStorage), then the OS setting.
 */
function theme_boot_script(): string
{
    return '<script>(function(){try{var r=document.documentElement,p=r.getAttribute("data-theme")'
        . '||localStorage.getItem("uw-theme")||"system";'
        . 'var d=p==="dark"||(p==="system"&&matchMedia("(prefers-color-scheme: dark)").matches);'
        . 'r.setAttribute("data-resolved",d?"dark":"light")}catch(e){}})();</script>';
}
