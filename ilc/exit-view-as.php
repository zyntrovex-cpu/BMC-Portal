<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!empty($_SESSION['view_as_mode']) && !empty($_SESSION['admin_backup'])) {
    $_SESSION['user'] = $_SESSION['admin_backup'];
    unset($_SESSION['view_as_mode'], $_SESSION['admin_backup']);
}

header('Location: ' . BASE_URL . '/ilc/view-as.php');
exit;
