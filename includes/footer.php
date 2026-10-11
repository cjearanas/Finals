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
          <div id="rm-proof-view" class="mt-1" hidden><dt>Proof of work</dt><dd id="rm-proof-links" class="fw-normal d-flex flex-wrap gap-2"></dd></div>
          <div id="rm-remarks-view" class="mt-1" hidden><dt>Remarks</dt><dd id="rm-remarks-text" class="fw-normal" style="white-space:pre-line"></dd></div>
          <div id="rm-count-wrap" class="mt-1"><dt>Number of Reports</dt><dd id="rm-count"></dd></div>
          <div id="rm-desc-wrap" class="mt-1"><dt>Description</dt><dd id="rm-desc" class="fw-normal"></dd></div>
          <div id="rm-assigned-wrap" class="mt-1" hidden><dt>Assigned to</dt><dd id="rm-assigned" class="fw-normal"></dd></div>
          <div id="rm-by-wrap" class="mt-1" hidden><dt>Reported by</dt><dd id="rm-by" class="fw-normal"></dd></div>
        </dl>

        <?php if (is_admin() || is_staff()): ?>
          <!-- Task workflow: take / approve / mark resolved -->
          <div id="rm-workflow" class="mt-3 pt-3 border-top" hidden>
            <div id="rm-wf-text" class="small fw-semibold mb-2"></div>
            <div id="rm-proof-wrap" class="mb-2" hidden>
              <label for="rm-remarks" class="small fw-semibold mb-1">Remarks <span class="text-danger">*</span></label>
              <textarea id="rm-remarks" name="remarks" form="rm-action-form" rows="2" maxlength="1000"
                        class="form-control form-control-sm mb-2" placeholder="What was done to fix the problem?"></textarea>
              <label for="rm-proof" class="small fw-semibold mb-1">Photo or video of the finished work <span class="text-danger">*</span></label>
              <input type="file" id="rm-proof" name="proof[]" form="rm-action-form" class="form-control form-control-sm"
                     accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/quicktime" multiple>
              <div class="form-text">Images or videos only, up to 50MB each.</div>
            </div>
            <div id="rm-wf-buttons" class="d-flex flex-wrap gap-2"></div>
          </div>
          <form method="post" action="task-action.php" id="rm-action-form" enctype="multipart/form-data" hidden>
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
              <label for="rm-assign-select" class="small fw-semibold mb-1">Assign to maintenance staff <span id="rm-dept" class="text-body-secondary fw-normal"></span></label>
              <select name="assigned_to" id="rm-assign-select" class="form-select form-select-sm mb-2" data-max="<?= MAX_ACTIVE_JOBS ?>">
                <option value="">Unassigned</option>
                <?php $allReports = db_read('reports'); foreach (staff_accounts() as $st): $jobs = active_jobs((int)$st['id'], $allReports); ?>
                  <option value="<?= (int)$st['id'] ?>" data-jobs="<?= $jobs ?>" data-dept="<?= e($st['department'] ?? '') ?>" data-active="<?= $st['active'] ? 1 : 0 ?>" <?= $st['active'] ? '' : 'disabled' ?>>
                    <?= e(full_name($st)) ?> (<?= $jobs ?>/<?= MAX_ACTIVE_JOBS ?> jobs<?= $jobs >= MAX_ACTIVE_JOBS ? ', full' : '' ?>)<?= $st['active'] ? '' : ' (disabled)' ?>
                  </option>
                <?php endforeach; ?>
              </select>
            <?php endif; ?>

            <input type="hidden" name="status" id="rm-status-input" value="">
            <button type="button" id="rm-save" class="btn btn-sm text-white w-100" style="background:#012a5e"></button>
            <div id="rm-wait-note" class="small text-body-secondary" hidden></div>
          </form>
          <?php endif; ?>

        <?php endif; ?>
      </div>

    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.14.5/dist/sweetalert2.all.min.js"></script>

<script>
// Notification bell: refreshes every 15 seconds
(function () {
  var badge = document.getElementById('notifBadge'), list = document.getElementById('notifList');
  if (!badge || !list) return;
  var CSRF = <?= json_encode(csrf_token()) ?>;

  function esc(t) { var d = document.createElement('div'); d.textContent = t; return d.innerHTML; }

  function draw(data) {
    var n = data.unread || 0;
    badge.hidden = n === 0;
    badge.textContent = n > 99 ? '99+' : n;
    if (!data.items.length) {
      list.innerHTML = '<div class="text-center text-body-secondary small py-4"><i class="bi bi-bell-slash fs-4 d-block mb-1"></i>No notifications yet</div>';
      return;
    }
    list.innerHTML = data.items.map(function (it) {
      return '<a href="notifications.php?go=' + it.id + '" class="d-flex gap-2 px-3 py-2 text-decoration-none border-bottom ' +
             (it.read ? 'text-body' : 'bg-primary-subtle text-body fw-semibold') + '">' +
             '<i class="bi bi-' + esc(it.icon) + ' fs-5 text-primary flex-shrink-0"></i>' +
             '<span class="flex-grow-1"><span class="d-block small">' + esc(it.text) + '</span>' +
             '<span class="text-body-secondary fw-normal" style="font-size:.72rem">' + esc(it.time) + '</span></span>' +
             (it.read ? '' : '<span class="rounded-circle bg-primary flex-shrink-0 mt-1" style="width:8px;height:8px"></span>') + '</a>';
    }).join('');
  }

  function load() {
    fetch('notifications.php?json=1', { credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (d) { if (d) draw(d); })
      .catch(function () {});
  }

  document.getElementById('notifReadAll').addEventListener('click', function () {
    var fd = new FormData();
    fd.append('csrf', CSRF); fd.append('action', 'read_all');
    fetch('notifications.php', { method: 'POST', body: fd, credentials: 'same-origin' }).then(load);
  });
  document.getElementById('notifBtn').addEventListener('show.bs.dropdown', load);

  load();
  setInterval(function () { if (!document.hidden) load(); }, 15000);
})();
</script>

<script>
// Shared "Are you sure?" popup. Returns a Promise that resolves true (confirmed) or false.
//   fxConfirm({ title, text, yes, no, icon })
// Any <form data-confirm="Message?"> is confirmed automatically before it submits
// (optional: data-confirm-title, data-confirm-yes).
window.fxConfirm = function (o) {
  o = o || {};
  if (!window.Swal) return Promise.resolve(window.confirm(o.text || o.title || 'Are you sure?'));
  return Swal.fire({
    icon: o.icon || 'warning',
    title: o.title || 'Are you sure?',
    text: o.html ? undefined : (o.text || ''),
    html: o.html || undefined,
    preConfirm: o.preConfirm || undefined,
    width: o.width || undefined,
    showCancelButton: !o.hideCancel,
    confirmButtonText: o.yes || 'Yes, confirm',
    cancelButtonText: o.no || 'Cancel',
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    reverseButtons: false,
    focusCancel: true,
    target: document.querySelector('.modal.show') || document.body
  }).then(function (r) { return !!r.isConfirmed; });
};

document.addEventListener('submit', function (e) {
  var f = e.target;
  if (!f || !f.dataset || !f.dataset.confirm || f.dataset.confirmed === '1') return;
  e.preventDefault();
  window.fxConfirm({ title: f.dataset.confirmTitle, text: f.dataset.confirm, yes: f.dataset.confirmYes })
    .then(function (ok) { if (ok) { f.dataset.confirmed = '1'; f.submit(); } });
}, true);
</script>

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
    $('rm-assigned').textContent = d.assigned;
    $('rm-assigned-wrap').hidden = !d.assigned;

    currentD = d;
    var cf = $('rm-confirm');
    if (cf) cf.hidden = true;
    renderWorkflow(d);

    var manage = $('rm-manage');                           // admin / maintenance staff only
    if (manage) {
      manage.hidden = !d.canUpdate;
      $('rm-code-input').value = d.code;
      var as = $('rm-assign-select');
      if (as) {
        // A staff member who already holds the maximum number of unresolved jobs cannot be picked,
        // unless this report is already one of theirs.
        var max = parseInt(as.dataset.max, 10);
        Array.prototype.forEach.call(as.options, function (o) {
          if (!o.value) return;
          var full = parseInt(o.dataset.jobs, 10) >= max && Number(o.value) !== d.assignedId;
          var wrongDept = o.dataset.dept !== d.dept;               // ICTC handles technology, GSO the rest
          o.hidden = wrongDept && Number(o.value) !== d.assignedId;
          o.disabled = o.dataset.active !== '1' || full || wrongDept;
        });
        as.value = d.assignedId ? String(d.assignedId) : '';
        if ($('rm-dept')) $('rm-dept').textContent = '(' + d.dept + ' staff only)';
      }
      refreshSave();
    }

    // Proof of work (photos/videos of the finished job)
    var pv = $('rm-proof-view'), pl = $('rm-proof-links');
    pl.innerHTML = '';
    (d.proof || []).forEach(function (f) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'btn btn-sm btn-outline-secondary';
      b.innerHTML = '<i class="bi bi-' + (f.kind === 'video' ? 'camera-video' : 'image') + '"></i> ';
      b.appendChild(document.createTextNode(f.name));
      b.addEventListener('click', function () { showMedia(f); $('rm-media').scrollIntoView({ behavior: 'smooth', block: 'nearest' }); });
      pl.appendChild(b);
    });
    pv.hidden = !(d.proof && d.proof.length);
    $('rm-remarks-text').textContent = d.remarks || '';
    $('rm-remarks-view').hidden = !d.remarks;

    showMedia(d.files[0]);
    fillThumbs(d.files);
  });

  // Stop any playing video when the popup closes
  modal.addEventListener('hidden.bs.modal', function () { $('rm-media').innerHTML = ''; });


  // ----- Confirmation + task workflow (admin / maintenance staff) -----
  var currentD = null, confirmFn = null;

  function ask(message, fn, yes) {
    window.fxConfirm({ text: message, yes: yes }).then(function (ok) { if (ok) fn(); });
  }

  function sendAction(action) {
    $('rm-act-code').value = currentD.code;
    $('rm-act-action').value = action;
    $('rm-action-form').submit();
  }

  // "Mark as resolved" opens one popup that asks for remarks + a photo/video, then confirms.
  function resolveDialog() {
    var code = currentD.code;
    var html =
      '<p class="small text-body-secondary mb-2">The admin has to confirm it before it is closed.</p>' +
      '<textarea id="sw-remarks" class="form-control mb-2" rows="3" maxlength="1000" placeholder="Remarks: what was done to fix the problem?"></textarea>' +
      '<div class="text-start small fw-semibold mb-1">Photo or video of the finished work</div>' +
      '<input type="file" id="sw-proof" class="form-control" multiple accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/quicktime">' +
      '<div class="text-start form-text">Images or videos only, up to 50MB each.</div>';
    window.fxConfirm({
      title: 'Mark ' + code + ' as resolved?',
      html: html,
      yes: 'Yes, mark as resolved',
      preConfirm: function () {
        var rem = document.getElementById('sw-remarks').value.trim();
        var fs  = document.getElementById('sw-proof').files, msg = '';
        if (!rem) msg = 'Please write your remarks about the work done.';
        else if (!fs.length) msg = 'Please add a photo or video of the finished work.';
        for (var i = 0; i < fs.length && !msg; i++) {
          if (!/^(image|video)\//.test(fs[i].type)) msg = '"' + fs[i].name + '" is not an image or video.';
          else if (fs[i].size > 50 * 1024 * 1024) msg = '"' + fs[i].name + '" is larger than 50MB.';
        }
        if (msg) { Swal.showValidationMessage(msg); return false; }
        $('rm-remarks').value = rem;
        $('rm-proof').files = fs;                       // hand the chosen files to the real form
        return true;
      }
    }).then(function (ok) { if (ok) sendAction('request_resolve'); });
  }

  function wfButton(label, cls, message, action, precheck, custom) {
    var b = document.createElement('button');
    b.type = 'button';
    b.className = 'btn btn-sm ' + cls;
    b.textContent = label;
    b.addEventListener('click', function () {
      if (custom) { custom(); return; }
      if (precheck && !precheck()) return;
      ask(message, function () { sendAction(action); });
    });
    $('rm-wf-buttons').appendChild(b);
  }

  function renderWorkflow(d) {
    var box = $('rm-workflow');
    if (!box) return;
    var text = '';
    $('rm-wf-buttons').innerHTML = '';
    $('rm-proof-wrap').hidden = true;

    if (d.viewer === 'staff') {
      if (d.mine && d.status !== 'Resolved') {
        if (d.resolvePending) {
          text = 'You marked this task as resolved. Waiting for the admin to confirm.';
        } else {
          text = d.sentBack
            ? 'The admin sent this task back. Please check it, then mark it as resolved again.'
            : 'Finished the work? Mark it as resolved and the admin will confirm it.';
          $('rm-proof').value = '';
          $('rm-remarks').value = '';
          wfButton('Mark as resolved', 'btn-success', '', 'request_resolve', null, resolveDialog);
        }
      } else if (d.canTake) {
        text = 'This task is open. You can ask to take it, and the admin will confirm.';
        wfButton('Take this task', 'btn-primary', 'Ask to take ' + d.code + '? It only becomes yours after the admin approves it.', 'take');
      } else if (d.atLimit) {
        text = 'You already have ' + d.maxJobs + ' unresolved jobs. Resolve one of them before taking another task.';
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
      }
    }
    box.hidden = text === '';
    $('rm-wf-text').textContent = text;
  }

  // One button: it moves the report to the next status (Under Review > In Progress > Resolved).
  // When a different staff member is picked, the button becomes "Save assignment" instead.
    var saveBtn = $('rm-save');

  function assignChanged() {
    var sel = $('rm-assign-select');
    return !!(sel && currentD && sel.value !== String(currentD.assignedId || ''));
  }
  function refreshSave() {
    if (!saveBtn || !currentD) return;
    var sel = $('rm-assign-select');
    var note = $('rm-wait-note');
    var text = '';
    var changed = assignChanged();

    if (changed && sel.value !== '') {                       // picked a staff member
      saveBtn.hidden = false;
      saveBtn.textContent = currentD.status === 'Under Review' ? 'Assign & start work' : 'Save assignment';
      $('rm-status-input').value = currentD.status;
    } else if (changed) {                                    // chose "Unassigned"
      saveBtn.hidden = false;
      saveBtn.textContent = 'Remove assignment';
      $('rm-status-input').value = 'Under Review';           // no one assigned, so it goes back to review
    } else {
      saveBtn.hidden = true;                                 // starting work needs a staff member, resolving needs the staff's submission
      if (currentD.status === 'Under Review') {
        var eligible = 0;
        if (sel) Array.prototype.forEach.call(sel.options, function (o) { if (o.value && !o.disabled) eligible++; });
        text = eligible
          ? 'Choose a maintenance staff member above. The task moves to In Progress once it is assigned.'
          : 'No available ' + currentD.dept + ' staff right now. Add or enable one under Maintenance Staff, or wait until someone has room for another job.';
      } else if (currentD.status === 'In Progress') {
        text = currentD.resolvePending
          ? 'The maintenance staff submitted the work. Use "Confirm resolved" above to review it.'
          : 'Waiting for the maintenance staff to submit the finished work. You can confirm it once they do.';
      }
    }
    if (note) { note.textContent = text; note.hidden = text === ''; }
  }
  if ($('rm-assign-select')) $('rm-assign-select').addEventListener('change', refreshSave);

  if (saveBtn) {
    saveBtn.addEventListener('click', function () {
      var form = $('rm-manage');
      var sel = $('rm-assign-select');
      var msg, yes;
      if (assignChanged()) {
        msg = sel.value === ''
          ? 'Remove the current assignment from ' + currentD.code + '?'
          : 'Assign ' + currentD.code + ' to ' + sel.options[sel.selectedIndex].text.trim() + '? The task goes to them right away and is set to In Progress.';
        yes = 'Yes, save it';
      } else {
        msg = 'Save the change to ' + currentD.code + '?';
        yes = 'Yes, save it';
      }
      ask(msg, function () { form.submit(); }, yes);
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