<?php
/**
 * Shared result-ranking helpers for Main Campus.
 *
 * getClassResultData(PDO, int classId, bool publishedOnly)
 *   Returns an array of student result rows, each with:
 *     id, name, gr_no, roll_no, subjects (assoc array sid => [...]),
 *     grand_obtained, grand_max, grand_pct, overall_grade, position
 *
 * getStudentPosition(PDO, int studentId, int classId)
 *   Returns the student's 1-based position (or null if no marks).
 */

require_once __DIR__ . '/../../config/config.php';

/**
 * Grade letter from percentage — same IBCC scale as config.php / report-card.php.
 */
function rkGradeLetter(float $pct, bool $hasMarks): string
{
    if (!$hasMarks) return 'N/A';
    if ($pct >= 95) return 'A++';
    if ($pct >= 90) return 'A+';
    if ($pct >= 85) return 'A';
    if ($pct >= 80) return 'B++';
    if ($pct >= 75) return 'B+';
    if ($pct >= 70) return 'B';
    if ($pct >= 60) return 'C';
    if ($pct >= 50) return 'D';
    if ($pct >= 40) return 'E';
    return 'U';
}

/**
 * Fetch all students in a class, compute their weighted percentage,
 * and assign class positions using standard competition ranking (1,1,3).
 *
 * @param  bool   $publishedOnly  true → only published assessments; false → all
 * @return array  Rows sorted by position asc, then name asc
 */
function getClassResultData(PDO $db, int $classId, bool $publishedOnly = true): array
{
    $pubCond = $publishedOnly
        ? "AND COALESCE(a.publish_status,'published')='published'"
        : '';

    // Fetch all marks for this class in one query
    try {
        $st = $db->prepare(
            "SELECT s.id AS student_id,
                    u.name AS student_name,
                    u.user_id AS gr_no,
                    s.roll_no,
                    a.subject_id,
                    sb.name AS subject_name,
                    a.id   AS assessment_id,
                    a.max_marks,
                    a.weight,
                    m.marks_obtained
             FROM students s
             JOIN users u ON s.user_id = u.id
             LEFT JOIN marks m ON m.student_id = s.id
             LEFT JOIN assessments a ON a.id = m.assessment_id AND a.class_id = ?  $pubCond
             LEFT JOIN subjects sb ON sb.id = a.subject_id
             WHERE s.class_id = ? AND s.deleted_at IS NULL
             ORDER BY s.id, a.subject_id, a.id"
        );
        $st->execute([$classId, $classId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }

    if (empty($rows)) {
        // Class exists but no marks yet — still return students with zero marks
        try {
            $st2 = $db->prepare(
                "SELECT s.id AS student_id, u.name AS student_name,
                        u.user_id AS gr_no, s.roll_no
                 FROM students s JOIN users u ON s.user_id=u.id
                 WHERE s.class_id=? AND s.deleted_at IS NULL
                 ORDER BY s.roll_no, u.name"
            );
            $st2->execute([$classId]);
            $studs = $st2->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) { return []; }

        $result = [];
        foreach ($studs as $s) {
            $result[$s['student_id']] = [
                'id'             => $s['student_id'],
                'name'           => $s['student_name'],
                'gr_no'          => $s['gr_no'],
                'roll_no'        => $s['roll_no'],
                'subjects'       => [],
                'grand_obtained' => 0,
                'grand_max'      => 0,
                'grand_pct'      => 0.0,
                'overall_grade'  => 'N/A',
                'position'       => null,
            ];
        }
        return array_values($result);
    }

    // Aggregate by student → subject
    $students = [];
    foreach ($rows as $r) {
        $sid  = $r['student_id'];
        $subj = $r['subject_id'];

        if (!isset($students[$sid])) {
            $students[$sid] = [
                'id'       => $sid,
                'name'     => $r['student_name'],
                'gr_no'    => $r['gr_no'],
                'roll_no'  => $r['roll_no'],
                'subjects' => [],
                '_raw'     => [],
            ];
        }

        if ($r['assessment_id'] === null) continue; // student with no marks at all

        if (!isset($students[$sid]['subjects'][$subj])) {
            $students[$sid]['subjects'][$subj] = [
                'name'       => $r['subject_name'],
                'obtained'   => 0.0,
                'max'        => 0.0,
                'w_sum'      => 0.0,
                'w_total'    => 0.0,
                'pct'        => 0.0,
                'grade'      => 'N/A',
                'has_marks'  => false,
            ];
        }

        if ($r['marks_obtained'] !== null) {
            $ob = (float)$r['marks_obtained'];
            $mx = (float)$r['max_marks'];
            $wt = (float)$r['weight'];

            $students[$sid]['subjects'][$subj]['obtained']  += $ob;
            $students[$sid]['subjects'][$subj]['max']       += $mx;
            $students[$sid]['subjects'][$subj]['has_marks']  = true;

            if ($wt > 0 && $mx > 0) {
                $students[$sid]['subjects'][$subj]['w_sum']   += ($ob / $mx * 100) * $wt;
                $students[$sid]['subjects'][$subj]['w_total'] += $wt;
            }
        }
    }

    // Finalize per-subject stats + grand totals
    foreach ($students as &$stu) {
        $grandObt   = 0.0;
        $grandMax   = 0.0;
        $grandWSum  = 0.0;
        $grandWTot  = 0.0;

        foreach ($stu['subjects'] as $subj => &$sub) {
            if ($sub['max'] > 0) {
                $sub['pct']   = $sub['w_total'] > 0
                    ? round($sub['w_sum'] / $sub['w_total'], 1)
                    : round($sub['obtained'] / $sub['max'] * 100, 1);
                $sub['grade'] = rkGradeLetter($sub['pct'], $sub['has_marks']);

                $grandObt  += $sub['obtained'];
                $grandMax  += $sub['max'];
                if ($sub['w_total'] > 0) {
                    $grandWSum += $sub['w_sum'];
                    $grandWTot += $sub['w_total'];
                }
            }
        }
        unset($sub);

        $grandPct = $grandWTot > 0
            ? round($grandWSum / $grandWTot, 1)
            : ($grandMax > 0 ? round($grandObt / $grandMax * 100, 1) : 0.0);

        $stu['grand_obtained'] = round($grandObt, 2);
        $stu['grand_max']      = round($grandMax, 2);
        $stu['grand_pct']      = $grandPct;
        $stu['overall_grade']  = rkGradeLetter($grandPct, $grandMax > 0);
        unset($stu['_raw']);
    }
    unset($stu);

    // Sort by percentage desc (then name asc for tie-breaking display)
    uasort($students, function ($a, $b) {
        if ($b['grand_max'] == 0 && $a['grand_max'] == 0) return strcmp($a['name'], $b['name']);
        if ($b['grand_max'] == 0) return -1;
        if ($a['grand_max'] == 0) return 1;
        $diff = $b['grand_pct'] - $a['grand_pct'];
        if (abs($diff) > 0.001) return $diff > 0 ? 1 : -1;
        // Tie: secondary sort by total obtained marks desc
        $diff2 = $b['grand_obtained'] - $a['grand_obtained'];
        if (abs($diff2) > 0.001) return $diff2 > 0 ? 1 : -1;
        return strcmp($a['name'], $b['name']);
    });

    // Assign positions — standard competition ranking (1,1,3 for ties)
    $pos    = 1;
    $prev   = null;
    $skip   = 0;
    $ranked = array_values($students);
    foreach ($ranked as $i => &$stu) {
        if ($stu['grand_max'] == 0) {
            $stu['position'] = null;
            continue;
        }
        $key = $stu['grand_pct'] . '|' . $stu['grand_obtained'];
        if ($prev === null || $prev !== $key) {
            $pos  = $i + 1;
            $prev = $key;
        }
        $stu['position'] = $pos;
    }
    unset($stu);

    return $ranked;
}

/**
 * Lightweight: get just one student's position within their class.
 * Returns int (1-based) or null if no marks / can't determine.
 */
function getStudentPosition(PDO $db, int $studentId, int $classId): ?int
{
    $all = getClassResultData($db, $classId, true);
    foreach ($all as $row) {
        if ((int)$row['id'] === $studentId) {
            return $row['position'];
        }
    }
    return null;
}

/**
 * Position ordinal label: 1 → "1st", 2 → "2nd", 3 → "3rd", etc.
 */
function positionLabel(int $pos): string
{
    if ($pos === 1) return '1st';
    if ($pos === 2) return '2nd';
    if ($pos === 3) return '3rd';
    return $pos . 'th';
}
