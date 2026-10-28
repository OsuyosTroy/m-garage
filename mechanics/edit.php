<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/_form.php';
guard();
$id = parse_int($_GET['id'] ?? $_POST['id'] ?? '');
$m = $id ? fetch_row('SELECT * FROM mechanics WHERE id=?', [$id]) : null;
if (!$m) { flash('error', 'Mechanic not found.'); redirect('mechanics/index.php'); }
$errors = [];
if (is_post()) {
    require_csrf();
    [$d, $errors] = mechanic_validate();
    if (!$errors) {
        db()->prepare('UPDATE mechanics SET full_name=?, contact_number=?, specialty=?, employment_status=?, notes=? WHERE id=?')->execute([$d['full_name'], $d['contact_number'] ?: null, $d['specialty'] ?: null, $d['status'], $d['notes'] ?: null, $id]);
        log_activity('mechanic_updated', 'mechanics', $d['full_name']); flash('success', 'Mechanic updated.'); redirect('mechanics/index.php');
    }
}
$pageTitle = 'Edit Mechanic';
require __DIR__ . '/../includes/header.php';
?>
<div class="card" style="max-width:760px"><?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
<form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>"><?php mechanic_form(is_post() ? $_POST : $m); ?><button class="btn btn-primary" type="submit">Update</button> <a class="btn btn-outline" href="index.php">Cancel</a></form></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
