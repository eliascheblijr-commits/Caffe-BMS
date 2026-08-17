<?php
/**
 * @var array $user
 * @var array $stats            from today_snapshot()
 * @var array $menuItems        from list_menu_admin()
 * @var array $categories       from list_menu_categories()
 * @var array $staff            from list_staff()
 * @var array $assignableRoles  from list_assignable_roles()
 * @var string|null $error
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager — Caffe BMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Caveat:wght@700&family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/style.css">
</head>
<body class="staff-page">
    <?php require VIEWS_PATH . '/partials/staff_header.view.php'; ?>

    <main class="staff-main">
        <h1>Manager Overview</h1>

        <?php if ($error !== null): ?>
            <div class="auth-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <div class="stat-cards">
            <div class="stat-card">
                <div class="stat-card-label">Revenue Today</div>
                <div class="stat-card-value">$<?= number_format((float) $stats['revenue_today'], 2) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-card-label">Orders Today</div>
                <div class="stat-card-value"><?= (int) $stats['orders_today'] ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-card-label">Active Orders</div>
                <div class="stat-card-value"><?= (int) $stats['active_orders'] ?></div>
            </div>
        </div>

        <h2 class="section-title">Menu</h2>

        <form method="post" action="/manager_dashboard.php" class="inline-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="add_category">
            <div class="field">
                <label for="category_name">New category</label>
                <input type="text" id="category_name" name="name" placeholder="e.g. Espresso" required maxlength="100">
            </div>
            <button type="submit" class="btn-primary">Add Category</button>
        </form>

        <form method="post" action="/manager_dashboard.php" class="inline-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="add_item">
            <div class="field">
                <label for="item_name">Item name</label>
                <input type="text" id="item_name" name="name" required maxlength="200">
            </div>
            <div class="field">
                <label for="item_category">Category</label>
                <select id="item_category" name="category_id">
                    <option value="">Uncategorized</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= (int) $cat['id'] ?>"><?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="item_price">Price</label>
                <input type="number" id="item_price" name="price" step="0.01" min="0" required>
            </div>
            <div class="field">
                <label for="item_description">Description (optional)</label>
                <input type="text" id="item_description" name="description" maxlength="255">
            </div>
            <button type="submit" class="btn-primary">Add Item</button>
        </form>

        <div class="menu-admin-list">
            <?php if (empty($menuItems)): ?>
                <div class="staff-empty">No menu items yet.</div>
            <?php else: ?>
                <?php foreach ($menuItems as $item): ?>
                    <div class="menu-admin-row<?= $item['is_available'] ? '' : ' is-unavailable' ?>">
                        <div class="menu-admin-info">
                            <span class="menu-admin-name"><?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="menu-admin-meta">
                                <?= htmlspecialchars($item['category_name'] ?? 'Uncategorized', ENT_QUOTES, 'UTF-8') ?>
                                &middot; $<?= number_format((float) $item['price'], 2) ?>
                            </span>
                        </div>
                        <form method="post" action="/manager_dashboard.php">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="action" value="toggle_item">
                            <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                            <button type="submit" class="btn-toggle">
                                <?= $item['is_available'] ? 'Mark Unavailable' : 'Mark Available' ?>
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <h2 class="section-title">Staff</h2>

        <form method="post" action="/manager_dashboard.php" class="inline-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="add_staff">
            <div class="field">
                <label for="staff_name">Full name</label>
                <input type="text" id="staff_name" name="full_name" required maxlength="100">
            </div>
            <div class="field">
                <label for="staff_email">Email</label>
                <input type="email" id="staff_email" name="email" required maxlength="100">
            </div>
            <div class="field">
                <label for="staff_phone">Phone</label>
                <input type="text" id="staff_phone" name="phone" required maxlength="20">
            </div>
            <div class="field">
                <label for="staff_password">Temporary password</label>
                <input type="text" id="staff_password" name="password" required minlength="8" maxlength="100">
            </div>
            <div class="field">
                <label for="staff_role">Role</label>
                <select id="staff_role" name="role">
                    <?php foreach ($assignableRoles as $role): ?>
                        <option value="<?= htmlspecialchars($role['name'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(ucfirst($role['name']), ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn-primary">Add Staff</button>
        </form>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Branch</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($staff as $member): ?>
                    <tr>
                        <td><?= htmlspecialchars($member['full_name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($member['email'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars(ucfirst($member['role_name']), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= $member['cafe_id'] === null ? 'All branches' : 'This branch' ?></td>
                        <td><span class="status-pill status-pill--<?= $member['status'] === 'active' ? 'active' : 'inactive' ?>"><?= htmlspecialchars($member['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                        <td>
                            <button type="button" class="btn-toggle js-toggle" data-target="reset-<?= (int) $member['id'] ?>">Reset Password</button>
                            <form method="post" action="/manager_dashboard.php" class="reset-password-form" id="reset-<?= (int) $member['id'] ?>" hidden>
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="action" value="reset_password">
                                <input type="hidden" name="user_id" value="<?= (int) $member['id'] ?>">
                                <input type="text" name="new_password" placeholder="New password" minlength="8" required>
                                <button type="submit" class="btn-toggle">Confirm</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </main>

    <script src="/js/main.js"></script>
</body>
</html>
