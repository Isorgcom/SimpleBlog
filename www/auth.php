<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/version.php';

function is_https(): bool {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return true;
    $xfp = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
    return strtolower($xfp) === 'https';
}

/**
 * Per-request CSP nonce. Inline <script> blocks must carry nonce="<?= csp_nonce() ?>"
 * to execute, since the CSP no longer allows 'unsafe-inline' for scripts.
 * (style-src keeps 'unsafe-inline' — inline styles are pervasive and far lower risk.)
 */
function csp_nonce(): string {
    static $nonce = null;
    if ($nonce === null) {
        $nonce = base64_encode(random_bytes(16));
    }
    return $nonce;
}

// ── Security headers (sent on every request) ──────────────────────────────────
header_remove('X-Powered-By');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-" . csp_nonce() . "'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
if (is_https()) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

function session_start_safe(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => is_https(),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }
}

function current_user(): ?array {
    session_start_safe();
    $id = $_SESSION['user_id'] ?? null;
    if ($id === null) return null;

    $stmt = get_db()->prepare(
        'SELECT id, username, email, role, last_login, must_change_password, email_verified
         FROM users WHERE id = ?'
    );
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function require_login(): array {
    $user = current_user();
    if ($user === null) {
        header('Location: /login.php');
        exit;
    }
    return $user;
}

function attempt_login(string $username, string $password): bool {
    $GLOBALS['_login_failure_reason'] = 'credentials';
    $stmt = get_db()->prepare(
        'SELECT id, password_hash, email_verified FROM users WHERE username = ?'
    );
    $stmt->execute([strtolower(trim($username))]);
    $row = $stmt->fetch();

    if (!$row) {
        // Verify against a fixed valid hash so the no-such-user path costs the
        // same as the wrong-password path (reduces username enumeration by timing).
        password_verify($password, '$2y$12$7TIkvZ9kZrClBkJphjLadusC5XZU49J59QBXvdAuMEltp/EgG3YYK');
        return false;
    }
    if (!password_verify($password, $row['password_hash'])) return false;

    require_once __DIR__ . '/mail.php';
    if ((int)$row['email_verified'] === 0 && smtp_configured()) {
        $GLOBALS['_login_failure_reason'] = 'unverified';
        return false;
    }

    session_start_safe();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $row['id'];

    $db = get_db();
    $db->prepare('UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?')
       ->execute([$row['id']]);
    db_log_activity($row['id'], 'login');
    return true;
}

function logout(): void {
    session_start_safe();
    $id = $_SESSION['user_id'] ?? null;
    if ($id) db_log_activity($id, 'logout');
    $_SESSION = [];
    session_destroy();
}

function csrf_token(): string {
    session_start_safe();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Register a new user. Returns null on success or an error string on failure.
 */
function register_user(string $username, string $email, string $password): ?string {
    $username = strtolower(trim($username));
    $email    = trim($email);

    if ($username === '' || $password === '') {
        return 'Username and password are required.';
    }
    if (!preg_match('/^[a-z0-9_]{3,30}$/', $username)) {
        return 'Username must be 3-30 characters and contain only letters, numbers, or underscores.';
    }
    if (strlen($password) < 12) {
        return 'Password must be at least 12 characters.';
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'Invalid email address.';
    }

    $db   = get_db();
    $stmt = $db->prepare('SELECT id FROM users WHERE username = ?');
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        return 'That username is already taken.';
    }

    require_once __DIR__ . '/mail.php';
    $needs_verification = $email !== '' && smtp_configured();
    $verify_token = $needs_verification ? bin2hex(random_bytes(32)) : null;
    $verify_expires = $needs_verification ? gmdate('Y-m-d H:i:s', time() + 86400) : null;

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $db->prepare(
        'INSERT INTO users (username, password_hash, email, role, email_verified,
                            verification_token, verification_token_expires)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $username, $hash, $email !== '' ? $email : null, 'user',
        $needs_verification ? 0 : 1, $verify_token, $verify_expires,
    ]);

    $id = (int)$db->lastInsertId();

    if ($needs_verification) {
        $link = get_site_url() . '/verify_email.php?token=' . urlencode($verify_token);
        $site = get_setting('site_name', 'SimpleBlog');
        $body = '<p>Hello ' . htmlspecialchars($username) . ',</p>'
              . '<p>Please verify your email address for <strong>' . htmlspecialchars($site) . '</strong>.</p>'
              . '<p><a href="' . htmlspecialchars($link) . '">Verify my email</a></p>'
              . '<p>Or copy this link: ' . htmlspecialchars($link) . '</p>'
              . '<p>The link expires in 24 hours.</p>';
        send_email($email, $username, 'Verify your email — ' . $site, $body);
        db_log_activity($id, 'registered (pending verification)');
        return null;
    }

    session_start_safe();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $id;
    $db->prepare('UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?')->execute([$id]);
    db_log_activity($id, 'registered');
    return null;
}

function csrf_verify(): bool {
    $expected = $_SESSION['csrf_token'] ?? '';
    $provided = $_POST['csrf_token'] ?? '';
    if ($expected === '' || $provided === '') return false;
    return hash_equals($expected, $provided);
}

function enforce_password_change(): void {
    session_start_safe();
    if (empty($_SESSION['user_id'])) return;
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if (in_array($script, ['settings.php', 'logout.php', 'login.php'], true)) return;
    $stmt = get_db()->prepare('SELECT must_change_password FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    if ((int)$stmt->fetchColumn() === 1) {
        header('Location: /settings.php?force_change=1');
        exit;
    }
}
enforce_password_change();

/**
 * Returns true when the current IP has met or exceeded $limit activity_log
 * entries whose action begins with $action_prefix within the last $window
 * (e.g. '1 hour', '5 minutes'). $window is interpolated into a SQLite
 * modifier, so it must be a trusted literal.
 */
function rate_limited(string $action_prefix, int $limit, string $window): bool {
    $ip = get_client_ip();
    if ($ip === '') return false;
    $like = $action_prefix . '%';
    $stmt = get_db()->prepare(
        "SELECT COUNT(*) FROM activity_log
         WHERE ip = ? AND action LIKE ? AND created_at > datetime('now', ?)"
    );
    $stmt->execute([$ip, $like, '-' . $window]);
    return (int)$stmt->fetchColumn() >= $limit;
}
