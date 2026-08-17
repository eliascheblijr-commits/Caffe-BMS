<?php

declare(strict_types=1);

require_once __DIR__ . '/../private/includes/bootstrap.php';
require_once CONTROLLERS_PATH . '/MenuController.php';
require_once CONTROLLERS_PATH . '/StaffController.php';
require_once CONTROLLERS_PATH . '/ReportController.php';

require_role(ROLE_ADMIN, ROLE_MANAGER);

$user = current_user();
$activeCafeId = $user['active_cafe_id'];
$franchiseId = $user['franchise_id'];
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('Invalid request.');
    }

    if ($activeCafeId !== null) {
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'add_category') {
            create_menu_category(db(), $activeCafeId, (string) ($_POST['name'] ?? ''));
        } elseif ($action === 'add_item') {
            $categoryId = (($_POST['category_id'] ?? '') !== '') ? (int) $_POST['category_id'] : null;
            create_menu_item(
                db(),
                $activeCafeId,
                $categoryId,
                (string) ($_POST['name'] ?? ''),
                trim((string) ($_POST['description'] ?? '')),
                (string) ($_POST['price'] ?? '0')
            );
        } elseif ($action === 'toggle_item') {
            toggle_menu_item_availability(db(), $activeCafeId, (int) ($_POST['item_id'] ?? 0));
        } elseif ($action === 'add_staff') {
            $created = create_staff_member(
                db(),
                $franchiseId,
                $activeCafeId,
                (string) ($_POST['full_name'] ?? ''),
                (string) ($_POST['email'] ?? ''),
                (string) ($_POST['phone'] ?? ''),
                (string) ($_POST['password'] ?? ''),
                (string) ($_POST['role'] ?? ''),
                $user['role']
            );
            if ($created === null) {
                $error = 'Could not add that staff member — check the email is unique, the password is at least 8 characters, and the role is valid.';
            }
        } elseif ($action === 'reset_password') {
            $targetUserId = (int) ($_POST['user_id'] ?? 0);
            $newPassword = (string) ($_POST['new_password'] ?? '');

            if ($targetUserId > 0 && !reset_staff_password(db(), $franchiseId, $activeCafeId, $targetUserId, $newPassword, $user['role'])) {
                $error = 'Could not reset that password — check it\'s at least 8 characters and you have permission.';
            }
        }
    }

    if ($error === null) {
        header('Location: /manager_dashboard.php');
        exit;
    }
}

$stats = $activeCafeId !== null
    ? today_snapshot(db(), $activeCafeId)
    : ['revenue_today' => 0, 'orders_today' => 0, 'active_orders' => 0];
$menuItems = $activeCafeId !== null ? list_menu_admin(db(), $activeCafeId) : [];
$categories = $activeCafeId !== null ? list_menu_categories(db(), $activeCafeId) : [];
$staff = $activeCafeId !== null ? list_staff(db(), $franchiseId, $activeCafeId) : [];
$assignableRoles = list_assignable_roles(db(), $user['role']);

require VIEWS_PATH . '/manager_dashboard.view.php';
