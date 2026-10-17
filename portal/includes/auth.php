<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Require a logged-in session of a specific role ──────────────
function requireAuth(string ...$roles): array {
    if (empty($_SESSION['user'])) {
        $base = defined('BASE_URL') ? BASE_URL : '';
        header('Location: ' . $base . '/portal/index.php?msg=login');
        exit;
    }
    $user = $_SESSION['user'];
    if (!empty($roles) && !in_array($user['role'], $roles, true)) {
        $base = defined('BASE_URL') ? BASE_URL : '';
        header('Location: ' . $base . '/portal/index.php?msg=unauthorized');
        exit;
    }
    return $user;
}

// ── Return logged-in user or null ────────────────────────────────
function currentUser(): ?array {
    return $_SESSION['user'] ?? null;
}

// ── Log user activity ────────────────────────────────────────────
function logActivity(int $userId, string $action, string $details = ''): void {
    try {
        $db = getDB();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $db->prepare('INSERT INTO activity_log (user_id, action, details, ip_address) VALUES (?,?,?,?)')
           ->execute([$userId, $action, $details, $ip]);
    } catch (Exception $e) {
        // non-fatal
    }
}

// ── Flash messages ───────────────────────────────────────────────
function setFlash(string $type, string $msg): void {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function getFlash(): ?array {
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function flashHtml(): string {
    $f = getFlash();
    if (!$f) return '';
    // Invisible sentinel consumed by portal-ajax.js and converted to a toast.
    $type = htmlspecialchars($f['type'] ?? 'info', ENT_QUOTES, 'UTF-8');
    $msg  = htmlspecialchars($f['msg']  ?? '',      ENT_QUOTES, 'UTF-8');
    return '<div data-portal-flash="' . $type . '" data-portal-msg="' . $msg . '" aria-hidden="true" style="display:none"></div>';
}
