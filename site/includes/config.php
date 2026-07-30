<?php
// Pull in the parent portal's DB connection helper
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

// ── Site constants ────────────────────────────────────────────────
define('SITE_DIR',    __DIR__ . '/../');
define('SITE_UPLOAD', __DIR__ . '/../assets/uploads/');

// SITE_URL: the public URL prefix for this sub-site
// e.g. /BMC-Portal/site  or  /site  or  ''
if (!defined('SITE_URL')) {
    define('SITE_URL', BASE_URL . '/site');
}

// ── Reuse parent DB ───────────────────────────────────────────────
function siteDB(): PDO { return getDB(); }

// ── Site admin session helpers ────────────────────────────────────
function isSiteAdmin(): bool {
    return !empty($_SESSION['site_admin']['id']);
}
function requireSiteAdmin(): array {
    if (!isSiteAdmin()) {
        header('Location: ' . SITE_URL . '/admin/login.php');
        exit;
    }
    return $_SESSION['site_admin'];
}
function currentAdmin(): array { return $_SESSION['site_admin'] ?? []; }

// ── Site student session helpers ──────────────────────────────────
// Students use the main portal auth; this just exposes a redirect.
function requireSiteStudent(): void {
    if (empty($_SESSION['user']['id']) || $_SESSION['user']['role'] !== 'student') {
        header('Location: ' . BASE_URL . '/student/dashboard.php');
        exit;
    }
}
