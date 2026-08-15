<?php

/**
 * ReportController — quick read-only stats for the manager dashboard
 * overview. Anything heavier (date-range exports, per-item breakdowns)
 * belongs in a later, dedicated reports screen — this is just today at a
 * glance.
 */

declare(strict_types=1);

function today_snapshot(PDO $db, int $cafeId): array
{
    $revenueStmt = $db->prepare(
        "SELECT COALESCE(SUM(fl.amount), 0)
         FROM financial_ledger fl
         JOIN ledger_account_types lat ON lat.id = fl.account_type_id
         WHERE fl.cafe_id = :cafe_id AND lat.name = 'revenue' AND DATE(fl.created_at) = CURDATE()"
    );
    $revenueStmt->execute([':cafe_id' => $cafeId]);
    $revenueToday = $revenueStmt->fetchColumn();

    $ordersStmt = $db->prepare(
        'SELECT COUNT(*) FROM orders WHERE cafe_id = :cafe_id AND DATE(created_at) = CURDATE()'
    );
    $ordersStmt->execute([':cafe_id' => $cafeId]);
    $ordersToday = (int) $ordersStmt->fetchColumn();

    $activeStmt = $db->prepare(
        "SELECT COUNT(*) FROM orders WHERE cafe_id = :cafe_id AND status IN ('queued','preparing','ready')"
    );
    $activeStmt->execute([':cafe_id' => $cafeId]);
    $activeOrders = (int) $activeStmt->fetchColumn();

    return [
        'revenue_today' => $revenueToday,
        'orders_today' => $ordersToday,
        'active_orders' => $activeOrders,
    ];
}
