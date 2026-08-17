<?php

declare(strict_types=1);

require_once __DIR__ . '/../private/includes/bootstrap.php';
require_once CONTROLLERS_PATH . '/OrderController.php';
require_once CONTROLLERS_PATH . '/MenuController.php';

require_role(ROLE_ADMIN, ROLE_CASHIER);

$user = current_user();
$activeCafeId = $user['active_cafe_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('Invalid request.');
    }

    if ($activeCafeId !== null) {
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'create_order') {
            $cartData = json_decode((string) ($_POST['cart_data'] ?? '[]'), true);
            if (is_array($cartData)) {
                create_order(
                    db(),
                    $activeCafeId,
                    $user['id'],
                    trim((string) ($_POST['customer_name'] ?? '')),
                    trim((string) ($_POST['customer_contact'] ?? '')),
                    $cartData
                );
            }
        } elseif ($action === 'complete_payment') {
            $orderId = (int) ($_POST['order_id'] ?? 0);
            $method = (string) ($_POST['method'] ?? 'cash');
            $payerName = trim((string) ($_POST['payer_name'] ?? ''));

            if ($orderId > 0) {
                complete_order_with_payment(db(), $orderId, $activeCafeId, $method, $user['id'], $payerName);
            }
        }
    }

    header('Location: /cashier_dashboard.php');
    exit;
}

$menuCategories = $activeCafeId !== null ? list_menu_by_category(db(), $activeCafeId) : [];
$readyOrders = $activeCafeId !== null ? list_ready_orders(db(), $activeCafeId) : [];

require VIEWS_PATH . '/cashier_dashboard.view.php';
