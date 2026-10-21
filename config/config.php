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

// ──────────────────────────────────────────────────────────────────────────
// Grading rubric — single source of truth for the entire portal.
// A+ 90-100% Exceptional · A 80-89% Excellent · B 70-79% Very Good
// C 60-69% Good · D 50-59% Developing · F <50% Requires Further Development
// Every grade computation in the portal — report cards, result pages,
// marks entry, Excel/Word/print exports, assessment-level badges — must
// delegate to these functions. Do not inline alternative thresholds.
// ──────────────────────────────────────────────────────────────────────────
function getGrade(float $pct): array {
    if ($pct >= 90) return ['label' => 'A+', 'class' => 'grade-aplus', 'performance' => 'Exceptional',
                            'description' => 'Demonstrates outstanding academic excellence, mastery of concepts and consistently exceptional performance.'];
    if ($pct >= 80) return ['label' => 'A',  'class' => 'grade-a',     'performance' => 'Excellent',
                            'description' => 'Demonstrates excellent understanding of concepts, strong subject knowledge and a high standard of achievement.'];
    if ($pct >= 70) return ['label' => 'B',  'class' => 'grade-b',     'performance' => 'Very Good',
                            'description' => 'Demonstrates very good understanding of the subject and consistently commendable academic progress.'];
    if ($pct >= 60) return ['label' => 'C',  'class' => 'grade-c',     'performance' => 'Good',
                            'description' => 'Demonstrates a satisfactory understanding of key concepts, with scope for further improvement.'];
    if ($pct >= 50) return ['label' => 'D',  'class' => 'grade-d',     'performance' => 'Developing',
                            'description' => 'Demonstrates basic subject understanding and would benefit from additional practice and focused guidance.'];
    return ['label' => 'F', 'class' => 'grade-f', 'performance' => 'Requires Further Development',
            'description' => 'Further support and targeted learning are recommended to strengthen understanding and achieve the expected learning outcomes.'];
}

function gradeHtml(float $pct): string {
    $g = getGrade($pct);
    return '<span class="grade ' . $g['class'] . '">' . $g['label'] . '</span>';
}

// Canonical rubric for grade-scale legends. Each row is [grade, range, performance, description].
function gradingRubric(): array {
    return [
        ['A+', '90–100%',   'Exceptional',                    'Demonstrates outstanding academic excellence, mastery of concepts and consistently exceptional performance.'],
        ['A',  '80–89%',    'Excellent',                      'Demonstrates excellent understanding of concepts, strong subject knowledge and a high standard of achievement.'],
        ['B',  '70–79%',    'Very Good',                      'Demonstrates very good understanding of the subject and consistently commendable academic progress.'],
        ['C',  '60–69%',    'Good',                           'Demonstrates a satisfactory understanding of key concepts, with scope for further improvement.'],
        ['D',  '50–59%',    'Developing',                     'Demonstrates basic subject understanding and would benefit from additional practice and focused guidance.'],
        ['F',  'Below 50%', 'Requires Further Development',   'Further support and targeted learning are recommended to strengthen understanding and achieve the expected learning outcomes.'],
    ];
}

// Convenience helpers (keyed by letter) — used by report-card/export files that
// previously inlined their own color / background / label maps.
function gradeLetterFor(float $pct): string      { return getGrade($pct)['label']; }
function gradeCssClassFor(string $letter): string {
    return match($letter) {
        'A+' => 'grade-aplus', 'A' => 'grade-a', 'B' => 'grade-b',
        'C'  => 'grade-c',     'D' => 'grade-d', 'F' => 'grade-f',
        // Legacy codes still render through aliases in portal.css
        'A++' => 'grade-aplusplus', 'B++' => 'grade-bplusplus', 'B+' => 'grade-bplus',
        'E'  => 'grade-e', 'U' => 'grade-u',
        default => '',
    };
}
function gradeColorFor(string $letter): string {
    return match($letter) {
        'A+' => '#166534', 'A' => '#15803d', 'B' => '#1d4ed8',
        'C'  => '#92400e', 'D' => '#7c2d12', 'F' => '#991b1b',
        default => '#374151',
    };
}
function gradeBgFor(string $letter): string {
    return match($letter) {
        'A+' => '#d1fae5', 'A' => '#bbf7d0', 'B' => '#bfdbfe',
        'C'  => '#fef3c7', 'D' => '#ffedd5', 'F' => '#fecaca',
        default => '#f3f4f6',
    };
}
function gradePerformanceFor(string $letter): string {
    return match($letter) {
        'A+' => 'Exceptional', 'A' => 'Excellent', 'B' => 'Very Good',
        'C'  => 'Good',        'D' => 'Developing', 'F' => 'Requires Further Development',
        default => '',
    };
}
