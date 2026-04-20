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

// ─── FETCH USER DETAILS ────────────────────────────────────────────────────────
$profileUser = null;
try {
    if (!isset($pdo)) {
        $pdo = new PDO(
            "mysql:host=localhost;dbname=loandb;charset=utf8mb4",
            "root", "",
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_email = ? LIMIT 1");
    $stmt->execute([$_SESSION['user_email']]);
    $profileUser = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // silently fail
}
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
    /* Closed stat card — golden highlight */
    .stat-card.stat-closed { background: linear-gradient(135deg, #f7f3e8, #fdf6d8); border-color: rgba(201,168,76,.35); }
    .stat-card.stat-closed .stat-value { color: #7a5200; }
    .stat-card.stat-closed .stat-label { color: #9a7020; }

    /* Loan table */
    .loan-table-wrapper { border: 1px solid #dceee8; border-radius: 1rem; overflow: hidden; background: #fff; box-shadow: 0 2px 12px rgba(0,54,49,.06); }
    .loan-table-wrapper table { margin: 0; }
    .loan-table-wrapper thead th { background: var(--eg-dark); color: #fff; font-size: .78rem; text-transform: uppercase; letter-spacing: .6px; border: none; padding: .9rem 1rem; }
    .loan-table-wrapper tbody td { padding: .85rem 1rem; font-size: .92rem; border-color: #e8f4ee; vertical-align: middle; }
    .loan-table-wrapper tbody tr:hover td { background: #f0faf6; }
    /* Highlight closed/paid rows */
    .loan-table-wrapper tbody tr.row-closed td { background: #f5fdf8; }
    .loan-table-wrapper tbody tr.row-closed:hover td { background: #e8f8ef; }

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

    /* ── NEW: Closed loan row status display ── */
    .status-closed-cell {
      display: inline-flex; align-items: center; gap: 5px;
      background: linear-gradient(90deg, #0a3b2f, #1a6b55);
      color: #e8c96b; font-size: .82rem; font-weight: 700;
      padding: 3px 10px; border-radius: .5rem;
    }
    .paid-chip {
      display: inline-flex; align-items: center; gap: 3px;
      background: linear-gradient(90deg,#0a3b2f,#1a6b55);
      color: #e8c96b; font-size: .65rem; font-weight: 700;
      padding: 1px 7px; border-radius: 6px; letter-spacing: .4px;
      text-transform: uppercase; margin-top: 3px;
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

    /* Notification Modal */
    .notification-modal { display: none; position: fixed; z-index: 1050; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,.55); animation: fadeIn .3s; }
    .notification-modal-content { background: #fefefe; margin: 3% auto; border-radius: .9rem; width: 90%; max-width: 700px; max-height: 85vh; overflow: hidden; box-shadow: 0 8px 32px rgba(0,0,0,.2); animation: slideDown .35s ease-out; }
    .notification-modal-header { background: linear-gradient(135deg, var(--eg-dark) 0%, var(--eg-mid) 100%); color: #fff; padding: 1.4rem 1.5rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 3px solid var(--eg-light); }
    .notification-modal-header h2 { margin: 0; font-size: 1.25rem; display: flex; align-items: center; gap: 10px; }
    .notification-close { color: #fff; font-size: 1.8rem; font-weight: 700; cursor: pointer; background: none; border: none; line-height: 1; padding: 0; transition: transform .2s; }
    .notification-close:hover { transform: rotate(90deg); }
    .notification-modal-body { padding: 1.5rem; max-height: 65vh; overflow-y: auto; background: #f8f9fa; }

    .notification-header-text { background: #fff; padding: 1rem; border-radius: .5rem; margin-bottom: 1.25rem; border-left: 4px solid var(--eg-dark); }
    .notification-header-text h3 { margin: 0; color: var(--eg-dark); font-size: 1rem; }

    .notification-item { background: #fff; border-left: 5px solid var(--eg-dark); padding: 1.2rem; margin-bottom: 1rem; border-radius: .5rem; transition: all .25s; box-shadow: 0 2px 8px rgba(0,0,0,.06); line-height: 1.5; }
    .notification-item:hover { transform: translateX(6px); box-shadow: 0 4px 16px rgba(0,0,0,.1); }
    .notification-item.approved { border-left-color: #4CAF50; background: linear-gradient(to right, #e8f5e9 0%, #fff 12%); }
    .notification-item.active   { border-left-color: #2e7d32; background: linear-gradient(to right, #c8e6c9 0%, #fff 12%); }
    .notification-item.rejected { border-left-color: #f44336; background: linear-gradient(to right, #ffebee 0%, #fff 12%); }
    .notification-item.closed   { border-left-color: #0a3b2f; background: linear-gradient(to right, #d0ece0 0%, #fff 12%); }
    .notification-item h3 { margin: 0 0 .75rem; color: var(--eg-dark); font-size: 1.05rem; display: flex; align-items: center; gap: 8px; }
    .notification-item.approved h3 { color: #4CAF50; }
    .notification-item.active   h3 { color: #2e7d32; }
    .notification-item.rejected h3 { color: #c62828; }
    .notification-item.closed   h3 { color: #0a3b2f; }
    .status-badge { display: inline-block; padding: 3px 10px; border-radius: 1rem; font-size: .8rem; font-weight: 700; }
    .status-badge.approved { background: #4CAF50; color: #fff; }
    .status-badge.active   { background: #2e7d32; color: #fff; }
    .status-badge.rejected { background: #f44336; color: #fff; }
    .status-badge.closed   { background: linear-gradient(90deg, #0a3b2f, #1a6b55); color: #e8c96b; }
    .notification-item p { margin: .4rem 0; color: #555; font-size: .92rem; }
    .notification-item p strong { color: var(--eg-dark); font-weight: 600; display: inline-block; min-width: 160px; }
    .notification-divider { height: 1px; background: linear-gradient(to right, transparent, #ddd, transparent); margin: .6rem 0; }
    .notification-empty { text-align: center; padding: 3rem 1rem; color: #999; }
    .notification-empty i { font-size: 3.5rem; color: #ddd; margin-bottom: 1rem; display: block; }
    .notification-timestamp { font-size: .82rem; color: #888; font-style: italic; margin-top: .6rem; }

    .pdf-actions { margin-top: .9rem; padding-top: .6rem; border-top: 1px solid #eee; display: flex; gap: .5rem; flex-wrap: wrap; }
    .download-btn, .generate-pdf-btn { padding: .45rem .9rem; border-radius: .4rem; font-size: .85rem; transition: all .2s; white-space: nowrap; cursor: pointer; }
    .download-btn { background: #007bff; color: #fff; text-decoration: none; display: inline-block; border: none; }
    .download-btn:hover { background: #0056b3; transform: translateY(-1px); }
    .generate-pdf-btn { background: #6c757d; color: #fff; border: none; }
    .generate-pdf-btn:hover { background: #545b62; transform: translateY(-1px); }

    @keyframes fadeIn    { from{opacity:0}  to{opacity:1} }
    @keyframes slideDown { from{transform:translateY(-80px);opacity:0} to{transform:translateY(0);opacity:1} }

    /* Footer */
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

    @media (max-width: 768px) { .profile-badges { margin-left: 0; } }
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

    <!-- ── Stats row ── -->
    <!--
      activeLoansCount  = loans with status 'active'
      pendingLoansCount = loans with status 'pending'
      closedLoansCount  = loans with status 'closed' (fully paid) — golden card
    -->
    <div class="row g-3 mb-4">
      <div class="col-4">
        <div class="stat-card">
          <p class="stat-label">Active Loans</p>
          <p class="stat-value" id="activeLoansCount">0</p>
        </div>
      </div>
      <div class="col-4">
        <div class="stat-card">
          <p class="stat-label">Pending</p>
          <p class="stat-value" id="pendingLoansCount">0</p>
        </div>
      </div>
      <div class="col-4 stat-closed-wrapper">
        <!-- This card is dynamically styled golden when closedLoansCount > 0 -->
        <div class="stat-card" id="statClosedCard">
          <p class="stat-label">Fully Paid</p>
          <p class="stat-value" id="closedLoansCount">0</p>
        </div>
      </div>
    </div>

    <!-- ── Loan Table + Notifications ── -->
    <div class="row g-4">

      <!-- Loan Table -->
      <div class="col-lg-8">
        <div class="loan-table-wrapper">
          <div style="max-height:400px;overflow-y:auto;">
            <table class="table table-hover mb-0">
              <thead>
                <tr>
                  <th>Loan ID</th>
                  <th>Type</th>
                  <th>Amount</th>
                  <th>Monthly</th>
                  <th>Status</th>
                  <th>Next Due / Info</th>
                </tr>
              </thead>
              <tbody id="loanTableBody">
                <tr><td colspan="6" class="text-center py-4">Loading…</td></tr>
              </tbody>
            </table>
          </div>
          <div class="loan-footer">
            <p>Next payment due: <strong id="nextPaymentDate">—</strong></p>
            <button type="button"
              class="btn-payment"
              onclick="location.href='http://localhost/Evergreen-loan-main/LoanSubsystem/Payment/Payment.php'">
              <i class="fas fa-credit-card me-1"></i> Make a Payment
            </button>
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

<!-- ══ NOTIFICATION MODAL ═══════════════════════════════════════════════════════ -->
<div id="notificationModal" class="notification-modal">
  <div class="notification-modal-content">
    <div class="notification-modal-header">
      <h2><i class="fas fa-bell"></i> Your Notifications</h2>
      <button class="notification-close" onclick="closeNotificationModal()">&times;</button>
    </div>
    <div class="notification-modal-body" id="notificationModalBody">
      <div class="notification-empty">
        <i class="fas fa-bell-slash"></i>
        <p>No notifications yet.</p>
      </div>
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
        <a href="#">Website Banking</a>
      </div>
      <div class="col-lg-3 col-md-6 footer-col">
        <h3>Contact Us</h3>
        <p style="font-size:.87rem; color:#9abfba;"><i class="fas fa-phone-alt me-2"></i>1-800-EVERGREEN</p>
        <p style="font-size:.87rem; color:#9abfba;"><i class="fas fa-envelope me-2"></i>support@evergreenbank.com</p>
        <p style="font-size:.87rem; color:#9abfba;"><i class="fas fa-map-marker-alt me-2"></i>123 Financial District, Suite 500, New York, NY 10004</p>
      </div>
    </div>
    <hr class="footer-divider">
    <div class="d-flex flex-wrap justify-content-between align-items-center footer-bottom gap-2">
      <p class="mb-0">&copy; 2025 Evergreen Bank. All rights reserved.</p>
      <div class="d-flex flex-wrap gap-3">
        <a href="Privacy.php">Privacy Policy</a>
        <a href="Terms.php">Terms and Agreements</a>
        <a href="FAQs.php">FAQs</a>
        <a href="AboutUs.php">About Us</a>
      </div>
      <p class="mb-0">Member FDIC. Equal Housing Lender. Evergreen Bank, N.A.</p>
    </div>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
let allLoans         = [];
let allNotifications = [];

document.addEventListener("DOMContentLoaded", async function () {
  const tbody               = document.getElementById('loanTableBody');
  const activeLoansCount    = document.getElementById('activeLoansCount');
  const pendingLoansCount   = document.getElementById('pendingLoansCount');
  const closedLoansCount    = document.getElementById('closedLoansCount');
  const statClosedCard      = document.getElementById('statClosedCard');
  const nextPaymentDate     = document.getElementById('nextPaymentDate');
  const notificationMessage = document.getElementById('notificationMessage');
  const notificationBadge   = document.getElementById('notificationBadge');

  async function loadLoans() {
    try {
      const response = await fetch('fetch_loan.php', { method: 'GET', credentials: 'include' });
      if (!response.ok) throw new Error('Network response was not ok');

      const loans = await response.json();
      if (loans.error) {
        tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:20px;color:red;">${loans.error}</td></tr>`;
        return;
      }

      allLoans = loans;
      allNotifications = [];

      loans.forEach((loan) => {
        const status = (loan.status || '').toLowerCase();

        // Approved notification
        if (loan.approved_at && (status === 'approved' || status === 'active')) {
          allNotifications.push({
            id: loan.id, type: 'approved',
            loan_type: loan.loan_type, loan_amount: loan.loan_amount,
            loan_terms: loan.loan_terms, monthly_payment: loan.monthly_payment,
            remarks: loan.remarks, timestamp: loan.approved_at,
            pdf_path: loan.pdf_approved || null
          });
        }
        // Active notification
        if (status === 'active' && loan.approved_at) {
          allNotifications.push({
            id: loan.id, type: 'active',
            loan_type: loan.loan_type, loan_amount: loan.loan_amount,
            loan_terms: loan.loan_terms, monthly_payment: loan.monthly_payment,
            next_payment_due: loan.next_payment_due, remarks: loan.remarks,
            timestamp: loan.approved_at, pdf_path: loan.pdf_active || null
          });
        }
        // Rejected notification
        if (status === 'rejected' && loan.rejected_at) {
          allNotifications.push({
            id: loan.id, type: 'rejected',
            loan_type: loan.loan_type, loan_amount: loan.loan_amount,
            loan_terms: loan.loan_terms, rejection_remarks: loan.rejection_remarks,
            timestamp: loan.rejected_at, pdf_path: loan.pdf_rejected || null
          });
        }
        // ── Closed / Fully Paid notification ──────────────────────────────
        if (status === 'closed') {
          allNotifications.push({
            id: loan.id, type: 'closed',
            loan_type: loan.loan_type, loan_amount: loan.loan_amount,
            loan_terms: loan.loan_terms, monthly_payment: loan.monthly_payment,
            timestamp: loan.updated_at || loan.approved_at || loan.created_at,
            pdf_path: null
          });
        }
      });

      allNotifications.sort((a, b) => new Date(b.timestamp) - new Date(a.timestamp));

      // Sort: most recent activity first
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

      tbody.innerHTML = '';

      if (loans.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:20px;">No loans found. Apply for a loan to get started!</td></tr>';
        activeLoansCount.textContent  = '0';
        pendingLoansCount.textContent = '0';
        closedLoansCount.textContent  = '0';
        nextPaymentDate.textContent   = '-';
        notificationMessage.textContent = 'No loans yet. Apply for a loan above!';
        notificationBadge.style.display = 'none';
        return;
      }

      let activeCount = 0, pendingCount = 0, closedCount = 0, approvedCount = 0, rejectedCount = 0;
      let earliestDueDate = null;

      loans.forEach((loan) => {
        const status = (loan.status || '').toLowerCase();

        if      (status === 'active')   { activeCount++; }
        else if (status === 'approved') { approvedCount++; }
        else if (status === 'pending')  { pendingCount++; }
        else if (status === 'rejected') { rejectedCount++; }
        else if (status === 'closed')   { closedCount++; }

        if (status === 'active' && loan.next_payment_due) {
          const d = new Date(loan.next_payment_due);
          if (!earliestDueDate || d < earliestDueDate) earliestDueDate = d;
        }

        // ── Row style & info based on status ──────────────────────────────
        let displayStatus = loan.status;
        let statusCell    = '';
        let nextInfo      = '—';
        let rowClass      = '';

        if (status === 'active') {
          statusCell = `<span style="color:#2e7d32;font-weight:bold;">Active</span>`;
          nextInfo   = loan.next_payment_due
            ? new Date(loan.next_payment_due).toLocaleDateString('en-US',{year:'numeric',month:'short',day:'numeric'})
            : '—';
        } else if (status === 'approved') {
          statusCell = `<span style="color:#4CAF50;font-weight:bold;">Approved – Awaiting Claim</span>`;
          if (loan.approved_at) {
            const cd = new Date(loan.approved_at);
            cd.setDate(cd.getDate() + 30);
            nextInfo = 'Claim by: ' + cd.toLocaleDateString('en-US',{year:'numeric',month:'short',day:'numeric'});
          } else { nextInfo = 'Awaiting Claim'; }
        } else if (status === 'pending') {
          statusCell = `<span style="color:#FF9800;font-weight:bold;">Pending</span>`;
          nextInfo   = 'Pending Approval';
        } else if (status === 'rejected') {
          statusCell = `<span style="color:#f44336;font-weight:bold;">Rejected</span>`;
          nextInfo   = 'N/A';
        } else if (status === 'closed') {
          // ── KEY: Closed / Fully Paid display ──────────────────────────
          rowClass   = 'row-closed';
          statusCell = `<span class="status-closed-cell">
                          <i class="fas fa-check-circle" style="font-size:.8rem;"></i>
                          Closed / Paid
                        </span>`;
          const lamt = parseFloat(loan.loan_amount || 0);
          nextInfo   = `<span style="color:#0a3b2f;font-weight:600;">
                          Fully Settled ₱${lamt.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2})}
                        </span>`;
        }

        tbody.insertAdjacentHTML('beforeend', `
          <tr class="${rowClass}">
            <td>${loan.id}</td>
            <td>${loan.loan_type || 'N/A'}</td>
            <td>₱${parseFloat(loan.loan_amount).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2})}</td>
            <td>₱${parseFloat(loan.monthly_payment||0).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2})}</td>
            <td>${statusCell}</td>
            <td>${nextInfo}</td>
          </tr>
        `);
      });

      activeLoansCount.textContent  = activeCount;
      pendingLoansCount.textContent = pendingCount;
      closedLoansCount.textContent  = closedCount;

      // ── Apply golden style to Closed stat card when count > 0 ──────────
      if (closedCount > 0) {
        statClosedCard.classList.add('stat-closed');
      } else {
        statClosedCard.classList.remove('stat-closed');
      }

      nextPaymentDate.textContent = earliestDueDate
        ? earliestDueDate.toLocaleDateString('en-US',{year:'numeric',month:'long',day:'numeric'})
        : '—';

      const notificationCount = allNotifications.length;
      notificationBadge.textContent   = notificationCount;
      notificationBadge.style.display = notificationCount > 0 ? 'flex' : 'none';

      // Build notification summary message
      const closedMsg = closedCount > 0 ? `<strong>${closedCount}</strong> fully paid` : '';
      if (approvedCount > 0 && activeCount > 0 && rejectedCount > 0) {
        notificationMessage.innerHTML = `You have <strong>${approvedCount}</strong> awaiting claim, <strong>${activeCount}</strong> active, and <strong>${rejectedCount}</strong> rejected loan${rejectedCount>1?'s':''}.`;
      } else if (approvedCount > 0 && activeCount > 0) {
        notificationMessage.innerHTML = `You have <strong>${approvedCount}</strong> loan${approvedCount>1?'s':''} awaiting claim and <strong>${activeCount}</strong> active.`;
      } else if (approvedCount > 0) {
        notificationMessage.innerHTML = `🎉 You have <strong>${approvedCount}</strong> approved loan${approvedCount>1?'s':''}. Please claim within 30 days!`;
      } else if (activeCount > 0 && closedCount > 0) {
        notificationMessage.innerHTML = `You have <strong>${activeCount}</strong> active loan${activeCount>1?'s':''} and ${closedMsg} 🏆`;
      } else if (activeCount > 0) {
        notificationMessage.innerHTML = `You have <strong>${activeCount}</strong> active loan${activeCount>1?'s':''}.`;
      } else if (closedCount > 0 && activeCount === 0 && pendingCount === 0) {
        notificationMessage.innerHTML = `🏆 Great job! All ${closedMsg} loan${closedCount>1?'s':''} fully settled!`;
      } else if (rejectedCount > 0) {
        notificationMessage.innerHTML = `You have <strong>${rejectedCount}</strong> rejected loan${rejectedCount>1?'s':''}.`;
      } else if (pendingCount > 0) {
        notificationMessage.innerHTML = `You have <strong>${pendingCount}</strong> pending application${pendingCount>1?'s':''}.`;
      } else {
        notificationMessage.textContent = 'All loans are settled. Great job!';
      }

    } catch (error) {
      console.error('Error loading loans:', error);
      tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:20px;color:red;">Error loading loan data. Please refresh the page.</td></tr>';
    }
  }

  loadLoans();
});

function openNotificationModal() {
  const modal     = document.getElementById('notificationModal');
  const modalBody = document.getElementById('notificationModalBody');

  if (allNotifications.length === 0) {
    modalBody.innerHTML = `<div class="notification-empty"><i class="fas fa-bell-slash"></i><p>No notifications yet.</p></div>`;
  } else {
    let html = '<div class="notification-header-text"><h3>📢 You have new notifications</h3></div>';

    allNotifications.forEach((notif) => {
      const icons  = { approved:'✅', active:'🎉', rejected:'❌', closed:'🏆' };
      const labels = { approved:'Loan Approved – Awaiting Claim', active:'Loan Activated', rejected:'Loan Rejected', closed:'Loan Fully Paid' };
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
        importantLine = `
          <p><strong>Status:</strong> <span style="color:#0a3b2f;font-weight:700;">Fully Paid &amp; Closed ✓</span></p>
          <p><strong>Total Amount:</strong> ₱${parseFloat(notif.loan_amount).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2})}</p>
        `;
      }

      const remarksText = notif.type === 'rejected' ? (notif.rejection_remarks||'') : (notif.remarks||'');
      const pdfButton   = notif.pdf_path
        ? `<a href="download_pdf.php?file=${encodeURIComponent(notif.pdf_path.replace('uploads/',''))}" class="download-btn" download><i class="fas fa-download"></i> Download PDF</a>`
        : (notif.type !== 'closed'
            ? `<button class="generate-pdf-btn" onclick="generatePDF(${notif.id}, '${notif.type}', this)"><i class="fas fa-file-pdf"></i> Generate PDF</button>`
            : '');

      html += `
        <div class="notification-item ${notif.type}">
          <h3>${icon} ${label} <span class="status-badge ${notif.type}">${label}</span></h3>
          <div class="notification-divider"></div>
          <p><strong>Loan ID:</strong> ${notif.id}</p>
          <p><strong>Loan Type:</strong> ${notif.loan_type||'N/A'}</p>
          <p><strong>Loan Amount:</strong> ₱${parseFloat(notif.loan_amount).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2})}</p>
          <p><strong>Term:</strong> ${notif.loan_terms||'N/A'}</p>
          ${notif.monthly_payment ? `<p><strong>Monthly Payment:</strong> ₱${parseFloat(notif.monthly_payment).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2})}</p>` : ''}
          ${importantLine}
          ${remarksText ? `<p><strong>Remarks:</strong> ${remarksText}</p>` : ''}
          ${notif.type === 'approved' ? '<p style="color:#f57c00;font-weight:bold;"><i class="fas fa-exclamation-triangle"></i> Please visit our bank within 30 days to claim your loan!</p>' : ''}
          <p class="notification-timestamp"><i class="fas fa-clock"></i> ${ts}</p>
          ${pdfButton ? `<div class="pdf-actions">${pdfButton}</div>` : ''}
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
    var raw = xhr.responseText.trim();
    if (raw.charAt(0) === '<') {
      var phpErr = raw.match(/(?:Fatal error|Parse error|Warning|Notice)[^<]{0,300}/i);
      alert('PDF Error:\n' + (phpErr ? phpErr[0].trim() : 'Server error (HTTP ' + xhr.status + ')'));
      btn.innerHTML = orig; btn.disabled = false;
      return;
    }
    var data;
    try { data = JSON.parse(raw); } catch(e) { alert('PDF Error: Invalid JSON'); btn.innerHTML = orig; btn.disabled = false; return; }
    if (!data.success) { alert('PDF Error: ' + (data.error||'Unknown error')); btn.innerHTML = orig; btn.disabled = false; return; }

    var xhr2 = new XMLHttpRequest();
    xhr2.open('POST','update_loan_pdf.php',true);
    xhr2.withCredentials = true;
    xhr2.setRequestHeader('Content-Type','application/json');
    xhr2.setRequestHeader('X-Requested-With','XMLHttpRequest');
    xhr2.onload = function() {
      var url = 'download_pdf.php?file=' + encodeURIComponent(data.filename);
      btn.outerHTML = '<a href="'+url+'" class="download-btn" download><i class="fas fa-download"></i> Download PDF</a>';
      allNotifications.forEach(function(n){ if(n.id===loanId && n.type===type) n.pdf_path='uploads/'+data.filename; });
    };
    xhr2.onerror = function() {
      var url = 'download_pdf.php?file=' + encodeURIComponent(data.filename);
      btn.outerHTML = '<a href="'+url+'" class="download-btn" download><i class="fas fa-download"></i> Download PDF</a>';
    };
    xhr2.send(JSON.stringify({ loan_id: loanId, pdf_path: data.filename, type: type }));
  };
  xhr.onerror = function() { alert('PDF Error: Network request failed.'); btn.innerHTML = orig; btn.disabled = false; };
  xhr.send();
}
</script>

</body>
</html>