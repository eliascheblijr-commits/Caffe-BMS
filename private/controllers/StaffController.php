<?php

/**
 * StaffController — staff account management for the manager dashboard.
 */

declare(strict_types=1);

function list_staff(PDO $db, int $cafeId): array
{
    $stmt = $db->prepare(
        'SELECT u.id, u.full_name, u.email, u.status, r.name AS role_name
         FROM users u
         JOIN roles r ON r.id = u.role_id
         WHERE u.cafe_id = :cafe_id AND u.deleted_at IS NULL
         ORDER BY u.full_name ASC'
    );
    $stmt->execute([':cafe_id' => $cafeId]);
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
 * Creates a new staff account. Returns the new user id, or null on any
 * validation failure (bad email, duplicate email, unknown/disallowed role,
 * password under 8 chars) — callers show one generic error either way.
 */
function create_staff_member(
    PDO $db,
    int $cafeId,
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
        "INSERT INTO users (full_name, email, phone_number, password, cafe_id, role_id, status)
         VALUES (:full_name, :email, :phone, :password, :cafe_id, :role_id, 'active')"
    );
    $insert->execute([
        ':full_name' => $fullName,
        ':email' => $email,
        ':phone' => $phone,
        ':password' => password_hash($password, PASSWORD_DEFAULT),
        ':cafe_id' => $cafeId,
        ':role_id' => $roleId,
    ]);

    return (int) $db->lastInsertId();
}

/**
 * Manager-assisted password reset — sets a new password directly, no email
 * involved. Same admin-protection rule as role assignment: a non-admin
 * can't reset an admin's password, regardless of what the form sent.
 */
function reset_staff_password(PDO $db, int $cafeId, int $userId, string $newPassword, string $actorRole): bool
{
    if (strlen($newPassword) < 8) {
        return false;
    }

    $stmt = $db->prepare(
        'SELECT u.id, r.name AS role_name FROM users u
         JOIN roles r ON r.id = u.role_id
         WHERE u.id = :id AND u.cafe_id = :cafe_id AND u.deleted_at IS NULL
         LIMIT 1'
    );
    $stmt->execute([':id' => $userId, ':cafe_id' => $cafeId]);
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
