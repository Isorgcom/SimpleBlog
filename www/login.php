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
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            $error = 'Username and password are required.';
        } elseif (!attempt_login($username, $password)) {
            $error = 'Invalid username or password.';
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
    <title>Login &mdash; <?= htmlspecialchars($site_name) ?></title>
    <link rel="stylesheet" href="/style.css">
</head>
<body>

<nav>
    <div class="nav-top">
        <a class="brand" href="/"><?= htmlspecialchars($site_name) ?></a>
    </div>
</nav>

<div class="card-wrap">
    <div class="card">
        <h2>Sign In</h2>
        <p class="subtitle">Enter your credentials to access the dashboard.</p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
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
                <input type="password" id="password" name="password"
                       autocomplete="current-password" required>
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%;margin-top:.5rem">
                Sign In
            </button>
        </form>

        <?php if (get_setting('allow_registration', '1') === '1'): ?>
        <p style="text-align:center;margin-top:1.25rem;font-size:.875rem;color:#64748b">
            Don't have an account? <a href="/register.php">Sign up</a>
        </p>
        <?php endif; ?>

    </div>
</div>

</body>
</html>
