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
 * Grade letter from percentage — delegates to the central rubric in config.php
 * so every surface in the portal uses the same thresholds.
 */
function rkGradeLetter(float $pct, bool $hasMarks): string
{
    if (!$hasMarks) return 'N/A';
    return getGrade($pct)['label'];
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
                'id'              => $s['student_id'],
                'name'            => $s['student_name'],
                'gr_no'           => $s['gr_no'],
                'roll_no'         => $s['roll_no'],
                'subjects'        => [],
                'grand_obtained'  => 0,
                'grand_max'       => 0,
                'grand_pct'       => 0.0,
                'overall_grade'   => 'N/A',
                'position'        => null,
                'subject_pass'    => false,
                'aggregate_pass'  => false,
                'overall_pass'    => false,
                'pass_status'     => '—',
                'failed_subjects' => [],
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

    // Finalize per-subject stats + grand totals + pass/fail eligibility.
    //
    // IMPORTANT — straight aggregate, not weighted.
    // The ranking rule (requirement #1) is explicitly:
    //     Overall % = (Total Marks Obtained / Total Max Marks) × 100
    // Weighted averages were producing inconsistent rankings — a student
    // with high weighted % could have a lower straight %, causing their
    // report-card percentage to disagree with the merit position.
    // We still carry w_sum/w_total through the shape in case other callers
    // need them, but neither grand_pct NOR sub.pct uses the weighted path
    // for pass/fail or ranking purposes.
    foreach ($students as &$stu) {
        $grandObt   = 0.0;
        $grandMax   = 0.0;

        foreach ($stu['subjects'] as $subj => &$sub) {
            if ($sub['max'] > 0) {
                $sub['pct']    = round($sub['obtained'] / $sub['max'] * 100, 2);
                $sub['grade']  = rkGradeLetter($sub['pct'], $sub['has_marks']);
                $sub['passed'] = isSubjectPassing((float)$sub['pct']);
                $grandObt  += $sub['obtained'];
                $grandMax  += $sub['max'];
            } else {
                $sub['passed'] = false;
            }
        }
        unset($sub);

        $grandPct = $grandMax > 0 ? round($grandObt / $grandMax * 100, 2) : 0.0;

        $hadMarks = $grandMax > 0;
        $stu['grand_obtained'] = round($grandObt, 2);
        $stu['grand_max']      = round($grandMax, 2);
        $stu['grand_pct']      = $grandPct;
        $stu['overall_grade']  = rkGradeLetter($grandPct, $hadMarks);

        // ── Pass/fail: subject-wise AND aggregate ────────────────────────
        $failedSubjects = [];
        if ($hadMarks) {
            foreach ($stu['subjects'] as $sub) {
                if (($sub['max'] ?? 0) > 0 && !isSubjectPassing((float)$sub['pct'])) {
                    $failedSubjects[] = $sub['name'];
                }
            }
        }
        $stu['subject_pass']    = $hadMarks && empty($failedSubjects);
        $stu['aggregate_pass']  = $hadMarks && isAggregatePassing($grandPct);
        $stu['overall_pass']    = $stu['subject_pass'] && $stu['aggregate_pass'];
        $stu['pass_status']     = studentPassStatusLabel($stu['subjects'], $grandPct, $hadMarks);
        $stu['failed_subjects'] = $failedSubjects;

        unset($stu['_raw']);
    }
    unset($stu);

    // ── Sort order ───────────────────────────────────────────────────────
    // Eligible (overall_pass = true) students sort first by percentage desc,
    // then failed students, then no-marks students. This keeps the roster
    // intact (useful for class summaries) while making the merit cohort
    // easy to slice.
    uasort($students, function ($a, $b) {
        // 1. Grouping: eligible > failed-with-marks > no-marks
        $groupA = $a['grand_max'] == 0 ? 2 : ($a['overall_pass'] ? 0 : 1);
        $groupB = $b['grand_max'] == 0 ? 2 : ($b['overall_pass'] ? 0 : 1);
        if ($groupA !== $groupB) return $groupA <=> $groupB;
        // 2. Within group: percentage desc
        $diff = $b['grand_pct'] - $a['grand_pct'];
        if (abs($diff) > 0.001) return $diff > 0 ? 1 : -1;
        // 3. Tie-break: total obtained desc, then name asc
        $diff2 = $b['grand_obtained'] - $a['grand_obtained'];
        if (abs($diff2) > 0.001) return $diff2 > 0 ? 1 : -1;
        return strcmp($a['name'], $b['name']);
    });

    // ── Positions — ONLY awarded to overall-pass students ────────────────
    // Standard competition ranking (1,1,3) among eligible students.
    // Students who failed any subject or whose aggregate is below the
    // threshold get position = null regardless of raw percentage.
    $ranked      = array_values($students);
    $pos         = 0;      // 1-based rank among eligible students
    $eligibleIdx = 0;      // counter for ties
    $prevKey     = null;
    foreach ($ranked as $i => &$stu) {
        if (!$stu['overall_pass']) {
            $stu['position'] = null;
            continue;
        }
        $eligibleIdx++;
        $key = $stu['grand_pct'] . '|' . $stu['grand_obtained'];
        if ($prevKey === null || $prevKey !== $key) {
            $pos      = $eligibleIdx;
            $prevKey  = $key;
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
