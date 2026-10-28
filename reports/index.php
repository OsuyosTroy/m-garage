<?php
require_once __DIR__ . '/../includes/auth.php';
guard();
$one = fn(string $sql) => (float)db()->query($sql)->fetchColumn();
$ranges = ['Today' => 'DATE(%s)=CURDATE()', 'This week' => 'YEARWEEK(%s,1)=YEARWEEK(CURDATE(),1)', 'This month' => "DATE_FORMAT(%s,'%%Y-%%m')=DATE_FORMAT(CURDATE(),'%%Y-%%m')"];
$pageTitle = 'Reports';
require __DIR__ . '/../includes/header.php';
?>
<div class="card"><h2 class="card-title">Quick summary (gross revenue, not profit)</h2><div class="table-wrap"><table class="table"><thead><tr><th>Period</th><th class="num">Direct sales</th><th class="num">Repair payments received</th><th class="num">Labor charged</th><th class="num">Repair jobs opened</th></tr></thead><tbody>
<?php foreach ($ranges as $label => $cond): ?><tr><td><?= e($label) ?></td>
<td class="num"><?= money($one("SELECT COALESCE(SUM(total),0) FROM sales WHERE status='completed' AND " . sprintf($cond, 'created_at'))) ?></td>
<td class="num"><?= money($one("SELECT COALESCE(SUM(amount_applied),0) FROM payments WHERE payable_type='repair' AND status='completed' AND " . sprintf($cond, 'paid_at'))) ?></td>
<td class="num"><?= money($one("SELECT COALESCE(SUM(amount),0) FROM labor_entries WHERE status='active' AND " . sprintf($cond, 'service_date'))) ?></td>
<td class="num"><?= (int)$one('SELECT COUNT(*) FROM repair_jobs WHERE ' . sprintf($cond, 'date_received')) ?></td></tr><?php endforeach; ?></tbody></table></div></div>
<div class="stat-grid mt">
<?php foreach ([['sales.php', 'fa-cash-register', 'Sales (daily / weekly / monthly) and items sold'], ['repairs.php', 'fa-screwdriver-wrench', 'Repair transactions, parts used, balances, customer history'], ['labor.php', 'fa-helmet-safety', 'Labor charges by mechanic'], ['inventory.php', 'fa-boxes-stacked', 'Inventory balances, low stock and stock receiving']] as [$f, $ic, $l]): ?>
<a class="stat-card" href="<?= $f ?>"><i class="fa-solid <?= $ic ?>"></i><div><strong style="font-size:1rem"><?= e($l) ?></strong></div></a><?php endforeach; ?></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
