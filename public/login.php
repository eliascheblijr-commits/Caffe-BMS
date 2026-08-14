<?php

declare(strict_types=1);

require_once __DIR__ . '/../private/includes/bootstrap.php';
require_once CONTROLLERS_PATH . '/AuthController.php';

// Already signed in? Skip straight to the right dashboard.
if (is_logged_in()) {
    header('Location: ' . dashboard_for_role(current_user()['role']));
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $error = handle_login_submission(db());
    // handle_login_submission() redirects and exits on success,
    // so reaching this line always means login failed.
}

require VIEWS_PATH . '/login.view.php';
