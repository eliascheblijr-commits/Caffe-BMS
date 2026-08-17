<?php

/**
 * AuthController — staff login (public/login.php).
 *
 * Customers never authenticate in this system — orders carry plain
 * customer_name/customer_contact fields (see README). Only staff
 * (barista, cashier, manager, admin) have rows in `users`.
 */

declare(strict_types=1);

require_once __DIR__ . '/CafeController.php';

const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_ATTEMPT_WINDOW_SECONDS = 900; // 15 minutes

/**
 * True if this email has too many recent failed attempts to try again.
 */
function login_is_rate_limited(PDO $db, string $email): bool
{
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM login_attempts
         WHERE email = :email AND attempt_time > (NOW() - INTERVAL :window SECOND)'
    );
    $stmt->bindValue(':email', $email);
    $stmt->bindValue(':window', LOGIN_ATTEMPT_WINDOW_SECONDS, PDO::PARAM_INT);
    $stmt->execute();

    return (int) $stmt->fetchColumn() >= LOGIN_MAX_ATTEMPTS;
}

function login_record_failed_attempt(PDO $db, string $email): void
{
    $stmt = $db->prepare(
        'INSERT INTO login_attempts (ip_address, email, attempt_time) VALUES (:ip, :email, NOW())'
    );
    $stmt->execute([
        ':ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ':email' => $email,
    ]);
}

/**
 * Processes a login POST. Returns an error message to display on failure.
 * On success it regenerates the session, populates it, redirects to the
 * user's dashboard, and exits — it never returns in that case.
 */
function handle_login_submission(PDO $db): string
{
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        return 'Your session expired. Please try again.';
    }

    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        return 'Enter your email and password.';
    }

    if (login_is_rate_limited($db, $email)) {
        return 'Too many login attempts. Please wait 15 minutes and try again.';
    }

    $stmt = $db->prepare(
        'SELECT u.id, u.full_name, u.email, u.password, u.status, u.franchise_id, u.cafe_id, r.name AS role_name
         FROM users u
         JOIN roles r ON r.id = u.role_id
         WHERE u.email = :email AND u.deleted_at IS NULL
         LIMIT 1'
    );
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        login_record_failed_attempt($db, $email);
        return 'Invalid email or password.';
    }

    if ($user['status'] !== 'active') {
        login_record_failed_attempt($db, $email);
        return 'This account is not active. Contact your manager.';
    }

    // Success — rotate the session ID on privilege change, then populate it.
    session_regenerate_id(true);

    $cafeId = $user['cafe_id'] !== null ? (int) $user['cafe_id'] : null;
    $franchiseId = (int) $user['franchise_id'];

    // Branch-pinned roles are always active on their one cafe. Admin/owner
    // (cafe_id NULL) defaults to the first branch in their franchise —
    // same ordering the switcher dropdown uses — or null if it has none yet.
    if ($cafeId !== null) {
        $activeCafeId = $cafeId;
    } else {
        $franchiseCafes = list_cafes_for_franchise($db, $franchiseId);
        $activeCafeId = $franchiseCafes[0]['id'] ?? null;
    }

    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'full_name' => $user['full_name'],
        'email' => $user['email'],
        'role' => $user['role_name'],
        'franchise_id' => $franchiseId,
        'cafe_id' => $cafeId,
        'active_cafe_id' => $activeCafeId,
    ];

    header('Location: ' . dashboard_for_role($user['role_name']));
    exit;
}
