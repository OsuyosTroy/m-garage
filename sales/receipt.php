<?php
require_once __DIR__ . '/../includes/auth.php';
guard();
$saleId = parse_int($_GET['id'] ?? ''); $jobId = parse_int($_GET['job_id'] ?? '');
$shop = setting('shop_name', 'M-Garage'); $addr = setting('shop_address'); $contact = setting('shop_contact'); $foot = setting('receipt_footer');
if ($saleId) {
    $sale = fetch_row('SELECT s.*, u.full_name AS cashier FROM sales s JOIN users u ON u.id=s.cashier_id WHERE s.id=?', [$saleId]);
    if (!$sale) { flash('error', 'Sale not found.'); redirect('sales/index.php'); }
    $lines = fetch_all('SELECT si.*, i.name FROM sale_items si JOIN inventory_items i ON i.id=si.item_id WHERE si.sale_id=?', [$saleId]);
    $pay = fetch_row("SELECT * FROM payments WHERE sale_id=? AND payable_type='sale' ORDER BY id LIMIT 1", [$saleId]);
    $pageTitle = 'Receipt ' . $sale['receipt_no'];
    require __DIR__ . '/../includes/header.php'; ?>
<p class="no-print"><button class="btn btn-primary" onclick="window.print()"><i class="fa-solid fa-print"></i> Print receipt</button> <a class="btn btn-outline" href="pos.php">New sale</a> <a class="btn btn-outline" href="view.php?id=<?= (int)$saleId ?>">Details</a></p>
<div class="receipt"><h2><?= e($shop) ?></h2><p><?= e($addr) ?></p><?php if ($contact): ?><p><?= e($contact) ?></p><?php endif; ?><hr>
<p style="text-align:left">Receipt: <?= e($sale['receipt_no']) ?><br>Date: <?= e($sale['created_at']) ?><br>Cashier: <?= e($sale['cashier']) ?><br>Customer: <?= e($sale['customer_name'] ?: 'Walk-in') ?></p><hr>
<table><?php foreach ($lines as $l): ?><tr><td colspan="2"><?= e($l['name']) ?></td></tr><tr><td><?= (int)$l['quantity'] ?> x <?= number_format((float)$l['unit_price'], 2) ?></td><td class="r"><?= number_format((float)$l['line_subtotal'], 2) ?></td></tr><?php endforeach; ?></table><hr>
<table><tr><td>Subtotal</td><td class="r"><?= number_format((float)$sale['subtotal'], 2) ?></td></tr><tr><td>Discount</td><td class="r">-<?= number_format((float)$sale['discount'], 2) ?></td></tr><tr><td><b>TOTAL</b></td><td class="r"><b><?= number_format((float)$sale['total'], 2) ?></b></td></tr>
<?php if ($pay): ?><tr><td>Cash</td><td class="r"><?= number_format((float)$pay['amount_tendered'], 2) ?></td></tr><tr><td>Change</td><td class="r"><?= number_format((float)$pay['change_given'], 2) ?></td></tr><?php endif; ?></table><hr>
<?php if ($sale['status'] !== 'completed'): ?><p><b>*** <?= strtoupper(e($sale['status'])) ?> ***</b></p><?php endif; ?><p><?= e($foot) ?></p></div>
<?php require __DIR__ . '/../includes/footer.php'; exit; }

if ($jobId) {
    $job = fetch_row('SELECT j.*, c.full_name, c.contact_number, m.brand, m.model, m.plate_number FROM repair_jobs j JOIN customers c ON c.id=j.customer_id JOIN motorcycles m ON m.id=j.motorcycle_id WHERE j.id=?', [$jobId]);
    if (!$job) { flash('error', 'Job not found.'); redirect('repairs/index.php'); }
    $parts = fetch_all("SELECT p.*, i.name FROM repair_parts_used p JOIN inventory_items i ON i.id=p.item_id WHERE p.repair_job_id=? AND p.status='active'", [$jobId]);
    $labor = fetch_all("SELECT l.*, m.full_name FROM labor_entries l JOIN mechanics m ON m.id=l.mechanic_id WHERE l.repair_job_id=? AND l.status='active'", [$jobId]);
    $pays = fetch_all("SELECT * FROM payments WHERE repair_job_id=? AND status='completed' ORDER BY id", [$jobId]);
    $pageTitle = 'Invoice ' . $job['job_no'];
    require __DIR__ . '/../includes/header.php'; ?>
<p class="no-print"><button class="btn btn-primary" onclick="window.print()"><i class="fa-solid fa-print"></i> Print invoice</button> <a class="btn btn-outline" href="../repairs/view.php?id=<?= (int)$jobId ?>">Back to job</a></p>
<div class="invoice card"><h2 style="margin:0"><?= e($shop) ?></h2><p class="muted"><?= e($addr) ?> <?= e($contact) ?></p><h3>Service Invoice <?= e($job['job_no']) ?></h3>
<p>Customer: <strong><?= e($job['full_name']) ?></strong> <?= e($job['contact_number']) ?><br>Motorcycle: <?= e($job['brand'] . ' ' . $job['model'] . ' ' . $job['plate_number']) ?><br>Received: <?= e($job['date_received']) ?> &middot; Status: <?= e($job['status']) ?></p>
<table class="table"><thead><tr><th>Parts</th><th class="num">Qty</th><th class="num">Price</th><th class="num">Amount</th></tr></thead><tbody><?php foreach ($parts as $p): ?><tr><td><?= e($p['name']) ?></td><td class="num"><?= (int)$p['quantity'] ?></td><td class="num"><?= money($p['unit_price']) ?></td><td class="num"><?= money($p['line_subtotal']) ?></td></tr><?php endforeach; if (!$parts) echo '<tr><td colspan="4" class="muted">None</td></tr>'; ?></tbody></table>
<table class="table"><thead><tr><th>Labor</th><th>Mechanic</th><th class="num">Amount</th></tr></thead><tbody><?php foreach ($labor as $l): ?><tr><td><?= e($l['service_type']) ?></td><td><?= e($l['full_name']) ?></td><td class="num"><?= money($l['amount']) ?></td></tr><?php endforeach; if (!$labor) echo '<tr><td colspan="3" class="muted">None</td></tr>'; ?></tbody></table>
<div class="totals"><div><span>Parts subtotal</span><span><?= money($job['parts_subtotal']) ?></span></div><div><span>Labor subtotal</span><span><?= money($job['labor_subtotal']) ?></span></div><div><span>Discount</span><span>- <?= money($job['discount']) ?></span></div>
<div class="grand"><span>Amount due</span><span><?= money($job['total_due']) ?></span></div><?php foreach ($pays as $p): ?><div><span>Paid <?= e(substr($p['paid_at'], 0, 10)) ?> (<?= e($p['payment_no']) ?>)</span><span><?= money($p['amount_applied']) ?></span></div><?php endforeach; ?>
<div class="grand"><span>Balance (<?= e($job['payment_status']) ?>)</span><span><?= money(max(0, $job['total_due'] - $job['amount_paid'])) ?></span></div></div><p class="muted" style="text-align:center"><?= e($foot) ?></p></div>
<?php require __DIR__ . '/../includes/footer.php'; exit; }
flash('error', 'Nothing to print.'); redirect('sales/index.php');
