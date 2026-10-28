<?php
require_once __DIR__ . '/../includes/auth.php';
guard();
$jobId = parse_int($_GET['job_id'] ?? $_POST['job_id'] ?? '');
if (!$jobId) {
    $jobs = fetch_all("SELECT j.id, j.job_no, j.total_due, j.amount_paid, j.payment_status, c.full_name FROM repair_jobs j JOIN customers c ON c.id=j.customer_id WHERE j.status<>'Cancelled' AND j.total_due>j.amount_paid ORDER BY j.id DESC");
    $pageTitle = 'Record Payment - Select Job';
    require __DIR__ . '/../includes/header.php'; ?>
<div class="card"><h2 class="card-title">Repair jobs with an outstanding balance</h2><div class="table-wrap"><table class="table"><thead><tr><th>Job</th><th>Customer</th><th class="num">Total</th><th class="num">Balance</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($jobs as $j): ?><tr><td><?= e($j['job_no']) ?></td><td><?= e($j['full_name']) ?></td><td class="num"><?= money($j['total_due']) ?></td><td class="num"><?= money($j['total_due'] - $j['amount_paid']) ?></td><td><?= status_badge($j['payment_status']) ?></td><td><a class="btn btn-sm btn-primary" href="?job_id=<?= (int)$j['id'] ?>">Receive payment</a></td></tr>
<?php endforeach; if (!$jobs): ?><tr><td colspan="6" class="empty">No outstanding balances.</td></tr><?php endif; ?></tbody></table></div></div>
<?php require __DIR__ . '/../includes/footer.php'; exit; }

$job = fetch_row('SELECT j.*, c.full_name FROM repair_jobs j JOIN customers c ON c.id=j.customer_id WHERE j.id=?', [$jobId]);
if (!$job) { flash('error', 'Repair job not found.'); redirect('payments/index.php'); }
$errors = [];
if (is_post()) {
    require_csrf();
    $tendered = parse_money($_POST['tendered'] ?? '', false);
    if (!consume_form_token('payment')) $errors[] = 'This payment form was already submitted. Check the payment list before trying again.';
    if ($tendered === null) $errors[] = 'Enter a valid amount received (greater than zero).';
    if (!$errors) {
        try {
            $res = in_transaction(function (PDO $pdo) use ($jobId, $tendered) {
                $pdo->prepare('SELECT id FROM repair_jobs WHERE id=? FOR UPDATE')->execute([$jobId]);
                recalc_job($pdo, $jobId);
                $s = $pdo->prepare('SELECT status, total_due, amount_paid, job_no FROM repair_jobs WHERE id=?'); $s->execute([$jobId]); $j = $s->fetch();
                if ($j['status'] === 'Cancelled') throw new RuntimeException('This job is cancelled.');
                $balance = round((float)$j['total_due'] - (float)$j['amount_paid'], 2);
                if ($balance <= 0) throw new RuntimeException('This job has no outstanding balance.');
                $applied = min($tendered, $balance); $change = round($tendered - $applied, 2);
                $payNo = next_number('PAY', 'payments', 'payment_no', 'paid_at');
                $pdo->prepare("INSERT INTO payments (payment_no,payable_type,repair_job_id,amount_due,amount_tendered,amount_applied,change_given,received_by,request_token) VALUES (?,'repair',?,?,?,?,?,?,?)")
                    ->execute([$payNo, $jobId, $balance, $tendered, $applied, $change, current_user()['id'], bin2hex(random_bytes(16))]);
                recalc_job($pdo, $jobId);
                log_activity('payment_recorded', 'payments', "$payNo " . money($applied) . " for {$j['job_no']}");
                return [$applied, $change, $balance - $applied];
            });
            flash('success', 'Payment recorded: ' . money($res[0]) . ($res[1] > 0 ? '. Change: ' . money($res[1]) : '') . ($res[2] > 0 ? '. Remaining balance: ' . money($res[2]) : '. Fully paid.'));
            redirect('repairs/view.php?id=' . $jobId);
        } catch (Throwable $ex) { $errors[] = $ex->getMessage(); }
    }
}
$balance = max(0, round((float)$job['total_due'] - (float)$job['amount_paid'], 2));
$pageTitle = 'Record Payment - ' . $job['job_no'];
require __DIR__ . '/../includes/header.php';
?>
<div class="card narrow"><h2 class="card-title"><?= e($job['job_no']) ?> &middot; <?= e($job['full_name']) ?> <?= status_badge($job['payment_status']) ?></h2>
<?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
<div class="totals" style="margin:0 0 14px"><div><span>Total due</span><span><?= money($job['total_due']) ?></span></div><div><span>Already paid</span><span><?= money($job['amount_paid']) ?></span></div><div class="grand"><span>Balance</span><span id="bal" data-v="<?= e($balance) ?>"><?= money($balance) ?></span></div></div>
<form method="post"><?= csrf_field() ?><?= form_token_field('payment') ?><input type="hidden" name="job_id" value="<?= (int)$jobId ?>">
 <label class="field"><span>Cash received *</span><input type="number" step="0.01" min="0.01" name="tendered" id="cash" required autofocus></label>
 <p>Change: <strong id="chg">&#8369;0.00</strong> <small class="hint">(a smaller amount is recorded as a partial payment)</small></p>
 <button class="btn btn-primary" type="submit" <?= $balance <= 0 ? 'disabled' : '' ?>><i class="fa-solid fa-check"></i> Record payment</button> <a class="btn btn-outline" href="../repairs/view.php?id=<?= (int)$jobId ?>">Back to job</a></form></div>
<script>const b=parseFloat(document.getElementById('bal').dataset.v);document.getElementById('cash').addEventListener('input',e=>{const c=(parseFloat(e.target.value)||0)-b;document.getElementById('chg').textContent='\u20b1'+(c>0?c:0).toFixed(2);});</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
