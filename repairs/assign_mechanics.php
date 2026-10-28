<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/_helpers.php';
guard();
$id = parse_int($_GET['job_id'] ?? $_POST['job_id'] ?? '');
$job = load_job($id);
if (is_post()) {
    require_csrf();
    if (job_locked($job)) { flash('error', 'Released or cancelled jobs cannot be changed.'); redirect('repairs/view.php?id=' . $id); }
    $sel = array_unique(array_filter(array_map('parse_int', (array)($_POST['mechanics'] ?? []))));
    $msg = [];
    in_transaction(function (PDO $pdo) use ($id, $sel, &$msg) {
        $cur = $pdo->prepare('SELECT mechanic_id FROM repair_mechanics WHERE repair_job_id=?'); $cur->execute([$id]);
        $have = array_map('intval', $cur->fetchAll(PDO::FETCH_COLUMN));
        foreach (array_diff($have, $sel) as $m) {
            $c = $pdo->prepare("SELECT COUNT(*) FROM labor_entries WHERE repair_job_id=? AND mechanic_id=? AND status='active'"); $c->execute([$id, $m]);
            if ($c->fetchColumn() > 0) { $msg[] = 'A mechanic with labor entries on this job was kept assigned.'; continue; }
            $pdo->prepare('DELETE FROM repair_mechanics WHERE repair_job_id=? AND mechanic_id=?')->execute([$id, $m]);
        }
        foreach (array_diff($sel, $have) as $m) {
            $ok = $pdo->prepare("SELECT id FROM mechanics WHERE id=? AND employment_status='active'"); $ok->execute([$m]);
            if ($ok->fetch()) $pdo->prepare('INSERT INTO repair_mechanics (repair_job_id, mechanic_id) VALUES (?,?)')->execute([$id, $m]);
        }
    });
    log_activity('mechanics_assigned', 'repairs', "Updated mechanics for {$job['job_no']}");
    flash($msg ? 'warning' : 'success', $msg ? implode(' ', array_unique($msg)) : 'Mechanic assignments saved.');
    redirect('repairs/assign_mechanics.php?job_id=' . $id);
}
$all = fetch_all("SELECT id, full_name, specialty FROM mechanics WHERE employment_status='active' ORDER BY full_name");
$assigned = array_map('intval', array_column(fetch_all('SELECT mechanic_id FROM repair_mechanics WHERE repair_job_id=?', [$id]), 'mechanic_id'));
$pageTitle = 'Assign Mechanics - ' . $job['job_no'];
require __DIR__ . '/../includes/header.php';
echo job_tabs($id, 'assign_mechanics');
?>
<div class="card narrow"><form method="post"><?= csrf_field() ?><input type="hidden" name="job_id" value="<?= (int)$id ?>">
<?php foreach ($all as $m): ?><label style="display:block;padding:6px 0"><input type="checkbox" name="mechanics[]" value="<?= (int)$m['id'] ?>" <?= in_array((int)$m['id'], $assigned, true) ? 'checked' : '' ?>> <strong><?= e($m['full_name']) ?></strong> <small class="muted"><?= e($m['specialty']) ?></small></label><?php endforeach; ?>
<?php if (!$all): ?><p class="empty">No active mechanics. <a href="../mechanics/create.php">Add one</a>.</p><?php endif; ?>
<button class="btn btn-primary mt" type="submit" <?= job_locked($job) ? 'disabled' : '' ?>>Save assignments</button></form></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
