<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
$uid = parse_int($_GET['user'] ?? ''); $mod = input('module'); $from = input('from'); $to = input('to'); $page = max(1, (int)($_GET['page'] ?? 1)); $per = 100;
$w = []; $p = [];
if ($uid) { $w[] = 'l.user_id=?'; $p[] = $uid; }
if ($mod !== '') { $w[] = 'l.module=?'; $p[] = $mod; }
if (valid_date($from)) { $w[] = 'DATE(l.created_at)>=?'; $p[] = $from; }
if (valid_date($to)) { $w[] = 'DATE(l.created_at)<=?'; $p[] = $to; }
$cond = $w ? ' WHERE ' . implode(' AND ', $w) : '';
$total = (int)fetch_row('SELECT COUNT(*) AS n FROM activity_logs l' . $cond, $p)['n'];
$rows = fetch_all('SELECT l.*, u.username FROM activity_logs l LEFT JOIN users u ON u.id=l.user_id' . $cond . ' ORDER BY l.id DESC LIMIT ' . $per . ' OFFSET ' . (($page - 1) * $per), $p);
$mods = array_column(fetch_all('SELECT DISTINCT module FROM activity_logs ORDER BY module'), 'module');
$q = http_build_query(['user' => $uid, 'module' => $mod, 'from' => $from, 'to' => $to]);
$pageTitle = 'Activity Logs';
require __DIR__ . '/../includes/header.php';
?>
<form class="toolbar" method="get"><select name="user"><option value="">All users</option><?php foreach (fetch_all('SELECT id, username FROM users ORDER BY username') as $u): ?><option value="<?= (int)$u['id'] ?>" <?= $uid === (int)$u['id'] ? 'selected' : '' ?>><?= e($u['username']) ?></option><?php endforeach; ?></select>
<select name="module"><option value="">All modules</option><?php foreach ($mods as $m): ?><option <?= $mod === $m ? 'selected' : '' ?>><?= e($m) ?></option><?php endforeach; ?></select><input type="date" name="from" value="<?= e($from) ?>"><input type="date" name="to" value="<?= e($to) ?>"><button class="btn btn-outline" type="submit">Filter</button></form>
<div class="card"><div class="table-wrap"><table class="table"><thead><tr><th>Time</th><th>User</th><th>Module</th><th>Action</th><th>Details</th><th>IP</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td><?= e($r['created_at']) ?></td><td><?= e($r['username'] ?? 'system') ?></td><td><?= e($r['module']) ?></td><td><?= e($r['action']) ?></td><td><?= e($r['description']) ?></td><td><?= e($r['ip_address']) ?></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="6" class="empty">No activity found.</td></tr><?php endif; ?></tbody></table></div>
<p class="mt"><?= $page > 1 ? '<a class="btn btn-sm btn-outline" href="?' . e($q) . '&page=' . ($page - 1) . '">&laquo; Newer</a>' : '' ?> Page <?= $page ?> of <?= max(1, (int)ceil($total / $per)) ?> <?= $page * $per < $total ? '<a class="btn btn-sm btn-outline" href="?' . e($q) . '&page=' . ($page + 1) . '">Older &raquo;</a>' : '' ?></p></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
