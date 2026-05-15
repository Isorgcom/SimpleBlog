<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/mail.php';

if (current_user()) { header('Location: /'); exit; }

$sent = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    session_start_safe();
    if (!csrf_verify()) {
        $error = 'Invalid request. Please try again.';
    } elseif (rate_limited('forgot_password', 3, '1 hour')) {
        $sent = true;
    } else {
        db_log_activity(0, 'forgot_password');
        $email = trim($_POST['email'] ?? '');
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) && smtp_configured()) {
            $db = get_db();
            $stmt = $db->prepare('SELECT id, username FROM users WHERE email = ?');
            $stmt->execute([$email]);
            if ($row = $stmt->fetch()) {
                $token   = bin2hex(random_bytes(32));
                $expires = gmdate('Y-m-d H:i:s', time() + 3600);
                $db->prepare('UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE id = ?')
                   ->execute([$token, $expires, $row['id']]);

                $link = get_site_url() . '/reset_password.php?token=' . urlencode($token);
                $site = get_setting('site_name', 'SimpleBlog');
                $body = '<p>Hello ' . htmlspecialchars($row['username']) . ',</p>'
                      . '<p>A password reset was requested for your account on <strong>' . htmlspecialchars($site) . '</strong>.</p>'
                      . '<p><a href="' . htmlspecialchars($link) . '">Reset my password</a></p>'
                      . '<p>Or copy this link: ' . htmlspecialchars($link) . '</p>'
                      . '<p>The link expires in 1 hour. If you didn\'t request this, you can ignore this email.</p>';
                send_email($email, $row['username'], 'Password reset — ' . $site, $body);
            }
        }
        $sent = true;
    }
}

$token = csrf_token();
$site_name = get_setting('site_name', 'SimpleBlog');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password &mdash; <?= htmlspecialchars($site_name) ?></title>
    <link rel="stylesheet" href="/style.css">
</head>
<body>
<nav><div class="nav-top"><a class="brand" href="/"><?= htmlspecialchars($site_name) ?></a></div></nav>
<div class="card-wrap">
    <div class="card">
        <h2>Forgot Password</h2>
        <?php if ($sent): ?>
            <div class="alert alert-success">
                If an account exists for that email address, a password reset link has been sent.
                Check your inbox (and spam folder).
            </div>
            <p style="text-align:center;margin-top:1rem"><a href="/login.php">Back to Sign In</a></p>
        <?php else: ?>
            <p class="subtitle">Enter your email address and we'll send you a link to reset your password.</p>
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="post" action="/forgot_password.php" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($token) ?>">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" autocomplete="email" autofocus required>
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%;margin-top:.5rem">Send Reset Link</button>
            </form>
            <p style="text-align:center;margin-top:1rem;font-size:.875rem;color:#64748b">
                <a href="/login.php">Back to Sign In</a>
            </p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
