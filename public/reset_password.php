<?php

declare(strict_types=1);

require_once __DIR__ . '/../private/includes/bootstrap.php';
require_once CONTROLLERS_PATH . '/PasswordResetController.php';

if (is_logged_in()) {
    header('Location: ' . dashboard_for_role(current_user()['role']));
    exit;
}

$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');

if ($token === '' || !password_reset_token_is_valid(db(), $token)) {
    require VIEWS_PATH . '/reset_password_invalid.view.php';
    exit;
}

$error = null;
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('Invalid request.');
    }

    $newPassword = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['password_confirm'] ?? '');

    if ($newPassword !== $confirmPassword) {
        $error = "Passwords don't match.";
    } elseif (strlen($newPassword) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif (!complete_password_reset(db(), $token, $newPassword)) {
        $error = 'This reset link is no longer valid. Request a new one.';
    } else {
        $success = true;
    }
}

require VIEWS_PATH . '/reset_password.view.php';
