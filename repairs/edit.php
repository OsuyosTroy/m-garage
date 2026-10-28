<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/_helpers.php';
guard();
$id = parse_int($_GET['id'] ?? $_POST['id'] ?? '');
$job = load_job($id);
$errors = [];
if (is_post()) {
    require_csrf();
    if (job_locked($job)) $errors[] = 'Released or cancelled jobs cannot be edited.';
    $date = input('date_received'); $problem = input('reported_problem'); $disc = parse_money($_POST['discount'] ?? '0');
    if (!valid_date($date)) $errors[] = 'Enter a valid date received.';
    if ($problem === '') $errors[] = 'Reported problem is required.';
    if ($disc === null) $errors[] = 'Discount must be a valid amount.';
    elseif ($disc > (float)$job['parts_subtotal'] + (float)$job['labor_subtotal']) $errors[] = 'Discount cannot exceed the parts and labor total.';
    if (!$errors) {
        in_transaction(function (PDO $pdo) use ($id, $date, $problem, $disc) {
            $pdo->prepare('UPDATE repair_jobs SET date_received=?, reported_problem=?, diagnosis=?, requested_services=?, notes=?, discount=? WHERE id=?')
                ->execute([$date, $problem, input('diagnosis') ?: null, input('requested_services') ?: null, input('notes') ?: null, $disc, $id]);
            recalc_job($pdo, $id);
        });
        log_activity('repair_updated', 'repairs', "Updated {$job['job_no']}"); flash('success', 'Job updated.'); redirect('repairs/view.php?id=' . $id);
    }
}
$v = is_post() ? $_POST : $job;
$pageTitle = 'Edit Job ' . $job['job_no'];
require __DIR__ . '/../includes/header.php';
echo job_tabs($id, 'edit');
?>
<div class="card"><?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
<form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>"><div class="form-grid">
 <label class="field"><span>Date received</span><input type="date" name="date_received" value="<?= e($v['date_received']) ?>" required></label>
 <label class="field"><span>Discount (amount)</span><input type="number" step="0.01" min="0" name="discount" value="<?= e($v['discount']) ?>"></label>
 <label class="field full"><span>Reported problem *</span><textarea name="reported_problem" rows="2" required><?= e($v['reported_problem']) ?></textarea></label>
 <label class="field full"><span>Diagnosis</span><textarea name="diagnosis" rows="2"><?= e($v['diagnosis'] ?? '') ?></textarea></label>
 <label class="field full"><span>Requested services</span><textarea name="requested_services" rows="2"><?= e($v['requested_services'] ?? '') ?></textarea></label>
 <label class="field full"><span>Notes</span><textarea name="notes" rows="2"><?= e($v['notes'] ?? '') ?></textarea></label></div>
 <button class="btn btn-primary" type="submit" <?= job_locked($job) ? 'disabled' : '' ?>>Save changes</button></form></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
