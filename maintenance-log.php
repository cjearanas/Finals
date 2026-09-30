<?php
$pageTitle  = 'Maintenance Log';
$activePage = 'log';
require __DIR__ . '/includes/header.php';

// Tabs: All + one per status
$tabs = ['all' => ['label' => 'All', 'status' => null]];
foreach (STATUSES as $label => $s) {
    $tabs[$s['tab']] = ['label' => $label, 'status' => $label];
}
$first = true;
?>

<?php if (isset($_GET['submitted'])): ?>
  <div class="alert alert-<?= $_GET['submitted'] === 'merged' ? 'info' : 'success' ?> alert-dismissible fade show" role="alert">
    <?php if ($_GET['submitted'] === 'merged'): ?>
      This issue was already reported, so your report was added to it.
      It now has <strong><?= (int)($_GET['n'] ?? 2) ?> reports</strong> and is prioritized.
    <?php else: ?>
      Your report was submitted and is now <strong>Under Review</strong>.
    <?php endif; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
<?php endif; ?>

<div class="card">
  <div class="card-header bg-dark text-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
    <span class="fw-semibold">Maintenance Log</span>

    <div class="input-group input-group-sm" style="max-width: 280px;">
      <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
      <input type="search" id="logSearch" class="form-control" placeholder="Search reports"
             title="Search by report ID, concern, item, location or status" autocomplete="off">
    </div>
  </div>

  <div id="searchInfo" class="small text-body-secondary px-3 pt-2" aria-live="polite" hidden></div>


  <!-- Tabs -->
  <ul class="nav nav-tabs px-3 pt-2" role="tablist">
    <?php foreach ($tabs as $id => $tab): ?>
      <li class="nav-item" role="presentation">
        <button class="nav-link <?= $id === 'all' ? 'active' : '' ?>"
                data-bs-toggle="tab" data-bs-target="#tab-<?= e($id) ?>"
                type="button" role="tab">
          <?= e($tab['label']) ?>
          <span class="badge text-bg-secondary ms-2"><?= count_reports($tab['status']) ?></span>
        </button>
      </li>
    <?php endforeach; ?>
  </ul>

  <!-- Tab content -->
  <div class="tab-content">
    <?php foreach ($tabs as $id => $tab): $reports = get_reports($tab['status']); ?>
      <div class="tab-pane fade <?= $id === 'all' ? 'show active' : '' ?>" id="tab-<?= e($id) ?>" role="tabpanel">
        <div class="list-group list-group-flush report-scroll" data-visible="4">
          <?php foreach ($reports as $report): ?>
            <?php render_report($report); ?>
          <?php endforeach; ?>

          <?php if (!$reports): ?>
            <div class="list-group-item text-body-secondary">No reports in this category.</div>
          <?php endif; ?>
          <div class="list-group-item text-body-secondary search-empty" hidden>No reports match your search.</div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<script>
  // Open the tab named in the URL hash (e.g. maintenance-log.php#tab-progress from the dashboard)
  document.addEventListener('DOMContentLoaded', function () {
    var hash = window.location.hash;
    if (!hash) return;
    var btn = document.querySelector('[data-bs-target="' + hash + '"]');
    if (btn) bootstrap.Tab.getOrCreateInstance(btn).show();
  });
</script>

<script>
// Live search across all tabs
(function () {
  var input = document.getElementById('logSearch');
  var info  = document.getElementById('searchInfo');
  var rows  = Array.prototype.slice.call(document.querySelectorAll('.report-row'));

  rows.forEach(function (r) {
    var d = {};
    try { d = JSON.parse(r.dataset.report); } catch (e) {}
    r._hay = (r.textContent + ' ' + (d.descriptions || []).join(' ')).toLowerCase().replace(/\s+/g, ' ');
  });

  function run() {
    var terms = input.value.toLowerCase().split(/\s+/).filter(Boolean);

    rows.forEach(function (r) {
      var ok = terms.every(function (t) { return r._hay.indexOf(t) !== -1; });
      r.classList.toggle('d-none', !ok);
    });

    document.querySelectorAll('.tab-pane').forEach(function (pane) {
      var n = pane.querySelectorAll('.report-row:not(.d-none)').length;
      var badge = document.querySelector('[data-bs-target="#' + pane.id + '"] .badge');
      if (badge) badge.textContent = n;
      var empty = pane.querySelector('.search-empty');
      if (empty) empty.hidden = !(terms.length && n === 0);
    });

    var all = document.querySelectorAll('#tab-all .report-row').length;
    var shown = document.querySelectorAll('#tab-all .report-row:not(.d-none)').length;
    info.textContent = terms.length ? shown + ' of ' + all + ' reports match' : '';
    info.hidden = !terms.length;

    if (window.fitReportLists) window.fitReportLists();
  }

  input.addEventListener('input', run);
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>