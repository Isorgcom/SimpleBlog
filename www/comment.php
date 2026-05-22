<?php
require_once __DIR__ . '/auth.php';

session_start_safe();

$user = current_user();
if (!$user) {
    header('Location: /login.php');
    exit;
}

// Validate redirect — only allow same-site relative paths.
// Must start with a single "/" that is NOT followed by "/" or "\", otherwise
// "//evil.com" / "/\evil.com" become protocol-relative open redirects.
$redirect = $_POST['redirect'] ?? '/';
if (!preg_match('#^/($|[^/\\\\])#', $redirect)) $redirect = '/';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    header('Location: ' . $redirect);
    exit;
}

$db     = get_db();
$action = $_POST['action'] ?? '';

if ($action === 'add') {
    if (rate_limited('comment_add', 10, '5 minutes')) {
        header('Location: ' . $redirect);
        exit;
    }
    $type       = ($_POST['type'] ?? '') === 'post' ? 'post' : null;
    $content_id = (int)($_POST['content_id'] ?? 0);
    $body       = mb_substr(strip_tags(trim($_POST['body'] ?? '')), 0, 2000);

    if ($type && $content_id > 0 && $body !== '') {
        $db->prepare('INSERT INTO comments (type, content_id, user_id, body) VALUES (?, ?, ?, ?)')
           ->execute([$type, $content_id, $user['id'], $body]);
        db_log_activity($user['id'], "comment_add on $type id $content_id");
    }
} elseif ($action === 'edit') {
    $comment_id = (int)($_POST['comment_id'] ?? 0);
    $body       = mb_substr(strip_tags(trim($_POST['body'] ?? '')), 0, 2000);

    if ($comment_id > 0 && $body !== '') {
        $stmt = $db->prepare('SELECT user_id FROM comments WHERE id = ?');
        $stmt->execute([$comment_id]);
        $row = $stmt->fetch();
        if ($row && ($row['user_id'] == $user['id'] || $user['role'] === 'admin')) {
            $db->prepare('UPDATE comments SET body = ? WHERE id = ?')->execute([$body, $comment_id]);
            db_log_activity($user['id'], "edited comment $comment_id");
        }
    }
} elseif ($action === 'delete') {
    $comment_id = (int)($_POST['comment_id'] ?? 0);
    if ($comment_id > 0) {
        $stmt = $db->prepare('SELECT user_id FROM comments WHERE id = ?');
        $stmt->execute([$comment_id]);
        $row = $stmt->fetch();
        if ($row && ($row['user_id'] == $user['id'] || $user['role'] === 'admin')) {
            $db->prepare('DELETE FROM comments WHERE id = ?')->execute([$comment_id]);
            db_log_activity($user['id'], "deleted comment $comment_id");
        }
    }
} elseif ($action === 'bulk_delete') {
    if ($user['role'] === 'admin') {
        $raw = json_decode($_POST['comment_ids'] ?? '[]', true);
        if (is_array($raw) && !empty($raw)) {
            $ids = array_values(array_filter(array_map('intval', $raw), fn($id) => $id > 0));
            if (!empty($ids)) {
                $ph = implode(',', array_fill(0, count($ids), '?'));
                $db->prepare("DELETE FROM comments WHERE id IN ($ph)")->execute($ids);
                db_log_activity($user['id'], "bulk deleted " . count($ids) . " comment(s)");
            }
        }
    }
}

header('Location: ' . $redirect);
exit;
