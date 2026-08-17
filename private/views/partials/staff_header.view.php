<?php
/**
 * Shared header for every authenticated staff page.
 * @var array $user  current_user() — always set, dashboards require login first.
 */

// Branch switcher — admin/owner only (see StaffController scope decision:
// manager/cashier/barista stay pinned to one cafe via users.cafe_id and
// never see this).
$switcherCafes = [];
if ($user['role'] === ROLE_ADMIN) {
    require_once CONTROLLERS_PATH . '/CafeController.php';
    $switcherCafes = list_cafes_for_franchise(db(), $user['franchise_id']);
}
?>
<header class="staff-header">
    <div class="staff-header-left">
        <span class="staff-header-mark">Caffe BMS</span>

        <?php if (count($switcherCafes) > 1): ?>
            <form method="post" action="/switch_cafe.php" class="branch-switcher js-auto-submit">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="redirect_to" value="<?= htmlspecialchars(basename((string) $_SERVER['SCRIPT_NAME']), ENT_QUOTES, 'UTF-8') ?>">
                <label for="branch-select" class="branch-switcher-label">Branch</label>
                <select id="branch-select" name="cafe_id">
                    <?php foreach ($switcherCafes as $cafe): ?>
                        <option value="<?= (int) $cafe['id'] ?>" <?= (int) $cafe['id'] === $user['active_cafe_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cafe['name'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        <?php elseif (count($switcherCafes) === 1): ?>
            <span class="branch-switcher-single"><?= htmlspecialchars($switcherCafes[0]['name'], ENT_QUOTES, 'UTF-8') ?></span>
        <?php endif; ?>
    </div>
    <div class="staff-header-right">
        <span class="staff-header-user">
            <?= htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8') ?>
            · <?= htmlspecialchars(ucfirst($user['role']), ENT_QUOTES, 'UTF-8') ?>
        </span>
        <a href="/logout.php" class="staff-header-logout">Sign out</a>
    </div>
</header>
