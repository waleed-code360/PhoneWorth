<?php
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/db.php';
if (is_admin($pdo)) {
    go('dashboard.php');
}
$error = '';
$failed = (int) ($_SESSION['failed_logins'] ?? 0);
$lockedUntil = (int) ($_SESSION['login_locked_until'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    if ($lockedUntil > time()) {
        $error = 'Too many attempts. Try again in a few minutes.';
    } else {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        if (!is_string($email) || !is_string($password)) {
            $email = '';
            $password = '';
        }
        $email = strtolower(trim($email));
        $admin = filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($email) <= 150
            ? one($pdo, "SELECT * FROM users WHERE email=? AND role='admin' AND is_active=1", [$email])
            : null;
        if ($admin && password_verify($password, $admin['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int) $admin['id'];
            $_SESSION['admin_name'] = $admin['name'];
            $_SESSION['admin_password_hash'] = $admin['password_hash'];
            unset($_SESSION['failed_logins'], $_SESSION['login_locked_until']);
            go('dashboard.php');
        }
        $_SESSION['failed_logins'] = $failed + 1;
        if ($_SESSION['failed_logins'] >= 5) {
            $_SESSION['login_locked_until'] = time() + 300;
            $_SESSION['failed_logins'] = 0;
            $error = 'Too many attempts. Try again in 5 minutes.';
        } else {
            $error = 'Incorrect admin email or password.';
        }
    }
}
site_header('Admin login', true);
?>
<main class="container content-page narrow">
  <div class="panel">
    <span class="eyebrow dark">ADMIN ACCESS</span>
    <h1>Welcome back</h1>
    <p>Sign in to manage the phone catalog and valuation data.</p>
    <?php show_flash(); if ($error) echo shown_error($error); ?>
    <form method="post" class="form-stack">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <div class="field">
        <label for="email">Email</label>
        <input id="email" type="email" name="email" required>
      </div>
      <div class="field">
        <label for="password">Password</label>
        <input id="password" type="password" name="password" required>
      </div>
      <button class="button primary" type="submit">Sign in</button>
    </form>
    <p class="subtle">
      Admin accounts are created by the project owner from the private Manage Admins page, not through
      public registration.
    </p>
  </div>
</main>
<?php site_footer(); ?>
