<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/_filter.php';
guard();
[$from, $to] = report_range();
$by = fetch_all("SELECT m.full_name, COUNT(l.id) AS n, COUNT(DISTINCT l.repair_job_id) AS jobs, SUM(l.amount) AS total FROM labor_entries l JOIN mechanics m ON m.id=l.mechanic_id JOIN repair_jobs j ON j.id=l.repair_job_id WHERE l.status='active' AND j.status<>'Cancelled' AND l.service_date BETWEEN ? AND ? GROUP BY m.id ORDER BY total DESC", [$from, $to]);
$detail = fetch_all("SELECT l.*, m.full_name, j.job_no, j.id AS jid FROM labor_entries l JOIN mechanics m ON m.id=l.mechanic_id JOIN repair_jobs j ON j.id=l.repair_job_id WHERE l.status='active' AND j.status<>'Cancelled' AND l.service_date BETWEEN ? AND ? ORDER BY l.service_date DESC, l.id DESC", [$from, $to]);
$pageTitle = 'Labor Charges by Mechanic';
require __DIR__ . '/../includes/header.php';
report_filter_form($from, $to);
?>
<div class="card"><p class="hint">Labor charges billed to customers. These are not wages or salary.</p><div class="table-wrap"><table class="table"><thead><tr><th>Mechanic</th><th class="num">Labor entries</th><th class="num">Jobs</th><th class="num">Total charged</th></tr></thead><tbody>
<?php $gt = 0; foreach ($by as $b): $gt += $b['total']; ?><tr><td><?= e($b['full_name']) ?></td><td class="num"><?= (int)$b['n'] ?></td><td class="num"><?= (int)$b['jobs'] ?></td><td class="num"><?= money($b['total']) ?></td></tr><?php endforeach; if (!$by): ?><tr><td colspan="4" class="empty">No labor entries in this period.</td></tr><?php endif; ?>
<tr><td colspan="3" class="num"><strong>Total</strong></td><td class="num"><strong><?= money($gt) ?></strong></td></tr></tbody></table></div></div>
<div class="card mt"><h2 class="card-title">Detail</h2><div class="table-wrap"><table class="table"><thead><tr><th>Date</th><th>Job</th><th>Mechanic</th><th>Service</th><th class="num">Charge</th></tr></thead><tbody>
<?php foreach ($detail as $d): ?><tr><td><?= e($d['service_date']) ?></td><td><a href="../repairs/view.php?id=<?= (int)$d['jid'] ?>"><?= e($d['job_no']) ?></a></td><td><?= e($d['full_name']) ?></td><td><?= e($d['service_type']) ?></td><td class="num"><?= money($d['amount']) ?></td></tr><?php endforeach; ?></tbody></table></div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
