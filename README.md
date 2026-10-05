# UniWallet

A budget tracker for UIU students in Dhaka: log expenses in taka (৳), set
monthly budgets, track savings goals, and see where the money actually went.
Includes a landing page, a dashboard that works out what is safe to spend
today, light and dark themes, and a profile & settings page.

Built with plain HTML/CSS/JS, PHP 8 + PDO, and MySQL — no framework, no ORM,
no build step. The only third-party library is Chart.js, and it is vendored
locally, along with the fonts, so the app works with no internet connection.

> An independent student project, not affiliated with United International University.

---

## Setup (XAMPP)

1. **Copy the project into your web root.**

   Put this `uniwallet` folder inside XAMPP's `htdocs`, so you end up with
   `xampp/htdocs/uniwallet/`.

2. **Start Apache and MySQL** from the XAMPP control panel.

3. **Create the database.** Open <http://localhost/phpmyadmin>, go to the
   **Import** tab, choose `sql/schema.sql`, and click Go. That creates the
   `uniwallet_app` database and all five tables.

4. **(Optional) Load the demo data.** Import `sql/demo_data.sql` the same way
   for an account with six months of expenses, budgets and goals already in
   it — useful for a demo or screenshots.

   | | |
   |---|---|
   | email | `demo@uniwallet.test` |
   | password | `demo1234` |

5. **Open** <http://localhost/uniwallet/> and register an account.

If you set up the database before the profile page and dark theme existed,
run `sql/upgrade_warn_pct.sql`, `sql/upgrade_theme.sql` and
`sql/upgrade_password_resets.sql` once (a fresh import of `schema.sql` already
includes them).

### Forgot password

A fresh XAMPP has no mail server, so with `RESET_SHOW_LINK` set to `true` in
`config/config.php` the reset link is **shown on the page** (and also written to
`storage/outbox.log`) so the feature can be demonstrated. Set it to `false`
once email works, and the link is only ever sent by `mail()` to the user's
inbox. On Linux, Apache must be able to write to `storage/`:
`chmod 777 storage` (or `chown` it to the Apache user).

Links work once, expire after `RESET_TTL_MINUTES` (60), and the page answers
the same whether or not the email is registered.

**Not using XAMPP?** Run PHP's built-in server with the router so unknown URLs
get the friendly 404 page and `includes/` stays blocked:

```
php -S localhost:8000 router.php
```

Error pages (403 / 404 / 500) live in `error.php` and `includes/error_page.php`.
On Apache they are wired up by `.htaccess` (needs `mod_rewrite`).

If your MySQL user is not the default `root` with a blank password, update
`config/config.php`.

---

## Configuration

Everything adjustable lives at the top of `config/config.php`:

| Setting | What it does |
|---|---|
| `DB_HOST` / `DB_NAME` / `DB_USER` / `DB_PASS` | Database connection |
| `CURRENCY` | Currency symbol used across the UI — change to `₹`, `€`, `£`, `৳`… |
| `APP_TIMEZONE` | Drives "today" and which month the dashboard opens on |
| `APP_DEBUG` | `true` shows PHP errors on screen. **Set to `false` before submitting.** |

---

## The modules

| Module | Pages | Notes |
|---|---|---|
| 1. Accounts | `register.php`, `login.php`, `logout.php`, `forgot.php`, `reset.php` | Passwords hashed with `password_hash()`; sessions regenerated on login; password show/hide and strength hint; **forgot password** with one-time, expiring links |
| 2. Expense tracking | `expenses.php` | Add / edit / delete, search notes, filter by date and category, grouped by day, paginated 20 per page |
| 3. Monthly budget | `budget.php` | Whole-month cap plus per-category caps, live allocation meter, suggested split, copy from last month; bars turn amber at your chosen % and red at 100% |
| 4. Savings goals | `goals.php` | Targets, deposits with quick amounts, deadlines, and a "save X per month" pace hint |
| 5. Spending insights | `insights.php` | Category doughnut, 6-month trend, spending pace vs budget, weekday pattern, biggest expenses (Chart.js) |
| 6. Reports & export | `export.php` | CSV via PHP's built-in `fputcsv()` — no PDF library needed |
| 7. Profile & settings | `profile.php` | Name/email, password change, light/dark/system theme, budget-alert level, data export, account deletion |

`index.php` is the public landing page (with an allowance calculator),
`dashboard.php` pulls the highlights into one screen, and `categories.php`
manages the category list the other pages are organised by. Friendly 403 /
404 / 500 pages come from `error.php`.

---

## Project structure

```
uniwallet/
├── index.php              landing page (guests) / redirect to dashboard
├── login.php  register.php  logout.php
├── forgot.php  reset.php  forgot password and the page the emailed link opens
├── dashboard.php          month-at-a-glance, quick-add, alerts
├── expenses.php           add / edit / delete / search / filter
├── budget.php             monthly and per-category limits
├── goals.php              savings goals and deposits
├── insights.php           charts and stats
├── categories.php         manage categories
├── profile.php            profile & settings
├── export.php             CSV download
├── error.php  router.php  .htaccess     403/404/500 pages and routing
│
├── config/config.php      database credentials and app settings
│
├── includes/
│   ├── db.php             PDO connection
│   ├── auth.php           sessions, login, page guards
│   ├── helpers.php        escaping, CSRF, flash, money, trimester, theme
│   ├── finance.php        shared spending / budget / goal queries
│   ├── expense_filters.php  filter logic shared by the list and the export
│   ├── password_reset.php token issue / redeem logic for forgot password
│   ├── error_page.php     error pages that work even if the DB is down
│   ├── header.php  footer.php            layout for logged-in pages
│   └── auth_header.php  auth_footer.php  layout for login / register
│
├── assets/
│   ├── css/style.css      all styling, including the dark theme
│   ├── js/                app, charts, theme, budget, profile, landing
│   ├── fonts/             Bricolage Grotesque, Plus Jakarta Sans, Hind Siliguri
│   ├── img/favicon.svg
│   └── vendor/chart.umd.min.js
│
├── sql/
│   ├── schema.sql         tables — import this first
│   ├── demo_data.sql      optional sample data (BDT, UIU categories)
│   └── upgrade_*.sql      one-off column additions for older databases
│
├── storage/               runtime files (the demo-mode mail log); blocked from the web
└── tests/smoke.sh         end-to-end smoke test (see Testing notes)
```

---

## Database

Five tables, as designed:

**users** — `user_id`, `full_name`, `email` (unique), `password_hash`, `warn_pct`, `theme`, `created_at`

**categories** — `category_id`, `user_id`, `name`, `icon`, `is_default`
Each account gets its own copy of the eight default categories at
registration. `(user_id, name)` is unique, so you cannot create two
categories with the same name.

**expenses** — `expense_id`, `user_id`, `category_id`, `amount`, `spent_on`, `note`
`category_id` is nullable on purpose: deleting a category leaves its expenses
in place as "Uncategorised" rather than deleting spending history with it.

**budgets** — `budget_id`, `user_id`, `month_year`, `category_id`, `total_limit`, `category_limit`
One table covers both kinds of limit for a given `month_year` (`'YYYY-MM'`):

- `category_id IS NULL` → the row's `total_limit` is the whole-month cap
- `category_id IS NOT NULL` → the row's `category_limit` caps that category

MySQL treats `NULL`s as distinct in a `UNIQUE` key, so the "only one
whole-month row per month" rule is enforced in PHP rather than by an index —
see `save_overall_limit()` in `includes/finance.php`.

**savings_goals** — `goal_id`, `user_id`, `title`, `target_amount`, `saved_amount`, `deadline`
Deposits update `saved_amount` directly, which keeps the schema at five tables.

---

## Security

- **SQL injection** — every query is a PDO prepared statement with bound
  parameters. `PDO::ATTR_EMULATE_PREPARES` is off, so these are real
  server-side prepares. The two places that interpolate (`LIMIT`/`OFFSET`)
  cast to `int` first, because those cannot be bound parameters.
- **Passwords** — stored with `password_hash()` (bcrypt), checked with
  `password_verify()`, and re-hashed automatically if PHP's default cost
  changes. Plain-text passwords are never stored or logged.
- **XSS** — all user-supplied output goes through `h()`
  (`htmlspecialchars`). Chart figures reach JavaScript as a JSON block, never
  as interpolated code.
- **CSRF** — every state-changing form carries a per-session token that is
  checked with `hash_equals()` before anything is written.
- **Access control** — every query filters on `user_id`, including updates
  and deletes, so one account cannot read or modify another's rows even by
  guessing an id.
- **Session fixation** — the session id is regenerated on login.
- **Direct include access** — `.htaccess` files block browser access to
  `includes/` and `config/`.

---

## Testing notes

The SQL was verified against MariaDB 11 with `ONLY_FULL_GROUP_BY` enabled
(stricter than XAMPP's default), and the application was driven end to end
through registration, expense CRUD, filtering, budgets, goal deposits,
insights, CSV export, cross-account isolation and logout.

A smoke test covers every page and the main security rules (CSRF, escaping,
input validation, access control). With the app running and the demo data
imported:

```
bash tests/smoke.sh
```

One thing worth keeping: `APP_DEBUG` is `true` by default so setup problems
are visible. Turn it off before submitting — on PHP 8.4+ a stray notice
printed mid-response is untidy on a page, and `export.php` explicitly
suppresses error display for exactly this reason (a notice landing inside a
CSV would corrupt the download).

---

## Scope

**In this version:** accounts, expense tracking, monthly budgets with
progress bars, savings goals, spending insights, CSV export, profile &
settings, light/dark theme, friendly error pages.

**Deliberately left for later:** email and browser
reminders, PDF export, spending prediction, shared/group budgets, bKash/Nagad
import, a Bangla interface, and a mobile app.
