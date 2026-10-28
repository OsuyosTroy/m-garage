<?php
require_once __DIR__ . '/../includes/auth.php';
guard();
$id = parse_int($_GET['id'] ?? $_POST['id'] ?? '');
$item = $id ? fetch_row('SELECT i.*, c.name AS cat, s.name AS sup FROM inventory_items i JOIN categories c ON c.id=i.category_id LEFT JOIN suppliers s ON s.id=i.supplier_id WHERE i.id=?', [$id]) : null;
if (!$item) { flash('error', 'Item not found.'); redirect('inventory/index.php'); }
$errors = [];
if (is_post()) {
    require_csrf();
    $delta = trim((string)($_POST['delta'] ?? '')); $reason = input('reason');
    if (!preg_match('/^-?\d{1,6}$/', $delta) || (int)$delta === 0) $errors[] = 'Enter a non-zero whole number (use a minus sign to reduce stock).';
    if ($reason === '') $errors[] = 'A reason is required for every stock adjustment.';
    if (!consume_form_token('adjust')) $errors[] = 'This form was already submitted. Please check the movement history.';
    if (!$errors) {
        try {
            in_transaction(fn(PDO $pdo) => stock_change($pdo, $id, (int)$delta, 'adjustment', 'inventory_item', $id, $reason, current_user()['id']));
            log_activity('stock_adjusted', 'inventory', "Adjusted {$item['sku']} by $delta: $reason");
            flash('success', 'Stock adjusted.');
            redirect('inventory/view.php?id=' . $id);
        } catch (Throwable $ex) { $errors[] = $ex->getMessage(); }
    }
}
$moves = fetch_all('SELECT v.*, u.full_name FROM inventory_movements v LEFT JOIN users u ON u.id=v.created_by WHERE v.item_id=? ORDER BY v.id DESC LIMIT 50', [$id]);
$pageTitle = $item['name'];
require __DIR__ . '/../includes/header.php';
?>
<div class="grid-2">
  <div class="card"><h2 class="card-title"><i class="fa-solid fa-box"></i> Item details</h2>
    <dl class="kv"><dt>SKU</dt><dd><?= e($item['sku']) ?></dd><dt>Category</dt><dd><?= e($item['cat']) ?></dd><dt>Brand</dt><dd><?= e($item['brand']) ?></dd>
    <dt>Compatibility</dt><dd><?= e($item['compatibility']) ?></dd><dt>Supplier</dt><dd><?= e($item['sup']) ?></dd>
    <dt>Purchase cost</dt><dd><?= money($item['purchase_cost']) ?></dd><dt>Selling price</dt><dd><?= money($item['selling_price']) ?></dd>
    <dt>Stock</dt><dd><strong><?= (int)$item['stock_qty'] ?> <?= e($item['unit']) ?></strong> <?= stock_badge((int)$item['stock_qty'], (int)$item['min_stock']) ?></dd>
    <dt>Minimum stock</dt><dd><?= (int)$item['min_stock'] ?></dd><dt>Description</dt><dd><?= nl2br(e($item['description'])) ?></dd>
    <dt>Created</dt><dd><?= e($item['created_at']) ?></dd><dt>Last updated</dt><dd><?= e($item['updated_at']) ?></dd></dl>
    <p class="mt"><a class="btn btn-outline" href="edit.php?id=<?= (int)$id ?>"><i class="fa-solid fa-pen"></i> Edit</a> <a class="btn btn-outline" href="index.php">Back</a></p></div>
  <div class="card"><h2 class="card-title"><i class="fa-solid fa-scale-balanced"></i> Adjust stock</h2>
    <?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
    <form method="post"><?= csrf_field() ?><?= form_token_field('adjust') ?><input type="hidden" name="id" value="<?= (int)$id ?>">
      <label class="field"><span>Quantity change (+ add, - remove)</span><input type="number" name="delta" required placeholder="e.g. 10 or -2"></label>
      <label class="field"><span>Reason *</span><input type="text" name="reason" required maxlength="255" placeholder="e.g. Stock count correction, damaged item"></label>
      <button class="btn btn-primary" type="submit">Apply adjustment</button></form></div>
</div>
<div class="card mt"><h2 class="card-title"><i class="fa-solid fa-clock-rotate-left"></i> Movement history (latest 50)</h2>
  <div class="table-wrap"><table class="table"><thead><tr><th>Date</th><th>Type</th><th class="num">Change</th><th class="num">Before</th><th class="num">After</th><th>Reason</th><th>By</th></tr></thead><tbody>
  <?php foreach ($moves as $m): ?><tr><td><?= e($m['created_at']) ?></td><td><?= e(str_replace('_', ' ', $m['movement_type'])) ?></td><td class="num <?= $m['quantity_change'] < 0 ? 'neg' : 'pos' ?>"><?= ($m['quantity_change'] > 0 ? '+' : '') . (int)$m['quantity_change'] ?></td><td class="num"><?= (int)$m['qty_before'] ?></td><td class="num"><?= (int)$m['qty_after'] ?></td><td><?= e($m['reason']) ?></td><td><?= e($m['full_name']) ?></td></tr>
  <?php endforeach; if (!$moves): ?><tr><td colspan="7" class="empty">No movements yet.</td></tr><?php endif; ?></tbody></table></div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
