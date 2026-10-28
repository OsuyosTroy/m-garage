<?php
require_once __DIR__ . '/../includes/auth.php';
guard();
$errors = [];
if (is_post()) {
    require_csrf();
    $act = input('action'); $id = parse_int($_POST['id'] ?? '');
    try {
        if ($act === 'toggle' && $id) {
            db()->prepare('UPDATE categories SET is_active = 1 - is_active WHERE id=?')->execute([$id]);
            log_activity('category_toggled', 'inventory', "Toggled category #$id"); flash('success', 'Category status changed.'); redirect('inventory/categories.php');
        }
        $name = input('name'); $desc = input('description');
        if ($name === '' || mb_strlen($name) > 100) $errors[] = 'Category name is required (max 100 characters).';
        if (!$errors) {
            if ($act === 'update' && $id) { db()->prepare('UPDATE categories SET name=?, description=? WHERE id=?')->execute([$name, $desc ?: null, $id]); log_activity('category_updated', 'inventory', $name); }
            else { db()->prepare('INSERT INTO categories (name, description) VALUES (?,?)')->execute([$name, $desc ?: null]); log_activity('category_created', 'inventory', $name); }
            flash('success', 'Category saved.'); redirect('inventory/categories.php');
        }
    } catch (PDOException $ex) { $errors[] = $ex->getCode() === '23000' ? 'A category with that name already exists.' : 'Database error: ' . (APP_DEBUG ? $ex->getMessage() : ''); }
}
$edit = ($eid = parse_int($_GET['edit'] ?? '')) ? fetch_row('SELECT * FROM categories WHERE id=?', [$eid]) : null;
$rows = fetch_all('SELECT c.*, (SELECT COUNT(*) FROM inventory_items i WHERE i.category_id=c.id) AS n FROM categories c ORDER BY c.name');
$pageTitle = 'Categories';
require __DIR__ . '/../includes/header.php';
?>
<div class="grid-2">
<div class="card"><h2 class="card-title"><?= $edit ? 'Edit category' : 'Add category' ?></h2>
  <?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="<?= $edit ? 'update' : 'create' ?>"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
    <label class="field"><span>Name *</span><input type="text" name="name" value="<?= e($_POST['name'] ?? $edit['name'] ?? '') ?>" required maxlength="100"></label>
    <label class="field"><span>Description</span><input type="text" name="description" value="<?= e($_POST['description'] ?? $edit['description'] ?? '') ?>" maxlength="255"></label>
    <button class="btn btn-primary" type="submit">Save</button> <?php if ($edit): ?><a class="btn btn-outline" href="categories.php">Cancel</a><?php endif; ?></form></div>
<div class="card"><div class="table-wrap"><table class="table"><thead><tr><th>Name</th><th class="num">Items</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td><?= e($r['name']) ?><br><small class="muted"><?= e($r['description']) ?></small></td><td class="num"><?= (int)$r['n'] ?></td><td><?= $r['is_active'] ? '<span class="badge b-ok">Active</span>' : '<span class="badge b-out">Inactive</span>' ?></td>
<td class="actions"><a class="btn btn-sm btn-outline" href="?edit=<?= (int)$r['id'] ?>"><i class="fa-solid fa-pen"></i></a>
<form method="post" data-confirm="Change status of this category?"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-outline" type="submit"><i class="fa-solid <?= $r['is_active'] ? 'fa-eye-slash' : 'fa-eye' ?>"></i></button></form></td></tr>
<?php endforeach; ?></tbody></table></div></div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
