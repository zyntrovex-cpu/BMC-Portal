<?php
// Password reset is now handled entirely by forgot-password.php (OTP flow).
// This file redirects anyone landing here to the new flow.
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();
redirect('/forgot-password.php');
