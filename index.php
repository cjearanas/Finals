<?php
$pageTitle  = 'Dashboard';
$activePage = 'dashboard';
require __DIR__ . '/includes/header.php';

$boxBase = 'dashboard-box h-100 position-relative d-flex flex-column align-items-center justify-content-center gap-2 rounded text-decoration-none fw-bold text-uppercase';
?>

<!-- Dashboard Cards -->
<div class="row g-3 justify-content-center">

  <div class="col-md-8">
    <div class="h-100 bg-dark text-white rounded p-3 fs-4 fw-semibold">
      Hello, <?= e($currentUser['greeting']) ?>!
    </div>
  </div>

  <div class="col-md-4">
    <div class="h-100 bg-secondary text-white rounded p-3 fw-semibold d-flex align-items-center justify-content-center">
      Issues Reported: <?= count_reports() ?>
    </div>
  </div>

  <div class="col-md-4">
    <a href="report-issue.php" class="<?= $boxBase ?> text-bg-danger">
      <i class="bi bi-exclamation-triangle fs-1"></i>
      Report an Issue
    </a>
  </div>

  <div class="col-md-4">
    <a href="maintenance-log.php" class="<?= $boxBase ?> text-bg-dark">
      <i class="bi bi-clipboard-check fs-1"></i>
      Maintenance Log
    </a>
  </div>

  <?php foreach (STATUSES as $label => $s): ?>
    <div class="col-md-4">
      <a href="maintenance-log.php#tab-<?= e($s['tab']) ?>" class="<?= $boxBase ?> text-bg-<?= e($s['badge']) ?>">
        <span class="position-absolute top-0 end-0 m-2 small"><?= count_reports($label) ?></span>
        <i class="bi <?= e($s['icon']) ?> fs-1"></i>
        <?= $label === 'Resolved' ? 'Resolved Issue' : e($label) ?>
      </a>
    </div>
  <?php endforeach; ?>

</div>

<!-- Issued Reports -->
<div class="card mt-4">
  <div class="card-header bg-dark text-white fw-semibold py-3">Issued Report</div>
  <div class="list-group list-group-flush report-scroll" data-visible="4">
    <?php foreach (get_reports() as $report): ?>
      <?php render_report($report, 'border-primary'); ?>
    <?php endforeach; ?>

    <?php if (count_reports() === 0): ?>
      <div class="list-group-item text-body-secondary">No reports yet.</div>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>