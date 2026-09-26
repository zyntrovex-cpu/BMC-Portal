<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/config.php';

unset($_SESSION['site_admin']);

// Fully destroy session only if no other module needs it
if (empty($_SESSION)) {
    session_destroy();
}

header('Location: ' . BASE_URL . '/site-admin/login.php');
exit;
