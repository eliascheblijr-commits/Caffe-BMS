<?php
/**
 * @var string|null $error
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — Caffe BMS</title>
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
            <p class="auth-subtitle">Staff sign in</p>
        </div>

        <?php if ($error !== null): ?>
            <div class="auth-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form method="post" action="/login.php" class="auth-form" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

            <div>
                <label for="email">Email</label>
                <input type="email" id="email" name="email" autocomplete="username" required autofocus value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div>
                <label for="password">Password</label>
                <input type="password" id="password" name="password" autocomplete="current-password" required>
            </div>

            <button type="submit" class="btn-primary">Sign In</button>
        </form>

        <a class="auth-link" href="/forgot_password.php">Forgot your password?</a>
    </main>
</body>
</html>
