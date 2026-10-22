<?php
/**
 * Backfill `assessments.name_key` for every existing row.
 *
 * Run once after applying database/migrations/assessment_name_key.sql.
 * Re-running is safe — it recomputes the key from the current
 * normalizeAssessmentName() implementation (useful if the normalization
 * rules change).
 *
 * Usage:   php scripts/backfill_assessment_name_keys.php
 *          php scripts/backfill_assessment_name_keys.php --dry-run
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/config.php';

$dry = in_array('--dry-run', $argv ?? [], true);
$db  = getDB();

try {
    $db->query("SELECT name_key FROM assessments LIMIT 0");
} catch (Exception $e) {
    fwrite(STDERR, "ERROR: name_key column missing. Run migrations/assessment_name_key.sql first.\n");
    exit(1);
}

$st = $db->query("SELECT id, name, title FROM assessments ORDER BY id");
$total = 0; $changed = 0; $empty = 0;
$upd = $db->prepare("UPDATE assessments SET name_key = ? WHERE id = ?");

while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
    $total++;
    // Prefer `title` when it has content (it's usually the human-friendly
    // name); fall back to `name`.
    $source = trim((string)($row['title'] ?? '')) !== '' ? $row['title'] : $row['name'];
    $key = normalizeAssessmentName((string)$source);
    if ($key === '') { $empty++; continue; }
    if (!$dry) {
        $upd->execute([$key, (int)$row['id']]);
    }
    $changed++;
    if ($dry) {
        printf("  #%-5d  %-40s  → %s\n", $row['id'], mb_substr((string)$source, 0, 40), $key);
    }
}

printf("\nScanned %d assessments · %d keys written · %d skipped (empty)%s\n",
    $total, $changed, $empty, $dry ? ' (DRY RUN — no writes)' : '');
