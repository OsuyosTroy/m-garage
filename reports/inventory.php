<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/_filter.php';
guard();
[$from, $to] = report_range();
$items = fetch_all('SELECT i.*, c.name AS cat FROM inventory_items i JOIN categories c ON c.id=i.category_id WHERE i.is_active=1 ORDER BY c.name, i.name');
$recv = fetch_all('SELECT r.reference_no, r.date_received, r.total_cost, s.name AS sup, u.full_name FROM stock_receivings r JOIN suppliers s ON s.id=r.supplier_id JOIN users u ON u.id=r.received_by WHERE r.date_received BETWEEN ? AND ? ORDER BY r.id DESC', [$from, $to]);
$value = 0; $qty = 0; foreach ($items as $i) { $value += $i['purchase_cost'] * $i['stock_qty']; $qty += $i['stock_qty']; }
$pageTitle = 'Inventory Report';
require __DIR__ . '/../includes/header.php';
report_filter_form($from, $to);
?>
<div class="card"><h2 class="card-title">Inventory balances</h2><p>Total quantity: <strong><?= (int)$qty ?></strong> &middot; Inventory value at purchase cost: <strong><?= money($value) ?></strong></p><div class="table-wrap"><table class="table"><thead><tr><th>Category</th><th>SKU</th><th>Item</th><th class="num">Stock</th><th class="num">Min</th><th class="num">Cost</th><th class="num">Value</th><th>Status</th></tr></thead><tbody>
<?php foreach ($items as $i): ?><tr><td><?= e($i['cat']) ?></td><td><?= e($i['sku']) ?></td><td><?= e($i['name']) ?></td><td class="num"><?= (int)$i['stock_qty'] ?></td><td class="num"><?= (int)$i['min_stock'] ?></td><td class="num"><?= money($i['purchase_cost']) ?></td><td class="num"><?= money($i['purchase_cost'] * $i['stock_qty']) ?></td><td><?= stock_badge((int)$i['stock_qty'], (int)$i['min_stock']) ?></td></tr><?php endforeach; ?></tbody></table></div></div>
<div class="card mt"><h2 class="card-title">Low-stock and out-of-stock items</h2><div class="table-wrap"><table class="table"><thead><tr><th>Item</th><th class="num">Stock</th><th class="num">Minimum</th><th>Status</th></tr></thead><tbody>
<?php $n = 0; foreach ($items as $i) if ($i['stock_qty'] <= $i['min_stock']) { $n++; ?><tr><td><?= e($i['name']) ?></td><td class="num"><?= (int)$i['stock_qty'] ?></td><td class="num"><?= (int)$i['min_stock'] ?></td><td><?= stock_badge((int)$i['stock_qty'], (int)$i['min_stock']) ?></td></tr><?php } if (!$n): ?><tr><td colspan="4" class="empty">All items are above their minimum.</td></tr><?php endif; ?></tbody></table></div></div>
<div class="card mt"><h2 class="card-title">Stock receiving (<?= e($from) ?> to <?= e($to) ?>)</h2><div class="table-wrap"><table class="table"><thead><tr><th>Reference</th><th>Date</th><th>Supplier</th><th>Received by</th><th class="num">Total cost</th></tr></thead><tbody>
<?php foreach ($recv as $r): ?><tr><td><?= e($r['reference_no']) ?></td><td><?= e($r['date_received']) ?></td><td><?= e($r['sup']) ?></td><td><?= e($r['full_name']) ?></td><td class="num"><?= money($r['total_cost']) ?></td></tr><?php endforeach; if (!$recv): ?><tr><td colspan="5" class="empty">No receiving transactions.</td></tr><?php endif; ?></tbody></table></div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
