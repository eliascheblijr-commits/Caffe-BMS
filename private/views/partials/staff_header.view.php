<?php
/**
 * Shared header for every authenticated staff page.
 * @var array $user  current_user() — always set, dashboards require login first.
 */
?>
<header class="staff-header">
    <div class="staff-header-left">
        <span class="staff-header-mark">Caffe BMS</span>
    </div>
    <div class="staff-header-right">
        <span class="staff-header-user">
            <?= htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8') ?>
            · <?= htmlspecialchars(ucfirst($user['role']), ENT_QUOTES, 'UTF-8') ?>
        </span>
        <a href="/logout.php" class="staff-header-logout">Sign out</a>
    </div>
</header>
