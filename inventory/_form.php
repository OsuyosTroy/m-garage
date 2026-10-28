<?php
/** Validates the inventory item form. Returns [data, errors]. */
function item_validate(?int $id = null): array {
    $d = [
        'sku' => strtoupper(input('sku')), 'name' => input('name'),
        'category_id' => parse_int($_POST['category_id'] ?? ''), 'brand' => input('brand'),
        'compatibility' => input('compatibility'), 'description' => input('description'),
        'supplier_id' => parse_int($_POST['supplier_id'] ?? '') ?: null,
        'purchase_cost' => parse_money($_POST['purchase_cost'] ?? ''), 'selling_price' => parse_money($_POST['selling_price'] ?? ''),
        'min_stock' => parse_int($_POST['min_stock'] ?? '', true), 'unit' => input('unit', 'pc'),
    ];
    $er = [];
    if ($d['sku'] === '' || !preg_match('/^[A-Z0-9._\-]{2,50}$/', $d['sku'])) $er[] = 'SKU is required (letters, numbers, - . _ only).';
    if ($d['name'] === '' || mb_strlen($d['name']) > 150) $er[] = 'Part name is required (max 150 characters).';
    if (!$d['category_id'] || !fetch_row('SELECT id FROM categories WHERE id=?', [$d['category_id']])) $er[] = 'Choose a valid category.';
    if ($d['supplier_id'] && !fetch_row('SELECT id FROM suppliers WHERE id=?', [$d['supplier_id']])) $er[] = 'Choose a valid supplier.';
    if ($d['purchase_cost'] === null) $er[] = 'Purchase cost must be a valid amount.';
    if ($d['selling_price'] === null) $er[] = 'Selling price must be a valid amount.';
    if ($d['min_stock'] === null) $er[] = 'Minimum stock must be a whole number.';
    if ($d['unit'] === '' || mb_strlen($d['unit']) > 20) $er[] = 'Unit of measurement is required.';
    if (!$er && fetch_row('SELECT id FROM inventory_items WHERE sku=? AND id<>?', [$d['sku'], $id ?? 0])) $er[] = 'That SKU already exists.';
    return [$d, $er];
}

function item_form(array $v, bool $isNew): void { ?>
<div class="form-grid">
  <label class="field"><span>SKU / Item ID *</span><input type="text" name="sku" value="<?= e($v['sku'] ?? '') ?>" required maxlength="50"></label>
  <label class="field"><span>Part name *</span><input type="text" name="name" value="<?= e($v['name'] ?? '') ?>" required maxlength="150"></label>
  <label class="field"><span>Category *</span><select name="category_id" required><option value="">Select...</option>
    <?php foreach (active_categories() as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (string)($v['category_id'] ?? '') === (string)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></label>
  <label class="field"><span>Brand</span><input type="text" name="brand" value="<?= e($v['brand'] ?? '') ?>" maxlength="80"></label>
  <label class="field"><span>Compatible models</span><input type="text" name="compatibility" value="<?= e($v['compatibility'] ?? '') ?>" placeholder="e.g. Honda Click, Beat"></label>
  <label class="field"><span>Supplier</span><select name="supplier_id"><option value="">None</option>
    <?php foreach (active_suppliers() as $s): ?><option value="<?= (int)$s['id'] ?>" <?= (string)($v['supplier_id'] ?? '') === (string)$s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?></select></label>
  <label class="field"><span>Purchase cost *</span><input type="number" step="0.01" min="0" name="purchase_cost" value="<?= e($v['purchase_cost'] ?? '0.00') ?>" required></label>
  <label class="field"><span>Selling price *</span><input type="number" step="0.01" min="0" name="selling_price" value="<?= e($v['selling_price'] ?? '0.00') ?>" required></label>
  <?php if ($isNew): ?><label class="field"><span>Initial stock quantity</span><input type="number" min="0" name="stock_qty" value="<?= e($v['stock_qty'] ?? '0') ?>"></label><?php endif; ?>
  <label class="field"><span>Minimum stock threshold *</span><input type="number" min="0" name="min_stock" value="<?= e($v['min_stock'] ?? '5') ?>" required></label>
  <label class="field"><span>Unit of measurement *</span><input type="text" name="unit" value="<?= e($v['unit'] ?? 'pc') ?>" required maxlength="20" placeholder="pc, set, bottle..."></label>
  <label class="field full"><span>Description</span><textarea name="description" rows="3"><?= e($v['description'] ?? '') ?></textarea></label>
</div>
<?php }
