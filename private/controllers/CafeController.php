<?php

/**
 * CafeController — public-facing café lookup for the unauthenticated menu
 * page. Nothing here requires a session or a role.
 */

declare(strict_types=1);

/**
 * Resolves which café's public menu to show.
 *   - If $slug is given, look it up directly (this is the real, scalable path).
 *   - If $slug is empty and exactly one active café exists system-wide,
 *     show that one — convenient before a slug is worth bothering with.
 *     Once there's more than one café, an empty slug stops resolving.
 */
function resolve_public_cafe(PDO $db, string $slug): ?array
{
    if ($slug !== '') {
        $stmt = $db->prepare(
            "SELECT id, name, address, contact_phone FROM cafes WHERE slug = :slug AND status = 'active' LIMIT 1"
        );
        $stmt->execute([':slug' => $slug]);
        $cafe = $stmt->fetch();
        return $cafe ?: null;
    }

    $stmt = $db->query("SELECT id, name, address, contact_phone FROM cafes WHERE status = 'active' LIMIT 2");
    $cafes = $stmt->fetchAll();

    return count($cafes) === 1 ? $cafes[0] : null;
}

/**
 * All cafes under a franchise, for the admin/owner branch switcher.
 * Ordered by name so the dropdown and the "default active branch on login"
 * logic in AuthController agree on which one is "first".
 */
function list_cafes_for_franchise(PDO $db, int $franchiseId): array
{
    $stmt = $db->prepare(
        "SELECT id, name FROM cafes WHERE franchise_id = :franchise_id AND status = 'active' ORDER BY name ASC"
    );
    $stmt->execute([':franchise_id' => $franchiseId]);
    return $stmt->fetchAll();
}

/**
 * Validates that $cafeId is actually part of $franchiseId before letting a
 * switch-branch request set it as the active cafe — without this, an
 * admin could switch into another franchise's data by guessing an id.
 */
function cafe_belongs_to_franchise(PDO $db, int $cafeId, int $franchiseId): bool
{
    $stmt = $db->prepare('SELECT id FROM cafes WHERE id = :id AND franchise_id = :franchise_id LIMIT 1');
    $stmt->execute([':id' => $cafeId, ':franchise_id' => $franchiseId]);
    return (bool) $stmt->fetchColumn();
}
