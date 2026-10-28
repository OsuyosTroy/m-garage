<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/_filter.php';
guard();
[$from, $to] = report_range();
$jobs = fetch_all("SELECT j.*, c.full_name, m.model FROM repair_jobs j JOIN customers c ON c.id=j.customer_id JOIN motorcycles m ON m.id=j.motorcycle_id WHERE j.date_received BETWEEN ? AND ? ORDER BY j.id DESC", [$from, $to]);
$parts = fetch_all("SELECT i.name, i.sku, SUM(p.quantity) AS qty, SUM(p.line_subtotal) AS rev FROM repair_parts_used p JOIN repair_jobs j ON j.id=p.repair_job_id JOIN inventory_items i ON i.id=p.item_id WHERE p.status='active' AND j.status<>'Cancelled' AND j.date_received BETWEEN ? AND ? GROUP BY i.id ORDER BY qty DESC", [$from, $to]);
$owing = fetch_all("SELECT j.job_no, j.id, c.full_name, j.total_due, j.amount_paid, j.payment_status FROM repair_jobs j JOIN customers c ON c.id=j.customer_id WHERE j.status<>'Cancelled' AND j.total_due>j.amount_paid ORDER BY j.id DESC");
$pageTitle = 'Repair Report';
require __DIR__ . '/../includes/header.php';
report_filter_form($from, $to);
$sum = ['parts' => 0, 'labor' => 0, 'total' => 0, 'paid' => 0];
?>
<div class="card"><h2 class="card-title">Repair service transactions</h2><div class="table-wrap"><table class="table"><thead><tr><th>Job</th><th>Date</th><th>Customer</th><th>Motorcycle</th><th>Status</th><th class="num">Parts</th><th class="num">Labor</th><th class="num">Total</th><th class="num">Paid</th></tr></thead><tbody>
<?php foreach ($jobs as $j): if ($j['status'] !== 'Cancelled') { $sum['parts'] += $j['parts_subtotal']; $sum['labor'] += $j['labor_subtotal']; $sum['total'] += $j['total_due']; $sum['paid'] += $j['amount_paid']; } ?>
<tr><td><a href="../repairs/view.php?id=<?= (int)$j['id'] ?>"><?= e($j['job_no']) ?></a></td><td><?= e($j['date_received']) ?></td><td><?= e($j['full_name']) ?></td><td><?= e($j['model']) ?></td><td><?= status_badge($j['status']) ?></td><td class="num"><?= money($j['parts_subtotal']) ?></td><td class="num"><?= money($j['labor_subtotal']) ?></td><td class="num"><?= money($j['total_due']) ?></td><td class="num"><?= money($j['amount_paid']) ?></td></tr>
<?php endforeach; if (!$jobs): ?><tr><td colspan="9" class="empty">No repair jobs in this period.</td></tr><?php endif; ?>
<tr><td colspan="5" class="num"><strong>Totals (excluding cancelled)</strong></td><td class="num"><strong><?= money($sum['parts']) ?></strong></td><td class="num"><strong><?= money($sum['labor']) ?></strong></td><td class="num"><strong><?= money($sum['total']) ?></strong></td><td class="num"><strong><?= money($sum['paid']) ?></strong></td></tr></tbody></table></div></div>
<div class="card mt"><h2 class="card-title">Parts used in repairs</h2><div class="table-wrap"><table class="table"><thead><tr><th>Part</th><th>SKU</th><th class="num">Qty used</th><th class="num">Charged</th></tr></thead><tbody>
<?php foreach ($parts as $p): ?><tr><td><?= e($p['name']) ?></td><td><?= e($p['sku']) ?></td><td class="num"><?= (int)$p['qty'] ?></td><td class="num"><?= money($p['rev']) ?></td></tr><?php endforeach; if (!$parts): ?><tr><td colspan="4" class="empty">None.</td></tr><?php endif; ?></tbody></table></div></div>
<div class="card mt"><h2 class="card-title">Outstanding balances (all dates)</h2><div class="table-wrap"><table class="table"><thead><tr><th>Job</th><th>Customer</th><th>Payment status</th><th class="num">Total</th><th class="num">Balance</th></tr></thead><tbody>
<?php foreach ($owing as $o): ?><tr><td><a href="../repairs/view.php?id=<?= (int)$o['id'] ?>"><?= e($o['job_no']) ?></a></td><td><?= e($o['full_name']) ?></td><td><?= status_badge($o['payment_status']) ?></td><td class="num"><?= money($o['total_due']) ?></td><td class="num"><?= money($o['total_due'] - $o['amount_paid']) ?></td></tr><?php endforeach; if (!$owing): ?><tr><td colspan="5" class="empty">No outstanding balances.</td></tr><?php endif; ?></tbody></table></div></div>
<form class="toolbar no-print mt" method="get" action="../customers/history.php"><select name="customer_id"><?php foreach (fetch_all('SELECT id, full_name FROM customers ORDER BY full_name') as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['full_name']) ?></option><?php endforeach; ?></select><button class="btn btn-outline" type="submit">Open customer repair history</button></form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
