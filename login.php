<?php
require_once __DIR__ . '/includes/data.php';

if (current_user()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pw    = (string)($_POST['password'] ?? '');

    // Find the account by email only. Its role (user, maintenance staff or administrator)
    // decides which dashboard the person gets after logging in.
    $user = null;
    foreach (users_all() as $u) {
        if ($email !== '' && strcasecmp($u['email'], $email) === 0) {
            $user = $u;
            break;
        }
    }

    if (!csrf_valid($_POST['csrf'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } elseif (!$user || !password_verify($pw, $user['password_hash'])) {
        $error = 'Incorrect email or password.';
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

      <?php if (isset($_GET['out']) && !$error): ?>
        <div class="alert alert-success py-2 small">You have been logged out.</div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="login-error mb-3"><?= e($error) ?></div>
      <?php endif; ?>

      <form method="post" action="login.php" autocomplete="on">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <div class="mb-3">
          <label for="email" class="login-label d-block">Email</label>
          <input type="email" id="email" name="email" class="form-control login-input" value="<?= e($email) ?>"
                 placeholder="name@pcu-d.edu.ph" required autocomplete="username">
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
  document.getElementById('togglePw').addEventListener('click', function () {
    var f = document.getElementById('password');
    var show = f.type === 'password';
    f.type = show ? 'text' : 'password';
    this.firstElementChild.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
  });
</script>
</body>
</html>