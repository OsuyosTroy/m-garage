<?php
require_once __DIR__ . '/../includes/auth.php';
guard();
$motoId = parse_int($_GET['moto_id'] ?? ''); $custId = parse_int($_GET['customer_id'] ?? '');
if ($motoId) { $m = fetch_row('SELECT customer_id FROM motorcycles WHERE id=?', [$motoId]); $custId = $m['customer_id'] ?? null; }
$cust = $custId ? fetch_row('SELECT * FROM customers WHERE id=?', [$custId]) : null;
if (!$cust) { flash('error', 'Customer or motorcycle not found.'); redirect('customers/index.php'); }
$jobs = fetch_all('SELECT j.*, m.brand, m.model, m.plate_number FROM repair_jobs j JOIN motorcycles m ON m.id=j.motorcycle_id WHERE j.customer_id=?' . ($motoId ? ' AND j.motorcycle_id=?' : '') . ' ORDER BY j.id DESC', $motoId ? [$custId, $motoId] : [$custId]);
$sales = $motoId ? [] : fetch_all("SELECT * FROM sales WHERE customer_id=? ORDER BY id DESC LIMIT 50", [$custId]);
$pageTitle = 'Customer History: ' . $cust['full_name'];
require __DIR__ . '/../includes/header.php';
?>
<div class="card"><dl class="kv"><dt>Customer</dt><dd><?= e($cust['full_name']) ?></dd><dt>Contact</dt><dd><?= e($cust['contact_number']) ?></dd><dt>Address</dt><dd><?= e($cust['address']) ?></dd></dl>
<p class="mt"><a class="btn btn-outline" href="motorcycles.php?customer_id=<?= (int)$custId ?>">Motorcycles</a> <a class="btn btn-outline" href="index.php">Back</a> <button class="btn btn-outline no-print" onclick="window.print()"><i class="fa-solid fa-print"></i> Print</button></p></div>
<?php foreach ($jobs as $j):
  $parts = fetch_all("SELECT p.*, i.name FROM repair_parts_used p JOIN inventory_items i ON i.id=p.item_id WHERE p.repair_job_id=? AND p.status='active'", [$j['id']]);
  $labor = fetch_all("SELECT l.*, mc.full_name FROM labor_entries l JOIN mechanics mc ON mc.id=l.mechanic_id WHERE l.repair_job_id=? AND l.status='active'", [$j['id']]); ?>
<div class="card mt"><h2 class="card-title"><a href="../repairs/view.php?id=<?= (int)$j['id'] ?>"><?= e($j['job_no']) ?></a> &middot; <?= e($j['brand'] . ' ' . $j['model']) ?> <?= status_badge($j['status']) ?> <?= status_badge($j['payment_status']) ?></h2>
  <p><strong>Problem:</strong> <?= e($j['reported_problem']) ?> <span class="muted">(<?= e($j['date_received']) ?>)</span></p>
  <div class="grid-2"><div><strong>Parts used</strong><ul><?php foreach ($parts as $p): ?><li><?= e($p['name']) ?> x<?= (int)$p['quantity'] ?> - <?= money($p['line_subtotal']) ?></li><?php endforeach; if (!$parts) echo '<li class="muted">None</li>'; ?></ul></div>
  <div><strong>Labor</strong><ul><?php foreach ($labor as $l): ?><li><?= e($l['service_type']) ?> (<?= e($l['full_name']) ?>) - <?= money($l['amount']) ?></li><?php endforeach; if (!$labor) echo '<li class="muted">None</li>'; ?></ul></div></div>
  <p>Total: <strong><?= money($j['total_due']) ?></strong> &middot; Paid: <?= money($j['amount_paid']) ?></p></div>
<?php endforeach; if (!$jobs): ?><div class="card mt"><p class="empty">No repair history.</p></div><?php endif; ?>
<?php if ($sales): ?><div class="card mt"><h2 class="card-title">Direct purchases</h2><div class="table-wrap"><table class="table"><thead><tr><th>Receipt</th><th>Date</th><th class="num">Total</th><th>Status</th></tr></thead><tbody>
<?php foreach ($sales as $s): ?><tr><td><a href="../sales/view.php?id=<?= (int)$s['id'] ?>"><?= e($s['receipt_no']) ?></a></td><td><?= e($s['created_at']) ?></td><td class="num"><?= money($s['total']) ?></td><td><?= e($s['status']) ?></td></tr><?php endforeach; ?></tbody></table></div></div><?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
