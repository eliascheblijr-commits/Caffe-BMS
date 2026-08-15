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

/**
 * Flat list of every menu item for this cafe — including unavailable ones —
 * for the manager's menu-management screen. Unlike list_menu_by_category(),
 * nothing is filtered out here.
 */
function list_menu_admin(PDO $db, int $cafeId): array
{
    $stmt = $db->prepare(
        'SELECT mi.id, mi.name, mi.description, mi.price, mi.is_available, mc.name AS category_name
         FROM menu_items mi
         LEFT JOIN menu_categories mc ON mc.id = mi.category_id
         WHERE mi.cafe_id = :cafe_id
         ORDER BY mc.name ASC, mi.name ASC'
    );
    $stmt->execute([':cafe_id' => $cafeId]);
    return $stmt->fetchAll();
}

function list_menu_categories(PDO $db, int $cafeId): array
{
    $stmt = $db->prepare('SELECT id, name FROM menu_categories WHERE cafe_id = :cafe_id ORDER BY name ASC');
    $stmt->execute([':cafe_id' => $cafeId]);
    return $stmt->fetchAll();
}

function create_menu_category(PDO $db, int $cafeId, string $name): ?int
{
    $name = trim($name);
    if ($name === '') {
        return null;
    }

    $stmt = $db->prepare('INSERT INTO menu_categories (cafe_id, name) VALUES (:cafe_id, :name)');
    $stmt->execute([':cafe_id' => $cafeId, ':name' => $name]);

    return (int) $db->lastInsertId();
}

/**
 * $price is a raw string from a form field — validated as numeric here
 * rather than trusted.
 */
function create_menu_item(
    PDO $db,
    int $cafeId,
    ?int $categoryId,
    string $name,
    string $description,
    string $price
): ?int {
    $name = trim($name);
    if ($name === '' || !is_numeric($price) || (float) $price < 0) {
        return null;
    }

    if ($categoryId !== null) {
        $check = $db->prepare('SELECT id FROM menu_categories WHERE id = :id AND cafe_id = :cafe_id');
        $check->execute([':id' => $categoryId, ':cafe_id' => $cafeId]);
        if (!$check->fetchColumn()) {
            $categoryId = null; // not this cafe's category — drop it rather than fail the whole request
        }
    }

    $stmt = $db->prepare(
        'INSERT INTO menu_items (cafe_id, category_id, name, description, price, is_available)
         VALUES (:cafe_id, :category_id, :name, :description, :price, 1)'
    );
    $stmt->execute([
        ':cafe_id' => $cafeId,
        ':category_id' => $categoryId,
        ':name' => $name,
        ':description' => $description !== '' ? $description : null,
        ':price' => number_format((float) $price, 2, '.', ''),
    ]);

    return (int) $db->lastInsertId();
}

function toggle_menu_item_availability(PDO $db, int $cafeId, int $itemId): bool
{
    $stmt = $db->prepare(
        'UPDATE menu_items SET is_available = NOT is_available WHERE id = :id AND cafe_id = :cafe_id'
    );

    return $stmt->execute([':id' => $itemId, ':cafe_id' => $cafeId]);
}
