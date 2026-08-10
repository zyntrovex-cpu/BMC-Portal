<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/config.php';

$user = requireAuth('finance');
requirePermission('fee_collection');
$db = getDB();

// ── Auto-create kuickpay_transactions table ───────────────────────
$db->exec("CREATE TABLE IF NOT EXISTS kuickpay_transactions (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    batch_id        VARCHAR(30)    NOT NULL,
    tran_datetime   DATETIME,
    reg_num         VARCHAR(20),
    consumer_num    VARCHAR(30),
    auth_id         VARCHAR(30),
    tran_date_raw   VARCHAR(10),
    tran_time_raw   VARCHAR(10),
    transaction_id  VARCHAR(60),
    voucher_num     VARCHAR(80),
    bank            VARCHAR(30),
    amount          DECIMAL(10,2)  DEFAULT 0,
    fee_charge      DECIMAL(10,2)  DEFAULT 0,
    tax             DECIMAL(10,2)  DEFAULT 0,
    net_amount      DECIMAL(10,2)  DEFAULT 0,
    consumer_detail VARCHAR(200),
    network         VARCHAR(50),
    channel         VARCHAR(50),
    fee_month       TINYINT,
    fee_year        SMALLINT,
    student_id      INT            NULL,
    status          ENUM('matched','unmatched','duplicate','already_paid') DEFAULT 'unmatched',
    imported_by     INT,
    imported_at     TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_batch(batch_id),
    INDEX idx_reg(reg_num),
    INDEX idx_student(student_id),
    FOREIGN KEY (student_id)  REFERENCES students(id) ON DELETE SET NULL,
    FOREIGN KEY (imported_by) REFERENCES users(id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// ── xlsx parser ───────────────────────────────────────────────────
function kpColToNum(string $col): int {
    $n = 0;
    for ($i = 0, $l = strlen($col); $i < $l; $i++) {
        $n = $n * 26 + (ord($col[$i]) - 64);
    }
    return $n;
}

function parseKuickpayXlsx(string $path): array {
    if (!class_exists('ZipArchive')) {
        throw new Exception('ZipArchive PHP extension is not available on this server.');
    }
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new Exception('Cannot open the uploaded file — it may not be a valid .xlsx file.');
    }

    /* 1. Shared strings (text values stored by index) */
    $ss = [];
    $ssRaw = $zip->getFromName('xl/sharedStrings.xml');
    if ($ssRaw) {
        $doc = new DOMDocument();
        @$doc->loadXML($ssRaw);
        foreach ($doc->getElementsByTagName('si') as $si) {
            $text = '';
            foreach ($si->getElementsByTagName('t') as $t) {
                $text .= $t->nodeValue;
            }
            $ss[] = $text;
        }
    }

    /* 2. Try to detect date-formatted style indices from styles.xml */
    $dateStyleIds = [];
    $stylesRaw = $zip->getFromName('xl/styles.xml');
    if ($stylesRaw) {
        $sdoc = new DOMDocument();
        @$sdoc->loadXML($stylesRaw);
        // Built-in date numFmtIds: 14–17 (date) and 18–21 (time)
        $dateBuiltin = range(14, 21);
        // Collect custom date formats (numFmtId >= 164 with 'y','m','d','h' in formatCode)
        $customDateFmts = [];
        $numFmts = $sdoc->getElementsByTagName('numFmt');
        foreach ($numFmts as $nf) {
            $fmtId   = (int)$nf->getAttribute('numFmtId');
            $fmtCode = strtolower($nf->getAttribute('formatCode'));
            if ($fmtId >= 164 && (str_contains($fmtCode, 'y') || str_contains($fmtCode, 'd') || str_contains($fmtCode, 'h'))) {
                $customDateFmts[] = $fmtId;
            }
        }
        // Map cell xf index → numFmtId
        $xfs = $sdoc->getElementsByTagName('xf');
        foreach ($xfs as $i => $xf) {
            $nfId = (int)$xf->getAttribute('numFmtId');
            if (in_array($nfId, $dateBuiltin) || in_array($nfId, $customDateFmts)) {
                $dateStyleIds[] = $i;
            }
        }
    }

    /* 3. Sheet 1 data */
    $sheetRaw = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    if (!$sheetRaw) throw new Exception('Sheet1 data not found in file.');

    $sdoc2 = new DOMDocument();
    @$sdoc2->loadXML($sheetRaw);

    $rows = [];
    foreach ($sdoc2->getElementsByTagName('row') as $rowEl) {
        $r = (int)$rowEl->getAttribute('r');
        if ($r < 5) continue; // rows 1–4 = title + headers

        $cols = [];
        foreach ($rowEl->getElementsByTagName('c') as $cell) {
            $ref   = $cell->getAttribute('r');
            $type  = $cell->getAttribute('t');   // 's' = shared string
            $sIdx  = (int)$cell->getAttribute('s'); // style index
            $vEls  = $cell->getElementsByTagName('v');
            $raw   = $vEls->length ? $vEls->item(0)->nodeValue : null;

            preg_match('/^([A-Z]+)/', $ref, $m);
            $colN = kpColToNum($m[1]);

            if ($raw === null || $raw === '') continue;

            if ($type === 's') {
                $val = $ss[(int)$raw] ?? '';
            } elseif (is_numeric($raw)) {
                $num = (float)$raw;
                // Column B (2) is Tran.Date; also detect by style
                $isDate = ($colN === 2) || in_array($sIdx, $dateStyleIds);
                if ($isDate && $num > 20000) {
                    // Excel serial → UTC datetime (accounting for 1900 leap-year bug)
                    $ts  = round(($num - 25569) * 86400);
                    $val = gmdate('Y-m-d H:i:s', $ts);
                } else {
                    $val = $num;
                }
            } else {
                $val = $raw;
            }

            $cols[$colN] = $val;
        }

        if (!empty($cols)) $rows[$r] = $cols;
    }

    /* 4. Build transaction list */
    $transactions = [];
    foreach ($rows as $rowNum => $cols) {
        $bank = trim((string)($cols[15] ?? ''));
        // Row with 'Total' in bank column = summary row — stop here
        if (strtolower($bank) === 'total') break;

        $tranDt = $cols[2] ?? null;
        // Skip rows without a transaction datetime
        if (!$tranDt || !is_string($tranDt)) continue;

        $transactions[] = [
            'tran_datetime'   => $tranDt,
            'reg_num'         => trim((string)($cols[3]  ?? '')),
            'consumer_num'    => trim((string)($cols[8]  ?? '')),
            'auth_id'         => trim((string)($cols[9]  ?? '')),
            'tran_date_raw'   => trim((string)($cols[10] ?? '')),
            'tran_time_raw'   => trim((string)($cols[11] ?? '')),
            'transaction_id'  => trim((string)($cols[12] ?? '')),
            'bank'            => $bank,
            'amount'          => (float)($cols[16] ?? 0),
            'fee_charge'      => (float)($cols[17] ?? 0),
            'tax'             => (float)($cols[19] ?? 0),
            'net_amount'      => (float)($cols[20] ?? 0),
            'consumer_detail' => trim((string)($cols[23] ?? '')),
            'voucher_num'     => trim((string)($cols[29] ?? '')),
            'network'         => trim((string)($cols[30] ?? '')),
            'channel'         => trim((string)($cols[31] ?? '')),
        ];
    }

    return $transactions;
}

// ── Enrich transactions with student match info ───────────────────
function enrichTransactions(array $txns, PDO $db, int $fMonth, int $fYear, int $userId): array {
    // Load all students: index by roll_no variants (exact, ltrim-zeros, int-cast)
    $allSt = $db->query(
        'SELECT s.id, s.roll_no, u.name AS student_name, c.name AS class_name
         FROM students s
         JOIN users u ON s.user_id = u.id
         LEFT JOIN classes c ON s.class_id = c.id
         WHERE u.status = "active"'
    )->fetchAll();

    $stMap = [];
    foreach ($allSt as $st) {
        $roll = $st['roll_no'];
        $stMap[$roll]              = $st; // exact
        $stMap[ltrim($roll,'0')]   = $st; // no leading zeros
        $stMap[(string)(int)$roll] = $st; // integer string
    }

    // Already paid students this month
    $paidSt = $db->prepare('SELECT student_id FROM fees WHERE month=? AND year=? AND paid=1');
    $paidSt->execute([$fMonth, $fYear]);
    $alreadyPaid = array_flip($paidSt->fetchAll(PDO::FETCH_COLUMN));

    // Already imported vouchers (dedup within same month)
    $existVch = $db->prepare(
        'SELECT DISTINCT voucher_num FROM kuickpay_transactions WHERE fee_month=? AND fee_year=?'
    );
    $existVch->execute([$fMonth, $fYear]);
    $existVchSet = array_flip($existVch->fetchAll(PDO::FETCH_COLUMN));

    foreach ($txns as &$t) {
        $regNum = $t['reg_num'];
        $student = $stMap[$regNum]
                ?? $stMap[ltrim($regNum,'0')]
                ?? $stMap[(string)(int)$regNum]
                ?? null;

        // Dedup check first (same voucher already imported for this month)
        if (!empty($t['voucher_num']) && isset($existVchSet[$t['voucher_num']])) {
            $t['status']       = 'duplicate';
            $t['student_id']   = null;
            $t['student_name'] = $student['student_name'] ?? '—';
            $t['class_name']   = $student['class_name']   ?? '—';
            continue;
        }

        if ($student) {
            $t['student_id']   = $student['id'];
            $t['student_name'] = $student['student_name'];
            $t['class_name']   = $student['class_name'];
            $t['status']       = isset($alreadyPaid[$student['id']]) ? 'already_paid' : 'matched';
        } else {
            $t['student_id']   = null;
            $t['student_name'] = '—';
            $t['class_name']   = '—';
            $t['status']       = 'unmatched';
        }
    }
    unset($t);

    return $txns;
}

// ── Request handling ──────────────────────────────────────────────
$step        = 'upload';
$err         = '';
$preview     = [];
$importStats = null;
$months      = ['','January','February','March','April','May','June','July',
                'August','September','October','November','December'];
$monthShort  = ['','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

// Restore preview from session if still valid
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_SESSION['kp_preview']['user_id']) && $_SESSION['kp_preview']['user_id'] === $user['id']) {
    $step    = 'preview';
    $preview = $_SESSION['kp_preview']['txns'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Step 1: Upload → Preview ──────────────────────────────────
    if ($action === 'preview') {
        $fMonth = max(1, min(12, (int)($_POST['fee_month'] ?? date('n'))));
        $fYear  = (int)($_POST['fee_year'] ?? date('Y'));

        $fileOk = isset($_FILES['kpfile']) && $_FILES['kpfile']['error'] === UPLOAD_ERR_OK;
        if (!$fileOk) {
            $err = 'Please select a Kuickpay Excel (.xlsx) file to upload.';
        } else {
            try {
                $txns = parseKuickpayXlsx($_FILES['kpfile']['tmp_name']);
                if (empty($txns)) {
                    $err = 'No transaction rows found in this file. Check that it matches the Kuickpay PPM Payment Log format.';
                } else {
                    $txns = enrichTransactions($txns, $db, $fMonth, $fYear, $user['id']);
                    $_SESSION['kp_preview'] = [
                        'txns'    => $txns,
                        'month'   => $fMonth,
                        'year'    => $fYear,
                        'user_id' => $user['id'],
                    ];
                    $step    = 'preview';
                    $preview = $txns;
                }
            } catch (Exception $e) {
                $err = 'File parse error: ' . $e->getMessage();
            }
        }
    }

    // ── Step 2: Confirm → Import ──────────────────────────────────
    if ($action === 'import') {
        $pData = $_SESSION['kp_preview'] ?? null;
        if (!$pData || ($pData['user_id'] ?? 0) !== $user['id']) {
            $err  = 'Preview session expired. Please re-upload the file.';
            $step = 'upload';
        } else {
            $txns    = $pData['txns'];
            $fMonth  = $pData['month'];
            $fYear   = $pData['year'];
            $batchId = 'KP' . date('YmdHis');

            $cMatched = $cSkipped = $cDuplicate = $cUnmatched = 0;
            $totalAmt = 0.0;

            $stmtKp = $db->prepare(
                'INSERT INTO kuickpay_transactions
                 (batch_id,tran_datetime,reg_num,consumer_num,auth_id,tran_date_raw,
                  tran_time_raw,transaction_id,voucher_num,bank,amount,fee_charge,tax,
                  net_amount,consumer_detail,network,channel,fee_month,fee_year,
                  student_id,status,imported_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            );
            $stmtFee = $db->prepare(
                'INSERT INTO fees (student_id,month,year,amount,paid,payment_date,
                  payment_mode,receipt_no,remarks,recorded_by)
                 VALUES (?,?,?,?,1,?,\'Online\',?,?,?)
                 ON DUPLICATE KEY UPDATE
                   paid=1, payment_date=VALUES(payment_date),
                   payment_mode=\'Online\', amount=VALUES(amount),
                   receipt_no=VALUES(receipt_no), remarks=VALUES(remarks),
                   recorded_by=VALUES(recorded_by)'
            );

            $db->beginTransaction();
            try {
                foreach ($txns as $t) {
                    $status    = $t['status'];
                    $studentId = $t['student_id'] ?? null;

                    if ($status === 'matched' && $studentId) {
                        $payDate = $t['tran_datetime'] ? substr($t['tran_datetime'], 0, 10) : date('Y-m-d');
                        $remarks = 'Kuickpay | Bank: ' . $t['bank']
                                 . ' | Auth: ' . $t['auth_id']
                                 . ' | Network: ' . $t['network'];
                        $stmtFee->execute([
                            $studentId, $fMonth, $fYear, $t['amount'],
                            $payDate,
                            $t['voucher_num'] ?: null,
                            $remarks,
                            $user['id'],
                        ]);
                        $cMatched++;
                        $totalAmt += $t['amount'];
                    } elseif ($status === 'already_paid') { $cSkipped++;
                    } elseif ($status === 'duplicate')    { $cDuplicate++;
                    } else                                 { $cUnmatched++; }

                    // Always log every transaction for audit trail
                    $stmtKp->execute([
                        $batchId, $t['tran_datetime'], $t['reg_num'],
                        $t['consumer_num'], $t['auth_id'], $t['tran_date_raw'],
                        $t['tran_time_raw'], $t['transaction_id'], $t['voucher_num'],
                        $t['bank'], $t['amount'], $t['fee_charge'], $t['tax'],
                        $t['net_amount'], $t['consumer_detail'], $t['network'],
                        $t['channel'], $fMonth, $fYear, $studentId, $status, $user['id'],
                    ]);
                }
                $db->commit();
                logActivity($user['id'], 'kuickpay_import',
                    "Kuickpay batch $batchId: $cMatched matched, $cUnmatched unmatched, $cSkipped already-paid, $cDuplicate duplicates");
                unset($_SESSION['kp_preview']);
                $step        = 'done';
                $importStats = [
                    'matched'    => $cMatched,
                    'skipped'    => $cSkipped,
                    'duplicate'  => $cDuplicate,
                    'unmatched'  => $cUnmatched,
                    'totalAmt'   => $totalAmt,
                    'batchId'    => $batchId,
                    'month'      => $fMonth,
                    'year'       => $fYear,
                ];
            } catch (Exception $e) {
                $db->rollBack();
                $err  = 'Import failed: ' . $e->getMessage();
                $step = 'preview';
                $preview = $txns;
                $_SESSION['kp_preview'] = $pData; // restore
            }
        }
    }

    // ── Cancel preview ────────────────────────────────────────────
    if ($action === 'cancel') {
        unset($_SESSION['kp_preview']);
        redirect('/finance/kuickpay.php');
    }
}

// ── Import history (last 10 batches) ─────────────────────────────
$history = [];
try {
    $history = $db->query(
        'SELECT batch_id, fee_month, fee_year,
                COUNT(*) AS total_txns,
                SUM(status="matched")     AS cnt_matched,
                SUM(status="unmatched")   AS cnt_unmatched,
                SUM(status="already_paid") AS cnt_already_paid,
                SUM(status="duplicate")   AS cnt_duplicate,
                SUM(IF(status="matched",amount,0)) AS total_amount,
                MIN(imported_at) AS imported_at
         FROM kuickpay_transactions
         GROUP BY batch_id, fee_month, fee_year
         ORDER BY imported_at DESC
         LIMIT 12'
    )->fetchAll();
} catch (Exception $e) { /* table just created, no data */ }

// ── Preview stats ─────────────────────────────────────────────────
$pvStats = [];
if (!empty($preview)) {
    foreach ($preview as $t) { $pvStats[$t['status']] = ($pvStats[$t['status']] ?? 0) + 1; }
    $pvStats['total']      = count($preview);
    $pvStats['total_amount'] = array_sum(array_column(
        array_filter($preview, fn($t) => $t['status'] === 'matched'), 'amount'
    ));
}

$fMonthSel = $_SESSION['kp_preview']['month'] ?? (int)date('n');
$fYearSel  = $_SESSION['kp_preview']['year']  ?? (int)date('Y');

pageHead('Kuickpay Import', 'finance');
$links = getFinanceLinks();
?>
<div class="portal-wrap">
<?php sidebar('finance', 'kuickpay', $links, $user); ?>
<div class="main-area">
<?php topbar('Kuickpay Import', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<?php if ($err): ?>
<div class="alert alert-danger d-flex align-items-center gap-2 mb-3">
  <i class="fas fa-exclamation-circle"></i> <?= h($err) ?>
</div>
<?php endif; ?>

<?php /* ═══════════════════ DONE STATE ═══════════════════ */ ?>
<?php if ($step === 'done' && $importStats): ?>
<div class="sec-card mb-4" style="border-top:4px solid #059669">
  <div class="sec-head" style="background:#f0fdf4">
    <h5 style="color:#059669"><i class="fas fa-check-circle me-2"></i>Import Successful</h5>
    <small class="text-muted">Batch: <?= h($importStats['batchId']) ?></small>
  </div>
  <div class="p-4">
    <p class="mb-3" style="font-size:1rem">
      Kuickpay file imported for <strong><?= $months[$importStats['month']] . ' ' . $importStats['year'] ?></strong>.
    </p>
    <div class="row g-3 mb-4">
      <div class="col-6 col-md-3">
        <div class="text-center p-3 rounded" style="background:#f0fdf4;border:1px solid #bbf7d0">
          <div style="font-size:2rem;font-weight:700;color:#059669"><?= $importStats['matched'] ?></div>
          <div style="font-size:.8rem;color:#6b7280">Fee Records Updated</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="text-center p-3 rounded" style="background:#fef2f2;border:1px solid #fecaca">
          <div style="font-size:2rem;font-weight:700;color:#ef4444"><?= $importStats['unmatched'] ?></div>
          <div style="font-size:.8rem;color:#6b7280">Unmatched (no student)</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="text-center p-3 rounded" style="background:#fffbeb;border:1px solid #fde68a">
          <div style="font-size:2rem;font-weight:700;color:#d97706"><?= $importStats['skipped'] ?></div>
          <div style="font-size:.8rem;color:#6b7280">Already Paid (skipped)</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="text-center p-3 rounded" style="background:#f8fafc;border:1px solid #e2e8f0">
          <div style="font-size:2rem;font-weight:700;color:#6b7280"><?= $importStats['duplicate'] ?></div>
          <div style="font-size:.8rem;color:#6b7280">Duplicates (skipped)</div>
        </div>
      </div>
    </div>
    <div class="d-flex align-items-center gap-2 mb-4 p-3 rounded" style="background:#fff7ed;border:1px solid #fde68a">
      <i class="fas fa-rupee-sign" style="color:#d97706;font-size:1.2rem"></i>
      <span>Total Fee Amount Collected via Kuickpay:</span>
      <strong style="font-size:1.1rem;color:#d97706">PKR <?= number_format($importStats['totalAmt']) ?></strong>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <a href="<?= url('/finance/kuickpay.php') ?>" class="btn btn-primary">
        <i class="fas fa-upload me-2"></i>Import Another File
      </a>
      <a href="<?= url('/finance/records.php') ?>" class="btn btn-outline-secondary">
        <i class="fas fa-file-invoice-dollar me-2"></i>View Fee Records
      </a>
    </div>
  </div>
</div>

<?php /* ═══════════════════ PREVIEW STATE ═══════════════════ */ ?>
<?php elseif ($step === 'preview' && !empty($preview)): ?>
<?php $pv = $_SESSION['kp_preview'] ?? []; ?>
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <h5 class="mb-0 fw-bold">
    <i class="fas fa-eye me-2" style="color:#d97706"></i>
    Preview — <?= $months[$pv['month'] ?? $fMonthSel] ?> <?= $pv['year'] ?? $fYearSel ?>
  </h5>
  <form method="POST">
    <input type="hidden" name="action" value="cancel">
    <button type="submit" class="btn btn-sm btn-outline-secondary">
      <i class="fas fa-arrow-left me-1"></i>Cancel &amp; Re-upload
    </button>
  </form>
</div>

<!-- Summary badges -->
<div class="row g-2 mb-3">
  <div class="col-6 col-md-3">
    <div class="d-flex align-items-center gap-2 p-2 rounded border" style="background:#f0fdf4">
      <span class="badge bg-success" style="font-size:.9rem;min-width:32px"><?= $pvStats['matched']    ?? 0 ?></span>
      <span style="font-size:.82rem">Will be marked Paid</span>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="d-flex align-items-center gap-2 p-2 rounded border" style="background:#fef2f2">
      <span class="badge bg-danger" style="font-size:.9rem;min-width:32px"><?= $pvStats['unmatched']   ?? 0 ?></span>
      <span style="font-size:.82rem">No student match</span>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="d-flex align-items-center gap-2 p-2 rounded border" style="background:#fffbeb">
      <span class="badge bg-warning text-dark" style="font-size:.9rem;min-width:32px"><?= $pvStats['already_paid'] ?? 0 ?></span>
      <span style="font-size:.82rem">Already paid (skip)</span>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="d-flex align-items-center gap-2 p-2 rounded border" style="background:#f8fafc">
      <span class="badge bg-secondary" style="font-size:.9rem;min-width:32px"><?= $pvStats['duplicate']  ?? 0 ?></span>
      <span style="font-size:.82rem">Duplicate (skip)</span>
    </div>
  </div>
</div>

<?php if (($pvStats['matched'] ?? 0) > 0): ?>
<div class="alert alert-warning d-flex align-items-center gap-2 mb-3 py-2">
  <i class="fas fa-info-circle"></i>
  <span>
    <strong><?= $pvStats['matched'] ?> students</strong> will have their
    <?= $months[$pv['month']] ?> <?= $pv['year'] ?> fee marked as <strong>Paid via Kuickpay</strong>.
    Total amount: <strong>PKR <?= number_format($pvStats['total_amount'] ?? 0) ?></strong>.
    Review the table below before confirming.
  </span>
</div>
<?php endif; ?>

<!-- Preview table -->
<div class="sec-card mb-4">
  <div class="sec-card-header d-flex justify-content-between align-items-center">
    <span><i class="fas fa-list me-2"></i>Transactions (<?= $pvStats['total'] ?? 0 ?>)</span>
    <span style="font-size:.78rem;color:#6b7280">Green rows will update fee records</span>
  </div>
  <div class="table-responsive" style="max-height:460px;overflow-y:auto">
    <table class="table table-sm mb-0" style="font-size:.8rem">
      <thead class="table-light" style="position:sticky;top:0;z-index:1">
        <tr>
          <th>#</th>
          <th>Date &amp; Time</th>
          <th>Reg. No.</th>
          <th>Student Name</th>
          <th>Class</th>
          <th>Bank</th>
          <th class="text-end">Amount (PKR)</th>
          <th class="text-end">Kuickpay Fee</th>
          <th class="text-end">Net Received</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($preview as $i => $t): ?>
        <?php
          $rowCls = match($t['status']) {
            'matched'     => 'table-success',
            'unmatched'   => 'table-danger',
            'already_paid'=> 'table-warning',
            'duplicate'   => '',
            default       => '',
          };
          $badge = match($t['status']) {
            'matched'     => '<span class="badge bg-success">Will Pay</span>',
            'unmatched'   => '<span class="badge bg-danger">No Match</span>',
            'already_paid'=> '<span class="badge bg-warning text-dark">Already Paid</span>',
            'duplicate'   => '<span class="badge bg-secondary">Duplicate</span>',
            default       => '',
          };
        ?>
        <tr class="<?= $rowCls ?>">
          <td class="text-muted"><?= $i+1 ?></td>
          <td>
            <?= $t['tran_datetime'] ? date('d M Y', strtotime($t['tran_datetime'])) : '—' ?>
            <div style="font-size:.72rem;color:#6b7280"><?= $t['tran_datetime'] ? date('H:i:s', strtotime($t['tran_datetime'])) : '' ?></div>
          </td>
          <td><strong><?= h($t['reg_num']) ?></strong></td>
          <td>
            <?= h($t['consumer_detail']) ?>
            <?php if ($t['student_name'] !== '—' && $t['student_name'] !== $t['consumer_detail']): ?>
              <div style="font-size:.7rem;color:#6b7280">→ <?= h($t['student_name']) ?></div>
            <?php endif; ?>
          </td>
          <td><?= h($t['class_name']) ?></td>
          <td><span class="badge bg-light text-dark border"><?= h($t['bank']) ?></span></td>
          <td class="text-end fw-semibold"><?= number_format($t['amount']) ?></td>
          <td class="text-end text-muted"><?= number_format($t['fee_charge']) ?></td>
          <td class="text-end text-success"><?= number_format($t['net_amount']) ?></td>
          <td><?= $badge ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot class="table-secondary" style="font-weight:600;font-size:.82rem">
        <tr>
          <td colspan="6" class="text-end">Total (<?= count($preview) ?> transactions):</td>
          <td class="text-end">PKR <?= number_format(array_sum(array_column($preview,'amount'))) ?></td>
          <td class="text-end">PKR <?= number_format(array_sum(array_column($preview,'fee_charge'))) ?></td>
          <td class="text-end text-success">PKR <?= number_format(array_sum(array_column($preview,'net_amount'))) ?></td>
          <td></td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<!-- Confirm button -->
<form method="POST">
  <input type="hidden" name="action" value="import">
  <div class="d-flex gap-2">
    <button type="submit" class="btn btn-success fw-semibold"
      <?= ($pvStats['matched'] ?? 0) === 0 ? 'disabled' : '' ?>
      onclick="return confirm('Confirm import?\n\n<?= $pvStats['matched'] ?? 0 ?> fee records will be marked as Paid.\nThis cannot be undone.')">
      <i class="fas fa-check-circle me-2"></i>
      Confirm Import (<?= $pvStats['matched'] ?? 0 ?> students)
    </button>
    <form method="POST" class="d-inline m-0">
      <input type="hidden" name="action" value="cancel">
      <button type="submit" class="btn btn-outline-secondary">Cancel</button>
    </form>
  </div>
</form>

<?php /* ═══════════════════ UPLOAD STATE ═══════════════════ */ ?>
<?php else: ?>
<div class="row g-4">
  <!-- Upload card -->
  <div class="col-lg-7">
    <div class="sec-card">
      <div class="sec-head">
        <h5><i class="fas fa-file-excel me-2" style="color:#059669"></i>Upload Kuickpay File</h5>
      </div>
      <div class="p-4">
        <p class="text-muted mb-4" style="font-size:.88rem">
          Upload the <strong>Kuickpay PPM Payment Log</strong> (.xlsx) received from Kuickpay every 24 hours.
          The system will automatically match transactions to students by Registration Number and update their fee status.
        </p>
        <form method="POST" enctype="multipart/form-data" id="uploadForm">
          <input type="hidden" name="action" value="preview">

          <div class="row g-3 mb-3">
            <div class="col-md-5">
              <label class="form-label fw-semibold" style="font-size:.84rem">Fee Month *</label>
              <select name="fee_month" class="form-select">
                <?php for ($m=1; $m<=12; $m++): ?>
                  <option value="<?= $m ?>" <?= $m===$fMonthSel?'selected':'' ?>><?= $months[$m] ?></option>
                <?php endfor; ?>
              </select>
              <div class="form-text">Select which month these payments are for</div>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold" style="font-size:.84rem">Year *</label>
              <select name="fee_year" class="form-select">
                <?php for ($y=2024; $y<=2028; $y++): ?>
                  <option value="<?= $y ?>" <?= $y===$fYearSel?'selected':'' ?>><?= $y ?></option>
                <?php endfor; ?>
              </select>
            </div>
          </div>

          <!-- Drop zone -->
          <div id="dropZone" class="mb-3 rounded text-center" style="
            border:2px dashed #d97706;padding:40px 20px;cursor:pointer;
            background:#fffbeb;transition:background .2s;">
            <i class="fas fa-file-excel mb-3" style="font-size:2.5rem;color:#059669;display:block"></i>
            <div class="fw-semibold mb-1">Drag &amp; drop the Kuickpay .xlsx file here</div>
            <div class="text-muted mb-3" style="font-size:.83rem">or click to browse</div>
            <input type="file" name="kpfile" id="kpfile" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
              style="display:none" required>
            <label for="kpfile" class="btn btn-outline-warning btn-sm">
              <i class="fas fa-folder-open me-1"></i>Browse File
            </label>
            <div id="fileName" class="mt-2" style="font-size:.82rem;color:#059669;font-weight:600"></div>
          </div>

          <button type="submit" class="btn btn-warning fw-semibold w-100" id="previewBtn">
            <i class="fas fa-eye me-2"></i>Parse &amp; Preview Transactions
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- Info card -->
  <div class="col-lg-5">
    <div class="sec-card mb-3">
      <div class="sec-head">
        <h6><i class="fas fa-info-circle me-2" style="color:#d97706"></i>How it works</h6>
      </div>
      <div class="p-3">
        <ol class="mb-0" style="font-size:.84rem;padding-left:1.2rem;line-height:2">
          <li>Receive the Kuickpay PPM Payment Log (.xlsx) from Kuickpay</li>
          <li>Select the fee month &amp; year these payments belong to</li>
          <li>Upload the file — system previews all transactions</li>
          <li>Review matched vs unmatched rows</li>
          <li>Confirm to mark matched students as <strong>Paid</strong></li>
        </ol>
      </div>
    </div>
    <div class="sec-card">
      <div class="sec-head">
        <h6><i class="fas fa-tags me-2" style="color:#d97706"></i>Status Legend</h6>
      </div>
      <div class="p-3" style="font-size:.83rem">
        <div class="d-flex align-items-center gap-2 mb-2">
          <span class="badge bg-success">Will Pay</span>
          <span class="text-muted">Student matched — fee will be marked Paid</span>
        </div>
        <div class="d-flex align-items-center gap-2 mb-2">
          <span class="badge bg-danger">No Match</span>
          <span class="text-muted">Reg. No. not found in student records</span>
        </div>
        <div class="d-flex align-items-center gap-2 mb-2">
          <span class="badge bg-warning text-dark">Already Paid</span>
          <span class="text-muted">Student already has a paid record this month</span>
        </div>
        <div class="d-flex align-items-center gap-2">
          <span class="badge bg-secondary">Duplicate</span>
          <span class="text-muted">Voucher already imported for this month</span>
        </div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ── Import History ─────────────────────────────────────────── -->
<?php if (!empty($history)): ?>
<div class="sec-card mt-4">
  <div class="sec-head">
    <h5><i class="fas fa-history me-2" style="color:#d97706"></i>Import History</h5>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0" style="font-size:.82rem">
      <thead class="table-light">
        <tr>
          <th>Batch ID</th>
          <th>Fee Period</th>
          <th>Imported On</th>
          <th class="text-center">Total Txns</th>
          <th class="text-center">Matched</th>
          <th class="text-center">Unmatched</th>
          <th class="text-center">Skipped</th>
          <th class="text-end">Amount</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($history as $h): ?>
        <tr>
          <td><code style="font-size:.77rem"><?= h($h['batch_id']) ?></code></td>
          <td><?= $months[$h['fee_month']] ?> <?= $h['fee_year'] ?></td>
          <td><?= date('d M Y, h:i A', strtotime($h['imported_at'])) ?></td>
          <td class="text-center"><?= $h['total_txns'] ?></td>
          <td class="text-center"><span class="badge bg-success"><?= $h['cnt_matched'] ?></span></td>
          <td class="text-center"><span class="badge bg-<?= $h['cnt_unmatched'] > 0 ? 'danger' : 'secondary' ?>"><?= $h['cnt_unmatched'] ?></span></td>
          <td class="text-center"><span class="badge bg-secondary"><?= (int)$h['cnt_already_paid'] + (int)$h['cnt_duplicate'] ?></span></td>
          <td class="text-end fw-semibold" style="color:#d97706">PKR <?= number_format($h['total_amount']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

</div><!-- /page-content -->
</div><!-- /main-area -->
</div><!-- /portal-wrap -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Drag & drop + file name display ──────────────────────────────
const dropZone = document.getElementById('dropZone');
const fileInput = document.getElementById('kpfile');
const fileNameEl = document.getElementById('fileName');

if (dropZone && fileInput) {
  dropZone.addEventListener('dragover', e => {
    e.preventDefault();
    dropZone.style.background = '#fef9c3';
  });
  dropZone.addEventListener('dragleave', () => dropZone.style.background = '#fffbeb');
  dropZone.addEventListener('drop', e => {
    e.preventDefault();
    dropZone.style.background = '#fffbeb';
    const f = e.dataTransfer.files[0];
    if (f) {
      // Create DataTransfer to set file input value
      const dt = new DataTransfer();
      dt.items.add(f);
      fileInput.files = dt.files;
      fileNameEl.textContent = '✓ ' + f.name;
    }
  });
  fileInput.addEventListener('change', () => {
    const f = fileInput.files[0];
    fileNameEl.textContent = f ? ('✓ ' + f.name) : '';
  });
}

// ── Loading state on submit ───────────────────────────────────────
const uploadForm = document.getElementById('uploadForm');
const previewBtn = document.getElementById('previewBtn');
if (uploadForm && previewBtn) {
  uploadForm.addEventListener('submit', () => {
    previewBtn.disabled = true;
    previewBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Parsing file…';
  });
}
</script>
</body>
</html>
