<?php

/**
 * MenuController — read access to a cafe's menu, grouped by category.
 * Used by the cashier's order-entry screen (and later the public menu page).
 */

declare(strict_types=1);

/**
 * Returns available menu items for $cafeId, grouped by category:
 * [ ['id' => ?int, 'name' => string, 'items' => [['id','name','price','description'], ...]], ... ]
 * Items with no category land in a trailing "Other" group.
 */
function list_menu_by_category(PDO $db, int $cafeId): array
{
    $stmt = $db->prepare(
        'SELECT mc.id AS category_id, mc.name AS category_name,
                mi.id AS item_id, mi.name AS item_name, mi.price, mi.description
         FROM menu_categories mc
         JOIN menu_items mi ON mi.category_id = mc.id AND mi.is_available = 1
         WHERE mc.cafe_id = :cafe_id
         ORDER BY mc.name ASC, mi.name ASC'
    );
    $stmt->execute([':cafe_id' => $cafeId]);

    $categories = [];
    foreach ($stmt->fetchAll() as $row) {
        $catId = (int) $row['category_id'];
        if (!isset($categories[$catId])) {
            $categories[$catId] = [
                'id' => $catId,
                'name' => $row['category_name'],
                'items' => [],
            ];
        }
        $categories[$catId]['items'][] = [
            'id' => (int) $row['item_id'],
            'name' => $row['item_name'],
            'price' => $row['price'],
            'description' => $row['description'],
        ];
    }

    $stmt = $db->prepare(
        'SELECT id, name, price, description
         FROM menu_items
         WHERE cafe_id = :cafe_id AND category_id IS NULL AND is_available = 1
         ORDER BY name ASC'
    );
    $stmt->execute([':cafe_id' => $cafeId]);
    $uncategorized = $stmt->fetchAll();

    if ($uncategorized) {
        $categories['uncategorized'] = [
            'id' => null,
            'name' => 'Other',
            'items' => array_map(
                static fn (array $i): array => [
                    'id' => (int) $i['id'],
                    'name' => $i['name'],
                    'price' => $i['price'],
                    'description' => $i['description'],
                ],
                $uncategorized
            ),
        ];
    }

    return array_values($categories);
}
