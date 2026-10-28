<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/_form.php';
guard();
$errors = [];
if (is_post()) {
    require_csrf();
    [$d, $errors] = mechanic_validate();
    if (!$errors) {
        db()->prepare('INSERT INTO mechanics (full_name, contact_number, specialty, employment_status, notes) VALUES (?,?,?,?,?)')->execute([$d['full_name'], $d['contact_number'] ?: null, $d['specialty'] ?: null, $d['status'], $d['notes'] ?: null]);
        log_activity('mechanic_created', 'mechanics', $d['full_name']); flash('success', 'Mechanic added.'); redirect('mechanics/index.php');
    }
}
$pageTitle = 'Add Mechanic';
require __DIR__ . '/../includes/header.php';
?>
<div class="card" style="max-width:760px"><?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
<p class="hint">Mechanics are personnel records only. They do not log in to the system.</p>
<form method="post"><?= csrf_field() ?><?php mechanic_form($_POST); ?><button class="btn btn-primary" type="submit">Save mechanic</button> <a class="btn btn-outline" href="index.php">Cancel</a></form></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
