<?php

/**
 * PasswordResetController — self-service password reset via emailed,
 * one-time token (see forgot_password.php / reset_password.php). Manager-
 * assisted reset lives in StaffController::reset_staff_password() instead —
 * this file is only the email path.
 *
 * Uses PHPMailer, installed via Composer (see composer.json). Run
 * `composer install` — or build the Docker image, which runs it — before
 * this will actually send anything.
 */

declare(strict_types=1);

require_once APP_ROOT . '/vendor/autoload.php';

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

const PASSWORD_RESET_TTL_SECONDS = 3600; // 1 hour

/**
 * Starts a reset for $email if an active account exists for it. Silent no-op
 * otherwise — callers should show the same "check your email" message
 * either way, so this never reveals whether an address has an account.
 */
function request_password_reset(PDO $db, string $email): void
{
    $email = trim(strtolower($email));
    if ($email === '') {
        return;
    }

    $stmt = $db->prepare(
        "SELECT id, full_name FROM users WHERE email = :email AND deleted_at IS NULL AND status = 'active' LIMIT 1"
    );
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if (!$user) {
        return;
    }

    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expiresAt = date('Y-m-d H:i:s', time() + PASSWORD_RESET_TTL_SECONDS);

    $insert = $db->prepare(
        'INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (:user_id, :token_hash, :expires_at)'
    );
    $insert->execute([
        ':user_id' => $user['id'],
        ':token_hash' => $tokenHash,
        ':expires_at' => $expiresAt,
    ]);

    send_password_reset_email($email, $user['full_name'], $token);
}

/**
 * True if $token is a live, unused, unexpired reset request. Doesn't
 * consume it — used to decide whether to show the "set a new password"
 * form or an "invalid link" state.
 */
function password_reset_token_is_valid(PDO $db, string $token): bool
{
    $stmt = $db->prepare(
        'SELECT id FROM password_resets WHERE token_hash = :hash AND used_at IS NULL AND expires_at > NOW() LIMIT 1'
    );
    $stmt->execute([':hash' => hash('sha256', $token)]);

    return (bool) $stmt->fetchColumn();
}

/**
 * Validates $token, sets the new password, and marks the token used — all
 * in one transaction so a token can never be spent twice even under a race.
 */
function complete_password_reset(PDO $db, string $token, string $newPassword): bool
{
    if (strlen($newPassword) < 8) {
        return false;
    }

    $db->beginTransaction();
    try {
        $stmt = $db->prepare(
            'SELECT id, user_id FROM password_resets
             WHERE token_hash = :hash AND used_at IS NULL AND expires_at > NOW()
             LIMIT 1 FOR UPDATE'
        );
        $stmt->execute([':hash' => hash('sha256', $token)]);
        $reset = $stmt->fetch();

        if (!$reset) {
            $db->rollBack();
            return false;
        }

        $update = $db->prepare('UPDATE users SET password = :password WHERE id = :id');
        $update->execute([
            ':password' => password_hash($newPassword, PASSWORD_DEFAULT),
            ':id' => $reset['user_id'],
        ]);

        $markUsed = $db->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = :id');
        $markUsed->execute([':id' => $reset['id']]);

        $db->commit();
        return true;
    } catch (Throwable $e) {
        $db->rollBack();
        error_log('[caffe-bms] complete_password_reset failed: ' . $e->getMessage());
        return false;
    }
}

function send_password_reset_email(string $toEmail, string $toName, string $token): bool
{
    $resetUrl = rtrim(env('APP_URL', ''), '/') . '/reset_password.php?token=' . urlencode($token);

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = env('SMTP_HOST', '');
        $mail->SMTPAuth = true;
        $mail->Username = env('SMTP_USERNAME', '');
        $mail->Password = env('SMTP_PASSWORD', '');
        $mail->SMTPSecure = env('SMTP_ENCRYPTION', 'tls');
        $mail->Port = (int) env('SMTP_PORT', '587');

        $mail->setFrom(env('MAIL_FROM_ADDRESS', 'no-reply@example.com'), env('MAIL_FROM_NAME', 'Caffe BMS'));
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = 'Reset your Caffe BMS password';
        $mail->Body = sprintf(
            '<p>Hi %s,</p><p>Click the link below to set a new password. It expires in one hour.</p><p><a href="%s">Reset your password</a></p><p>If you didn\'t request this, you can ignore this email.</p>',
            htmlspecialchars($toName, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8')
        );
        $mail->AltBody = "Reset your password: {$resetUrl} (expires in 1 hour)";

        $mail->send();
        return true;
    } catch (PHPMailerException $e) {
        error_log('[caffe-bms] password reset email failed: ' . $mail->ErrorInfo);
        return false;
    }
}
