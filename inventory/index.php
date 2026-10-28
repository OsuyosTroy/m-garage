<?php
require_once __DIR__ . '/../includes/auth.php';
guard();
if (is_post()) {
    require_csrf();
    $id = parse_int($_POST['id'] ?? '');
    if ($id && ($_POST['action'] ?? '') === 'toggle') {
        db()->prepare('UPDATE inventory_items SET is_active = 1 - is_active WHERE id=?')->execute([$id]);
        log_activity('item_toggled', 'inventory', "Toggled active status of item #$id");
        flash('success', 'Item status changed.');
    }
    redirect('inventory/index.php?' . http_build_query(['q' => input('q'), 'category' => input('category'), 'status' => input('status')]));
}
$q = input('q'); $cat = parse_int($_GET['category'] ?? ''); $st = input('status');
$w = []; $p = [];
if ($q !== '') { $w[] = '(i.name LIKE ? OR i.sku LIKE ? OR i.brand LIKE ? OR c.name LIKE ?)'; array_push($p, "%$q%", "%$q%", "%$q%", "%$q%"); }
if ($cat) { $w[] = 'i.category_id = ?'; $p[] = $cat; }
if ($st === 'low') $w[] = 'i.is_active=1 AND i.stock_qty>0 AND i.stock_qty<=i.min_stock';
elseif ($st === 'out') $w[] = 'i.is_active=1 AND i.stock_qty=0';
elseif ($st === 'inactive') $w[] = 'i.is_active=0';
elseif ($st !== 'all') $w[] = 'i.is_active=1';
$items = fetch_all('SELECT i.*, c.name AS cat FROM inventory_items i JOIN categories c ON c.id=i.category_id' . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . ' ORDER BY i.name LIMIT 500', $p);
$value = (float)db()->query('SELECT COALESCE(SUM(purchase_cost*stock_qty),0) FROM inventory_items WHERE is_active=1')->fetchColumn();
$pageTitle = 'Inventory';
require __DIR__ . '/../includes/header.php';
?>
<form class="toolbar" method="get">
  <div class="grow"><input type="text" name="q" value="<?= e($q) ?>" placeholder="Search part name, SKU, brand or category..."></div>
  <select name="category"><option value="">All categories</option><?php foreach (active_categories() as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $cat === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select>
  <select name="status"><?php foreach (['' => 'Active items', 'low' => 'Low stock', 'out' => 'Out of stock', 'inactive' => 'Inactive', 'all' => 'All'] as $k => $l): ?><option value="<?= e($k) ?>" <?= $st === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
  <button class="btn btn-outline" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Filter</button>
  <a class="btn btn-outline" href="movements.php"><i class="fa-solid fa-clock-rotate-left"></i> Movements</a> <a class="btn btn-primary" href="create.php"><i class="fa-solid fa-plus"></i> Add item</a>
</form>
<div class="card">
  <p class="muted">Total inventory value (purchase cost x stock, active items): <strong><?= money($value) ?></strong> &middot; Sample items are demonstration data.</p>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>SKU</th><th>Part</th><th>Category</th><th>Brand</th><th class="num">Cost</th><th class="num">Price</th><th class="num">Stock</th><th>Status</th><th></th></tr></thead><tbody>
    <?php foreach ($items as $i): ?>
      <tr><td><?= e($i['sku']) ?></td><td><a href="view.php?id=<?= (int)$i['id'] ?>"><?= e($i['name']) ?></a><?= $i['is_demo'] ? ' <span class="badge">demo</span>' : '' ?><?= $i['is_active'] ? '' : ' <span class="badge b-out">inactive</span>' ?></td>
      <td><?= e($i['cat']) ?></td><td><?= e($i['brand']) ?></td><td class="num"><?= money($i['purchase_cost']) ?></td><td class="num"><?= money($i['selling_price']) ?></td>
      <td class="num"><?= (int)$i['stock_qty'] ?> <?= e($i['unit']) ?></td><td><?= stock_badge((int)$i['stock_qty'], (int)$i['min_stock']) ?></td>
      <td class="actions"><a class="btn btn-sm btn-outline" href="edit.php?id=<?= (int)$i['id'] ?>" title="Edit"><i class="fa-solid fa-pen"></i></a>
        <form method="post" data-confirm="<?= $i['is_active'] ? 'Deactivate' : 'Activate' ?> this item?"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$i['id'] ?>"><input type="hidden" name="q" value="<?= e($q) ?>"><input type="hidden" name="status" value="<?= e($st) ?>">
        <button class="btn btn-sm btn-outline" type="submit" title="<?= $i['is_active'] ? 'Deactivate' : 'Activate' ?>"><i class="fa-solid <?= $i['is_active'] ? 'fa-eye-slash' : 'fa-eye' ?>"></i></button></form></td></tr>
    <?php endforeach; if (!$items): ?><tr><td colspan="9" class="empty">No items found.</td></tr><?php endif; ?>
    </tbody></table></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
