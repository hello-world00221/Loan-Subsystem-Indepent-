<?php
session_start();

// ─── SESSION BRIDGE ───────────────────────────────────────────────────────────
if (!isset($_SESSION['user_email'])) {
    if (isset($_SESSION['user_id']) && isset($_SESSION['email'])) {

        $host   = "localhost";
        $dbuser = "root";
        $dbpass = "";
        $dbname = "loandb";

        try {
            $pdo = new PDO(
                "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
                $dbuser, $dbpass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            $stmt = $pdo->prepare(
                "SELECT id, full_name, user_email, role FROM users WHERE id = ? AND user_email = ? LIMIT 1"
            );
            $stmt->execute([$_SESSION['user_id'], $_SESSION['email']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $_SESSION['user_email'] = $user['user_email'];
                $_SESSION['user_name']  = $user['full_name'];
                $_SESSION['user_role']  = strtolower($user['role']);
            } else {
                session_destroy();
                header('Location: login.php');
                exit();
            }

        } catch (PDOException $e) {
            session_destroy();
            header('Location: login.php?error=db');
            exit();
        }

    } else {
        header('Location: login.php');
        exit();
    }
}

// ─── CONNECT TO loandb ────────────────────────────────────────────────────────
$host   = "localhost";
$dbuser = "root";
$dbpass = "";
$dbname = "loandb";

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $dbuser, $dbpass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("Database connection failed. Please contact admin.");
}

// ─── BUILD $currentUser ───────────────────────────────────────────────────────
$currentUser = null;
try {
    $stmt = $pdo->prepare(
        "SELECT full_name, user_email, contact_number, account_number
         FROM users WHERE user_email = ? LIMIT 1"
    );
    $stmt->execute([$_SESSION['user_email']]);
    $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

if (!$currentUser) {
    $currentUser = [
        'full_name'      => $_SESSION['user_name'] ?? $_SESSION['full_name'] ?? '',
        'user_email'     => $_SESSION['user_email'],
        'contact_number' => '',
        'account_number' => '',
    ];
}

// ─── FETCH REMAINING BALANCES (mirrors Payment.php logic) ─────────────────────
$remainingBalancesMap = [];
try {
    $rbStmt = $pdo->prepare("
        SELECT
            la.id                           AS loan_id,
            la.loan_amount,
            COALESCE(SUM(lp.amount), 0)     AS total_paid
        FROM   loan_applications la
        LEFT JOIN loan_payments lp
               ON  lp.loan_application_id = la.id
               AND lp.status = 'Completed'
        WHERE  la.user_email = ?
        GROUP  BY la.id, la.loan_amount
    ");
    $rbStmt->execute([$_SESSION['user_email']]);
    foreach ($rbStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $remaining = max(0, floatval($row['loan_amount']) - floatval($row['total_paid']));
        $remainingBalancesMap[(int)$row['loan_id']] = round($remaining, 2);
    }
} catch (PDOException $e) {}

// ─── FETCH ACTIVE PENALTIES for this user ─────────────────────────────────────
// penalty_map: { loan_id => { penalty_amount, total_balance_with_penalty, months_overdue, penalty_rate } }
$penaltyMap = [];
try {
    $penStmt = $pdo->prepare("
        SELECT
            loan_application_id AS loan_id,
            penalty_amount,
            total_balance_with_penalty,
            months_overdue,
            penalty_rate,
            original_balance
        FROM loan_penalties
        WHERE user_email = ? AND status = 'Active'
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
} catch (PDOException $e) {
    // loan_penalties table might not exist yet — silently ignore
}

// ─── CHECK if ANY loan is overdue (for showing the Penalty column) ────────────
$hasAnyPenalty = !empty($penaltyMap);

// ─── DYNAMIC BASE URL ─────────────────────────────────────────────────────────
$_SCHEME      = (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) ? $_SERVER['HTTP_X_FORWARDED_PROTO'] : (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http'));
$_HOST        = $_SERVER['HTTP_HOST'];
$_BASE_URL    = $_SCHEME . '://' . $_HOST . '/Evergreen-loan-main/LoanSubsystem';
$_PAYMENT_URL = $_BASE_URL . '/Payment/Payment.php';

$profileUser = $currentUser;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Dashboard – Evergreen Trust and Savings</title>
  <link rel="icon" type="logo/png" href="pictures/logo.png" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <link rel="stylesheet" href="style.css">

  <style>
    :root {
      --eg-dark:   #003631;
      --eg-mid:    #005a4d;
      --eg-light:  #00796b;
      --eg-accent: #1db57a;
      --eg-danger: #c0392b;
      --eg-warn:   #e67e22;
    }

    body { background: #f0faf6; }

    .dash-hero {
      background: linear-gradient(135deg, var(--eg-dark) 0%, var(--eg-mid) 60%, var(--eg-light) 100%);
      padding: 7rem 1.5rem 4rem;
      color: #fff;
      position: relative;
      overflow: hidden;
    }
    .dash-hero::before {
      content: '';
      position: absolute;
      inset: 0;
      background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
    }
    .dash-hero h1 { font-size: clamp(1.6rem, 3.5vw, 2.5rem); font-weight: 800; margin-bottom: .25rem; position: relative; }
    .dash-hero p  { color: rgba(255,255,255,.75); font-size: 1rem; position: relative; }

    .dashboard-wrap { padding: 2.5rem 1.5rem 5rem; }

    /* Profile card */
    .profile-card {
      background: #fff; border: 1px solid #dceee8; border-radius: 1.25rem;
      padding: 1.75rem 1.5rem; margin-bottom: 2rem;
      display: flex; align-items: center; gap: 1.5rem; flex-wrap: wrap;
      box-shadow: 0 2px 12px rgba(0,54,49,.06);
    }
    .profile-avatar-lg {
      width: 72px; height: 72px; border-radius: 50%;
      background: var(--eg-dark); display: flex; align-items: center;
      justify-content: center; color: #fff; font-size: 1.7rem; font-weight: 800;
      flex-shrink: 0; letter-spacing: -1px;
    }
    .profile-info h2 { font-size: 1.3rem; font-weight: 700; color: var(--eg-dark); margin-bottom: .2rem; }
    .profile-info p  { font-size: .88rem; color: #666; margin: 0; }
    .profile-badges  { margin-left: auto; display: flex; gap: .6rem; flex-wrap: wrap; }
    .profile-badge {
      background: #f0faf6; border: 1px solid #c4e8da; border-radius: .6rem;
      padding: .45rem 1rem; font-size: .82rem; color: var(--eg-dark); font-weight: 600; text-align: center;
    }
    .profile-badge span { display: block; font-size: .72rem; color: #888; font-weight: 400; margin-bottom: 1px; }

    /* Stat cards */
    .stat-card { background: #fff; border: 1px solid #dceee8; border-radius: 1rem; padding: 1.4rem 1.25rem; text-align: center; box-shadow: 0 2px 10px rgba(0,54,49,.05); }
    .stat-label { font-size: .78rem; color: #6c757d; text-transform: uppercase; letter-spacing: .8px; margin-bottom: .35rem; }
    .stat-value { font-size: 2.1rem; font-weight: 800; color: var(--eg-dark); margin: 0; }
    .stat-card.stat-closed { background: linear-gradient(135deg, #f7f3e8, #fdf6d8); border-color: rgba(201,168,76,.35); }
    .stat-card.stat-closed .stat-value { color: #7a5200; }
    .stat-card.stat-closed .stat-label { color: #9a7020; }
    /* ── PENALTY stat card ── */
    .stat-card.stat-overdue { background: linear-gradient(135deg,#fff5f4,#fff0ef); border-color: rgba(192,57,43,0.30); }
    .stat-card.stat-overdue .stat-value { color: var(--eg-danger); }
    .stat-card.stat-overdue .stat-label { color: #8b1a1a; }

    /* ── Loan table ── */
    .loan-table-wrapper {
      border: 1px solid #dceee8; border-radius: 1rem; overflow: hidden;
      background: #fff; box-shadow: 0 2px 12px rgba(0,54,49,.06);
    }
    .loan-table-wrapper table { margin: 0; }
    .loan-table-wrapper thead th {
      background: var(--eg-dark); color: #fff; font-size: .78rem;
      text-transform: uppercase; letter-spacing: .6px; border: none;
      padding: .9rem 1rem; white-space: nowrap;
    }
    /* ── Penalty column header highlight ── */
    .loan-table-wrapper thead th.th-penalty {
      background: linear-gradient(135deg, #8b1a1a, #c0392b);
      animation: pulseBg 2.5s ease-in-out infinite;
    }
    @keyframes pulseBg {
      0%,100% { background: linear-gradient(135deg,#8b1a1a,#c0392b); }
      50%      { background: linear-gradient(135deg,#c0392b,#e74c3c); }
    }
    .loan-table-wrapper tbody td {
      padding: .85rem 1rem; font-size: .92rem;
      border-color: #e8f4ee; vertical-align: middle;
    }
    .loan-table-wrapper tbody tr:hover td { background: #f0faf6; }
    .loan-table-wrapper tbody tr.row-closed td { background: #f5fdf8; }
    .loan-table-wrapper tbody tr.row-closed:hover td { background: #e8f8ef; }
    /* ── Overdue row highlight ── */
    .loan-table-wrapper tbody tr.row-overdue td { background: #fff5f4; }
    .loan-table-wrapper tbody tr.row-overdue:hover td { background: #ffeeec; }

    .table-scroll-wrap {
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
      max-height: 420px;
      overflow-y: auto;
    }
    .table-scroll-wrap::-webkit-scrollbar { height: 5px; width: 5px; }
    .table-scroll-wrap::-webkit-scrollbar-track { background: #f0faf6; }
    .table-scroll-wrap::-webkit-scrollbar-thumb { background: #a8d5c2; border-radius: 4px; }
    .loan-table-wrapper table { min-width: 760px; }

    /* ── Balance cells ── */
    .balance-active { font-weight: 700; color: #b45309; }
    .balance-zero {
      display: inline-flex; align-items: center; gap: 4px;
      background: linear-gradient(90deg, #0a3b2f, #1a6b55);
      color: #e8c96b; font-size: .78rem; font-weight: 700;
      padding: 3px 9px; border-radius: .45rem; letter-spacing: .3px;
    }
    .balance-na { color: #aaa; font-size: .85rem; font-style: italic; }

    /* ── PENALTY CELLS ── */
    .balance-with-penalty {
      font-weight: 800;
      color: var(--eg-danger);
    }
    .penalty-breakdown {
      font-size: .72rem;
      color: var(--eg-danger);
      opacity: .8;
      margin-top: 2px;
    }
    .penalty-badge-inline {
      display: inline-flex; align-items: center; gap: 3px;
      background: #ffebee; color: #b71c1c;
      font-size: .68rem; font-weight: 700;
      padding: 2px 7px; border-radius: 5px;
      border: 1px solid #ef9a9a;
      margin-top: 3px;
    }
    .overdue-row-label {
      display: inline-flex; align-items: center; gap: 4px;
      background: linear-gradient(90deg,#c0392b,#e74c3c);
      color: #fff; font-size: .66rem; font-weight: 700;
      padding: 2px 7px; border-radius: 5px;
      text-transform: uppercase; letter-spacing: .3px;
    }

    /* ── Penalty-specific cell styles ── */
    .td-penalty-active {
      background: rgba(255,235,238,0.6) !important;
    }
    .td-penalty-none {
      color: #aaa; font-size: .8rem; font-style: italic; text-align: center;
    }
    .penalty-amount-cell {
      display: flex; flex-direction: column; gap: 2px;
    }
    .penalty-fee-value {
      font-weight: 800; color: #c0392b; font-size: .95rem;
    }
    .penalty-months-tag {
      display: inline-flex; align-items: center; gap: 3px;
      background: #ffebee; color: #b71c1c;
      font-size: .65rem; font-weight: 700;
      padding: 1px 6px; border-radius: 4px;
      border: 1px solid #ef9a9a;
      width: fit-content;
    }
    .penalty-rate-note {
      font-size: .65rem; color: #999; margin-top: 1px;
    }

    .loan-footer {
      background: #f8faf9; border-top: 1px solid #dceee8; padding: 1rem 1.25rem;
      display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: .75rem;
    }
    .loan-footer p { margin: 0; font-size: .9rem; color: #555; }

    .btn-payment {
      background-color: #28a745; color: white; padding: .55rem 1.6rem; border: none; border-radius: .5rem;
      font-size: .92rem; font-weight: 700; cursor: pointer; transition: background .3s ease, transform .15s;
      text-decoration: none; display: inline-block;
    }
    .btn-payment:hover { background-color: #218838; color: #fff; transform: translateY(-1px); }

    /* ── Overdue alert banner ── */
    .overdue-alert-banner {
      background: linear-gradient(135deg,#fff5f4,#ffe8e6);
      border: 1.5px solid rgba(192,57,43,0.35);
      border-radius: 1rem;
      padding: 1.1rem 1.5rem;
      margin-bottom: 1.5rem;
      display: flex;
      align-items: flex-start;
      gap: 1rem;
    }
    .overdue-alert-banner .alert-icon {
      font-size: 1.5rem;
      flex-shrink: 0;
    }
    .overdue-alert-banner h4 { color: #b71c1c; font-size: .95rem; font-weight: 700; margin: 0 0 .25rem; }
    .overdue-alert-banner p  { color: #7d1c1c; font-size: .85rem; margin: 0; line-height: 1.5; }

    /* Closed status */
    .status-closed-cell {
      display: inline-flex; align-items: center; gap: 5px;
      background: linear-gradient(90deg, #0a3b2f, #1a6b55);
      color: #e8c96b; font-size: .82rem; font-weight: 700;
      padding: 3px 10px; border-radius: .5rem;
    }

    /* Notifications panel */
    .notifications-panel { background: #fff; border: 1px solid #dceee8; border-radius: 1rem; padding: 1.5rem; height: 100%; box-shadow: 0 2px 12px rgba(0,54,49,.06); }
    .notifications-panel h2 { font-size: 1.05rem; font-weight: 700; color: var(--eg-dark); margin-bottom: .75rem; }

    .notification-btn {
      position: relative; background: var(--eg-dark); color: #fff; border: none;
      padding: .65rem 1.5rem; border-radius: .6rem; cursor: pointer; font-size: .92rem;
      margin-top: 1rem; transition: background .2s, transform .15s; font-weight: 500; width: 100%;
    }
    .notification-btn:hover { background: var(--eg-mid); transform: translateY(-2px); }
    .notification-badge {
      position: absolute; top: -8px; right: -8px; background: #ff4444; color: #fff;
      border-radius: 50%; min-width: 24px; height: 24px; display: flex; align-items: center;
      justify-content: center; font-size: 12px; font-weight: 700; border: 2px solid #fff;
      animation: pulse 2s infinite;
    }
    @keyframes pulse { 0%,100%{transform:scale(1)} 50%{transform:scale(1.1)} }

    .notification-modal { display: none; position: fixed; z-index: 1050; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,.55); animation: fadeIn .3s; }
    .notification-modal-content { background: #fefefe; margin: 3% auto; border-radius: .9rem; width: 90%; max-width: 700px; max-height: 85vh; overflow: hidden; box-shadow: 0 8px 32px rgba(0,0,0,.2); animation: slideDown .35s ease-out; }
    .notification-modal-header { background: linear-gradient(135deg, var(--eg-dark) 0%, var(--eg-mid) 100%); color: #fff; padding: 1.4rem 1.5rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 3px solid var(--eg-light); }
    .notification-modal-header h2 { margin: 0; font-size: 1.25rem; display: flex; align-items: center; gap: 10px; }
    .notification-close { color: #fff; font-size: 1.8rem; font-weight: 700; cursor: pointer; background: none; border: none; line-height: 1; padding: 0; transition: transform .2s; }
    .notification-close:hover { transform: rotate(90deg); }
    .notification-modal-body { padding: 1.5rem; max-height: 65vh; overflow-y: auto; background: #f8f9fa; }

    .notification-item { background: #fff; border-left: 5px solid var(--eg-dark); padding: 1.2rem; margin-bottom: 1rem; border-radius: .5rem; transition: all .25s; box-shadow: 0 2px 8px rgba(0,0,0,.06); line-height: 1.5; }
    .notification-item:hover { transform: translateX(6px); box-shadow: 0 4px 16px rgba(0,0,0,.1); }
    .notification-item.approved { border-left-color: #4CAF50; background: linear-gradient(to right, #e8f5e9 0%, #fff 12%); }
    .notification-item.active   { border-left-color: #2e7d32; background: linear-gradient(to right, #c8e6c9 0%, #fff 12%); }
    .notification-item.rejected { border-left-color: #f44336; background: linear-gradient(to right, #ffebee 0%, #fff 12%); }
    .notification-item.closed   { border-left-color: #0a3b2f; background: linear-gradient(to right, #d0ece0 0%, #fff 12%); }
    .notification-item.overdue  { border-left-color: #c0392b; background: linear-gradient(to right, #ffebee 0%, #fff 12%); }
    .notification-item h3 { margin: 0 0 .75rem; color: var(--eg-dark); font-size: 1.05rem; display: flex; align-items: center; gap: 8px; }
    .notification-item.approved h3 { color: #4CAF50; }
    .notification-item.active   h3 { color: #2e7d32; }
    .notification-item.rejected h3 { color: #c62828; }
    .notification-item.closed   h3 { color: #0a3b2f; }
    .notification-item.overdue  h3 { color: #c0392b; }
    .status-badge { display: inline-block; padding: 3px 10px; border-radius: 1rem; font-size: .8rem; font-weight: 700; }
    .status-badge.approved { background: #4CAF50; color: #fff; }
    .status-badge.active   { background: #2e7d32; color: #fff; }
    .status-badge.rejected { background: #f44336; color: #fff; }
    .status-badge.closed   { background: linear-gradient(90deg, #0a3b2f, #1a6b55); color: #e8c96b; }
    .status-badge.overdue  { background: #c0392b; color: #fff; }
    .notification-item p { margin: .4rem 0; color: #555; font-size: .92rem; }
    .notification-item p strong { color: var(--eg-dark); font-weight: 600; display: inline-block; min-width: 160px; }
    .notification-divider { height: 1px; background: linear-gradient(to right, transparent, #ddd, transparent); margin: .6rem 0; }
    .notification-timestamp { font-size: .82rem; color: #888; font-style: italic; margin-top: .6rem; }

    .pdf-actions { margin-top: .9rem; padding-top: .6rem; border-top: 1px solid #eee; display: flex; gap: .5rem; flex-wrap: wrap; }
    .download-btn, .generate-pdf-btn { padding: .45rem .9rem; border-radius: .4rem; font-size: .85rem; transition: all .2s; white-space: nowrap; cursor: pointer; }
    .download-btn { background: #007bff; color: #fff; text-decoration: none; display: inline-block; border: none; }
    .download-btn:hover { background: #0056b3; transform: translateY(-1px); }
    .generate-pdf-btn { background: #6c757d; color: #fff; border: none; }
    .generate-pdf-btn:hover { background: #545b62; transform: translateY(-1px); }

    @keyframes fadeIn    { from{opacity:0}  to{opacity:1} }
    @keyframes slideDown { from{transform:translateY(-80px);opacity:0} to{transform:translateY(0);opacity:1} }

    footer { background: var(--eg-dark); color: #cde8e1; padding: 2rem 1.5rem 1rem; }
    .footer-logo { width: 90px; margin-bottom: .75rem; }
    .footer-tagline { font-size: .87rem; color: #9abfba; line-height: 1.6; }
    .footer-col h3 { color: #fff; font-size: .92rem; font-weight: 700; text-transform: uppercase; letter-spacing: .8px; margin-bottom: .75rem; }
    .footer-col a  { color: #9abfba; text-decoration: none; font-size: .87rem; display: block; margin-bottom: .4rem; transition: color .15s; }
    .footer-col a:hover { color: #fff; }
    .social-links a { display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px; border-radius: 50%; background: rgba(255,255,255,.1); color: #fff; margin-right: .4rem; font-size: .85rem; transition: background .2s; }
    .social-links a:hover { background: rgba(255,255,255,.25); }
    .footer-divider { border-color: rgba(255,255,255,.1); margin: 1.5rem 0; }
    .footer-bottom { font-size: .8rem; color: #7aada6; }
    .footer-bottom a { color: #9abfba; text-decoration: none; }
    .footer-bottom a:hover { color: #fff; }

    @media (max-width: 768px) {
      .profile-badges { margin-left: 0; }
      .loan-footer { flex-direction: column; align-items: flex-start; }
      .btn-payment  { width: 100%; text-align: center; }
      .loan-table-wrapper thead th,
      .loan-table-wrapper tbody td { font-size: .78rem; padding: .65rem .6rem; }
    }
    @media (max-width: 480px) {
      .stat-value { font-size: 1.6rem; }
      .stat-label { font-size: .7rem; }
    }
  </style>
</head>
<body>

<?php include 'header.php'; ?>

<!-- PAGE HERO -->
<div class="dash-hero">
  <div class="container">
    <h1><i class="fas fa-tachometer-alt me-2"></i>My Dashboard</h1>
    <p>Overview of your account, loans, and notifications.</p>
  </div>
</div>

<!-- DASHBOARD CONTENT -->
<div class="dashboard-wrap">
  <div class="container">

    <!-- User Profile Card -->
    <div class="profile-card">
      <div class="profile-avatar-lg" id="profileAvatarLg">
        <?php
          $initials = 'U';
          $fullName = $profileUser['full_name'] ?? ($_SESSION['user_name'] ?? 'User');
          if (!empty($fullName)) {
              $parts    = explode(' ', trim($fullName));
              $first    = strtoupper($parts[0][0] ?? 'U');
              $last     = strtoupper(end($parts)[0] ?? '');
              $initials = $first . $last;
          }
          echo htmlspecialchars($initials);
        ?>
      </div>
      <div class="profile-info">
        <h2><?= htmlspecialchars($profileUser['full_name'] ?? $_SESSION['user_name'] ?? 'User') ?></h2>
        <p><i class="fas fa-envelope me-1"></i><?= htmlspecialchars($profileUser['user_email'] ?? $_SESSION['user_email'] ?? '') ?></p>
        <?php if (!empty($profileUser['phone'])): ?>
          <p><i class="fas fa-phone me-1"></i><?= htmlspecialchars($profileUser['phone']) ?></p>
        <?php endif; ?>
      </div>
      <div class="profile-badges">
        <?php if (!empty($profileUser['account_number'])): ?>
          <div class="profile-badge"><span>Account No.</span><?= htmlspecialchars($profileUser['account_number']) ?></div>
        <?php endif; ?>
        <?php if (!empty($profileUser['account_type'])): ?>
          <div class="profile-badge"><span>Account Type</span><?= htmlspecialchars($profileUser['account_type']) ?></div>
        <?php endif; ?>
        <div class="profile-badge">
          <span>Member Since</span>
          <?= !empty($profileUser['created_at']) ? date('M Y', strtotime($profileUser['created_at'])) : date('M Y') ?>
        </div>
      </div>
    </div>

    <!-- ── Overdue alert banner (shown only if user has active penalties) ── -->
    <?php if (!empty($penaltyMap)): ?>
    <div class="overdue-alert-banner" id="overdueBanner">
      <div class="alert-icon">⚠️</div>
      <div>
        <h4>Overdue Payment — Penalty Applied</h4>
        <p>
          You have <strong><?= count($penaltyMap) ?></strong> overdue loan<?= count($penaltyMap) > 1 ? 's' : '' ?> with
          active late payment penalties. Your outstanding balance has increased.
          Please <a href="<?= htmlspecialchars($_PAYMENT_URL) ?>" style="color:#b71c1c;font-weight:700;">make a payment now</a>
          to stop further penalty accumulation.
        </p>
      </div>
    </div>
    <?php endif; ?>

    <!-- Stats row -->
    <div class="row g-3 mb-4">
      <div class="col-3">
        <div class="stat-card">
          <p class="stat-label">Active Loans</p>
          <p class="stat-value" id="activeLoansCount">0</p>
        </div>
      </div>
      <div class="col-3">
        <div class="stat-card">
          <p class="stat-label">Pending</p>
          <p class="stat-value" id="pendingLoansCount">0</p>
        </div>
      </div>
      <div class="col-3">
        <div class="stat-card" id="statClosedCard">
          <p class="stat-label">Fully Paid</p>
          <p class="stat-value" id="closedLoansCount">0</p>
        </div>
      </div>
      <!-- ── Overdue count stat ── -->
      <div class="col-3">
        <div class="stat-card" id="statOverdueCard">
          <p class="stat-label">Overdue</p>
          <p class="stat-value" id="overdueLoansCount">0</p>
        </div>
      </div>
    </div>

    <!-- Loan Table + Notifications -->
    <div class="row g-4">
      <div class="col-lg-8">
        <div class="loan-table-wrapper">
          <div class="table-scroll-wrap">
            <table class="table table-hover mb-0" id="loanTable">
              <thead>
                <tr id="loanTableHead">
                  <th>Loan ID</th>
                  <th>Type</th>
                  <th>Amount</th>
                  <th>Monthly</th>
                  <th>Outstanding</th>
                  <!-- ── Penalty column: only injected by JS when there are overdue loans ── -->
                  <th>Status</th>
                  <th>Next Due / Info</th>
                </tr>
              </thead>
              <tbody id="loanTableBody">
                <tr><td colspan="7" class="text-center py-4">Loading…</td></tr>
              </tbody>
            </table>
          </div>
          <div class="loan-footer">
            <p>Next payment due: <strong id="nextPaymentDate">—</strong></p>
            <a href="<?= htmlspecialchars($_PAYMENT_URL) ?>" class="btn-payment">
              <i class="fas fa-credit-card me-1"></i> Make a Payment
            </a>
          </div>
        </div>
      </div>

      <!-- Notifications -->
      <div class="col-lg-4">
        <div class="notifications-panel">
          <h2><i class="fas fa-bell me-2"></i>Notifications</h2>
          <p id="notificationMessage" class="text-muted" style="font-size:.92rem;">No new notifications.</p>
          <button class="notification-btn" id="viewNotificationsBtn" onclick="openNotificationModal()">
            <i class="fas fa-bell me-2"></i>View All Notifications
            <span class="notification-badge" id="notificationBadge" style="display:none;">0</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- NOTIFICATION MODAL -->
<div id="notificationModal" class="notification-modal">
  <div class="notification-modal-content">
    <div class="notification-modal-header">
      <h2><i class="fas fa-bell"></i> Your Notifications</h2>
      <button class="notification-close" onclick="closeNotificationModal()">&times;</button>
    </div>
    <div class="notification-modal-body" id="notificationModalBody">
      <div style="text-align:center;padding:3rem 1rem;color:#999;">No notifications yet.</div>
    </div>
  </div>
</div>

<!-- FOOTER -->
<footer>
  <div class="container">
    <div class="row g-4 mb-4">
      <div class="col-lg-4 col-md-6">
        <img src="pictures/logo.png" alt="Evergreen Bank" class="footer-logo">
        <p class="footer-tagline">Secure. Invest. Achieve. Your trusted financial partner for a prosperous future.</p>
        <div class="social-links mt-3">
          <a href="#"><i class="fab fa-facebook-f"></i></a>
          <a href="#"><i class="fab fa-twitter"></i></a>
          <a href="#"><i class="fab fa-instagram"></i></a>
          <a href="#"><i class="fab fa-linkedin-in"></i></a>
        </div>
      </div>
      <div class="col-lg-2 col-md-6 footer-col">
        <h3>Products</h3>
        <a href="#">Credit Cards</a>
        <a href="#">Debit Cards</a>
        <a href="#">Prepaid Cards</a>
      </div>
      <div class="col-lg-3 col-md-6 footer-col">
        <h3>Services</h3>
        <a href="#">Home Loans</a>
        <a href="#">Personal Loans</a>
        <a href="#">Auto Loans</a>
        <a href="#">Multipurpose Loans</a>
      </div>
      <div class="col-lg-3 col-md-6 footer-col">
        <h3>Contact Us</h3>
        <p style="font-size:.87rem; color:#9abfba;"><i class="fas fa-phone-alt me-2"></i>1-800-EVERGREEN</p>
        <p style="font-size:.87rem; color:#9abfba;"><i class="fas fa-envelope me-2"></i>support@evergreenbank.com</p>
        <p style="font-size:.87rem; color:#9abfba;"><i class="fas fa-map-marker-alt me-2"></i>123 Financial District, New York, NY 10004</p>
      </div>
    </div>
    <hr class="footer-divider">
    <div class="d-flex flex-wrap justify-content: space-between; align-items-center footer-bottom gap-2">
      <p class="mb-0">&copy; 2025 Evergreen Bank. All rights reserved.</p>
      <div class="d-flex flex-wrap gap-3">
        <a href="Privacy.php">Privacy Policy</a>
        <a href="Terms.php">Terms and Agreements</a>
        <a href="FAQs.php">FAQs</a>
        <a href="AboutUs.php">About Us</a>
      </div>
    </div>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// ── PHP-injected data available to JS ─────────────────────────────────────────
const phpRemainingMap = <?= json_encode($remainingBalancesMap, JSON_NUMERIC_CHECK) ?>;
// Penalty map: { loan_id: { penalty_amount, total_balance_with_penalty, months_overdue, penalty_rate, original_balance } }
const phpPenaltyMap   = <?= json_encode($penaltyMap, JSON_NUMERIC_CHECK) ?>;
// Whether any loan is currently overdue (server-side check)
const hasAnyPenalty   = <?= json_encode($hasAnyPenalty) ?>;

let allLoans         = [];
let allNotifications = [];

// ── Helper: pick best remaining balance source ─────────────────────────────
function getRemainingBalance(loan) {
    const loanId = parseInt(loan.id, 10);

    // If loan has an active penalty → use total_balance_with_penalty as outstanding
    if (phpPenaltyMap.hasOwnProperty(loanId)) {
        return phpPenaltyMap[loanId].total_balance_with_penalty;
    }
    // PHP map (payment-based)
    if (phpRemainingMap.hasOwnProperty(loanId)) {
        return parseFloat(phpRemainingMap[loanId]);
    }
    if (loan.remaining_balance !== undefined && loan.remaining_balance !== null) {
        return parseFloat(loan.remaining_balance);
    }
    if (loan.total_paid !== undefined && loan.total_paid !== null) {
        const r = parseFloat(loan.loan_amount || 0) - parseFloat(loan.total_paid);
        return r > 0 ? r : 0;
    }
    return null;
}

// ── Insert Penalty column header if any loan is overdue ──────────────────────
function injectPenaltyColumn() {
    const thead = document.getElementById('loanTableHead');
    if (!thead) return;
    // Insert before "Status" (index 5 → after Outstanding at index 4)
    const statusTh = thead.querySelector('th:nth-child(6)');
    if (!statusTh) return;
    const th = document.createElement('th');
    th.className = 'th-penalty';
    th.innerHTML = '⚠ Penalty Fee';
    th.title     = 'Active penalty applied due to overdue payment';
    thead.insertBefore(th, statusTh);
}

document.addEventListener("DOMContentLoaded", async function () {
    // If any penalty exists, add the column header immediately
    if (hasAnyPenalty) {
        injectPenaltyColumn();
        // Update colspan on loading row
        const loadingTd = document.querySelector('#loanTableBody tr td');
        if (loadingTd) loadingTd.setAttribute('colspan', '8');
    }

    const tbody               = document.getElementById('loanTableBody');
    const activeLoansCount    = document.getElementById('activeLoansCount');
    const pendingLoansCount   = document.getElementById('pendingLoansCount');
    const closedLoansCount    = document.getElementById('closedLoansCount');
    const overdueLoansCount   = document.getElementById('overdueLoansCount');
    const statClosedCard      = document.getElementById('statClosedCard');
    const statOverdueCard     = document.getElementById('statOverdueCard');
    const nextPaymentDate     = document.getElementById('nextPaymentDate');
    const notificationMessage = document.getElementById('notificationMessage');
    const notificationBadge   = document.getElementById('notificationBadge');

    async function loadLoans() {
        try {
            const response = await fetch('fetch_loan.php', { method: 'GET', credentials: 'include' });
            if (!response.ok) throw new Error('Network error');

            const loans = await response.json();
            if (loans.error) {
                tbody.innerHTML = `<tr><td colspan="${hasAnyPenalty ? 8 : 7}" style="text-align:center;color:red;">${loans.error}</td></tr>`;
                return;
            }

            allLoans         = loans;
            allNotifications = [];

            loans.forEach((loan) => {
                const status = (loan.status || '').toLowerCase();
                const loanId = parseInt(loan.id, 10);
                const hasPenalty = phpPenaltyMap.hasOwnProperty(loanId);

                if (loan.approved_at && (status === 'approved' || status === 'active')) {
                    allNotifications.push({ id: loan.id, type: 'approved', loan_type: loan.loan_type, loan_amount: loan.loan_amount, loan_terms: loan.loan_terms, monthly_payment: loan.monthly_payment, remarks: loan.remarks, timestamp: loan.approved_at, pdf_path: loan.pdf_approved || null });
                }
                if (status === 'active' && loan.approved_at) {
                    allNotifications.push({ id: loan.id, type: 'active', loan_type: loan.loan_type, loan_amount: loan.loan_amount, loan_terms: loan.loan_terms, monthly_payment: loan.monthly_payment, next_payment_due: loan.next_payment_due, remarks: loan.remarks, timestamp: loan.approved_at, pdf_path: loan.pdf_active || null });
                }
                if (status === 'rejected' && loan.rejected_at) {
                    allNotifications.push({ id: loan.id, type: 'rejected', loan_type: loan.loan_type, loan_amount: loan.loan_amount, loan_terms: loan.loan_terms, rejection_remarks: loan.rejection_remarks, timestamp: loan.rejected_at, pdf_path: loan.pdf_rejected || null });
                }
                if (status === 'closed') {
                    allNotifications.push({ id: loan.id, type: 'closed', loan_type: loan.loan_type, loan_amount: loan.loan_amount, loan_terms: loan.loan_terms, monthly_payment: loan.monthly_payment, timestamp: loan.updated_at || loan.approved_at || loan.created_at });
                }
                // ── Penalty notification ──────────────────────────────────
                if (hasPenalty && status === 'active') {
                    const pen = phpPenaltyMap[loanId];
                    allNotifications.push({
                        id: loan.id, type: 'overdue',
                        loan_type: loan.loan_type,
                        loan_amount: loan.loan_amount,
                        loan_terms: loan.loan_terms,
                        penalty_amount: pen.penalty_amount,
                        total_balance_with_penalty: pen.total_balance_with_penalty,
                        months_overdue: pen.months_overdue,
                        penalty_rate: pen.penalty_rate,
                        next_payment_due: loan.next_payment_due,
                        timestamp: new Date().toISOString()
                    });
                }
            });

            allNotifications.sort((a, b) => {
                // Overdue notifications always first
                if (a.type === 'overdue' && b.type !== 'overdue') return -1;
                if (b.type === 'overdue' && a.type !== 'overdue') return 1;
                return new Date(b.timestamp) - new Date(a.timestamp);
            });

            loans.sort((a, b) => {
                const getDate = l => {
                    const s = (l.status || '').toLowerCase();
                    if (s === 'active'   && l.approved_at) return new Date(l.approved_at);
                    if (s === 'rejected' && l.rejected_at) return new Date(l.rejected_at);
                    if (s === 'approved' && l.approved_at) return new Date(l.approved_at);
                    if (s === 'closed'   && l.updated_at)  return new Date(l.updated_at);
                    return new Date(l.created_at || 0);
                };
                return getDate(b) - getDate(a);
            });

            // Re-check if ANY loan is actually overdue after data loads
            let anyOverdueInData = hasAnyPenalty;
            loans.forEach(loan => {
                const loanId = parseInt(loan.id, 10);
                if (phpPenaltyMap.hasOwnProperty(loanId)) anyOverdueInData = true;
            });

            tbody.innerHTML = '';
            const colSpan = anyOverdueInData ? 8 : 7;

            if (loans.length === 0) {
                tbody.innerHTML = `<tr><td colspan="${colSpan}" style="text-align:center;padding:20px;">No loans found.</td></tr>`;
                ['activeLoansCount','pendingLoansCount','closedLoansCount','overdueLoansCount'].forEach(id => document.getElementById(id).textContent = '0');
                nextPaymentDate.textContent = '-';
                notificationMessage.textContent = 'No loans yet. Apply for a loan!';
                notificationBadge.style.display = 'none';
                return;
            }

            let activeCount = 0, pendingCount = 0, closedCount = 0, approvedCount = 0, rejectedCount = 0, overdueCount = 0;
            let earliestDueDate = null;

            loans.forEach((loan) => {
                const status  = (loan.status || '').toLowerCase();
                const loanId  = parseInt(loan.id, 10);
                const pen     = phpPenaltyMap[loanId];
                const hasPen  = !!pen;

                if      (status === 'active')   activeCount++;
                else if (status === 'approved') approvedCount++;
                else if (status === 'pending')  pendingCount++;
                else if (status === 'rejected') rejectedCount++;
                else if (status === 'closed')   closedCount++;

                if (hasPen && status === 'active') overdueCount++;

                if (status === 'active' && loan.next_payment_due) {
                    const d = new Date(loan.next_payment_due);
                    if (!earliestDueDate || d < earliestDueDate) earliestDueDate = d;
                }

                let statusCell = '';
                let nextInfo   = '—';
                let rowClass   = '';

                if (status === 'active') {
                    if (hasPen) {
                        statusCell = `<span style="color:#c0392b;font-weight:bold;">Active <span class="overdue-row-label">OVERDUE</span></span>`;
                        rowClass   = 'row-overdue';
                    } else {
                        statusCell = `<span style="color:#2e7d32;font-weight:bold;">Active</span>`;
                    }
                    nextInfo = loan.next_payment_due
                        ? new Date(loan.next_payment_due).toLocaleDateString('en-US',{year:'numeric',month:'short',day:'numeric'})
                        : '—';
                } else if (status === 'approved') {
                    statusCell = `<span style="color:#4CAF50;font-weight:bold;">Approved – Awaiting Claim</span>`;
                    if (loan.approved_at) {
                        const cd = new Date(loan.approved_at);
                        cd.setDate(cd.getDate() + 30);
                        nextInfo = 'Claim by: ' + cd.toLocaleDateString('en-US',{year:'numeric',month:'short',day:'numeric'});
                    }
                } else if (status === 'pending') {
                    statusCell = `<span style="color:#FF9800;font-weight:bold;">Pending</span>`;
                    nextInfo   = 'Pending Approval';
                } else if (status === 'rejected') {
                    statusCell = `<span style="color:#f44336;font-weight:bold;">Rejected</span>`;
                    nextInfo   = 'N/A';
                } else if (status === 'closed') {
                    rowClass   = 'row-closed';
                    statusCell = `<span class="status-closed-cell"><i class="fas fa-check-circle" style="font-size:.8rem;"></i> Closed / Paid</span>`;
                    const lamt = parseFloat(loan.loan_amount || 0);
                    nextInfo   = `<span style="color:#0a3b2f;font-weight:600;">Fully Settled ₱${lamt.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2})}</span>`;
                }

                // ── Outstanding / Balance cell ──────────────────────────────
                let balanceCell = '';
                if (status === 'closed') {
                    balanceCell = `<span class="balance-zero"><i class="fas fa-check" style="font-size:.7rem;"></i> ₱0.00</span>`;
                } else if (status === 'active' || status === 'approved') {
                    if (hasPen) {
                        const origBal = pen.original_balance;
                        balanceCell = `<span class="balance-active">₱${origBal.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2})}</span>`;
                    } else {
                        let rem = getRemainingBalance(loan);
                        if (rem === null) rem = parseFloat(loan.loan_amount || 0);
                        balanceCell = `<span class="balance-active">₱${rem.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2})}</span>`;
                    }
                } else {
                    balanceCell = `<span class="balance-na">—</span>`;
                }

                // ── Penalty cell (only rendered when column exists) ──────────
                let penaltyCell = '';
                if (anyOverdueInData) {
                    if (hasPen && (status === 'active')) {
                        const penAmt   = pen.penalty_amount;
                        const months   = pen.months_overdue;
                        const rateStr  = (pen.penalty_rate * 100).toFixed(0) + '%';
                        const totalDue = pen.total_balance_with_penalty;
                        penaltyCell = `
                            <td class="td-penalty-active">
                                <div class="penalty-amount-cell">
                                    <span class="penalty-fee-value">+₱${penAmt.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2})}</span>
                                    <span class="penalty-months-tag">⏰ ${months} mo overdue</span>
                                    <span class="penalty-rate-note">${rateStr}/mo compound · Total: ₱${totalDue.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2})}</span>
                                </div>
                            </td>`;
                    } else {
                        penaltyCell = `<td><span class="td-penalty-none">—</span></td>`;
                    }
                }

                tbody.insertAdjacentHTML('beforeend', `
                    <tr class="${rowClass}">
                        <td>${loan.id}</td>
                        <td>${loan.loan_type || 'N/A'}</td>
                        <td>₱${parseFloat(loan.loan_amount).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2})}</td>
                        <td>₱${parseFloat(loan.monthly_payment||0).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2})}</td>
                        <td>${balanceCell}</td>
                        ${penaltyCell}
                        <td>${statusCell}</td>
                        <td>${nextInfo}</td>
                    </tr>
                `);
            });

            activeLoansCount.textContent  = activeCount;
            pendingLoansCount.textContent = pendingCount;
            closedLoansCount.textContent  = closedCount;
            overdueLoansCount.textContent = overdueCount;

            if (closedCount > 0)   statClosedCard.classList.add('stat-closed');
            else                   statClosedCard.classList.remove('stat-closed');
            if (overdueCount > 0)  statOverdueCard.classList.add('stat-overdue');
            else                   statOverdueCard.classList.remove('stat-overdue');

            nextPaymentDate.textContent = earliestDueDate
                ? earliestDueDate.toLocaleDateString('en-US',{year:'numeric',month:'long',day:'numeric'})
                : '—';

            const notifCount = allNotifications.length;
            notificationBadge.textContent   = notifCount;
            notificationBadge.style.display = notifCount > 0 ? 'flex' : 'none';

            // Build summary message
            if (overdueCount > 0) {
                notificationMessage.innerHTML = `⚠️ You have <strong style="color:#c0392b;">${overdueCount}</strong> overdue loan${overdueCount>1?'s':''} with active penalties. Please pay immediately!`;
            } else if (approvedCount > 0 && activeCount > 0) {
                notificationMessage.innerHTML = `You have <strong>${approvedCount}</strong> awaiting claim and <strong>${activeCount}</strong> active loan${activeCount>1?'s':''}.`;
            } else if (approvedCount > 0) {
                notificationMessage.innerHTML = `🎉 <strong>${approvedCount}</strong> loan${approvedCount>1?'s':''} approved. Claim within 30 days!`;
            } else if (activeCount > 0) {
                notificationMessage.innerHTML = `You have <strong>${activeCount}</strong> active loan${activeCount>1?'s':''}.`;
            } else if (closedCount > 0) {
                notificationMessage.innerHTML = `🏆 All loans fully settled. Great job!`;
            } else if (pendingCount > 0) {
                notificationMessage.innerHTML = `<strong>${pendingCount}</strong> application${pendingCount>1?'s':''} pending review.`;
            } else {
                notificationMessage.textContent = 'No active loans.';
            }

        } catch (err) {
            console.error(err);
            tbody.innerHTML = `<tr><td colspan="${hasAnyPenalty ? 8 : 7}" style="text-align:center;color:red;">Error loading loans. Please refresh.</td></tr>`;
        }
    }

    loadLoans();
});

function openNotificationModal() {
    const modal     = document.getElementById('notificationModal');
    const modalBody = document.getElementById('notificationModalBody');

    if (allNotifications.length === 0) {
        modalBody.innerHTML = `<div style="text-align:center;padding:3rem;color:#999;"><i class="fas fa-bell-slash" style="font-size:3rem;margin-bottom:1rem;display:block;"></i><p>No notifications yet.</p></div>`;
    } else {
        let html = '';
        allNotifications.forEach((notif) => {
            const icons  = { approved:'✅', active:'🎉', rejected:'❌', closed:'🏆', overdue:'⚠️' };
            const labels = { approved:'Loan Approved', active:'Loan Active', rejected:'Loan Rejected', closed:'Loan Fully Paid', overdue:'Overdue – Penalty Applied' };
            const icon   = icons[notif.type]  || '';
            const label  = labels[notif.type] || notif.type;

            const ts = notif.timestamp
                ? new Date(notif.timestamp).toLocaleDateString('en-US',{year:'numeric',month:'long',day:'numeric',hour:'2-digit',minute:'2-digit'})
                : '—';

            let importantLine = '';
            if (notif.type === 'approved' && notif.timestamp) {
                const cd = new Date(notif.timestamp); cd.setDate(cd.getDate()+30);
                importantLine = `<p><strong>Claim Deadline:</strong> ${cd.toLocaleDateString('en-US',{year:'numeric',month:'long',day:'numeric'})}</p>`;
            } else if (notif.type === 'active' && notif.next_payment_due) {
                importantLine = `<p><strong>Next Payment Due:</strong> ${new Date(notif.next_payment_due).toLocaleDateString('en-US',{year:'numeric',month:'long',day:'numeric'})}</p>`;
            } else if (notif.type === 'closed') {
                importantLine = `<p><strong>Status:</strong> <span style="color:#0a3b2f;font-weight:700;">Fully Paid & Closed ✓</span></p>`;
            } else if (notif.type === 'overdue') {
                const penAmt   = notif.penalty_amount   ? '₱' + notif.penalty_amount.toLocaleString('en-US',{minimumFractionDigits:2}) : '—';
                const totalDue = notif.total_balance_with_penalty ? '₱' + notif.total_balance_with_penalty.toLocaleString('en-US',{minimumFractionDigits:2}) : '—';
                const rateStr  = notif.penalty_rate ? (notif.penalty_rate*100).toFixed(0)+'%' : '5%';
                importantLine = `
                    <p><strong>Months Overdue:</strong> <span style="color:#c0392b;font-weight:700;">${notif.months_overdue} month(s)</span></p>
                    <p><strong>Penalty Rate:</strong> ${rateStr}/month (compounded)</p>
                    <p><strong>Penalty Charged:</strong> <span style="color:#c0392b;font-weight:700;">${penAmt}</span></p>
                    <p><strong>Total Balance Due:</strong> <span style="color:#c0392b;font-weight:800;font-size:1.1em;">${totalDue}</span></p>
                    <p style="color:#c0392b;font-weight:700;margin-top:.5rem;">⚡ Pay immediately to stop further penalty accumulation!</p>
                `;
            }

            html += `
                <div class="notification-item ${notif.type}">
                    <h3>${icon} ${label} <span class="status-badge ${notif.type}">${label}</span></h3>
                    <div class="notification-divider"></div>
                    <p><strong>Loan ID:</strong> ${notif.id}</p>
                    <p><strong>Loan Type:</strong> ${notif.loan_type || 'N/A'}</p>
                    <p><strong>Loan Amount:</strong> ₱${parseFloat(notif.loan_amount).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2})}</p>
                    ${notif.loan_terms ? `<p><strong>Term:</strong> ${notif.loan_terms}</p>` : ''}
                    ${importantLine}
                    <p class="notification-timestamp"><i class="fas fa-clock"></i> ${ts}</p>
                </div>`;
        });
        modalBody.innerHTML = html;
    }
    modal.style.display = 'block';
}

function closeNotificationModal() {
    document.getElementById('notificationModal').style.display = 'none';
}
window.onclick = function(e) {
    const modal = document.getElementById('notificationModal');
    if (e.target === modal) closeNotificationModal();
};
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeNotificationModal(); });

function generatePDF(loanId, type, btn) {
    var orig = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
    btn.disabled  = true;
    var xhr = new XMLHttpRequest();
    xhr.open('GET', 'generate_pdf.php?loan_id=' + loanId + '&type=' + type, true);
    xhr.withCredentials = true;
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.onload = function() {
        try {
            var data = JSON.parse(xhr.responseText.trim());
            if (!data.success) { alert('PDF Error: ' + (data.error||'Unknown')); btn.innerHTML = orig; btn.disabled = false; return; }
            var url = 'download_pdf.php?file=' + encodeURIComponent(data.filename);
            btn.outerHTML = '<a href="'+url+'" class="download-btn" download><i class="fas fa-download"></i> Download PDF</a>';
        } catch(e) { alert('PDF Error'); btn.innerHTML = orig; btn.disabled = false; }
    };
    xhr.onerror = function() { alert('Network error'); btn.innerHTML = orig; btn.disabled = false; };
    xhr.send();
}
</script>
</body>
</html>