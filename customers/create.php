<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/_form.php';
guard();
$errors = [];
if (is_post()) {
    require_csrf();
    [$d, $errors] = customer_validate();
    if (!$errors) {
        db()->prepare('INSERT INTO customers (full_name, contact_number, address, notes) VALUES (?,?,?,?)')->execute([$d['full_name'], $d['contact_number'] ?: null, $d['address'] ?: null, $d['notes'] ?: null]);
        $id = (int)db()->lastInsertId();
        log_activity('customer_created', 'customers', $d['full_name']);
        flash('success', 'Customer registered. You can now add their motorcycle.');
        redirect('customers/motorcycles.php?customer_id=' . $id);
    }
}
$pageTitle = 'Register Customer';
require __DIR__ . '/../includes/header.php';
?>
<div class="card narrow" style="max-width:760px">
  <?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
  <form method="post"><?= csrf_field() ?><?php customer_form($_POST); ?>
    <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Save customer</button> <a class="btn btn-outline" href="index.php">Cancel</a></form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
