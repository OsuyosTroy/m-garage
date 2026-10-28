<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/_helpers.php';
guard();
$id = parse_int($_GET['job_id'] ?? $_POST['job_id'] ?? '');
if (!$id) { // job picker
    $open = fetch_all("SELECT j.id, j.job_no, j.status, c.full_name, m.model FROM repair_jobs j JOIN customers c ON c.id=j.customer_id JOIN motorcycles m ON m.id=j.motorcycle_id WHERE j.status NOT IN ('Released','Cancelled') ORDER BY j.id DESC");
    $pageTitle = 'Parts Used - Select a Repair Job';
    require __DIR__ . '/../includes/header.php'; ?>
<div class="card"><h2 class="card-title">Choose a repair job</h2><div class="table-wrap"><table class="table"><thead><tr><th>Job</th><th>Customer</th><th>Motorcycle</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($open as $o): ?><tr><td><?= e($o['job_no']) ?></td><td><?= e($o['full_name']) ?></td><td><?= e($o['model']) ?></td><td><?= status_badge($o['status']) ?></td><td><a class="btn btn-sm btn-primary" href="?job_id=<?= (int)$o['id'] ?>">Record parts</a></td></tr>
<?php endforeach; if (!$open): ?><tr><td colspan="5" class="empty">No open repair jobs. <a href="create.php">Create one</a>.</td></tr><?php endif; ?></tbody></table></div></div>
<?php require __DIR__ . '/../includes/footer.php'; exit; }

$job = load_job($id);
$errors = [];
if (is_post()) {
    require_csrf();
    $act = input('action'); $uid = current_user()['id'];
    try {
        if ($act === 'add') {
            if (!consume_form_token('parts')) throw new RuntimeException('This form was already submitted. Check the list below before adding again.');
            $token = bin2hex(random_bytes(16));
            $iid = parse_int($_POST['item_id'] ?? ''); $qty = parse_int($_POST['quantity'] ?? ''); $mid = parse_int($_POST['mechanic_id'] ?? '');
            if (!$iid || !$qty || !$mid) throw new RuntimeException('Select a part, mechanic and quantity above zero.');
            in_transaction(function (PDO $pdo) use ($id, $iid, $qty, $mid, $token, $uid) {
                $j = $pdo->prepare('SELECT job_no, status FROM repair_jobs WHERE id=? FOR UPDATE'); $j->execute([$id]); $jr = $j->fetch();
                if (!$jr || in_array($jr['status'], ['Released', 'Cancelled'], true)) throw new RuntimeException('Parts cannot be added to a released or cancelled job.');
                $assigned = $pdo->prepare('SELECT 1 FROM repair_mechanics WHERE repair_job_id=? AND mechanic_id=?'); $assigned->execute([$id, $mid]);
                if (!$assigned->fetchColumn()) throw new RuntimeException('Assign this mechanic to the job first.');
                $it = $pdo->prepare('SELECT selling_price, is_active, name FROM inventory_items WHERE id=?'); $it->execute([$iid]); $item = $it->fetch();
                if (!$item || !$item['is_active']) throw new RuntimeException('That part is not available.');
                $dup = $pdo->prepare("SELECT id FROM repair_parts_used WHERE repair_job_id=? AND item_id=? AND status='active'"); $dup->execute([$id, $iid]);
                if ($dup->fetch()) throw new RuntimeException('"' . $item['name'] . '" is already on this job. Edit its quantity instead of adding it again.');
                $price = (float)$item['selling_price'];
                $pdo->prepare('INSERT INTO repair_parts_used (repair_job_id,item_id,mechanic_id,quantity,unit_price,line_subtotal,request_token,recorded_by) VALUES (?,?,?,?,?,?,?,?)')->execute([$id, $iid, $mid, $qty, $price, round($qty * $price, 2), $token, $uid]);
                stock_change($pdo, $iid, -$qty, 'repair_use', 'repair_job', $id, 'Used in ' . $jr['job_no'], $uid);
                recalc_job($pdo, $id);
            });
            log_activity('part_used', 'repairs', "Added part #$iid x$qty to {$job['job_no']}"); flash('success', 'Part recorded and stock deducted.');
        } else {
            $pid = parse_int($_POST['part_id'] ?? '');
            in_transaction(function (PDO $pdo) use ($act, $id, $pid, $uid, $job) {
                $s = $pdo->prepare("SELECT p.*, j.status AS jstatus, j.job_no FROM repair_parts_used p JOIN repair_jobs j ON j.id=p.repair_job_id WHERE p.id=? AND p.repair_job_id=? AND p.status='active' FOR UPDATE"); $s->execute([$pid, $id]); $p = $s->fetch();
                if (!$p) throw new RuntimeException('Parts entry not found.');
                if ($p['jstatus'] === 'Cancelled') throw new RuntimeException('Job is cancelled.');
                $released = $p['jstatus'] === 'Released';
                if ($act === 'reverse') {
                    $reason = input('reason');
                    if (!$released) throw new RuntimeException('Use Remove for jobs that are not yet released.');
                    if ($reason === '') throw new RuntimeException('A reason is required for a reversal.');
                    stock_change($pdo, (int)$p['item_id'], (int)$p['quantity'], 'reversal', 'repair_job', $id, "Reversal on released {$p['job_no']}: $reason", $uid);
                    $pdo->prepare("UPDATE repair_parts_used SET status='reversed' WHERE id=?")->execute([$pid]);
                } elseif ($released) { throw new RuntimeException('Released jobs can only be changed through a reversal with a reason.');
                } elseif ($act === 'remove') {
                    stock_change($pdo, (int)$p['item_id'], (int)$p['quantity'], 'reversal', 'repair_job', $id, "Part removed from {$p['job_no']}", $uid);
                    $pdo->prepare("UPDATE repair_parts_used SET status='removed' WHERE id=?")->execute([$pid]);
                } elseif ($act === 'update') {
                    $new = parse_int($_POST['quantity'] ?? '');
                    if (!$new) throw new RuntimeException('Quantity must be above zero.');
                    $diff = $new - (int)$p['quantity'];
                    if ($diff > 0) stock_change($pdo, (int)$p['item_id'], -$diff, 'repair_use', 'repair_job', $id, "Quantity increased on {$p['job_no']}", $uid);
                    elseif ($diff < 0) stock_change($pdo, (int)$p['item_id'], -$diff, 'reversal', 'repair_job', $id, "Quantity decreased on {$p['job_no']}", $uid);
                    $pdo->prepare('UPDATE repair_parts_used SET quantity=?, line_subtotal=? WHERE id=?')->execute([$new, round($new * (float)$p['unit_price'], 2), $pid]);
                } else throw new RuntimeException('Unknown action.');
                recalc_job($pdo, $id);
            });
            log_activity('part_' . $act, 'repairs', "Parts entry #$pid ($act) on {$job['job_no']}"); flash('success', 'Parts list updated and stock adjusted.');
        }
    } catch (Throwable $ex) { flash('error', $ex->getMessage()); }
    redirect('repairs/parts_used.php?job_id=' . $id);
}
$job = load_job($id);
$items = fetch_all('SELECT id, sku, name, selling_price, stock_qty, unit FROM inventory_items WHERE is_active=1 ORDER BY name');
$mechanics = fetch_all('SELECT m.id, m.full_name FROM repair_mechanics rm JOIN mechanics m ON m.id=rm.mechanic_id WHERE rm.repair_job_id=? ORDER BY m.full_name', [$id]);
$parts = fetch_all("SELECT p.*, i.name, i.sku, i.unit, m.full_name AS mechanic_name FROM repair_parts_used p JOIN inventory_items i ON i.id=p.item_id LEFT JOIN mechanics m ON m.id=p.mechanic_id WHERE p.repair_job_id=? AND p.status='active' ORDER BY p.id", [$id]);
$locked = job_locked($job); $released = $job['status'] === 'Released';
$pageTitle = 'Parts Used - ' . $job['job_no'];
require __DIR__ . '/../includes/header.php';
echo job_tabs($id, 'parts_used');
?>
<?php if (!$locked): ?>
<div class="card"><h2 class="card-title"><i class="fa-solid fa-gears"></i> Add part used</h2>
<form method="post" id="partForm"><?= csrf_field() ?><?= form_token_field('parts') ?><input type="hidden" name="job_id" value="<?= (int)$id ?>"><input type="hidden" name="action" value="add">
 <div class="form-grid"><label class="field"><span>Search part (name or SKU)</span><input type="text" id="search" autocomplete="off" placeholder="Type to filter..."></label>
 <label class="field"><span>Part *</span><select name="item_id" id="item" size="5" required></select></label>
 <label class="field"><span>Used by mechanic *</span><select name="mechanic_id" required><option value="">Select mechanic...</option><?php foreach ($mechanics as $m): ?><option value="<?= (int)$m['id'] ?>"><?= e($m['full_name']) ?></option><?php endforeach; ?></select></label>
 <label class="field"><span>Quantity used *</span><input type="number" name="quantity" id="qty" min="1" value="1" required></label>
 <div class="field"><span>Price / subtotal (preview)</span><div id="preview" class="muted">Select a part</div></div></div>
 <button class="btn btn-primary" type="submit">Add to job</button></form></div>
<script>
const ITEMS=<?= json_encode(array_map(fn($i) => ['id' => (int)$i['id'], 't' => $i['name'] . ' (' . $i['sku'] . ')', 'p' => (float)$i['selling_price'], 's' => (int)$i['stock_qty'], 'u' => $i['unit']], $items)) ?>;
const sel=document.getElementById('item'),s=document.getElementById('search'),q=document.getElementById('qty'),pv=document.getElementById('preview');
function render(){const t=s.value.toLowerCase();sel.innerHTML='';ITEMS.filter(i=>i.t.toLowerCase().includes(t)).forEach(i=>{const o=document.createElement('option');o.value=i.id;o.textContent=i.t+' - stock: '+i.s+' '+i.u;if(i.s<=0)o.disabled=true;sel.appendChild(o);});}
function prev(){const i=ITEMS.find(x=>x.id==sel.value);if(!i){pv.textContent='Select a part';return;}const n=parseInt(q.value)||0;q.max=i.s;pv.innerHTML='Unit price: <b>\u20b1'+i.p.toFixed(2)+'</b> &middot; Available: '+i.s+' &middot; Subtotal: <b>\u20b1'+(i.p*n).toFixed(2)+'</b> <small>(final amount is calculated by the server)</small>';}
s.addEventListener('input',render);sel.addEventListener('change',prev);q.addEventListener('input',prev);render();
</script>
<?php endif; ?>
<div class="card mt"><h2 class="card-title">Parts on this job</h2><div class="table-wrap"><table class="table"><thead><tr><th>Part</th><th>Used by</th><th class="num">Qty</th><th class="num">Unit price</th><th class="num">Subtotal</th><th>Recorded</th><th></th></tr></thead><tbody>
<?php foreach ($parts as $p): ?><tr><td><?= e($p['name']) ?> <small>(<?= e($p['sku']) ?>)</small></td>
<td><?= e($p['mechanic_name'] ?: 'Not assigned') ?></td>
<td class="num"><?php if (!$locked): ?><form method="post" class="actions"><?= csrf_field() ?><input type="hidden" name="job_id" value="<?= (int)$id ?>"><input type="hidden" name="action" value="update"><input type="hidden" name="part_id" value="<?= (int)$p['id'] ?>"><input type="number" name="quantity" min="1" value="<?= (int)$p['quantity'] ?>" style="width:80px"><button class="btn btn-sm btn-outline" type="submit">Save</button></form><?php else: echo (int)$p['quantity']; endif; ?></td>
<td class="num"><?= money($p['unit_price']) ?></td><td class="num"><?= money($p['line_subtotal']) ?></td><td><?= e($p['created_at']) ?></td>
<td><?php if (!$locked): ?><form method="post" data-confirm="Remove this part and return it to stock?"><?= csrf_field() ?><input type="hidden" name="job_id" value="<?= (int)$id ?>"><input type="hidden" name="action" value="remove"><input type="hidden" name="part_id" value="<?= (int)$p['id'] ?>"><button class="btn btn-sm btn-outline" type="submit"><i class="fa-solid fa-trash"></i></button></form>
<?php elseif ($released): ?><form method="post" class="actions" data-confirm="Reverse this part? Stock will be restored and a reversal recorded."><?= csrf_field() ?><input type="hidden" name="job_id" value="<?= (int)$id ?>"><input type="hidden" name="action" value="reverse"><input type="hidden" name="part_id" value="<?= (int)$p['id'] ?>"><input type="text" name="reason" placeholder="Reason" required style="width:140px"><button class="btn btn-sm btn-danger" type="submit">Reverse</button></form><?php endif; ?></td></tr>
<?php endforeach; if (!$parts): ?><tr><td colspan="7" class="empty">No parts recorded yet.</td></tr><?php endif; ?>
<tr><td colspan="4" class="num"><strong>Parts subtotal</strong></td><td class="num"><strong><?= money($job['parts_subtotal']) ?></strong></td><td colspan="2"></td></tr></tbody></table></div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
