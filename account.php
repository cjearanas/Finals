<?php
require_once __DIR__ . '/includes/data.php';

$errors = [];
$tab    = ($_GET['tab'] ?? '') === 'password' ? 'password' : 'profile';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        $errors[] = 'The picture was too large for the server.';
    } elseif (!csrf_valid($_POST['csrf'] ?? null)) {
        $errors[] = 'Your session expired. Please try again.';
    } elseif ($action === 'avatar') {
        $file = save_avatar($_FILES['avatar'] ?? [], $errors);
        if ($file) {
            delete_avatar_file($_SESSION['user']['avatar']);
            $_SESSION['user']['avatar'] = $file;
            flash_set('success', 'Profile picture updated.');
            header('Location: account.php');
            exit;
        }
    } elseif ($action === 'avatar_remove') {
        delete_avatar_file($_SESSION['user']['avatar']);
        $_SESSION['user']['avatar'] = null;
        flash_set('success', 'Profile picture removed.');
        header('Location: account.php');
        exit;
    } elseif ($action === 'password') {
        $tab = 'password';
        $cur = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $con = $_POST['confirm_password'] ?? '';

        if (!password_verify($cur, $_SESSION['user']['password_hash'])) {
            $errors[] = 'Your current password is incorrect.';
        }
        if (strlen($new) < 8 || !preg_match('/[A-Za-z]/', $new) || !preg_match('/\d/', $new)) {
            $errors[] = 'The new password must be at least 8 characters and include a letter and a number.';
        }
        if ($new !== $con) {
            $errors[] = 'The new password and its confirmation do not match.';
        }
        if (!$errors && $cur === $new) {
            $errors[] = 'The new password must be different from the current one.';
        }
        if (!$errors) {
            $_SESSION['user']['password_hash'] = password_hash($new, PASSWORD_DEFAULT);
            flash_set('success', 'Your password was changed.');
            header('Location: account.php?tab=password');
            exit;
        }
    }
}

$u     = $_SESSION['user'];
$flash = flash_get();

$pageTitle  = 'Account';
$activePage = 'account';

$extraHead = <<<'HTML'
<style>
  .acct-wrap { max-width: 780px; margin: 0 auto; }
  .acct-hero { background: linear-gradient(135deg, #0d2b5e 0%, #1c4aa0 100%); color: #fff; border-radius: 16px;
               box-shadow: 0 8px 24px rgba(13,43,94,.35); overflow: hidden; font-family: 'Poppins', sans-serif; }
  .acct-avatar { width: 88px; height: 88px; border-radius: 50%; background: rgba(255,255,255,.15);
                 border: 3px solid rgba(255,255,255,.35); display: flex; align-items: center; justify-content: center;
                 font-size: 1.7rem; font-weight: 600; overflow: hidden; }
  .acct-avatar img { width: 100%; height: 100%; object-fit: cover; }
  .acct-pencil { position: absolute; right: -2px; bottom: 0; width: 30px; height: 30px; border-radius: 50%;
                 background: #3b9df8; color: #fff; display: flex; align-items: center; justify-content: center;
                 cursor: pointer; border: 2px solid #1c4aa0; font-size: .8rem; }
  .acct-pencil:hover { background: #2384e6; }
  .acct-name { font-weight: 600; font-size: 1.45rem; margin: 0; }
  .acct-chip { background: rgba(255,255,255,.18); border-radius: 999px; padding: .1rem .75rem; font-size: .8rem; font-weight: 600; }
  .acct-stats { border-top: 1px solid rgba(255,255,255,.15); }
  .acct-stats > div + div { border-left: 1px solid rgba(255,255,255,.15); }
  .acct-stats .num { font-size: 1.5rem; font-weight: 600; }
  .acct-stats .lbl { font-size: .8rem; color: rgba(255,255,255,.65); }
  .acct-card { border: 0; border-radius: 16px; box-shadow: 0 2px 10px rgba(0,0,0,.08); overflow: hidden; }
  .acct-tabs .nav-link { border: 0; border-radius: 0; color: #6b7280; font-weight: 500; padding: .9rem; border-bottom: 3px solid transparent; }
  .acct-tabs .nav-link.active { background: #eef4ff; color: #0d2b5e; border-bottom-color: #0d2b5e; }
  .acct-label { font-size: .72rem; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: #9aa1ad; }
  .btn-navy { background: linear-gradient(90deg, #1c4aa0, #0d2b5e); color: #fff; font-weight: 600; border: 0; border-radius: 10px; padding: .6rem 1.75rem; }
  .btn-navy:hover { color: #fff; filter: brightness(1.1); }
</style>
HTML;

require __DIR__ . '/includes/header.php';
$initials = $currentUser['initials'];
?>

<div class="acct-wrap">

  <?php if ($flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
      <?= e($flash['msg']) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <?php if ($errors): ?>
    <div class="alert alert-danger">
      <ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>
  <div id="clientErr" class="alert alert-danger" hidden></div>

  <!-- ============ Profile header ============ -->
  <div class="acct-hero mb-3">
    <div class="p-4 d-flex flex-wrap align-items-center gap-3">

      <div class="text-center">
        <form method="post" enctype="multipart/form-data" id="avatarForm" class="position-relative d-inline-block">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="avatar">
          <div class="acct-avatar">
            <?php if ($u['avatar']): ?>
              <img src="<?= e(avatar_url($u['avatar'])) ?>" alt="Profile picture">
            <?php else: ?>
              <?= e($initials) ?>
            <?php endif; ?>
          </div>
          <label for="avatarInput" class="acct-pencil" title="Change picture"><i class="bi bi-pencil-fill"></i></label>
          <input type="file" id="avatarInput" name="avatar" accept="image/jpeg,image/png,image/webp,image/gif" hidden>
        </form>

        <?php if ($u['avatar']): ?>
          <form method="post" class="mt-1">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="avatar_remove">
            <button type="submit" class="btn btn-link btn-sm text-white-50 p-0" style="font-size:.75rem"
                    onclick="return confirm('Remove your profile picture?');">Remove photo</button>
          </form>
        <?php endif; ?>
      </div>

      <div class="flex-grow-1">
        <h2 class="acct-name"><?= e($currentUser['name']) ?></h2>
        <div class="small text-white-50 mb-2"><?= e($u['email']) ?></div>
        <span class="acct-chip"><?= e($u['role']) ?></span>
        <span class="small text-white-50 ms-2"><?= e($u['school_id']) ?></span>
      </div>

      <div class="text-md-end">
        <div class="small text-white-50">Department</div>
        <div class="fw-semibold"><?= e($u['department']) ?></div>
      </div>
    </div>

    <div class="acct-stats row g-0 text-center">
      <div class="col py-3"><div class="num"><?= count_reports() ?></div><div class="lbl">Reports Filed</div></div>
      <div class="col py-3"><div class="num"><?= count_reports('In Progress') ?></div><div class="lbl">In Progress</div></div>
      <div class="col py-3"><div class="num"><?= count_reports('Resolved') ?></div><div class="lbl">Resolved</div></div>
    </div>
  </div>

  <!-- ============ Tabs ============ -->
  <div class="card acct-card">

    <ul class="nav nav-fill acct-tabs" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link <?= $tab === 'profile' ? 'active' : '' ?>" data-bs-toggle="tab"
                data-bs-target="#tab-profile" type="button" role="tab">
          <i class="bi bi-person-fill me-1"></i> Profile
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link <?= $tab === 'password' ? 'active' : '' ?>" data-bs-toggle="tab"
                data-bs-target="#tab-password" type="button" role="tab">
          <i class="bi bi-lock-fill me-1"></i> Password
        </button>
      </li>
    </ul>

    <div class="tab-content p-4">

      <!-- Profile: read-only -->
      <div class="tab-pane fade <?= $tab === 'profile' ? 'show active' : '' ?>" id="tab-profile" role="tabpanel">

        <div class="alert alert-light border d-flex align-items-start gap-2 small">
          <i class="bi bi-lock-fill mt-1"></i>
          <div>These details are set by the school and can't be edited here. To correct them, contact the administrator.
          You can still change your <strong>profile picture</strong> and <strong>password</strong>.</div>
        </div>

        <fieldset disabled>
          <div class="row g-3 mb-3">
            <div class="col-md-5">
              <div class="acct-label mb-1">Last name</div>
              <input class="form-control" value="<?= e($u['last_name']) ?>">
            </div>
            <div class="col-md-5">
              <div class="acct-label mb-1">First name</div>
              <input class="form-control" value="<?= e($u['first_name']) ?>">
            </div>
            <div class="col-md-2">
              <div class="acct-label mb-1">M.I.</div>
              <input class="form-control" value="<?= e($u['mi']) ?>">
            </div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <div class="acct-label mb-1">Email address</div>
              <input class="form-control" value="<?= e($u['email']) ?>">
            </div>
            <div class="col-md-6">
              <div class="acct-label mb-1">Student / Staff ID</div>
              <input class="form-control" value="<?= e($u['school_id']) ?>">
            </div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <div class="acct-label mb-1">Department</div>
              <input class="form-control" value="<?= e($u['department']) ?>">
            </div>
            <div class="col-md-6">
              <div class="acct-label mb-1">Contact number</div>
              <input class="form-control" value="<?= e($u['contact']) ?>">
            </div>
          </div>

          <div class="acct-label mb-2">Role</div>
          <div class="d-flex gap-4">
            <?php foreach (['Student', 'Faculty', 'Staff'] as $role): ?>
              <div class="form-check">
                <input class="form-check-input" type="radio" <?= $u['role'] === $role ? 'checked' : '' ?>>
                <label class="form-check-label"><?= e($role) ?></label>
              </div>
            <?php endforeach; ?>
          </div>
        </fieldset>
      </div>

      <!-- Password -->
      <div class="tab-pane fade <?= $tab === 'password' ? 'show active' : '' ?>" id="tab-password" role="tabpanel">
        <form method="post" action="account.php" style="max-width:460px" autocomplete="off">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="password">

          <?php foreach ([
              'current_password' => 'Current password',
              'new_password'     => 'New password',
              'confirm_password' => 'Confirm new password',
          ] as $name => $label): ?>
            <div class="mb-3">
              <label for="<?= $name ?>" class="acct-label mb-1"><?= e($label) ?></label>
              <div class="input-group">
                <input type="password" id="<?= $name ?>" name="<?= $name ?>" class="form-control" required
                       autocomplete="<?= $name === 'current_password' ? 'current-password' : 'new-password' ?>">
                <button type="button" class="btn btn-outline-secondary toggle-pw" data-target="<?= $name ?>"
                        aria-label="Show or hide password"><i class="bi bi-eye"></i></button>
              </div>
            </div>
          <?php endforeach; ?>

          <div class="form-text mb-3">At least 8 characters, with a letter and a number.</div>
          <button type="submit" class="btn btn-navy">Change password</button>
        </form>
      </div>

    </div>
  </div>
</div>

<script>
(function () {
  // Profile picture: check the file, then upload automatically
  var input = document.getElementById('avatarInput');
  var err = document.getElementById('clientErr');
  input.addEventListener('change', function () {
    var f = input.files[0];
    if (!f) return;
    var okType = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'].indexOf(f.type) !== -1;
    if (!okType || f.size > 5 * 1024 * 1024) {
      err.textContent = !okType ? 'Only JPG, PNG, WebP or GIF pictures are allowed.' : 'The picture is larger than 5MB.';
      err.hidden = false;
      input.value = '';
      window.scrollTo({ top: 0, behavior: 'smooth' });
      return;
    }
    err.hidden = true;
    document.getElementById('avatarForm').submit();
  });

  // Show / hide password
  document.querySelectorAll('.toggle-pw').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var field = document.getElementById(btn.dataset.target);
      var show = field.type === 'password';
      field.type = show ? 'text' : 'password';
      btn.firstElementChild.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
    });
  });
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
