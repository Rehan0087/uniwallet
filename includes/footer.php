<?php
/**
 * Bottom of every logged-in page.
 *
 * Set $page_scripts before including to append page-specific <script> tags
 * (e.g. the Chart.js bundle plus chart setup).
 */
$page_scripts = $page_scripts ?? '';
?>
    <footer class="page-foot">
      <div class="foot-brand">
        <span class="logo-mark logo-mark-sm" aria-hidden="true"></span>
        <div>
          <strong><?= h(APP_NAME) ?></strong>
          <span>Budget tracker for UIU students</span>
        </div>
      </div>

      <nav class="foot-links" aria-label="Footer">
        <a href="expenses.php">Expenses</a>
        <a href="budget.php">Budget</a>
        <a href="goals.php">Savings goals</a>
        <a href="insights.php">Insights</a>
        <a href="export.php">Export CSV</a>
        <a href="profile.php">Profile &amp; settings</a>
      </nav>

      <p class="foot-legal">
        © <?= date('Y') ?> <?= h(APP_NAME) ?>. An independent student project, not affiliated with United International University.
      </p>
    </footer>

  </main>
</div>

<script src="<?= h(asset('assets/js/theme.js')) ?>"></script>
<script src="<?= h(asset('assets/js/app.js')) ?>"></script>
<?= $page_scripts ?>
</body>
</html>
