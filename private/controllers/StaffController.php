<?php

/**
 * StaffController — staff account management for the manager dashboard.
 *
 * Scoping rule used throughout: a franchise's staff list for a given branch
 * is everyone pinned to that branch (cafe_id = activeCafeId) PLUS every
 * franchise-wide admin/owner (cafe_id IS NULL) — admins show up on every
 * branch's staff list since they oversee all of them.
 */

declare(strict_types=1);

function list_staff(PDO $db, int $franchiseId, int $activeCafeId): array
{
    $stmt = $db->prepare(
        'SELECT u.id, u.full_name, u.email, u.status, u.cafe_id, r.name AS role_name
         FROM users u
         JOIN roles r ON r.id = u.role_id
         WHERE u.franchise_id = :franchise_id
           AND u.deleted_at IS NULL
           AND (u.cafe_id = :cafe_id OR u.cafe_id IS NULL)
         ORDER BY (u.cafe_id IS NULL) DESC, u.full_name ASC'
    );
    $stmt->execute([':franchise_id' => $franchiseId, ':cafe_id' => $activeCafeId]);
    return $stmt->fetchAll();
}

/**
 * Roles a staff member with $creatorRole is allowed to grant. Non-admins
 * can never grant admin — this is the read-side of that guard; the
 * write-side check lives in create_staff_member() too, since the form
 * value can't be trusted just because the dropdown didn't offer it.
 */
function list_assignable_roles(PDO $db, string $creatorRole): array
{
    $roles = $db->query(
        "SELECT id, name FROM roles ORDER BY FIELD(name, 'manager','cashier','barista','admin')"
    )->fetchAll();

    if ($creatorRole !== ROLE_ADMIN) {
        $roles = array_values(array_filter($roles, static fn (array $r): bool => $r['name'] !== ROLE_ADMIN));
    }

    return $roles;
}

/**
 * Creates a new staff account. $cafeId is the branch to pin them to — it's
 * ignored (forced to NULL) when $roleName is admin, since admin/owner
 * operates franchise-wide rather than being pinned to one branch. Returns
 * the new user id, or null on any validation failure (bad email, duplicate
 * email, unknown/disallowed role, password under 8 chars, or a non-admin
 * role with no branch to pin it to) — callers show one generic error either way.
 */
function create_staff_member(
    PDO $db,
    int $franchiseId,
    ?int $cafeId,
    string $fullName,
    string $email,
    string $phone,
    string $password,
    string $roleName,
    string $creatorRole
): ?int {
    $fullName = trim($fullName);
    $email = trim(strtolower($email));
    $phone = trim($phone);

    if ($fullName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
        return null;
    }

    if ($roleName === ROLE_ADMIN && $creatorRole !== ROLE_ADMIN) {
        return null; // non-admins can never grant admin, regardless of what the form sent
    }

    $assignedCafeId = $roleName === ROLE_ADMIN ? null : $cafeId;
    if ($roleName !== ROLE_ADMIN && $assignedCafeId === null) {
        return null; // branch-pinned roles need an actual branch
    }

    $roleStmt = $db->prepare('SELECT id FROM roles WHERE name = :name');
    $roleStmt->execute([':name' => $roleName]);
    $roleId = $roleStmt->fetchColumn();
    if ($roleId === false) {
        return null;
    }

    $dupeStmt = $db->prepare('SELECT id FROM users WHERE email = :email AND deleted_at IS NULL');
    $dupeStmt->execute([':email' => $email]);
    if ($dupeStmt->fetchColumn()) {
        return null;
    }

    $insert = $db->prepare(
        "INSERT INTO users (full_name, email, phone_number, password, franchise_id, cafe_id, role_id, status)
         VALUES (:full_name, :email, :phone, :password, :franchise_id, :cafe_id, :role_id, 'active')"
    );
    $insert->execute([
        ':full_name' => $fullName,
        ':email' => $email,
        ':phone' => $phone,
        ':password' => password_hash($password, PASSWORD_DEFAULT),
        ':franchise_id' => $franchiseId,
        ':cafe_id' => $assignedCafeId,
        ':role_id' => $roleId,
    ]);

    return (int) $db->lastInsertId();
}

/**
 * Manager-assisted password reset — sets a new password directly, no email
 * involved. Scoped the same way list_staff() is (this branch's pinned staff
 * plus franchise-wide admins), so a manager can't reach into another
 * branch. Same admin-protection rule as role assignment: a non-admin can't
 * reset an admin's password, regardless of what the form sent.
 */
function reset_staff_password(
    PDO $db,
    int $franchiseId,
    int $activeCafeId,
    int $userId,
    string $newPassword,
    string $actorRole
): bool {
    if (strlen($newPassword) < 8) {
        return false;
    }

    $stmt = $db->prepare(
        'SELECT u.id, r.name AS role_name FROM users u
         JOIN roles r ON r.id = u.role_id
         WHERE u.id = :id
           AND u.franchise_id = :franchise_id
           AND (u.cafe_id = :cafe_id OR u.cafe_id IS NULL)
           AND u.deleted_at IS NULL
         LIMIT 1'
    );
    $stmt->execute([':id' => $userId, ':franchise_id' => $franchiseId, ':cafe_id' => $activeCafeId]);
    $target = $stmt->fetch();

    if (!$target) {
        return false;
    }

    if ($target['role_name'] === ROLE_ADMIN && $actorRole !== ROLE_ADMIN) {
        return false;
    }

    $update = $db->prepare('UPDATE users SET password = :password WHERE id = :id');
    return $update->execute([
        ':password' => password_hash($newPassword, PASSWORD_DEFAULT),
        ':id' => $userId,
    ]);
}
