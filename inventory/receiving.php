<?php
require_once __DIR__ . '/../includes/auth.php';
guard();
$errors = [];
if (is_post()) {
    require_csrf();
    $token = consume_form_token('receiving');
    $supplier = parse_int($_POST['supplier_id'] ?? ''); $ref = input('reference_no'); $date = input('date_received'); $notes = input('notes');
    if (!$token) $errors[] = 'This form was already submitted or has expired. Check the history below before trying again.';
    if (!$supplier || !fetch_row('SELECT id FROM suppliers WHERE id=? AND is_active=1', [$supplier])) $errors[] = 'Select an active supplier.';
    if ($ref === '' || mb_strlen($ref) > 50) $errors[] = 'Reference number is required.';
    if (!valid_date($date) || $date > date('Y-m-d')) $errors[] = 'Enter a valid date received (not in the future).';
    $lines = [];
    foreach (($_POST['item_id'] ?? []) as $k => $iid) {
        if ($iid === '') continue;
        $iid = parse_int($iid); $qty = parse_int($_POST['qty'][$k] ?? ''); $cost = parse_money($_POST['cost'][$k] ?? '');
        if (!$iid || !$qty || $cost === null) { $errors[] = 'Every line needs an item, a quantity above zero and a valid cost.'; break; }
        $lines[] = [$iid, $qty, $cost];
    }
    if (!$lines) $errors[] = 'Add at least one item.';
    if (!$errors) {
        try {
            $rid = in_transaction(function (PDO $pdo) use ($supplier, $ref, $date, $notes, $lines, $token) {
                $total = array_sum(array_map(fn($l) => $l[1] * $l[2], $lines));
                $pdo->prepare('INSERT INTO stock_receivings (reference_no,supplier_id,date_received,total_cost,notes,received_by,request_token) VALUES (?,?,?,?,?,?,?)')
                    ->execute([$ref, $supplier, $date, $total, $notes ?: null, current_user()['id'], $token]);
                $rid = (int)$pdo->lastInsertId();
                foreach ($lines as [$iid, $qty, $cost]) {
                    $pdo->prepare('INSERT INTO stock_receiving_items (receiving_id,item_id,quantity,unit_cost,line_total) VALUES (?,?,?,?,?)')->execute([$rid, $iid, $qty, $cost, $qty * $cost]);
                    stock_change($pdo, $iid, $qty, 'receiving', 'stock_receiving', $rid, "Received ref $ref", current_user()['id']);
                    $pdo->prepare('UPDATE inventory_items SET purchase_cost=? WHERE id=?')->execute([$cost, $iid]);
                }
                return $rid;
            });
            log_activity('stock_received', 'inventory', "Stock receiving $ref (#$rid)");
            flash('success', 'Stock received and inventory updated.');
            redirect('inventory/receiving.php?view=' . $rid);
        } catch (PDOException $ex) { $errors[] = $ex->getCode() === '23000' ? 'That reference number was already used.' : 'Database error: ' . (APP_DEBUG ? $ex->getMessage() : ''); }
        catch (Throwable $ex) { $errors[] = $ex->getMessage(); }
    }
}
$items = fetch_all('SELECT id, sku, name FROM inventory_items WHERE is_active=1 ORDER BY name');
$optHtml = '<option value="">Select item...</option>';
foreach ($items as $i) $optHtml .= '<option value="' . (int)$i['id'] . '">' . e($i['name'] . ' (' . $i['sku'] . ')') . '</option>';
$viewId = parse_int($_GET['view'] ?? '');
$detail = $viewId ? fetch_row('SELECT r.*, s.name AS sup, u.full_name FROM stock_receivings r JOIN suppliers s ON s.id=r.supplier_id JOIN users u ON u.id=r.received_by WHERE r.id=?', [$viewId]) : null;
$detailItems = $detail ? fetch_all('SELECT ri.*, i.name, i.sku FROM stock_receiving_items ri JOIN inventory_items i ON i.id=ri.item_id WHERE ri.receiving_id=?', [$viewId]) : [];
$history = fetch_all('SELECT r.*, s.name AS sup FROM stock_receivings r JOIN suppliers s ON s.id=r.supplier_id ORDER BY r.id DESC LIMIT 50');
$pageTitle = 'Stock Receiving';
require __DIR__ . '/../includes/header.php';
?>
<?php if ($detail): ?>
<div class="card mb"><h2 class="card-title">Receiving <?= e($detail['reference_no']) ?></h2>
  <p>Supplier: <strong><?= e($detail['sup']) ?></strong> &middot; Date: <?= e($detail['date_received']) ?> &middot; By: <?= e($detail['full_name']) ?></p>
  <div class="table-wrap"><table class="table"><thead><tr><th>Item</th><th class="num">Qty</th><th class="num">Unit cost</th><th class="num">Total</th></tr></thead><tbody>
  <?php foreach ($detailItems as $d): ?><tr><td><?= e($d['name']) ?> <small>(<?= e($d['sku']) ?>)</small></td><td class="num"><?= (int)$d['quantity'] ?></td><td class="num"><?= money($d['unit_cost']) ?></td><td class="num"><?= money($d['line_total']) ?></td></tr><?php endforeach; ?>
  <tr><td colspan="3" class="num"><strong>Total</strong></td><td class="num"><strong><?= money($detail['total_cost']) ?></strong></td></tr></tbody></table></div></div><br>
<?php endif; ?>
<div class="card"><h2 class="card-title"><i class="fa-solid fa-dolly"></i> Receive stock from supplier</h2>
  <?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
  <form method="post"><?= csrf_field() ?><?= form_token_field('receiving') ?>
    <div class="form-grid">
      <label class="field"><span>Supplier *</span><select name="supplier_id" required><option value="">Select...</option><?php foreach (active_suppliers() as $s): ?><option value="<?= (int)$s['id'] ?>" <?= (string)($_POST['supplier_id'] ?? '') === (string)$s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?></select></label>
      <label class="field"><span>Reference no. *</span><input type="text" name="reference_no" value="<?= old('reference_no') ?>" required maxlength="50" placeholder="Supplier invoice / DR no."></label>
      <label class="field"><span>Date received *</span><input type="date" name="date_received" value="<?= old('date_received', date('Y-m-d')) ?>" max="<?= date('Y-m-d') ?>" required></label>
    </div>
    <div class="row-lines" id="lines"></div>
    <button type="button" class="btn btn-outline btn-sm" id="addLine"><i class="fa-solid fa-plus"></i> Add item line</button>
    <label class="field mt"><span>Notes</span><input type="text" name="notes" value="<?= old('notes') ?>"></label>
    <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Save receiving</button>
  </form></div>
<div class="card mt"><h2 class="card-title">Previous receivings</h2><div class="table-wrap"><table class="table"><thead><tr><th>Reference</th><th>Supplier</th><th>Date</th><th class="num">Total</th><th></th></tr></thead><tbody>
<?php foreach ($history as $h): ?><tr><td><?= e($h['reference_no']) ?></td><td><?= e($h['sup']) ?></td><td><?= e($h['date_received']) ?></td><td class="num"><?= money($h['total_cost']) ?></td><td><a class="btn btn-sm btn-outline" href="?view=<?= (int)$h['id'] ?>">View</a></td></tr>
<?php endforeach; if (!$history): ?><tr><td colspan="5" class="empty">No receiving transactions yet.</td></tr><?php endif; ?></tbody></table></div></div>
<script>
const opts = <?= json_encode($optHtml) ?>;
const box = document.getElementById('lines');
function addLine(){const d=document.createElement('div');d.className='line-row';
 d.innerHTML='<select name="item_id[]">'+opts+'</select><input type="number" name="qty[]" min="1" placeholder="Qty"><input type="number" name="cost[]" min="0" step="0.01" placeholder="Unit cost"><button type="button" class="btn btn-sm btn-outline">&times;</button>';
 d.querySelector('button').onclick=()=>d.remove();box.appendChild(d);}
document.getElementById('addLine').onclick=addLine;addLine();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
