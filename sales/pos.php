<?php
require_once __DIR__ . '/../includes/auth.php';
guard();
$errors = [];
if (is_post()) {
    require_csrf();
    $token = consume_form_token('pos');
    if (!$token) $errors[] = 'This sale form was already submitted or expired. Check Sales History before ringing it up again.';
    $cart = [];
    foreach ((array)($_POST['item_id'] ?? []) as $k => $iid) {
        $iid = parse_int($iid); $qty = parse_int($_POST['qty'][$k] ?? '');
        if (!$iid || !$qty) { $errors[] = 'Invalid cart line.'; break; }
        $cart[$iid] = ($cart[$iid] ?? 0) + $qty;
    }
    if (!$cart) $errors[] = 'The cart is empty.';
    $disc = parse_money($_POST['discount'] ?? '0'); $tendered = parse_money($_POST['tendered'] ?? '');
    if ($disc === null) $errors[] = 'Discount must be a valid amount.';
    if ($tendered === null) $errors[] = 'Enter the cash amount received.';
    $cid = parse_int($_POST['customer_id'] ?? ''); $cname = input('customer_name');
    if ($cid) { $c = fetch_row('SELECT full_name FROM customers WHERE id=?', [$cid]); if (!$c) $errors[] = 'Invalid customer.'; else $cname = $c['full_name']; }
    if (!$errors) {
        try {
            $sid = in_transaction(function (PDO $pdo) use ($cart, $disc, $tendered, $cid, $cname, $token) {
                $uid = current_user()['id']; $lines = []; $subtotal = 0.0;
                foreach ($cart as $iid => $qty) {
                    $s = $pdo->prepare('SELECT id, name, selling_price, purchase_cost, is_active FROM inventory_items WHERE id=?'); $s->execute([$iid]); $it = $s->fetch();
                    if (!$it || !$it['is_active']) throw new RuntimeException('An item in the cart is no longer available.');
                    $line = round($qty * (float)$it['selling_price'], 2); $subtotal += $line;
                    $lines[] = [$iid, $qty, (float)$it['selling_price'], (float)$it['purchase_cost'], $line];
                }
                if ($disc > $subtotal) throw new RuntimeException('Discount cannot exceed the subtotal.');
                $total = round($subtotal - $disc, 2);
                if ($tendered + 0.001 < $total) throw new RuntimeException('Cash received (' . money($tendered) . ') is less than the total (' . money($total) . ').');
                $receipt = next_number('OR', 'sales', 'receipt_no');
                $pdo->prepare('INSERT INTO sales (receipt_no,customer_id,customer_name,subtotal,discount,total,cashier_id,request_token) VALUES (?,?,?,?,?,?,?,?)')->execute([$receipt, $cid, $cname ?: null, $subtotal, $disc, $total, $uid, $token]);
                $sid = (int)$pdo->lastInsertId();
                foreach ($lines as [$iid, $qty, $price, $cost, $line]) {
                    $pdo->prepare('INSERT INTO sale_items (sale_id,item_id,quantity,unit_price,unit_cost,line_subtotal) VALUES (?,?,?,?,?,?)')->execute([$sid, $iid, $qty, $price, $cost, $line]);
                    stock_change($pdo, $iid, -$qty, 'sale', 'sale', $sid, "Sale $receipt", $uid);
                }
                $payNo = next_number('PAY', 'payments', 'payment_no', 'paid_at');
                $pdo->prepare("INSERT INTO payments (payment_no,payable_type,sale_id,amount_due,amount_tendered,amount_applied,change_given,received_by) VALUES (?,'sale',?,?,?,?,?,?)")
                    ->execute([$payNo, $sid, $total, $tendered, $total, round($tendered - $total, 2), $uid]);
                log_activity('sale_created', 'sales', "Sale $receipt total " . money($total));
                return $sid;
            });
            flash('success', 'Sale completed.'); redirect('sales/receipt.php?id=' . $sid);
        } catch (Throwable $ex) { $errors[] = $ex->getMessage(); }
    }
}
$items = fetch_all('SELECT i.id, i.sku, i.name, i.selling_price, i.stock_qty, i.unit, c.name AS cat FROM inventory_items i JOIN categories c ON c.id=i.category_id WHERE i.is_active=1 ORDER BY i.name');
$custs = fetch_all('SELECT id, full_name FROM customers WHERE is_active=1 ORDER BY full_name');
$pageTitle = 'Point of Sale';
require __DIR__ . '/../includes/header.php';
?>
<?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
<div class="pos">
<div class="card"><input type="text" id="psearch" placeholder="Search part, SKU or category..." autofocus style="margin-bottom:10px">
 <div class="prod-list table-wrap"><table class="table" id="ptable"><thead><tr><th>Part</th><th class="num">Price</th><th class="num">Stock</th></tr></thead><tbody>
 <?php foreach ($items as $i): ?><tr data-id="<?= (int)$i['id'] ?>" data-name="<?= e($i['name']) ?>" data-price="<?= e($i['selling_price']) ?>" data-stock="<?= (int)$i['stock_qty'] ?>" <?= $i['stock_qty'] <= 0 ? 'style="opacity:.45"' : '' ?>>
 <td><?= e($i['name']) ?><br><small class="muted"><?= e($i['sku'] . ' | ' . $i['cat']) ?></small></td><td class="num"><?= money($i['selling_price']) ?></td><td class="num"><?= (int)$i['stock_qty'] ?> <?= e($i['unit']) ?></td></tr><?php endforeach; ?></tbody></table></div></div>
<div class="card"><h2 class="card-title"><i class="fa-solid fa-cart-shopping"></i> Cart</h2>
 <form method="post" id="saleForm"><?= csrf_field() ?><?= form_token_field('pos') ?>
  <div id="cart"><p class="empty muted">Click a part to add it.</p></div>
  <label class="field mt"><span>Customer (optional)</span><select name="customer_id"><option value="">Walk-in</option><?php foreach ($custs as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['full_name']) ?></option><?php endforeach; ?></select></label>
  <label class="field"><span>Discount (amount)</span><input type="number" step="0.01" min="0" name="discount" id="disc" value="0"></label>
  <div class="totals"><div><span>Subtotal</span><span id="sub">&#8369;0.00</span></div><div class="grand"><span>Total</span><span id="tot">&#8369;0.00</span></div></div>
  <label class="field mt"><span>Cash received *</span><input type="number" step="0.01" min="0" name="tendered" id="cash" required></label>
  <div class="totals"><div class="grand"><span>Change</span><span id="chg">&#8369;0.00</span></div></div>
  <button class="btn btn-primary btn-block mt" type="submit"><i class="fa-solid fa-check"></i> Complete sale</button></form></div></div>
<script>
const cart={}, fm=n=>'\u20b1'+n.toFixed(2);
const box=document.getElementById('cart'),disc=document.getElementById('disc'),cash=document.getElementById('cash');
function draw(){box.innerHTML='';let sub=0;const ids=Object.keys(cart);
 if(!ids.length)box.innerHTML='<p class="empty muted">Click a part to add it.</p>';
 ids.forEach(id=>{const c=cart[id];sub+=c.price*c.qty;const d=document.createElement('div');d.className='line-row';d.style.gridTemplateColumns='3fr 90px 90px 36px';
  d.innerHTML='<div>'+c.name+'<br><small class="muted">'+fm(c.price)+'</small><input type="hidden" name="item_id[]" value="'+id+'"></div><input type="number" name="qty[]" min="1" max="'+c.stock+'" value="'+c.qty+'"><b style="padding-top:10px">'+fm(c.price*c.qty)+'</b><button type="button" class="btn btn-sm btn-outline">&times;</button>';
  d.querySelector('input[type=number]').addEventListener('change',e=>{let v=parseInt(e.target.value)||1;v=Math.max(1,Math.min(v,c.stock));c.qty=v;draw();});
  d.querySelector('button').onclick=()=>{delete cart[id];draw();};box.appendChild(d);});
 const dv=Math.min(parseFloat(disc.value)||0,sub),t=sub-dv;document.getElementById('sub').textContent=fm(sub);document.getElementById('tot').textContent=fm(t);
 const ch=(parseFloat(cash.value)||0)-t;document.getElementById('chg').textContent=fm(ch>0?ch:0);}
document.querySelectorAll('#ptable tbody tr').forEach(r=>r.addEventListener('click',()=>{const id=r.dataset.id,stock=+r.dataset.stock;if(stock<=0){alert('Out of stock.');return;}
 if(cart[id]){if(cart[id].qty<stock)cart[id].qty++;else alert('No more stock available.');}else cart[id]={name:r.dataset.name,price:+r.dataset.price,stock:stock,qty:1};draw();}));
disc.addEventListener('input',draw);cash.addEventListener('input',draw);
document.getElementById('psearch').addEventListener('input',e=>{const q=e.target.value.toLowerCase();document.querySelectorAll('#ptable tbody tr').forEach(r=>r.style.display=r.textContent.toLowerCase().includes(q)?'':'none');});
document.getElementById('saleForm').addEventListener('submit',e=>{if(!Object.keys(cart).length){e.preventDefault();alert('The cart is empty.');}});
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
