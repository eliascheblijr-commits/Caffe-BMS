<?php

declare(strict_types=1);

require_once __DIR__ . '/../private/includes/bootstrap.php';
require_once CONTROLLERS_PATH . '/OrderController.php';

require_role(ROLE_ADMIN, ROLE_BARISTA);

$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('Invalid request.');
    }

    $orderId = (int) ($_POST['order_id'] ?? 0);
    $newStatus = (string) ($_POST['new_status'] ?? '');

    if ($orderId > 0 && $newStatus !== '' && $user['cafe_id'] !== null) {
        advance_order_status(db(), $orderId, $user['cafe_id'], $newStatus);
    }

    header('Location: /Barista_dashboard.php');
    exit;
}

$orders = $user['cafe_id'] !== null ? list_active_orders(db(), $user['cafe_id']) : [];

require VIEWS_PATH . '/barista_dashboard.view.php';
