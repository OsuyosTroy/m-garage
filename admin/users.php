<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
$errors = []; $me = current_user()['id'];
function pw_errors(string $pw): array {
    $e = [];
    if (strlen($pw) < 8) $e[] = 'Password must be at least 8 characters.';
    if (!preg_match('/[A-Za-z]/', $pw) || !preg_match('/\d/', $pw)) $e[] = 'Password must contain letters and numbers.';
    return $e;
}
if (is_post()) {
    require_csrf();
    $act = input('action'); $id = parse_int($_POST['id'] ?? '');
    try {
        if ($act === 'toggle' && $id) {
            $u = fetch_row('SELECT role, username FROM users WHERE id=?', [$id]);
            if (!$u || $u['role'] !== 'cashier') throw new RuntimeException('Only cashier accounts can be activated or deactivated here.');
            db()->prepare('UPDATE users SET is_active = 1 - is_active WHERE id=?')->execute([$id]);
            log_activity('user_toggled', 'users', "Toggled account {$u['username']}"); flash('success', 'Account status changed.'); redirect('admin/users.php');
        }
        $name = input('full_name'); $pw = (string)($_POST['password'] ?? '');
        if ($name === '' || mb_strlen($name) > 120) $errors[] = 'Full name is required.';
        if ($act === 'create') {
            $un = strtolower(input('username'));
            if (!preg_match('/^[a-z0-9_.]{3,30}$/', $un)) $errors[] = 'Username: 3-30 characters (letters, numbers, . _).';
            $errors = array_merge($errors, pw_errors($pw));
            if (!$errors) {
                db()->prepare("INSERT INTO users (username,password_hash,full_name,role,must_change_password) VALUES (?,?,?,'cashier',1)")->execute([$un, password_hash($pw, PASSWORD_DEFAULT), $name]);
                log_activity('user_created', 'users', "Created cashier account $un"); flash('success', "Cashier account '$un' created. They must change the password on first login."); redirect('admin/users.php');
            }
        } elseif ($act === 'update' && $id) {
            $u = fetch_row('SELECT role FROM users WHERE id=?', [$id]);
            if (!$u || ($u['role'] !== 'cashier' && $id !== $me)) throw new RuntimeException('You can only edit cashier accounts and your own profile.');
            if ($pw !== '') $errors = array_merge($errors, pw_errors($pw));
            if (!$errors) {
                db()->prepare('UPDATE users SET full_name=? WHERE id=?')->execute([$name, $id]);
                if ($pw !== '') { db()->prepare('UPDATE users SET password_hash=?, must_change_password=? WHERE id=?')->execute([password_hash($pw, PASSWORD_DEFAULT), $id === $me ? 0 : 1, $id]); log_activity('password_reset', 'users', "Reset password for user #$id"); }
                log_activity('user_updated', 'users', "Updated user #$id"); flash('success', 'Account updated.'); redirect('admin/users.php');
            }
        }
    } catch (PDOException $ex) { $errors[] = $ex->getCode() === '23000' ? 'That username already exists.' : 'Database error.'; }
    catch (RuntimeException $ex) { $errors[] = $ex->getMessage(); }
}
$edit = ($eid = parse_int($_GET['edit'] ?? '')) ? fetch_row('SELECT * FROM users WHERE id=?', [$eid]) : null;
$rows = fetch_all('SELECT * FROM users ORDER BY role, username');
$pageTitle = 'User Management';
require __DIR__ . '/../includes/header.php';
?>
<div class="grid-2">
<div class="card"><h2 class="card-title"><?= $edit ? 'Edit account: ' . e($edit['username']) : 'Create cashier account' ?></h2>
<?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
<form method="post" autocomplete="off"><?= csrf_field() ?><input type="hidden" name="action" value="<?= $edit ? 'update' : 'create' ?>"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
<?php if (!$edit): ?><label class="field"><span>Username *</span><input type="text" name="username" value="<?= old('username') ?>" required></label><?php endif; ?>
<label class="field"><span>Full name *</span><input type="text" name="full_name" value="<?= e($_POST['full_name'] ?? $edit['full_name'] ?? '') ?>" required></label>
<label class="field"><span><?= $edit ? 'New password (leave blank to keep)' : 'Temporary password *' ?></span><input type="password" name="password" <?= $edit ? '' : 'required' ?> autocomplete="new-password"></label>
<button class="btn btn-primary" type="submit">Save</button> <?php if ($edit): ?><a class="btn btn-outline" href="users.php">Cancel</a><?php endif; ?></form>
<p class="hint mt">New and reset accounts must change their password at next login. Only the Admin and Cashier roles exist.</p></div>
<div class="card"><div class="table-wrap"><table class="table"><thead><tr><th>User</th><th>Role</th><th>Last login</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $u): ?><tr><td><?= e($u['full_name']) ?><br><small class="muted"><?= e($u['username']) ?><?= $u['is_demo'] ? ' (demo)' : '' ?></small></td><td><?= e(ucfirst($u['role'])) ?></td><td><?= e($u['last_login'] ?: '-') ?></td><td><?= $u['is_active'] ? '<span class="badge b-ok">Active</span>' : '<span class="badge b-out">Inactive</span>' ?></td>
<td class="actions"><?php if ($u['role'] === 'cashier' || (int)$u['id'] === $me): ?><a class="btn btn-sm btn-outline" href="?edit=<?= (int)$u['id'] ?>"><i class="fa-solid fa-pen"></i></a><?php endif; ?>
<?php if ($u['role'] === 'cashier'): ?><form method="post" data-confirm="Change status of this account?"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><button class="btn btn-sm btn-outline" type="submit"><i class="fa-solid fa-user-slash"></i></button></form><?php endif; ?></td></tr>
<?php endforeach; ?></tbody></table></div></div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
