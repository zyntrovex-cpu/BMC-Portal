<?php
// Pull in the parent portal's DB connection and base config only
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Site constants ────────────────────────────────────────────────
// Use dirname() so the path has no literal ".." segments (important on Windows)
define('SITE_DIR',    dirname(__DIR__) . '/');
define('SITE_UPLOAD', dirname(__DIR__) . '/assets/uploads/');

// ── Auto-create upload subdirectories ────────────────────────────
foreach (['sliders', 'news', 'notices', 'gallery', 'downloads', 'faculty', 'admissions', 'events', 'testimonials', 'partners', 'contact'] as $_uploadDir) {
    $__path = SITE_UPLOAD . $_uploadDir;
    if (!is_dir($__path)) {
        @mkdir($__path, 0755, true);
    }
}
unset($_uploadDir, $__path);

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
