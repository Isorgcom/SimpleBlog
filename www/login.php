<?php
require_once __DIR__ . '/auth.php';

// Already logged in
if (current_user()) {
    header('Location: /');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    session_start_safe();
    if (!csrf_verify()) {
        $error = 'Invalid request. Please try again.';
    } elseif (rate_limited('login_failed', 10, '1 hour')) {
        $error = 'Too many sign-in attempts. Please try again later.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            $error = 'Username and password are required.';
        } elseif (!attempt_login($username, $password)) {
            db_log_activity(0, 'login_failed:' . strtolower($username));
            if (($GLOBALS['_login_failure_reason'] ?? '') === 'unverified') {
                $error = 'Please verify your email address before signing in.';
                $error_show_resend = true;
            } else {
                $error = 'Invalid username or password.';
            }
        } else {
            header('Location: /');
            exit;
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
    <title>Sign in &mdash; <?= htmlspecialchars($site_name) ?></title>
    <link rel="stylesheet" href="/style.css?v=<?= @filemtime(__DIR__ . "/style.css") ?>">
    <?php require __DIR__ . '/_head.php'; ?>
</head>
<body>
<?php require __DIR__ . '/_nav.php'; ?>

<main class="center-wrap">
    <div class="form-card">
        <h1>Sign in</h1>
        <p class="subtitle">Welcome back.</p>

        <?php if (($_GET['registered'] ?? '') === 'pending'): ?>
            <div class="alert alert-success">Account created. Check your email for a verification link before signing in.</div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($error) ?>
                <?php if (!empty($error_show_resend)): ?>
                    <br><a href="/resend_verification.php">Resend verification email</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="/login.php" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($token) ?>">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username"
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                       autocomplete="username" autofocus required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" autocomplete="current-password" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block btn-lg">Sign in</button>
        </form>

        <p style="text-align:center;margin-top:1.25rem;font-size:.88rem;color:var(--text-muted)">
            <a href="/forgot_password.php">Forgot password?</a>
        </p>
        <?php if (get_setting('allow_registration', '1') === '1'): ?>
        <p style="text-align:center;margin-top:.4rem;font-size:.88rem;color:var(--text-muted)">
            Don't have an account? <a href="/register.php">Sign up</a>
        </p>
        <?php endif; ?>
    </div>
</main>

<?php require __DIR__ . '/_footer.php'; ?>
</body>
</html>
