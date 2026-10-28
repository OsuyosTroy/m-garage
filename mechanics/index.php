<?php
require_once __DIR__ . '/../includes/auth.php';
guard();
if (is_post()) {
    require_csrf();
    if (input('action') === 'toggle' && ($id = parse_int($_POST['id'] ?? ''))) {
        db()->prepare("UPDATE mechanics SET employment_status = IF(employment_status='active','inactive','active') WHERE id=?")->execute([$id]);
        log_activity('mechanic_toggled', 'mechanics', "Toggled mechanic #$id"); flash('success', 'Mechanic status changed.');
    }
    redirect('mechanics/index.php');
}
$rows = fetch_all("SELECT m.*, (SELECT COUNT(*) FROM repair_mechanics rm WHERE rm.mechanic_id=m.id) AS jobs, (SELECT COALESCE(SUM(l.amount),0) FROM labor_entries l WHERE l.mechanic_id=m.id AND l.status='active') AS labor FROM mechanics m ORDER BY m.full_name");
$viewId = parse_int($_GET['view'] ?? '');
$assigned = $viewId ? fetch_all('SELECT j.id, j.job_no, j.status, j.date_received, c.full_name FROM repair_mechanics rm JOIN repair_jobs j ON j.id=rm.repair_job_id JOIN customers c ON c.id=j.customer_id WHERE rm.mechanic_id=? ORDER BY j.id DESC', [$viewId]) : [];
$pageTitle = 'Mechanics and Labor';
require __DIR__ . '/../includes/header.php';
?>
<div class="toolbar"><a class="btn btn-primary" href="create.php"><i class="fa-solid fa-plus"></i> Add mechanic</a><a class="btn btn-outline" href="../reports/labor.php"><i class="fa-solid fa-chart-line"></i> Labor report</a>
<span class="hint">Labor charges are entered per repair job (Repair and Service Jobs &rarr; Labor).</span></div>
<div class="card"><div class="table-wrap"><table class="table"><thead><tr><th>Name</th><th>Specialty</th><th>Contact</th><th class="num">Assigned jobs</th><th class="num">Labor charged</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $m): ?><tr><td><?= e($m['full_name']) ?></td><td><?= e($m['specialty']) ?></td><td><?= e($m['contact_number']) ?></td><td class="num"><a href="?view=<?= (int)$m['id'] ?>"><?= (int)$m['jobs'] ?></a></td><td class="num"><?= money($m['labor']) ?></td>
<td><?= $m['employment_status'] === 'active' ? '<span class="badge b-ok">Active</span>' : '<span class="badge b-out">Inactive</span>' ?></td>
<td class="actions"><a class="btn btn-sm btn-outline" href="edit.php?id=<?= (int)$m['id'] ?>"><i class="fa-solid fa-pen"></i></a>
<form method="post" data-confirm="Change status of this mechanic?"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button class="btn btn-sm btn-outline" type="submit"><i class="fa-solid fa-user-slash"></i></button></form></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="7" class="empty">No mechanics yet.</td></tr><?php endif; ?></tbody></table></div></div>
<?php if ($viewId): ?><div class="card mt"><h2 class="card-title">Assigned jobs</h2><div class="table-wrap"><table class="table"><thead><tr><th>Job</th><th>Customer</th><th>Date</th><th>Status</th></tr></thead><tbody>
<?php foreach ($assigned as $a): ?><tr><td><a href="../repairs/view.php?id=<?= (int)$a['id'] ?>"><?= e($a['job_no']) ?></a></td><td><?= e($a['full_name']) ?></td><td><?= e($a['date_received']) ?></td><td><?= status_badge($a['status']) ?></td></tr>
<?php endforeach; if (!$assigned): ?><tr><td colspan="4" class="empty">No assigned jobs.</td></tr><?php endif; ?></tbody></table></div></div><?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
