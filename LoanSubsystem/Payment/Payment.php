<?php
session_start();

// ─── DB CONFIG ────────────────────────────────────────────────────────────────
$host   = "localhost";
$dbuser = "root";
$dbpass = "";
$dbname = "loandb";

// ─── DYNAMIC BASE URL (works on localhost AND ngrok / any proxy) ──────────────
$_SCHEME   = (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) ? $_SERVER['HTTP_X_FORWARDED_PROTO'] : (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http'));
$_HOST     = $_SERVER['HTTP_HOST'];
$_BASE_URL = $_SCHEME . '://' . $_HOST . '/Evergreen-loan-main/LoanSubsystem';
$_LOAN_HOME    = $_BASE_URL . '/Loan/index.php';
$_DASHBOARD    = $_BASE_URL . '/Loan/Dashboard.php';
$_PAYMENT_PAGE = $_BASE_URL . '/Payment/Payment.php';

// ─── AUTH GUARD ───────────────────────────────────────────────────────────────
if (!isset($_SESSION['user_email'])) {
    if (isset($_SESSION['user_id']) && isset($_SESSION['email'])) {
        try {
            $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $dbuser, $dbpass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $stmt = $pdo->prepare("SELECT id, full_name, user_email, role FROM users WHERE id = ? AND user_email = ? LIMIT 1");
            $stmt->execute([$_SESSION['user_id'], $_SESSION['email']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                $_SESSION['user_email'] = $user['user_email'];
                $_SESSION['user_name']  = $user['full_name'];
                $_SESSION['user_role']  = strtolower($user['role']);
            } else {
                session_destroy();
                header('Location: ../../login.php');
                exit();
            }
        } catch (PDOException $e) {
            session_destroy();
            header('Location: ../../login.php?error=db');
            exit();
        }
    } else {
        header('Location: ../../login.php');
        exit();
    }
}

// ─── ENSURE PAYMENTS TABLE EXISTS & MIGRATE ──────────────────────────────────
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $dbuser, $dbpass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    $pdo->exec("CREATE TABLE IF NOT EXISTS loan_payments (
        id                  INT UNSIGNED    NOT NULL AUTO_INCREMENT,
        loan_application_id INT             NOT NULL,
        user_email          VARCHAR(255)    NOT NULL,
        borrower_name       VARCHAR(150)    DEFAULT NULL,
        account_number      VARCHAR(50)     DEFAULT NULL,
        amount              DECIMAL(15,2)   NOT NULL,
        payment_method      ENUM('online','cheque') NOT NULL,
        transaction_ref     VARCHAR(70)     NOT NULL,
        payment_date        DATETIME(6)     NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
        created_at          TIMESTAMP(6)    NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
        updated_at          TIMESTAMP(6)    NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
        status              VARCHAR(30)     NOT NULL DEFAULT 'Completed',
        notes               TEXT            DEFAULT NULL,
        processed_by        VARCHAR(150)    DEFAULT NULL,
        processed_by_id     INT             DEFAULT NULL,
        ip_address          VARCHAR(45)     DEFAULT NULL,
        user_agent          VARCHAR(255)    DEFAULT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_transaction_ref (transaction_ref),
        INDEX idx_loan_app  (loan_application_id),
        INDEX idx_email     (user_email),
        INDEX idx_pay_date  (payment_date),
        INDEX idx_status    (status),
        INDEX idx_method    (payment_method),
        INDEX idx_officer   (processed_by_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Migrate old column name loan_id → loan_application_id if needed
    $oldCol = $pdo->query("SHOW COLUMNS FROM loan_payments LIKE 'loan_id'")->fetchAll();
    if (!empty($oldCol)) {
        $pdo->exec("ALTER TABLE loan_payments CHANGE COLUMN `loan_id` `loan_application_id` INT NOT NULL");
    }

    // Add any columns that might be missing
    $missingCols = [
        'borrower_name'   => "ALTER TABLE loan_payments ADD COLUMN borrower_name VARCHAR(150) DEFAULT NULL AFTER user_email",
        'account_number'  => "ALTER TABLE loan_payments ADD COLUMN account_number VARCHAR(50) DEFAULT NULL AFTER borrower_name",
        'updated_at'      => "ALTER TABLE loan_payments ADD COLUMN updated_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6) AFTER created_at",
        'notes'           => "ALTER TABLE loan_payments ADD COLUMN notes TEXT DEFAULT NULL AFTER status",
        'processed_by'    => "ALTER TABLE loan_payments ADD COLUMN processed_by VARCHAR(150) DEFAULT NULL AFTER notes",
        'processed_by_id' => "ALTER TABLE loan_payments ADD COLUMN processed_by_id INT DEFAULT NULL AFTER processed_by",
        'ip_address'      => "ALTER TABLE loan_payments ADD COLUMN ip_address VARCHAR(45) DEFAULT NULL AFTER processed_by_id",
        'user_agent'      => "ALTER TABLE loan_payments ADD COLUMN user_agent VARCHAR(255) DEFAULT NULL AFTER ip_address",
    ];
    foreach ($missingCols as $col => $sql) {
        $exists = $pdo->query("SHOW COLUMNS FROM loan_payments LIKE '$col'")->fetchAll();
        if (empty($exists)) $pdo->exec($sql);
    }

    // ── CATCH-UP: Close loans already fully paid but still Active/Approved ──
    $pdo->exec("
        UPDATE loan_applications la
        INNER JOIN (
            SELECT lp.loan_application_id, SUM(lp.amount) AS total_paid
            FROM   loan_payments lp
            WHERE  lp.status = 'Completed'
            GROUP  BY lp.loan_application_id
        ) paid ON paid.loan_application_id = la.id
        SET    la.status = 'Closed', la.next_payment_due = NULL
        WHERE  la.status IN ('Active', 'Approved')
          AND  paid.total_paid >= la.loan_amount
    ");

} catch (PDOException $e) { /* non-fatal */ }

// ══════════════════════════════════════════════════════════════════════════════
// ─── MAIL CONFIG (mirrors forgotpass.php exactly) ────────────────────────────
// ══════════════════════════════════════════════════════════════════════════════
$MAIL_HOST      = 'smtp.gmail.com';
$MAIL_PORT      = 587;
$MAIL_USERNAME  = 'franciscarpeso@gmail.com';
$MAIL_PASSWORD  = 'bwobttvnbpqvzimv';
$MAIL_FROM      = 'franciscarpeso@gmail.com';
$MAIL_FROM_NAME = 'Evergreen Trust and Savings';

// ══════════════════════════════════════════════════════════════════════════════
// ─── FPDF EXTENDED: adds Circle() support ────────────────────────────────────
// ══════════════════════════════════════════════════════════════════════════════
if (file_exists(__DIR__ . '/fpdf/fpdf.php')) {
    require_once __DIR__ . '/fpdf/fpdf.php';

    class FPDF_Extended extends FPDF {
        public function Circle(float $x, float $y, float $r, string $style = 'D'): void {
            $this->Ellipse($x, $y, $r, $r, $style);
        }

        public function Ellipse(float $x, float $y, float $rx, float $ry, string $style = 'D'): void {
            if ($style === 'F')                       $op = 'f';
            elseif ($style === 'FD' || $style === 'DF') $op = 'B';
            else                                        $op = 'S';

            $lx = (4 / 3) * (M_SQRT2 - 1) * $rx;
            $ly = (4 / 3) * (M_SQRT2 - 1) * $ry;
            $k  = $this->k;
            $h  = $this->h;

            $this->_out(sprintf(
                '%.2F %.2F m '
              . '%.2F %.2F %.2F %.2F %.2F %.2F c '
              . '%.2F %.2F %.2F %.2F %.2F %.2F c '
              . '%.2F %.2F %.2F %.2F %.2F %.2F c '
              . '%.2F %.2F %.2F %.2F %.2F %.2F c %s',
                ($x + $rx) * $k,         ($h - $y) * $k,
                ($x + $rx) * $k,         ($h - ($y - $ly)) * $k,
                ($x + $lx) * $k,         ($h - ($y - $ry)) * $k,
                $x * $k,                 ($h - ($y - $ry)) * $k,
                ($x - $lx) * $k,         ($h - ($y - $ry)) * $k,
                ($x - $rx) * $k,         ($h - ($y - $ly)) * $k,
                ($x - $rx) * $k,         ($h - $y) * $k,
                ($x - $rx) * $k,         ($h - ($y + $ly)) * $k,
                ($x - $lx) * $k,         ($h - ($y + $ry)) * $k,
                $x * $k,                 ($h - ($y + $ry)) * $k,
                ($x + $lx) * $k,         ($h - ($y + $ry)) * $k,
                ($x + $rx) * $k,         ($h - ($y + $ly)) * $k,
                ($x + $rx) * $k,         ($h - $y) * $k,
                $op
            ));
        }
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// ─── PDF RECEIPT GENERATOR ───────────────────────────────────────────────────
// ══════════════════════════════════════════════════════════════════════════════
function generateReceiptPdf(array $data): void {
    if (!class_exists('FPDF_Extended')) return;

    $pdf = new FPDF_Extended('P', 'mm', 'A4');
    $pdf->AddPage();
    $pdf->SetMargins(20, 20, 20);
    $pw = 170;

    $pdf->SetFillColor(0, 54, 49);
    $pdf->Rect(0, 0, 210, 38, 'F');

    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->SetXY(20, 10);
    $pdf->Cell($pw, 8, 'Evergreen Trust and Savings', 0, 1, 'L');
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetX(20);
    $pdf->Cell($pw, 6, 'Official Payment Receipt', 0, 1, 'L');

    $pdf->SetFont('Courier', 'B', 9);
    $pdf->SetXY(20, 10);
    $pdf->Cell($pw, 8, $data['txn_ref'], 0, 0, 'R');

    $pdf->SetFillColor(29, 181, 122);
    $pdf->Circle(105, 52, 8, 'F');

    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 13);
    $pdf->SetXY(97, 48);
    $pdf->Cell(16, 8, 'OK', 0, 0, 'C');

    $pdf->SetTextColor(0, 54, 49);
    $pdf->SetFont('Arial', 'B', 17);
    $pdf->SetXY(20, 63);
    $pdf->Cell($pw, 9, 'Payment Successful!', 0, 1, 'C');

    $pdf->SetTextColor(100, 100, 100);
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetX(20);
    $pdf->Cell($pw, 5, 'Your payment has been recorded on ' . $data['paid_at'], 0, 1, 'C');

    $pdf->SetFillColor(240, 250, 246);
    $pdf->SetDrawColor(196, 232, 218);
    $pdf->SetTextColor(0, 54, 49);
    $pdf->SetFont('Courier', 'B', 10);
    $pdf->SetXY(55, 80);
    $pdf->Cell(100, 8, $data['txn_ref'], 1, 1, 'C', true);

    $pdf->Ln(4);

    // ── Build rows — include penalty breakdown if applicable ──────────────────
    $rows = [
        ['Borrower',          $data['borrower_name']  ?? '—'],
        ['Account No.',       $data['account_number'] ?? '—'],
        ['Loan ID',           '#' . $data['loan_id']],
        ['Loan Type',         $data['loan_type']],
        ['Loan Amount Paid',  'PHP ' . number_format($data['amount_paid'], 2)],
    ];
    if (!empty($data['penalty_paid']) && $data['penalty_paid'] > 0) {
        $rows[] = ['Penalty Fee Paid',  'PHP ' . number_format($data['penalty_paid'], 2)];
        $rows[] = ['Total Paid (incl. penalty)', 'PHP ' . number_format($data['amount_paid'] + $data['penalty_paid'], 2)];
    }
    $rows = array_merge($rows, [
        ['Total Loan Paid to Date','PHP ' . number_format($data['total_paid'],  2)],
        ['Loan Principal',        'PHP ' . number_format($data['loan_amount'], 2)],
        ['Remaining Loan Balance','PHP ' . number_format($data['remaining'],   2)],
        ['Payment Method',        ucfirst($data['payment_method'])],
        ['Loan Status',           $data['is_fully_paid'] ? 'Closed / Paid in Full' : $data['new_status']],
    ]);

    $colL = 70; $colR = $pw - $colL;
    foreach ($rows as $i => $row) {
        $fill = ($i % 2 === 0);
        $pdf->SetFillColor($fill ? 248 : 255, $fill ? 253 : 255, $fill ? 252 : 255);
        $pdf->SetDrawColor(230, 244, 238);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->SetFont('Arial', '', 9);
        $pdf->SetX(20);
        $pdf->Cell($colL, 8, $row[0], 'B', 0, 'L', $fill);

        $pdf->SetFont('Arial', 'B', 9);
        if ($row[0] === 'Loan Amount Paid' || $row[0] === 'Penalty Fee Paid' || $row[0] === 'Total Paid (incl. penalty)')
            $pdf->SetTextColor(46, 125, 50);
        elseif ($row[0] === 'Remaining Loan Balance')
            $pdf->SetTextColor($data['remaining'] > 0 ? 192 : 46, $data['remaining'] > 0 ? 57 : 125, $data['remaining'] > 0 ? 43 : 50);
        elseif ($row[0] === 'Loan Status')
            $pdf->SetTextColor(0, 54, 49);
        else
            $pdf->SetTextColor(30, 30, 30);

        $pdf->Cell($colR, 8, $row[1], 'B', 1, 'R', $fill);
    }

    if ($data['is_fully_paid']) {
        $pdf->Ln(5);
        $bY = $pdf->GetY();
        $pdf->SetFillColor(10, 59, 47);
        $pdf->Rect(20, $bY, $pw, 28, 'F');

        $pdf->SetTextColor(232, 201, 107);
        $pdf->SetFont('Arial', 'B', 13);
        $pdf->SetXY(20, $bY + 3);
        $pdf->Cell($pw, 7, '** LOAN FULLY PAID! **', 0, 1, 'C');

        $pdf->SetTextColor(200, 230, 210);
        $pdf->SetFont('Arial', '', 9);
        $pdf->SetX(20);
        $pdf->Cell($pw, 5, 'This loan has been marked CLOSED in the system.', 0, 1, 'C');

        $chips   = [['New Status', 'Closed / Paid'], ['Total Settled', 'PHP ' . number_format($data['total_paid'], 2)]];
        $chipW   = ($pw - 6) / 2;
        $chipY   = $bY + 17;
        foreach ($chips as $ci => $chip) {
            $cx = 20 + $ci * ($chipW + 6);
            $pdf->SetXY($cx, $chipY);
            $pdf->SetFont('Arial', '', 7);
            $pdf->SetTextColor(180, 220, 200);
            $pdf->Cell($chipW, 4, strtoupper($chip[0]), 0, 1, 'C');
            $pdf->SetXY($cx, $chipY + 4);
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->Cell($chipW, 5, $chip[1], 0, 0, 'C');
        }
        $pdf->SetY($bY + 30);
    }

    $pdf->SetY(-30);
    $pdf->SetDrawColor(196, 232, 218);
    $pdf->SetLineWidth(0.4);
    $pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
    $pdf->Ln(2);
    $pdf->SetFont('Arial', 'I', 8);
    $pdf->SetTextColor(150, 150, 150);
    $pdf->SetX(20);
    $pdf->Cell($pw, 5, 'Evergreen Trust and Savings  |  This is a mock payment receipt — no real money was transferred.', 0, 1, 'C');
    $pdf->SetX(20);
    $pdf->Cell($pw, 4, 'Generated: ' . date('F d, Y H:i:s') . '  |  IP: ' . ($data['ip_address'] ?? '—'), 0, 0, 'C');

    $filename = 'Receipt-' . $data['txn_ref'] . '.pdf';
    $pdf->Output('D', $filename);
    exit;
}

// ─── HANDLE PDF DOWNLOAD REQUEST ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'downloadpdf') {
    $txnRef = $_GET['txn_ref'] ?? '';
    if (!empty($txnRef) && preg_match('/^TXN-[A-Z0-9]{12}$/', $txnRef)) {
        try {
            $pdoPdf = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $dbuser, $dbpass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $sPdf = $pdoPdf->prepare("
                SELECT lp.*, la.loan_amount, la.status AS loan_status,
                       COALESCE(lt.name, CONCAT('Loan #', lp.loan_application_id)) AS loan_type
                FROM   loan_payments lp
                JOIN   loan_applications la ON la.id = lp.loan_application_id
                LEFT JOIN loan_types lt     ON lt.id = la.loan_type_id
                WHERE  lp.transaction_ref = ? AND lp.user_email = ?
                LIMIT  1
            ");
            $sPdf->execute([$txnRef, $_SESSION['user_email']]);
            $rec = $sPdf->fetch(PDO::FETCH_ASSOC);

            if ($rec) {
                $sTot = $pdoPdf->prepare("
                    SELECT COALESCE(SUM(amount),0) AS total_paid
                    FROM   loan_payments
                    WHERE  loan_application_id = ? AND status = 'Completed'
                      AND  payment_date <= ?
                ");
                $sTot->execute([$rec['loan_application_id'], $rec['payment_date']]);
                $totalPaid  = floatval($sTot->fetch(PDO::FETCH_ASSOC)['total_paid']);
                $loanAmount = floatval($rec['loan_amount']);
                $remaining  = max(0, $loanAmount - $totalPaid);

                // Check if this transaction had a linked penalty payment
                $penPaid = 0;
                try {
                    $penPaidStmt = $pdoPdf->prepare("
                        SELECT COALESCE(SUM(penalty_paid), 0) AS penalty_paid
                        FROM   loan_penalty_payments
                        WHERE  linked_txn_ref = ? AND user_email = ?
                    ");
                    $penPaidStmt->execute([$txnRef, $_SESSION['user_email']]);
                    $penPaid = floatval($penPaidStmt->fetch(PDO::FETCH_ASSOC)['penalty_paid'] ?? 0);
                } catch (Exception $e) { /* table may not exist yet */ }

                generateReceiptPdf([
                    'txn_ref'        => $rec['transaction_ref'],
                    'loan_id'        => $rec['loan_application_id'],
                    'loan_type'      => $rec['loan_type'],
                    'borrower_name'  => $rec['borrower_name'],
                    'account_number' => $rec['account_number'],
                    'amount_paid'    => floatval($rec['amount']),
                    'penalty_paid'   => $penPaid,
                    'total_paid'     => $totalPaid,
                    'loan_amount'    => $loanAmount,
                    'remaining'      => $remaining,
                    'payment_method' => $rec['payment_method'],
                    'paid_at'        => date('Y-m-d H:i:s', strtotime($rec['payment_date'])),
                    'new_status'     => $rec['loan_status'],
                    'is_fully_paid'  => ($totalPaid >= $loanAmount),
                    'ip_address'     => $rec['ip_address'],
                ]);
            }
        } catch (PDOException $e) { /* fall through */ }
    }
    header('Location: Payment.php');
    exit;
}

// ══════════════════════════════════════════════════════════════════════════════
// ─── EMAIL NOTIFICATION ──────────────────────────────────────────────────────
// ══════════════════════════════════════════════════════════════════════════════
function sendPaymentEmail(array $data, array $mailCfg): void {
    $phpMailerBase = __DIR__ . '/PHPMailer-7.0.0/src/';
    if (!file_exists($phpMailerBase . 'PHPMailer.php')) {
        error_log('Payment email: PHPMailer not found at ' . $phpMailerBase);
        return;
    }

    require_once $phpMailerBase . 'PHPMailer.php';
    require_once $phpMailerBase . 'SMTP.php';
    require_once $phpMailerBase . 'Exception.php';

    $isFullyPaid = $data['is_fully_paid'];
    $penPaid     = $data['penalty_paid'] ?? 0;

    $subject = $isFullyPaid
        ? 'Congratulations! Your Loan Has Been Fully Paid - Evergreen Trust and Savings'
        : 'Payment Confirmation - Evergreen Trust and Savings';

    $accentColor = '#1db57a';
    $darkColor   = '#003631';
    $bannerBg    = $isFullyPaid ? '#0a3b2f' : $darkColor;
    $bannerTitle = $isFullyPaid ? '&#127881; Loan Fully Paid!' : 'Payment Successful!';
    $bannerSub   = $isFullyPaid
        ? 'Your loan has been marked <strong style="color:#e8c96b;">Closed</strong> in the system. Congratulations!'
        : 'Your monthly payment has been recorded successfully.';

    $rows = [
        ['Transaction Ref',    '<code style="background:#f0faf6;padding:2px 6px;border-radius:4px;">' . htmlspecialchars($data['txn_ref']) . '</code>'],
        ['Borrower',           htmlspecialchars($data['borrower_name']  ?? '—')],
        ['Account No.',        htmlspecialchars($data['account_number'] ?? '—')],
        ['Loan ID',            '#' . intval($data['loan_id'])],
        ['Loan Type',          htmlspecialchars($data['loan_type'])],
        ['Loan Amount Paid',   '<strong style="color:#2e7d32;">&#8369;' . number_format($data['amount_paid'], 2) . '</strong>'],
    ];
    if ($penPaid > 0) {
        $rows[] = ['Penalty Fee Paid', '<strong style="color:#c0392b;">&#8369;' . number_format($penPaid, 2) . '</strong>'];
        $rows[] = ['Total Paid This Txn', '<strong style="color:#1a6a2a;">&#8369;' . number_format($data['amount_paid'] + $penPaid, 2) . '</strong>'];
    }
    $rows = array_merge($rows, [
        ['Total Loan Paid to Date', '&#8369;' . number_format($data['total_paid'],  2)],
        ['Loan Principal',          '&#8369;' . number_format($data['loan_amount'], 2)],
        ['Remaining Loan Balance',  '<strong style="color:' . ($data['remaining'] > 0 ? '#c0392b' : '#2e7d32') . ';">&#8369;' . number_format($data['remaining'], 2) . '</strong>'],
        ['Payment Method',          ucfirst($data['payment_method'])],
        ['Date',                    $data['paid_at']],
        ['Loan Status',             $isFullyPaid ? '<strong style="color:#0a3b2f;">&#10003; Closed / Paid in Full</strong>' : htmlspecialchars($data['new_status'])],
    ]);

    $tableRows = '';
    foreach ($rows as $i => $row) {
        $bg = ($i % 2 === 0) ? '#f8fdfc' : '#ffffff';
        $tableRows .= "
        <tr style='background:{$bg};'>
          <td style='padding:9px 14px;color:#666;font-size:13px;border-bottom:1px solid #eef4ee;width:45%;'>{$row[0]}</td>
          <td style='padding:9px 14px;font-weight:600;color:#1a2e2a;font-size:13px;border-bottom:1px solid #eef4ee;text-align:right;'>{$row[1]}</td>
        </tr>";
    }

    $fullyPaidSection = '';
    if ($isFullyPaid) {
        $fullyPaidSection = "
        <div style='background:linear-gradient(135deg,#0a3b2f,#1a6b55);border-radius:10px;padding:20px;margin-top:20px;text-align:center;'>
          <div style='font-size:28px;margin-bottom:8px;'>&#11088;</div>
          <div style='font-family:Georgia,serif;font-size:18px;color:#fff;font-weight:bold;margin-bottom:4px;'>Congratulations!</div>
          <div style='font-size:13px;color:rgba(255,255,255,.8);margin-bottom:14px;'>Your loan has been completely settled. Thank you for your prompt payments!</div>
          <table style='width:100%;border-collapse:collapse;'>
            <tr>
              <td style='background:rgba(255,255,255,.1);border-radius:6px;padding:10px;text-align:center;width:50%;'>
                <div style='font-size:10px;color:rgba(255,255,255,.6);text-transform:uppercase;letter-spacing:1px;'>Final Status</div>
                <div style='font-size:14px;font-weight:bold;color:#e8c96b;'>Closed / Paid</div>
              </td>
              <td style='width:6px;'></td>
              <td style='background:rgba(255,255,255,.1);border-radius:6px;padding:10px;text-align:center;width:50%;'>
                <div style='font-size:10px;color:rgba(255,255,255,.6);text-transform:uppercase;letter-spacing:1px;'>Total Settled</div>
                <div style='font-size:14px;font-weight:bold;color:#fff;'>&#8369;" . number_format($data['total_paid'], 2) . "</div>
              </td>
            </tr>
          </table>
        </div>";
    }

    $htmlBody = "
<!DOCTYPE html>
<html>
<head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'></head>
<body style='margin:0;padding:0;background:#f4faf7;font-family:Arial,sans-serif;'>
  <table style='width:100%;background:#f4faf7;padding:30px 10px;' cellpadding='0' cellspacing='0'>
    <tr><td align='center'>
      <table style='max-width:520px;width:100%;background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 4px 24px rgba(0,54,49,.10);' cellpadding='0' cellspacing='0'>
        <tr><td style='background:linear-gradient(135deg,{$darkColor},#005a4d);padding:28px 30px;'>
          <div style='font-size:20px;font-weight:bold;color:#fff;'>Evergreen Trust and Savings</div>
          <div style='font-size:12px;color:#a8d5c8;margin-top:4px;'>Loan Payment Notification</div>
        </td></tr>
        <tr><td style='background:{$bannerBg};padding:22px 30px;text-align:center;'>
          <div style='width:52px;height:52px;background:{$accentColor};border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:22px;color:#fff;margin-bottom:10px;'>&#10003;</div>
          <div style='font-family:Georgia,serif;font-size:22px;color:#fff;font-weight:bold;'>{$bannerTitle}</div>
          <div style='font-size:13px;color:rgba(255,255,255,.8);margin-top:6px;'>{$bannerSub}</div>
          <div style='background:rgba(255,255,255,.12);border-radius:6px;padding:6px 16px;display:inline-block;margin-top:12px;font-family:Courier New,monospace;font-size:13px;color:#fff;font-weight:bold;letter-spacing:1px;'>" . htmlspecialchars($data['txn_ref']) . "</div>
        </td></tr>
        <tr><td style='padding:24px 24px 10px;'>
          <div style='font-size:13px;font-weight:700;color:{$darkColor};text-transform:uppercase;letter-spacing:.8px;margin-bottom:12px;'>Payment Summary</div>
          <table style='width:100%;border-collapse:collapse;border-radius:8px;overflow:hidden;border:1px solid #eef4ee;'>
            {$tableRows}
          </table>
          {$fullyPaidSection}
        </td></tr>
        <tr><td style='padding:20px 24px;border-top:1px solid #eef4ee;text-align:center;'>
          <p style='font-size:11px;color:#aaa;margin:0;'>This is an automated notification from <strong>Evergreen Trust and Savings</strong>.<br>
          This is a mock payment system — no real money was transferred.<br>
          Generated on " . date('F d, Y \a\t H:i:s') . "</p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>";

    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->SMTPDebug  = PHPMailer\PHPMailer\SMTP::DEBUG_OFF;
        $mail->isSMTP();
        $mail->Host       = $mailCfg['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $mailCfg['username'];
        $mail->Password   = $mailCfg['password'];
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $mailCfg['port'];
        $mail->SMTPOptions = ['ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true,
        ]];
        $mail->Timeout = 60;
        $mail->setFrom($mailCfg['from'], $mailCfg['from_name']);
        $mail->addAddress($data['user_email'], $data['borrower_name'] ?? '');
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body    = $htmlBody;
        $mail->AltBody = strip_tags(str_replace(['<br>', '</tr>'], ["\n", "\n"], $htmlBody));
        $mail->send();
    } catch (\Exception $e) {
        error_log('Payment email error: ' . $e->getMessage());
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// ─── HANDLE PAYMENT POST ─────────────────────────────────────────────────────
// ══════════════════════════════════════════════════════════════════════════════
$paymentResult = null;
$paymentError  = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'pay') {
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $dbuser, $dbpass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        $loan_id           = intval($_POST['loan_id']         ?? 0);
        $payment_method    = $_POST['payment_method']         ?? '';
        $loan_amount_pay   = floatval($_POST['amount']        ?? 0);  // principal portion
        $penalty_amount_pay= floatval($_POST['penalty_amount'] ?? 0); // penalty portion (may be 0)
        $user_email        = $_SESSION['user_email'];

        $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
        $ip_address = $ip_address ? substr($ip_address, 0, 45) : null;
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : null;

        if (!in_array($payment_method, ['online', 'cheque'])) {
            $paymentError = "Invalid payment method.";
        } elseif ($loan_id <= 0 || $loan_amount_pay <= 0) {
            $paymentError = "Invalid loan or amount.";
        } else {
            $stmt = $pdo->prepare("
                SELECT la.*,
                       COALESCE(lt.name, CONCAT('Loan #', la.id)) AS loan_type_label,
                       lt.code AS loan_type_code,
                       u.full_name      AS user_full_name,
                       u.account_number AS user_account_number
                FROM   loan_applications la
                LEFT JOIN loan_types lt ON lt.id = la.loan_type_id
                LEFT JOIN users u       ON u.user_email = la.user_email
                WHERE  la.id = ? AND la.user_email = ?
                LIMIT  1
            ");
            $stmt->execute([$loan_id, $user_email]);
            $loan = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$loan) {
                $paymentError = "Loan not found or does not belong to your account.";
            } elseif (!in_array($loan['status'], ['Active', 'Approved'])) {
                $paymentError = "This loan is not eligible for payment (Status: "
                              . htmlspecialchars($loan['status']) . ").";
            } else {
                $borrowerName  = $loan['user_full_name'] ?? null;
                $accountNumber = $loan['user_account_number'] ?? null;

                if (!$borrowerName) {
                    $lb = $pdo->prepare("SELECT full_name, account_number FROM loan_borrowers WHERE loan_application_id = ? LIMIT 1");
                    $lb->execute([$loan_id]);
                    $lbRow = $lb->fetch(PDO::FETCH_ASSOC);
                    $borrowerName  = $lbRow['full_name']      ?? ($_SESSION['user_name'] ?? $user_email);
                    $accountNumber = $accountNumber ?? ($lbRow['account_number'] ?? null);
                }

                // ── Generate a single shared transaction ref for this payment ──
                $txn_ref = 'TXN-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 12));

                // ── 1. Insert loan principal payment → loan_payments ──────────
                $ins = $pdo->prepare("
                    INSERT INTO loan_payments
                        (loan_application_id, user_email, borrower_name, account_number,
                         amount, payment_method, transaction_ref, payment_date,
                         status, processed_by, processed_by_id, ip_address, user_agent)
                    VALUES
                        (?, ?, ?, ?,
                         ?, ?, ?, NOW(6),
                         'Completed', ?, NULL, ?, ?)
                ");
                $ins->execute([
                    $loan_id, $user_email, $borrowerName, $accountNumber,
                    $loan_amount_pay, $payment_method, $txn_ref,
                    $borrowerName, $ip_address, $user_agent,
                ]);

                // ── 2. If penalty amount > 0, update loan_penalties ───────────
                $actualPenaltyPaid = 0;
                if ($penalty_amount_pay > 0) {
                    // Fetch the active penalty record for this loan
                    $penRow = $pdo->prepare("
                        SELECT id, penalty_amount, total_balance_with_penalty
                        FROM   loan_penalties
                        WHERE  loan_application_id = ?
                          AND  user_email = ?
                          AND  status = 'Active'
                        LIMIT 1
                    ");
                    $penRow->execute([$loan_id, $user_email]);
                    $activePen = $penRow->fetch(PDO::FETCH_ASSOC);

                    if ($activePen) {
                        // Clamp penalty payment to the actual penalty amount owed
                        $actualPenaltyPaid = min($penalty_amount_pay, floatval($activePen['penalty_amount']));

                        // Ensure loan_penalty_payments table exists
                        $pdo->exec("
                            CREATE TABLE IF NOT EXISTS loan_penalty_payments (
                                id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
                                loan_penalties_id   INT          NOT NULL,
                                loan_application_id INT          NOT NULL,
                                user_email          VARCHAR(255) NOT NULL,
                                borrower_name       VARCHAR(150) DEFAULT NULL,
                                penalty_paid        DECIMAL(15,2) NOT NULL,
                                payment_method      ENUM('online','cheque') NOT NULL,
                                linked_txn_ref      VARCHAR(70)  NOT NULL,
                                payment_date        DATETIME(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                                created_at          TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                                ip_address          VARCHAR(45)  DEFAULT NULL,
                                PRIMARY KEY (id),
                                INDEX idx_pen_app   (loan_application_id),
                                INDEX idx_pen_email (user_email),
                                INDEX idx_pen_txn   (linked_txn_ref)
                            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                        ");

                        // Insert penalty payment record
                        $pdo->prepare("
                            INSERT INTO loan_penalty_payments
                                (loan_penalties_id, loan_application_id, user_email, borrower_name,
                                 penalty_paid, payment_method, linked_txn_ref, ip_address)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                        ")->execute([
                            $activePen['id'],
                            $loan_id,
                            $user_email,
                            $borrowerName,
                            $actualPenaltyPaid,
                            $payment_method,
                            $txn_ref,
                            $ip_address,
                        ]);

                        // Settle or reduce the penalty in loan_penalties
                        $remaining_penalty = round(floatval($activePen['penalty_amount']) - $actualPenaltyPaid, 2);
                        if ($remaining_penalty <= 0) {
                            // Penalty fully paid → mark Settled
                            $pdo->prepare("
                                UPDATE loan_penalties
                                SET    status = 'Settled',
                                       penalty_amount = 0,
                                       total_balance_with_penalty = total_balance_with_penalty - ?,
                                       updated_at = NOW()
                                WHERE  id = ?
                            ")->execute([$actualPenaltyPaid, $activePen['id']]);
                        } else {
                            // Partial penalty payment → reduce amounts
                            $pdo->prepare("
                                UPDATE loan_penalties
                                SET    penalty_amount = ?,
                                       total_balance_with_penalty = total_balance_with_penalty - ?,
                                       updated_at = NOW()
                                WHERE  id = ?
                            ")->execute([$remaining_penalty, $actualPenaltyPaid, $activePen['id']]);
                        }
                    }
                }

                // ── 3. Recalculate totals ─────────────────────────────────────
                $s = $pdo->prepare("
                    SELECT COALESCE(SUM(amount), 0) AS total_paid
                    FROM   loan_payments
                    WHERE  loan_application_id = ? AND status = 'Completed'
                ");
                $s->execute([$loan_id]);
                $totalPaid  = floatval($s->fetch(PDO::FETCH_ASSOC)['total_paid']);
                $loanAmount = floatval($loan['loan_amount']);
                $newStatus  = $loan['status'];

                if ($totalPaid >= $loanAmount) {
                    $newStatus = 'Closed';
                    $pdo->prepare("
                        UPDATE loan_applications
                        SET    status = 'Closed',
                               next_payment_due = NULL
                        WHERE  id = ?
                    ")->execute([$loan_id]);

                    // Also settle any remaining penalty when loan is fully closed
                    $pdo->prepare("
                        UPDATE loan_penalties
                        SET    status = 'Settled', updated_at = NOW()
                        WHERE  loan_application_id = ? AND status = 'Active'
                    ")->execute([$loan_id]);
                } else {
                    $pdo->prepare("
                        UPDATE loan_applications
                        SET    next_payment_due =
                               DATE_ADD(COALESCE(next_payment_due, NOW()), INTERVAL 1 MONTH)
                        WHERE  id = ?
                    ")->execute([$loan_id]);
                }

                $loanTypeLabel = $loan['loan_type_label'] ?? ('Loan #' . $loan_id);
                $remaining     = max(0, $loanAmount - $totalPaid);

                $paymentResult = [
                    'success'        => true,
                    'txn_ref'        => $txn_ref,
                    'loan_id'        => $loan_id,
                    'loan_type'      => $loanTypeLabel,
                    'amount_paid'    => $loan_amount_pay,
                    'penalty_paid'   => $actualPenaltyPaid,
                    'total_paid'     => $totalPaid,
                    'loan_amount'    => $loanAmount,
                    'remaining'      => $remaining,
                    'payment_method' => $payment_method,
                    'paid_at'        => date('Y-m-d H:i:s'),
                    'new_status'     => $newStatus,
                    'is_fully_paid'  => ($totalPaid >= $loanAmount),
                    'borrower_name'  => $borrowerName,
                    'account_number' => $accountNumber,
                    'ip_address'     => $ip_address,
                    'user_email'     => $user_email,
                ];

                sendPaymentEmail($paymentResult, [
                    'host'      => $MAIL_HOST,
                    'port'      => $MAIL_PORT,
                    'username'  => $MAIL_USERNAME,
                    'password'  => $MAIL_PASSWORD,
                    'from'      => $MAIL_FROM,
                    'from_name' => $MAIL_FROM_NAME,
                ]);
            }
        }
    } catch (PDOException $e) {
        $paymentError = "Database error: " . $e->getMessage();
    }
}

// ─── FETCH USER INFO, ACTIVE LOANS, PAYMENT HISTORY & PENALTIES ──────────────
$loans      = [];
$userInfo   = [];
$payHistory = [];
$penaltyMap = [];  // active penalties keyed by loan_id

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $dbuser, $dbpass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    $uStmt = $pdo->prepare("
        SELECT full_name, user_email, account_number, contact_number
        FROM   users WHERE user_email = ? LIMIT 1
    ");
    $uStmt->execute([$_SESSION['user_email']]);
    $userInfo = $uStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $lStmt = $pdo->prepare("
        SELECT la.*,
               COALESCE(lt.name, CONCAT('Loan #', la.id)) AS loan_type,
               lt.code         AS loan_type_code,
               lt.interest_rate,
               lt.max_amount,
               lt.max_term_months
        FROM   loan_applications la
        LEFT JOIN loan_types lt ON lt.id = la.loan_type_id
        WHERE  la.user_email = ?
          AND  la.status IN ('Active', 'Approved')
        ORDER  BY la.created_at DESC
    ");
    $lStmt->execute([$_SESSION['user_email']]);
    $loans = $lStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($loans as &$loan) {
        $s = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) AS total_paid
            FROM   loan_payments
            WHERE  loan_application_id = ? AND status = 'Completed'
        ");
        $s->execute([$loan['id']]);
        $row = $s->fetch(PDO::FETCH_ASSOC);
        $loan['total_paid'] = floatval($row['total_paid'] ?? 0);
        $loan['remaining']  = max(0, floatval($loan['loan_amount']) - $loan['total_paid']);
    }
    unset($loan);

    // ── Fetch active penalties for this user ─────────────────────────────────
    try {
        $penStmt = $pdo->prepare("
            SELECT loan_application_id AS loan_id,
                   penalty_amount,
                   total_balance_with_penalty,
                   months_overdue,
                   penalty_rate,
                   original_balance
            FROM   loan_penalties
            WHERE  user_email = ? AND status = 'Active'
        ");
        $penStmt->execute([$_SESSION['user_email']]);
        foreach ($penStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $penaltyMap[(int)$row['loan_id']] = [
                'penalty_amount'             => round((float)$row['penalty_amount'], 2),
                'total_balance_with_penalty' => round((float)$row['total_balance_with_penalty'], 2),
                'months_overdue'             => (int)$row['months_overdue'],
                'penalty_rate'               => (float)$row['penalty_rate'],
                'original_balance'           => round((float)$row['original_balance'], 2),
            ];
        }
    } catch (PDOException $e) { /* table may not exist yet */ }

    $hStmt = $pdo->prepare("
        SELECT lp.*,
               la.status       AS loan_status,
               la.loan_amount  AS loan_total_amount,
               COALESCE(lt.name, CONCAT('Loan #', lp.loan_application_id)) AS loan_type,
               lt.code         AS loan_type_code
        FROM   loan_payments lp
        JOIN   loan_applications la ON lp.loan_application_id = la.id
        LEFT JOIN loan_types lt     ON lt.id = la.loan_type_id
        WHERE  lp.user_email = ?
        ORDER  BY lp.payment_date DESC
        LIMIT  20
    ");
    $hStmt->execute([$_SESSION['user_email']]);
    $payHistory = $hStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $paymentError = ($paymentError ?? '') . " Data error: " . $e->getMessage();
}

$root = '../../';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Make a Payment — Evergreen Trust and Savings</title>
  <link rel="icon" type="image/png" href="<?= $root ?>pictures/logo.png"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700;800&family=DM+Serif+Display:ital@0;1&display=swap" rel="stylesheet">
  <style>
    :root {
      --eg-dark:   #003631;
      --eg-mid:    #005a4d;
      --eg-light:  #00796b;
      --eg-accent: #1db57a;
      --eg-pale:   #f0faf6;
      --eg-border: #c4e8da;
      --radius:    1rem;
      --font:      'Sora', sans-serif;
      --serif:     'DM Serif Display', serif;
    }
    *, *::before, *::after { box-sizing: border-box; }
    body { font-family: var(--font); background: #f4faf7; color: #1a2e2a; min-height: 100vh; }

    .pay-header {
      background: linear-gradient(135deg, var(--eg-dark) 0%, var(--eg-mid) 60%, var(--eg-light) 100%);
      padding: 2rem 0 3.5rem; position: relative; overflow: hidden;
    }
    .pay-header::before {
      content: ''; position: absolute; inset: 0;
      background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23ffffff' fill-opacity='0.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/svg%3E");
    }
    .pay-header .breadcrumb { font-size: .8rem; opacity: .7; }
    .pay-header .breadcrumb-item a { color: #a8d5c8; text-decoration: none; }
    .pay-header .breadcrumb-item.active { color: #fff; }
    .pay-header h1 { font-family: var(--serif); font-size: clamp(1.6rem,4vw,2.4rem); color: #fff; margin: 0; }
    .pay-header p  { color: #a8d5c8; font-size: .9rem; margin: .4rem 0 0; }

    .card-clean { background: #fff; border: 1px solid var(--eg-border); border-radius: var(--radius); box-shadow: 0 2px 12px rgba(0,54,49,.08); overflow: hidden; }
    .card-header-green { background: linear-gradient(90deg, var(--eg-dark), var(--eg-mid)); color: #fff; padding: 1.1rem 1.5rem; font-weight: 700; font-size: .95rem; display: flex; align-items: center; gap: .6rem; }
    .card-header-red   { background: linear-gradient(90deg, #8b1a1a, #c0392b); color: #fff; padding: 1.1rem 1.5rem; font-weight: 700; font-size: .95rem; display: flex; align-items: center; gap: .6rem; }
    .card-body-pad { padding: 1.5rem; }

    .loan-select-item { border: 2px solid var(--eg-border); border-radius: .75rem; padding: 1rem 1.2rem; cursor: pointer; transition: all .2s; background: #fff; }
    .loan-select-item:hover { border-color: var(--eg-light); background: var(--eg-pale); }
    .loan-select-item.selected { border-color: var(--eg-dark); background: var(--eg-pale); box-shadow: 0 0 0 3px rgba(0,54,49,.12); }
    .check-ring { width: 22px; height: 22px; border-radius: 50%; border: 2px solid var(--eg-border); display: flex; align-items: center; justify-content: center; transition: all .2s; flex-shrink: 0; }
    .loan-select-item.selected .check-ring { background: var(--eg-dark); border-color: var(--eg-dark); }
    .check-ring i { color: #fff; font-size: .7rem; display: none; }
    .loan-select-item.selected .check-ring i { display: block; }
    .loan-badge { font-size: .72rem; font-weight: 700; padding: .2rem .65rem; border-radius: 1rem; text-transform: uppercase; letter-spacing: .5px; }
    .loan-badge.active   { background: #d4edda; color: #1a6a2a; }
    .loan-badge.approved { background: #cce5ff; color: #0050a0; }
    .loan-badge.overdue  { background: #ffebee; color: #b71c1c; border: 1px solid #ef9a9a; }

    .progress-clean { height: 8px; border-radius: 4px; background: #e9ecef; overflow: hidden; }
    .progress-clean .bar { height: 100%; border-radius: 4px; background: linear-gradient(90deg, var(--eg-accent), var(--eg-light)); transition: width .6s; }

    .method-tabs { display: flex; gap: .75rem; flex-wrap: wrap; }
    .method-tab { flex: 1; min-width: 140px; border: 2px solid var(--eg-border); border-radius: .75rem; padding: 1rem; cursor: pointer; text-align: center; transition: all .2s; background: #fff; position: relative; }
    .method-tab:hover { border-color: var(--eg-light); background: var(--eg-pale); }
    .method-tab.active { border-color: var(--eg-dark); background: var(--eg-pale); }
    .method-icon { width: 48px; height: 48px; border-radius: .6rem; display: flex; align-items: center; justify-content: center; margin: 0 auto .6rem; font-size: 1.4rem; transition: all .2s; }
    .method-tab[data-method="online"] .method-icon { background: #e8f4ff; color: #1a6fce; }
    .method-tab[data-method="cheque"] .method-icon { background: #fff5e6; color: #c47a00; }
    .method-tab.active[data-method="online"] .method-icon { background: #1a6fce; color: #fff; }
    .method-tab.active[data-method="cheque"] .method-icon { background: #c47a00; color: #fff; }
    .method-tab h4 { font-size: .88rem; font-weight: 700; margin: 0 0 .2rem; color: var(--eg-dark); }
    .method-tab p  { font-size: .78rem; color: #777; margin: 0; }
    .sel-badge { position: absolute; top: -8px; right: -8px; background: var(--eg-dark); color: #fff; border-radius: 50%; width: 20px; height: 20px; display: none; align-items: center; justify-content: center; font-size: .65rem; }
    .method-tab.active .sel-badge { display: flex; }

    .form-label-eg { font-size: .82rem; font-weight: 600; color: var(--eg-dark); margin-bottom: .35rem; display: block; }
    .form-control-eg { border: 1.5px solid var(--eg-border); border-radius: .55rem; padding: .65rem .9rem; font-size: .92rem; font-family: var(--font); transition: border-color .2s, box-shadow .2s; width: 100%; background: #fff; }
    .form-control-eg:focus { outline: none; border-color: var(--eg-light); box-shadow: 0 0 0 3px rgba(0,121,107,.12); }
    .form-control-eg[readonly] { background: #f8faf9; color: #555; }
    .card-wrap { position: relative; }
    .card-wrap .c-icon { position: absolute; right: .9rem; top: 50%; transform: translateY(-50%); font-size: 1.3rem; color: #aaa; pointer-events: none; }
    .card-wrap input { padding-right: 2.8rem; }

    .cheque-preview { background: #fefdf5; border: 1.5px dashed #c8b800; border-radius: .75rem; padding: 1.2rem 1.5rem; position: relative; overflow: hidden; font-family: 'Courier New', monospace; }
    .cheque-preview::before { content: 'MOCK CHEQUE'; position: absolute; top: 50%; right: -20px; transform: translateY(-50%) rotate(-30deg); font-size: 2.5rem; font-weight: 900; color: rgba(200,184,0,.12); pointer-events: none; white-space: nowrap; font-family: var(--font); }
    .cheque-line { border-bottom: 1px solid #ddd; margin-bottom: .6rem; padding-bottom: .4rem; font-size: .85rem; color: #333; }
    .cheque-line span { font-weight: 700; color: #222; }

    .amount-display { background: linear-gradient(135deg, var(--eg-pale), #e0f7ef); border: 1px solid var(--eg-border); border-radius: .75rem; padding: 1rem 1.25rem; text-align: center; }
    .amount-display .lbl { font-size: .75rem; color: #6c757d; text-transform: uppercase; letter-spacing: .8px; margin-bottom: .2rem; }
    .amount-display .val { font-size: 1.75rem; font-weight: 800; color: var(--eg-dark); font-family: var(--serif); }

    /* ── Penalty breakdown in summary ── */
    .penalty-summary-box {
      background: linear-gradient(135deg,#fff5f4,#ffeeec);
      border: 1.5px solid rgba(192,57,43,0.3);
      border-radius: .65rem;
      padding: .85rem 1rem;
      margin-bottom: 1rem;
    }
    .penalty-summary-box .pen-title {
      font-size: .78rem; font-weight: 700; color: #b71c1c;
      text-transform: uppercase; letter-spacing: .6px;
      margin-bottom: .5rem; display: flex; align-items: center; gap: .4rem;
    }
    .pen-row { display: flex; justify-content: space-between; font-size: .82rem; padding: .2rem 0; }
    .pen-row .pl { color: #666; }
    .pen-row .pv { font-weight: 700; color: #c0392b; }
    .pen-row .pv.green { color: #2e7d32; }
    .pen-divider { height: 1px; background: rgba(192,57,43,0.2); margin: .4rem 0; }
    .pen-total-row { display: flex; justify-content: space-between; font-size: .9rem; font-weight: 800; padding: .3rem 0; }
    .pen-total-row .ptl { color: #8b1a1a; }
    .pen-total-row .ptv { color: #c0392b; font-size: 1rem; }

    /* ── Penalty amount input section ── */
    .penalty-input-section {
      background: #fff5f4;
      border: 1.5px solid rgba(192,57,43,0.25);
      border-radius: .65rem;
      padding: 1rem;
      margin-bottom: 1rem;
    }
    .penalty-input-section .pen-sec-title {
      font-size: .82rem; font-weight: 700; color: #c0392b;
      margin-bottom: .6rem; display: flex; align-items: center; gap: .4rem;
    }
    .penalty-checkbox-wrap {
      display: flex; align-items: flex-start; gap: .6rem; margin-bottom: .6rem;
    }
    .penalty-checkbox-wrap input[type="checkbox"] {
      margin-top: 3px; width: 16px; height: 16px; flex-shrink: 0; cursor: pointer; accent-color: #c0392b;
    }
    .penalty-checkbox-wrap label { font-size: .85rem; color: #333; cursor: pointer; line-height: 1.4; }

    .summary-row { display: flex; justify-content: space-between; align-items: center; padding: .45rem 0; font-size: .9rem; }
    .summary-row:not(:last-child) { border-bottom: 1px solid #eee; }
    .sl { color: #666; } .sv { font-weight: 600; color: var(--eg-dark); }
    .sv.danger { color: #c0392b; }

    .total-pay-display {
      background: linear-gradient(135deg, #fff3e0, #fbe9e7);
      border: 1.5px solid rgba(230,126,34,0.3);
      border-radius: .65rem;
      padding: .8rem 1rem;
      margin-bottom: 1rem;
      display: flex; justify-content: space-between; align-items: center;
    }
    .total-pay-display .tpl { font-size: .82rem; color: #7d5000; font-weight: 600; }
    .total-pay-display .tpv { font-size: 1.3rem; font-weight: 800; color: #8b3000; font-family: var(--serif); }

    .btn-pay { background: linear-gradient(135deg, var(--eg-dark), var(--eg-light)); color: #fff; border: none; padding: .85rem 2rem; border-radius: .65rem; font-weight: 700; font-size: 1rem; font-family: var(--font); width: 100%; cursor: pointer; transition: all .25s; }
    .btn-pay:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,54,49,.3); }
    .btn-pay:disabled { opacity: .6; cursor: not-allowed; transform: none; }

    .info-box { background: #fff8e6; border-left: 4px solid #f0ad00; border-radius: .5rem; padding: .9rem 1rem; font-size: .85rem; color: #6b4e00; }
    .danger-box { background: #fff5f4; border-left: 4px solid #c0392b; border-radius: .5rem; padding: .9rem 1rem; font-size: .85rem; color: #7d1a1a; }

    /* ── Success Overlay ── */
    .success-overlay {
      display: none; position: fixed; inset: 0; z-index: 9999;
      background: rgba(0,0,0,.6);
      align-items: center; justify-content: center;
      padding: 10px; overflow-y: auto;
    }
    .success-overlay.show { display: flex; animation: fadeIn .3s; }
    .success-card {
      background: #fff; border-radius: 1.1rem;
      padding: 1.1rem 1.4rem .9rem;
      max-width: 440px; width: 100%; margin: auto;
      text-align: center;
      animation: popIn .4s cubic-bezier(.34,1.56,.64,1);
      box-shadow: 0 12px 40px rgba(0,54,49,.2);
      max-height: calc(100vh - 20px);
      overflow-y: auto;
      scrollbar-width: thin;
      scrollbar-color: var(--eg-border) transparent;
    }
    .success-card::-webkit-scrollbar { width: 4px; }
    .success-card::-webkit-scrollbar-thumb { background: var(--eg-border); border-radius: 4px; }
    .success-icon {
      width: 52px; height: 52px;
      background: linear-gradient(135deg, var(--eg-accent), var(--eg-light));
      border-radius: 50%; display: flex; align-items: center;
      justify-content: center; margin: 0 auto .6rem;
      font-size: 1.4rem; color: #fff;
    }
    .success-card h2 { font-family: var(--serif); font-size: 1.35rem; color: var(--eg-dark); margin: 0 0 .2rem; }
    .success-card > p { font-size: .8rem; color: #888; margin-bottom: .4rem; }
    .txn-ref {
      background: var(--eg-pale); border: 1px solid var(--eg-border);
      border-radius: .4rem; padding: .3rem .8rem;
      font-family: 'Courier New', monospace; font-weight: 700;
      color: var(--eg-dark); font-size: .82rem; letter-spacing: 1px;
      display: inline-block; margin-bottom: .55rem;
    }
    .success-card .summary-row { padding: .25rem 0; font-size: .82rem; }

    .fully-paid-banner {
      background: linear-gradient(135deg, #0a3b2f, #1a6b55);
      border-radius: .7rem; padding: .65rem 1rem;
      margin-top: .5rem; text-align: center;
    }
    .fully-paid-banner .fp-icon {
      width: 36px; height: 36px; background: rgba(255,255,255,.15);
      border-radius: 50%; display: flex; align-items: center;
      justify-content: center; margin: 0 auto .35rem;
      font-size: 1rem; color: #e8c96b;
    }
    .fully-paid-banner .fp-title { font-family: var(--serif); font-size: .95rem; color: #fff; margin-bottom: .1rem; }
    .fully-paid-banner .fp-sub   { font-size: .72rem; color: rgba(255,255,255,.7); }
    .fp-details-grid { display: grid; grid-template-columns: 1fr 1fr; gap: .3rem; margin-top: .45rem; }
    .fp-detail-chip { background: rgba(255,255,255,.1); border-radius: .35rem; padding: .3rem .55rem; text-align: left; }
    .fp-detail-chip .fp-chip-label { font-size: .6rem; color: rgba(255,255,255,.55); text-transform: uppercase; letter-spacing: .5px; }
    .fp-detail-chip .fp-chip-val   { font-size: .78rem; font-weight: 700; color: #fff; }

    .btn-pdf {
      background: linear-gradient(135deg, #c0392b, #e74c3c);
      color: #fff; border: none; padding: .5rem 1.25rem;
      border-radius: .65rem; font-weight: 700; font-size: .88rem;
      font-family: var(--font); cursor: pointer; transition: all .25s;
      text-decoration: none; display: inline-flex; align-items: center; gap: .4rem;
      width: auto;
    }
    .btn-pdf:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(192,57,43,.35); color: #fff; }

    .email-sent-badge {
      display: inline-flex; align-items: center; gap: .35rem;
      background: #e8f7ef; border: 1px solid #a8d5c8;
      color: #1a6a3a; font-size: .75rem; font-weight: 600;
      padding: .3rem .8rem; border-radius: 1rem; margin-top: .4rem;
    }

    /* ── Penalty paid chip in success ── */
    .penalty-paid-chip {
      display: inline-flex; align-items: center; gap: .35rem;
      background: #ffebee; border: 1px solid #ef9a9a;
      color: #c0392b; font-size: .75rem; font-weight: 700;
      padding: .3rem .8rem; border-radius: 1rem; margin-top: .35rem;
    }

    /* ── History table ── */
    .history-table th { background: var(--eg-dark); color: #fff; font-size: .78rem; text-transform: uppercase; letter-spacing: .6px; padding: .75rem 1rem; border: none; }
    .history-table td { padding: .7rem 1rem; font-size: .87rem; vertical-align: middle; border-color: #e8f4ee; }
    .history-table tbody tr:hover td { background: var(--eg-pale); }
    .history-table tbody tr.row-closed td { background: #f0faf6; }
    .history-table tbody tr.row-closed:hover td { background: #e4f5ed; }

    .mpill { display: inline-flex; align-items: center; gap: .35rem; padding: .2rem .7rem; border-radius: 1rem; font-size: .77rem; font-weight: 600; }
    .mpill.online { background: #e8f4ff; color: #1a6fce; }
    .mpill.cheque { background: #fff5e6; color: #c47a00; }
    .spill { display: inline-block; padding: .2rem .7rem; border-radius: 1rem; font-size: .77rem; font-weight: 700; background: #d4edda; color: #1a6a2a; }
    .loan-status-pill { display: inline-block; padding: .2rem .65rem; border-radius: 1rem; font-size: .72rem; font-weight: 700; }
    .loan-status-pill.closed   { background: linear-gradient(90deg,#0a3b2f,#1a6b55); color: #e8c96b; }
    .loan-status-pill.active   { background: #d4edda; color: #1a6a2a; }
    .loan-status-pill.approved { background: #cce5ff; color: #0050a0; }
    .paid-full-chip { display: inline-flex; align-items: center; gap: 3px; background: linear-gradient(90deg,#0a3b2f,#1a6b55); color: #e8c96b; font-size: .65rem; font-weight: 700; padding: 1px 7px; border-radius: 6px; letter-spacing: .4px; text-transform: uppercase; margin-top: 3px; }

    .btn-dl-pdf { display: inline-flex; align-items: center; gap: 4px; background: #fdecea; color: #c0392b; border: 1px solid #f5c6cb; border-radius: .4rem; padding: 2px 8px; font-size: .72rem; font-weight: 700; text-decoration: none; transition: background .15s; white-space: nowrap; }
    .btn-dl-pdf:hover { background: #f5c6cb; color: #922b21; }

    .empty-state { text-align: center; padding: 4rem 1rem; }
    .empty-state i { font-size: 4rem; color: #c4e8da; margin-bottom: 1rem; display: block; }
    .empty-state h3 { color: var(--eg-dark); font-family: var(--serif); }
    .empty-state .btn-empty { display: inline-block; margin-top: 1rem; background: var(--eg-dark); color: #fff; padding: .7rem 2rem; border-radius: .65rem; text-decoration: none; font-weight: 700; transition: background .2s; }
    .empty-state .btn-empty:hover { background: var(--eg-mid); }

    @keyframes fadeIn { from{opacity:0} to{opacity:1} }
    @keyframes popIn  { from{transform:scale(.8);opacity:0} to{transform:scale(1);opacity:1} }
    @media (max-width: 576px) { .method-tab { min-width: 100%; } .amount-display .val { font-size: 1.4rem; } .fp-details-grid { grid-template-columns: 1fr; } }
  </style>
</head>
<body>

<?php
$headerPath = __DIR__ . '/../../header.php';
if (file_exists($headerPath)) {
    include $headerPath;
} else {
    echo '
    <nav class="navbar navbar-dark py-3" style="background:#003631;">
      <div class="container d-flex align-items-center justify-content-between">
        <a class="navbar-brand fw-bold text-white d-flex align-items-center gap-2" href="' . $root . 'index.php">
          <img src="' . $root . 'pictures/logo.png" height="36" alt="Logo" onerror="this.style.display=\'none\'">
          Evergreen Trust and Savings
        </a>
        <a href="' . htmlspecialchars($_LOAN_HOME) . '#home" class="text-white text-decoration-none" style="font-size:.9rem;">
          <i class="fas fa-home me-1"></i> Home
        </a>
      </div>
    </nav>';
}
?>

<!-- PAGE HEADER -->
<div class="pay-header">
  <div class="container">
    <nav aria-label="breadcrumb" class="mb-2">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= htmlspecialchars($_LOAN_HOME) ?>#home">Home</a></li>
        <li class="breadcrumb-item active">Make a Payment</li>
      </ol>
    </nav>
    <h1 class="mt-1"><i class="fas fa-credit-card me-2" style="font-size:.85em;"></i>Loan Payment</h1>
    <p>
      <i class="fas fa-user-circle me-1"></i>
      <?= htmlspecialchars($userInfo['full_name'] ?? ($_SESSION['user_name'] ?? $_SESSION['user_email'])) ?>
      <?php if (!empty($userInfo['account_number'])): ?>
        &nbsp;&middot;&nbsp; Acct: <strong><?= htmlspecialchars($userInfo['account_number']) ?></strong>
      <?php endif; ?>
    </p>
  </div>
</div>

<!-- MAIN CONTENT -->
<div class="container py-4" style="margin-top:-1.5rem; position:relative; z-index:1;">

  <?php if ($paymentError): ?>
  <div class="alert alert-danger d-flex align-items-center gap-2 mb-4" style="border-radius:.75rem;">
    <i class="fas fa-exclamation-circle fa-lg flex-shrink-0"></i>
    <div><?= htmlspecialchars($paymentError) ?></div>
  </div>
  <?php endif; ?>

  <?php if (empty($loans)): ?>
  <div class="card-clean mb-4">
    <div class="card-body-pad">
      <div class="empty-state">
        <i class="fas fa-file-invoice-dollar"></i>
        <h3>No Payable Loans</h3>
        <p style="color:#888;font-size:.9rem;">
          No active or approved loans found for<br>
          <code><?= htmlspecialchars($_SESSION['user_email']) ?></code>
        </p>
        <a href="<?= htmlspecialchars($_LOAN_HOME) ?>#loan-services" class="btn-empty">Apply for a Loan</a>
        <a href="<?= htmlspecialchars($_DASHBOARD) ?>" class="btn-empty ms-2" style="background:#555;">View Dashboard</a>
      </div>
    </div>
  </div>

  <?php else: ?>

  <div class="row g-4">

    <!-- LEFT COL: Steps -->
    <div class="col-lg-7">

      <!-- STEP 1: Select Loan -->
      <div class="card-clean mb-4">
        <div class="card-header-green"><i class="fas fa-list-ul"></i> Step 1 — Select Loan to Pay</div>
        <div class="card-body-pad">
          <div class="d-flex flex-column gap-3">
            <?php foreach ($loans as $i => $loan):
              $monthly   = floatval($loan['monthly_payment'] ?? 0);
              $loanAmt   = floatval($loan['loan_amount']);
              $totalPaid = $loan['total_paid'];
              $remaining = $loan['remaining'];
              $pct       = $loanAmt > 0 ? min(100, ($totalPaid / $loanAmt) * 100) : 0;
              $loanLabel = $loan['loan_type'] ?? ('Loan #' . $loan['id']);
              $loanCode  = $loan['loan_type_code'] ?? '';
              $loanId    = (int)$loan['id'];
              $hasPen    = isset($penaltyMap[$loanId]);
              $penData   = $hasPen ? $penaltyMap[$loanId] : null;
            ?>
            <div class="loan-select-item <?= $i===0?'selected':'' ?>"
                 data-loan-id="<?= $loan['id'] ?>"
                 data-loan-type="<?= htmlspecialchars($loanLabel) ?>"
                 data-monthly="<?= number_format($monthly,2,'.','') ?>"
                 data-loan-amount="<?= number_format($loanAmt,2,'.','') ?>"
                 data-remaining="<?= number_format($remaining,2,'.','') ?>"
                 data-has-penalty="<?= $hasPen ? '1' : '0' ?>"
                 data-penalty-amount="<?= $hasPen ? number_format($penData['penalty_amount'],2,'.','') : '0' ?>"
                 data-penalty-total="<?= $hasPen ? number_format($penData['total_balance_with_penalty'],2,'.','') : '0' ?>"
                 data-months-overdue="<?= $hasPen ? $penData['months_overdue'] : '0' ?>"
                 data-penalty-rate="<?= $hasPen ? number_format($penData['penalty_rate']*100,0) : '0' ?>"
                 onclick="selectLoan(this)">
              <div class="d-flex align-items-start gap-3">
                <div class="check-ring mt-1"><i class="fas fa-check"></i></div>
                <div class="flex-grow-1">
                  <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                    <strong style="color:var(--eg-dark);"><?= htmlspecialchars($loanLabel) ?></strong>
                    <?php if ($loanCode): ?>
                      <span style="font-size:.68rem;font-weight:700;background:#e8f4f0;color:var(--eg-mid);padding:.15rem .55rem;border-radius:.5rem;letter-spacing:.6px;text-transform:uppercase;"><?= htmlspecialchars($loanCode) ?></span>
                    <?php endif; ?>
                    <span class="loan-badge <?= strtolower($loan['status']) ?>"><?= htmlspecialchars($loan['status']) ?></span>
                    <?php if ($hasPen): ?>
                      <span class="loan-badge overdue">⚠ OVERDUE</span>
                    <?php endif; ?>
                    <small class="text-muted ms-auto">ID #<?= $loan['id'] ?></small>
                  </div>
                  <div class="row g-2 mb-2">
                    <div class="col-6">
                      <div style="font-size:.75rem;color:#888;">Loan Amount</div>
                      <div style="font-weight:700;color:var(--eg-dark);">₱<?= number_format($loanAmt,2) ?></div>
                    </div>
                    <div class="col-6">
                      <div style="font-size:.75rem;color:#888;">Monthly Due</div>
                      <div style="font-weight:700;color:var(--eg-dark);">₱<?= number_format($monthly,2) ?></div>
                    </div>
                    <div class="col-6">
                      <div style="font-size:.75rem;color:#888;">Total Paid</div>
                      <div style="font-weight:600;color:#2e7d32;">₱<?= number_format($totalPaid,2) ?></div>
                    </div>
                    <div class="col-6">
                      <div style="font-size:.75rem;color:#888;">Remaining Principal</div>
                      <div style="font-weight:700;color:#c0392b;">₱<?= number_format($remaining,2) ?></div>
                    </div>
                    <?php if ($hasPen): ?>
                    <div class="col-6">
                      <div style="font-size:.75rem;color:#c0392b;font-weight:600;">Penalty Fee</div>
                      <div style="font-weight:800;color:#c0392b;">+₱<?= number_format($penData['penalty_amount'],2) ?></div>
                    </div>
                    <div class="col-6">
                      <div style="font-size:.75rem;color:#c0392b;font-weight:600;">Total Due (incl. penalty)</div>
                      <div style="font-weight:800;color:#8b1a1a;">₱<?= number_format($penData['total_balance_with_penalty'],2) ?></div>
                    </div>
                    <?php endif; ?>
                  </div>
                  <?php if ($hasPen): ?>
                  <div class="danger-box mb-2" style="padding:.55rem .75rem;font-size:.78rem;">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    <strong><?= $penData['months_overdue'] ?> month(s) overdue</strong> — <?= number_format($penData['penalty_rate']*100,0) ?>%/mo compounded penalty applied.
                    Pay your penalty fee below to clear this charge.
                  </div>
                  <?php elseif (!empty($loan['next_payment_due'])): ?>
                  <div style="font-size:.78rem;color:#e67e22;margin-bottom:.5rem;">
                    <i class="fas fa-calendar-alt me-1"></i>Next due: <?= date('M d, Y', strtotime($loan['next_payment_due'])) ?>
                  </div>
                  <?php endif; ?>
                  <div>
                    <div class="d-flex justify-content-between mb-1" style="font-size:.75rem;color:#888;">
                      <span>Repayment Progress</span><span><?= round($pct,1) ?>%</span>
                    </div>
                    <div class="progress-clean"><div class="bar" style="width:<?= $pct ?>%"></div></div>
                  </div>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- STEP 2: Payment Method -->
      <div class="card-clean mb-4">
        <div class="card-header-green"><i class="fas fa-wallet"></i> Step 2 — Choose Payment Method</div>
        <div class="card-body-pad">
          <div class="method-tabs">
            <div class="method-tab active" data-method="online" onclick="selectMethod('online')">
              <div class="sel-badge"><i class="fas fa-check"></i></div>
              <div class="method-icon"><i class="fas fa-globe"></i></div>
              <h4>Pay Online</h4>
              <p>Credit / Debit Card</p>
            </div>
            <div class="method-tab" data-method="cheque" onclick="selectMethod('cheque')">
              <div class="sel-badge"><i class="fas fa-check"></i></div>
              <div class="method-icon"><i class="fas fa-money-check"></i></div>
              <h4>Pay by Cheque</h4>
              <p>Submit cheque details</p>
            </div>
          </div>

          <!-- Online form -->
          <div id="onlineForm" class="mt-4">
            <div class="info-box mb-3">
              <i class="fas fa-shield-alt me-2"></i>
              <strong>Mock Payment:</strong> Simulated only — no real transaction occurs.
            </div>
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label-eg">Cardholder Name</label>
                <input type="text" id="cardName" class="form-control-eg" placeholder="Full name on card"
                       value="<?= htmlspecialchars($userInfo['full_name'] ?? '') ?>">
              </div>
              <div class="col-12">
                <label class="form-label-eg">Card Number</label>
                <div class="card-wrap">
                  <input type="text" id="cardNumber" class="form-control-eg"
                         placeholder="1234 5678 9012 3456" maxlength="19" oninput="fmtCard(this)">
                  <span class="c-icon" id="cardIcon"><i class="fas fa-credit-card"></i></span>
                </div>
              </div>
              <div class="col-6">
                <label class="form-label-eg">Expiry (MM / YY)</label>
                <input type="text" id="cardExpiry" class="form-control-eg" placeholder="MM / YY" maxlength="7" oninput="fmtExpiry(this)">
              </div>
              <div class="col-6">
                <label class="form-label-eg">CVV</label>
                <input type="password" id="cardCvv" class="form-control-eg" placeholder="•••" maxlength="4">
              </div>
            </div>
          </div>

          <!-- Cheque form -->
          <div id="chequeForm" class="mt-4" style="display:none;">
            <div class="info-box mb-3">
              <i class="fas fa-info-circle me-2"></i>
              <strong>Mock Cheque:</strong> Fill in details to simulate a cheque payment.
            </div>
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label-eg">Payable To</label>
                <input type="text" class="form-control-eg" value="Evergreen Trust and Savings Bank" readonly>
              </div>
              <div class="col-sm-6">
                <label class="form-label-eg">Cheque Number</label>
                <input type="text" id="chequeNumber" class="form-control-eg" placeholder="e.g. 001234" oninput="updateCheque()">
              </div>
              <div class="col-sm-6">
                <label class="form-label-eg">Bank Name</label>
                <input type="text" id="bankName" class="form-control-eg" placeholder="e.g. BDO, BPI, Metrobank" oninput="updateCheque()">
              </div>
              <div class="col-sm-6">
                <label class="form-label-eg">Account Name</label>
                <input type="text" id="acctName" class="form-control-eg"
                       value="<?= htmlspecialchars($userInfo['full_name'] ?? '') ?>" oninput="updateCheque()">
              </div>
              <div class="col-sm-6">
                <label class="form-label-eg">Cheque Date</label>
                <input type="date" id="chequeDate" class="form-control-eg" value="<?= date('Y-m-d') ?>" oninput="updateCheque()">
              </div>
              <div class="col-12">
                <label class="form-label-eg">Live Preview</label>
                <div class="cheque-preview">
                  <div class="cheque-line">Pay to: <span>Evergreen Trust and Savings Bank</span></div>
                  <div class="cheque-line">Amount: <span id="cpAmt">₱0.00</span></div>
                  <div class="cheque-line">Date: <span id="cpDate"><?= date('F d, Y') ?></span></div>
                  <div class="cheque-line">Cheque No.: <span id="cpNo">—</span></div>
                  <div class="cheque-line">Bank: <span id="cpBank">—</span></div>
                  <div style="font-size:.75rem;color:#999;margin-top:.5rem;">Signature: ___________________</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div><!-- /col-lg-7 -->

    <!-- RIGHT COL: Summary -->
    <div class="col-lg-5">
      <div class="card-clean" style="position:sticky;top:80px;">
        <div class="card-header-green"><i class="fas fa-receipt"></i> Payment Summary</div>
        <div class="card-body-pad">

          <!-- Loan amount display -->
          <div class="amount-display mb-3">
            <div class="lbl">Loan Amount to Pay</div>
            <div class="val" id="summaryAmount">₱0.00</div>
          </div>

          <!-- ── Penalty section (shown only if selected loan has penalty) ── -->
          <div id="penaltySummarySection" style="display:none;">
            <div class="penalty-summary-box">
              <div class="pen-title"><i class="fas fa-exclamation-triangle"></i> Active Penalty Breakdown</div>
              <div class="pen-row"><span class="pl">Principal Balance</span>      <span class="pv green" id="penOrigBal">₱0.00</span></div>
              <div class="pen-row"><span class="pl">Penalty Fee</span>            <span class="pv" id="penAmt">+₱0.00</span></div>
              <div class="pen-row"><span class="pl">Months Overdue</span>         <span class="pv" id="penMonths">0</span></div>
              <div class="pen-row"><span class="pl">Penalty Rate</span>           <span class="pv" id="penRate">5%/mo</span></div>
              <div class="pen-divider"></div>
              <div class="pen-total-row"><span class="ptl">Total Balance Due</span><span class="ptv" id="penTotal">₱0.00</span></div>
            </div>

            <!-- Penalty payment checkbox -->
            <div class="penalty-input-section">
              <div class="pen-sec-title"><i class="fas fa-gavel"></i> Pay Penalty Fee (Separate)</div>
              <div class="penalty-checkbox-wrap">
                <input type="checkbox" id="payPenaltyCheck" onchange="togglePenaltyInput(this.checked)">
                <label for="payPenaltyCheck">
                  Also pay the penalty fee in this transaction.
                  <span style="color:#c0392b;font-weight:700;" id="penAmtLabel"></span>
                  <br><small style="color:#999;">Penalty payment is recorded separately in the penalties ledger.</small>
                </label>
              </div>
              <div id="penaltyInputWrap" style="display:none;">
                <label class="form-label-eg">Penalty Amount to Pay <span style="color:#e74c3c;">*</span></label>
                <div style="position:relative;">
                  <span style="position:absolute;left:.9rem;top:50%;transform:translateY(-50%);font-weight:700;color:#c0392b;pointer-events:none;">₱</span>
                  <input type="number" id="penaltyAmtInput" class="form-control-eg"
                         style="padding-left:2rem;border-color:rgba(192,57,43,0.4);"
                         placeholder="0.00" step="0.01" min="1"
                         oninput="onPenaltyAmtChange(this.value)">
                </div>
                <div style="font-size:.77rem;color:#888;margin-top:.3rem;">Pre-filled with full penalty. You may pay partial.</div>
              </div>
            </div>

            <!-- Total payment display (loan + penalty) -->
            <div class="total-pay-display" id="totalPayDisplay" style="display:none;">
              <div class="tpl">Total Payment (Loan + Penalty)</div>
              <div class="tpv" id="totalPayValue">₱0.00</div>
            </div>
          </div>

          <div class="mb-4">
            <div class="summary-row">
              <span class="sl">Account Holder</span>
              <span class="sv"><?= htmlspecialchars($userInfo['full_name'] ?? ($_SESSION['user_name'] ?? '—')) ?></span>
            </div>
            <?php if (!empty($userInfo['account_number'])): ?>
            <div class="summary-row">
              <span class="sl">Account No.</span>
              <span class="sv"><?= htmlspecialchars($userInfo['account_number']) ?></span>
            </div>
            <?php endif; ?>
            <div class="summary-row"><span class="sl">Loan ID</span>             <span class="sv" id="sumLoanId">—</span></div>
            <div class="summary-row"><span class="sl">Loan Type</span>           <span class="sv" id="sumLoanType">—</span></div>
            <div class="summary-row"><span class="sl">Monthly Due</span>         <span class="sv" id="sumMonthly">₱0.00</span></div>
            <div class="summary-row"><span class="sl">Remaining Principal</span> <span class="sv danger" id="sumRemaining">₱0.00</span></div>
            <div class="summary-row" id="sumPenaltyRow" style="display:none;">
              <span class="sl" style="color:#c0392b;">Penalty Fee</span>
              <span class="sv danger" id="sumPenaltyAmt">₱0.00</span>
            </div>
            <div class="summary-row"><span class="sl">Payment Method</span>      <span class="sv" id="sumMethod">Online</span></div>
          </div>

          <div class="mb-3">
            <label class="form-label-eg">Loan Payment Amount <span style="color:#e74c3c;">*</span></label>
            <div style="position:relative;">
              <span style="position:absolute;left:.9rem;top:50%;transform:translateY(-50%);font-weight:700;color:var(--eg-dark);pointer-events:none;">₱</span>
              <input type="number" id="amtInput" class="form-control-eg" style="padding-left:2rem;"
                     placeholder="0.00" step="0.01" min="1" oninput="onAmtChange(this.value)">
            </div>
            <div style="font-size:.77rem;color:#888;margin-top:.3rem;">Pre-filled with monthly due. You may pay more.</div>
          </div>

          <form id="payForm" method="POST" action="Payment.php" onsubmit="return validatePay(event)">
            <input type="hidden" name="action"          value="pay">
            <input type="hidden" name="loan_id"         id="hidLoanId"      value="<?= htmlspecialchars($loans[0]['id'] ?? '') ?>">
            <input type="hidden" name="payment_method"  id="hidMethod"      value="online">
            <input type="hidden" name="amount"          id="hidAmount"      value="">
            <!-- ── Penalty amount sent separately — written to loan_penalties ── -->
            <input type="hidden" name="penalty_amount"  id="hidPenaltyAmt"  value="0">

            <button type="submit" class="btn-pay" id="payBtn">
              <i class="fas fa-lock me-2"></i> Confirm &amp; Process Payment
            </button>
          </form>

          <p style="font-size:.75rem;color:#aaa;text-align:center;margin-top:.75rem;">
            <i class="fas fa-shield-alt me-1"></i> Mock system — no real money is transferred.
          </p>
        </div>
      </div>
    </div>

  </div><!-- /row -->
  <?php endif; ?>

  <!-- ══ PAYMENT HISTORY ══════════════════════════════════════════════════════ -->
  <?php if (!empty($payHistory)): ?>
  <div class="card-clean mt-4">
    <div class="card-header-green">
      <i class="fas fa-history"></i> Payment History
      <span style="margin-left:auto;font-size:.78rem;font-weight:400;opacity:.8;"><?= count($payHistory) ?> recent records</span>
    </div>
    <div style="overflow-x:auto;">
      <table class="table mb-0 history-table">
        <thead>
          <tr>
            <th>Txn Reference</th>
            <th>Loan ID</th>
            <th>Type</th>
            <th>Amount Paid</th>
            <th>Method</th>
            <th>Date</th>
            <th>Txn Status</th>
            <th>Loan Status</th>
            <th>Receipt</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($payHistory as $ph):
            $ls     = strtolower($ph['loan_status'] ?? 'active');
            $rowCls = ($ls === 'closed') ? ' row-closed' : '';
            $loanAmt = floatval($ph['loan_total_amount'] ?? 0);
          ?>
          <tr class="<?= $rowCls ?>">
            <td>
              <code style="font-size:.82rem;"><?= htmlspecialchars($ph['transaction_ref']) ?></code>
              <?php if ($ls === 'closed'): ?>
                <div class="paid-full-chip"><i class="fas fa-check me-1" style="font-size:.6rem;"></i> Paid in Full</div>
              <?php endif; ?>
            </td>
            <td>#<?= (int)$ph['loan_application_id'] ?></td>
            <td><?= htmlspecialchars($ph['loan_type'] ?? '—') ?></td>
            <td style="font-weight:700;color:var(--eg-dark);">
              ₱<?= number_format(floatval($ph['amount']),2) ?>
              <?php if ($ls === 'closed' && $loanAmt > 0): ?>
                <div style="font-size:.72rem;color:var(--eg-mid);font-weight:500;">
                  Total: ₱<?= number_format($loanAmt,2) ?>
                </div>
              <?php endif; ?>
            </td>
            <td>
              <span class="mpill <?= $ph['payment_method'] ?>">
                <i class="fas fa-<?= $ph['payment_method']==='online'?'globe':'money-check' ?>"></i>
                <?= ucfirst($ph['payment_method']) ?>
              </span>
            </td>
            <td style="font-size:.85rem;"><?= date('M d, Y H:i', strtotime($ph['payment_date'])) ?></td>
            <td><span class="spill"><?= htmlspecialchars($ph['status']) ?></span></td>
            <td>
              <span class="loan-status-pill <?= $ls ?>">
                <?= $ls === 'closed' ? '✓ Closed / Paid' : htmlspecialchars($ph['loan_status']) ?>
              </span>
            </td>
            <td>
              <a href="Payment.php?action=downloadpdf&txn_ref=<?= urlencode($ph['transaction_ref']) ?>"
                 class="btn-dl-pdf" title="Download PDF Receipt">
                <i class="fas fa-file-pdf"></i> PDF
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

</div><!-- /container -->

<!-- ══ SUCCESS OVERLAY ════════════════════════════════════════════════════════ -->
<?php if ($paymentResult && $paymentResult['success']): ?>
<div class="success-overlay show" id="successOverlay">
  <div class="success-card">
    <div class="success-icon"><i class="fas fa-check"></i></div>
    <h2>Payment Successful!</h2>
    <p style="color:#666;font-size:.78rem;margin-bottom:.35rem;">Your payment has been recorded.</p>
    <div class="txn-ref mb-2"><?= htmlspecialchars($paymentResult['txn_ref']) ?></div>

    <div class="mb-2">
      <span class="email-sent-badge">
        <i class="fas fa-envelope-circle-check"></i>
        <?= $paymentResult['is_fully_paid']
            ? 'Loan completion email sent to ' . htmlspecialchars($paymentResult['user_email'])
            : 'Payment confirmation email sent to ' . htmlspecialchars($paymentResult['user_email']) ?>
      </span>
    </div>

    <?php if ($paymentResult['penalty_paid'] > 0): ?>
    <div class="mb-2">
      <span class="penalty-paid-chip">
        <i class="fas fa-gavel"></i>
        Penalty fee paid: ₱<?= number_format($paymentResult['penalty_paid'], 2) ?> — recorded in penalties ledger
      </span>
    </div>
    <?php endif; ?>

    <div class="mb-2 text-start" style="font-size:.82rem;border:1px solid #eef4ee;border-radius:.55rem;padding:.55rem .75rem;background:#fafffe;">
      <div class="summary-row"><span class="sl">Borrower</span>              <span class="sv"><?= htmlspecialchars($paymentResult['borrower_name'] ?? '—') ?></span></div>
      <?php if (!empty($paymentResult['account_number'])): ?>
      <div class="summary-row"><span class="sl">Account No.</span>           <span class="sv"><?= htmlspecialchars($paymentResult['account_number']) ?></span></div>
      <?php endif; ?>
      <div class="summary-row"><span class="sl">Loan ID</span>               <span class="sv">#<?= $paymentResult['loan_id'] ?></span></div>
      <div class="summary-row"><span class="sl">Loan Type</span>             <span class="sv"><?= htmlspecialchars($paymentResult['loan_type']) ?></span></div>
      <div class="summary-row"><span class="sl">Loan Amount Paid</span>      <span class="sv" style="color:#2e7d32;">₱<?= number_format($paymentResult['amount_paid'],2) ?></span></div>
      <?php if ($paymentResult['penalty_paid'] > 0): ?>
      <div class="summary-row"><span class="sl" style="color:#c0392b;">Penalty Fee Paid</span> <span class="sv danger">₱<?= number_format($paymentResult['penalty_paid'],2) ?></span></div>
      <div class="summary-row"><span class="sl">Total This Transaction</span><span class="sv">₱<?= number_format($paymentResult['amount_paid'] + $paymentResult['penalty_paid'],2) ?></span></div>
      <?php endif; ?>
      <div class="summary-row"><span class="sl">Total Loan Paid to Date</span><span class="sv">₱<?= number_format($paymentResult['total_paid'],2) ?></span></div>
      <div class="summary-row"><span class="sl">Loan Principal</span>        <span class="sv">₱<?= number_format($paymentResult['loan_amount'],2) ?></span></div>
      <div class="summary-row">
        <span class="sl">Remaining Loan Balance</span>
        <span class="sv" style="color:<?= $paymentResult['remaining'] > 0 ? '#c0392b' : '#2e7d32' ?>;">
          ₱<?= number_format($paymentResult['remaining'],2) ?>
        </span>
      </div>
      <div class="summary-row"><span class="sl">Method</span>                <span class="sv"><?= ucfirst($paymentResult['payment_method']) ?></span></div>
      <div class="summary-row">
        <span class="sl">Loan Status</span>
        <span class="sv">
          <?php if ($paymentResult['is_fully_paid']): ?>
            <span style="color:#0a3b2f;font-weight:700;">✓ Closed / Paid</span>
          <?php else: ?>
            <?= htmlspecialchars($paymentResult['new_status']) ?>
          <?php endif; ?>
        </span>
      </div>
      <?php if (!empty($paymentResult['ip_address'])): ?>
      <div class="summary-row">
        <span class="sl">IP Address</span>
        <span class="sv" style="font-family:monospace;font-size:.82rem;"><?= htmlspecialchars($paymentResult['ip_address']) ?></span>
      </div>
      <?php endif; ?>
    </div>

    <?php if ($paymentResult['is_fully_paid']): ?>
    <div class="fully-paid-banner">
      <div class="fp-icon"><i class="fas fa-star"></i></div>
      <div class="fp-title">🎉 Loan Fully Paid!</div>
      <div class="fp-sub">This loan has been marked <strong>Closed</strong> in the system.</div>
      <div class="fp-details-grid">
        <div class="fp-detail-chip"><div class="fp-chip-label">New Status</div>   <div class="fp-chip-val">Closed / Paid</div></div>
        <div class="fp-detail-chip"><div class="fp-chip-label">Total Settled</div><div class="fp-chip-val">₱<?= number_format($paymentResult['total_paid'],2) ?></div></div>
        <div class="fp-detail-chip"><div class="fp-chip-label">Loan ID</div>      <div class="fp-chip-val">#<?= $paymentResult['loan_id'] ?></div></div>
        <div class="fp-detail-chip"><div class="fp-chip-label">Remaining</div>    <div class="fp-chip-val">₱0.00</div></div>
      </div>
    </div>
    <?php endif; ?>

    <div class="d-flex gap-2 mt-3 flex-wrap justify-content-center">
      <button class="btn-pay" style="width:auto;padding:.5rem 1.25rem;font-size:.88rem;"
              onclick="document.getElementById('successOverlay').classList.remove('show')">
        <i class="fas fa-redo me-1"></i> Another Payment
      </button>
      <a href="Payment.php?action=downloadpdf&txn_ref=<?= urlencode($paymentResult['txn_ref']) ?>"
         class="btn-pdf" title="Download PDF Receipt">
        <i class="fas fa-file-pdf"></i> Download Receipt
      </a>
      <a href="<?= htmlspecialchars($_DASHBOARD) ?>"
         class="btn-pay text-decoration-none"
         style="width:auto;padding:.5rem 1.25rem;font-size:.88rem;background:linear-gradient(135deg,#555,#333);">
        <i class="fas fa-th-large me-1"></i> Dashboard
      </a>
    </div>
  </div>
</div>
<?php endif; ?>

<?php
$footerPath = __DIR__ . '/../../footer.php';
if (file_exists($footerPath)) include $footerPath;
?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const loansData = <?= json_encode(array_values(array_map(function($l) use ($penaltyMap) {
    $loanId = (int)$l['id'];
    $pen    = $penaltyMap[$loanId] ?? null;
    return [
        'id'             => $loanId,
        'loan_type'      => $l['loan_type'] ?? ('Loan #' . $l['id']),
        'monthly'        => number_format(floatval($l['monthly_payment'] ?? 0), 2, '.', ''),
        'remaining'      => number_format(floatval($l['remaining']), 2, '.', ''),
        'has_penalty'    => !!$pen,
        'penalty_amount' => $pen ? number_format($pen['penalty_amount'], 2, '.', '') : '0.00',
        'penalty_total'  => $pen ? number_format($pen['total_balance_with_penalty'], 2, '.', '') : '0.00',
        'months_overdue' => $pen ? $pen['months_overdue'] : 0,
        'penalty_rate'   => $pen ? number_format($pen['penalty_rate'] * 100, 0) : '0',
        'original_balance'=> $pen ? number_format($pen['original_balance'], 2, '.', '') : '0.00',
    ];
}, $loans))) ?>;

let selLoanId      = loansData[0]?.id        ?? null;
let selMonthly     = loansData[0]?.monthly   ?? '0.00';
let selRemain      = loansData[0]?.remaining ?? '0.00';
let selType        = loansData[0]?.loan_type ?? '';
let selMethod      = 'online';
let selHasPenalty  = loansData[0]?.has_penalty  ?? false;
let selPenAmt      = loansData[0]?.penalty_amount ?? '0.00';
let selPenTotal    = loansData[0]?.penalty_total  ?? '0.00';
let selPenMonths   = loansData[0]?.months_overdue ?? 0;
let selPenRate     = loansData[0]?.penalty_rate   ?? '0';
let selPenOrigBal  = loansData[0]?.original_balance ?? '0.00';

let currentPenaltyPayment = 0;

document.addEventListener('DOMContentLoaded', () => {
    refreshSummary();
    setAmt(selMonthly);
    refreshPenaltySection();
});

function selectLoan(el) {
    document.querySelectorAll('.loan-select-item').forEach(x => x.classList.remove('selected'));
    el.classList.add('selected');
    selLoanId     = el.dataset.loanId;
    selMonthly    = el.dataset.monthly;
    selRemain     = el.dataset.remaining;
    selType       = el.dataset.loanType;
    selHasPenalty = el.dataset.hasPenalty === '1';
    selPenAmt     = el.dataset.penaltyAmount || '0.00';
    selPenTotal   = el.dataset.penaltyTotal  || '0.00';
    selPenMonths  = parseInt(el.dataset.monthsOverdue || '0', 10);
    selPenRate    = el.dataset.penaltyRate || '0';
    // Reconstruct original balance from remaining (before penalty added)
    selPenOrigBal = el.dataset.remaining || '0.00';

    document.getElementById('hidLoanId').value = selLoanId;

    // Reset penalty checkbox
    const chk = document.getElementById('payPenaltyCheck');
    if (chk) { chk.checked = false; togglePenaltyInput(false); }
    currentPenaltyPayment = 0;
    document.getElementById('hidPenaltyAmt').value = '0';

    setAmt(selMonthly);
    refreshSummary();
    refreshPenaltySection();
}

function refreshPenaltySection() {
    const section    = document.getElementById('penaltySummarySection');
    const penRow     = document.getElementById('sumPenaltyRow');

    if (!section) return;

    if (selHasPenalty) {
        section.style.display = '';
        if (penRow) penRow.style.display = '';

        // Populate breakdown
        const penAmtF  = parseFloat(selPenAmt)   || 0;
        const penTotF  = parseFloat(selPenTotal)  || 0;
        const origBalF = parseFloat(selPenOrigBal)|| 0;

        document.getElementById('penOrigBal').textContent = '₱' + origBalF.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});
        document.getElementById('penAmt').textContent     = '+₱' + penAmtF.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});
        document.getElementById('penMonths').textContent  = selPenMonths + ' month(s)';
        document.getElementById('penRate').textContent    = selPenRate + '%/mo (compounded)';
        document.getElementById('penTotal').textContent   = '₱' + penTotF.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});
        document.getElementById('sumPenaltyAmt').textContent = '+₱' + penAmtF.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});
        document.getElementById('penAmtLabel').textContent   = '(₱' + penAmtF.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}) + ')';

        // Pre-fill penalty input with full penalty amount
        const penInput = document.getElementById('penaltyAmtInput');
        if (penInput) penInput.value = penAmtF.toFixed(2);
    } else {
        section.style.display = 'none';
        if (penRow) penRow.style.display = 'none';
        currentPenaltyPayment = 0;
        document.getElementById('hidPenaltyAmt').value = '0';
    }
    updateTotalDisplay();
}

function togglePenaltyInput(isChecked) {
    const wrap = document.getElementById('penaltyInputWrap');
    if (wrap) wrap.style.display = isChecked ? '' : 'none';

    if (!isChecked) {
        currentPenaltyPayment = 0;
        document.getElementById('hidPenaltyAmt').value = '0';
    } else {
        // Default to full penalty amount
        const penAmtF = parseFloat(selPenAmt) || 0;
        currentPenaltyPayment = penAmtF;
        document.getElementById('hidPenaltyAmt').value = penAmtF.toFixed(2);
        const penInput = document.getElementById('penaltyAmtInput');
        if (penInput) penInput.value = penAmtF.toFixed(2);
    }
    updateTotalDisplay();
}

function onPenaltyAmtChange(v) {
    const maxPen = parseFloat(selPenAmt) || 0;
    let n = parseFloat(v) || 0;
    if (n > maxPen) n = maxPen;  // can't pay more than owed
    currentPenaltyPayment = n;
    document.getElementById('hidPenaltyAmt').value = n.toFixed(2);
    updateTotalDisplay();
}

function updateTotalDisplay() {
    const totalDisplay = document.getElementById('totalPayDisplay');
    const totalVal     = document.getElementById('totalPayValue');
    if (!totalDisplay || !totalVal) return;

    const loanAmt = parseFloat(document.getElementById('amtInput')?.value || 0) || 0;
    const penAmt  = currentPenaltyPayment;

    if (penAmt > 0) {
        totalDisplay.style.display = '';
        totalVal.textContent = '₱' + (loanAmt + penAmt).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});
    } else {
        totalDisplay.style.display = 'none';
    }
}

function selectMethod(m) {
    selMethod = m;
    document.querySelectorAll('.method-tab').forEach(t => t.classList.remove('active'));
    document.querySelector(`.method-tab[data-method="${m}"]`).classList.add('active');
    document.getElementById('hidMethod').value          = m;
    document.getElementById('onlineForm').style.display = m === 'online' ? '' : 'none';
    document.getElementById('chequeForm').style.display = m === 'cheque' ? '' : 'none';
    document.getElementById('sumMethod').textContent    = m === 'online' ? 'Online (Card)' : 'Cheque';
}

function refreshSummary() {
    document.getElementById('sumLoanId').textContent    = '#' + selLoanId;
    document.getElementById('sumLoanType').textContent  = selType;
    document.getElementById('sumMonthly').textContent   = '₱' + fmt(selMonthly);
    document.getElementById('sumRemaining').textContent = '₱' + fmt(selRemain);
}

function setAmt(v) {
    const n = parseFloat(v) || 0;
    document.getElementById('amtInput').value            = n.toFixed(2);
    document.getElementById('hidAmount').value           = n.toFixed(2);
    document.getElementById('summaryAmount').textContent = '₱' + fmt(v);
    document.getElementById('cpAmt').textContent         = '₱' + fmt(v);
    updateTotalDisplay();
}

function onAmtChange(v) {
    const n = parseFloat(v) || 0;
    document.getElementById('summaryAmount').textContent = '₱' + n.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});
    document.getElementById('hidAmount').value           = n.toFixed(2);
    document.getElementById('cpAmt').textContent         = '₱' + n.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});
    updateTotalDisplay();
}

function fmt(v) {
    return parseFloat(v||0).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});
}

function fmtCard(inp) {
    let v = inp.value.replace(/\D/g,'').substring(0,16);
    inp.value = v.replace(/(.{4})/g,'$1 ').trim();
    const ic = document.getElementById('cardIcon');
    if      (v.startsWith('4'))                        ic.innerHTML = '<i class="fab fa-cc-visa" style="color:#1a1f71;font-size:1.4rem;"></i>';
    else if (/^5[1-5]/.test(v))                        ic.innerHTML = '<i class="fab fa-cc-mastercard" style="color:#eb001b;font-size:1.4rem;"></i>';
    else if (v.startsWith('34') || v.startsWith('37')) ic.innerHTML = '<i class="fab fa-cc-amex" style="color:#007bc1;font-size:1.4rem;"></i>';
    else ic.innerHTML = '<i class="fas fa-credit-card"></i>';
}

function fmtExpiry(inp) {
    let v = inp.value.replace(/\D/g,'');
    if (v.length >= 3) v = v.substring(0,2) + ' / ' + v.substring(2,4);
    inp.value = v;
}

function updateCheque() {
    const d  = document.getElementById('chequeDate').value;
    const no = document.getElementById('chequeNumber').value || '—';
    const bk = document.getElementById('bankName').value     || '—';
    if (d) {
        const parts = d.split('-');
        const dt = new Date(parseInt(parts[0]), parseInt(parts[1])-1, parseInt(parts[2]));
        document.getElementById('cpDate').textContent = dt.toLocaleDateString('en-US',{year:'numeric',month:'long',day:'numeric'});
    }
    document.getElementById('cpNo').textContent   = no;
    document.getElementById('cpBank').textContent = bk;
}

function validatePay(e) {
    const amt = parseFloat(document.getElementById('amtInput').value || 0);
    if (!selLoanId) { alert('Please select a loan.'); e.preventDefault(); return false; }
    if (amt <= 0)   { alert('Please enter a valid payment amount greater than 0.'); e.preventDefault(); return false; }

    // Validate penalty amount if checkbox ticked
    const chk = document.getElementById('payPenaltyCheck');
    if (chk && chk.checked) {
        const maxPen = parseFloat(selPenAmt) || 0;
        if (currentPenaltyPayment <= 0) {
            alert('Please enter a valid penalty payment amount.'); e.preventDefault(); return false;
        }
        if (currentPenaltyPayment > maxPen) {
            alert('Penalty payment cannot exceed the penalty amount owed (₱' + fmt(String(maxPen)) + ').'); e.preventDefault(); return false;
        }
    }

    if (selMethod === 'online') {
        const num  = document.getElementById('cardNumber').value.replace(/\s/g,'');
        const name = document.getElementById('cardName').value.trim();
        const exp  = document.getElementById('cardExpiry').value;
        const cvv  = document.getElementById('cardCvv').value;
        if (!name)           { alert('Please enter cardholder name.'); e.preventDefault(); return false; }
        if (num.length < 16) { alert('Please enter a valid 16-digit card number.'); e.preventDefault(); return false; }
        if (exp.length < 7)  { alert('Please enter a valid expiry date (MM / YY).'); e.preventDefault(); return false; }
        if (cvv.length < 3)  { alert('Please enter a valid CVV.'); e.preventDefault(); return false; }
    } else {
        if (!document.getElementById('chequeNumber').value.trim()) { alert('Please enter the cheque number.'); e.preventDefault(); return false; }
        if (!document.getElementById('bankName').value.trim())     { alert('Please enter the bank name.'); e.preventDefault(); return false; }
        if (!document.getElementById('chequeDate').value)          { alert('Please enter the cheque date.'); e.preventDefault(); return false; }
    }

    document.getElementById('hidAmount').value = amt.toFixed(2);
    document.getElementById('hidPenaltyAmt').value = currentPenaltyPayment.toFixed(2);

    const btn = document.getElementById('payBtn');
    btn.disabled  = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Processing…';
    return true;
}
</script>
</body>
</html>