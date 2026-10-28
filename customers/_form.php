<?php
function customer_validate(): array {
    $d = ['full_name' => input('full_name'), 'contact_number' => input('contact_number'), 'address' => input('address'), 'notes' => input('notes')];
    $er = [];
    if ($d['full_name'] === '' || mb_strlen($d['full_name']) > 150) $er[] = 'Full name is required (max 150 characters).';
    if ($d['contact_number'] !== '' && !preg_match('/^[0-9+\-\s()]{5,30}$/', $d['contact_number'])) $er[] = 'Contact number format is invalid.';
    return [$d, $er];
}
function customer_form(array $v): void { ?>
<div class="form-grid">
  <label class="field"><span>Full name *</span><input type="text" name="full_name" value="<?= e($v['full_name'] ?? '') ?>" required maxlength="150"></label>
  <label class="field"><span>Contact number</span><input type="text" name="contact_number" value="<?= e($v['contact_number'] ?? '') ?>" maxlength="30"></label>
  <label class="field full"><span>Address</span><input type="text" name="address" value="<?= e($v['address'] ?? '') ?>" maxlength="255"></label>
  <label class="field full"><span>Notes</span><textarea name="notes" rows="2"><?= e($v['notes'] ?? '') ?></textarea></label>
</div>
<?php }
