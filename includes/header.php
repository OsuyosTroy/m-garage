<?php
// Usage: set $pageTitle, then require this file (after auth.php).
require_once __DIR__ . '/auth.php';
require_login();
$pageTitle = $pageTitle ?? 'M-Garage';
$user = current_user();
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> | <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body>
<div class="app">
<?php require __DIR__ . '/sidebar.php'; ?>
<div class="main">
  <header class="topbar">
    <button class="icon-btn" id="menuToggle" aria-label="Toggle menu"><i class="fa-solid fa-bars"></i></button>
    <h1 class="page-title"><?= e($pageTitle) ?></h1>
    <div class="topbar-user">
      <span class="user-chip"><i class="fa-solid fa-circle-user"></i> <?= e($user['full_name']) ?> <em><?= e(ucfirst($user['role'])) ?></em></span>
      <a class="btn btn-sm btn-outline" href="<?= e(url('change_password.php')) ?>" title="Change password"><i class="fa-solid fa-key"></i></a>
    </div>
  </header>
  <main class="content">
  <?php foreach (get_flashes() as $f): ?>
    <div class="alert alert-<?= e($f['type']) ?>" role="alert"><?= e($f['msg']) ?><button class="alert-close" type="button">&times;</button></div>
  <?php endforeach; ?>
