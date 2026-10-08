<?php
$pageTitle  = 'Dashboard';
$activePage = 'dashboard';
require __DIR__ . '/includes/header.php';

$boxBase = 'dashboard-box h-100 position-relative d-flex flex-column align-items-center justify-content-center gap-2 rounded text-decoration-none fw-bold text-uppercase';
?>

<?php if ($flash = flash_get()): ?>
  <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
    <?= e($flash['msg']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
<?php endif; ?>

<?php render_task_alerts(); ?>

<?php if (is_admin()): ?>

  <!-- ================= ADMIN DASHBOARD ================= -->
  <div class="row g-3">

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
      <a href="maintenance-log.php" class="<?= $boxBase ?> text-bg-dark">
        <i class="bi bi-clipboard-check fs-1"></i>
        Maintenance Log
      </a>
    </div>

    <div class="col-md-4">
      <a href="admin-accounts.php?type=user" class="<?= $boxBase ?> text-bg-secondary">
        <i class="bi bi-person-fill fs-1"></i>
        User
      </a>
    </div>

    <div class="col-md-4">
      <a href="admin-accounts.php?type=staff" class="<?= $boxBase ?> text-bg-primary">
        <i class="bi bi-person-gear fs-1"></i>
        Maintenance Staff
      </a>
    </div>

  </div>

<?php elseif (is_staff()): ?>

  <!-- ================= MAINTENANCE STAFF DASHBOARD ================= -->
  <div class="row g-3 justify-content-center">

    <div class="col-md-8">
      <div class="h-100 bg-dark text-white rounded p-3 fs-4 fw-semibold">
        Hello, <?= e($currentUser['greeting']) ?>!
      </div>
    </div>

    <div class="col-md-4">
      <div class="h-100 bg-secondary text-white rounded p-3 fw-semibold d-flex align-items-center justify-content-center">
        Active Tasks: <?= count_active_tasks() ?>
      </div>
    </div>

    <div class="col-md-4">
      <a href="maintenance-log.php" class="<?= $boxBase ?> text-bg-dark">
        <i class="bi bi-clipboard-check fs-1"></i>
        Maintenance Log
      </a>
    </div>

    <div class="col-md-4">
      <a href="maintenance-log.php?q=unassigned" class="<?= $boxBase ?> text-bg-secondary">
        <span class="position-absolute top-0 end-0 m-2 small"><?= count_available_tasks() ?></span>
        <i class="bi bi-person-plus fs-1"></i>
        Available Tasks
      </a>
    </div>

    <div class="col-md-4">
      <a href="maintenance-log.php?q=awaiting+your+acceptance" class="<?= $boxBase ?> text-bg-warning">
        <span class="position-absolute top-0 end-0 m-2 small"><?= count_awaiting_acceptance() ?></span>
        <i class="bi bi-bell fs-1"></i>
        Awaiting Acceptance
      </a>
    </div>

    <div class="col-md-4">
      <a href="maintenance-log.php?q=assigned+to+you" class="<?= $boxBase ?> text-bg-primary">
        <span class="position-absolute top-0 end-0 m-2 small"><?= count_assigned_to_me() ?></span>
        <i class="bi bi-tools fs-1"></i>
        Assigned Task
      </a>
    </div>

    <div class="col-md-4">
      <a href="maintenance-log.php?q=assigned+to+you+resolved" class="<?= $boxBase ?> text-bg-success">
        <span class="position-absolute top-0 end-0 m-2 small"><?= count_resolved_by_me() ?></span>
        <i class="bi bi-check-circle fs-1"></i>
        Resolved
      </a>
    </div>

  </div>

<?php else: ?>

  <!-- ================= USER DASHBOARD ================= -->
  <div class="row g-3 justify-content-center">

    <div class="col-md-8">
      <div class="h-100 bg-dark text-white rounded p-3 fs-4 fw-semibold">
        Hello, <?= e($currentUser['greeting']) ?>!
      </div>
    </div>

    <div class="col-md-4">
      <div class="h-100 bg-secondary text-white rounded p-3 fw-semibold d-flex align-items-center justify-content-center">
        Issues Reported: <?= count_reported_by_me() ?>
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

<?php endif; ?>

<!-- Issued Report: the same reports (and order) as the Maintenance Log -->
<div class="card mt-4">
  <div class="card-header bg-dark text-white fw-semibold py-3">Issued Report</div>
  <div class="list-group list-group-flush report-scroll" data-visible="4">
    <?php foreach (get_reports() as $report): ?>
      <?php render_report($report, is_admin() ? null : 'border-primary'); ?>
    <?php endforeach; ?>

    <?php if (count_reports() === 0): ?>
      <div class="list-group-item text-body-secondary">No reports yet.</div>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>