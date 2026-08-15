<?php

declare(strict_types=1);

require_once __DIR__ . '/../private/includes/bootstrap.php';
require_once CONTROLLERS_PATH . '/PasswordResetController.php';

if (is_logged_in()) {
    header('Location: ' . dashboard_for_role(current_user()['role']));
    exit;
}

$submitted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('Invalid request.');
    }

    request_password_reset(db(), (string) ($_POST['email'] ?? ''));
    $submitted = true; // same confirmation whether or not the email exists — see request_password_reset()
}

require VIEWS_PATH . '/forgot_password.view.php';
