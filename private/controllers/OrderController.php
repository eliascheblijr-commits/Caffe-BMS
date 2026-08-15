<?php

/**
 * OrderController — order queue + status transitions, shared by the
 * barista/kitchen and cashier dashboards.
 *
 * Every query is scoped by cafe_id — this is a multi-tenant system and no
 * query here should ever run without it.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/ledger.php';

const ORDER_STATUS_TRANSITIONS = [
    'queued' => ['preparing', 'cancelled'],
    'preparing' => ['ready', 'cancelled'],
    'ready' => ['completed'],
];

/**
 * Orders still in the kitchen pipeline (not yet ready/completed/cancelled),
 * oldest first, each with its line items attached.
 */
function list_active_orders(PDO $db, int $cafeId): array
{
    $stmt = $db->prepare(
        "SELECT id, customer_name, status, total_amount, created_at
         FROM orders
         WHERE cafe_id = :cafe_id AND status IN ('queued','preparing')
         ORDER BY created_at ASC"
    );
    $stmt->execute([':cafe_id' => $cafeId]);
    $orders = $stmt->fetchAll();

    if (!$orders) {
        return [];
    }

    $orderIds = array_column($orders, 'id');
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));

    $itemsStmt = $db->prepare(
        "SELECT oi.order_id, oi.quantity, oi.notes, mi.name
         FROM order_items oi
         JOIN menu_items mi ON mi.id = oi.menu_item_id
         WHERE oi.order_id IN ($placeholders)
         ORDER BY oi.id ASC"
    );
    $itemsStmt->execute($orderIds);
    $items = $itemsStmt->fetchAll();

    $itemsByOrder = [];
    foreach ($items as $item) {
        $itemsByOrder[$item['order_id']][] = $item;
    }

    foreach ($orders as &$order) {
        $order['items'] = $itemsByOrder[$order['id']] ?? [];
    }
    unset($order);

    return $orders;
}

/**
 * Moves an order to $newStatus if that's a legal transition from its current
 * status, and the order actually belongs to $cafeId. Returns false silently
 * on any invalid attempt — callers don't need to distinguish why.
 */
function advance_order_status(PDO $db, int $orderId, int $cafeId, string $newStatus): bool
{
    $stmt = $db->prepare('SELECT status FROM orders WHERE id = :id AND cafe_id = :cafe_id LIMIT 1');
    $stmt->execute([':id' => $orderId, ':cafe_id' => $cafeId]);
    $current = $stmt->fetchColumn();

    if ($current === false) {
        return false;
    }

    $allowed = ORDER_STATUS_TRANSITIONS[$current] ?? [];
    if (!in_array($newStatus, $allowed, true)) {
        return false;
    }

    $update = $db->prepare('UPDATE orders SET status = :status WHERE id = :id AND cafe_id = :cafe_id');
    return $update->execute([
        ':status' => $newStatus,
        ':id' => $orderId,
        ':cafe_id' => $cafeId,
    ]);
}

/**
 * Creates a new order with line items. $lineItems is an array of
 * ['menu_item_id' => int, 'quantity' => int, 'notes' => string|null].
 * Every item is re-validated server-side against this cafe's live menu
 * (price, availability) rather than trusting anything the client sent.
 * Returns the new order id, or null if validation failed.
 */
function create_order(
    PDO $db,
    int $cafeId,
    int $recordedBy,
    ?string $customerName,
    ?string $customerContact,
    array $lineItems
): ?int {
    $lineItems = array_values(array_filter($lineItems, fn ($li) => (int) ($li['quantity'] ?? 0) > 0));
    if (empty($lineItems)) {
        return null;
    }

    $itemIds = array_map(fn ($li) => (int) $li['menu_item_id'], $lineItems);
    $placeholders = implode(',', array_fill(0, count($itemIds), '?'));

    $stmt = $db->prepare(
        "SELECT id, price FROM menu_items
         WHERE cafe_id = ? AND is_available = 1 AND id IN ($placeholders)"
    );
    $stmt->execute([$cafeId, ...$itemIds]);
    $priceById = [];
    foreach ($stmt->fetchAll() as $row) {
        $priceById[(int) $row['id']] = $row['price'];
    }

    foreach ($itemIds as $id) {
        if (!isset($priceById[$id])) {
            return null; // item doesn't exist, isn't this cafe's, or isn't available
        }
    }

    $db->beginTransaction();
    try {
        $orderStmt = $db->prepare(
            "INSERT INTO orders (cafe_id, customer_name, customer_contact, status, total_amount, payment_status, recorded_by)
             VALUES (:cafe_id, :customer_name, :customer_contact, 'queued', 0, 'pending', :recorded_by)"
        );
        $orderStmt->execute([
            ':cafe_id' => $cafeId,
            ':customer_name' => ($customerName !== null && $customerName !== '') ? $customerName : null,
            ':customer_contact' => ($customerContact !== null && $customerContact !== '') ? $customerContact : null,
            ':recorded_by' => $recordedBy,
        ]);
        $orderId = (int) $db->lastInsertId();

        $itemStmt = $db->prepare(
            'INSERT INTO order_items (order_id, menu_item_id, quantity, unit_price, total_price, notes)
             VALUES (:order_id, :menu_item_id, :quantity, :unit_price, :total_price, :notes)'
        );

        $total = '0.00';
        foreach ($lineItems as $li) {
            $itemId = (int) $li['menu_item_id'];
            $qty = (int) $li['quantity'];
            $unitPrice = (string) $priceById[$itemId];
            $lineTotal = bcmul($unitPrice, (string) $qty, 2);
            $total = bcadd($total, $lineTotal, 2);

            $itemStmt->execute([
                ':order_id' => $orderId,
                ':menu_item_id' => $itemId,
                ':quantity' => $qty,
                ':unit_price' => $unitPrice,
                ':total_price' => $lineTotal,
                ':notes' => trim((string) ($li['notes'] ?? '')) ?: null,
            ]);
        }

        $updateStmt = $db->prepare('UPDATE orders SET total_amount = :total WHERE id = :id');
        $updateStmt->execute([':total' => $total, ':id' => $orderId]);

        $db->commit();
        return $orderId;
    } catch (Throwable $e) {
        $db->rollBack();
        error_log('[caffe-bms] create_order failed: ' . $e->getMessage());
        return null;
    }
}

/**
 * Orders the kitchen has finished and the cashier still needs to hand off
 * and collect payment for.
 */
function list_ready_orders(PDO $db, int $cafeId): array
{
    $stmt = $db->prepare(
        "SELECT id, customer_name, customer_contact, total_amount, payment_status, created_at
         FROM orders
         WHERE cafe_id = :cafe_id AND status = 'ready'
         ORDER BY created_at ASC"
    );
    $stmt->execute([':cafe_id' => $cafeId]);
    return $stmt->fetchAll();
}

/**
 * Marks a 'ready' order as picked up and paid: validates the transition,
 * records a payment row, updates the order, and posts a hash-chained
 * ledger entry — all in one transaction. This bypasses
 * ORDER_STATUS_TRANSITIONS/advance_order_status on purpose: this specific
 * transition has to carry a payment with it, which that generic helper
 * doesn't handle.
 */
function complete_order_with_payment(
    PDO $db,
    int $orderId,
    int $cafeId,
    string $method,
    int $recordedBy,
    ?string $payerName
): bool {
    $db->beginTransaction();
    try {
        $stmt = $db->prepare(
            'SELECT status, total_amount FROM orders WHERE id = :id AND cafe_id = :cafe_id FOR UPDATE'
        );
        $stmt->execute([':id' => $orderId, ':cafe_id' => $cafeId]);
        $order = $stmt->fetch();

        if (!$order || $order['status'] !== 'ready') {
            $db->rollBack();
            return false;
        }

        $paymentStmt = $db->prepare(
            "INSERT INTO payments (cafe_id, order_id, payer_name, recorded_by, amount, method, status)
             VALUES (:cafe_id, :order_id, :payer_name, :recorded_by, :amount, :method, 'approved')"
        );
        $paymentStmt->execute([
            ':cafe_id' => $cafeId,
            ':order_id' => $orderId,
            ':payer_name' => ($payerName !== null && $payerName !== '') ? $payerName : null,
            ':recorded_by' => $recordedBy,
            ':amount' => $order['total_amount'],
            ':method' => $method,
        ]);
        $paymentId = (int) $db->lastInsertId();

        $updateOrder = $db->prepare(
            "UPDATE orders SET status = 'completed', payment_status = 'paid' WHERE id = :id AND cafe_id = :cafe_id"
        );
        $updateOrder->execute([':id' => $orderId, ':cafe_id' => $cafeId]);

        record_ledger_entry($db, $cafeId, 'sale', 'revenue', $order['total_amount'], 'payments', $paymentId, $recordedBy);

        $db->commit();
        return true;
    } catch (Throwable $e) {
        $db->rollBack();
        error_log('[caffe-bms] complete_order_with_payment failed: ' . $e->getMessage());
        return false;
    }
}
