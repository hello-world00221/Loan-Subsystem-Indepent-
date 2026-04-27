<?php
session_start();

// ── Auth guard — Super Admin only ────────────────────────────────────────────
if (isset($_SESSION['officer_id']) && !isset($_SESSION['admin_id'])) {
    header("Location: ../Loan/adminindex.php");
    exit;
}
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../Loan/login.php");
    exit;
}

// ── Load PHPMailer ────────────────────────────────────────────────────────────
require 'PHPMailer-7.0.0/src/Exception.php';
require 'PHPMailer-7.0.0/src/PHPMailer.php';
require 'PHPMailer-7.0.0/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

$MAIL_HOST      = 'smtp.gmail.com';
$MAIL_PORT      = 587;
$MAIL_USERNAME  = 'franciscarpeso@gmail.com';
$MAIL_PASSWORD  = 'bwobttvnbpqvzimv';
$MAIL_FROM      = 'franciscarpeso@gmail.com';
$MAIL_FROM_NAME = 'Evergreen Trust and Savings';

// ── DB ────────────────────────────────────────────────────────────────────────
$host   = 'localhost';
$dbname = 'loandb';
$dbuser = 'root';
$dbpass = '';

$error   = null;
$success = null;

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $dbuser, $dbpass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    // ── Ensure loan_penalties table exists ─────────────────────────────────
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS loan_penalties (
            id                    INT AUTO_INCREMENT PRIMARY KEY,
            loan_application_id   INT NOT NULL,
            user_email            VARCHAR(255) NOT NULL,
            penalty_amount        DECIMAL(15,2) NOT NULL DEFAULT 0,
            penalty_rate          DECIMAL(5,4) NOT NULL DEFAULT 0.05,
            months_overdue        INT NOT NULL DEFAULT 0,
            original_balance      DECIMAL(15,2) NOT NULL DEFAULT 0,
            total_balance_with_penalty DECIMAL(15,2) NOT NULL DEFAULT 0,
            status                ENUM('Active','Waived','Settled') NOT NULL DEFAULT 'Active',
            email_sent            TINYINT(1) NOT NULL DEFAULT 0,
            email_sent_at         DATETIME NULL,
            created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_loan (loan_application_id),
            INDEX idx_email (user_email),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // ── Auto-compute & upsert penalties for overdue Active loans ──────────
    // A loan is overdue if next_payment_due < TODAY and status = 'Active'
    // Penalty = remaining_balance * rate * months_overdue  (compounded per month)
    $overdue = $pdo->query("
        SELECT
            la.id,
            la.user_email,
            la.loan_amount,
            la.next_payment_due,
            la.status                                                AS loan_status,
            COALESCE(SUM(lp.amount), 0)                             AS total_paid,
            GREATEST(0, la.loan_amount - COALESCE(SUM(lp.amount),0)) AS remaining_balance,
            TIMESTAMPDIFF(MONTH, la.next_payment_due, CURDATE())    AS months_overdue,
            u.full_name,
            COALESCE(lt.name, CONCAT('Loan #',la.id))               AS loan_type
        FROM   loan_applications la
        LEFT JOIN loan_payments lp ON lp.loan_application_id = la.id AND lp.status = 'Completed'
        LEFT JOIN users u          ON u.user_email = la.user_email
        LEFT JOIN loan_types lt    ON lt.id = la.loan_type_id
        WHERE  la.status = 'Active'
          AND  la.next_payment_due IS NOT NULL
          AND  la.next_payment_due < CURDATE()
          AND  la.loan_amount > COALESCE((SELECT SUM(lp2.amount) FROM loan_payments lp2 WHERE lp2.loan_application_id = la.id AND lp2.status = 'Completed'), 0)
        GROUP BY la.id, la.user_email, la.loan_amount, la.next_payment_due, la.status, u.full_name, lt.name
        HAVING months_overdue >= 1
    ")->fetchAll();

    $penaltyRate = 0.05; // 5% per month (configurable: 0.03–0.05)

    foreach ($overdue as $loan) {
        $monthsOverdue = (int)$loan['months_overdue'];
        $remaining     = floatval($loan['remaining_balance']);

        // Compound penalty: balance * ((1 + rate)^months - 1)
        $penaltyAmt  = round($remaining * (pow(1 + $penaltyRate, $monthsOverdue) - 1), 2);
        $totalWithPenalty = round($remaining + $penaltyAmt, 2);

        // Check if penalty record already exists
        $exists = $pdo->prepare("SELECT id, email_sent FROM loan_penalties WHERE loan_application_id = ? AND status = 'Active' LIMIT 1");
        $exists->execute([$loan['id']]);
        $existing = $exists->fetch();

        if ($existing) {
            $pdo->prepare("
                UPDATE loan_penalties
                SET penalty_amount = ?, months_overdue = ?,
                    original_balance = ?, total_balance_with_penalty = ?,
                    penalty_rate = ?, updated_at = NOW()
                WHERE id = ?
            ")->execute([$penaltyAmt, $monthsOverdue, $remaining, $totalWithPenalty, $penaltyRate, $existing['id']]);
            $emailSent = (bool)$existing['email_sent'];
        } else {
            $pdo->prepare("
                INSERT INTO loan_penalties
                    (loan_application_id, user_email, penalty_amount, penalty_rate,
                     months_overdue, original_balance, total_balance_with_penalty, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'Active')
            ")->execute([$loan['id'], $loan['user_email'], $penaltyAmt, $penaltyRate, $monthsOverdue, $remaining, $totalWithPenalty]);
            $emailSent = false;
        }

        // ── Send penalty notification email (once per new penalty) ─────────
        if (!$emailSent) {
            $mail = new PHPMailer(true);
            try {
                $mail->SMTPDebug   = SMTP::DEBUG_OFF;
                $mail->isSMTP();
                $mail->Host        = $MAIL_HOST;
                $mail->SMTPAuth    = true;
                $mail->Username    = $MAIL_USERNAME;
                $mail->Password    = $MAIL_PASSWORD;
                $mail->SMTPSecure  = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port        = $MAIL_PORT;
                $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];
                $mail->Timeout     = 30;
                $mail->setFrom($MAIL_FROM, $MAIL_FROM_NAME);
                $mail->addAddress($loan['user_email'], $loan['full_name']);
                $mail->isHTML(true);
                $mail->Subject = '⚠️ Overdue Loan Notice – Penalty Applied | Evergreen Trust and Savings';
                $mail->Body    = getPenaltyEmailBody(
                    $loan['full_name'],
                    $loan['id'],
                    $loan['loan_type'],
                    $remaining,
                    $penaltyAmt,
                    $totalWithPenalty,
                    $monthsOverdue,
                    $penaltyRate * 100,
                    $loan['next_payment_due']
                );
                $mail->AltBody = "Dear {$loan['full_name']},\n\nYour loan #{$loan['id']} is {$monthsOverdue} month(s) overdue. A penalty of ₱" . number_format($penaltyAmt, 2) . " has been applied. Total balance due: ₱" . number_format($totalWithPenalty, 2) . ".\n\nPlease pay immediately to avoid further penalties.";
                $mail->send();

                // Mark email sent
                $pdo->prepare("UPDATE loan_penalties SET email_sent = 1, email_sent_at = NOW() WHERE loan_application_id = ? AND status = 'Active'")->execute([$loan['id']]);
            } catch (Exception $e) {
                // Log but don't crash
                error_log("Penalty email error for loan #{$loan['id']}: " . $mail->ErrorInfo);
            }
        }
    }

    // ── Manual "Send Reminder" action ─────────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reminder'])) {
        $loanId = (int)$_POST['loan_id'];
        $penRow = $pdo->prepare("
            SELECT lpen.*, la.loan_amount, u.full_name,
                   COALESCE(lt.name, CONCAT('Loan #',la.id)) AS loan_type,
                   la.next_payment_due
            FROM   loan_penalties lpen
            JOIN   loan_applications la ON la.id = lpen.loan_application_id
            LEFT JOIN users u           ON u.user_email = lpen.user_email
            LEFT JOIN loan_types lt     ON lt.id = la.loan_type_id
            WHERE  lpen.loan_application_id = ? AND lpen.status = 'Active'
            LIMIT 1
        ");
        $penRow->execute([$loanId]);
        $penData = $penRow->fetch();

        if ($penData) {
            $mail = new PHPMailer(true);
            try {
                $mail->SMTPDebug   = SMTP::DEBUG_OFF;
                $mail->isSMTP();
                $mail->Host        = $MAIL_HOST;
                $mail->SMTPAuth    = true;
                $mail->Username    = $MAIL_USERNAME;
                $mail->Password    = $MAIL_PASSWORD;
                $mail->SMTPSecure  = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port        = $MAIL_PORT;
                $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];
                $mail->Timeout     = 30;
                $mail->setFrom($MAIL_FROM, $MAIL_FROM_NAME);
                $mail->addAddress($penData['user_email'], $penData['full_name']);
                $mail->isHTML(true);
                $mail->Subject = '🔔 Payment Reminder – Overdue Loan | Evergreen Trust and Savings';
                $mail->Body    = getPenaltyEmailBody(
                    $penData['full_name'], $loanId, $penData['loan_type'],
                    $penData['original_balance'], $penData['penalty_amount'],
                    $penData['total_balance_with_penalty'], $penData['months_overdue'],
                    $penData['penalty_rate'] * 100, $penData['next_payment_due']
                );
                $mail->send();
                $pdo->prepare("UPDATE loan_penalties SET email_sent = 1, email_sent_at = NOW() WHERE loan_application_id = ? AND status = 'Active'")->execute([$loanId]);
                $success = "Reminder sent to {$penData['user_email']}.";
            } catch (Exception $e) {
                $error = "Could not send reminder: " . $mail->ErrorInfo;
            }
        }
    }

    // ── Waive penalty ──────────────────────────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['waive_penalty'])) {
        $loanId = (int)$_POST['loan_id'];
        $pdo->prepare("UPDATE loan_penalties SET status = 'Waived', updated_at = NOW() WHERE loan_application_id = ? AND status = 'Active'")->execute([$loanId]);
        $success = "Penalty waived for Loan #$loanId.";
    }

    // ── Filters ───────────────────────────────────────────────────────────
    $filterEmail  = trim($_GET['email']  ?? '');
    $filterStatus = trim($_GET['status'] ?? '');
    $page         = max(1, (int)($_GET['page'] ?? 1));
    $perPage      = 20;
    $offset       = ($page - 1) * $perPage;

    $where  = ['lpen.status != "Settled"'];
    $params = [];

    if ($filterEmail) {
        $where[] = "lpen.user_email LIKE :email";
        $params[':email'] = '%' . $filterEmail . '%';
    }
    if ($filterStatus) {
        $where[] = "lpen.status = :status";
        $params[':status'] = $filterStatus;
    }

    $whereSQL = 'WHERE ' . implode(' AND ', $where);

    $countStmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM loan_penalties lpen $whereSQL");
    $countStmt->execute($params);
    $totalRows  = (int)$countStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($totalRows / $perPage));

    $dataStmt = $pdo->prepare("
        SELECT
            lpen.*,
            la.loan_amount,
            la.next_payment_due,
            COALESCE(lt.name, CONCAT('Loan #', la.id)) AS loan_type,
            COALESCE(u.full_name, lb.full_name, lpen.user_email) AS borrower_name,
            u.contact_number
        FROM   loan_penalties lpen
        JOIN   loan_applications la ON la.id = lpen.loan_application_id
        LEFT JOIN loan_types lt     ON lt.id = la.loan_type_id
        LEFT JOIN users u           ON u.user_email = lpen.user_email
        LEFT JOIN loan_borrowers lb ON lb.loan_application_id = la.id
        $whereSQL
        ORDER  BY lpen.months_overdue DESC, lpen.penalty_amount DESC
        LIMIT  :lim OFFSET :off
    ");
    foreach ($params as $k => $v) $dataStmt->bindValue($k, $v);
    $dataStmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
    $dataStmt->bindValue(':off', $offset,  PDO::PARAM_INT);
    $dataStmt->execute();
    $penalties = $dataStmt->fetchAll();

    // ── Summary stats ──────────────────────────────────────────────────────
    $stats = $pdo->query("
        SELECT
            COUNT(*) AS total_overdue,
            COALESCE(SUM(penalty_amount), 0) AS total_penalty_amount,
            COALESCE(SUM(total_balance_with_penalty), 0) AS total_balance_due,
            SUM(months_overdue >= 3) AS critical_count,
            SUM(status = 'Waived') AS waived_count
        FROM loan_penalties
        WHERE status != 'Settled'
    ")->fetch();

} catch (PDOException $e) {
    $error = "Database error: " . htmlspecialchars($e->getMessage());
    $penalties = [];
    $stats = ['total_overdue'=>0,'total_penalty_amount'=>0,'total_balance_due'=>0,'critical_count'=>0,'waived_count'=>0];
    $totalRows = 0; $totalPages = 1;
}

// ── Email body builder ────────────────────────────────────────────────────────
function getPenaltyEmailBody(string $name, int $loanId, string $loanType, float $originalBal, float $penaltyAmt, float $totalDue, int $monthsOverdue, float $ratePercent, ?string $dueDate): string {
    $orig    = '₱' . number_format($originalBal, 2);
    $pen     = '₱' . number_format($penaltyAmt, 2);
    $total   = '₱' . number_format($totalDue, 2);
    $due     = $dueDate ? date('F d, Y', strtotime($dueDate)) : 'N/A';
    $now     = date('F d, Y');
    $loanRef = 'LOAN-' . str_pad($loanId, 6, '0', STR_PAD_LEFT);
    $rateStr = number_format($ratePercent, 0) . '%';

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"/></head>
<body style="margin:0;padding:0;background:#f4f4f4;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;padding:40px 0;">
    <tr><td align="center">
      <table width="560" cellpadding="0" cellspacing="0"
             style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.12);">
        <!-- HEADER -->
        <tr>
          <td style="background:linear-gradient(135deg,#0a3b2f 0%,#1a6b55 100%);padding:30px 36px;text-align:center;">
            <h1 style="color:#fff;margin:0;font-size:22px;letter-spacing:1px;">🌿 EVERGREEN</h1>
            <p style="color:#a8d5b5;margin:6px 0 0;font-size:13px;">Trust and Savings Bank</p>
          </td>
        </tr>
        <!-- ALERT BANNER -->
        <tr>
          <td style="background:#fff3cd;border-bottom:3px solid #ffc107;padding:16px 36px;text-align:center;">
            <p style="margin:0;color:#856404;font-size:15px;font-weight:700;">⚠️ OVERDUE LOAN PAYMENT NOTICE</p>
          </td>
        </tr>
        <!-- BODY -->
        <tr>
          <td style="padding:32px 36px;">
            <p style="color:#2d4a3e;font-size:15px;margin:0 0 16px;">Dear <strong>{$name}</strong>,</p>
            <p style="color:#555;font-size:14px;line-height:1.7;margin:0 0 24px;">
              This is an official notice that your loan payment is <strong style="color:#c0392b;">{$monthsOverdue} month(s) overdue</strong>.
              A monthly penalty of <strong>{$rateStr}</strong> per month has been applied to your outstanding balance.
              Please settle your account immediately to prevent further charges.
            </p>

            <!-- Loan Details Table -->
            <table width="100%" cellpadding="0" cellspacing="0"
                   style="border:1.5px solid #dceee8;border-radius:10px;overflow:hidden;margin-bottom:24px;">
              <tr style="background:#f0faf6;">
                <td colspan="2" style="padding:12px 18px;font-size:12px;font-weight:700;color:#0a3b2f;text-transform:uppercase;letter-spacing:.8px;">
                  Loan Summary
                </td>
              </tr>
              <tr style="border-top:1px solid #dceee8;">
                <td style="padding:11px 18px;font-size:13px;color:#666;width:50%;">Reference No.</td>
                <td style="padding:11px 18px;font-size:13px;font-weight:700;color:#0a3b2f;">{$loanRef}</td>
              </tr>
              <tr style="border-top:1px solid #dceee8;background:#fafafa;">
                <td style="padding:11px 18px;font-size:13px;color:#666;">Loan Type</td>
                <td style="padding:11px 18px;font-size:13px;font-weight:600;color:#333;">{$loanType}</td>
              </tr>
              <tr style="border-top:1px solid #dceee8;">
                <td style="padding:11px 18px;font-size:13px;color:#666;">Original Due Date</td>
                <td style="padding:11px 18px;font-size:13px;font-weight:600;color:#c0392b;">{$due}</td>
              </tr>
              <tr style="border-top:1px solid #dceee8;background:#fafafa;">
                <td style="padding:11px 18px;font-size:13px;color:#666;">Outstanding Balance</td>
                <td style="padding:11px 18px;font-size:13px;font-weight:700;color:#333;">{$orig}</td>
              </tr>
              <tr style="border-top:1px solid #dceee8;">
                <td style="padding:11px 18px;font-size:13px;color:#666;">Months Overdue</td>
                <td style="padding:11px 18px;font-size:13px;font-weight:700;color:#c0392b;">{$monthsOverdue} month(s)</td>
              </tr>
              <tr style="border-top:1px solid #dceee8;background:#fafafa;">
                <td style="padding:11px 18px;font-size:13px;color:#666;">Penalty Rate</td>
                <td style="padding:11px 18px;font-size:13px;font-weight:600;color:#e67e22;">{$rateStr} / month (compounded)</td>
              </tr>
              <tr style="border-top:1px solid #dceee8;">
                <td style="padding:11px 18px;font-size:13px;color:#666;">Penalty Charged</td>
                <td style="padding:11px 18px;font-size:13px;font-weight:700;color:#c0392b;">{$pen}</td>
              </tr>
              <tr style="border-top:2px solid #0a3b2f;background:#fff3cd;">
                <td style="padding:13px 18px;font-size:14px;font-weight:700;color:#0a3b2f;">TOTAL AMOUNT DUE</td>
                <td style="padding:13px 18px;font-size:16px;font-weight:800;color:#0a3b2f;">{$total}</td>
              </tr>
            </table>

            <p style="color:#555;font-size:13px;line-height:1.7;margin:0 0 20px;">
              To avoid further accumulation of penalties, please log in to your Evergreen dashboard and make a payment immediately.
              If you are experiencing financial difficulties, please contact us to discuss a payment arrangement.
            </p>

            <div style="background:#fef9ec;border-left:4px solid #f39c12;padding:14px 18px;border-radius:8px;margin-bottom:24px;">
              <p style="margin:0;color:#7d5a00;font-size:13px;line-height:1.6;">
                <strong>⚡ Important:</strong> Penalties are compounded monthly at {$rateStr}.
                The longer the delay, the higher your total outstanding balance becomes.
                Settle now to stop the accumulation.
              </p>
            </div>

            <div style="text-align:center;margin-bottom:8px;">
              <a href="#" style="display:inline-block;background:linear-gradient(135deg,#0a3b2f,#1a6b55);color:#fff;text-decoration:none;padding:14px 36px;border-radius:8px;font-weight:700;font-size:15px;letter-spacing:.3px;">
                Pay Now →
              </a>
            </div>
          </td>
        </tr>
        <!-- FOOTER -->
        <tr>
          <td style="background:#f9f5f0;padding:20px 36px;text-align:center;border-top:1px solid #e8e0d8;">
            <p style="color:#aaa;font-size:12px;margin:0 0 4px;">This is an automated notice sent on {$now}.</p>
            <p style="color:#aaa;font-size:12px;margin:0;">&copy; 2025 Evergreen Trust and Savings &middot; All rights reserved.</p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
}

// ── Session info ──────────────────────────────────────────────────────────────
$adminName     = htmlspecialchars($_SESSION['admin_name']            ?? 'Staff User');
$adminRole     = htmlspecialchars($_SESSION['admin_role']            ?? 'SuperAdmin');
$adminEmpNum   = htmlspecialchars($_SESSION['admin_employee_number'] ?? '');
$adminInitials = implode('', array_map(
    fn($w) => strtoupper($w[0]),
    array_slice(explode(' ', strip_tags($adminName)), 0, 2)
));

$navItems = [
    ['label' => 'Dashboard',          'href' => 'Employeedashboard.php', 'icon' => 'bi-speedometer2'],
    ['label' => 'Account Management', 'href' => 'add_officer.php',       'icon' => 'bi-person-gear'],
    ['label' => 'Audit Logs',         'href' => 'audit_logs.php',        'icon' => 'bi-journal-text'],
    ['label' => 'Loan Penalties',     'href' => 'loan_penalty.php',      'icon' => 'bi-exclamation-triangle'],
    ['label' => 'Manage Payments',    'href' => 'manage_payments.php',   'icon' => 'bi-credit-card-2-front'],
];
$activeNav = 'Loan Penalties';

function pgUrl(int $pg): string {
    $p = $_GET; $p['page'] = $pg;
    return '?' . http_build_query($p);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Evergreen – Loan Penalties</title>
  <link rel="icon" type="image/png" href="pictures/logo.png"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css"/>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>

  <style>
    :root {
      --eg-forest:    #0a3b2f;
      --eg-deep:      #062620;
      --eg-mid:       #1a6b55;
      --eg-light:     #e8f4ef;
      --eg-cream:     #f7f3ee;
      --eg-gold:      #c9a84c;
      --eg-gold-l:    #e8c96b;
      --eg-text:      #1c2b25;
      --eg-muted:     #6b8c7e;
      --eg-border:    #d4e6de;
      --eg-bg:        #f4f8f6;
      --eg-card:      #ffffff;
      --eg-sidebar-w: 262px;
      --eg-topbar-h:  62px;
      --eg-danger:    #c0392b;
      --eg-warn:      #e67e22;
    }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'DM Sans', sans-serif; background: var(--eg-bg); color: var(--eg-text); min-height: 100vh; }

    /* ══ SIDEBAR ══ */
    .eg-sidebar { position: fixed; top: 0; left: 0; width: var(--eg-sidebar-w); height: 100vh; background: linear-gradient(180deg, var(--eg-deep) 0%, var(--eg-forest) 60%, #0e4535 100%); z-index: 1040; display: flex; flex-direction: column; transform: translateX(-100%); transition: transform 0.28s cubic-bezier(.4,0,.2,1); box-shadow: 4px 0 28px rgba(6,38,32,0.35); }
    .eg-sidebar.open { transform: translateX(0); }
    @media (min-width: 992px) { .eg-sidebar { transform: translateX(0); } .eg-main { margin-left: var(--eg-sidebar-w); } }
    .eg-sidebar-logo { display: flex; align-items: center; gap: 10px; padding: 20px 22px 16px; border-bottom: 1px solid rgba(255,255,255,0.08); text-decoration: none; }
    .eg-sidebar-logo-icon { width: 36px; height: 36px; background: var(--eg-gold); border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .eg-sidebar-logo-icon img { height: 22px; width: auto; filter: brightness(0) saturate(100%) invert(10%) sepia(40%) saturate(800%) hue-rotate(105deg) brightness(40%); }
    .eg-sidebar-logo-text { font-family: 'Playfair Display', serif; font-size: 17px; font-weight: 700; color: #fff; letter-spacing: .8px; line-height: 1.1; }
    .eg-sidebar-logo-sub  { font-size: 10px; color: rgba(255,255,255,0.45); letter-spacing: .3px; }
    .eg-nav-toggle-btn { display: flex; align-items: center; gap: 8px; width: 100%; background: none; border: none; color: rgba(255,255,255,0.50); padding: 12px 22px; font-size: 10.5px; font-weight: 700; letter-spacing: 1.5px; cursor: pointer; transition: color .2s; text-transform: uppercase; font-family: 'DM Sans', sans-serif; }
    .eg-nav-toggle-btn:hover { color: var(--eg-gold-l); }
    .eg-nav-toggle-btn .chevron { margin-left: auto; transition: transform .25s; }
    .eg-nav-collapse { overflow: hidden; max-height: 600px; transition: max-height .3s ease; }
    .eg-nav-collapse.hidden { max-height: 0; }
    .eg-nav-item { display: flex; align-items: center; gap: 10px; padding: 11px 22px 11px 30px; color: rgba(255,255,255,0.60); text-decoration: none; font-size: 14px; font-weight: 500; transition: background .18s, color .18s; border-left: 3px solid transparent; font-family: 'DM Sans', sans-serif; }
    .eg-nav-item:hover  { background: rgba(255,255,255,0.07); color: #fff; }
    .eg-nav-item.active { color: var(--eg-gold-l); border-left-color: var(--eg-gold); background: rgba(201,168,76,0.10); }
    .eg-nav-item i { font-size: 16px; width: 20px; text-align: center; }
    .eg-sidebar-footer { margin-top: auto; border-top: 1px solid rgba(255,255,255,0.08); padding: 16px 22px; }
    .eg-sidebar-footer-user { display: flex; align-items: center; gap: 10px; }
    .eg-sidebar-avatar { width: 34px; height: 34px; border-radius: 50%; background: var(--eg-gold); display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; color: var(--eg-deep); flex-shrink: 0; }
    .eg-sidebar-uname { font-size: 13px; font-weight: 600; color: #fff; line-height: 1.2; }
    .eg-sidebar-urole  { font-size: 11px; color: var(--eg-gold-l); }
    .eg-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.50); z-index: 1039; }
    .eg-overlay.show { display: block; }
    @media (min-width: 992px) { .eg-overlay { display: none !important; } }

    /* ══ TOP BAR ══ */
    .eg-topbar { position: sticky; top: 0; height: var(--eg-topbar-h); background: linear-gradient(90deg, var(--eg-deep) 0%, var(--eg-forest) 100%); display: flex; align-items: center; justify-content: space-between; padding: 0 26px; z-index: 1030; box-shadow: 0 2px 16px rgba(6,38,32,0.28); }
    .eg-topbar-left { display: flex; align-items: center; gap: 14px; }
    .eg-hamburger { background: none; border: none; color: rgba(255,255,255,0.80); font-size: 22px; cursor: pointer; padding: 4px 8px; border-radius: 6px; transition: color .2s, background .2s; display: none; }
    @media (max-width: 991px) { .eg-hamburger { display: flex; } }
    .eg-hamburger:hover { color: var(--eg-gold-l); background: rgba(255,255,255,0.08); }
    .eg-breadcrumb { display: flex; align-items: center; gap: 6px; font-size: 13px; color: rgba(255,255,255,0.55); }
    @media (max-width: 991px) { .eg-breadcrumb { display: none; } }
    .eg-breadcrumb .bc-sep { opacity: .4; }
    .eg-breadcrumb .bc-active { color: #fff; font-weight: 600; }
    .eg-profile-wrap { position: relative; }
    .eg-profile-btn { display: flex; align-items: center; gap: 10px; background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.14); border-radius: 10px; padding: 6px 14px 6px 8px; color: #fff; cursor: pointer; transition: background .2s; font-family: 'DM Sans', sans-serif; }
    .eg-profile-btn:hover { background: rgba(255,255,255,0.15); }
    .eg-avatar { width: 32px; height: 32px; border-radius: 50%; background: var(--eg-gold); display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; color: var(--eg-deep); flex-shrink: 0; }
    .eg-profile-info { text-align: left; }
    .eg-profile-name { font-size: 13px; font-weight: 600; line-height: 1.2; }
    .eg-profile-role { font-size: 11px; color: var(--eg-gold-l); line-height: 1.2; }
    .eg-profile-dropdown { position: absolute; top: calc(100% + 8px); right: 0; background: #fff; border-radius: 12px; box-shadow: 0 8px 32px rgba(6,38,32,0.18); min-width: 190px; overflow: hidden; z-index: 2000; display: none; animation: dropIn .18s ease; border: 1px solid var(--eg-border); }
    .eg-profile-dropdown.show { display: block; }
    @keyframes dropIn { from{opacity:0;transform:translateY(-6px)} to{opacity:1;transform:translateY(0)} }
    .eg-profile-dropdown .dd-header { padding: 14px 16px 10px; border-bottom: 1px solid var(--eg-border); }
    .eg-profile-dropdown .dd-header .dd-name { font-size: 13.5px; font-weight: 700; color: var(--eg-text); }
    .eg-profile-dropdown .dd-header .dd-empnum { font-size: 11px; color: var(--eg-muted); }
    .eg-profile-dropdown a { display: flex; align-items: center; gap: 8px; padding: 10px 16px; color: var(--eg-text); text-decoration: none; font-size: 13.5px; transition: background .15s; }
    .eg-profile-dropdown a:hover { background: var(--eg-bg); }
    .eg-profile-dropdown a i { width: 18px; color: var(--eg-muted); }
    .eg-profile-dropdown .divider { height: 1px; background: var(--eg-border); margin: 4px 0; }
    .eg-profile-dropdown a.logout-link { color: #c0392b; }

    /* ══ MAIN ══ */
    .eg-main { min-height: 100vh; transition: margin-left .28s; }
    .eg-content { padding: 30px 30px 56px; }
    .eg-page-header { margin-bottom: 28px; }
    .eg-page-title { font-family: 'Playfair Display', serif; font-size: 28px; font-weight: 700; color: var(--eg-forest); letter-spacing: -.2px; }
    .eg-page-sub   { font-size: 13.5px; color: var(--eg-muted); margin-top: 3px; }

    /* ══ STAT CARDS ══ */
    .eg-stat-card { background: var(--eg-card); border-radius: 16px; padding: 22px 24px; box-shadow: 0 1px 6px rgba(10,59,47,0.06); border: 1.5px solid var(--eg-border); height: 100%; position: relative; overflow: hidden; transition: transform .2s; }
    .eg-stat-card::before { content: ''; position: absolute; width: 80px; height: 80px; border-radius: 50%; background: var(--eg-light); opacity: .6; top: -20px; right: -20px; }
    .eg-stat-card:hover { transform: translateY(-2px); }
    .eg-stat-card.danger-card { background: linear-gradient(135deg,#fff5f4 0%,#fff0ef 100%); border-color: rgba(192,57,43,0.30); }
    .eg-stat-card.danger-card::before { background: rgba(192,57,43,0.08); opacity:1; }
    .eg-stat-card.danger-card .eg-stat-num { color: var(--eg-danger); }
    .eg-stat-card.warn-card  { background: linear-gradient(135deg,#fefaf3 0%,#fff8ed 100%); border-color: rgba(230,126,34,0.30); }
    .eg-stat-card.warn-card::before  { background: rgba(230,126,34,0.08); opacity:1; }
    .eg-stat-card.warn-card  .eg-stat-num { color: var(--eg-warn); }
    .eg-stat-card.forest-card { background: linear-gradient(135deg,var(--eg-forest) 0%,var(--eg-mid) 100%); border-color: transparent; }
    .eg-stat-card.forest-card::before { background: rgba(255,255,255,0.08); opacity:1; }
    .eg-stat-card.forest-card .eg-stat-num, .eg-stat-card.forest-card .eg-stat-label, .eg-stat-card.forest-card .eg-stat-sub { color: #fff; }
    .eg-stat-card.forest-card .eg-stat-num { color: var(--eg-gold-l); }
    .eg-stat-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; background: var(--eg-light); margin-bottom: 14px; position: relative; z-index: 1; }
    .eg-stat-icon i { font-size: 18px; color: var(--eg-forest); }
    .eg-stat-card.danger-card .eg-stat-icon { background: rgba(192,57,43,0.12); }
    .eg-stat-card.danger-card .eg-stat-icon i { color: var(--eg-danger); }
    .eg-stat-card.warn-card  .eg-stat-icon { background: rgba(230,126,34,0.12); }
    .eg-stat-card.warn-card  .eg-stat-icon i { color: var(--eg-warn); }
    .eg-stat-card.forest-card .eg-stat-icon { background: rgba(255,255,255,0.15); }
    .eg-stat-card.forest-card .eg-stat-icon i { color: var(--eg-gold-l); }
    .eg-stat-label { font-size: 11.5px; color: var(--eg-muted); font-weight: 600; text-transform: uppercase; letter-spacing: .6px; margin-bottom: 6px; position: relative; z-index: 1; }
    .eg-stat-num   { font-size: 30px; font-weight: 800; color: var(--eg-forest); line-height: 1; margin-bottom: 4px; position: relative; z-index: 1; }
    .eg-stat-num.sm { font-size: 20px; }
    .eg-stat-sub   { font-size: 13px; color: var(--eg-muted); position: relative; z-index: 1; }

    /* ══ ALERTS ══ */
    .eg-alert-success { background: #f0faf6; border: 1px solid #a0d4b8; color: #1a6b55; border-radius: 12px; padding: 13px 18px; margin-bottom: 22px; display: flex; align-items: center; gap: 10px; font-size: 14px; }
    .eg-alert-error   { background: #fdf0ef; border: 1px solid #f5c6c3; color: #c0392b; border-radius: 12px; padding: 13px 18px; margin-bottom: 22px; display: flex; align-items: center; gap: 10px; font-size: 14px; }

    /* ══ FILTER ══ */
    .eg-filter-panel { background: var(--eg-card); border: 1.5px solid var(--eg-border); border-radius: 14px; padding: 18px 20px; margin-bottom: 24px; }
    .eg-filter-input, .eg-filter-select { width: 100%; padding: 9px 12px; border: 1.5px solid var(--eg-border); border-radius: 9px; font-family: 'DM Sans', sans-serif; font-size: 13.5px; color: var(--eg-text); background: var(--eg-bg); outline: none; transition: border-color .2s; }
    .eg-filter-input:focus, .eg-filter-select:focus { border-color: var(--eg-forest); background: white; }
    .filter-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .7px; color: var(--eg-muted); margin-bottom: 5px; display: block; }
    .btn-filter { display: inline-flex; align-items: center; gap: 7px; background: linear-gradient(135deg,var(--eg-forest),var(--eg-mid)); color: #fff; border: none; border-radius: 9px; padding: 10px 20px; font-size: 13.5px; font-weight: 600; cursor: pointer; font-family: 'DM Sans',sans-serif; transition: all .2s; }
    .btn-filter:hover { transform: translateY(-1px); box-shadow: 0 4px 14px rgba(10,59,47,0.22); }
    .btn-clear { display: inline-flex; align-items: center; gap: 6px; background: #f0f0f0; color: #555; border: none; border-radius: 9px; padding: 10px 16px; font-size: 13.5px; font-weight: 600; cursor: pointer; font-family: 'DM Sans',sans-serif; text-decoration: none; transition: background .15s; }
    .btn-clear:hover { background: #e0e0e0; }

    /* ══ TABLE ══ */
    .eg-table-card { background: var(--eg-card); border-radius: 16px; box-shadow: 0 1px 6px rgba(10,59,47,0.06); border: 1.5px solid var(--eg-border); overflow: hidden; }
    .eg-table-header { padding: 16px 22px; border-bottom: 1px solid var(--eg-border); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; }
    .eg-table-header-title { font-family: 'Playfair Display',serif; font-size: 18px; font-weight: 700; color: var(--eg-forest); }
    .eg-table { width: 100%; border-collapse: collapse; }
    .eg-table thead th { background: #f4f8f6; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .7px; color: var(--eg-muted); padding: 13px 16px; border-bottom: 1.5px solid var(--eg-border); white-space: nowrap; }
    .eg-table tbody tr { border-bottom: 1px solid #eef4f0; transition: background .15s; }
    .eg-table tbody tr:last-child { border-bottom: none; }
    .eg-table tbody tr:hover { background: #f8fcfa; }
    .eg-table tbody tr.critical { background: #fff5f4; }
    .eg-table tbody tr.critical:hover { background: #ffeeec; }
    .eg-table tbody td { padding: 13px 16px; font-size: 13.5px; color: var(--eg-text); vertical-align: middle; }

    /* Overdue badges */
    .overdue-badge { display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 20px; font-size: 11.5px; font-weight: 700; }
    .overdue-badge.low      { background: #fff8e1; color: #8a6000; border: 1px solid #ffe082; }
    .overdue-badge.medium   { background: #fff3e0; color: #e65100; border: 1px solid #ffcc80; }
    .overdue-badge.high     { background: #fce4ec; color: #880e4f; border: 1px solid #f48fb1; }
    .overdue-badge.critical { background: #ffebee; color: #b71c1c; border: 1px solid #ef9a9a; animation: blink 1.8s ease-in-out infinite; }
    @keyframes blink { 0%,100%{opacity:1} 50%{opacity:.65} }

    .penalty-amount { font-weight: 700; font-size: 14px; color: var(--eg-danger); }
    .total-amount   { font-weight: 800; font-size: 14px; color: var(--eg-forest); }

    .btn-action { display: inline-flex; align-items: center; gap: 5px; padding: 5px 12px; border-radius: 7px; font-size: 12px; font-weight: 600; cursor: pointer; border: none; font-family: 'DM Sans',sans-serif; transition: all .15s; }
    .btn-action.send  { background: #e8f4ef; color: var(--eg-forest); }
    .btn-action.send:hover  { background: var(--eg-light); }
    .btn-action.waive { background: #fff8e1; color: #8a6000; }
    .btn-action.waive:hover { background: #fff3cd; }
    .btn-action.waived-chip { background: #e8f4ef; color: var(--eg-mid); cursor: default; pointer-events: none; }

    .email-sent-chip { display: inline-flex; align-items: center; gap: 4px; background: #e8f4ef; color: var(--eg-mid); font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 6px; }

    /* Pagination */
    .pg-wrap { display: flex; align-items: center; justify-content: space-between; padding: 14px 22px; border-top: 1px solid var(--eg-border); flex-wrap: wrap; gap: 10px; }
    .pg-info  { font-size: 13px; color: var(--eg-muted); }
    .pg-btns  { display: flex; gap: 5px; }
    .pg-btn { display: inline-flex; align-items: center; justify-content: center; min-width: 34px; height: 34px; padding: 0 8px; border-radius: 8px; border: 1.5px solid var(--eg-border); background: #fff; color: var(--eg-text); font-size: 13px; font-weight: 600; text-decoration: none; transition: all .15s; }
    .pg-btn:hover   { background: var(--eg-light); border-color: var(--eg-mid); }
    .pg-btn.active  { background: var(--eg-forest); border-color: var(--eg-forest); color: #fff; }
    .pg-btn.disabled{ opacity: .4; pointer-events: none; }

    .eg-empty { text-align: center; padding: 56px 20px; color: var(--eg-muted); }
    .eg-empty i { font-size: 44px; margin-bottom: 14px; display: block; opacity: .35; }

    @media (max-width: 575px) { .eg-content { padding: 18px 14px 40px; } }
  </style>
</head>
<body>

<div class="eg-overlay" id="egOverlay" onclick="closeSidebar()"></div>

<!-- ══ SIDEBAR ══ -->
<aside class="eg-sidebar" id="egSidebar">
  <a href="Employeedashboard.php" class="eg-sidebar-logo">
    <div class="eg-sidebar-logo-icon">
      <img src="pictures/logo.png" alt="Evergreen Logo"/>
    </div>
    <div>
      <div class="eg-sidebar-logo-text">EVERGREEN</div>
      <div class="eg-sidebar-logo-sub">Trust &amp; Savings</div>
    </div>
  </a>
  <div style="padding:10px 0;flex:1;">
    <button class="eg-nav-toggle-btn" id="navToggleBtn" onclick="toggleNav()">
      <i class="bi bi-grid-fill" style="font-size:11px;"></i>
      Navigation
      <i class="bi bi-chevron-down chevron" style="font-size:10px;"></i>
    </button>
    <div class="eg-nav-collapse" id="navCollapse">
      <?php foreach ($navItems as $item): ?>
        <a href="<?= htmlspecialchars($item['href']) ?>"
           class="eg-nav-item<?= $item['label'] === $activeNav ? ' active' : '' ?>">
          <i class="bi <?= htmlspecialchars($item['icon']) ?>"></i>
          <?= htmlspecialchars($item['label']) ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="eg-sidebar-footer">
    <div class="eg-sidebar-footer-user">
      <div class="eg-sidebar-avatar"><?= htmlspecialchars($adminInitials) ?></div>
      <div>
        <div class="eg-sidebar-uname"><?= $adminName ?></div>
        <div class="eg-sidebar-urole"><?= $adminRole ?></div>
      </div>
    </div>
  </div>
</aside>

<!-- ══ MAIN ══ -->
<div class="eg-main">

  <header class="eg-topbar">
    <div class="eg-topbar-left">
      <button class="eg-hamburger" onclick="toggleSidebar()">
        <i class="bi bi-list" id="hamburgerIcon"></i>
      </button>
      <div class="eg-breadcrumb">
        <span>Staff Portal</span>
        <span class="bc-sep"><i class="bi bi-chevron-right" style="font-size:10px;"></i></span>
        <a href="Employeedashboard.php" style="color:rgba(255,255,255,0.55);text-decoration:none;">Dashboard</a>
        <span class="bc-sep"><i class="bi bi-chevron-right" style="font-size:10px;"></i></span>
        <span class="bc-active">Loan Penalties</span>
      </div>
    </div>
    <div class="eg-profile-wrap">
      <button class="eg-profile-btn" onclick="toggleProfileDropdown()">
        <div class="eg-avatar"><?= htmlspecialchars($adminInitials) ?></div>
        <div class="eg-profile-info">
          <div class="eg-profile-name"><?= $adminName ?></div>
          <div class="eg-profile-role"><?= $adminRole ?></div>
        </div>
        <i class="bi bi-chevron-down ms-1" style="font-size:11px;opacity:.7;"></i>
      </button>
      <div class="eg-profile-dropdown" id="profileDropdown">
        <div class="dd-header">
          <div class="dd-name"><?= $adminName ?></div>
          <div class="dd-empnum"><?= $adminEmpNum ?></div>
        </div>
        <div class="divider"></div>
        <a href="logout.php" class="logout-link"><i class="bi bi-box-arrow-right"></i> Sign Out</a>
      </div>
    </div>
  </header>

  <main class="eg-content">

    <div class="eg-page-header">
      <h1 class="eg-page-title"><i class="bi bi-exclamation-triangle me-2"></i>Loan Penalties</h1>
      <p class="eg-page-sub">Overdue loan monitoring — penalty computed at <?= number_format($penaltyRate * 100, 0) ?>% per month (compounded)</p>
    </div>

    <?php if ($success): ?>
      <div class="eg-alert-success"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="eg-alert-error"><i class="bi bi-exclamation-circle-fill"></i> <?= $error ?></div>
    <?php endif; ?>

    <!-- ── STAT CARDS ── -->
    <div class="row g-3 mb-4">
      <div class="col-6 col-md-3">
        <div class="eg-stat-card forest-card">
          <div class="eg-stat-icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
          <div class="eg-stat-label">Total Overdue</div>
          <div class="eg-stat-num"><?= number_format((int)$stats['total_overdue']) ?></div>
          <div class="eg-stat-sub">Active penalties</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="eg-stat-card danger-card">
          <div class="eg-stat-icon"><i class="bi bi-cash-stack"></i></div>
          <div class="eg-stat-label">Total Penalties</div>
          <div class="eg-stat-num sm">₱<?= number_format(floatval($stats['total_penalty_amount']), 2) ?></div>
          <div class="eg-stat-sub">Accumulated fees</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="eg-stat-card warn-card">
          <div class="eg-stat-icon"><i class="bi bi-wallet2"></i></div>
          <div class="eg-stat-label">Total Balance Due</div>
          <div class="eg-stat-num sm">₱<?= number_format(floatval($stats['total_balance_due']), 2) ?></div>
          <div class="eg-stat-sub">Principal + penalties</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="eg-stat-card danger-card">
          <div class="eg-stat-icon"><i class="bi bi-fire"></i></div>
          <div class="eg-stat-label">Critical (3+ mos)</div>
          <div class="eg-stat-num"><?= number_format((int)$stats['critical_count']) ?></div>
          <div class="eg-stat-sub">Urgent action needed</div>
        </div>
      </div>
    </div>

    <!-- ── FILTER ── -->
    <div class="eg-filter-panel">
      <form method="GET" action="">
        <div class="row g-3 align-items-end">
          <div class="col-12 col-md-4">
            <label class="filter-label">Borrower Email</label>
            <input type="text" name="email" class="eg-filter-input" placeholder="Search email…" value="<?= htmlspecialchars($filterEmail) ?>">
          </div>
          <div class="col-6 col-md-3">
            <label class="filter-label">Penalty Status</label>
            <select name="status" class="eg-filter-select">
              <option value="">All</option>
              <option value="Active" <?= $filterStatus==='Active'?'selected':'' ?>>Active</option>
              <option value="Waived" <?= $filterStatus==='Waived'?'selected':'' ?>>Waived</option>
            </select>
          </div>
          <div class="col-6 col-md-2 d-flex gap-2">
            <button type="submit" class="btn-filter"><i class="bi bi-search"></i> Search</button>
            <a href="loan_penalty.php" class="btn-clear"><i class="bi bi-x-lg"></i></a>
          </div>
        </div>
      </form>
    </div>

    <!-- ── TABLE ── -->
    <div class="eg-table-card">
      <div class="eg-table-header">
        <div>
          <div class="eg-table-header-title">Overdue Loan Records</div>
          <div style="font-size:12.5px;color:var(--eg-muted);margin-top:2px;"><?= number_format($totalRows) ?> record(s) found</div>
        </div>
        <div style="font-size:12.5px;color:var(--eg-muted);">Page <?= $page ?> of <?= $totalPages ?></div>
      </div>

      <?php if (empty($penalties)): ?>
        <div class="eg-empty">
          <i class="bi bi-check-circle"></i>
          <p>No overdue loans found. All accounts are current!</p>
        </div>
      <?php else: ?>
      <div style="overflow-x:auto;">
        <table class="eg-table">
          <thead>
            <tr>
              <th>Loan ID</th>
              <th>Borrower</th>
              <th>Loan Type</th>
              <th>Original Due</th>
              <th>Months Overdue</th>
              <th>Remaining Balance</th>
              <th>Penalty Fee</th>
              <th>Total Due</th>
              <th>Email Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($penalties as $p):
              $mo           = (int)$p['months_overdue'];
              $overdueClass = $mo >= 6 ? 'critical' : ($mo >= 3 ? 'high' : ($mo >= 2 ? 'medium' : 'low'));
              $rowClass     = $mo >= 3 ? 'critical' : '';
              $dueDate      = $p['next_payment_due'] ? date('M d, Y', strtotime($p['next_payment_due'])) : '—';

              $initials = '';
              foreach (array_slice(explode(' ', trim($p['borrower_name'] ?? '')), 0, 2) as $w)
                  $initials .= strtoupper($w[0] ?? '');
            ?>
            <tr class="<?= $rowClass ?>">
              <td style="font-weight:700;color:var(--eg-forest);">#<?= (int)$p['loan_application_id'] ?></td>
              <td>
                <div style="display:flex;align-items:center;gap:9px;">
                  <div style="width:30px;height:30px;border-radius:50%;background:var(--eg-light);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:var(--eg-forest);flex-shrink:0;"><?= htmlspecialchars($initials ?: '?') ?></div>
                  <div>
                    <div style="font-weight:600;font-size:13px;"><?= htmlspecialchars($p['borrower_name'] ?? '—') ?></div>
                    <div style="font-size:11px;color:var(--eg-muted);"><?= htmlspecialchars($p['user_email']) ?></div>
                  </div>
                </div>
              </td>
              <td style="font-size:13px;"><?= htmlspecialchars($p['loan_type']) ?></td>
              <td style="font-size:12.5px;color:var(--eg-danger);font-weight:600;"><?= $dueDate ?></td>
              <td>
                <span class="overdue-badge <?= $overdueClass ?>">
                  <?= $overdueClass === 'critical' ? '🔥' : ($overdueClass === 'high' ? '⚠️' : '⏰') ?>
                  <?= $mo ?> month<?= $mo !== 1 ? 's' : '' ?>
                </span>
              </td>
              <td style="font-weight:600;font-size:13.5px;">₱<?= number_format(floatval($p['original_balance']), 2) ?></td>
              <td><span class="penalty-amount">+₱<?= number_format(floatval($p['penalty_amount']), 2) ?></span>
                  <div style="font-size:10.5px;color:var(--eg-muted);"><?= number_format($p['penalty_rate'] * 100, 0) ?>%/mo compound</div>
              </td>
              <td><span class="total-amount">₱<?= number_format(floatval($p['total_balance_with_penalty']), 2) ?></span></td>
              <td>
                <?php if ($p['email_sent']): ?>
                  <span class="email-sent-chip"><i class="bi bi-check2-circle"></i> Sent</span>
                  <?php if ($p['email_sent_at']): ?>
                    <div style="font-size:10.5px;color:var(--eg-muted);margin-top:2px;"><?= date('M d', strtotime($p['email_sent_at'])) ?></div>
                  <?php endif; ?>
                <?php else: ?>
                  <span style="font-size:11.5px;color:#999;font-style:italic;">Not sent</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($p['status'] === 'Waived'): ?>
                  <span class="btn-action waived-chip"><i class="bi bi-check-circle"></i> Waived</span>
                <?php else: ?>
                  <div style="display:flex;gap:6px;flex-wrap:wrap;">
                    <form method="POST" style="display:inline;">
                      <input type="hidden" name="loan_id" value="<?= (int)$p['loan_application_id'] ?>">
                      <button type="submit" name="send_reminder" class="btn-action send">
                        <i class="bi bi-envelope"></i> Remind
                      </button>
                    </form>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Waive penalty for Loan #<?= (int)$p['loan_application_id'] ?>? This cannot be undone.');">
                      <input type="hidden" name="loan_id" value="<?= (int)$p['loan_application_id'] ?>">
                      <button type="submit" name="waive_penalty" class="btn-action waive">
                        <i class="bi bi-shield-check"></i> Waive
                      </button>
                    </form>
                  </div>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <?php if ($totalPages > 1): ?>
      <div class="pg-wrap">
        <div class="pg-info">Showing <?= number_format(($page-1)*$perPage+1) ?>–<?= number_format(min($page*$perPage,$totalRows)) ?> of <?= number_format($totalRows) ?></div>
        <div class="pg-btns">
          <a href="<?= pgUrl(1) ?>"         class="pg-btn <?= $page<=1?'disabled':'' ?>"><i class="bi bi-chevron-double-left"></i></a>
          <a href="<?= pgUrl($page-1) ?>"   class="pg-btn <?= $page<=1?'disabled':'' ?>"><i class="bi bi-chevron-left"></i></a>
          <?php for ($pg = max(1,$page-2); $pg <= min($totalPages,$page+2); $pg++): ?>
            <a href="<?= pgUrl($pg) ?>" class="pg-btn <?= $pg===$page?'active':'' ?>"><?= $pg ?></a>
          <?php endfor; ?>
          <a href="<?= pgUrl($page+1) ?>"   class="pg-btn <?= $page>=$totalPages?'disabled':'' ?>"><i class="bi bi-chevron-right"></i></a>
          <a href="<?= pgUrl($totalPages) ?>" class="pg-btn <?= $page>=$totalPages?'disabled':'' ?>"><i class="bi bi-chevron-double-right"></i></a>
        </div>
      </div>
      <?php endif; ?>
      <?php endif; ?>
    </div>

    <!-- Info box -->
    <div style="margin-top:24px;background:#fefbec;border:1px solid #f5e0a0;border-radius:12px;padding:16px 20px;font-size:13px;color:#7a5200;">
      <strong>📋 Penalty Policy:</strong> A compounded monthly penalty of <strong><?= number_format($penaltyRate*100,0) ?>%</strong>
      is automatically applied to outstanding loan balances once the <code>next_payment_due</code> date is passed.
      Penalty = Remaining Balance × ((1 + rate)^months − 1).
      Overdue borrowers are notified by email automatically. Admins can manually send reminders or waive penalties.
    </div>

  </main>
</div>

<script>
  function toggleSidebar() {
    const sidebar = document.getElementById('egSidebar');
    const overlay = document.getElementById('egOverlay');
    const icon    = document.getElementById('hamburgerIcon');
    const isOpen  = sidebar.classList.toggle('open');
    overlay.classList.toggle('show', isOpen);
    icon.className = isOpen ? 'bi bi-x-lg' : 'bi bi-list';
  }
  function closeSidebar() {
    document.getElementById('egSidebar').classList.remove('open');
    document.getElementById('egOverlay').classList.remove('show');
    document.getElementById('hamburgerIcon').className = 'bi bi-list';
  }
  function toggleNav() {
    document.getElementById('navToggleBtn').classList.toggle('collapsed');
    document.getElementById('navCollapse').classList.toggle('hidden');
  }
  function toggleProfileDropdown() {
    document.getElementById('profileDropdown').classList.toggle('show');
  }
  document.addEventListener('click', function(e) {
    const wrap = document.querySelector('.eg-profile-wrap');
    if (wrap && !wrap.contains(e.target)) {
      document.getElementById('profileDropdown').classList.remove('show');
    }
  });
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
</body>
</html>