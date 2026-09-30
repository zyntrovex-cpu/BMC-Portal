<?php
define('APP_NAME',    'BMC Portal');
define('SCHOOL_NAME', 'Bahria Model College');
define('SESSION_YEAR','2025-26');

// Auto-detect base URL so the app works in both root and subdirectory installs.
// e.g. XAMPP at http://localhost/BMC-Portal/ → BASE_URL = '/BMC-Portal'
//      root install at http://localhost/      → BASE_URL = ''
//
// Primary strategy: filesystem comparison of DOCUMENT_ROOT vs. this file's
// directory. This is immune to the double-URL cascade bug where SCRIPT_NAME
// contains a repeated path segment (e.g. /BMC-Portal/BMC-Portal/portal/...)
// that caused BASE_URL to be detected incorrectly.
// Fallback: SCRIPT_NAME parsing (for environments where DOCUMENT_ROOT is absent).
if (!defined('BASE_URL')) {
    $__base = '';

    // ── Primary: filesystem-based ───────────────────────────────────
    // dirname(__DIR__) = project root (one level up from config/)
    $__docRoot  = rtrim(str_replace('\\', '/', (string)($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
    $__projRoot = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/');

    if ($__docRoot !== '' && strlen($__docRoot) > 3) {
        // Case-insensitive compare handles Windows mixed-case paths
        if (str_starts_with(strtolower($__projRoot), strtolower($__docRoot))) {
            $__base = substr($__projRoot, strlen($__docRoot)); // e.g. '/BMC-Portal'
        }
    }

    // ── Fallback: SCRIPT_NAME-based ──────────────────────────────────
    if ($__base === '') {
        $__sn = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        foreach (['/portal/', '/notices.php', '/index.php', '/database/', '/config/'] as $__seg) {
            $__pos = strpos($__sn, $__seg);
            if ($__pos !== false) {
                $__base = rtrim(substr($__sn, 0, $__pos), '/');
                break;
            }
        }
    }

    define('BASE_URL', $__base);
    unset($__docRoot, $__projRoot, $__base, $__sn, $__seg, $__pos);
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
