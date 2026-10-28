<?php
require_once __DIR__ . '/../includes/auth.php';
guard();
$errors = [];
if (is_post()) {
    require_csrf();
    $act = input('action'); $id = parse_int($_POST['id'] ?? '');
    if ($act === 'toggle' && $id) {
        db()->prepare('UPDATE suppliers SET is_active = 1 - is_active WHERE id=?')->execute([$id]);
        log_activity('supplier_toggled', 'inventory', "Toggled supplier #$id"); flash('success', 'Supplier status changed.'); redirect('inventory/suppliers.php');
    }
    $f = ['name' => input('name'), 'contact_person' => input('contact_person'), 'phone' => input('phone'), 'address' => input('address'), 'notes' => input('notes')];
    if ($f['name'] === '' || mb_strlen($f['name']) > 150) $errors[] = 'Supplier name is required.';
    if ($f['phone'] !== '' && !preg_match('/^[0-9+\-\s()]{5,30}$/', $f['phone'])) $errors[] = 'Phone number format is invalid.';
    if (!$errors) {
        $vals = [$f['name'], $f['contact_person'] ?: null, $f['phone'] ?: null, $f['address'] ?: null, $f['notes'] ?: null];
        if ($act === 'update' && $id) { db()->prepare('UPDATE suppliers SET name=?,contact_person=?,phone=?,address=?,notes=? WHERE id=?')->execute([...$vals, $id]); log_activity('supplier_updated', 'inventory', $f['name']); }
        else { db()->prepare('INSERT INTO suppliers (name,contact_person,phone,address,notes) VALUES (?,?,?,?,?)')->execute($vals); log_activity('supplier_created', 'inventory', $f['name']); }
        flash('success', 'Supplier saved.'); redirect('inventory/suppliers.php');
    }
}
$edit = ($eid = parse_int($_GET['edit'] ?? '')) ? fetch_row('SELECT * FROM suppliers WHERE id=?', [$eid]) : null;
$rows = fetch_all('SELECT * FROM suppliers ORDER BY name');
$v = fn($k) => e($_POST[$k] ?? $edit[$k] ?? '');
$pageTitle = 'Suppliers';
require __DIR__ . '/../includes/header.php';
?>
<div class="grid-2">
<div class="card"><h2 class="card-title"><?= $edit ? 'Edit supplier' : 'Add supplier' ?></h2>
  <?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="<?= $edit ? 'update' : 'create' ?>"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
    <label class="field"><span>Supplier / business name *</span><input type="text" name="name" value="<?= $v('name') ?>" required></label>
    <label class="field"><span>Contact person</span><input type="text" name="contact_person" value="<?= $v('contact_person') ?>"></label>
    <label class="field"><span>Phone</span><input type="text" name="phone" value="<?= $v('phone') ?>"></label>
    <label class="field"><span>Address</span><input type="text" name="address" value="<?= $v('address') ?>"></label>
    <label class="field"><span>Notes</span><textarea name="notes" rows="2"><?= $v('notes') ?></textarea></label>
    <button class="btn btn-primary" type="submit">Save</button> <?php if ($edit): ?><a class="btn btn-outline" href="suppliers.php">Cancel</a><?php endif; ?></form></div>
<div class="card"><div class="table-wrap"><table class="table"><thead><tr><th>Supplier</th><th>Contact</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td><?= e($r['name']) ?></td><td><?= e($r['contact_person']) ?><br><small class="muted"><?= e($r['phone']) ?></small></td><td><?= $r['is_active'] ? '<span class="badge b-ok">Active</span>' : '<span class="badge b-out">Inactive</span>' ?></td>
<td class="actions"><a class="btn btn-sm btn-outline" href="?edit=<?= (int)$r['id'] ?>"><i class="fa-solid fa-pen"></i></a>
<form method="post" data-confirm="Change status of this supplier?"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-outline" type="submit"><i class="fa-solid <?= $r['is_active'] ? 'fa-eye-slash' : 'fa-eye' ?>"></i></button></form></td></tr>
<?php endforeach; ?></tbody></table></div></div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
