<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/_form.php';
guard();
$id = parse_int($_GET['id'] ?? $_POST['id'] ?? '');
$item = $id ? fetch_row('SELECT * FROM inventory_items WHERE id=?', [$id]) : null;
if (!$item) { flash('error', 'Item not found.'); redirect('inventory/index.php'); }
$errors = [];
if (is_post()) {
    require_csrf();
    [$d, $errors] = item_validate($id);
    if (!$errors) {
        try {
            db()->prepare('UPDATE inventory_items SET sku=?,name=?,category_id=?,brand=?,compatibility=?,description=?,supplier_id=?,purchase_cost=?,selling_price=?,min_stock=?,unit=? WHERE id=?')
                ->execute([$d['sku'], $d['name'], $d['category_id'], $d['brand'] ?: null, $d['compatibility'] ?: null, $d['description'] ?: null, $d['supplier_id'], $d['purchase_cost'], $d['selling_price'], $d['min_stock'], $d['unit'], $id]);
            log_activity('item_updated', 'inventory', "Updated item {$d['sku']}");
            flash('success', 'Item updated.');
            redirect('inventory/view.php?id=' . $id);
        } catch (Throwable $ex) { $errors[] = 'Could not update item: ' . (APP_DEBUG ? $ex->getMessage() : 'database error'); }
    }
}
$v = is_post() ? $_POST : $item;
$pageTitle = 'Edit Item: ' . $item['sku'];
require __DIR__ . '/../includes/header.php';
?>
<div class="card">
  <p class="hint">Stock quantity cannot be edited here. Use "Adjust stock" on the item page so every change is recorded.</p>
  <?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
    <?php item_form($v, false); ?>
    <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Update item</button>
    <a class="btn btn-outline" href="view.php?id=<?= (int)$id ?>">Cancel</a>
  </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
