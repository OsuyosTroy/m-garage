<?php
require_once __DIR__ . '/../includes/auth.php';
guard();
$errors = [];
$custFilter = parse_int($_GET['customer_id'] ?? $_POST['customer_filter'] ?? '');
if (is_post()) {
    require_csrf();
    $act = input('action'); $id = parse_int($_POST['id'] ?? '');
    if ($act === 'toggle' && $id) {
        db()->prepare('UPDATE motorcycles SET is_active = 1 - is_active WHERE id=?')->execute([$id]);
        log_activity('motorcycle_toggled', 'customers', "Toggled motorcycle #$id"); flash('success', 'Motorcycle status changed.');
        redirect('customers/motorcycles.php' . ($custFilter ? "?customer_id=$custFilter" : ''));
    }
    $cid = parse_int($_POST['customer_id'] ?? '');
    $d = ['brand' => input('brand'), 'model' => input('model'), 'plate' => strtoupper(input('plate_number')), 'eng' => input('engine_chassis_no'), 'year' => input('year_model'), 'notes' => input('notes')];
    if (!$cid || !fetch_row('SELECT id FROM customers WHERE id=?', [$cid])) $errors[] = 'Select the owner (customer).';
    if ($d['brand'] === '' || $d['model'] === '') $errors[] = 'Brand and model are required.';
    if ($d['year'] !== '' && !preg_match('/^(19|20)\d{2}$/', $d['year'])) $errors[] = 'Year must be a 4-digit year.';
    if (!$errors) {
        $vals = [$cid, $d['brand'], $d['model'], $d['plate'] ?: null, $d['eng'] ?: null, $d['year'] ?: null, $d['notes'] ?: null];
        if ($act === 'update' && $id) { db()->prepare('UPDATE motorcycles SET customer_id=?,brand=?,model=?,plate_number=?,engine_chassis_no=?,year_model=?,notes=? WHERE id=?')->execute([...$vals, $id]); log_activity('motorcycle_updated', 'customers', "{$d['brand']} {$d['model']}"); }
        else { db()->prepare('INSERT INTO motorcycles (customer_id,brand,model,plate_number,engine_chassis_no,year_model,notes) VALUES (?,?,?,?,?,?,?)')->execute($vals); log_activity('motorcycle_created', 'customers', "{$d['brand']} {$d['model']} {$d['plate']}"); }
        flash('success', 'Motorcycle saved.'); redirect('customers/motorcycles.php?customer_id=' . $cid);
    }
}
$edit = ($eid = parse_int($_GET['edit'] ?? '')) ? fetch_row('SELECT * FROM motorcycles WHERE id=?', [$eid]) : null;
$q = input('q'); $w = []; $p = [];
if ($custFilter) { $w[] = 'm.customer_id=?'; $p[] = $custFilter; }
if ($q !== '') { $w[] = '(m.plate_number LIKE ? OR m.model LIKE ? OR m.brand LIKE ? OR c.full_name LIKE ?)'; array_push($p, "%$q%", "%$q%", "%$q%", "%$q%"); }
$rows = fetch_all('SELECT m.*, c.full_name FROM motorcycles m JOIN customers c ON c.id=m.customer_id' . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . ' ORDER BY c.full_name, m.model LIMIT 300', $p);
$customers = fetch_all('SELECT id, full_name FROM customers WHERE is_active=1 ORDER BY full_name');
$v = fn($k, $dk = null) => e($_POST[$k] ?? $edit[$dk ?? $k] ?? '');
$sel = $_POST['customer_id'] ?? $edit['customer_id'] ?? $custFilter;
$pageTitle = 'Motorcycles';
require __DIR__ . '/../includes/header.php';
?>
<div class="toolbar tabs" role="navigation" aria-label="Customer records">
  <a class="btn btn-sm btn-outline" href="index.php"><i class="fa-solid fa-users"></i> Customers</a>
  <a class="btn btn-sm btn-primary" href="motorcycles.php"><i class="fa-solid fa-motorcycle"></i> Motorcycles</a>
</div>
<div class="card"><h2 class="card-title"><i class="fa-solid fa-motorcycle"></i> <?= $edit ? 'Edit motorcycle' : 'Register motorcycle' ?></h2>
  <?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="<?= $edit ? 'update' : 'create' ?>"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>"><input type="hidden" name="customer_filter" value="<?= (int)$custFilter ?>">
  <div class="form-grid">
    <label class="field"><span>Owner *</span><select name="customer_id" required><option value="">Select customer...</option><?php foreach ($customers as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (string)$sel === (string)$c['id'] ? 'selected' : '' ?>><?= e($c['full_name']) ?></option><?php endforeach; ?></select></label>
    <label class="field"><span>Brand *</span><input type="text" name="brand" value="<?= $v('brand') ?>" required></label>
    <label class="field"><span>Model *</span><input type="text" name="model" value="<?= $v('model') ?>" required></label>
    <label class="field"><span>Plate number</span><input type="text" name="plate_number" value="<?= $v('plate_number') ?>"></label>
    <label class="field"><span>Engine / chassis no.</span><input type="text" name="engine_chassis_no" value="<?= $v('engine_chassis_no') ?>"></label>
    <label class="field"><span>Year</span><input type="text" name="year_model" value="<?= $v('year_model') ?>" maxlength="4"></label>
    <label class="field full"><span>Notes</span><input type="text" name="notes" value="<?= $v('notes') ?>"></label></div>
  <button class="btn btn-primary" type="submit">Save motorcycle</button> <?php if ($edit): ?><a class="btn btn-outline" href="motorcycles.php">Cancel</a><?php endif; ?></form></div>
<form class="toolbar mt" method="get"><input type="hidden" name="customer_id" value="<?= (int)$custFilter ?>"><div class="grow"><input type="text" name="q" value="<?= e($q) ?>" placeholder="Search plate number, model, brand or owner..."></div>
  <button class="btn btn-outline" type="submit">Search</button><?php if ($custFilter): ?><a class="btn btn-outline" href="motorcycles.php">Show all</a><?php endif; ?></form>
<div class="card"><div class="table-wrap"><table class="table"><thead><tr><th>Owner</th><th>Motorcycle</th><th>Plate</th><th>Year</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $m): ?><tr><td><?= e($m['full_name']) ?></td><td><?= e($m['brand'] . ' ' . $m['model']) ?></td><td><?= e($m['plate_number']) ?></td><td><?= e($m['year_model']) ?></td><td><?= $m['is_active'] ? '<span class="badge b-ok">Active</span>' : '<span class="badge b-out">Inactive</span>' ?></td>
<td class="actions"><a class="btn btn-sm btn-outline" title="Service history" href="history.php?moto_id=<?= (int)$m['id'] ?>"><i class="fa-solid fa-clock-rotate-left"></i></a><a class="btn btn-sm btn-outline" href="?edit=<?= (int)$m['id'] ?>&customer_id=<?= (int)$custFilter ?>"><i class="fa-solid fa-pen"></i></a>
<form method="post" data-confirm="Change status of this motorcycle?"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><input type="hidden" name="customer_filter" value="<?= (int)$custFilter ?>"><button class="btn btn-sm btn-outline" type="submit"><i class="fa-solid <?= $m['is_active'] ? 'fa-eye-slash' : 'fa-eye' ?>"></i></button></form></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="6" class="empty">No motorcycles found.</td></tr><?php endif; ?></tbody></table></div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
