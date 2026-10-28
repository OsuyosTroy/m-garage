<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
$keys = ['shop_name' => 'Shop name', 'shop_address' => 'Address', 'shop_contact' => 'Contact number', 'receipt_footer' => 'Receipt footer message'];
$errors = [];
if (is_post()) {
    require_csrf();
    $vals = []; foreach ($keys as $k => $l) $vals[$k] = input($k);
    if ($vals['shop_name'] === '') $errors[] = 'Shop name is required.';
    foreach ($vals as $k => $v) if (mb_strlen($v) > 400) $errors[] = "$keys[$k] is too long.";
    if (!$errors) {
        foreach ($vals as $k => $v) db()->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)')->execute([$k, $v]);
        log_activity('settings_updated', 'settings', 'Updated shop settings'); flash('success', 'Settings saved.'); redirect('admin/settings.php');
    }
}
$pageTitle = 'Settings';
require __DIR__ . '/../includes/header.php';
?>
<div class="card" style="max-width:640px"><?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
<form method="post"><?= csrf_field() ?><?php foreach ($keys as $k => $l): ?><label class="field"><span><?= e($l) ?></span><input type="text" name="<?= e($k) ?>" value="<?= e($_POST[$k] ?? setting($k)) ?>"></label><?php endforeach; ?>
<button class="btn btn-primary" type="submit">Save settings</button></form></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
