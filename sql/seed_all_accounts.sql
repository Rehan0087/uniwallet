-- Copy the demo account's data to every other account that has none yet.
-- Safe to re-run: accounts that already have expenses / budgets / goals are skipped,
-- and the demo account itself is never a target. Run AFTER schema.sql + demo_data.sql:
--   mysql -u root < sql/seed_all_accounts.sql

USE `uniwallet_app`;
SET @demo = (SELECT user_id FROM users WHERE email = 'demo@uniwallet.test');

-- Expenses: match categories by name, so each user's own category ids are used.
INSERT INTO expenses (user_id, category_id, amount, spent_on, note)
SELECT t.user_id, ck.category_id, e.amount, e.spent_on, e.note
  FROM expenses e
  JOIN categories cd ON cd.category_id = e.category_id AND cd.user_id = @demo
  JOIN users t ON t.user_id <> @demo
              AND NOT EXISTS (SELECT 1 FROM expenses x WHERE x.user_id = t.user_id)
  JOIN categories ck ON ck.user_id = t.user_id AND ck.name = cd.name
 WHERE e.user_id = @demo;

-- Budgets: whole-month rows (category_id IS NULL) and per-category rows.
INSERT INTO budgets (user_id, month_year, category_id, total_limit, category_limit)
SELECT t.user_id, b.month_year, ck.category_id, b.total_limit, b.category_limit
  FROM budgets b
  LEFT JOIN categories cd ON cd.category_id = b.category_id
  JOIN users t ON t.user_id <> @demo
              AND NOT EXISTS (SELECT 1 FROM budgets x WHERE x.user_id = t.user_id)
  LEFT JOIN categories ck ON ck.user_id = t.user_id AND ck.name = cd.name
 WHERE b.user_id = @demo
   AND (b.category_id IS NULL OR ck.category_id IS NOT NULL);

-- Savings goals.
INSERT INTO savings_goals (user_id, title, target_amount, saved_amount, deadline)
SELECT t.user_id, g.title, g.target_amount, g.saved_amount, g.deadline
  FROM savings_goals g
  JOIN users t ON t.user_id <> @demo
              AND NOT EXISTS (SELECT 1 FROM savings_goals x WHERE x.user_id = t.user_id)
 WHERE g.user_id = @demo;
