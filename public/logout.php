<?php

declare(strict_types=1);

require_once __DIR__ . '/../private/includes/bootstrap.php';

$_SESSION = [];

// session_destroy() only clears server-side data — the browser still holds
// the cookie unless we explicitly expire it, using the same name/params
// bootstrap.php configured the session with.
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

header('Location: /login.php');
exit();
