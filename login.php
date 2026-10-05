<?php
require_once __DIR__ . '/includes/data.php';

if (current_user()) {
    header('Location: dashboard.php');
    exit;
}

// Login type => roles that may use it
$groups = [
    'user'  => ['label' => 'User',              'roles' => ROLES_USER,        'idLabel' => 'User ID'],
    'staff' => ['label' => 'Maintenance Staff', 'roles' => ROLES_STAFF,       'idLabel' => 'Staff ID'],
    'admin' => ['label' => 'Administrator',     'roles' => ['Administrator'], 'idLabel' => ''],
];

$tab   = 'user';
$error = '';
$email = '';
$sid   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tab   = isset($groups[$_POST['tab'] ?? '']) ? $_POST['tab'] : 'user';
    $email = trim($_POST['email'] ?? '');
    $sid   = trim($_POST['school_id'] ?? '');
    $pw    = (string)($_POST['password'] ?? '');
    $g     = $groups[$tab];

    $user = $email !== '' ? user_by_login($email) : null;
    $ok   = $user
        && strcasecmp($user['email'], $email) === 0
        && in_array($user['role'], $g['roles'], true)
        && ($tab === 'admin' || strcasecmp($user['school_id'], $sid) === 0)
        && password_verify($pw, $user['password_hash']);

    if (!csrf_valid($_POST['csrf'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } elseif (!$ok) {
        $error = 'No matching ' . strtolower($g['label']) . ' account. Check your details or contact the administrator.';
    } elseif (!$user['active']) {
        $error = 'This account is disabled. Please contact the administrator.';
    } else {
        login_user($user['id']);
        header('Location: dashboard.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>FixTrack | Sign in</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Poppins', sans-serif; background: linear-gradient(135deg, #0d2b5e, #1c4aa0); min-height: 100vh; }
    .login-card { max-width: 440px; border: 0; border-radius: 20px; }

    /* Segmented role switch */
    .role-switch { display: flex; gap: 4px; padding: 5px; background: #eef1f9; border-radius: 14px; }
    .role-switch button { flex: 1; border: 0; background: transparent; color: #6b7a99; font-weight: 600; font-size: .85rem;
                          padding: .7rem .4rem; border-radius: 10px; transition: background .15s, color .15s; }
    .role-switch button.active { background: #fff; color: #012a5e; box-shadow: 0 2px 6px rgba(1,42,94,.18); }

    /* Error banner */
    .login-error { background: #fbe6e6; color: #7a1010; border-left: 5px solid #a31515; border-radius: 10px; padding: .8rem 1rem; font-size: .92rem; }

    /* Fields */
    .login-label { font-size: .75rem; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: #3d4a67; margin-bottom: .35rem; }
    .login-input { border: 2px solid #d6dfeb; border-radius: 12px; padding: .75rem 1rem; }
    .login-input:focus { border-color: #2a63e8; box-shadow: 0 0 0 .2rem rgba(42,99,232,.15); }
    .pw-wrap { position: relative; }
    .pw-wrap .login-input { padding-right: 3rem; }
    .pw-toggle { position: absolute; top: 50%; right: .75rem; transform: translateY(-50%); border: 0; background: none; color: #6b7a99; }

    .btn-signin { background: linear-gradient(180deg, #2a63e8, #1352c0); color: #fff; font-weight: 600; border: 0;
                  border-radius: 12px; padding: .85rem; box-shadow: 0 6px 14px rgba(19,82,192,.35); }
    .btn-signin:hover { color: #fff; filter: brightness(1.08); }
    .login-foot { color: #98a2b8; font-size: .78rem; }
  </style>
</head>
<body class="d-flex align-items-center justify-content-center p-3">

  <div class="card login-card shadow-lg w-100">
    <div class="card-body p-4 p-md-5">

      <div class="text-center mb-4">
        <img src="Images/fixtrack.png" alt="FIXTRACK Logo" width="64" height="64" style="object-fit:contain">
        <h1 class="h4 fw-semibold mt-2 mb-0">FIXTRACK</h1>
        <div class="small text-body-secondary">Campus Maintenance Request and Management System</div>
      </div>

      <hr class="opacity-10 mb-3">

      <!-- Role switch -->
      <div class="role-switch mb-3" role="tablist">
        <?php foreach ($groups as $key => $g): ?>
          <button type="button" data-tab="<?= e($key) ?>" class="<?= $key === $tab ? 'active' : '' ?>"><?= e($g['label']) ?></button>
        <?php endforeach; ?>
      </div>

      <?php if (isset($_GET['out']) && !$error): ?>
        <div class="alert alert-success py-2 small">You have been logged out.</div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="login-error mb-3"><?= e($error) ?></div>
      <?php endif; ?>

      <form method="post" action="login.php" autocomplete="on">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="tab" id="tabInput" value="<?= e($tab) ?>">

        <div class="mb-3">
          <label for="email" class="login-label d-block">Email</label>
          <input type="email" id="email" name="email" class="form-control login-input" value="<?= e($email) ?>"
                 placeholder="name@pcu-d.edu.ph" required autocomplete="username">
        </div>

        <div class="mb-3" id="idField" <?= $tab === 'admin' ? 'hidden' : '' ?>>
          <label for="school_id" class="login-label d-block" id="idLabel"><?= e($groups[$tab]['idLabel'] ?: 'ID') ?></label>
          <input id="school_id" name="school_id" class="form-control login-input" value="<?= e($sid) ?>"
                 <?= $tab === 'admin' ? '' : 'required' ?>>
        </div>

        <div class="mb-4">
          <label for="password" class="login-label d-block">Password</label>
          <div class="pw-wrap">
            <input type="password" id="password" name="password" class="form-control login-input" required autocomplete="current-password">
            <button type="button" class="pw-toggle" id="togglePw" aria-label="Show or hide password"><i class="bi bi-eye"></i></button>
          </div>
        </div>

        <button type="submit" class="btn btn-signin w-100">Sign in</button>
      </form>

      <p class="login-foot text-center mt-4 mb-0">By signing in you agree to follow the campus rules of the system.</p>
    </div>
  </div>

<script>
(function () {
  var labels = <?= json_encode(array_map(fn($g) => $g['idLabel'], $groups)) ?>;
  var tabInput = document.getElementById('tabInput');
  var idField = document.getElementById('idField');
  var idInput = document.getElementById('school_id');

  document.querySelectorAll('.role-switch button').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var tab = btn.dataset.tab;
      tabInput.value = tab;
      document.querySelectorAll('.role-switch button').forEach(function (b) { b.classList.toggle('active', b === btn); });

      var needsId = tab !== 'admin';
      idField.hidden = !needsId;
      idInput.required = needsId;
      if (needsId) document.getElementById('idLabel').textContent = labels[tab];
      var banner = document.querySelector('.login-error');
      if (banner) banner.remove();
    });
  });

  document.getElementById('togglePw').addEventListener('click', function () {
    var f = document.getElementById('password');
    var show = f.type === 'password';
    f.type = show ? 'text' : 'password';
    this.firstElementChild.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
  });
})();
</script>
</body>
</html>