<?php
/**
 * @var string|null $error
 * @var bool $success
 * @var string $token
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set New Password — Caffe BMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Caveat:wght@700&family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/style.css">
</head>
<body class="auth-page">
    <main class="auth-card">
        <div class="auth-brand">
            <span class="auth-brand-word">Caffe BMS</span>
            <svg class="auth-brand-swash" viewBox="0 0 140 12" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path d="M2 6.5C22 2 40 10 60 6C80 2 98 10 118 5.5C126 4 132 6 138 5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
            <p class="auth-subtitle">Set a new password</p>
        </div>

        <?php if ($success): ?>
            <div class="auth-success">Your password has been reset.</div>
            <a class="auth-link" href="/login.php">Sign in</a>
        <?php else: ?>
            <?php if ($error !== null): ?>
                <div class="auth-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <form method="post" action="/reset_password.php?token=<?= urlencode($token) ?>" class="auth-form" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">

                <div>
                    <label for="password">New password</label>
                    <input type="password" id="password" name="password" autocomplete="new-password" required minlength="8">
                </div>

                <div>
                    <label for="password_confirm">Confirm password</label>
                    <input type="password" id="password_confirm" name="password_confirm" autocomplete="new-password" required minlength="8">
                </div>

                <button type="submit" class="btn-primary">Reset Password</button>
            </form>
        <?php endif; ?>
    </main>
</body>
</html>
