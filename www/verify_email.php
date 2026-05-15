<?php
require_once __DIR__ . '/auth.php';

$token_raw = trim($_POST['verify_token'] ?? $_GET['token'] ?? '');
$db    = get_db();
$user  = null;
$done  = false;
$error = '';

if ($token_raw !== '') {
    $stmt = $db->prepare(
        "SELECT id, username, email_verified FROM users
         WHERE verification_token = ? AND verification_token_expires > datetime('now')"
    );
    $stmt->execute([$token_raw]);
    $user = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    session_start_safe();
    if (!csrf_verify()) {
        $error = 'Invalid request. Please try again.';
    } elseif (!$user) {
        $error = 'This verification link is invalid or has expired.';
    } else {
        $db->prepare(
            'UPDATE users
             SET email_verified = 1, verification_token = NULL, verification_token_expires = NULL
             WHERE id = ?'
        )->execute([$user['id']]);
        db_log_activity($user['id'], 'verified email');
        $done = true;
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
    <title>Verify email &mdash; <?= htmlspecialchars($site_name) ?></title>
    <link rel="stylesheet" href="/style.css">
    <?php require __DIR__ . '/_head.php'; ?>
</head>
<body>
<?php require __DIR__ . '/_nav.php'; ?>
<main class="center-wrap">
    <div class="form-card">
        <h1>Verify email</h1>
        <?php if ($done): ?>
            <div class="alert alert-success">Your email is now verified. You can sign in.</div>
            <p style="text-align:center;margin-top:1rem"><a href="/login.php" class="btn btn-primary">Sign in</a></p>
        <?php elseif (!$user): ?>
            <div class="alert alert-error">This verification link is invalid or has expired.</div>
            <p style="text-align:center;margin-top:1rem"><a href="/resend_verification.php">Request a new link</a></p>
        <?php elseif ((int)$user['email_verified'] === 1): ?>
            <div class="alert alert-success">This account is already verified.</div>
            <p style="text-align:center;margin-top:1rem"><a href="/login.php">Sign in</a></p>
        <?php else: ?>
            <p class="subtitle">Confirm verification for <strong><?= htmlspecialchars($user['username']) ?></strong>.</p>
            <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <form method="post" action="/verify_email.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($token) ?>">
                <input type="hidden" name="verify_token" value="<?= htmlspecialchars($token_raw) ?>">
                <button type="submit" class="btn btn-primary btn-block btn-lg">Verify my email</button>
            </form>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
