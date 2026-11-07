<?php
require_once __DIR__ . '/_start.php';
require_once __DIR__ . '/_layout.php';
$error = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    check_csrf();
    $admin = one($pdo,'SELECT * FROM users WHERE id=? AND role=?',[$_SESSION['admin_id'],'admin']);
    $old = $_POST['old_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    if (!is_string($old) || !is_string($new)) {
        $old = '';
        $new = '';
    }
    if (!$admin || !password_verify($old,$admin['password_hash'])) {
        $error = 'Current password is incorrect.';
    } elseif (strlen($new) < 10 || strlen($new) > 72 || strpos($new, "\0") !== false) {
        $error = 'New password must have 10 to 72 valid characters.';
    } else {
        $newHash = password_hash($new, PASSWORD_DEFAULT);
        execute_sql($pdo, 'UPDATE users SET password_hash=? WHERE id=?', [$newHash, $_SESSION['admin_id']]);
        $_SESSION['admin_password_hash'] = $newHash;
        session_regenerate_id(true);
        flash('Your password was updated.');
        go('account.php');
    }
}
admin_header('My account','account.php');
?>
<div class="admin-card">
  <h2>Change admin password</h2>
  <?php if ($error) echo shown_error($error); ?>
  <form method="post" class="form-stack narrow-form">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <div class="field">
      <label>Current password</label>
      <input type="password" name="old_password" required>
    </div>
    <div class="field">
      <label>New password (10 to 72 characters)</label>
      <input type="password" name="new_password" minlength="10" maxlength="72" required>
    </div>
    <button class="button primary" type="submit">Change password</button>
  </form>
</div>
<?php admin_footer(); ?>
