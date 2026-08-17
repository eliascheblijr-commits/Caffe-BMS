<?php

/**
 * Switches the admin/owner's active branch (see AuthController and
 * CafeController::list_cafes_for_franchise()). POST-only, admin-only —
 * branch-pinned roles never call this since they have nothing to switch.
 */

declare(strict_types=1);

require_once __DIR__ . '/../private/includes/bootstrap.php';
require_once CONTROLLERS_PATH . '/CafeController.php';

require_role(ROLE_ADMIN);

// Whitelisted so this can't be turned into an open redirect.
const SWITCH_CAFE_ALLOWED_REDIRECTS = [
    'manager_dashboard.php',
    'cashier_dashboard.php',
    'Barista_dashboard.php',
];

$user = current_user();
$redirectTo = (string) ($_POST['redirect_to'] ?? '');
if (!in_array($redirectTo, SWITCH_CAFE_ALLOWED_REDIRECTS, true)) {
    $redirectTo = 'manager_dashboard.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify($_POST['csrf_token'] ?? null)) {
    $requestedCafeId = (int) ($_POST['cafe_id'] ?? 0);

    if ($requestedCafeId > 0 && cafe_belongs_to_franchise(db(), $requestedCafeId, $user['franchise_id'])) {
        $_SESSION['user']['active_cafe_id'] = $requestedCafeId;
    }
}

header('Location: /' . $redirectTo);
exit;
