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
