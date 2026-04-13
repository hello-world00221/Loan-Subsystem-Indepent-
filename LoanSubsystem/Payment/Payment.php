<?php
session_start();

// ─── DB CONFIG ────────────────────────────────────────────────────────────────
$host   = "localhost";
$dbuser = "root";
$dbpass = "";
$dbname = "loandb";

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

    // Create with full schema matching loan_payments_schema.sql
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
        $pdo->exec("ALTER TABLE loan_payments
                    CHANGE COLUMN `loan_id` `loan_application_id` INT NOT NULL");
    }

    // Add any columns that might be missing from an older table version
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
        if (empty($exists)) {
            $pdo->exec($sql);
        }
    }
} catch (PDOException $e) { /* non-fatal — table already fine */ }

// ─── HANDLE PAYMENT POST ──────────────────────────────────────────────────────
$paymentResult = null;
$paymentError  = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'pay') {
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $dbuser, $dbpass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        $loan_id        = intval($_POST['loan_id']        ?? 0);
        $payment_method = $_POST['payment_method']        ?? '';
        $amount         = floatval($_POST['amount']       ?? 0);
        $user_email     = $_SESSION['user_email'];

        // Capture audit fields
        $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR']
                   ?? $_SERVER['REMOTE_ADDR']
                   ?? null;
        // Truncate to VARCHAR(45) max (handles IPv6 + port combos safely)
        $ip_address = $ip_address ? substr($ip_address, 0, 45) : null;
        $user_agent = isset($_SERVER['HTTP_USER_AGENT'])
                    ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255)
                    : null;

        if (!in_array($payment_method, ['online', 'cheque'])) {
            $paymentError = "Invalid payment method.";
        } elseif ($loan_id <= 0 || $amount <= 0) {
            $paymentError = "Invalid loan or amount.";
        } else {
            // ── Fetch loan + loan type + borrower details ──────────────────
            $stmt = $pdo->prepare("
                SELECT la.*,
                       COALESCE(lt.name, CONCAT('Loan #', la.id)) AS loan_type_label,
                       lt.code AS loan_type_code,
                       u.full_name     AS user_full_name,
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
                // ── Resolve borrower display name & account number ─────────
                // Fallback chain: users.full_name → loan_borrowers → session
                $borrowerName   = $loan['user_full_name'] ?? null;
                $accountNumber  = $loan['user_account_number'] ?? null;

                if (!$borrowerName) {
                    $lb = $pdo->prepare("SELECT full_name, account_number FROM loan_borrowers WHERE loan_application_id = ? LIMIT 1");
                    $lb->execute([$loan_id]);
                    $lbRow = $lb->fetch(PDO::FETCH_ASSOC);
                    $borrowerName  = $lbRow['full_name']      ?? ($_SESSION['user_name'] ?? $user_email);
                    $accountNumber = $accountNumber ?? ($lbRow['account_number'] ?? null);
                }

                $txn_ref = 'TXN-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 12));
                $paid_at = date('Y-m-d H:i:s.u'); // microsecond precision for DATETIME(6)

                // ── Insert payment record with ALL schema fields ────────────
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
                    $loan_id,
                    $user_email,
                    $borrowerName,
                    $accountNumber,
                    $amount,
                    $payment_method,
                    $txn_ref,
                    // processed_by: self-service so we store borrower name
                    $borrowerName,
                    $ip_address,
                    $user_agent,
                ]);

                // ── Total paid so far ──────────────────────────────────────
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
                    // ── Mark loan as Closed / Paid ─────────────────────────
                    $newStatus = 'Closed';
                    $pdo->prepare("
                        UPDATE loan_applications
                        SET    status = 'Closed',
                               next_payment_due = NULL
                        WHERE  id = ?
                    ")->execute([$loan_id]);
                } else {
                    // Advance next due date by one month
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
                    'amount_paid'    => $amount,
                    'total_paid'     => $totalPaid,
                    'loan_amount'    => $loanAmount,
                    'remaining'      => $remaining,
                    'payment_method' => $payment_method,
                    'paid_at'        => $paid_at,
                    'new_status'     => $newStatus,
                    'is_fully_paid'  => ($totalPaid >= $loanAmount),
                    'borrower_name'  => $borrowerName,
                    'account_number' => $accountNumber,
                    'ip_address'     => $ip_address,
                ];
            }
        }
    } catch (PDOException $e) {
        $paymentError = "Database error: " . $e->getMessage();
    }
}

// ─── FETCH USER INFO & LOANS ──────────────────────────────────────────────────
$loans      = [];
$userInfo   = [];
$payHistory = [];

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $dbuser, $dbpass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    // ── User info ──
    $uStmt = $pdo->prepare("
        SELECT full_name, user_email, account_number, contact_number
        FROM   users
        WHERE  user_email = ?
        LIMIT  1
    ");
    $uStmt->execute([$_SESSION['user_email']]);
    $userInfo = $uStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    // ── Active / Approved loans ──
    $lStmt = $pdo->prepare("
        SELECT la.*,
               COALESCE(lt.name, CONCAT('Loan #', la.id)) AS loan_type,
               lt.code                                      AS loan_type_code,
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

    // ── Attach payment totals ──
    foreach ($loans as &$loan) {
        $loan['total_paid'] = 0.0;
        $loan['remaining']  = floatval($loan['loan_amount'] ?? 0);
        try {
            $s = $pdo->prepare("
                SELECT COALESCE(SUM(amount), 0) AS total_paid
                FROM   loan_payments
                WHERE  loan_application_id = ? AND status = 'Completed'
            ");
            $s->execute([$loan['id']]);
            $row = $s->fetch(PDO::FETCH_ASSOC);
            if ($row !== false) {
                $loan['total_paid'] = floatval($row['total_paid']);
                $loan['remaining']  = max(0, floatval($loan['loan_amount']) - $loan['total_paid']);
            }
        } catch (PDOException $inner) { /* keep safe defaults */ }
    }
    unset($loan);

    // ── Payment history (last 20) ──
    $hStmt = $pdo->prepare("
        SELECT lp.*,
               COALESCE(lt.name, CONCAT('Loan #', lp.loan_application_id)) AS loan_type,
               lt.code AS loan_type_code
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
    .back-link { color: #a8d5c8; text-decoration: none; font-size: .85rem; display: inline-flex; align-items: center; gap: .4rem; transition: color .15s; }
    .back-link:hover { color: #fff; }

    .card-clean { background: #fff; border: 1px solid var(--eg-border); border-radius: var(--radius); box-shadow: 0 2px 12px rgba(0,54,49,.08); overflow: hidden; }
    .card-header-green { background: linear-gradient(90deg, var(--eg-dark), var(--eg-mid)); color: #fff; padding: 1.1rem 1.5rem; font-weight: 700; font-size: .95rem; display: flex; align-items: center; gap: .6rem; }
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

    .summary-row { display: flex; justify-content: space-between; align-items: center; padding: .45rem 0; font-size: .9rem; }
    .summary-row:not(:last-child) { border-bottom: 1px solid #eee; }
    .sl { color: #666; } .sv { font-weight: 600; color: var(--eg-dark); }

    .btn-pay { background: linear-gradient(135deg, var(--eg-dark), var(--eg-light)); color: #fff; border: none; padding: .85rem 2rem; border-radius: .65rem; font-weight: 700; font-size: 1rem; font-family: var(--font); width: 100%; cursor: pointer; transition: all .25s; }
    .btn-pay:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,54,49,.3); }
    .btn-pay:disabled { opacity: .6; cursor: not-allowed; transform: none; }

    .info-box { background: #fff8e6; border-left: 4px solid #f0ad00; border-radius: .5rem; padding: .9rem 1rem; font-size: .85rem; color: #6b4e00; }

    /* ── Success Overlay ── */
    .success-overlay {
      display: none;
      position: fixed; inset: 0; z-index: 9999;
      background: rgba(0,0,0,.6);
      align-items: center; justify-content: center;
    }
    .success-overlay.show { display: flex; animation: fadeIn .3s; }
    .success-card {
      background: #fff; border-radius: 1.5rem;
      padding: 2.5rem 2rem; max-width: 500px; width: 92%;
      text-align: center; animation: popIn .4s cubic-bezier(.34,1.56,.64,1);
      box-shadow: 0 16px 48px rgba(0,54,49,.18);
    }
    .success-icon { width: 80px; height: 80px; background: linear-gradient(135deg, var(--eg-accent), var(--eg-light)); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem; font-size: 2rem; color: #fff; }
    .success-card h2 { font-family: var(--serif); font-size: 1.7rem; color: var(--eg-dark); margin: 0 0 .5rem; }
    .txn-ref { background: var(--eg-pale); border: 1px solid var(--eg-border); border-radius: .5rem; padding: .5rem 1rem; font-family: 'Courier New', monospace; font-weight: 700; color: var(--eg-dark); font-size: .95rem; letter-spacing: 1px; }

    /* ── Fully Paid Banner ── */
    .fully-paid-banner {
      background: linear-gradient(135deg, #0a3b2f, #1a6b55);
      border-radius: .9rem;
      padding: 1.1rem 1.5rem;
      margin-top: .9rem;
      text-align: center;
    }
    .fully-paid-banner .fp-icon {
      width: 52px; height: 52px;
      background: rgba(255,255,255,.15);
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto .65rem;
      font-size: 1.5rem; color: #e8c96b;
    }
    .fully-paid-banner .fp-title {
      font-family: var(--serif); font-size: 1.25rem;
      color: #fff; margin-bottom: .2rem;
    }
    .fully-paid-banner .fp-sub {
      font-size: .82rem; color: rgba(255,255,255,.7);
    }
    .fp-details-grid {
      display: grid; grid-template-columns: 1fr 1fr;
      gap: .5rem; margin-top: .75rem;
    }
    .fp-detail-chip {
      background: rgba(255,255,255,.1);
      border-radius: .5rem; padding: .5rem .75rem; text-align: left;
    }
    .fp-detail-chip .fp-chip-label { font-size: .68rem; color: rgba(255,255,255,.55); text-transform: uppercase; letter-spacing: .5px; }
    .fp-detail-chip .fp-chip-val   { font-size: .88rem; font-weight: 700; color: #fff; }

    .history-table th { background: var(--eg-dark); color: #fff; font-size: .78rem; text-transform: uppercase; letter-spacing: .6px; padding: .75rem 1rem; border: none; }
    .history-table td { padding: .7rem 1rem; font-size: .87rem; vertical-align: middle; border-color: #e8f4ee; }
    .history-table tbody tr:hover td { background: var(--eg-pale); }
    .mpill { display: inline-flex; align-items: center; gap: .35rem; padding: .2rem .7rem; border-radius: 1rem; font-size: .77rem; font-weight: 600; }
    .mpill.online { background: #e8f4ff; color: #1a6fce; }
    .mpill.cheque { background: #fff5e6; color: #c47a00; }
    .spill { display: inline-block; padding: .2rem .7rem; border-radius: 1rem; font-size: .77rem; font-weight: 700; background: #d4edda; color: #1a6a2a; }

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
        <a href="http://localhost/Evergreen-loan-main/LoanSubsystem/Loan/index.php#home" class="text-white text-decoration-none" style="font-size:.9rem;">
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
        <li class="breadcrumb-item"><a href="http://localhost/Evergreen-loan-main/LoanSubsystem/Loan/index.php#home">Home</a></li>
        <li class="breadcrumb-item active">Make a Payment</li>
      </ol>
    </nav>
    <a href="http://localhost/Evergreen-loan-main/LoanSubsystem/Loan/index.php#loan-dashboard" class="back-link mb-2 d-inline-flex">
      <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>
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
  <div class="card-clean">
    <div class="card-body-pad">
      <div class="empty-state">
        <i class="fas fa-file-invoice-dollar"></i>
        <h3>No Active Loans Found</h3>
        <p style="color:#888;font-size:.9rem;">
          No active or approved loans found for<br>
          <code><?= htmlspecialchars($_SESSION['user_email']) ?></code>
        </p>
        <a href="http://localhost/Evergreen-loan-main/LoanSubsystem/Loan/index.php#loan-services" class="btn-empty">Apply for a Loan</a>
        <a href="http://localhost/Evergreen-loan-main/LoanSubsystem/Loan/index.php#loan-dashboard" class="btn-empty ms-2" style="background:#555;">View Dashboard</a>
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
            ?>
            <div class="loan-select-item <?= $i===0?'selected':'' ?>"
                 data-loan-id="<?= $loan['id'] ?>"
                 data-loan-type="<?= htmlspecialchars($loanLabel) ?>"
                 data-monthly="<?= number_format($monthly,2,'.','') ?>"
                 data-loan-amount="<?= number_format($loanAmt,2,'.','') ?>"
                 data-remaining="<?= number_format($remaining,2,'.','') ?>"
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
                      <div style="font-size:.75rem;color:#888;">Remaining</div>
                      <div style="font-weight:700;color:#c0392b;">₱<?= number_format($remaining,2) ?></div>
                    </div>
                  </div>
                  <?php if (!empty($loan['next_payment_due'])): ?>
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
                <input type="text" id="cardName" class="form-control-eg"
                       placeholder="Full name on card"
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

          <div class="amount-display mb-3">
            <div class="lbl">Amount to Pay</div>
            <div class="val" id="summaryAmount">₱0.00</div>
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
            <div class="summary-row"><span class="sl">Loan ID</span>           <span class="sv" id="sumLoanId">—</span></div>
            <div class="summary-row"><span class="sl">Loan Type</span>         <span class="sv" id="sumLoanType">—</span></div>
            <div class="summary-row"><span class="sl">Monthly Due</span>       <span class="sv" id="sumMonthly">₱0.00</span></div>
            <div class="summary-row"><span class="sl">Remaining Balance</span> <span class="sv text-danger" id="sumRemaining">₱0.00</span></div>
            <div class="summary-row"><span class="sl">Payment Method</span>    <span class="sv" id="sumMethod">Online</span></div>
          </div>

          <div class="mb-4">
            <label class="form-label-eg">Payment Amount <span style="color:#e74c3c;">*</span></label>
            <div style="position:relative;">
              <span style="position:absolute;left:.9rem;top:50%;transform:translateY(-50%);font-weight:700;color:var(--eg-dark);pointer-events:none;">₱</span>
              <input type="number" id="amtInput" class="form-control-eg" style="padding-left:2rem;"
                     placeholder="0.00" step="0.01" min="1" oninput="onAmtChange(this.value)">
            </div>
            <div style="font-size:.77rem;color:#888;margin-top:.3rem;">Pre-filled with monthly due. You may pay more.</div>
          </div>

          <form id="payForm" method="POST" action="Payment.php" onsubmit="return validatePay(event)">
            <input type="hidden" name="action"         value="pay">
            <input type="hidden" name="loan_id"        id="hidLoanId" value="<?= htmlspecialchars($loans[0]['id'] ?? '') ?>">
            <input type="hidden" name="payment_method" id="hidMethod" value="online">
            <input type="hidden" name="amount"         id="hidAmount" value="">

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

  <!-- PAYMENT HISTORY -->
  <?php if (!empty($payHistory)): ?>
  <div class="card-clean mt-4">
    <div class="card-header-green"><i class="fas fa-history"></i> Payment History</div>
    <div style="overflow-x:auto;">
      <table class="table mb-0 history-table">
        <thead>
          <tr>
            <th>Txn Reference</th><th>Loan ID</th><th>Type</th>
            <th>Amount</th><th>Method</th><th>Date</th><th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($payHistory as $ph): ?>
          <tr>
            <td><code style="font-size:.82rem;"><?= htmlspecialchars($ph['transaction_ref']) ?></code></td>
            <td>#<?= $ph['loan_application_id'] ?></td>
            <td><?= htmlspecialchars($ph['loan_type'] ?? '—') ?></td>
            <td style="font-weight:700;color:var(--eg-dark);">₱<?= number_format($ph['amount'],2) ?></td>
            <td>
              <span class="mpill <?= $ph['payment_method'] ?>">
                <i class="fas fa-<?= $ph['payment_method']==='online'?'globe':'money-check' ?>"></i>
                <?= ucfirst($ph['payment_method']) ?>
              </span>
            </td>
            <td style="font-size:.85rem;"><?= date('M d, Y H:i', strtotime($ph['payment_date'])) ?></td>
            <td><span class="spill"><?= htmlspecialchars($ph['status']) ?></span></td>
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
    <p style="color:#666;font-size:.9rem;">Your payment has been recorded.</p>
    <div class="txn-ref mb-3"><?= htmlspecialchars($paymentResult['txn_ref']) ?></div>

    <div class="mb-3 text-start" style="font-size:.88rem;">
      <div class="summary-row">
        <span class="sl">Borrower</span>
        <span class="sv"><?= htmlspecialchars($paymentResult['borrower_name'] ?? '—') ?></span>
      </div>
      <?php if (!empty($paymentResult['account_number'])): ?>
      <div class="summary-row">
        <span class="sl">Account No.</span>
        <span class="sv"><?= htmlspecialchars($paymentResult['account_number']) ?></span>
      </div>
      <?php endif; ?>
      <div class="summary-row">
        <span class="sl">Loan ID</span>
        <span class="sv">#<?= $paymentResult['loan_id'] ?></span>
      </div>
      <div class="summary-row">
        <span class="sl">Loan Type</span>
        <span class="sv"><?= htmlspecialchars($paymentResult['loan_type']) ?></span>
      </div>
      <div class="summary-row">
        <span class="sl">Amount Paid</span>
        <span class="sv" style="color:#2e7d32;">₱<?= number_format($paymentResult['amount_paid'],2) ?></span>
      </div>
      <div class="summary-row">
        <span class="sl">Total Paid to Date</span>
        <span class="sv">₱<?= number_format($paymentResult['total_paid'],2) ?></span>
      </div>
      <div class="summary-row">
        <span class="sl">Loan Amount</span>
        <span class="sv">₱<?= number_format($paymentResult['loan_amount'],2) ?></span>
      </div>
      <div class="summary-row">
        <span class="sl">Method</span>
        <span class="sv"><?= ucfirst($paymentResult['payment_method']) ?></span>
      </div>
      <?php if (!empty($paymentResult['ip_address'])): ?>
      <div class="summary-row">
        <span class="sl">IP Address</span>
        <span class="sv" style="font-family:monospace;font-size:.82rem;"><?= htmlspecialchars($paymentResult['ip_address']) ?></span>
      </div>
      <?php endif; ?>
    </div>

    <?php if ($paymentResult['is_fully_paid']): ?>
    <!-- ── Fully Paid Banner ── -->
    <div class="fully-paid-banner">
      <div class="fp-icon"><i class="fas fa-star"></i></div>
      <div class="fp-title">🎉 Loan Fully Paid!</div>
      <div class="fp-sub">This loan has been marked <strong>Closed</strong> in the system.</div>
      <div class="fp-details-grid">
        <div class="fp-detail-chip">
          <div class="fp-chip-label">New Status</div>
          <div class="fp-chip-val">Closed / Paid</div>
        </div>
        <div class="fp-detail-chip">
          <div class="fp-chip-label">Total Settled</div>
          <div class="fp-chip-val">₱<?= number_format($paymentResult['total_paid'],2) ?></div>
        </div>
        <div class="fp-detail-chip">
          <div class="fp-chip-label">Loan ID</div>
          <div class="fp-chip-val">#<?= $paymentResult['loan_id'] ?></div>
        </div>
        <div class="fp-detail-chip">
          <div class="fp-chip-label">Remaining</div>
          <div class="fp-chip-val">₱0.00</div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <div class="d-flex gap-2 mt-4 flex-wrap justify-content-center">
      <button class="btn-pay"
              style="width:auto;padding:.65rem 1.5rem;"
              onclick="document.getElementById('successOverlay').classList.remove('show')">
        <i class="fas fa-redo me-1"></i> Another Payment
      </button>
      <a href="http://localhost/Evergreen-loan-main/LoanSubsystem/Loan/index.php#loan-dashboard"
         class="btn-pay text-decoration-none"
         style="width:auto;padding:.65rem 1.5rem;background:linear-gradient(135deg,#555,#333);">
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
const loansData = <?= json_encode(array_values(array_map(function($l) {
  return [
    'id'        => (int)$l['id'],
    'loan_type' => $l['loan_type'] ?? ('Loan #' . $l['id']),
    'monthly'   => number_format(floatval($l['monthly_payment'] ?? 0), 2, '.', ''),
    'remaining' => number_format(floatval($l['remaining']), 2, '.', ''),
  ];
}, $loans))) ?>;

let selLoanId  = loansData[0]?.id        ?? null;
let selMonthly = loansData[0]?.monthly   ?? '0.00';
let selRemain  = loansData[0]?.remaining ?? '0.00';
let selType    = loansData[0]?.loan_type ?? '';
let selMethod  = 'online';

document.addEventListener('DOMContentLoaded', () => {
  refreshSummary();
  setAmt(selMonthly);
});

function selectLoan(el) {
  document.querySelectorAll('.loan-select-item').forEach(x => x.classList.remove('selected'));
  el.classList.add('selected');
  selLoanId  = el.dataset.loanId;
  selMonthly = el.dataset.monthly;
  selRemain  = el.dataset.remaining;
  selType    = el.dataset.loanType;
  document.getElementById('hidLoanId').value = selLoanId;
  setAmt(selMonthly);
  refreshSummary();
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
}

function onAmtChange(v) {
  const n = parseFloat(v) || 0;
  document.getElementById('summaryAmount').textContent = '₱' + n.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});
  document.getElementById('hidAmount').value           = n.toFixed(2);
  document.getElementById('cpAmt').textContent         = '₱' + n.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});
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
  const btn = document.getElementById('payBtn');
  btn.disabled  = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Processing…';
  return true;
}
</script>
</body>
</html>