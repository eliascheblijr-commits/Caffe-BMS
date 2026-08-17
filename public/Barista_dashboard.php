<?php

declare(strict_types=1);

require_once __DIR__ . '/../private/includes/bootstrap.php';
require_once CONTROLLERS_PATH . '/OrderController.php';

require_role(ROLE_ADMIN, ROLE_BARISTA);

$user = current_user();
$activeCafeId = $user['active_cafe_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('Invalid request.');
    }

    $orderId = (int) ($_POST['order_id'] ?? 0);
    $newStatus = (string) ($_POST['new_status'] ?? '');

    if ($orderId > 0 && $newStatus !== '' && $activeCafeId !== null) {
        advance_order_status(db(), $orderId, $activeCafeId, $newStatus);
    }

    header('Location: /Barista_dashboard.php');
    exit;
}

$orders = $activeCafeId !== null ? list_active_orders(db(), $activeCafeId) : [];

require VIEWS_PATH . '/barista_dashboard.view.php';
