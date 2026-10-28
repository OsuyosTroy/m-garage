<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/_form.php';
guard();
$id = parse_int($_GET['id'] ?? $_POST['id'] ?? '');
$c = $id ? fetch_row('SELECT * FROM customers WHERE id=?', [$id]) : null;
if (!$c) { flash('error', 'Customer not found.'); redirect('customers/index.php'); }
$errors = [];
if (is_post()) {
    require_csrf();
    [$d, $errors] = customer_validate();
    if (!$errors) {
        db()->prepare('UPDATE customers SET full_name=?, contact_number=?, address=?, notes=? WHERE id=?')->execute([$d['full_name'], $d['contact_number'] ?: null, $d['address'] ?: null, $d['notes'] ?: null, $id]);
        log_activity('customer_updated', 'customers', $d['full_name']);
        flash('success', 'Customer updated.'); redirect('customers/index.php');
    }
}
$pageTitle = 'Edit Customer';
require __DIR__ . '/../includes/header.php';
?>
<div class="card narrow" style="max-width:760px">
  <?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>"><?php customer_form(is_post() ? $_POST : $c); ?>
    <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Update</button> <a class="btn btn-outline" href="index.php">Cancel</a></form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
