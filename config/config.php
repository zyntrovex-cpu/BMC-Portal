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

// ──────────────────────────────────────────────────────────────────────────
// Pass/fail & ranking policy — single source of truth
//
// School rule (effective 2026-10):
//   * Subject-wise pass: a student must score at least 45% in EVERY subject.
//     (This mirrors "45 marks" on the standard 100-mark subject; the check
//     is done on subject percentage so it scales regardless of max_marks.)
//   * Aggregate pass: the overall weighted / arithmetic percentage must be
//     at least 50%.
//   * Rankings (1st, 2nd, 3rd…) are issued only to students who clear
//     BOTH thresholds — subject-wise AND aggregate.
//
// Every pass/fail check and every merit calculation in the portal must
// delegate to the helpers below. Do NOT inline alternative thresholds.
// ──────────────────────────────────────────────────────────────────────────
if (!defined('SUBJECT_PASS_PCT'))   define('SUBJECT_PASS_PCT',   45.0);
if (!defined('AGGREGATE_PASS_PCT')) define('AGGREGATE_PASS_PCT', 50.0);

function isSubjectPassing(float $subjectPct): bool {
    return $subjectPct >= SUBJECT_PASS_PCT;
}

function isAggregatePassing(float $grandPct): bool {
    return $grandPct >= AGGREGATE_PASS_PCT;
}

/**
 * Does a student pass OVERALL? True iff every attempted subject clears
 * SUBJECT_PASS_PCT AND the aggregate clears AGGREGATE_PASS_PCT.
 *
 * $subjects — iterable of subject rows; each must expose a numeric 'pct'
 *             key for the subject percentage and a numeric 'max' key.
 *             Subjects with max == 0 (not attempted) are ignored.
 * $grandPct — overall aggregate percentage (weighted or straight — the
 *             caller decides, we just check the threshold).
 * $hadMarks — must be true if any subject carried marks; false means the
 *             student has no results and the function returns false.
 */
function studentOverallPasses(iterable $subjects, float $grandPct, bool $hadMarks): bool {
    if (!$hadMarks) return false;
    foreach ($subjects as $sub) {
        $max = (float)($sub['max'] ?? 0);
        if ($max <= 0) continue;
        $pct = (float)($sub['pct'] ?? 0);
        if (!isSubjectPassing($pct)) return false;
    }
    return isAggregatePassing($grandPct);
}

/**
 * The reason a student failed — used for status badges / cells.
 * Returns 'Pass' | 'Fail: Subject' | 'Fail: Aggregate' | 'Fail' | '—' (no marks).
 */
function studentPassStatusLabel(iterable $subjects, float $grandPct, bool $hadMarks): string {
    if (!$hadMarks) return '—';
    $failedSubject = false;
    foreach ($subjects as $sub) {
        $max = (float)($sub['max'] ?? 0);
        if ($max <= 0) continue;
        $pct = (float)($sub['pct'] ?? 0);
        if (!isSubjectPassing($pct)) { $failedSubject = true; break; }
    }
    $failedAggregate = !isAggregatePassing($grandPct);
    if ($failedSubject && $failedAggregate) return 'Fail';
    if ($failedSubject)   return 'Fail: Subject';
    if ($failedAggregate) return 'Fail: Aggregate';
    return 'Pass';
}

// ──────────────────────────────────────────────────────────────────────────
// Assessment-name normalization — the single source of truth for deciding
// whether two assessment titles refer to the SAME examination.
//
// Two assessments share an identity iff normalizeAssessmentName() returns
// the same string for both. The report card dropdown, the "possible
// duplicate" check at creation time, and the admin consolidation UI all
// use this function — do not inline alternative matching rules anywhere.
//
// Normalization pipeline:
//   1. Lowercase, trim, collapse whitespace, strip punctuation.
//   2. Expand ordinals  (1st → first, 2nd → second, 3rd → third …).
//   3. Expand common abbreviations (mid → midterm, hw → homework …).
//   4. Drop generic trailing words that carry no meaning
//      ("assessment", "exam", "paper", "test") so
//      "First Midterm Assessment" matches "First Midterm".
//   5. Collapse whitespace again and return the canonical key.
//
// Returns '' when the input is empty / only noise — callers should treat
// an empty key as "no identity" and never group under it.
// ──────────────────────────────────────────────────────────────────────────
function normalizeAssessmentName(?string $name): string {
    if ($name === null) return '';
    $s = mb_strtolower(trim($name));
    if ($s === '') return '';

    // Replace punctuation with spaces (keeps word boundaries).
    $s = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $s) ?? $s;

    // Expand ordinal digits: 1st → first, 2nd → second, 3rd → third,
    // 4th-10th spelled out. 11th+ left as digits — rare in assessment names.
    static $ords = [
        '1st'  => 'first',  '2nd'  => 'second', '3rd'  => 'third',
        '4th'  => 'fourth', '5th'  => 'fifth',  '6th'  => 'sixth',
        '7th'  => 'seventh','8th'  => 'eighth', '9th'  => 'ninth',
        '10th' => 'tenth',
    ];
    $s = preg_replace_callback('/\b(\d+)(st|nd|rd|th)\b/', function ($m) use ($ords) {
        $full = $m[0];
        return $ords[$full] ?? $full;
    }, $s);

    // Expand single-token abbreviations to their canonical forms.
    static $abbr = [
        'mid'    => 'midterm',
        'midterm'=> 'midterm',
        'final'  => 'final',
        'ann'    => 'annual',
        'annual' => 'annual',
        'qz'     => 'quiz',
        'quizz'  => 'quiz',
        'hw'     => 'homework',
        'cw'     => 'classwork',
        'ut'     => 'unittest',
        'wk'     => 'weekly',
        'mnth'   => 'monthly',
        'mon'    => 'monthly',
        'practcl'=> 'practical',
    ];
    $parts = preg_split('/\s+/', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $parts = array_map(fn($t) => $abbr[$t] ?? $t, $parts);

    // Drop generic trailing noise words so "First Midterm Assessment"
    // ≡ "First Midterm" ≡ "First Midterm Exam".
    static $noise = ['assessment','exam','examination','paper','test','evaluation'];
    while (!empty($parts) && in_array(end($parts), $noise, true)) {
        array_pop($parts);
    }
    // Also drop leading noise ("Exam First Term" → "first term")
    while (!empty($parts) && in_array($parts[0], $noise, true)) {
        array_shift($parts);
    }

    return implode(' ', $parts);
}

/**
 * Human-friendly canonical display name for an assessment identity.
 * Uses the stored name of the longest / earliest assessment in the group
 * when callers have one; falls back to a title-cased form of the key.
 */
function canonicalAssessmentLabel(string $nameKey): string {
    if ($nameKey === '') return 'Untitled';
    return ucwords($nameKey);
}
