<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/_filter.php';
guard();
[$from, $to] = report_range();
$g = input('group', 'daily');
$expr = ['daily' => 'DATE(created_at)', 'weekly' => "CONCAT(YEAR(created_at),'-W',LPAD(WEEK(created_at,1),2,'0'))", 'monthly' => "DATE_FORMAT(created_at,'%Y-%m')"][$g] ?? 'DATE(created_at)';
$rows = fetch_all("SELECT $expr AS period, COUNT(*) AS n, SUM(subtotal) AS sub, SUM(discount) AS disc, SUM(total) AS tot FROM sales WHERE status='completed' AND DATE(created_at) BETWEEN ? AND ? GROUP BY period ORDER BY period", [$from, $to]);
$items = fetch_all("SELECT i.name, i.sku, SUM(si.quantity) AS qty, SUM(si.line_subtotal) AS rev FROM sale_items si JOIN sales s ON s.id=si.sale_id JOIN inventory_items i ON i.id=si.item_id WHERE s.status='completed' AND DATE(s.created_at) BETWEEN ? AND ? GROUP BY i.id ORDER BY qty DESC", [$from, $to]);
$reversed = fetch_row("SELECT COUNT(*) AS n, COALESCE(SUM(total),0) AS t FROM sales WHERE status<>'completed' AND DATE(created_at) BETWEEN ? AND ?", [$from, $to]);
$pageTitle = 'Sales Report';
require __DIR__ . '/../includes/header.php';
report_filter_form($from, $to, '<select name="group">' . implode('', array_map(fn($k) => '<option value="' . $k . '"' . ($g === $k ? ' selected' : '') . '>' . ucfirst($k) . '</option>', ['daily', 'weekly', 'monthly'])) . '</select>');
?>
<div class="card"><h2 class="card-title">Sales by period (completed sales)</h2><div class="table-wrap"><table class="table"><thead><tr><th>Period</th><th class="num">Transactions</th><th class="num">Subtotal</th><th class="num">Discounts</th><th class="num">Total sales</th></tr></thead><tbody>
<?php $gt = 0; foreach ($rows as $r): $gt += $r['tot']; ?><tr><td><?= e($r['period']) ?></td><td class="num"><?= (int)$r['n'] ?></td><td class="num"><?= money($r['sub']) ?></td><td class="num"><?= money($r['disc']) ?></td><td class="num"><?= money($r['tot']) ?></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="5" class="empty">No sales in this period.</td></tr><?php endif; ?><tr><td colspan="4" class="num"><strong>Total</strong></td><td class="num"><strong><?= money($gt) ?></strong></td></tr></tbody></table></div>
<p class="hint">Cancelled/refunded sales excluded: <?= (int)$reversed['n'] ?> (<?= money($reversed['t']) ?>). Figures are gross sales, not profit.</p></div>
<div class="card mt"><h2 class="card-title">Directly sold inventory items</h2><div class="table-wrap"><table class="table"><thead><tr><th>Item</th><th>SKU</th><th class="num">Qty sold</th><th class="num">Revenue</th></tr></thead><tbody>
<?php foreach ($items as $i): ?><tr><td><?= e($i['name']) ?></td><td><?= e($i['sku']) ?></td><td class="num"><?= (int)$i['qty'] ?></td><td class="num"><?= money($i['rev']) ?></td></tr><?php endforeach; if (!$items): ?><tr><td colspan="4" class="empty">No items sold.</td></tr><?php endif; ?></tbody></table></div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
