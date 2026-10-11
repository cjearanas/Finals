<?php
require_once __DIR__ . '/includes/data.php';
require_login();
if (is_admin() || is_staff()) {   // admins and maintenance staff handle reports, they don't file them
    header('Location: dashboard.php');
    exit;
}

$campus = campus_layout();
$items  = concern_items();
$roomTypes = [];
foreach ($campus as $floors) {
    foreach ($floors as $rooms) {
        foreach ($rooms as $rm) {
            $roomTypes[$rm] = room_concerns($rm);
        }
    }
}
$errors = [];
$old    = ['building' => '', 'floor' => '', 'room' => '', 'type' => '', 'item' => '', 'description' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // If the upload was bigger than post_max_size, PHP drops all fields
    if (empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        $errors[] = 'The upload was too large for the server. Try smaller files.';
    } else {
        foreach ($old as $key => $_) {
            $old[$key] = trim($_POST[$key] ?? '');
        }

        if (!csrf_valid($_POST['csrf'] ?? null)) {
            $errors[] = 'Your session expired. Please try again.';
        }
        if (!isset($campus[$old['building']])) {
            $errors[] = 'Please select a building.';
        } elseif (!isset($campus[$old['building']][$old['floor']])) {
            $errors[] = 'Please select a floor.';
        } elseif (!in_array($old['room'], $campus[$old['building']][$old['floor']], true)) {
            $errors[] = 'Please select a room.';
        }
        if (!isset($items[$old['type']]) || !in_array($old['type'], room_concerns($old['room']), true)) {
            $errors[] = 'Please select a concern type that fits the room.';
        } elseif (!in_array($old['item'], $items[$old['type']], true)) {
            $errors[] = 'Please select an item.';
        }
        if (mb_strlen($old['description']) > 1000) {
            $errors[] = 'Description is too long (max 1000 characters).';
        }

        $saved = [];
        if (!$errors) {
            $saved = handle_uploads($_FILES['files'] ?? [], $errors);
            if (!$errors && !$saved) {
                $errors[] = 'Please attach at least one photo or video of the problem.';
            }
        }

        if (!$errors) {
            $res = add_report($old['type'], $old['item'], $old['building'], $old['floor'], $old['room'],
                              $old['description'], $saved, (int)current_user()['id']);
            // Post/Redirect/Get
            $qs = $res['status'] === 'new' ? 'submitted=1'
                : ($res['status'] === 'merged' ? 'submitted=merged&n=' . $res['count'] : 'submitted=self&n=' . $res['count']);
            header('Location: maintenance-log.php?' . $qs);
            exit;
        }
    }
}

$pageTitle  = 'Report an Issue';
$activePage = 'report';


// Building photos: assets/buildings/<slug>.jpg|jpeg|png|webp wins over the placeholder <slug>.svg
$buildingImages = [];
foreach (array_keys($campus) as $b) {
    $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($b)), '-');
    $img  = 'images/' . $slug . '.svg';
    foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
        if (is_file(__DIR__ . '/images/' . $slug . '.' . $ext)) {
            $img = 'images/' . $slug . '.' . $ext;
            break;
        }
    }
    $buildingImages[$b] = $img;
}

$pageTitle  = 'Report an Issue';
$activePage = 'report';

$extraHead = <<<'HTML'
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
<style>
  :root { --navy: #012a5e; }
  .report-card { border: 2px solid var(--navy); border-radius: 18px; font-family: 'Poppins', sans-serif; overflow: hidden; }
  .report-form { max-width: 760px; margin: 0 auto; }
  .report-form label.field-label { font-weight: 500; font-size: 1.05rem; }
  .report-form .form-select,
  .report-form .form-control { border: 1px solid #adb5bd; border-radius: 6px; box-shadow: 0 2px 4px rgba(0,0,0,.25); }
  .report-form .form-select:disabled { background-color: #f1f3f5; box-shadow: none; }

  /* Stepper */
  .wiz-head { display: flex; align-items: center; gap: .5rem; padding: 1.1rem 1.5rem; border-bottom: 1px solid #dee2e6; flex-wrap: wrap; }
  .wiz-tab { display: flex; align-items: center; gap: .75rem; background: none; border: 0; padding: .25rem .5rem; text-align: left; font-family: inherit; }
  .wiz-tab:disabled { cursor: default; }
  .wiz-num { width: 40px; height: 40px; border-radius: 8px; background: #eef0f3; color: #495057; font-weight: 600;
             display: flex; align-items: center; justify-content: center; flex: none; }
  .wiz-title { font-weight: 600; color: #8a919a; line-height: 1.15; font-size: .95rem; }
  .wiz-sub   { font-size: .8rem; color: #a6acb3; }
  .wiz-tab.active .wiz-num { background: var(--navy); color: #fff; box-shadow: 0 3px 8px rgba(1,42,94,.35); }
  .wiz-tab.active .wiz-title { color: var(--navy); }
  .wiz-tab.active .wiz-sub { color: #495057; }
  .wiz-tab.done .wiz-num { background: #d6e4f7; color: var(--navy); }
  .wiz-tab.done .wiz-title { color: var(--navy); }
  .wiz-arrow { color: #6c757d; }
  @media (max-width: 767px) { .wiz-sub, .wiz-arrow { display: none; } .wiz-head { gap: .25rem; } }

  .wiz-step { display: none; }
  .wiz-step.active { display: block; animation: wizIn .2s ease; }
  @keyframes wizIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }
  .wiz-h { font-weight: 600; font-size: 1.15rem; margin-bottom: .1rem; }
  .wiz-p { color: #6c757d; font-size: .9rem; margin-bottom: 1.1rem; }

  /* Building cards */
  .bld-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; }
  .bld-card { border: 2px solid #dee2e6; border-radius: 12px; overflow: hidden; background: #fff; padding: 0; text-align: left;
              cursor: pointer; transition: border-color .15s, box-shadow .15s, transform .15s; font-family: inherit; }
  .bld-card:hover { border-color: #8aa6cf; transform: translateY(-2px); }
  .bld-card img { width: 100%; height: 150px; object-fit: cover; display: block; background: #e9ecef; }
  .bld-card .bld-name { padding: .65rem .9rem; font-weight: 600; color: var(--navy); display: flex; justify-content: space-between; align-items: center; }
  .bld-card .bld-name i { visibility: hidden; color: #16a34a; }
  .bld-card.selected { border-color: var(--navy); box-shadow: 0 0 0 3px rgba(1,42,94,.2); }
  .bld-card.selected .bld-name i { visibility: visible; }
  @media (max-width: 575px) { .bld-grid { grid-template-columns: 1fr; } }

  /* Floor / room choices */
  .choice-grid { display: flex; flex-wrap: wrap; gap: .6rem; }
  .choice { border: 1px solid #adb5bd; background: #fff; border-radius: 8px; padding: .55rem 1.1rem; font-weight: 500;
            font-family: inherit; color: #212529; transition: all .12s; }
  .choice:hover { border-color: var(--navy); }
  .choice.selected { background: var(--navy); color: #fff; border-color: var(--navy); }
  .summary-chip { display: inline-flex; align-items: center; gap: .5rem; background: #eef3fa; color: var(--navy); border-radius: 999px;
                  padding: .35rem .9rem; font-size: .85rem; font-weight: 500; margin-bottom: 1rem; }

  .wiz-error { color: #dc3545; font-size: .875rem; margin-top: .75rem; min-height: 1.2rem; }
  .btn-wiz { border-radius: 8px; font-weight: 600; padding: .55rem 1.4rem; }
  .btn-wiz-prev { background: #c9d0d8; color: #fff; border: 0; }
  .btn-wiz-prev:hover { background: #aeb7c1; color: #fff; }
  .btn-wiz-prev:disabled { opacity: .6; }

  .dropzone { border: 1px solid #adb5bd; border-radius: 6px; box-shadow: 0 2px 4px rgba(0,0,0,.25);
              padding: 1.1rem 1.25rem; background: #fff; transition: background .15s, border-color .15s; }
  .dropzone.is-invalid { border-color: #dc3545; box-shadow: 0 0 0 .2rem rgba(220,53,69,.25); }
  .dropzone.dragover { background: #e7f0ff; border-color: var(--navy); }
  .dropzone .dz-text { font-size: 1.1rem; color: #555; line-height: 1.1; }
  .dropzone .dz-hint { font-size: .75rem; color: #444; }
  .btn-browse { border: 1px solid var(--navy); color: var(--navy); font-weight: 600; border-radius: 8px; background: #fff; }
  .btn-browse:hover { background: var(--navy); color: #fff; }
  .btn-navy { background: var(--navy); color: #fff; font-weight: 600; letter-spacing: .03em;
              border-radius: 8px; padding: .55rem 1.6rem; }
  .btn-navy:hover { background: #001c40; color: #fff; }
  .file-thumb { width: 40px; height: 40px; object-fit: cover; border-radius: 4px; }
</style>
HTML;

require __DIR__ . '/includes/header.php';
?>

<div class="card report-card">
  <form id="reportForm" action="report-issue.php" method="post" enctype="multipart/form-data" novalidate>
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="building" id="building" value="<?= e($old['building']) ?>">
    <input type="hidden" name="floor" id="floor" value="<?= e($old['floor']) ?>">
    <input type="hidden" name="room" id="room" value="<?= e($old['room']) ?>">

    <!-- Stepper -->
    <div class="wiz-head" id="wizHead">
      <button type="button" class="wiz-tab" data-go="1">
        <span class="wiz-num">1</span>
        <span><span class="wiz-title d-block">Building</span><span class="wiz-sub">Choose the building</span></span>
      </button>
      <i class="bi bi-chevron-right wiz-arrow"></i>
      <button type="button" class="wiz-tab" data-go="2">
        <span class="wiz-num">2</span>
        <span><span class="wiz-title d-block">Floor &amp; Room</span><span class="wiz-sub">Where is the problem</span></span>
      </button>
      <i class="bi bi-chevron-right wiz-arrow"></i>
      <button type="button" class="wiz-tab" data-go="3">
        <span class="wiz-num">3</span>
        <span><span class="wiz-title d-block">Concern Details</span><span class="wiz-sub">Describe &amp; upload</span></span>
      </button>
    </div>

    <div class="p-4 p-md-5">
      <div class="report-form">

        <?php if ($errors): ?>
          <div class="alert alert-danger">
            <ul class="mb-0">
              <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <!-- Step 1: building -->
        <section class="wiz-step" data-step="1">
          <div class="wiz-h">Choose the building</div>
          <div class="wiz-p">Tap the building where the problem is.</div>
          <div class="bld-grid">
            <?php foreach ($buildingImages as $name => $img): ?>
              <button type="button" class="bld-card" data-building="<?= e($name) ?>">
                <img src="<?= e($img) ?>" alt="<?= e($name) ?>" loading="lazy">
                <span class="bld-name"><?= e($name) ?> <i class="bi bi-check-circle-fill"></i></span>
              </button>
            <?php endforeach; ?>
          </div>
        </section>

        <!-- Step 2: floor and room -->
        <section class="wiz-step" data-step="2">
          <div class="wiz-h">Choose the floor and room</div>
          <div class="wiz-p">Pick the floor first, then the room.</div>
          <div class="summary-chip"><i class="bi bi-building"></i> <span id="chipBuilding"></span></div>

          <div class="field-label fw-semibold mb-2">Floor</div>
          <div id="floorChoices" class="choice-grid mb-4"></div>

          <div id="roomWrap" hidden>
            <div class="field-label fw-semibold mb-2">Room</div>
            <div id="roomChoices" class="choice-grid"></div>
          </div>
        </section>

        <!-- Step 3: concern details -->
        <section class="wiz-step" data-step="3">
          <div class="wiz-h">Concern details</div>
          <div class="wiz-p">Tell us what is wrong and attach a photo or video.</div>
          <div class="summary-chip"><i class="bi bi-geo-alt"></i> <span id="chipPlace"></span></div>

          <div class="row g-3 align-items-center mb-4">
            <label for="type" class="col-sm-4 field-label">Concern Type</label>
            <div class="col-sm-8"><select id="type" name="type" class="form-select" required></select></div>

            <label for="item" class="col-sm-4 field-label">Item</label>
            <div class="col-sm-8"><select id="item" name="item" class="form-select" required></select></div>
          </div>

          <div class="mb-4">
            <label for="description" class="field-label d-block mb-2">Describe Your Concern</label>
            <textarea id="description" name="description" rows="4" maxlength="1000"
                      class="form-control"><?= e($old['description']) ?></textarea>
          </div>

          <div class="mb-2">
            <label class="field-label d-block mb-2">Upload Files <span class="text-danger" title="Required">*</span></label>
            <div id="dropzone" class="dropzone d-flex align-items-center gap-3 flex-wrap">
              <i class="bi bi-file-earmark-arrow-up fs-1 text-secondary"></i>
              <div class="flex-grow-1">
                <div class="dz-text">Drag files to upload or</div>
                <div class="dz-hint">Max. file size: 50MB &middot; Images or videos only &middot; At least 1 required</div>
              </div>
              <button type="button" id="browseBtn" class="btn btn-browse px-4">Browse Files</button>
              <input type="file" id="files" name="files[]" accept="image/*,video/*" multiple hidden>
            </div>
            <div id="fileError" class="text-danger small mt-2"></div>
            <ul id="fileList" class="list-group mt-2"></ul>
          </div>
        </section>

        <div id="wizError" class="wiz-error" role="alert"></div>

        <div class="d-flex justify-content-between mt-3">
          <button type="button" id="prevBtn" class="btn btn-wiz btn-wiz-prev"><i class="bi bi-arrow-left me-1"></i> Previous</button>
          <button type="button" id="nextBtn" class="btn btn-wiz btn-navy">Next <i class="bi bi-arrow-right ms-1"></i></button>
          <button type="submit" id="submitBtn" class="btn btn-wiz btn-navy" hidden>Submit Report <i class="bi bi-send ms-1"></i></button>
        </div>

      </div>
    </div>
  </form>
</div>

<script>
(function () {
  const campus = <?= json_encode($campus, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  const items  = <?= json_encode($items,  JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  const roomTypes = <?= json_encode($roomTypes, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  const old    = <?= json_encode($old,    JSON_HEX_TAG | JSON_HEX_AMP) ?>;

  const $ = id => document.getElementById(id);
  const building = $('building'), floor = $('floor'), room = $('room'), type = $('type'), item = $('item');
  const floorsOf = b => Object.keys(campus[b] || {});
  const roomsOf  = (b, f) => (campus[b] && campus[b][f]) || [];

  function fill(select, values, placeholder, selected) {
    select.innerHTML = '';
    select.add(new Option(placeholder, '', true, true));
    select.options[0].disabled = true;
    values.forEach(v => select.add(new Option(v, v, false, v === selected)));
    select.disabled = values.length === 0;
  }

  // ---------- Wizard ----------
  let step = 1;
  const steps = document.querySelectorAll('.wiz-step');
  const tabs  = document.querySelectorAll('.wiz-tab');
  const prevBtn = $('prevBtn'), nextBtn = $('nextBtn'), submitBtn = $('submitBtn'), wizError = $('wizError');

  function valid(n) {
    if (n === 1) return !!building.value && !!campus[building.value];
    if (n === 2) return !!floor.value && !!room.value;
    return true;
  }
  function maxReachable() { return !valid(1) ? 1 : (!valid(2) ? 2 : 3); }

  function show(n) {
    step = n;
    wizError.textContent = '';
    steps.forEach(s => s.classList.toggle('active', Number(s.dataset.step) === n));
    tabs.forEach(t => {
      const k = Number(t.dataset.go);
      t.classList.toggle('active', k === n);
      t.classList.toggle('done', k < n);
      t.disabled = k > maxReachable();
    });
    prevBtn.disabled = n === 1;
    nextBtn.hidden = n === 3;
    submitBtn.hidden = n !== 3;
    if (n === 2) { $('chipBuilding').textContent = building.value; drawFloors(); }
    if (n === 3) {
      $('chipPlace').textContent = building.value + ' · ' + floor.value + ' · ' + room.value;
      if (!type.options.length || type.dataset.room !== room.value) {
        type.dataset.room = room.value;
        fill(type, (roomTypes[room.value] || []), 'Select a concern type', old.room === room.value ? old.type : '');
        fill(item, items[type.value] || [], 'Select an item', old.room === room.value ? old.item : '');
      }
    }
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  function next() {
    if (!valid(step)) {
      wizError.textContent = step === 1 ? 'Please choose a building to continue.' : 'Please choose a floor and a room to continue.';
      return;
    }
    show(step + 1);
  }
  nextBtn.addEventListener('click', next);
  prevBtn.addEventListener('click', () => show(step - 1));
  tabs.forEach(t => t.addEventListener('click', () => { const k = Number(t.dataset.go); if (k <= maxReachable()) show(k); }));

  // Step 1: building cards
  const cards = document.querySelectorAll('.bld-card');
  function markBuilding() { cards.forEach(c => c.classList.toggle('selected', c.dataset.building === building.value)); }
  cards.forEach(c => c.addEventListener('click', () => {
    if (building.value !== c.dataset.building) { building.value = c.dataset.building; floor.value = ''; room.value = ''; }
    markBuilding();
    wizError.textContent = '';
    show(2);                                               // go straight to the floors
  }));

  // Step 2: floor + room buttons
  function choice(label, selected, onClick) {
    const b = document.createElement('button');
    b.type = 'button';
    b.className = 'choice' + (selected ? ' selected' : '');
    b.textContent = label;
    b.addEventListener('click', onClick);
    return b;
  }
  function drawFloors() {
    const box = $('floorChoices');
    box.innerHTML = '';
    floorsOf(building.value).forEach(f => box.appendChild(choice(f, f === floor.value, () => {
      if (floor.value !== f) { floor.value = f; room.value = ''; }
      wizError.textContent = '';
      drawFloors();
    })));
    drawRooms();
  }
  function drawRooms() {
    const box = $('roomChoices');
    box.innerHTML = '';
    $('roomWrap').hidden = !floor.value;
    if (!floor.value) return;
    roomsOf(building.value, floor.value).forEach(r => box.appendChild(choice(r, r === room.value, () => {
      room.value = r;
      wizError.textContent = '';
      drawRooms();
      tabs.forEach(t => { t.disabled = Number(t.dataset.go) > maxReachable(); });
    })));
  }

  // Step 3: concern type depends on the room, item on the concern type
  type.addEventListener('change', () => fill(item, items[type.value] || [], 'Select an item'));

  // ---------- File upload (images / videos only, 50MB) ----------
  const MAX = 50 * 1024 * 1024;
  const dz = $('dropzone'), input = $('files'), list = $('fileList'), errBox = $('fileError');
  let selected = [];
  const form = $('reportForm');

  form.addEventListener('submit', function (e) {
    if (form.dataset.confirmed === '1') return;
    e.preventDefault();
    if (step !== 3) { next(); return; }                    // Enter key on an earlier step just moves on

    let msg = '';
    if (!type.value) { msg = 'Please choose a concern type.'; type.focus(); }
    else if (!item.value) { msg = 'Please choose an item.'; item.focus(); }
    if (msg) { wizError.textContent = msg; return; }
    if (selected.length === 0) {
      dz.classList.add('is-invalid');
      errBox.textContent = 'Please attach at least one photo or video of the problem.';
      dz.scrollIntoView({ behavior: 'smooth', block: 'center' });
      return;
    }
    window.fxConfirm({
      text: 'Your report for ' + building.value + ', ' + floor.value + ', ' + room.value + ' will be sent to the maintenance team.',
      yes: 'Yes, submit it'
    }).then(function (ok) { if (ok) { form.dataset.confirmed = '1'; form.submit(); } });
  });

  $('browseBtn').addEventListener('click', () => input.click());
  input.addEventListener('change', () => { addFiles(input.files); });

  ['dragenter', 'dragover'].forEach(ev => dz.addEventListener(ev, e => { e.preventDefault(); dz.classList.add('dragover'); }));
  ['dragleave', 'drop'].forEach(ev => dz.addEventListener(ev, e => { e.preventDefault(); dz.classList.remove('dragover'); }));
  dz.addEventListener('drop', e => addFiles(e.dataTransfer.files));

  function addFiles(fileList) {
    const problems = [];
    Array.from(fileList).forEach(f => {
      if (!/^(image|video)\//.test(f.type)) { problems.push('"' + f.name + '" is not an image or video.'); return; }
      if (f.size > MAX)                     { problems.push('"' + f.name + '" is larger than 50MB.'); return; }
      if (!selected.some(s => s.name === f.name && s.size === f.size)) selected.push(f);
    });
    errBox.innerHTML = problems.map(p => p.replace(/</g, '&lt;')).join('<br>');
    if (selected.length) dz.classList.remove('is-invalid');
    sync();
  }

  // Keep the real <input> in sync so the files are submitted with the form
  function sync() {
    const dt = new DataTransfer();
    selected.forEach(f => dt.items.add(f));
    input.files = dt.files;
    render();
  }

  function render() {
    list.innerHTML = '';
    selected.forEach((f, i) => {
      const li = document.createElement('li');
      li.className = 'list-group-item d-flex align-items-center gap-3';

      let thumb;
      if (f.type.startsWith('image/')) {
        thumb = document.createElement('img');
        thumb.src = URL.createObjectURL(f);
        thumb.className = 'file-thumb';
      } else {
        thumb = document.createElement('i');
        thumb.className = 'bi bi-camera-video fs-3 text-secondary';
      }

      const name = document.createElement('span');
      name.className = 'flex-grow-1 text-truncate';
      name.textContent = f.name + ' (' + (f.size / 1048576).toFixed(1) + ' MB)';

      const rm = document.createElement('button');
      rm.type = 'button';
      rm.className = 'btn-close';
      rm.setAttribute('aria-label', 'Remove');
      rm.addEventListener('click', () => { selected.splice(i, 1); sync(); });

      li.append(thumb, name, rm);
      list.appendChild(li);
    });
  }

  // ---------- Start ----------
  if (!campus[building.value]) { building.value = ''; floor.value = ''; room.value = ''; }
  else if (!roomsOf(building.value, floor.value).includes(room.value)) { room.value = ''; if (!campus[building.value][floor.value]) floor.value = ''; }
  markBuilding();
  show(<?= $errors ? 'maxReachable()' : '1' ?>);
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>