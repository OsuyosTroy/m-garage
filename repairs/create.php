<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/_helpers.php';
guard();

$errors = [];
$mechanics = fetch_all("SELECT id, full_name FROM mechanics WHERE employment_status='active' ORDER BY full_name");
$items = fetch_all('SELECT id, sku, name, selling_price, stock_qty, unit FROM inventory_items WHERE is_active=1 ORDER BY name');

if (is_post()) {
    require_csrf();
    $token = consume_form_token('repair_create');
    $mode = 'new';
    $cid = null;
    $mid = null;
    $customerName = input('new_customer_name');
    $customerPhone = input('new_customer_phone');
    $newMechanicName = input('new_mechanic_name');
    $bikeBrand = input('new_bike_brand');
    $bikeModel = input('new_bike_model');
    $bikePlate = strtoupper(input('new_bike_plate'));
    $primaryRaw = trim((string)($_POST['primary_mechanic'] ?? ''));
    $primaryMechanic = $primaryRaw === 'new' ? 'new' : parse_int($primaryRaw);
    $date = input('date_received');
    $problem = input('reported_problem');
    $diagnosis = input('diagnosis');
    $requested = input('requested_services');
    $notes = input('notes');
    $discount = parse_money($_POST['discount'] ?? '0');
    $tenderedRaw = trim((string)($_POST['cash_received'] ?? ''));
    $tendered = $tenderedRaw === '' ? 0.0 : parse_money($tenderedRaw, false);

    $partRows = [];
    $partIds = (array)($_POST['part_item'] ?? []);
    $partQtys = (array)($_POST['part_qty'] ?? []);
    $partMechanics = (array)($_POST['part_mechanic'] ?? []);
    foreach ($partIds as $i => $rawItem) {
        if (trim((string)$rawItem) === '' && trim((string)($partQtys[$i] ?? '')) === '') continue;
        $itemId = parse_int($rawItem);
        $qty = parse_int($partQtys[$i] ?? '');
        $rawMechanic = trim((string)($partMechanics[$i] ?? ''));
        if ($rawMechanic === '') $rawMechanic = $primaryRaw;
        $mechanicId = $rawMechanic === 'new' ? 'new' : parse_int($rawMechanic);
        if (!$itemId || !$qty || !$mechanicId) { $errors[] = 'Each parts line needs a part, quantity and mechanic.'; break; }
        $partRows[] = ['item_id' => $itemId, 'quantity' => $qty, 'mechanic_id' => $mechanicId];
    }

    $laborRows = [];
    $laborMechanics = (array)($_POST['labor_mechanic'] ?? []);
    $laborTypes = (array)($_POST['labor_type'] ?? []);
    $laborAmounts = (array)($_POST['labor_amount'] ?? []);
    foreach ($laborTypes as $i => $rawType) {
        $type = trim((string)$rawType);
        $amountRaw = trim((string)($laborAmounts[$i] ?? ''));
        if ($type === '' && $amountRaw === '') continue;
        $rawMechanic = trim((string)($laborMechanics[$i] ?? ''));
        if ($rawMechanic === '') $rawMechanic = $primaryRaw;
        $mechanicId = $rawMechanic === 'new' ? 'new' : parse_int($rawMechanic);
        $amount = parse_money($amountRaw, false);
        if (!$mechanicId || $type === '' || mb_strlen($type) > 120 || $amount === null) { $errors[] = 'Each labor line needs a mechanic, service and valid charge.'; break; }
        $laborRows[] = ['mechanic_id' => $mechanicId, 'type' => $type, 'description' => trim((string)($_POST['labor_description'][$i] ?? '')), 'amount' => $amount];
    }

    if (!$token) $errors[] = 'This form was already submitted or expired. Check the repair list before trying again.';
    if (!valid_date($date)) $errors[] = 'Enter a valid date received.';
    if ($problem === '') $errors[] = 'Enter the customer\'s reported problem.';
    if ($customerName === '' || mb_strlen($customerName) > 150) $errors[] = 'Enter the customer\'s name.';
    if ($customerPhone !== '' && !preg_match('/^[0-9+\-\s()]{5,30}$/', $customerPhone)) $errors[] = 'Enter a valid customer phone number.';
    if ($bikeBrand === '' || $bikeModel === '') $errors[] = 'Enter the motorcycle brand and model.';
    if ($discount === null) $errors[] = 'Enter a valid discount amount.';
    if ($tendered === null) $errors[] = 'Enter a valid cash amount or leave it blank.';

    $validMechanics = array_map('intval', array_column($mechanics, 'id'));
    if (!$primaryMechanic) $errors[] = 'Choose the mechanic responsible for this repair.';
    if ($primaryMechanic && $primaryMechanic !== 'new' && !in_array($primaryMechanic, $validMechanics, true)) $errors[] = 'Choose an active mechanic.';
    $needsNewMechanic = $primaryMechanic === 'new' || in_array('new', array_column($partRows, 'mechanic_id'), true) || in_array('new', array_column($laborRows, 'mechanic_id'), true);
    if ($needsNewMechanic && ($newMechanicName === '' || mb_strlen($newMechanicName) > 150)) $errors[] = 'Enter the new mechanic\'s name.';
    $partSubtotal = 0.0;
    foreach ($partRows as &$row) {
        $item = fetch_row('SELECT name, selling_price, is_active FROM inventory_items WHERE id=?', [$row['item_id']]);
        if (!$item || !$item['is_active']) { $errors[] = 'A selected part is no longer available.'; break; }
        if ($row['mechanic_id'] !== 'new' && !in_array($row['mechanic_id'], $validMechanics, true)) { $errors[] = 'Choose an active mechanic for every parts line.'; break; }
        $row['name'] = $item['name'];
        $row['unit_price'] = (float)$item['selling_price'];
        $row['line_total'] = round($row['quantity'] * $row['unit_price'], 2);
        $partSubtotal += $row['line_total'];
    }
    unset($row);
    foreach ($laborRows as $row) if ($row['mechanic_id'] !== 'new' && !in_array($row['mechanic_id'], $validMechanics, true)) { $errors[] = 'Choose an active mechanic for every labor line.'; break; }
    $laborSubtotal = array_sum(array_column($laborRows, 'amount'));
    $preDiscount = round($partSubtotal + $laborSubtotal, 2);
    if ($discount !== null && $discount > $preDiscount) $errors[] = 'Discount cannot exceed the parts and labor total.';
    $due = round($preDiscount - (float)$discount, 2);
    if ($tendered !== null && $tendered > 0 && $due <= 0) $errors[] = 'There is no balance to pay.';

    if (!$errors) {
        try {
            $jid = in_transaction(function (PDO $pdo) use ($mode, $cid, $mid, $customerName, $customerPhone, $bikeBrand, $bikeModel, $bikePlate, $primaryMechanic, $newMechanicName, $date, $problem, $diagnosis, $requested, $notes, $discount, $tendered, $partRows, $laborRows) {
                if ($mode === 'new') {
                    $pdo->prepare('INSERT INTO customers (full_name,contact_number) VALUES (?,?)')->execute([$customerName, $customerPhone ?: null]);
                    $cid = (int)$pdo->lastInsertId();
                    $pdo->prepare('INSERT INTO motorcycles (customer_id,brand,model,plate_number) VALUES (?,?,?,?)')->execute([$cid, $bikeBrand, $bikeModel, $bikePlate ?: null]);
                    $mid = (int)$pdo->lastInsertId();
                }
                $no = next_number('JOB', 'repair_jobs', 'job_no');
                $pdo->prepare('INSERT INTO repair_jobs (job_no,customer_id,motorcycle_id,date_received,reported_problem,diagnosis,requested_services,notes,discount,created_by) VALUES (?,?,?,?,?,?,?,?,?,?)')
                    ->execute([$no, $cid, $mid, $date, $problem, $diagnosis ?: null, $requested ?: null, $notes ?: null, $discount, current_user()['id']]);
                $jid = (int)$pdo->lastInsertId();
                $hasNewMechanic = $primaryMechanic === 'new' || in_array('new', array_column($partRows, 'mechanic_id'), true) || in_array('new', array_column($laborRows, 'mechanic_id'), true);
                $newMechanicId = null;
                if ($hasNewMechanic) {
                    $pdo->prepare('INSERT INTO mechanics (full_name) VALUES (?)')->execute([$newMechanicName]);
                    $newMechanicId = (int)$pdo->lastInsertId();
                    foreach ($partRows as &$partRow) if ($partRow['mechanic_id'] === 'new') $partRow['mechanic_id'] = $newMechanicId;
                    unset($partRow);
                    foreach ($laborRows as &$laborRow) if ($laborRow['mechanic_id'] === 'new') $laborRow['mechanic_id'] = $newMechanicId;
                    unset($laborRow);
                }
                if ($primaryMechanic === 'new') $primaryMechanic = $newMechanicId;
                $mechanicIds = [];
                $mechanicIds[] = $primaryMechanic;
                foreach ($partRows as $row) $mechanicIds[] = $row['mechanic_id'];
                foreach ($laborRows as $row) $mechanicIds[] = $row['mechanic_id'];
                foreach (array_unique(array_map('intval', $mechanicIds)) as $mechanicId) {
                    $activeCheck = $pdo->prepare("SELECT id FROM mechanics WHERE id=? AND employment_status='active'");
                    $activeCheck->execute([$mechanicId]);
                    if (!$activeCheck->fetchColumn()) throw new RuntimeException('A selected mechanic is no longer active.');
                    $pdo->prepare('INSERT INTO repair_mechanics (repair_job_id,mechanic_id) VALUES (?,?)')->execute([$jid, $mechanicId]);
                }
                $grouped = [];
                foreach ($partRows as $row) {
                    $key = $row['item_id'] . ':' . $row['mechanic_id'];
                    if (!isset($grouped[$key])) $grouped[$key] = $row;
                    else $grouped[$key]['quantity'] += $row['quantity'];
                }
                $actualPartsSubtotal = 0.0;
                foreach ($grouped as $row) {
                    $itemCheck = $pdo->prepare('SELECT selling_price,is_active FROM inventory_items WHERE id=? FOR UPDATE');
                    $itemCheck->execute([$row['item_id']]);
                    $currentItem = $itemCheck->fetch();
                    if (!$currentItem || !$currentItem['is_active']) throw new RuntimeException('A selected part is no longer available.');
                    $row['unit_price'] = (float)$currentItem['selling_price'];
                    $lineTotal = round($row['quantity'] * $row['unit_price'], 2);
                    $actualPartsSubtotal += $lineTotal;
                    $pdo->prepare('INSERT INTO repair_parts_used (repair_job_id,item_id,mechanic_id,quantity,unit_price,line_subtotal,request_token,recorded_by) VALUES (?,?,?,?,?,?,?,?)')
                        ->execute([$jid, $row['item_id'], $row['mechanic_id'], $row['quantity'], $row['unit_price'], $lineTotal, bin2hex(random_bytes(16)), current_user()['id']]);
                    stock_change($pdo, $row['item_id'], -$row['quantity'], 'repair_use', 'repair_job', $jid, 'Used in ' . $no, current_user()['id']);
                }
                foreach ($laborRows as $row) {
                    $pdo->prepare('INSERT INTO labor_entries (repair_job_id,mechanic_id,service_type,description,service_date,amount,request_token,created_by) VALUES (?,?,?,?,?,?,?,?)')
                        ->execute([$jid, $row['mechanic_id'], $row['type'], $row['description'] ?: null, $date, $row['amount'], bin2hex(random_bytes(16)), current_user()['id']]);
                }
                if ($discount > $actualPartsSubtotal + array_sum(array_column($laborRows, 'amount'))) throw new RuntimeException('Discount cannot exceed the current parts and labor total.');
                recalc_job($pdo, $jid);
                if ($tendered !== null && $tendered > 0) {
                    $fresh = fetch_row('SELECT total_due,amount_paid FROM repair_jobs WHERE id=?', [$jid]);
                    $balance = round((float)$fresh['total_due'] - (float)$fresh['amount_paid'], 2);
                    $applied = min($tendered, $balance);
                    $paymentNo = next_number('PAY', 'payments', 'payment_no', 'paid_at');
                    $pdo->prepare("INSERT INTO payments (payment_no,payable_type,repair_job_id,amount_due,amount_tendered,amount_applied,change_given,received_by,request_token) VALUES (?,'repair',?,?,?,?,?,?,?)")
                        ->execute([$paymentNo, $jid, $balance, $tendered, $applied, round($tendered - $applied, 2), current_user()['id'], bin2hex(random_bytes(16))]);
                    recalc_job($pdo, $jid);
                }
                log_activity('repair_created', 'repairs', "Created $no with parts, labor and payment entered at intake");
                return $jid;
            });
            flash('success', 'Repair saved. Parts, mechanic labor and any payment were recorded together.');
            redirect('repairs/view.php?id=' . $jid);
        } catch (Throwable $ex) { $errors[] = 'Could not save repair: ' . (APP_DEBUG ? $ex->getMessage() : 'check your entries and try again'); }
    }
}

$pageTitle = 'Quick Repair Entry';
require __DIR__ . '/../includes/header.php';
$itemOptions = array_map(fn($i) => ['id' => (int)$i['id'], 'label' => $i['name'] . ' (' . $i['sku'] . ')', 'stock' => (int)$i['stock_qty'], 'price' => (float)$i['selling_price']], $items);
$mechanicOptions = array_map(fn($m) => ['id' => (int)$m['id'], 'name' => $m['full_name']], $mechanics);
?>
<div class="quick-entry-intro"><p>Record the customer, assigned mechanic, parts, labor and payment in one place.</p><span>Fields marked * are required.</span></div>
<?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
<form method="post" id="quickRepairForm"><?= csrf_field() ?><?= form_token_field('repair_create') ?>
<div class="card quick-entry-card"><h2 class="card-title"><span class="step-number">1</span> Customer and motorcycle</h2>
 <div class="form-grid">
  <label class="field"><span>Customer name *</span><input name="new_customer_name" value="<?= old('new_customer_name') ?>"></label>
  <label class="field"><span>Phone</span><input name="new_customer_phone" value="<?= old('new_customer_phone') ?>"></label>
  <label class="field"><span>Motorcycle brand *</span><input name="new_bike_brand" value="<?= old('new_bike_brand') ?>"></label>
  <label class="field"><span>Motorcycle model *</span><input name="new_bike_model" value="<?= old('new_bike_model') ?>"></label>
  <label class="field"><span>Plate number</span><input name="new_bike_plate" value="<?= old('new_bike_plate') ?>"></label>
 </div>
</div>

<div class="card quick-entry-card"><h2 class="card-title"><span class="step-number">2</span> Repair and mechanic</h2><div class="form-grid">
 <label class="field"><span>Mechanic responsible for this repair *</span><select name="primary_mechanic" id="primaryMechanic" required><option value="">Select mechanic...</option><?php foreach ($mechanics as $m): ?><option value="<?= (int)$m['id'] ?>" <?= (string)($_POST['primary_mechanic'] ?? '') === (string)$m['id'] ? 'selected' : '' ?>><?= e($m['full_name']) ?></option><?php endforeach; ?><option value="new" <?= (($_POST['primary_mechanic'] ?? '') === 'new') ? 'selected' : '' ?>>＋ Add a new mechanic</option></select></label>
 <label class="field"><span>Date received *</span><input type="date" name="date_received" value="<?= old('date_received', date('Y-m-d')) ?>" required></label>
 <label class="field"><span>Reported problem *</span><input name="reported_problem" value="<?= old('reported_problem') ?>" required></label>
 <label class="field"><span>Diagnosis</span><input name="diagnosis" value="<?= old('diagnosis') ?>"></label>
 <label class="field"><span>Requested service</span><input name="requested_services" value="<?= old('requested_services') ?>"></label>
 <label class="field full"><span>Notes</span><input name="notes" value="<?= old('notes') ?>"></label>
 </div><label class="field"><span>New mechanic name (if you selected “Add a new mechanic”)</span><input id="newMechanicName" name="new_mechanic_name" value="<?= old('new_mechanic_name') ?>" placeholder="Enter mechanic name once"></label><p class="hint">Parts and labor lines default to this mechanic. Choose a different mechanic on a line only when needed.</p></div>

<div class="card quick-entry-card"><h2 class="card-title"><span class="step-number">3</span> Parts used</h2><div id="partRows"></div><button type="button" class="btn btn-sm btn-outline" id="addPart"><i class="fa-solid fa-plus"></i> Add part</button></div>
<div class="card quick-entry-card"><h2 class="card-title"><span class="step-number">4</span> Labor</h2><div id="laborRows"></div><button type="button" class="btn btn-sm btn-outline" id="addLabor"><i class="fa-solid fa-plus"></i> Add labor</button></div>
<div class="card quick-entry-card"><h2 class="card-title"><span class="step-number">5</span> Discount and payment</h2><div class="form-grid">
 <label class="field"><span>Discount</span><input type="number" name="discount" min="0" step="0.01" value="<?= e($_POST['discount'] ?? '0') ?>"></label>
 <label class="field"><span>Cash received (optional)</span><input type="number" name="cash_received" min="0.01" step="0.01" value="<?= e($_POST['cash_received'] ?? '') ?>"><small class="hint">Leave blank if the customer will pay later. Partial payments are accepted.</small></label>
 </div></div>
<div class="quick-entry-actions"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Save repair</button> <a class="btn btn-outline" href="index.php">Cancel</a></div>
</form>
<script>
const mechanics = <?= json_encode($mechanicOptions, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
const items = <?= json_encode($itemOptions, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
function option(value,label){const o=document.createElement('option');o.value=value;o.textContent=label;return o;}
const primarySelect=document.getElementById('primaryMechanic');
const newMechanicInput=document.getElementById('newMechanicName');
function syncNewMechanicRequirement(){newMechanicInput.required=primarySelect.value==='new'||[...document.querySelectorAll('select[name="part_mechanic[]"],select[name="labor_mechanic[]"]')].some(s=>s.value==='new');}
function mechanicSelect(name){const s=document.createElement('select');s.name=name;s.required=true;s.append(option('','Use repair mechanic...'));mechanics.forEach(m=>s.append(option(m.id,m.name)));s.append(option('new','＋ Add new mechanic'));s.value=primarySelect.value;return s;}
let previousPrimary=primarySelect.value;primarySelect.addEventListener('change',()=>{document.querySelectorAll('select[name="part_mechanic[]"],select[name="labor_mechanic[]"]').forEach(s=>{if(!s.value||s.value===previousPrimary)s.value=primarySelect.value;});previousPrimary=primarySelect.value;syncNewMechanicRequirement();});
function removeButton(row){const b=document.createElement('button');b.type='button';b.className='btn btn-sm btn-outline';b.textContent='Remove';b.addEventListener('click',()=>{row.remove();syncNewMechanicRequirement();});return b;}
document.getElementById('addPart').addEventListener('click',()=>{const row=document.createElement('div');row.className='line-row quick-entry-row';const item= document.createElement('select');item.name='part_item[]';item.append(option('','Select part...'));items.forEach(i=>{const o=option(i.id,`${i.label} — stock ${i.stock} — ₱${i.price.toFixed(2)}`);if(i.stock<=0)o.disabled=true;item.append(o);});const qty=document.createElement('input');qty.type='number';qty.name='part_qty[]';qty.min='1';qty.placeholder='Qty';const mech=mechanicSelect('part_mechanic[]');mech.addEventListener('change',syncNewMechanicRequirement);row.append(item,qty,mech,removeButton(row));document.getElementById('partRows').append(row);syncNewMechanicRequirement();});
document.getElementById('addLabor').addEventListener('click',()=>{const row=document.createElement('div');row.className='line-row quick-labor-row';const mech=mechanicSelect('labor_mechanic[]');mech.addEventListener('change',syncNewMechanicRequirement);const type=document.createElement('input');type.name='labor_type[]';type.placeholder='Service (e.g. tune-up)';const amount=document.createElement('input');amount.type='number';amount.name='labor_amount[]';amount.min='0.01';amount.step='0.01';amount.placeholder='Labor charge';const desc=document.createElement('input');desc.name='labor_description[]';desc.placeholder='Details (optional)';row.append(mech,type,amount,desc,removeButton(row));document.getElementById('laborRows').append(row);syncNewMechanicRequirement();});
document.getElementById('addPart').click();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
