<?php
require_once __DIR__ . '/auth.php';

$current = require_login();
if ($current['role'] !== 'admin') {
    http_response_code(403);
    exit('Access denied.');
}

$db    = get_db();
$id    = (int)($_GET['id'] ?? 0);
$flash = ['type' => '', 'msg' => ''];

// Load target user
$stmt = $db->prepare('SELECT id, username, email, role, created_at, last_login FROM users WHERE id = ?');
$stmt->execute([$id]);
$target = $stmt->fetch();

if (!$target) {
    header('Location: /users.php');
    exit;
}

session_start_safe();
if (!empty($_SESSION['flash'])) {
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $flash = ['type' => 'error', 'msg' => 'Invalid request token.'];
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'update_profile') {
            $username = strtolower(trim($_POST['username'] ?? ''));
            $email    = trim($_POST['email'] ?? '');
            $role     = in_array($_POST['role'] ?? '', ['admin', 'user']) ? $_POST['role'] : 'user';

            if ($username === '') {
                $flash = ['type' => 'error', 'msg' => 'Username cannot be empty.'];
            } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $flash = ['type' => 'error', 'msg' => 'Invalid email address.'];
            } else {
                // Guard: cannot demote last admin
                if ($role !== 'admin' && $target['role'] === 'admin') {
                    $adminCount = (int)$db->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
                    if ($adminCount <= 1) {
                        $flash = ['type' => 'error', 'msg' => 'Cannot demote the last admin.'];
                        $_SESSION['flash'] = $flash;
                        header("Location: /user_edit.php?id=$id");
                        exit;
                    }
                }
                try {
                    $db->prepare('UPDATE users SET username=?, email=?, role=? WHERE id=?')
                       ->execute([$username, $email ?: null, $role, $id]);
                    db_log_activity($current['id'], "admin updated profile for user id: $id");
                    $flash = ['type' => 'success', 'msg' => 'Profile updated.'];
                    // Reload target
                    $stmt->execute([$id]);
                    $target = $stmt->fetch();
                } catch (PDOException $e) {
                    $flash = ['type' => 'error', 'msg' => 'That username is already taken.'];
                }
            }
        }

        elseif ($action === 'delete') {
            $err = null;
            if ($id === (int)$current['id']) {
                $err = 'You cannot delete your own account.';
            } elseif ($target['role'] === 'admin') {
                $adminCount = (int)$db->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
                if ($adminCount <= 1) $err = 'Cannot delete the last admin.';
            }
            if ($err) {
                $flash = ['type' => 'error', 'msg' => $err];
            } else {
                $db->prepare('DELETE FROM activity_log WHERE user_id = ?')->execute([$id]);
                $db->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
                db_log_activity($current['id'], "admin deleted user: {$target['username']}");
                $_SESSION['flash'] = ['type' => 'success', 'msg' => "User \"{$target['username']}\" deleted."];
                header('Location: /users.php');
                exit;
            }
        }

        elseif ($action === 'update_password') {
            $new_pw     = $_POST['new_password'] ?? '';
            $confirm_pw = $_POST['confirm_password'] ?? '';

            if (strlen($new_pw) < 12) {
                $flash = ['type' => 'error', 'msg' => 'Password must be at least 12 characters.'];
            } elseif ($new_pw !== $confirm_pw) {
                $flash = ['type' => 'error', 'msg' => 'Passwords do not match.'];
            } else {
                $hash = password_hash($new_pw, PASSWORD_BCRYPT);
                $db->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([$hash, $id]);
                db_log_activity($current['id'], "admin reset password for user id: $id");
                $flash = ['type' => 'success', 'msg' => 'Password updated.'];
            }
        }
    }

    $_SESSION['flash'] = $flash;
    header("Location: /user_edit.php?id=$id");
    exit;
}

$token = csrf_token();
$sel   = fn($a, $b) => $a === $b ? 'selected' : '';
$site_name = get_setting('site_name', 'SimpleBlog');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit <?= htmlspecialchars($target['username']) ?> &mdash; <?= htmlspecialchars($site_name) ?></title>
    <link rel="stylesheet" href="/style.css?v=<?= @filemtime(__DIR__ . "/style.css") ?>">
    <?php require __DIR__ . '/_head.php'; ?>
    <style>
        .edit-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
        @media (max-width: 640px) { .edit-grid { grid-template-columns: 1fr; } }
        select.form-select {
            width: 100%; padding: .6rem .85rem;
            border: 1.5px solid var(--border); border-radius: 7px;
            font-size: .95rem; background: var(--bg-soft); color: var(--text);
        }
        select.form-select:focus { outline: none; border-color: var(--accent); background: var(--surface); }
    </style>
</head>
<body>

<?php $nav_active = 'site-settings'; require __DIR__ . '/_nav.php'; ?>

<div class="admin-wrap">

    <div class="dash-header">
        <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap">
            <div>
                <h1>Edit User: <?= htmlspecialchars($target['username']) ?></h1>
                <p>
                    <a href="/admin_settings.php?tab=users">&larr; Back to Users</a>
                </p>
            </div>
        </div>
    </div>

    <?php if ($flash['msg']): ?>
        <div class="alert alert-<?= $flash['type'] === 'error' ? 'error' : 'success' ?>" style="margin-bottom:1.5rem">
            <?= htmlspecialchars($flash['msg']) ?>
        </div>
    <?php endif; ?>

    <div class="edit-grid">

        <!-- Profile + Role -->
        <div class="card" style="max-width:100%">
            <h2>Profile</h2>
            <p class="subtitle">Username, email, and role.</p>
            <form method="post" action="/user_edit.php?id=<?= $id ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($token) ?>">
                <input type="hidden" name="action" value="update_profile">

                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username"
                           value="<?= htmlspecialchars($target['username']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email"
                           value="<?= htmlspecialchars($target['email'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="role">Role</label>
                    <select id="role" name="role" class="form-select">
                        <option value="user"  <?= $sel($target['role'], 'user') ?>>User</option>
                        <option value="admin" <?= $sel($target['role'], 'admin') ?>>Admin</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%">Save Profile</button>
            </form>
        </div>

        <!-- Password reset (no current password required for admin) -->
        <div class="card" style="max-width:100%">
            <h2>Reset Password</h2>
            <p class="subtitle">Set a new password for this user.</p>
            <form method="post" action="/user_edit.php?id=<?= $id ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($token) ?>">
                <input type="hidden" name="action" value="update_password">

                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password"
                           autocomplete="new-password" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password"
                           autocomplete="new-password" required>
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%">Update Password</button>
            </form>
        </div>

    </div>

    <!-- Read-only account info -->
    <div class="table-card" style="margin-top:1.5rem;max-width:540px">
        <h3>Account Info</h3>
        <table>
            <tbody>
                <tr><td style="color:var(--text-muted);width:140px">User ID</td><td><?= (int)$target['id'] ?></td></tr>
                <tr><td style="color:var(--text-muted)">Role</td>
                    <td><span class="badge badge-<?= $target['role'] === 'admin' ? 'admin' : 'user' ?>">
                        <?= htmlspecialchars($target['role']) ?></span></td></tr>
                <tr><td style="color:var(--text-muted)">Member since</td><td><?= htmlspecialchars($target['created_at']) ?></td></tr>
                <tr><td style="color:var(--text-muted)">Last login</td><td><?= htmlspecialchars($target['last_login'] ?? 'Never') ?></td></tr>
            </tbody>
        </table>
    </div>

    <!-- Danger zone -->
    <?php if ($id !== $current['id']): ?>
    <div style="margin-top:2rem;max-width:540px;border:1.5px solid var(--danger);border-radius:10px;padding:1.25rem 1.5rem;background:#fff">
        <h3 style="color:var(--danger);margin-bottom:.4rem">Danger Zone</h3>
        <p style="font-size:.875rem;color:var(--text-muted);margin-bottom:1rem">
            Permanently delete this account and all associated activity logs. This cannot be undone.
        </p>
        <form method="post" action="/user_edit.php?id=<?= $id ?>"
              data-confirm="Permanently delete <?= htmlspecialchars($target['username']) ?>? This cannot be undone.">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($token) ?>">
            <input type="hidden" name="action" value="delete">
            <button type="submit" class="btn" style="background:var(--danger);color:#fff">
                Delete <?= htmlspecialchars($target['username']) ?>
            </button>
        </form>
    </div>
    <?php endif; ?>


</div>

<?php require __DIR__ . '/_footer.php'; ?>

</body>
</html>
