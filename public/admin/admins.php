<?php
require_once __DIR__ . '/_start.php';
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../../includes/admin_management.php';

// Ordinary admins must not view this page or submit its forms.
require_owner($pdo);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add') {
            add_admin_account(
                $pdo,
                $_POST['name'] ?? '',
                $_POST['email'] ?? '',
                $_POST['password'] ?? '',
                $_POST['confirm_password'] ?? ''
            );
            flash('New admin added successfully.');
        } elseif ($action === 'reset_password') {
            reset_admin_password(
                $pdo,
                $_POST['id'] ?? '',
                $_SESSION['admin_id'],
                $_POST['password'] ?? '',
                $_POST['confirm_password'] ?? ''
            );
            flash('Password reset. The admin must sign in again.');
        } elseif ($action === 'toggle') {
            $status = change_admin_status(
                $pdo,
                $_POST['id'] ?? '',
                $_SESSION['admin_id']
            );
            flash($status === 1 ? 'Admin reactivated.' : 'Admin deactivated.');
        } else {
            throw new Exception('Unknown action.');
        }

        // A redirect prevents the form from being sent again on refresh.
        go('admins.php');
    } catch (PDOException $exception) {
        $error = 'Could not save. Please check the email and try again.';
    } catch (Exception $exception) {
        $error = $exception->getMessage();
    }
}

$admins = rows(
    $pdo,
    "SELECT id, name, email, is_active, created_at
     FROM users WHERE role = 'admin' ORDER BY id"
);

admin_header('Manage admins', 'admins.php');
if ($error) {
    echo shown_error($error);
}
?>

<details class="admin-card form-details" <?= $error ? 'open' : '' ?>>
  <summary>Add a new admin</summary>
  <p>
    Only the project owner can add admins. New admins can manage phones and prices, but cannot add
    other admins. They must sign in to this same website/server.
  </p>

  <form method="post" class="form-stack narrow-form">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="add">

    <div class="field">
      <label for="admin-name">Name</label>
      <input id="admin-name" name="name" maxlength="100" required autocomplete="name">
    </div>

    <div class="field">
      <label for="admin-email">Email</label>
      <input id="admin-email" type="email" name="email" maxlength="150" required autocomplete="off">
    </div>

    <div class="field">
      <label for="admin-password">Password (10 to 72 characters)</label>
      <input
        id="admin-password"
        type="password"
        name="password"
        minlength="10"
        maxlength="72"
        required
        autocomplete="new-password">
    </div>

    <div class="field">
      <label for="admin-confirm">Confirm password</label>
      <input
        id="admin-confirm"
        type="password"
        name="confirm_password"
        minlength="10"
        maxlength="72"
        required
        autocomplete="new-password">
    </div>

    <button class="button primary small" type="submit">Add admin</button>
  </form>
</details>

<div class="admin-card">
  <h2>Existing admins</h2>
  <p>Use Deactivate to remove login access. The account stays in MySQL and can be reactivated later.</p>

  <div class="table-scroll">
    <table class="data-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Name</th>
          <th>Email</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($admins as $admin): ?>
        <?php
                    $isOwnerAccount = strcasecmp(
                        $admin['email'],
                        PHONEWORTH_OWNER_EMAIL
                    ) === 0;
                    $isCurrentAccount = (int) $admin['id'] === (int) $_SESSION['admin_id'];
                  ?>
        <tr>
          <td><?= (int) $admin['id'] ?>
          </td>
          <td><?= e($admin['name']) ?>
          </td>
          <td><?= e($admin['email']) ?>
          </td>
          <td><?= active_label($admin['is_active']) ?>
          </td>
          <td>
            <?php if ($isOwnerAccount): ?>
            <strong>Project owner</strong>
            <?php elseif ($isCurrentAccount): ?>
            <strong>Your account</strong>
            <?php else: ?>
            <form method="post" class="inline-form">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="id" value="<?= (int) $admin['id'] ?>">
              <button class="mini-button" type="submit">
                <?= (int) $admin['is_active'] === 1 ? 'Deactivate' : 'Reactivate' ?>
              </button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<details class="admin-card form-details">
  <summary>Reset another admin's password</summary>
  <p>Use this if an admin forgot their password. This action logs out their old sessions.
    They must log in on THIS server with the new password.</p>
  <form method="post" class="form-stack narrow-form">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="reset_password">
    <div class="field">
      <label for="reset-admin">Select admin</label>
      <select id="reset-admin" name="id" required>
        <option value="">Choose an admin</option>
        <?php foreach ($admins as $admin): ?>
        <?php if (strcasecmp($admin['email'], PHONEWORTH_OWNER_EMAIL) !== 0 &&
                            (int) $admin['id'] !== (int) $_SESSION['admin_id']): ?>
        <option value="<?= (int) $admin['id'] ?>">
          <?= e($admin['name'] . ' (' . $admin['email'] . ')') ?>
        </option>
        <?php endif; ?>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="reset-pass">New password (10-72 characters)</label>
      <input id="reset-pass" name="password" type="password"
        minlength="10" maxlength="72" required autocomplete="new-password">
    </div>
    <div class="field">
      <label for="reset-confirm">Confirm new password</label>
      <input id="reset-confirm" name="confirm_password" type="password"
        minlength="10" maxlength="72" required autocomplete="new-password">
    </div>
    <button class="button primary small" type="submit">Reset password</button>
  </form>
  <p class="subtle">
    Important: your friend must log in to the SAME server and database. His own localhost is a
    different website installation. If login fails on this server, verify the email, active status and
    reset the password here.
  </p>
</details>

<?php admin_footer(); ?>
