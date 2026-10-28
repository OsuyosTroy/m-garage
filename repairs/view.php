<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/_helpers.php';
guard();
$id = parse_int($_GET['id'] ?? $_POST['id'] ?? '');
$job = load_job($id);
if (is_post()) {
    require_csrf();
    $act = input('action'); $new = input('status'); $reason = input('reason');
    try {
        if (job_locked($job)) throw new RuntimeException('This job is ' . $job['status'] . ' and can no longer change status.');
        if ($act === 'cancel') {
            if ($reason === '') throw new RuntimeException('A reason is required to cancel a job.');
            if ((float)$job['amount_paid'] > 0) throw new RuntimeException('This job has payments. Void the payments first, then cancel.');
            in_transaction(function (PDO $pdo) use ($id, $job, $reason) {
                $parts = $pdo->prepare("SELECT id, item_id, quantity FROM repair_parts_used WHERE repair_job_id=? AND status='active' FOR UPDATE"); $parts->execute([$id]);
                foreach ($parts->fetchAll() as $p) {
                    stock_change($pdo, (int)$p['item_id'], (int)$p['quantity'], 'reversal', 'repair_job', $id, "Job {$job['job_no']} cancelled: $reason", current_user()['id']);
                    $pdo->prepare("UPDATE repair_parts_used SET status='reversed' WHERE id=?")->execute([$p['id']]);
                }
                $pdo->prepare("UPDATE repair_jobs SET status='Cancelled', notes=CONCAT(COALESCE(notes,''), ?) WHERE id=?")->execute(["\nCancelled: $reason", $id]);
                recalc_job($pdo, $id);
            });
            log_activity('repair_cancelled', 'repairs', "Cancelled {$job['job_no']}: $reason"); flash('success', 'Job cancelled and parts returned to stock.');
        } elseif ($act === 'status') {
            if (!in_array($new, ['Pending', 'In Progress', 'Waiting for Parts', 'Completed', 'Released'], true)) throw new RuntimeException('Invalid status.');
            if ($new === 'Released' && (float)$job['total_due'] - (float)$job['amount_paid'] > 0.004) throw new RuntimeException('The job must be fully paid before it can be released.');
            $done = in_array($new, ['Completed', 'Released'], true);
            db()->prepare('UPDATE repair_jobs SET status=?, date_completed=IF(?=1, COALESCE(date_completed, CURDATE()), NULL), is_finalized=? WHERE id=?')->execute([$new, $done ? 1 : 0, $new === 'Released' ? 1 : 0, $id]);
            log_activity('repair_status', 'repairs', "{$job['job_no']} -> $new"); flash('success', "Status updated to $new.");
        }
    } catch (Throwable $ex) { flash('error', $ex->getMessage()); }
    redirect('repairs/view.php?id=' . $id);
}
$mechs = fetch_all('SELECT m.full_name, m.specialty FROM repair_mechanics rm JOIN mechanics m ON m.id=rm.mechanic_id WHERE rm.repair_job_id=?', [$id]);
$labor = fetch_all("SELECT l.*, m.full_name FROM labor_entries l JOIN mechanics m ON m.id=l.mechanic_id WHERE l.repair_job_id=? AND l.status='active' ORDER BY l.id", [$id]);
$parts = fetch_all("SELECT p.*, i.name, i.sku, m.full_name AS mechanic_name FROM repair_parts_used p JOIN inventory_items i ON i.id=p.item_id LEFT JOIN mechanics m ON m.id=p.mechanic_id WHERE p.repair_job_id=? AND p.status='active' ORDER BY p.id", [$id]);
$pays = fetch_all('SELECT p.*, u.full_name FROM payments p JOIN users u ON u.id=p.received_by WHERE p.repair_job_id=? ORDER BY p.id', [$id]);
$balance = max(0, (float)$job['total_due'] - (float)$job['amount_paid']);
$pageTitle = 'Job ' . $job['job_no'];
require __DIR__ . '/../includes/header.php';
echo job_tabs($id, 'view');
?>
<div class="grid-2">
<div class="card"><h2 class="card-title"><?= e($job['job_no']) ?> <?= status_badge($job['status']) ?> <?= status_badge($job['payment_status']) ?></h2>
 <dl class="kv"><dt>Customer</dt><dd><?= e($job['cust']) ?> <?= e($job['contact_number']) ?></dd><dt>Motorcycle</dt><dd><?= e($job['brand'] . ' ' . $job['model'] . ' ' . $job['plate_number']) ?></dd>
 <dt>Date received</dt><dd><?= e($job['date_received']) ?></dd><dt>Completed</dt><dd><?= e($job['date_completed'] ?: '-') ?></dd>
 <dt>Reported problem</dt><dd><?= nl2br(e($job['reported_problem'])) ?></dd><dt>Diagnosis</dt><dd><?= nl2br(e($job['diagnosis'])) ?></dd><dt>Requested services</dt><dd><?= nl2br(e($job['requested_services'])) ?></dd>
 <dt>Mechanics</dt><dd><?= $mechs ? e(implode(', ', array_column($mechs, 'full_name'))) : '<span class="muted">None assigned</span>' ?></dd><dt>Notes</dt><dd><?= nl2br(e($job['notes'])) ?></dd></dl></div>
<div class="card"><h2 class="card-title">Billing</h2>
 <div class="totals"><div><span>Parts subtotal</span><span><?= money($job['parts_subtotal']) ?></span></div><div><span>Labor subtotal</span><span><?= money($job['labor_subtotal']) ?></span></div>
 <div><span>Total before discount</span><span><?= money($job['parts_subtotal'] + $job['labor_subtotal']) ?></span></div><div><span>Discount</span><span>- <?= money($job['discount']) ?></span></div>
 <div class="grand"><span>Amount due</span><span><?= money($job['total_due']) ?></span></div><div><span>Paid</span><span><?= money($job['amount_paid']) ?></span></div><div class="grand"><span>Balance</span><span><?= money($balance) ?></span></div></div>
 <?php if (!job_locked($job)): ?>
 <form method="post" class="toolbar mt no-print"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>"><input type="hidden" name="action" value="status">
  <select name="status"><?php foreach (['Pending', 'In Progress', 'Waiting for Parts', 'Completed', 'Released'] as $s): ?><option <?= $job['status'] === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?></select><button class="btn btn-primary btn-sm" type="submit">Update status</button></form>
 <form method="post" class="toolbar no-print" data-confirm="Cancel this job? Parts used will be returned to stock."><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>"><input type="hidden" name="action" value="cancel">
  <input type="text" name="reason" placeholder="Reason for cancelling" required><button class="btn btn-danger btn-sm" type="submit">Cancel job</button></form>
 <?php endif; ?></div></div>
<div class="grid-2 mt">
<div class="card"><h2 class="card-title">Parts used</h2><div class="table-wrap"><table class="table"><thead><tr><th>Part</th><th>Used by</th><th class="num">Qty</th><th class="num">Price</th><th class="num">Subtotal</th></tr></thead><tbody>
<?php foreach ($parts as $p): ?><tr><td><?= e($p['name']) ?></td><td><?= e($p['mechanic_name'] ?: 'Not assigned') ?></td><td class="num"><?= (int)$p['quantity'] ?></td><td class="num"><?= money($p['unit_price']) ?></td><td class="num"><?= money($p['line_subtotal']) ?></td></tr><?php endforeach; if (!$parts): ?><tr><td colspan="5" class="empty">No parts recorded.</td></tr><?php endif; ?></tbody></table></div></div>
<div class="card"><h2 class="card-title">Labor</h2><div class="table-wrap"><table class="table"><thead><tr><th>Service</th><th>Mechanic</th><th>Date</th><th class="num">Charge</th></tr></thead><tbody>
<?php foreach ($labor as $l): ?><tr><td><?= e($l['service_type']) ?><br><small class="muted"><?= e($l['description']) ?></small></td><td><?= e($l['full_name']) ?></td><td><?= e($l['service_date']) ?></td><td class="num"><?= money($l['amount']) ?></td></tr><?php endforeach; if (!$labor): ?><tr><td colspan="4" class="empty">No labor recorded.</td></tr><?php endif; ?></tbody></table></div></div></div>
<div class="card mt"><h2 class="card-title">Payments</h2><div class="table-wrap"><table class="table"><thead><tr><th>Payment no.</th><th>Date</th><th class="num">Applied</th><th class="num">Tendered</th><th class="num">Change</th><th>Status</th><th>By</th></tr></thead><tbody>
<?php foreach ($pays as $p): ?><tr><td><?= e($p['payment_no']) ?></td><td><?= e($p['paid_at']) ?></td><td class="num"><?= money($p['amount_applied']) ?></td><td class="num"><?= money($p['amount_tendered']) ?></td><td class="num"><?= money($p['change_given']) ?></td><td><?= e($p['status']) ?></td><td><?= e($p['full_name']) ?></td></tr><?php endforeach; if (!$pays): ?><tr><td colspan="7" class="empty">No payments yet.</td></tr><?php endif; ?></tbody></table></div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
