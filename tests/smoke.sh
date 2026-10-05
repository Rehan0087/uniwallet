#!/usr/bin/env bash
# Smoke test: log in as the demo user and check every page, plus the key
# security rules. Needs the app running and the demo data imported:
#
#     php -S localhost:8000 router.php        (in one terminal)
#     bash tests/smoke.sh                     (in another)
#
# Exits non-zero if anything fails. Writes nothing permanent: the one row it
# creates (an XSS probe) is removed through the app's own delete action.

BASE="${1:-http://localhost:8000}"
JAR="$(mktemp)"; trap 'rm -f "$JAR"' EXIT
pass=0; fail=0

ok()   { pass=$((pass+1)); printf '  \033[32mPASS\033[0m %s\n' "$1"; }
bad()  { fail=$((fail+1)); printf '  \033[31mFAIL\033[0m %s\n' "$1"; }
check(){ if [ "$2" = "$3" ]; then ok "$1"; else bad "$1 (expected $3, got $2)"; fi; }
csrf() { curl -s -b "$JAR" -c "$JAR" "$BASE/$1" | grep -o 'name="csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//'; }

echo "Public pages"
for p in "" login.php register.php; do
  check "GET /$p is 200" "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/$p")" 200
done
check "unknown URL is 404"            "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/no-such-page")" 404
check "includes/ is blocked (403)"    "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/includes/db.php")" 403
check "private page redirects guests" "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/dashboard.php")" 302

echo "Forgot password"
check "login page links to forgot.php" "$(curl -s "$BASE/login.php" | grep -c 'forgot.php')" 1
check "forgot.php is 200"              "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/forgot.php")" 200
check "bad reset token shows 'expired'" "$(curl -s "$BASE/reset.php?token=deadbeef" | grep -c '<h1 class="auth-title">This link has expired')" 1
T="$(csrf forgot.php)"
page="$(curl -s -b "$JAR" -c "$JAR" -d "csrf=$T&email=nobody-$RANDOM@nowhere.test" "$BASE/forgot.php")"
check "unknown email gets the generic answer" "$(echo "$page" | grep -c 'a reset link is on its way')" 1
check "unknown email leaks no reset link"     "$(echo "$page" | grep -c 'reset.php?token')" 0
check "storage/ is not downloadable"          "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/storage/outbox.log")" 403

echo "Login"
T="$(csrf login.php)"
check "wrong password is refused" \
  "$(curl -s -b "$JAR" -c "$JAR" -o /dev/null -w '%{http_code}' -d "csrf=$T&email=demo@uniwallet.test&password=nope" "$BASE/login.php")" 200
T="$(csrf login.php)"
check "demo login redirects" \
  "$(curl -s -b "$JAR" -c "$JAR" -o /dev/null -w '%{http_code}' -d "csrf=$T&email=demo@uniwallet.test&password=demo1234" "$BASE/login.php")" 302

echo "Private pages (200, no PHP errors)"
for p in dashboard.php expenses.php "expenses.php?q=a&cat=none" budget.php "budget.php?month=2026-01" \
         budget.php?month=garbage goals.php insights.php categories.php profile.php "export.php?from=&to="; do
  body="$(curl -s -b "$JAR" -w '\n%{http_code}' "$BASE/$p")"
  code="$(echo "$body" | tail -1)"
  errs="$(echo "$body" | grep -ciE 'warning:|notice:|fatal error|deprecated:|undefined (variable|index|array)')"
  if [ "$code" = 200 ] && [ "$errs" = 0 ]; then ok "$p"; else bad "$p (status $code, $errs PHP error lines)"; fi
done

echo "Security"
T="$(csrf expenses.php)"
check "POST without CSRF token is rejected" \
  "$(curl -s -b "$JAR" -o /dev/null -w '%{http_code}' -d "action=delete&expense_id=1" "$BASE/expenses.php")" 400
curl -s -b "$JAR" -c "$JAR" -o /dev/null --data-urlencode "csrf=$T" -d "action=save&expense_id=0&amount=1&spent_on=$(date +%F)" \
  --data-urlencode 'note=<script>smoke()</script>' "$BASE/expenses.php"
page="$(curl -s -b "$JAR" "$BASE/expenses.php?q=smoke")"
check "script in a note is escaped" "$(echo "$page" | grep -c '<script>smoke()')" 0
for a in -5 0 abc; do
  curl -s -b "$JAR" -c "$JAR" -o /dev/null -d "csrf=$T&action=save&expense_id=0&amount=$a&spent_on=$(date +%F)&note=BADAMT" "$BASE/expenses.php"
done
check "bad amounts are not saved" "$(curl -s -b "$JAR" "$BASE/expenses.php?q=BADAMT" | grep -c 'class="exp-note">BADAMT')" 0

# Clean up the probe row through the app (the delete form carries its id).
id="$(curl -s -b "$JAR" "$BASE/expenses.php?q=smoke" | grep -o 'name="expense_id" value="[1-9][0-9]*"' | head -1 | grep -o '[0-9]*')"
[ -n "$id" ] && curl -s -b "$JAR" -c "$JAR" -o /dev/null -d "csrf=$T&action=delete&expense_id=$id" "$BASE/expenses.php"

echo; echo "$pass passed, $fail failed"
[ "$fail" = 0 ]
