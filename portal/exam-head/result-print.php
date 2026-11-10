<?php
/**
 * Exam Head — class result print/PDF view (delegates to shared VP view).
 * Auth: examination_head (eh_results)
 */
require_once __DIR__ . '/../includes/auth.php';
requireAuth('examination_head', 'admin');
require __DIR__ . '/../vp/result-print.php';
