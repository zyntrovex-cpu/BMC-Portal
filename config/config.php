<?php
define('APP_NAME',    'BMC Portal');
define('SCHOOL_NAME', 'Bahria Model College');
define('SESSION_YEAR','2025-26');

// Auto-detect base URL so the app works in both root and subdirectory installs.
// e.g. XAMPP at http://localhost/BMC-Portal/ → BASE_URL = '/BMC-Portal'
//      root install at http://localhost/      → BASE_URL = ''
if (!defined('BASE_URL')) {
    $_projRoot = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/');
    $_docRoot  = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    if ($_docRoot && str_starts_with($_projRoot, $_docRoot)) {
        define('BASE_URL', substr($_projRoot, strlen($_docRoot)));
    } else {
        define('BASE_URL', '');
    }
    unset($_projRoot, $_docRoot);
}

// Grade boundaries (percentage → grade)
function getGrade(float $pct): array {
    if ($pct >= 90) return ['label' => 'A+', 'class' => 'grade-aplus'];
    if ($pct >= 80) return ['label' => 'A',  'class' => 'grade-a'];
    if ($pct >= 70) return ['label' => 'B+', 'class' => 'grade-bplus'];
    if ($pct >= 60) return ['label' => 'B',  'class' => 'grade-b'];
    if ($pct >= 50) return ['label' => 'C',  'class' => 'grade-c'];
    if ($pct >= 40) return ['label' => 'D',  'class' => 'grade-d'];
    return ['label' => 'F', 'class' => 'grade-f'];
}

function gradeHtml(float $pct): string {
    $g = getGrade($pct);
    return '<span class="grade ' . $g['class'] . '">' . $g['label'] . '</span>';
}
