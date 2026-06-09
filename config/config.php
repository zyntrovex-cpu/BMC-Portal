<?php
define('APP_NAME',    'BMC Portal');
define('SCHOOL_NAME', 'Bahria Model College');
define('SESSION_YEAR','2025-26');
define('BASE_URL',    '/');   // change to e.g. '/bmc-portal/' if in a subdirectory

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
