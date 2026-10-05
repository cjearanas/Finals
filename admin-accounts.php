<?php
require_once __DIR__ . '/includes/data.php';
require_admin();

$type = ($_REQUEST['type'] ?? 'user') === 'staff' ? 'staff' : 'user';
$cfg  = $type === 'staff'
    ? ['title' => 'Maintenance Staff', 'roles' => ROLES_STAFF]
    : ['title' => 'User',  'roles' => ROLES_USER];
$selfUrl = 'admin-accounts.php?type=' . $type;
$me      = current_user();

$managed = fn($u) => in_array($u['role'], $cfg['roles'], true);
$strong  = fn($p) => strlen($p) >= 8 && preg_match('/[A-Za-z]/', $p) && preg_match('/\d/', $p);

$errors = [];
$old    = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $all    = users_all();

    if (!csrf_valid($_POST['csrf'] ?? null)) {
        $errors[] = 'Your session expired. Please try again.';

    } elseif ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        foreach ($all as $u) {
            if ($u['id'] === $id && $managed($u) && $u['id'] !== $me['id']) {
                user_update($id, ['active' => !$u['active']]);
                flash_set('success', $u['active'] ? 'Account disabled.' : 'Account enabled.');
                break;
            }
        }
        header('Location: ' . $selfUrl);
        exit;

    } elseif ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $f  = [];
        foreach (['last_name', 'first_name', 'mi', 'email', 'school_id', 'department', 'contact', 'role'] as $k) {
            $f[$k] = trim((string)($_POST[$k] ?? ''));
        }
        $f['mi'] = mb_strtoupper(mb_substr($f['mi'], 0, 1));
        $password = (string)($_POST['password'] ?? '');

        $target = null;
        foreach ($all as $u) {
            if ($u['id'] === $id) {
                $target = $u;
            }
        }
        if ($id && (!$target || !$managed($target))) {
            $errors[] = 'That account was not found.';
        }
        if ($f['last_name'] === '' || mb_strlen($f['last_name']) > 50) {
            $errors[] = 'Please enter a last name (max 50 characters).';
        }
        if ($f['first_name'] === '' || mb_strlen($f['first_name']) > 50) {
            $errors[] = 'Please enter a first name (max 50 characters).';
        }
        if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($f['email']) > 120) {
            $errors[] = 'Please enter a valid email address.';
        }
        if ($f['school_id'] === '' || mb_strlen($f['school_id']) > 30) {
            $errors[] = 'Please enter a ' . ($type === 'staff' ? 'Staff ID' : 'User ID') . ' (max 30 characters).';
        }
        if (!in_array($f['department'], DEPARTMENTS, true)) {
            $errors[] = 'Please select a department.';
        }
        if (!preg_match('/^[0-9xX+\-\s()]{0,20}$/', $f['contact'])) {
            $errors[] = 'The contact number can only contain digits, spaces and + - ( ).';
        }
        if (!in_array($f['role'], $cfg['roles'], true)) {
            $errors[] = 'Please select a valid role.';
        }
        foreach ($all as $u) {
            if ($u['id'] !== $id) {
                if (strcasecmp($u['email'], $f['email']) === 0) {
                    $errors[] = 'Another account already uses that email.';
                }
                if (strcasecmp($u['school_id'], $f['school_id']) === 0) {
                    $errors[] = 'Another account already uses that ID.';
                }
            }
        }
        if (!$id && $password === '') {
            $errors[] = 'Please set a password for the new account.';
        }
        if ($password !== '' && !$strong($password)) {
            $errors[] = 'The password must be at least 8 characters and include a letter and a number.';
        }

        if (!$errors) {
            if ($id) {
                $changes = $f;
                if ($password !== '') {
                    $changes['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
                }
                user_update($id, $changes);
                flash_set('success', 'Account updated.');
            } else {
                user_create($f + ['avatar' => null, 'active' => true,
                                  'password_hash' => password_hash($password, PASSWORD_DEFAULT)]);
                flash_set('success', 'Account created.');
            }
            header('Location: ' . $selfUrl);
            exit;
        }
        $old = $f + ['id' => $id, 'active' => $target['active'] ?? true];
    }
}

$accounts = array_values(array_filter(users_all(), $managed));
usort($accounts, fn($a, $b) => strcasecmp($a['last_name'] . $a['first_name'], $b['last_name'] . $b['first_name']));

$jsAccounts = [];
foreach ($accounts as $a) {
    $jsAccounts[$a['id']] = array_diff_key($a, ['password_hash' => 1, 'avatar' => 1]);
}

$flash      = flash_get();
$pageTitle  = $cfg['title'];
$activePage = $type === 'staff' ? 'acc-staff' : 'acc-user';
require __DIR__ . '/includes/header.php';
?>

<?php if ($flash): ?>
  <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
    <?= e($flash['msg']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
<?php endif; ?>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <ul class="mb-0"><?php foreach (array_unique($errors) as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
  </div>
<?php endif; ?>

<div class="card">
  <div class="card-header bg-dark text-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
    <span class="fw-semibold"><?= e($cfg['title']) ?> <span class="badge text-bg-secondary ms-1"><?= count($accounts) ?></span></span>

    <div class="d-flex gap-2">
      <div class="input-group input-group-sm" style="max-width: 240px;">
        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
        <input type="search" id="acctSearch" class="form-control" placeholder="Search accounts" autocomplete="off">
      </div>
      <button type="button" class="btn btn-primary btn-sm text-nowrap" data-bs-toggle="modal" data-bs-target="#accountModal">
        <i class="bi bi-plus-lg me-1"></i>Add account
      </button>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0" id="acctTable">
      <thead class="table-light">
        <tr>
          <th>ID</th><th>Name</th><th>Email</th><th>Department</th><th>Role</th><th>Status</th><th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($accounts as $a): ?>
          <tr>
            <td class="text-nowrap"><?= e($a['school_id']) ?></td>
            <td><?= e(full_name($a)) ?></td>
            <td><?= e($a['email']) ?></td>
            <td><?= e($a['department']) ?></td>
            <td><?= e($a['role']) ?></td>
            <td>
              <span class="badge text-bg-<?= $a['active'] ? 'success' : 'secondary' ?>"><?= $a['active'] ? 'Active' : 'Disabled' ?></span>
            </td>
            <td class="text-end text-nowrap">
              <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal"
                      data-bs-target="#accountModal" data-id="<?= (int)$a['id'] ?>">
                <i class="bi bi-pencil-square"></i> Edit
              </button>
              <form method="post" action="<?= e($selfUrl) ?>" class="d-inline"
                    onsubmit="return confirm('<?= $a['active'] ? 'Disable' : 'Enable' ?> this account?');">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="type" value="<?= e($type) ?>">
                <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline-<?= $a['active'] ? 'danger' : 'success' ?>">
                  <i class="bi bi-<?= $a['active'] ? 'slash-circle' : 'check-circle' ?>"></i> <?= $a['active'] ? 'Disable' : 'Enable' ?>
                </button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <tr id="acctEmpty" <?= $accounts ? 'hidden' : '' ?>>
          <td colspan="7" class="text-center text-body-secondary py-4">
            <?= $accounts ? 'No accounts match your search.' : 'No accounts yet.' ?>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<!-- ============ Add / Edit modal ============ -->
<div class="modal fade" id="accountModal" tabindex="-1" aria-labelledby="accountModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <form method="post" action="<?= e($selfUrl) ?>" class="modal-content" autocomplete="off">
      <div class="modal-header bg-dark text-white">
        <h5 class="modal-title" id="accountModalTitle">Add account</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="type" value="<?= e($type) ?>">
        <input type="hidden" name="id" id="f-id" value="0">

        <div class="row g-3">
          <div class="col-md-5"><label class="form-label small fw-semibold" for="f-last_name">Last name</label>
            <input class="form-control" id="f-last_name" name="last_name" maxlength="50" required></div>
          <div class="col-md-5"><label class="form-label small fw-semibold" for="f-first_name">First name</label>
            <input class="form-control" id="f-first_name" name="first_name" maxlength="50" required></div>
          <div class="col-md-2"><label class="form-label small fw-semibold" for="f-mi">M.I.</label>
            <input class="form-control" id="f-mi" name="mi" maxlength="1"></div>

          <div class="col-md-6"><label class="form-label small fw-semibold" for="f-email">Email address</label>
            <input type="email" class="form-control" id="f-email" name="email" maxlength="120" required></div>
          <div class="col-md-6"><label class="form-label small fw-semibold" for="f-school_id"><?= $type === 'staff' ? 'Staff ID' : 'User ID' ?></label>
            <input class="form-control" id="f-school_id" name="school_id" maxlength="30" required></div>

          <div class="col-md-4"><label class="form-label small fw-semibold" for="f-department">Department</label>
            <select class="form-select" id="f-department" name="department" required>
              <option value="" disabled selected>Select</option>
              <?php foreach (DEPARTMENTS as $d): ?><option><?= e($d) ?></option><?php endforeach; ?>
            </select></div>
          <div class="col-md-4"><label class="form-label small fw-semibold" for="f-contact">Contact number</label>
            <input class="form-control" id="f-contact" name="contact" maxlength="20"></div>
          <div class="col-md-4"><label class="form-label small fw-semibold" for="f-role">Role</label>
            <select class="form-select" id="f-role" name="role" required>
              <?php foreach ($cfg['roles'] as $r): ?><option><?= e($r) ?></option><?php endforeach; ?>
            </select></div>

          <div class="col-12"><label class="form-label small fw-semibold" for="f-password" id="f-password-label">Password</label>
            <input type="text" class="form-control" id="f-password" name="password" autocomplete="new-password">
            <div class="form-text">At least 8 characters, with a letter and a number. Share it with the person securely.</div></div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Save account</button>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var accounts = <?= json_encode($jsAccounts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_FORCE_OBJECT) ?>;
  var old      = <?= json_encode($old, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  var modalEl  = document.getElementById('accountModal');
  var $ = function (id) { return document.getElementById(id); };
  var fields = ['last_name', 'first_name', 'mi', 'email', 'school_id', 'department', 'contact', 'role'];

  function fill(a) {
    var editing = a && a.id > 0;
    $('accountModalTitle').textContent = editing ? 'Edit account' : 'Add account';
    $('f-id').value = editing ? a.id : 0;
    fields.forEach(function (k) {
      var el = $('f-' + k);
      el.value = a && a[k] !== undefined ? a[k] : (k === 'role' ? el.options[0].value : '');
    });
    $('f-password').value = '';
    $('f-password').required = !editing;
    $('f-password-label').textContent = editing ? 'New password (leave blank to keep the current one)' : 'Password';
  }

  modalEl.addEventListener('show.bs.modal', function (ev) {
    var btn = ev.relatedTarget;
    if (btn && btn.dataset.id) fill(accounts[btn.dataset.id]);
    else if (!btn && old) fill(old);        // reopened after a validation error
    else fill(null);
  });
  if (old) bootstrap.Modal.getOrCreateInstance(modalEl).show();

  // Live search
  var rows = Array.prototype.slice.call(document.querySelectorAll('#acctTable tbody tr:not(#acctEmpty)'));
  $('acctSearch').addEventListener('input', function () {
    var terms = this.value.toLowerCase().split(/\s+/).filter(Boolean), shown = 0;
    rows.forEach(function (r) {
      var txt = r.textContent.toLowerCase();
      var ok = terms.every(function (t) { return txt.indexOf(t) !== -1; });
      r.classList.toggle('d-none', !ok);
      if (ok) shown++;
    });
    $('acctEmpty').hidden = shown !== 0;
  });
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>