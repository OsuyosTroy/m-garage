<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$pdo = db();
$one = fn(string $sql) => $pdo->query($sql)->fetchColumn();

$stats = [
  ['Active Items',      (int)$one("SELECT COUNT(*) FROM inventory_items WHERE is_active=1"), 'fa-boxes-stacked', ''],
  ['Total Stock Qty',   (int)$one("SELECT COALESCE(SUM(stock_qty),0) FROM inventory_items WHERE is_active=1"), 'fa-cubes', ''],
  ['Low-Stock Items',   (int)$one("SELECT COUNT(*) FROM inventory_items WHERE is_active=1 AND stock_qty>0 AND stock_qty<=min_stock"), 'fa-triangle-exclamation', 'warn'],
  ['Out of Stock',      (int)$one("SELECT COUNT(*) FROM inventory_items WHERE is_active=1 AND stock_qty=0"), 'fa-circle-xmark', 'danger'],
  ["Today's Sales",     money($one("SELECT COALESCE(SUM(total),0) FROM sales WHERE status='completed' AND DATE(created_at)=CURDATE()")), 'fa-cash-register', 'ok'],
  ["Today's Repair Revenue", money($one("SELECT COALESCE(SUM(amount_applied),0) FROM payments WHERE payable_type='repair' AND status='completed' AND DATE(paid_at)=CURDATE()")), 'fa-screwdriver-wrench', 'ok'],
  ['Pending Repairs',   (int)$one("SELECT COUNT(*) FROM repair_jobs WHERE status='Pending'"), 'fa-hourglass-half', ''],
  ['In Progress',       (int)$one("SELECT COUNT(*) FROM repair_jobs WHERE status='In Progress'"), 'fa-wrench', ''],
  ['Completed Repairs', (int)$one("SELECT COUNT(*) FROM repair_jobs WHERE status IN ('Completed','Released')"), 'fa-circle-check', 'ok'],
  ['Total Labor Charges', money($one("SELECT COALESCE(SUM(amount),0) FROM labor_entries WHERE status='active'")), 'fa-helmet-safety', ''],
  ['Inventory Value (at cost)', money($one("SELECT COALESCE(SUM(purchase_cost*stock_qty),0) FROM inventory_items WHERE is_active=1")), 'fa-peso-sign', ''],
];

$recentSales = $pdo->query("SELECT receipt_no, COALESCE(customer_name,'Walk-in') AS cust, total, status, created_at FROM sales ORDER BY id DESC LIMIT 5")->fetchAll();
$recentJobs = $pdo->query("SELECT j.job_no, c.full_name, m.model, j.status, j.total_due FROM repair_jobs j JOIN customers c ON c.id=j.customer_id JOIN motorcycles m ON m.id=j.motorcycle_id ORDER BY j.id DESC LIMIT 5")->fetchAll();
$recentMoves = $pdo->query("SELECT i.name, v.movement_type, v.quantity_change, v.created_at FROM inventory_movements v JOIN inventory_items i ON i.id=v.item_id ORDER BY v.id DESC LIMIT 5")->fetchAll();
$lowItems = $pdo->query("SELECT name, sku, stock_qty, min_stock FROM inventory_items WHERE is_active=1 AND stock_qty<=min_stock ORDER BY stock_qty ASC LIMIT 6")->fetchAll();

$pageTitle = 'Dashboard';
require __DIR__ . '/includes/header.php';
?>
<div class="stat-grid">
<?php foreach ($stats as [$label, $val, $icon, $tone]): ?>
  <div class="stat-card <?= e($tone) ?>"><i class="fa-solid <?= e($icon) ?>"></i><div><span><?= e($label) ?></span><strong><?= e($val) ?></strong></div></div>
<?php endforeach; ?>
</div>

<?php if ($lowItems): ?>
<div class="alert alert-warning"><strong>Stock alert:</strong>
  <?= implode(', ', array_map(fn($i) => e($i['name']) . ' (' . (int)$i['stock_qty'] . ' left)', $lowItems)) ?></div>
<?php endif; ?>

<div class="grid-3">
  <section class="card"><h2 class="card-title"><i class="fa-solid fa-cash-register"></i> Recent Sales</h2>
    <div class="table-wrap"><table class="table"><thead><tr><th>Receipt</th><th>Customer</th><th class="num">Total</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($recentSales as $s): ?><tr><td><?= e($s['receipt_no']) ?></td><td><?= e($s['cust']) ?></td><td class="num"><?= money($s['total']) ?></td><td><span class="badge b-<?= e($s['status']) ?>"><?= e($s['status']) ?></span></td></tr>
    <?php endforeach; if (!$recentSales): ?><tr><td colspan="4" class="empty">No sales yet.</td></tr><?php endif; ?></tbody></table></div></section>

  <section class="card"><h2 class="card-title"><i class="fa-solid fa-screwdriver-wrench"></i> Recent Repair Jobs</h2>
    <div class="table-wrap"><table class="table"><thead><tr><th>Job</th><th>Customer</th><th>Status</th><th class="num">Total</th></tr></thead><tbody>
    <?php foreach ($recentJobs as $j): ?><tr><td><?= e($j['job_no']) ?></td><td><?= e($j['full_name']) ?> <small>(<?= e($j['model']) ?>)</small></td><td><span class="badge"><?= e($j['status']) ?></span></td><td class="num"><?= money($j['total_due']) ?></td></tr>
    <?php endforeach; if (!$recentJobs): ?><tr><td colspan="4" class="empty">No repair jobs yet.</td></tr><?php endif; ?></tbody></table></div></section>

  <section class="card"><h2 class="card-title"><i class="fa-solid fa-dolly"></i> Recent Inventory Movements</h2>
    <div class="table-wrap"><table class="table"><thead><tr><th>Item</th><th>Type</th><th class="num">Qty</th></tr></thead><tbody>
    <?php foreach ($recentMoves as $m): ?><tr><td><?= e($m['name']) ?></td><td><?= e(str_replace('_',' ',$m['movement_type'])) ?></td><td class="num <?= $m['quantity_change'] < 0 ? 'neg' : 'pos' ?>"><?= ($m['quantity_change'] > 0 ? '+' : '') . (int)$m['quantity_change'] ?></td></tr>
    <?php endforeach; if (!$recentMoves): ?><tr><td colspan="3" class="empty">No movements yet.</td></tr><?php endif; ?></tbody></table></div></section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
