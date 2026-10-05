</main>

      <!-- ================= FOOTER ================= -->
      <footer class="fixtrack-footer">
        <a href="dashboard.php" class="text-decoration-none text-white d-flex align-items-center justify-content-center">
          <img src="Images/fixtrack.png" alt="FIXTRACK Logo" class="footer-logo me-2">
          <span class="fw-bold">FIXTRACK</span>
        </a>
        <div class="small text-white-50">Campus Maintenance Request and Management System</div>
      </footer>

    </div>
  </div>
</div>

<!-- Help Button -->
<button class="btn btn-light border shadow-sm rounded-circle position-fixed bottom-0 end-0 m-3 p-2 lh-1" aria-label="Help">
  <i class="bi bi-question-lg"></i>
</button>

<!-- ================= REPORT DETAILS MODAL ================= -->
<style>
  .modal-backdrop.show { opacity: .45; backdrop-filter: blur(5px); }
  .report-modal { border: 0; border-radius: 20px; overflow: hidden; font-family: 'Poppins', sans-serif; color: #012a5e; }
  .rm-header { background: #012a5e; color: #fff; padding: 1.1rem 1.5rem; border-radius: 20px; }
  .rm-header h5 { font-weight: 500; font-size: 1.5rem; }
  .rm-close { background: none; border: 0; color: #fff; font-size: 1.9rem; line-height: 1; padding: 0 .25rem; }
  .rm-body { padding: 1rem 1.5rem 1.5rem; }
  .rm-media { width: 100%; max-width: 360px; height: 200px; margin: 0 auto .75rem; background: #d9d9d9;
              display: flex; align-items: center; justify-content: center; overflow: hidden; color: #6b6b6b; text-align: center; }
  .rm-media img, .rm-media video { width: 100%; height: 100%; object-fit: contain; background: #000; }
  .rm-thumbs { display: flex; gap: .5rem; justify-content: center; flex-wrap: wrap; margin-bottom: 1rem; }
  .rm-thumbs button { width: 44px; height: 44px; padding: 0; border: 2px solid transparent; border-radius: 4px;
                      background: #d9d9d9; overflow: hidden; color: #012a5e; }
  .rm-thumbs button.active { border-color: #012a5e; }
  .rm-thumbs img { width: 100%; height: 100%; object-fit: cover; }
  .rm-list dt, .rm-list dd { font-weight: 600; margin: 0; }
  .rm-list dd { margin-bottom: .25rem; }
  .rm-pill { display: inline-flex; align-items: center; gap: .4rem; padding: .15rem .8rem; border-radius: 999px;
             font-size: .8rem; font-weight: 500; }
  .rm-pill::before { content: ''; width: 8px; height: 8px; border-radius: 50%; background: currentColor; }
  .rm-pill-secondary { background: #6c757d; color: #fff; }
  .rm-pill-warning   { background: #e8890c; color: #012a5e; }
  .rm-pill-success   { background: #16a34a; color: #fff; }
</style>

<div class="modal fade" id="reportModal" tabindex="-1" aria-labelledby="reportModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content report-modal">

      <div class="rm-header d-flex justify-content-between align-items-start">
        <div>
          <h5 id="reportModalTitle" class="mb-0">Report details</h5>
          <div id="rm-code"></div>
        </div>
        <button type="button" class="rm-close" data-bs-dismiss="modal" aria-label="Close">&times;</button>
      </div>

      <div class="rm-body">
        <div id="rm-media" class="rm-media"></div>
        <div id="rm-thumbs" class="rm-thumbs"></div>

        <dl class="rm-list mb-0">
          <dt>Concern Type</dt><dd id="rm-type"></dd>
          <div id="rm-item-wrap"><dt>Item</dt><dd id="rm-item"></dd></div>
          <dt>Location</dt><dd id="rm-location"></dd>
          <dt>Date Filed</dt><dd id="rm-filed"></dd>
          <dt>Status</dt><dd><span id="rm-status" class="rm-pill"></span></dd>
          <div id="rm-count-wrap" class="mt-1"><dt>Number of Reports</dt><dd id="rm-count"></dd></div>
          <div id="rm-desc-wrap" class="mt-1"><dt>Description</dt><dd id="rm-desc" class="fw-normal"></dd></div>
          <div id="rm-assigned-wrap" class="mt-1" hidden><dt>Assigned to</dt><dd id="rm-assigned" class="fw-normal"></dd></div>
          <div id="rm-by-wrap" class="mt-1" hidden><dt>Reported by</dt><dd id="rm-by" class="fw-normal"></dd></div>
        </dl>

        <?php if (is_admin() || is_staff()): ?>
          <!-- Task workflow: accept / decline / take / approve -->
          <div id="rm-workflow" class="mt-3 pt-3 border-top" hidden>
            <div id="rm-wf-text" class="small fw-semibold mb-2"></div>
            <div id="rm-wf-buttons" class="d-flex flex-wrap gap-2"></div>
          </div>
          <form method="post" action="task-action.php" id="rm-action-form" hidden>
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="back" value="<?= e(basename($_SERVER['PHP_SELF'])) ?>">
            <input type="hidden" name="code" id="rm-act-code">
            <input type="hidden" name="action" id="rm-act-action">
          </form>

          <?php if (is_admin()): ?>
          <form method="post" action="update-status.php" class="mt-3 pt-3 border-top" id="rm-manage" hidden>
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="back" value="<?= e(basename($_SERVER['PHP_SELF'])) ?>">
            <input type="hidden" name="code" id="rm-code-input">

            <?php if (is_admin()): ?>
              <label for="rm-assign-select" class="small fw-semibold mb-1">Assign to maintenance staff</label>
              <select name="assigned_to" id="rm-assign-select" class="form-select form-select-sm mb-2">
                <option value="">Unassigned</option>
                <?php foreach (staff_accounts() as $st): ?>
                  <option value="<?= (int)$st['id'] ?>" <?= $st['active'] ? '' : 'disabled' ?>>
                    <?= e(full_name($st)) ?><?= $st['active'] ? '' : ' (disabled)' ?>
                  </option>
                <?php endforeach; ?>
              </select>
            <?php endif; ?>

            <label for="rm-status-select" class="small fw-semibold mb-1">Update status</label>
            <div class="d-flex gap-2">
              <select name="status" id="rm-status-select" class="form-select form-select-sm">
                <?php foreach (array_keys(STATUSES) as $label): ?><option><?= e($label) ?></option><?php endforeach; ?>
              </select>
              <button type="button" id="rm-save" class="btn btn-sm text-white px-3" style="background:#012a5e">Save</button>
            </div>
          </form>
          <?php endif; ?>

          <div id="rm-confirm" class="alert alert-warning mt-3 mb-0 py-2" role="alert" hidden>
            <div id="rm-confirm-text" class="small fw-semibold mb-2"></div>
            <div class="d-flex gap-2">
              <button type="button" id="rm-confirm-yes" class="btn btn-sm btn-dark">Yes, confirm</button>
              <button type="button" id="rm-confirm-no" class="btn btn-sm btn-outline-secondary">Cancel</button>
            </div>
          </div>
        <?php endif; ?>
      </div>

    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
(function () {
  var modal = document.getElementById('reportModal');
  if (!modal) return;
  var $ = function (id) { return document.getElementById(id); };
  var DIR = 'uploads/';

  function showMedia(file) {
    var box = $('rm-media');
    box.innerHTML = '';
    if (!file) {
      var ph = document.createElement('div');
      ph.innerHTML = '<i class="bi bi-image fs-1 d-block"></i><span class="small">No photo or video attached</span>';
      box.appendChild(ph);
      return;
    }
    var el;
    if (file.kind === 'video') {
      el = document.createElement('video');
      el.controls = true;
    } else {
      el = document.createElement('img');
      el.alt = file.name;
    }
    el.src = DIR + encodeURIComponent(file.file);
    box.appendChild(el);
  }

  function fillThumbs(files) {
    var wrap = $('rm-thumbs');
    wrap.innerHTML = '';
    if (files.length < 2) return;
    files.forEach(function (f, i) {
      var b = document.createElement('button');
      b.type = 'button';
      b.title = f.name;
      if (i === 0) b.className = 'active';
      if (f.kind === 'video') {
        b.innerHTML = '<i class="bi bi-camera-video"></i>';
      } else {
        var im = document.createElement('img');
        im.src = DIR + encodeURIComponent(f.file);
        im.alt = f.name;
        b.appendChild(im);
      }
      b.addEventListener('click', function () {
        wrap.querySelectorAll('button').forEach(function (x) { x.classList.remove('active'); });
        b.classList.add('active');
        showMedia(f);
      });
      wrap.appendChild(b);
    });
  }

  modal.addEventListener('show.bs.modal', function (ev) {
    var d = JSON.parse(ev.relatedTarget.dataset.report);

    $('rm-code').textContent     = d.code;
    $('rm-type').textContent     = d.type;
    $('rm-location').textContent = d.location;
    $('rm-filed').textContent    = d.filed;

    $('rm-item').textContent = d.item;
    $('rm-item-wrap').hidden = !d.item;

    var st = $('rm-status');
    st.textContent = d.status;
    st.className = 'rm-pill rm-pill-' + d.badge;

    $('rm-count').textContent = d.count + ' reports of this issue';
    $('rm-count-wrap').hidden = d.count < 2;

    $('rm-desc').innerHTML = '';
    d.descriptions.forEach(function (t) {
      var p = document.createElement('div');
      p.textContent = t;
      $('rm-desc').appendChild(p);
    });
    $('rm-desc-wrap').hidden = d.descriptions.length === 0;

    var by = $('rm-by');                                   // admin only
    if (by) {
      by.textContent = (d.reporters || []).join(', ');
      $('rm-by-wrap').hidden = !(d.reporters && d.reporters.length);
    }
    $('rm-assigned').textContent = d.assigned + (d.assignState === 'pending' ? ' (awaiting acceptance)' : '');
    $('rm-assigned-wrap').hidden = !d.assigned;

    currentD = d;
    var cf = $('rm-confirm');
    if (cf) cf.hidden = true;
    renderWorkflow(d);

    var manage = $('rm-manage');                           // admin / maintenance staff only
    if (manage) {
      manage.hidden = !d.canUpdate;
      $('rm-code-input').value = d.code;
      $('rm-status-select').value = d.status;
      var as = $('rm-assign-select');
      if (as) as.value = d.assignedId ? String(d.assignedId) : '';
    }

    showMedia(d.files[0]);
    fillThumbs(d.files);
  });

  // Stop any playing video when the popup closes
  modal.addEventListener('hidden.bs.modal', function () { $('rm-media').innerHTML = ''; });


  // ----- Confirmation + task workflow (admin / maintenance staff) -----
  var currentD = null, confirmFn = null;

  function ask(message, fn) {
    var panel = $('rm-confirm');
    if (!panel) { fn(); return; }
    $('rm-confirm-text').textContent = message;
    confirmFn = fn;
    panel.hidden = false;
    panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }
  if ($('rm-confirm-yes')) {
    $('rm-confirm-yes').addEventListener('click', function () {
      var f = confirmFn;
      $('rm-confirm').hidden = true;
      confirmFn = null;
      if (f) f();
    });
    $('rm-confirm-no').addEventListener('click', function () {
      $('rm-confirm').hidden = true;
      confirmFn = null;
    });
  }

  function sendAction(action) {
    $('rm-act-code').value = currentD.code;
    $('rm-act-action').value = action;
    $('rm-action-form').submit();
  }

  function wfButton(label, cls, message, action) {
    var b = document.createElement('button');
    b.type = 'button';
    b.className = 'btn btn-sm ' + cls;
    b.textContent = label;
    b.addEventListener('click', function () { ask(message, function () { sendAction(action); }); });
    $('rm-wf-buttons').appendChild(b);
  }

  function renderWorkflow(d) {
    var box = $('rm-workflow');
    if (!box) return;
    var text = '';
    $('rm-wf-buttons').innerHTML = '';

    if (d.viewer === 'staff') {
      if (d.mine && d.assignState === 'pending') {
        text = 'The admin assigned this task to you. Please accept or decline it.';
        wfButton('Accept task', 'btn-success', 'Accept task ' + d.code + '? Its status will change to In Progress.', 'accept');
        wfButton('Decline', 'btn-outline-danger', 'Decline task ' + d.code + '? It will go back to the admin to assign again.', 'decline');
      } else if (d.mine && d.assignState === 'accepted' && d.status !== 'Resolved') {
        if (d.resolvePending) {
          text = 'You marked this task as resolved. Waiting for the admin to confirm.';
        } else {
          text = d.sentBack
            ? 'The admin sent this task back. Please check it, then mark it as resolved again.'
            : 'Finished the work? Mark it as resolved and the admin will confirm it.';
          wfButton('Mark as resolved', 'btn-success', 'Mark ' + d.code + ' as resolved? The admin has to confirm it before it is closed.', 'request_resolve');
        }
      } else if (d.canTake) {
        text = 'This task is open. You can ask to take it, and the admin will confirm.';
        wfButton('Take this task', 'btn-primary', 'Ask to take ' + d.code + '? It only becomes yours after the admin approves it.', 'take');
      } else if (d.requestedByMe) {
        text = 'You asked to take this task. Waiting for the admin to approve.';
        wfButton('Cancel request', 'btn-outline-secondary', 'Cancel your request for ' + d.code + '?', 'cancel_request');
      } else if (d.hasRequest && !d.assigned) {
        text = 'Another staff member already asked to take this task.';
      }
    } else if (d.viewer === 'admin') {
      if (d.resolvePending) {
        text = d.resolveBy + ' marked this task as resolved. Please check the work, then confirm.';
        wfButton('Confirm resolved', 'btn-success', 'Confirm that ' + d.code + ' is resolved? It will be closed.', 'confirm_resolved');
        wfButton('Send back', 'btn-outline-danger', 'Send ' + d.code + ' back to ' + d.resolveBy + '? It stays In Progress.', 'reject_resolved');
      } else if (d.hasRequest) {
        text = d.requestName + ' asked to take this task.';
        wfButton('Approve', 'btn-success', 'Approve ' + d.requestName + ' for ' + d.code + '? The task will be assigned to them and set to In Progress.', 'approve_request');
        wfButton('Reject', 'btn-outline-danger', 'Reject the request from ' + d.requestName + ' for ' + d.code + '?', 'reject_request');
      } else if (d.assignState === 'pending') {
        text = 'Waiting for ' + d.assigned + ' to accept this task.';
      } else if (d.declinedNote) {
        text = d.declinedNote + '. This task is unassigned.';
      }
    }
    box.hidden = text === '';
    $('rm-wf-text').textContent = text;
  }

  // Save: ask for confirmation first when the assignment changes
  var saveBtn = $('rm-save');
  if (saveBtn) {
    saveBtn.addEventListener('click', function () {
      var form = $('rm-manage');
      var sel = $('rm-assign-select');
      if (sel && currentD && sel.value !== String(currentD.assignedId || '')) {
        var msg = sel.value === ''
          ? 'Remove the current assignment from ' + currentD.code + '?'
          : 'Assign ' + currentD.code + ' to ' + sel.options[sel.selectedIndex].text.trim() + '? They will be asked to accept the task.';
        ask(msg, function () { form.submit(); });
      } else {
        form.submit();
      }
    });
  }

  // Keyboard access: Enter / Space opens the focused row
  document.addEventListener('keydown', function (e) {
    if ((e.key === 'Enter' || e.key === ' ') && e.target.classList && e.target.classList.contains('report-row')) {
      e.preventDefault();
      e.target.click();
    }
  });
})();
</script>

<script>
// Limit each .report-scroll list to data-visible rows; the rest scrolls.
(function () {
  function fit() {
    document.querySelectorAll('.report-scroll[data-visible]').forEach(function (box) {
      var n = parseInt(box.dataset.visible, 10) || 4;
      var rows = Array.prototype.filter.call(box.querySelectorAll('.report-row'), function (r) { return r.offsetParent !== null; });
      if (box.offsetParent === null) return;               // hidden tab, measured when shown
      if (rows.length <= n) { box.style.maxHeight = 'none'; return; }
      var h = 0;
      for (var i = 0; i < n; i++) h += rows[i].offsetHeight;
      box.style.maxHeight = h + 'px';
    });
  }
  window.fitReportLists = fit;
  window.addEventListener('load', fit);
  window.addEventListener('resize', fit);
  document.addEventListener('shown.bs.tab', fit);          // re-measure when a tab opens
})();
</script>
</body>
</html>