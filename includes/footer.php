</main>

      <!-- ================= FOOTER ================= -->
      <footer class="fixtrack-footer">
        <a href="index.php" class="text-decoration-none text-white d-flex align-items-center justify-content-center">
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
        </dl>
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

    showMedia(d.files[0]);
    fillThumbs(d.files);
  });

  // Stop any playing video when the popup closes
  modal.addEventListener('hidden.bs.modal', function () { $('rm-media').innerHTML = ''; });

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
      var rows = box.querySelectorAll('.report-row');
      if (box.offsetParent === null) return;               // hidden tab, measured when shown
      if (rows.length <= n) { box.style.maxHeight = 'none'; return; }
      var h = 0;
      for (var i = 0; i < n; i++) h += rows[i].offsetHeight;
      box.style.maxHeight = h + 'px';
    });
  }
  window.addEventListener('load', fit);
  window.addEventListener('resize', fit);
  document.addEventListener('shown.bs.tab', fit);          // re-measure when a tab opens
})();
</script>
</body>
</html>