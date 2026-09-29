<?php
require_once __DIR__ . '/includes/data.php';

$campus = campus_layout();
$items  = concern_items();
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
        if (!isset($items[$old['type']])) {
            $errors[] = 'Please select a concern type.';
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
            $total = add_report($old['type'], $old['item'], $old['building'], $old['floor'], $old['room'],
                                $old['description'], $saved);
            // Post/Redirect/Get
            header('Location: maintenance-log.php?' . ($total > 1 ? 'submitted=merged&n=' . $total : 'submitted=1'));
            exit;
        }
    }
}

$pageTitle  = 'Report an Issue';
$activePage = 'report';

$extraHead = <<<'HTML'
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
<style>
  :root { --navy: #012a5e; }
  .report-card { border: 2px solid var(--navy); border-radius: 18px; font-family: 'Poppins', sans-serif; }
  .report-form { max-width: 640px; margin: 0 auto; }
  .report-form label.field-label { font-weight: 500; font-size: 1.05rem; }
  .report-form .form-select,
  .report-form .form-control { border: 1px solid #adb5bd; border-radius: 6px; box-shadow: 0 2px 4px rgba(0,0,0,.25); }
  .report-form .form-select:disabled { background-color: #f1f3f5; box-shadow: none; }
  .dropzone { border: 1px solid #adb5bd; border-radius: 6px; box-shadow: 0 2px 4px rgba(0,0,0,.25);
              padding: 1.1rem 1.25rem; background: #fff; transition: background .15s, border-color .15s; }
  .dropzone.is-invalid { border-color: #dc3545; box-shadow: 0 0 0 .2rem rgba(220,53,69,.25); }
  .dropzone.dragover { background: #e7f0ff; border-color: var(--navy); }
  .dropzone .dz-text { font-size: 1.1rem; color: #555; line-height: 1.1; }
  .dropzone .dz-hint { font-size: .75rem; color: #444; }
  .btn-browse { border: 1px solid var(--navy); color: var(--navy); font-weight: 600; border-radius: 8px; background: #fff; }
  .btn-browse:hover { background: var(--navy); color: #fff; }
  .btn-navy { background: var(--navy); color: #fff; font-weight: 600; letter-spacing: .03em;
              text-transform: uppercase; border-radius: 8px; padding: .7rem 4rem; }
  .btn-navy:hover { background: #001c40; color: #fff; }
  .file-thumb { width: 40px; height: 40px; object-fit: cover; border-radius: 4px; }
</style>
HTML;

require __DIR__ . '/includes/header.php';
?>

<div class="card report-card">
  <form class="card-body p-4 p-md-5" action="report-issue.php" method="post" enctype="multipart/form-data" novalidate>
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

    <div class="report-form">

      <?php if ($errors): ?>
        <div class="alert alert-danger">
          <ul class="mb-0">
            <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <!-- Dependent dropdowns -->
      <div class="row g-3 align-items-center mb-4">

        <label for="building" class="col-sm-4 field-label">Building Name</label>
        <div class="col-sm-8">
          <select id="building" name="building" class="form-select" required></select>
        </div>

        <label for="floor" class="col-sm-4 field-label">Floor Number</label>
        <div class="col-sm-8">
          <select id="floor" name="floor" class="form-select" required></select>
        </div>

        <label for="room" class="col-sm-4 field-label">Room Number</label>
        <div class="col-sm-8">
          <select id="room" name="room" class="form-select" required></select>
        </div>

        <label for="type" class="col-sm-4 field-label">Concern Type</label>
        <div class="col-sm-8">
          <select id="type" name="type" class="form-select" required></select>
        </div>

        <label for="item" class="col-sm-4 field-label">Item</label>
        <div class="col-sm-8">
          <select id="item" name="item" class="form-select" required></select>
        </div>

      </div>

      <!-- Description -->
      <div class="mb-4">
        <label for="description" class="field-label d-block mb-2">Describe Your Concern</label>
        <textarea id="description" name="description" rows="5" maxlength="1000"
                  class="form-control"><?= e($old['description']) ?></textarea>
      </div>

      <!-- Upload -->
      <div class="mb-4">
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

      <div class="text-center">
        <button type="submit" class="btn btn-navy">Submit Report</button>
      </div>

    </div>
  </form>
</div>

<script>
(function () {
  // ---------- Dependent dropdowns ----------
  const campus = <?= json_encode($campus, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  const items  = <?= json_encode($items,  JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  const old    = <?= json_encode($old,    JSON_HEX_TAG | JSON_HEX_AMP) ?>;

  const $ = id => document.getElementById(id);
  const building = $('building'), floor = $('floor'), room = $('room'), type = $('type'), item = $('item');

  function fill(select, values, placeholder, selected) {
    select.innerHTML = '';
    select.add(new Option(placeholder, '', true, true));
    select.options[0].disabled = true;
    values.forEach(v => select.add(new Option(v, v, false, v === selected)));
    select.disabled = values.length === 0;
  }

  const floorsOf = b => Object.keys(campus[b] || {});
  const roomsOf  = (b, f) => (campus[b] && campus[b][f]) || [];
  const itemsOf  = t => items[t] || [];

  fill(building, Object.keys(campus), 'Select a building', old.building);
  fill(floor, floorsOf(building.value),                 'Select a floor',    old.floor);
  fill(room,  roomsOf(building.value, floor.value),     'Select a room',     old.room);
  fill(type,  Object.keys(items),                       'Select a concern type', old.type);
  fill(item,  itemsOf(type.value),                      'Select an item',    old.item);

  building.addEventListener('change', () => {
    fill(floor, floorsOf(building.value), 'Select a floor');
    fill(room, [], 'Select a room');
  });
  floor.addEventListener('change', () => fill(room, roomsOf(building.value, floor.value), 'Select a room'));
  type.addEventListener('change',  () => fill(item, itemsOf(type.value), 'Select an item'));

  // ---------- File upload (images / videos only, 50MB) ----------
  const MAX = 50 * 1024 * 1024;
  const dz = $('dropzone'), input = $('files'), list = $('fileList'), errBox = $('fileError');
  let selected = [];
  const form = document.querySelector('form.card-body');

  form.addEventListener('submit', function (e) {
    if (selected.length === 0) {
      e.preventDefault();
      dz.classList.add('is-invalid');
      errBox.textContent = 'Please attach at least one photo or video of the problem.';
      dz.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
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
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>