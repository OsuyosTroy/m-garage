<?php
function mechanic_validate(): array {
    $d = ['full_name' => input('full_name'), 'contact_number' => input('contact_number'), 'specialty' => input('specialty'), 'status' => input('employment_status', 'active'), 'notes' => input('notes')];
    $er = [];
    if ($d['full_name'] === '' || mb_strlen($d['full_name']) > 150) $er[] = 'Full name is required.';
    if ($d['contact_number'] !== '' && !preg_match('/^[0-9+\-\s()]{5,30}$/', $d['contact_number'])) $er[] = 'Contact number format is invalid.';
    if (!in_array($d['status'], ['active', 'inactive'], true)) $er[] = 'Invalid employment status.';
    return [$d, $er];
}
function mechanic_form(array $v): void { ?>
<div class="form-grid">
  <label class="field"><span>Full name *</span><input type="text" name="full_name" value="<?= e($v['full_name'] ?? '') ?>" required></label>
  <label class="field"><span>Contact number</span><input type="text" name="contact_number" value="<?= e($v['contact_number'] ?? '') ?>"></label>
  <label class="field"><span>Specialty / skills</span><input type="text" name="specialty" value="<?= e($v['specialty'] ?? '') ?>"></label>
  <label class="field"><span>Employment status</span><select name="employment_status"><?php foreach (['active', 'inactive'] as $s): ?><option value="<?= $s ?>" <?= ($v['employment_status'] ?? 'active') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select></label>
  <label class="field full"><span>Notes</span><textarea name="notes" rows="2"><?= e($v['notes'] ?? '') ?></textarea></label>
</div>
<?php }
