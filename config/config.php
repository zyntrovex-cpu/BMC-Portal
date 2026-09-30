<?php
define('APP_NAME',    'BMC Portal');
define('SCHOOL_NAME', 'Bahria Model College');
define('SESSION_YEAR','2025-26');

// Auto-detect base URL so the app works in both root and subdirectory installs.
// e.g. XAMPP at http://localhost/BMC-Portal/ → BASE_URL = '/BMC-Portal'
//      root install at http://localhost/      → BASE_URL = ''
//
// Uses SCRIPT_NAME (always a URL path) instead of filesystem paths so it
// works correctly on Windows XAMPP where DOCUMENT_ROOT may be unreliable.
if (!defined('BASE_URL')) {
    $__sn = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $__base = '';
    // Known path segments that are exactly one level below the project root.
    foreach (['/portal/', '/notices.php', '/index.php', '/database/', '/config/'] as $__seg) {
        $__pos = strpos($__sn, $__seg);
        if ($__pos !== false) {
            $__base = rtrim(substr($__sn, 0, $__pos), '/');
            break;
        }
    }
    define('BASE_URL', $__base);
    unset($__sn, $__base, $__seg, $__pos);
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
