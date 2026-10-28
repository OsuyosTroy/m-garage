<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$errors = [];
if (is_post()) {
    require_csrf();
    $cur = (string)($_POST['current_password'] ?? '');
    $new = (string)($_POST['new_password'] ?? '');
    $con = (string)($_POST['confirm_password'] ?? '');
    $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
    $stmt->execute([current_user()['id']]);
    $hash = (string)$stmt->fetchColumn();
    if (!password_verify($cur, $hash)) $errors[] = 'Current password is incorrect.';
    if (strlen($new) < 8) $errors[] = 'New password must be at least 8 characters.';
    if (!preg_match('/[A-Za-z]/', $new) || !preg_match('/\d/', $new)) $errors[] = 'New password must contain letters and numbers.';
    if ($new !== $con) $errors[] = 'New password and confirmation do not match.';
    if ($new === $cur) $errors[] = 'New password must be different from the current one.';
    if (!$errors) {
        db()->prepare('UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ?')
            ->execute([password_hash($new, PASSWORD_DEFAULT), current_user()['id']]);
        log_activity('password_changed', 'auth', 'User changed own password');
        flash('success', 'Password updated successfully.');
        redirect('dashboard.php');
    }
}
$pageTitle = 'Change Password';
require __DIR__ . '/includes/header.php';
?>
<div class="card narrow">
  <h2 class="card-title"><i class="fa-solid fa-key"></i> Change Password</h2>
  <?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
  <form method="post"><?= csrf_field() ?>
    <label class="field"><span>Current password</span><input type="password" name="current_password" required></label>
    <label class="field"><span>New password</span><input type="password" name="new_password" required minlength="8"></label>
    <label class="field"><span>Confirm new password</span><input type="password" name="confirm_password" required minlength="8"></label>
    <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Save password</button>
  </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
