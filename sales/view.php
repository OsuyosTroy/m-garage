<?php
require_once __DIR__ . '/../includes/auth.php';
guard();
$id = parse_int($_GET['id'] ?? $_POST['id'] ?? '');
$sale = $id ? fetch_row('SELECT s.*, u.full_name AS cashier FROM sales s JOIN users u ON u.id=s.cashier_id WHERE s.id=?', [$id]) : null;
if (!$sale) { flash('error', 'Sale not found.'); redirect('sales/index.php'); }
if (is_post()) {
    require_csrf();
    $reason = input('reason'); $as = input('as') === 'refunded' ? 'refunded' : 'cancelled';
    try {
        if ($reason === '') throw new RuntimeException('A reason is required.');
        in_transaction(function (PDO $pdo) use ($id, $reason, $as, $sale) {
            $s = $pdo->prepare('SELECT status FROM sales WHERE id=? FOR UPDATE'); $s->execute([$id]);
            if ($s->fetchColumn() !== 'completed') throw new RuntimeException('This sale has already been ' . $sale['status'] . '.');
            $it = $pdo->prepare('SELECT item_id, quantity FROM sale_items WHERE sale_id=?'); $it->execute([$id]);
            foreach ($it->fetchAll() as $l) stock_change($pdo, (int)$l['item_id'], (int)$l['quantity'], 'reversal', 'sale', $id, "Sale {$sale['receipt_no']} $as: $reason", current_user()['id']);
            $pdo->prepare('UPDATE sales SET status=?, cancel_reason=?, cancelled_by=?, cancelled_at=NOW() WHERE id=?')->execute([$as, $reason, current_user()['id'], $id]);
            $pdo->prepare("UPDATE payments SET status='voided' WHERE sale_id=? AND payable_type='sale'")->execute([$id]);
        });
        log_activity('sale_' . $as, 'sales', "{$sale['receipt_no']} $as: $reason"); flash('success', 'Sale ' . $as . '. Stock was restored.');
    } catch (Throwable $ex) { flash('error', $ex->getMessage()); }
    redirect('sales/view.php?id=' . $id);
}
$lines = fetch_all('SELECT si.*, i.name, i.sku FROM sale_items si JOIN inventory_items i ON i.id=si.item_id WHERE si.sale_id=?', [$id]);
$pay = fetch_row("SELECT * FROM payments WHERE sale_id=? AND payable_type='sale' ORDER BY id LIMIT 1", [$id]);
$pageTitle = 'Sale ' . $sale['receipt_no'];
require __DIR__ . '/../includes/header.php';
?>
<div class="card"><h2 class="card-title"><?= e($sale['receipt_no']) ?> <span class="badge b-<?= e($sale['status']) ?>"><?= e($sale['status']) ?></span></h2>
<p>Customer: <strong><?= e($sale['customer_name'] ?: 'Walk-in') ?></strong> &middot; Cashier: <?= e($sale['cashier']) ?> &middot; <?= e($sale['created_at']) ?></p>
<div class="table-wrap"><table class="table"><thead><tr><th>Item</th><th class="num">Qty</th><th class="num">Price</th><th class="num">Subtotal</th></tr></thead><tbody>
<?php foreach ($lines as $l): ?><tr><td><?= e($l['name']) ?> <small>(<?= e($l['sku']) ?>)</small></td><td class="num"><?= (int)$l['quantity'] ?></td><td class="num"><?= money($l['unit_price']) ?></td><td class="num"><?= money($l['line_subtotal']) ?></td></tr><?php endforeach; ?></tbody></table></div>
<div class="totals mt"><div><span>Subtotal</span><span><?= money($sale['subtotal']) ?></span></div><div><span>Discount</span><span>- <?= money($sale['discount']) ?></span></div><div class="grand"><span>Total</span><span><?= money($sale['total']) ?></span></div>
<?php if ($pay): ?><div><span>Cash received</span><span><?= money($pay['amount_tendered']) ?></span></div><div><span>Change</span><span><?= money($pay['change_given']) ?></span></div><?php endif; ?></div>
<?php if ($sale['status'] !== 'completed'): ?><p class="mt"><strong><?= e(ucfirst($sale['status'])) ?>:</strong> <?= e($sale['cancel_reason']) ?> <span class="muted"><?= e($sale['cancelled_at']) ?></span></p><?php endif; ?>
<p class="mt no-print"><a class="btn btn-outline" href="receipt.php?id=<?= (int)$id ?>"><i class="fa-solid fa-print"></i> Receipt</a> <a class="btn btn-outline" href="index.php">Back</a></p></div>
<?php if ($sale['status'] === 'completed'): ?><div class="card mt no-print"><h2 class="card-title">Cancel or refund this sale</h2><p class="hint">Stock is returned to inventory through a recorded reversal. The sale record is kept.</p>
<form method="post" class="toolbar" data-confirm="Reverse this sale and restore stock?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
<select name="as"><option value="cancelled">Cancel (entry error)</option><option value="refunded">Refund (customer return)</option></select><input type="text" name="reason" placeholder="Reason" required class="grow"><button class="btn btn-danger" type="submit">Reverse sale</button></form></div><?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
