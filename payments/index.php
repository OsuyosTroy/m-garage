<?php
require_once __DIR__ . '/../includes/auth.php';
guard();
if (is_post()) {
    require_csrf();
    $pid = parse_int($_POST['id'] ?? ''); $reason = input('reason');
    try {
        if (!$pid || $reason === '') throw new RuntimeException('A reason is required to void a payment.');
        in_transaction(function (PDO $pdo) use ($pid, $reason) {
            $s = $pdo->prepare("SELECT * FROM payments WHERE id=? AND payable_type='repair' AND status='completed' FOR UPDATE"); $s->execute([$pid]); $p = $s->fetch();
            if (!$p) throw new RuntimeException('Only completed repair payments can be voided here. Sales are reversed from Sales History.');
            $pdo->prepare("UPDATE payments SET status='voided', notes=? WHERE id=?")->execute(["Voided: $reason", $pid]);
            recalc_job($pdo, (int)$p['repair_job_id']);
            log_activity('payment_voided', 'payments', "{$p['payment_no']} voided: $reason");
        });
        flash('success', 'Payment voided and balance recalculated.');
    } catch (Throwable $ex) { flash('error', $ex->getMessage()); }
    redirect('payments/index.php');
}
$q = input('q'); $type = input('type'); $from = input('from'); $to = input('to'); $w = []; $p = [];
if ($q !== '') { $w[] = '(p.payment_no LIKE ? OR s.receipt_no LIKE ? OR j.job_no LIKE ?)'; array_push($p, "%$q%", "%$q%", "%$q%"); }
if (in_array($type, ['sale', 'repair'], true)) { $w[] = 'p.payable_type=?'; $p[] = $type; }
if (valid_date($from)) { $w[] = 'DATE(p.paid_at)>=?'; $p[] = $from; }
if (valid_date($to)) { $w[] = 'DATE(p.paid_at)<=?'; $p[] = $to; }
$rows = fetch_all('SELECT p.*, s.receipt_no, j.job_no, u.full_name FROM payments p LEFT JOIN sales s ON s.id=p.sale_id LEFT JOIN repair_jobs j ON j.id=p.repair_job_id JOIN users u ON u.id=p.received_by' . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . ' ORDER BY p.id DESC LIMIT 300', $p);
$owing = fetch_all("SELECT j.id, j.job_no, j.total_due, j.amount_paid, j.payment_status, c.full_name FROM repair_jobs j JOIN customers c ON c.id=j.customer_id WHERE j.status<>'Cancelled' AND j.total_due>j.amount_paid ORDER BY j.id DESC LIMIT 20");
$pageTitle = 'Payments and Transactions';
require __DIR__ . '/../includes/header.php';
?>
<form class="toolbar" method="get"><div class="grow"><input type="text" name="q" value="<?= e($q) ?>" placeholder="Payment no., receipt no. or job no..."></div>
<select name="type"><option value="">All types</option><option value="sale" <?= $type === 'sale' ? 'selected' : '' ?>>Sales</option><option value="repair" <?= $type === 'repair' ? 'selected' : '' ?>>Repairs</option></select><input type="date" name="from" value="<?= e($from) ?>"><input type="date" name="to" value="<?= e($to) ?>">
<button class="btn btn-outline" type="submit">Filter</button><a class="btn btn-primary" href="record.php"><i class="fa-solid fa-plus"></i> Record repair payment</a></form>
<?php if ($owing): ?><div class="card" style="margin-bottom:16px"><h2 class="card-title">Outstanding repair balances</h2><div class="table-wrap"><table class="table"><tbody>
<?php foreach ($owing as $o): ?><tr><td><a href="../repairs/view.php?id=<?= (int)$o['id'] ?>"><?= e($o['job_no']) ?></a></td><td><?= e($o['full_name']) ?></td><td><?= status_badge($o['payment_status']) ?></td><td class="num"><?= money($o['total_due'] - $o['amount_paid']) ?></td><td><a class="btn btn-sm btn-outline" href="record.php?job_id=<?= (int)$o['id'] ?>">Pay</a></td></tr><?php endforeach; ?></tbody></table></div></div><?php endif; ?>
<div class="card"><div class="table-wrap"><table class="table"><thead><tr><th>Payment no.</th><th>Date</th><th>For</th><th class="num">Applied</th><th class="num">Tendered</th><th class="num">Change</th><th>Status</th><th>By</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td><?= e($r['payment_no']) ?></td><td><?= e($r['paid_at']) ?></td><td><?= $r['payable_type'] === 'sale' ? '<a href="../sales/view.php?id=' . (int)$r['sale_id'] . '">' . e($r['receipt_no']) . '</a>' : '<a href="../repairs/view.php?id=' . (int)$r['repair_job_id'] . '">' . e($r['job_no']) . '</a>' ?></td>
<td class="num"><?= money($r['amount_applied']) ?></td><td class="num"><?= money($r['amount_tendered']) ?></td><td class="num"><?= money($r['change_given']) ?></td><td><span class="badge <?= $r['status'] === 'voided' ? 'b-out' : 'b-ok' ?>"><?= e($r['status']) ?></span></td><td><?= e($r['full_name']) ?></td>
<td><?php if ($r['payable_type'] === 'repair' && $r['status'] === 'completed'): ?><form method="post" class="actions" data-confirm="Void this payment?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="text" name="reason" placeholder="Reason" required style="width:110px"><button class="btn btn-sm btn-danger" type="submit">Void</button></form><?php endif; ?></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="9" class="empty">No payments found.</td></tr><?php endif; ?></tbody></table></div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
