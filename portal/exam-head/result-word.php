<?php
/**
 * Exam Head — class result Word export (delegates to shared VP handler).
 * Auth: examination_head (eh_results)
 */
require_once __DIR__ . '/../includes/auth.php';
requireAuth('examination_head', 'admin');
require __DIR__ . '/../vp/result-word.php';
