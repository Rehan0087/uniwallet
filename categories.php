<?php
/**
 * Category management.
 *
 * Not one of the six modules on its own, but the expense, budget and insight
 * pages are all organised by category, so users need a way to add their own
 * beyond the seeded defaults.
 *
 * Deleting a category keeps its expenses: the FK is ON DELETE SET NULL, so
 * they fall back to "Uncategorised" instead of vanishing from the totals.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/finance.php';

require_login();
$userId = current_user_id();

if (is_post()) {
    require_csrf();
    $action = post('action');

    if ($action === 'delete') {
        $categoryId = (int) post('category_id');
        $stmt = db()->prepare('DELETE FROM categories WHERE category_id = ? AND user_id = ?');
        $stmt->execute([$categoryId, $userId]);

        flash($stmt->rowCount() ? 'ok' : 'error',
              $stmt->rowCount()
                  ? 'Category deleted. Its expenses are now uncategorised.'
                  : 'That category no longer exists.');
        redirect('categories.php');
    }

    if ($action === 'save') {
        $categoryId = (int) post('category_id');
        $name       = post('name');
        $icon       = post('icon');

        if ($icon === '') {
            $icon = '📦';
        }

        $errors = [];
        if ($name === '' || mb_strlen($name) > 60) {
            $errors[] = 'Enter a category name of 60 characters or fewer.';
        }
        if (mb_strlen($icon) > 4) {
            $errors[] = 'Use a single emoji or a couple of characters for the icon.';
        }

        if ($errors) {
            flash('error', implode(' ', $errors));
            redirect('categories.php' . ($categoryId ? '?edit=' . $categoryId : ''));
        }

        try {
            if ($categoryId > 0) {
                $stmt = db()->prepare(
                    'UPDATE categories SET name = ?, icon = ? WHERE category_id = ? AND user_id = ?'
                );
                $stmt->execute([$name, $icon, $categoryId, $userId]);
                flash('ok', 'Category updated.');
            } else {
                $stmt = db()->prepare(
                    'INSERT INTO categories (user_id, name, icon, is_default) VALUES (?, ?, ?, 0)'
                );
                $stmt->execute([$userId, $name, $icon]);
                flash('ok', 'Category added.');
            }
        } catch (PDOException $e) {
            // uq_cat_user_name — this user already has a category by that name.
            if ($e->getCode() === '23000') {
                flash('error', 'You already have a category called "' . $name . '".');
            } else {
                throw $e;
            }
        }

        redirect('categories.php');
    }

    redirect('categories.php');
}

// --------------------------------------------------------------- load data

// Category list with lifetime usage, so deleting is an informed decision.
$stmt = db()->prepare(
    'SELECT c.category_id, c.name, c.icon, c.is_default,
            COUNT(e.expense_id) AS uses,
            COALESCE(SUM(e.amount), 0) AS spent
       FROM categories c
       LEFT JOIN expenses e ON e.category_id = c.category_id AND e.user_id = c.user_id
      WHERE c.user_id = ?
      GROUP BY c.category_id, c.name, c.icon, c.is_default
      ORDER BY c.name'
);
$stmt->execute([$userId]);
$categories = $stmt->fetchAll();
$grandTotal = array_sum(array_column($categories, 'spent'));

$editing = null;
$editId  = (int) query('edit');
if ($editId > 0) {
    $find = db()->prepare(
        'SELECT category_id, name, icon FROM categories WHERE category_id = ? AND user_id = ?'
    );
    $find->execute([$editId, $userId]);
    $editing = $find->fetch() ?: null;
}

$page_title = 'Categories';
$active_nav = 'categories';
$page_lead  = 'Group your spending however makes sense for you.';

require __DIR__ . '/includes/header.php';
?>

<div class="card">
  <div class="card-head">
    <div>
      <h2><?= $editing ? 'Rename category' : 'Add a category' ?></h2>
      <p>The icon is optional — any emoji works.</p>
    </div>
    <?php if ($editing): ?>
      <a class="btn btn-ghost btn-sm" href="categories.php">Cancel edit</a>
    <?php endif; ?>
  </div>

  <form method="post" action="categories.php" data-validated novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="category_id" value="<?= (int) ($editing['category_id'] ?? 0) ?>">

    <div class="form-grid">
      <div class="field">
        <label for="name">Name</label>
        <input type="text" id="name" name="name" maxlength="60"
               placeholder="e.g. Hostel mess"
               value="<?= h($editing['name'] ?? '') ?>"
               data-validate="required" <?= $editing ? 'data-autofocus' : '' ?>>
      </div>
      <div class="field">
        <label for="icon">Icon</label>
        <input type="text" id="icon" name="icon" maxlength="4"
               placeholder="📦" value="<?= h($editing['icon'] ?? '') ?>">
      </div>
      <div class="field">
        <button type="submit" class="btn btn-primary">
          <?= $editing ? 'Save changes' : 'Add category' ?>
        </button>
      </div>
    </div>
    <div class="emoji-picks" aria-label="Pick an icon">
      <?php foreach (['🎓','🍛','🛺','🚌','📱','📚','🏠','🎬','☕','🛒','💊','👕','🎁','⚽','✈️','💼','🖨️','💡'] as $emoji): ?>
        <button type="button" class="emoji-pick" data-emoji="<?= h($emoji) ?>" aria-label="Use <?= h($emoji) ?>"><?= h($emoji) ?></button>
      <?php endforeach; ?>
    </div>
  </form>
</div>

<div class="card card-tight">
  <div class="card-head">
    <div>
      <h2>Your categories</h2>
      <p><?= count($categories) ?> in total. Figures cover all time.</p>
    </div>
  </div>

  <?php if (!$categories): ?>
    <div class="empty">
      <div class="empty-mark" aria-hidden="true">⬡</div>
      <p>No categories yet. Add your first one above.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="data">
        <thead>
          <tr>
            <th>Category</th>
            <th>Share of spending</th>
            <th class="num">Expenses</th>
            <th class="num">Total spent</th>
            <th class="actions">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($categories as $category): ?>
            <tr>
              <td>
                <div class="exp-main">
                  <span class="exp-icon" aria-hidden="true"><?= h($category['icon']) ?></span>
                  <div>
                    <div class="exp-note"><?= h($category['name']) ?></div>
                    <?php if ((int) $category['is_default'] === 1): ?><span class="badge badge-muted">starter</span><?php endif; ?>
                  </div>
                </div>
              </td>
              <td class="cat-share">
                <?php $share = $grandTotal > 0 ? $category['spent'] / $grandTotal * 100 : 0; ?>
                <div class="progress"><div class="progress-bar" style="width: <?= round($share, 1) ?>%"></div></div>
                <small><?= round($share) ?>%</small>
              </td>
              <td class="num"><?= number_format((int) $category['uses']) ?></td>
              <td class="num"><?= h(money($category['spent'])) ?></td>
              <td class="actions">
                <a class="btn btn-ghost btn-sm"
                   href="categories.php?edit=<?= (int) $category['category_id'] ?>#name">Edit</a>
                <form method="post" action="categories.php" class="inline-form"
                      data-confirm="Delete &quot;<?= h($category['name']) ?>&quot;? Its <?= (int) $category['uses'] ?> expense(s) will become uncategorised, and any budget limit set for it will be removed.">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="category_id" value="<?= (int) $category['category_id'] ?>">
                  <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
