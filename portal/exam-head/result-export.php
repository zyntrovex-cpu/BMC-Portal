<?php
/**
 * Exam Head — class result export (delegates to shared VP export logic).
 * Auth: examination_head (eh_results)
 */
require_once __DIR__ . '/../includes/auth.php';
requireAuth('examination_head', 'admin');
// Re-dispatch to the shared export handler (which already handles examination_head role)
require __DIR__ . '/../vp/result-export.php';
