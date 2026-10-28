<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/_helpers.php';
guard();
$id = parse_int($_GET['job_id'] ?? $_POST['job_id'] ?? '');
$job = load_job($id);
$errors = [];
if (is_post()) {
    require_csrf();
    $act = input('action');
    if (job_locked($job)) $errors[] = 'Labor cannot be changed on a released or cancelled job.';
    elseif ($act === 'remove') {
        $lid = parse_int($_POST['labor_id'] ?? '');
        if ($lid) {
            in_transaction(function (PDO $pdo) use ($lid, $id) { $pdo->prepare("UPDATE labor_entries SET status='removed' WHERE id=? AND repair_job_id=?")->execute([$lid, $id]); recalc_job($pdo, $id); });
            log_activity('labor_removed', 'repairs', "Removed labor #$lid from {$job['job_no']}"); flash('success', 'Labor entry removed.'); redirect('repairs/labor.php?job_id=' . $id);
        }
    } else {
        $mid = parse_int($_POST['mechanic_id'] ?? ''); $type = input('service_type'); $desc = input('description'); $date = input('service_date'); $amt = parse_money($_POST['amount'] ?? '', false);
        $token = consume_form_token('labor');
        if (!$token) $errors[] = 'This form was already submitted. Check the list below.';
        if (!$mid || !fetch_row('SELECT 1 FROM repair_mechanics WHERE repair_job_id=? AND mechanic_id=?', [$id, $mid])) $errors[] = 'Choose a mechanic assigned to this job (assign mechanics first).';
        if ($type === '' || mb_strlen($type) > 120) $errors[] = 'Type of service is required.';
        if (!valid_date($date)) $errors[] = 'Enter a valid service date.';
        if ($amt === null) $errors[] = 'Labor charge must be greater than zero.';
        if (!$errors) {
            try {
                in_transaction(function (PDO $pdo) use ($id, $mid, $type, $desc, $date, $amt, $token) {
                    $pdo->prepare('INSERT INTO labor_entries (repair_job_id,mechanic_id,service_type,description,service_date,amount,request_token,created_by) VALUES (?,?,?,?,?,?,?,?)')
                        ->execute([$id, $mid, $type, $desc ?: null, $date, $amt, $token, current_user()['id']]);
                    recalc_job($pdo, $id);
                });
                log_activity('labor_added', 'repairs', "Labor '$type' " . money($amt) . " on {$job['job_no']}"); flash('success', 'Labor entry added.'); redirect('repairs/labor.php?job_id=' . $id);
            } catch (Throwable $ex) { $errors[] = 'Could not save: ' . (APP_DEBUG ? $ex->getMessage() : 'database error'); }
        }
    }
}
$assigned = fetch_all('SELECT m.id, m.full_name FROM repair_mechanics rm JOIN mechanics m ON m.id=rm.mechanic_id WHERE rm.repair_job_id=?', [$id]);
$labor = fetch_all("SELECT l.*, m.full_name FROM labor_entries l JOIN mechanics m ON m.id=l.mechanic_id WHERE l.repair_job_id=? AND l.status='active' ORDER BY l.id", [$id]);
$total = array_sum(array_column($labor, 'amount'));
$pageTitle = 'Labor - ' . $job['job_no'];
require __DIR__ . '/../includes/header.php';
echo job_tabs($id, 'labor');
?>
<div class="grid-2">
<div class="card"><h2 class="card-title">Add labor charge</h2><?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
<?php if (!$assigned): ?><div class="alert alert-warning">No mechanic is assigned. <a href="assign_mechanics.php?job_id=<?= (int)$id ?>">Assign mechanics</a> first.</div><?php endif; ?>
<form method="post"><?= csrf_field() ?><?= form_token_field('labor') ?><input type="hidden" name="job_id" value="<?= (int)$id ?>">
 <label class="field"><span>Mechanic *</span><select name="mechanic_id" required><option value="">Select...</option><?php foreach ($assigned as $m): ?><option value="<?= (int)$m['id'] ?>"><?= e($m['full_name']) ?></option><?php endforeach; ?></select></label>
 <label class="field"><span>Type of service *</span><input type="text" name="service_type" value="<?= old('service_type') ?>" required placeholder="e.g. Tune-up, Brake replacement"></label>
 <label class="field"><span>Description</span><input type="text" name="description" value="<?= old('description') ?>"></label>
 <label class="field"><span>Service date *</span><input type="date" name="service_date" value="<?= old('service_date', date('Y-m-d')) ?>" required></label>
 <label class="field"><span>Labor charge *</span><input type="number" step="0.01" min="0.01" name="amount" value="<?= old('amount') ?>" required></label>
 <button class="btn btn-primary" type="submit" <?= job_locked($job) ? 'disabled' : '' ?>>Add labor</button></form></div>
<div class="card"><h2 class="card-title">Labor entries</h2><div class="table-wrap"><table class="table"><thead><tr><th>Service</th><th>Mechanic</th><th class="num">Charge</th><th></th></tr></thead><tbody>
<?php foreach ($labor as $l): ?><tr><td><?= e($l['service_type']) ?><br><small class="muted"><?= e($l['service_date']) ?> <?= e($l['description']) ?></small></td><td><?= e($l['full_name']) ?></td><td class="num"><?= money($l['amount']) ?></td>
<td><?php if (!job_locked($job)): ?><form method="post" data-confirm="Remove this labor entry?"><?= csrf_field() ?><input type="hidden" name="job_id" value="<?= (int)$id ?>"><input type="hidden" name="action" value="remove"><input type="hidden" name="labor_id" value="<?= (int)$l['id'] ?>"><button class="btn btn-sm btn-outline" type="submit"><i class="fa-solid fa-trash"></i></button></form><?php endif; ?></td></tr>
<?php endforeach; if (!$labor): ?><tr><td colspan="4" class="empty">No labor entries.</td></tr><?php endif; ?>
<tr><td colspan="2" class="num"><strong>Labor subtotal</strong></td><td class="num"><strong><?= money($total) ?></strong></td><td></td></tr></tbody></table></div></div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
