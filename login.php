<?php
require_once __DIR__ . '/includes/auth.php';
if (is_logged_in()) redirect('dashboard.php');

$error = '';
if (is_post()) {
    require_csrf();
    $username = input('username');
    $password = (string)($_POST['password'] ?? '');
    if ($username === '' || $password === '') {
        $error = 'Please enter your username and password.';
    } else {
        $error = attempt_login($username, $password) ?? '';
        if ($error === '') redirect('dashboard.php');
    }
}
$flashes = get_flashes();
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login | M-Garage</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body class="login-body">
<div class="login-card">
  <div class="login-brand"><i class="fa-solid fa-motorcycle"></i><h1>M-<b>Garage</b></h1><p>Inventory and Service Management System</p></div>
  <?php foreach ($flashes as $f): ?><div class="alert alert-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div><?php endforeach; ?>
  <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
  <form method="post" autocomplete="off" novalidate>
    <?= csrf_field() ?>
    <label class="field"><span>Username</span>
      <div class="input-icon"><i class="fa-solid fa-user"></i><input type="text" name="username" value="<?= e($_POST['username'] ?? '') ?>" required autofocus></div></label>
    <label class="field"><span>Password</span>
      <div class="input-icon"><i class="fa-solid fa-lock"></i><input type="password" name="password" required></div></label>
    <button class="btn btn-primary btn-block" type="submit"><i class="fa-solid fa-right-to-bracket"></i> Log in</button>
  </form>
  <p class="login-foot">Poblacion, Inabanga, Bohol</p>
</div>
</body>
</html>
