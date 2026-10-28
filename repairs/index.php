<?php
require_once __DIR__ . '/../includes/auth.php';
guard();
$q = input('q'); $st = input('status'); $w = []; $p = [];
if ($q !== '') { $w[] = '(j.job_no LIKE ? OR c.full_name LIKE ? OR m.plate_number LIKE ? OR m.model LIKE ?)'; array_push($p, "%$q%", "%$q%", "%$q%", "%$q%"); }
if (in_array($st, ['Pending', 'In Progress', 'Waiting for Parts', 'Completed', 'Released', 'Cancelled'], true)) { $w[] = 'j.status=?'; $p[] = $st; }
$rows = fetch_all('SELECT j.*, c.full_name, m.brand, m.model, m.plate_number, (SELECT GROUP_CONCAT(me.full_name ORDER BY me.full_name SEPARATOR ", ") FROM repair_mechanics rm JOIN mechanics me ON me.id=rm.mechanic_id WHERE rm.repair_job_id=j.id) AS mechanic_names FROM repair_jobs j JOIN customers c ON c.id=j.customer_id JOIN motorcycles m ON m.id=j.motorcycle_id' . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . ' ORDER BY j.id DESC LIMIT 300', $p);
$pageTitle = 'Repair and Service Jobs';
require __DIR__ . '/../includes/header.php';
?>
<form class="toolbar" method="get"><div class="grow"><input type="text" name="q" value="<?= e($q) ?>" placeholder="Search job no., customer, plate or model..."></div>
  <select name="status"><option value="">All statuses</option><?php foreach (['Pending', 'In Progress', 'Waiting for Parts', 'Completed', 'Released', 'Cancelled'] as $s): ?><option <?= $st === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?></select>
  <button class="btn btn-outline" type="submit">Filter</button><a class="btn btn-primary" href="create.php"><i class="fa-solid fa-plus"></i> Quick repair entry</a></form>
<p class="hint">Enter the customer, motorcycle, mechanic, parts used, labor and any payment together from Quick repair entry. Open a job below to review its customer, assigned mechanic, parts and service history.</p>
<div class="card"><div class="table-wrap"><table class="table"><thead><tr><th>Job no.</th><th>Date</th><th>Customer</th><th>Motorcycle</th><th>Mechanic</th><th>Status</th><th class="num">Total</th><th>Payment</th></tr></thead><tbody>
<?php foreach ($rows as $j): ?><tr><td><a href="view.php?id=<?= (int)$j['id'] ?>"><?= e($j['job_no']) ?></a></td><td><?= e($j['date_received']) ?></td><td><?= e($j['full_name']) ?></td><td><?= e($j['brand'] . ' ' . $j['model']) ?><br><small class="muted"><?= e($j['plate_number']) ?></small></td><td><?= e($j['mechanic_names'] ?: 'Not assigned') ?></td><td><?= status_badge($j['status']) ?></td><td class="num"><?= money($j['total_due']) ?></td><td><?= status_badge($j['payment_status']) ?></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="8" class="empty">No repair jobs found.</td></tr><?php endif; ?></tbody></table></div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
