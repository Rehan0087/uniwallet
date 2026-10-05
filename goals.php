<?php
/**
 * Savings goals — set a target, put money aside, watch the bar fill.
 *
 * Deposits move `saved_amount` directly rather than living in their own
 * table, which keeps the schema at the five tables in the deck.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/finance.php';

require_login();
$userId = current_user_id();

if (is_post()) {
    require_csrf();
    $action = post('action');
    $goalId = (int) post('goal_id');

    // ------------------------------------------------------------ delete
    if ($action === 'delete') {
        $stmt = db()->prepare('DELETE FROM savings_goals WHERE goal_id = ? AND user_id = ?');
        $stmt->execute([$goalId, $userId]);

        flash($stmt->rowCount() ? 'ok' : 'error',
              $stmt->rowCount() ? 'Goal deleted.' : 'That goal no longer exists.');
        redirect('goals.php');
    }

    // ----------------------------------------------------------- deposit
    if ($action === 'deposit') {
        $amount = parse_amount(post('amount'));
        if ($amount === null) {
            flash('error', 'Enter a deposit amount greater than zero.');
            redirect('goals.php');
        }

        // LEAST() keeps the stored total inside DECIMAL(10,2) even if someone
        // deposits repeatedly into an already-full goal.
        $stmt = db()->prepare(
            'UPDATE savings_goals
                SET saved_amount = LEAST(saved_amount + ?, 99999999.99)
              WHERE goal_id = ? AND user_id = ?'
        );
        $stmt->execute([$amount, $goalId, $userId]);

        if (!$stmt->rowCount()) {
            flash('error', 'That goal could not be found.');
            redirect('goals.php');
        }

        // Re-read so the "goal reached" message reflects the stored value.
        $check = db()->prepare(
            'SELECT title, target_amount, saved_amount FROM savings_goals WHERE goal_id = ? AND user_id = ?'
        );
        $check->execute([$goalId, $userId]);
        $goal = $check->fetch();

        if ($goal && (float) $goal['saved_amount'] >= (float) $goal['target_amount']) {
            flash('ok', 'Added ' . money($amount) . '. You have reached your "' . $goal['title'] . '" goal.');
        } else {
            flash('ok', 'Added ' . money($amount) . ' to your goal.');
        }
        redirect('goals.php');
    }

    // -------------------------------------------------------- create/edit
    if ($action === 'save') {
        $title    = post('title');
        $target   = parse_amount(post('target_amount'));
        $savedRaw = post('saved_amount');
        $deadline = post('deadline');

        $errors = [];
        if ($title === '' || mb_strlen($title) > 120) {
            $errors[] = 'Enter a goal title of 120 characters or fewer.';
        }
        if ($target === null) {
            $errors[] = 'Enter a target amount greater than zero.';
        }

        $saved = 0.0;
        if ($savedRaw !== '') {
            $parsed = parse_amount($savedRaw);
            // 0 is a legitimate "already saved" value but parse_amount rejects
            // it, so treat a literal zero as fine and anything else as an error.
            if ($parsed === null && (float) $savedRaw !== 0.0) {
                $errors[] = 'Saved-so-far must be zero or a positive number.';
            } else {
                $saved = $parsed ?? 0.0;
            }
        }

        if ($deadline !== '' && !valid_date($deadline)) {
            $errors[] = 'Pick a valid deadline, or leave it blank.';
        }

        if ($errors) {
            flash('error', implode(' ', $errors));
            redirect('goals.php' . ($goalId ? '?edit=' . $goalId : ''));
        }

        if ($goalId > 0) {
            $stmt = db()->prepare(
                'UPDATE savings_goals
                    SET title = ?, target_amount = ?, saved_amount = ?, deadline = ?
                  WHERE goal_id = ? AND user_id = ?'
            );
            $stmt->execute([$title, $target, $saved, $deadline !== '' ? $deadline : null, $goalId, $userId]);
            flash('ok', 'Goal updated.');
        } else {
            $stmt = db()->prepare(
                'INSERT INTO savings_goals (user_id, title, target_amount, saved_amount, deadline)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$userId, $title, $target, $saved, $deadline !== '' ? $deadline : null]);
            flash('ok', 'Goal created.');
        }

        redirect('goals.php');
    }

    redirect('goals.php');
}

// --------------------------------------------------------------- load data

$goals = goals_for($userId);

$editing = null;
$editId  = (int) query('edit');
if ($editId > 0) {
    $find = db()->prepare(
        'SELECT goal_id, title, target_amount, saved_amount, deadline
           FROM savings_goals WHERE goal_id = ? AND user_id = ?'
    );
    $find->execute([$editId, $userId]);
    $editing = $find->fetch() ?: null;
}

$totalTarget = array_sum(array_column($goals, 'target_amount'));
$totalSaved  = array_sum(array_column($goals, 'saved_amount'));
$completed   = count(array_filter($goals, static fn(array $g): bool => $g['saved_amount'] >= $g['target_amount']));

$page_title = 'Savings Goals';
$active_nav = 'goals';
$page_lead  = 'Put money aside for the things you are actually saving towards.';
$formOpen   = $editing !== null || !$goals;

require __DIR__ . '/includes/header.php';
?>

<?php if ($goals): ?>
<div class="grid grid-3">
  <div class="stat">
    <div class="stat-label">Saved so far</div>
    <div class="stat-value"><?= h(money($totalSaved)) ?></div>
    <div class="stat-note">across <?= count($goals) ?> goal<?= count($goals) === 1 ? '' : 's' ?></div>
  </div>
  <div class="stat">
    <div class="stat-label">Total target</div>
    <div class="stat-value"><?= h(money($totalTarget)) ?></div>
    <div class="stat-note"><?= h(money(max(0, $totalTarget - $totalSaved))) ?> to go</div>
  </div>
  <div class="stat">
    <div class="stat-label">Goals reached</div>
    <div class="stat-value"><?= $completed ?> / <?= count($goals) ?></div>
    <div class="stat-note <?= $completed === count($goals) ? 'is-ok' : '' ?>">
      <?= $completed === count($goals) ? 'All done — nice work' : 'Keep going' ?>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ------------------------------------------------------------ add / edit -->
<details class="card disclose" id="goal-form"<?= $formOpen ? ' open' : '' ?>>
  <summary class="disclose-summary">
    <span class="disclose-title"><?= $editing ? 'Edit goal' : 'New savings goal' ?></span>
    <span class="btn btn-primary btn-sm disclose-btn"><?= $editing ? 'Editing' : '+ Add a goal' ?></span>
  </summary>
  <div class="card-head">
    <div>
      <p>The deadline is optional, but it lets <?= h(APP_NAME) ?> work out a monthly pace for you.</p>
    </div>
    <?php if ($editing): ?>
      <a class="btn btn-ghost btn-sm" href="goals.php">Cancel edit</a>
    <?php endif; ?>
  </div>

  <form method="post" action="goals.php" data-validated novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="goal_id" value="<?= (int) ($editing['goal_id'] ?? 0) ?>">

    <div class="form-grid">
      <div class="field">
        <label for="title">What are you saving for?</label>
        <input type="text" id="title" name="title" maxlength="120"
               placeholder="e.g. Next trimester tuition"
               value="<?= h($editing['title'] ?? '') ?>"
               data-validate="required" <?= $editing ? 'data-autofocus' : '' ?>>
      </div>
      <div class="field">
        <label for="target_amount">Target (<?= h(CURRENCY) ?>)</label>
        <input type="number" id="target_amount" name="target_amount" step="0.01" min="0.01"
               value="<?= $editing ? h($editing['target_amount']) : '' ?>"
               data-validate="required amount">
      </div>
      <div class="field">
        <label for="saved_amount">Already saved (<?= h(CURRENCY) ?>)</label>
        <input type="number" id="saved_amount" name="saved_amount" step="0.01" min="0"
               placeholder="0.00"
               value="<?= $editing ? h($editing['saved_amount']) : '' ?>">
      </div>
      <div class="field">
        <label for="deadline">Deadline</label>
        <input type="date" id="deadline" name="deadline"
               value="<?= h($editing['deadline'] ?? '') ?>">
      </div>
      <div class="field">
        <button type="submit" class="btn btn-primary">
          <?= $editing ? 'Save changes' : 'Create goal' ?>
        </button>
      </div>
    </div>
  </form>
</details>

<!-- ----------------------------------------------------------------- goals -->
<?php if (!$goals): ?>
  <div class="card">
    <div class="empty">
      <div class="empty-mark" aria-hidden="true">◆</div>
      <p>No savings goals yet. Create one above to start tracking.</p>
    </div>
  </div>
<?php else: ?>
  <div class="grid grid-2">
    <?php foreach ($goals as $goal): ?>
      <?php
        $target    = $goal['target_amount'];
        $saved     = $goal['saved_amount'];
        $left      = max(0, $target - $saved);
        $isDone    = $saved >= $target;
        $pct       = progress_pct($saved, $target);

        // Pace hint: how much per month to finish by the deadline.
        $paceNote = '';
        if (!$isDone && $goal['deadline']) {
            $daysLeft = (int) floor((strtotime($goal['deadline']) - strtotime(date('Y-m-d'))) / 86400);
            if ($daysLeft < 0) {
                $paceNote = 'Deadline passed ' . abs($daysLeft) . ' day(s) ago';
            } elseif ($daysLeft === 0) {
                $paceNote = 'Deadline is today';
            } else {
                $monthsLeft = max(1, (int) ceil($daysLeft / 30.44));
                $paceNote   = 'Save ' . money(ceil($left / $monthsLeft)) . '/month for the next '
                            . $monthsLeft . ' month(s) · ' . $daysLeft . ' day(s) left';
            }
        }
      ?>
      <?php $overdue = !$isDone && $goal['deadline'] && strtotime($goal['deadline']) < strtotime(date('Y-m-d')); ?>
      <article class="card goal <?= $isDone ? 'is-done' : '' ?>">
        <div class="goal-top">
          <div class="ring <?= $isDone ? 'is-ok' : ($overdue ? 'is-over' : '') ?>" style="--p: <?= round(progress_width($saved, $target), 1) ?>"
               role="img" aria-label="<?= round($pct) ?> percent saved">
            <span><?= round($pct) ?>%</span>
          </div>
          <div class="goal-title">
            <h2><?= h($goal['title']) ?></h2>
            <p>
              <?php if ($isDone): ?>
                <span class="badge badge-ok">Reached</span>
              <?php elseif ($overdue): ?>
                <span class="badge badge-over">Overdue</span>
              <?php endif; ?>
              <?= $goal['deadline'] ? 'By ' . h(nice_date($goal['deadline'])) : 'No deadline' ?>
            </p>
          </div>
        </div>

        <div class="goal-figures">
          <div><small>Saved</small><strong><?= h(money($saved)) ?></strong></div>
          <div><small>Target</small><strong><?= h(money($target)) ?></strong></div>
          <div><small><?= $isDone ? 'Over target' : 'To go' ?></small><strong><?= h(money($isDone ? $saved - $target : $left)) ?></strong></div>
        </div>

        <div class="progress">
          <div class="progress-bar <?= $isDone ? 'is-ok' : '' ?>"
               style="width: <?= round(progress_width($saved, $target), 2) ?>%"></div>
        </div>

        <?php if ($paceNote !== ''): ?>
          <p class="goal-pace <?= $overdue ? 'is-over' : '' ?>"><?= h($paceNote) ?></p>
        <?php elseif ($isDone): ?>
          <p class="goal-pace is-ok">Fully funded. Nice work.</p>
        <?php endif; ?>

        <form method="post" action="goals.php" class="deposit-form" data-validated novalidate>
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="deposit">
          <input type="hidden" name="goal_id" value="<?= (int) $goal['goal_id'] ?>">
          <div class="quick-amounts" aria-label="Quick deposit amounts">
            <?php foreach ([500, 1000, 2000] as $quick): ?>
              <button type="button" class="chip chip-btn" data-quick-amount="<?= $quick ?>">+<?= h(money($quick)) ?></button>
            <?php endforeach; ?>
          </div>
          <div class="btn-row">
            <input type="number" name="amount" step="0.01" min="0.01" inputmode="decimal"
                   placeholder="Add amount" class="deposit-input"
                   data-validate="required amount"
                   aria-label="Deposit amount for <?= h($goal['title']) ?>">
            <button type="submit" class="btn btn-primary btn-sm">Deposit</button>
            <a class="btn btn-ghost btn-sm" href="goals.php?edit=<?= (int) $goal['goal_id'] ?>#title">Edit</a>
          </div>
        </form>

        <form method="post" action="goals.php" class="goal-delete"
              data-confirm="Delete the goal &quot;<?= h($goal['title']) ?>&quot;? This cannot be undone.">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="goal_id" value="<?= (int) $goal['goal_id'] ?>">
          <button type="submit" class="btn btn-danger btn-sm">Delete goal</button>
        </form>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
