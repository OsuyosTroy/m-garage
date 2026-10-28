<?php
require_once __DIR__ . '/../includes/auth.php';
guard();
$item = parse_int($_GET['item'] ?? ''); $type = input('type'); $from = input('from'); $to = input('to');
$w = []; $p = [];
if ($item) { $w[] = 'v.item_id=?'; $p[] = $item; }
if (in_array($type, ['receiving', 'sale', 'repair_use', 'adjustment', 'reversal'], true)) { $w[] = 'v.movement_type=?'; $p[] = $type; }
if (valid_date($from)) { $w[] = 'DATE(v.created_at)>=?'; $p[] = $from; }
if (valid_date($to)) { $w[] = 'DATE(v.created_at)<=?'; $p[] = $to; }
$rows = fetch_all('SELECT v.*, i.name, i.sku, u.full_name FROM inventory_movements v JOIN inventory_items i ON i.id=v.item_id LEFT JOIN users u ON u.id=v.created_by' . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . ' ORDER BY v.id DESC LIMIT 300', $p);
$pageTitle = 'Inventory Movement History';
require __DIR__ . '/../includes/header.php';
?>
<form class="toolbar" method="get">
  <select name="item"><option value="">All items</option><?php foreach (fetch_all('SELECT id,name FROM inventory_items ORDER BY name') as $i): ?><option value="<?= (int)$i['id'] ?>" <?= $item === (int)$i['id'] ? 'selected' : '' ?>><?= e($i['name']) ?></option><?php endforeach; ?></select>
  <select name="type"><option value="">All types</option><?php foreach (['receiving', 'sale', 'repair_use', 'adjustment', 'reversal'] as $t): ?><option value="<?= $t ?>" <?= $type === $t ? 'selected' : '' ?>><?= e(str_replace('_', ' ', $t)) ?></option><?php endforeach; ?></select>
  <input type="date" name="from" value="<?= e($from) ?>"><input type="date" name="to" value="<?= e($to) ?>">
  <button class="btn btn-outline" type="submit">Filter</button> <a class="btn btn-outline" href="index.php">Back to inventory</a>
</form>
<div class="card"><div class="table-wrap"><table class="table"><thead><tr><th>Date</th><th>Item</th><th>Type</th><th class="num">Change</th><th class="num">After</th><th>Reason</th><th>By</th></tr></thead><tbody>
<?php foreach ($rows as $m): ?><tr><td><?= e($m['created_at']) ?></td><td><?= e($m['name']) ?> <small>(<?= e($m['sku']) ?>)</small></td><td><?= e(str_replace('_', ' ', $m['movement_type'])) ?></td><td class="num <?= $m['quantity_change'] < 0 ? 'neg' : 'pos' ?>"><?= ($m['quantity_change'] > 0 ? '+' : '') . (int)$m['quantity_change'] ?></td><td class="num"><?= (int)$m['qty_after'] ?></td><td><?= e($m['reason']) ?></td><td><?= e($m['full_name']) ?></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="7" class="empty">No movements found.</td></tr><?php endif; ?></tbody></table></div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
