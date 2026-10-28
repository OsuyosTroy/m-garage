<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/_form.php';
guard();
$errors = [];
if (is_post()) {
    require_csrf();
    [$d, $errors] = item_validate();
    $qty = parse_int($_POST['stock_qty'] ?? '0', true);
    if ($qty === null) $errors[] = 'Initial stock must be a whole number.';
    if (!$errors) {
        try {
            $id = in_transaction(function (PDO $pdo) use ($d, $qty) {
                $pdo->prepare('INSERT INTO inventory_items (sku,name,category_id,brand,compatibility,description,supplier_id,purchase_cost,selling_price,stock_qty,min_stock,unit) VALUES (?,?,?,?,?,?,?,?,?,0,?,?)')
                    ->execute([$d['sku'], $d['name'], $d['category_id'], $d['brand'] ?: null, $d['compatibility'] ?: null, $d['description'] ?: null, $d['supplier_id'], $d['purchase_cost'], $d['selling_price'], $d['min_stock'], $d['unit']]);
                $id = (int)$pdo->lastInsertId();
                if ($qty > 0) stock_change($pdo, $id, $qty, 'adjustment', 'inventory_item', $id, 'Initial stock', current_user()['id']);
                return $id;
            });
            log_activity('item_created', 'inventory', "Added item {$d['sku']} - {$d['name']}");
            flash('success', 'Item added.');
            redirect('inventory/view.php?id=' . $id);
        } catch (Throwable $ex) { $errors[] = 'Could not save item: ' . (APP_DEBUG ? $ex->getMessage() : 'database error'); }
    }
}
$pageTitle = 'Add Inventory Item';
require __DIR__ . '/../includes/header.php';
?>
<div class="card">
  <?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
  <form method="post"><?= csrf_field() ?>
    <?php item_form($_POST, true); ?>
    <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Save item</button>
    <a class="btn btn-outline" href="index.php">Cancel</a>
  </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
