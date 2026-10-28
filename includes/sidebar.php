<?php
$role = current_user()['role'];
$current = trim(str_replace(BASE_URL, '', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)), '/');
?>
<aside class="sidebar" id="sidebar">
  <a class="brand" href="<?= e(url('dashboard.php')) ?>"><i class="fa-solid fa-motorcycle"></i><span>M-<b>Garage</b></span></a>
  <nav>
  <?php foreach (modules() as [$label, $icon, $path, $roles]):
        if (!in_array($role, $roles, true)) continue;                       // hide unauthorized modules
        $exists = file_exists(__DIR__ . '/../' . $path);
        $active = ($current === $path || ($path === 'customers/index.php' && strpos($current, 'customers/') === 0)) ? ' active' : ''; ?>
    <?php if ($exists): ?>
      <a class="nav-link<?= $active ?>" href="<?= e(url($path)) ?>"><i class="fa-solid <?= e($icon) ?>"></i><span><?= e($label) ?></span></a>
    <?php else: ?>
      <span class="nav-link disabled" title="Coming in a later build stage"><i class="fa-solid <?= e($icon) ?>"></i><span><?= e($label) ?></span></span>
    <?php endif; ?>
  <?php endforeach; ?>
    <a class="nav-link logout" href="<?= e(url('logout.php')) ?>" data-confirm="Log out of M-Garage?"><i class="fa-solid fa-right-from-bracket"></i><span>Logout</span></a>
  </nav>
</aside>
