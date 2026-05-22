<?php
require_once __DIR__ . '/auth.php';

if (current_user()) { header('Location: /'); exit; }

// Token may arrive on GET (from email link) or POST (form re-submit)
$token_raw = trim($_POST['reset_token'] ?? $_GET['token'] ?? '');
$error  = '';
$done   = false;

$db = get_db();
$user = null;
if ($token_raw !== '') {
    $stmt = $db->prepare(
        "SELECT id, username FROM users
         WHERE reset_token = ? AND reset_token_expires > datetime('now')"
    );
    $stmt->execute([$token_raw]);
    $user = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    session_start_safe();
    if (!csrf_verify()) {
        $error = 'Invalid request. Please try again.';
    } elseif (!$user) {
        $error = 'This reset link is invalid or has expired.';
    } else {
        $pw  = $_POST['new_password'] ?? '';
        $pw2 = $_POST['confirm_password'] ?? '';
        if (strlen($pw) < 12) {
            $error = 'Password must be at least 12 characters.';
        } elseif ($pw !== $pw2) {
            $error = 'Passwords do not match.';
        } else {
            $hash = password_hash($pw, PASSWORD_BCRYPT);
            $db->prepare(
                'UPDATE users
                 SET password_hash = ?, reset_token = NULL, reset_token_expires = NULL,
                     must_change_password = 0
                 WHERE id = ?'
            )->execute([$hash, $user['id']]);
            db_log_activity($user['id'], 'reset password via email');
            $done = true;
        }
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
    <title>Reset password &mdash; <?= htmlspecialchars($site_name) ?></title>
    <link rel="stylesheet" href="/style.css?v=<?= @filemtime(__DIR__ . "/style.css") ?>">
    <?php require __DIR__ . '/_head.php'; ?>
</head>
<body>
<?php require __DIR__ . '/_nav.php'; ?>
<main class="center-wrap">
    <div class="form-card">
        <h1>Reset password</h1>
        <?php if ($done): ?>
            <div class="alert alert-success">Your password has been reset. You can now sign in with your new password.</div>
            <p style="text-align:center;margin-top:1rem"><a href="/login.php" class="btn btn-primary">Sign in</a></p>
        <?php elseif (!$user): ?>
            <div class="alert alert-error">This reset link is invalid or has expired. Please request a new one.</div>
            <p style="text-align:center;margin-top:1rem"><a href="/forgot_password.php">Request a new link</a></p>
        <?php else: ?>
            <p class="subtitle">Choose a new password for <strong><?= htmlspecialchars($user['username']) ?></strong>.</p>
            <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <form method="post" action="/reset_password.php" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($token) ?>">
                <input type="hidden" name="reset_token" value="<?= htmlspecialchars($token_raw) ?>">
                <div class="form-group">
                    <label for="new_password">New password</label>
                    <input type="password" id="new_password" name="new_password" autocomplete="new-password" required minlength="12">
                    <p class="hint">At least 12 characters.</p>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm password</label>
                    <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" required minlength="12">
                </div>
                <button type="submit" class="btn btn-primary btn-block btn-lg">Reset password</button>
            </form>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
