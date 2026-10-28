<?php
require_once __DIR__ . '/../includes/auth.php';
guard();
if (is_post()) {
    require_csrf();
    if (input('action') === 'toggle' && ($id = parse_int($_POST['id'] ?? ''))) {
        db()->prepare('UPDATE customers SET is_active = 1 - is_active WHERE id=?')->execute([$id]);
        log_activity('customer_toggled', 'customers', "Toggled customer #$id"); flash('success', 'Customer status changed.');
    }
    redirect('customers/index.php');
}
$q = input('q'); $p = []; $w = '';
if ($q !== '') { $w = 'WHERE c.full_name LIKE ? OR c.contact_number LIKE ?'; $p = ["%$q%", "%$q%"]; }
$rows = fetch_all("SELECT c.*, (SELECT COUNT(*) FROM motorcycles m WHERE m.customer_id=c.id AND m.is_active=1) AS motos, (SELECT COUNT(*) FROM repair_jobs j WHERE j.customer_id=c.id) AS jobs FROM customers c $w ORDER BY c.full_name LIMIT 300", $p);
$pageTitle = 'Customers';
require __DIR__ . '/../includes/header.php';
?>
<div class="toolbar tabs" role="navigation" aria-label="Customer records">
  <a class="btn btn-sm btn-primary" href="index.php"><i class="fa-solid fa-users"></i> Customers</a>
  <a class="btn btn-sm btn-outline" href="motorcycles.php"><i class="fa-solid fa-motorcycle"></i> Motorcycles</a>
</div>
<form class="toolbar" method="get"><div class="grow"><input type="text" name="q" value="<?= e($q) ?>" placeholder="Search by name or contact number..."></div>
  <button class="btn btn-outline" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Search</button><a class="btn btn-primary" href="create.php"><i class="fa-solid fa-user-plus"></i> Register customer</a></form>
<div class="card"><div class="table-wrap"><table class="table"><thead><tr><th>Name</th><th>Contact</th><th>Address</th><th class="num">Motorcycles</th><th class="num">Repairs</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $c): ?><tr><td><a href="history.php?customer_id=<?= (int)$c['id'] ?>"><?= e($c['full_name']) ?></a></td><td><?= e($c['contact_number']) ?></td><td><?= e($c['address']) ?></td><td class="num"><?= (int)$c['motos'] ?></td><td class="num"><?= (int)$c['jobs'] ?></td>
<td><?= $c['is_active'] ? '<span class="badge b-ok">Active</span>' : '<span class="badge b-out">Inactive</span>' ?></td>
<td class="actions"><a class="btn btn-sm btn-outline" title="Motorcycles" href="motorcycles.php?customer_id=<?= (int)$c['id'] ?>"><i class="fa-solid fa-motorcycle"></i></a><a class="btn btn-sm btn-outline" title="Edit" href="edit.php?id=<?= (int)$c['id'] ?>"><i class="fa-solid fa-pen"></i></a>
<form method="post" data-confirm="Change status of this customer?"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn btn-sm btn-outline" type="submit"><i class="fa-solid <?= $c['is_active'] ? 'fa-eye-slash' : 'fa-eye' ?>"></i></button></form></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="7" class="empty">No customers found.</td></tr><?php endif; ?></tbody></table></div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
