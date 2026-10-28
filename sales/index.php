<?php
require_once __DIR__ . '/../includes/auth.php';
guard();
$q = input('q'); $from = input('from'); $to = input('to'); $w = []; $p = [];
if ($q !== '') { $w[] = '(s.receipt_no LIKE ? OR s.customer_name LIKE ?)'; array_push($p, "%$q%", "%$q%"); }
if (valid_date($from)) { $w[] = 'DATE(s.created_at)>=?'; $p[] = $from; }
if (valid_date($to)) { $w[] = 'DATE(s.created_at)<=?'; $p[] = $to; }
$rows = fetch_all('SELECT s.*, u.full_name AS cashier FROM sales s JOIN users u ON u.id=s.cashier_id' . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . ' ORDER BY s.id DESC LIMIT 300', $p);
$pageTitle = 'Sales History';
require __DIR__ . '/../includes/header.php';
?>
<form class="toolbar" method="get"><div class="grow"><input type="text" name="q" value="<?= e($q) ?>" placeholder="Receipt number or customer..."></div><input type="date" name="from" value="<?= e($from) ?>"><input type="date" name="to" value="<?= e($to) ?>">
<button class="btn btn-outline" type="submit">Search</button><a class="btn btn-primary" href="pos.php"><i class="fa-solid fa-cash-register"></i> New sale</a></form>
<div class="card"><div class="table-wrap"><table class="table"><thead><tr><th>Receipt</th><th>Date</th><th>Customer</th><th>Cashier</th><th class="num">Total</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $s): ?><tr><td><a href="view.php?id=<?= (int)$s['id'] ?>"><?= e($s['receipt_no']) ?></a></td><td><?= e($s['created_at']) ?></td><td><?= e($s['customer_name'] ?: 'Walk-in') ?></td><td><?= e($s['cashier']) ?></td><td class="num"><?= money($s['total']) ?></td><td><span class="badge b-<?= e($s['status']) ?>"><?= e($s['status']) ?></span></td><td><a class="btn btn-sm btn-outline" href="receipt.php?id=<?= (int)$s['id'] ?>" title="Receipt"><i class="fa-solid fa-print"></i></a></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="7" class="empty">No sales found.</td></tr><?php endif; ?></tbody></table></div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
