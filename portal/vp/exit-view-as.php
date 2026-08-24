<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$returnUrl = BASE_URL . '/portal/vp/view-as.php';

if (!empty($_SESSION['view_as_mode']) && !empty($_SESSION['admin_backup'])) {
    $returnUrl = $_SESSION['view_as_return'] ?? $returnUrl;
    $_SESSION['user'] = $_SESSION['admin_backup'];
    unset($_SESSION['view_as_mode'], $_SESSION['admin_backup'], $_SESSION['view_as_return']);
}

header('Location: ' . $returnUrl);
exit;
