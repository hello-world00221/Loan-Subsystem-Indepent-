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
                "SELECT id, full_name, user_email, role
                 FROM users
                 WHERE id = ? AND user_email = ?
                 LIMIT 1"
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Evergreen Trust and Savings</title>
  <link rel="icon" type="logo/png" href="pictures/logo.png" />

  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <!-- Original stylesheet (kept for non-Bootstrap custom styles) -->
  <link rel="stylesheet" href="style.css">

  <style>
    /* ── Brand tokens ── */
    :root {
      --eg-dark:   #003631;
      --eg-mid:    #005a4d;
      --eg-light:  #00796b;
      --eg-accent: #1db57a;
    }

    /* ── Hero ── */
    .hero {
      min-height: 90vh;
      display: flex;
      align-items: center;
      padding: 5rem 1.5rem 3rem;
      background: linear-gradient(135deg, #f0faf6 0%, #e8f5f0 100%);
    }
    .hero-title  { font-size: clamp(2rem, 5vw, 3.5rem); font-weight: 800; color: var(--eg-dark); line-height: 1.15; }
    .hero-subtitle { font-size: clamp(1.2rem, 3vw, 2rem); font-weight: 700; color: var(--eg-mid); }
    .hero-description { color: #555; font-size: 1.05rem; max-width: 520px; }
    .hero-image img { width: 100%; max-width: 520px; border-radius: 1.5rem; }

    .btn-hero-primary {
      background: var(--eg-dark);
      color: #fff;
      border: none;
      padding: .75rem 2rem;
      border-radius: .6rem;
      font-weight: 600;
      transition: background .2s, transform .15s;
    }
    .btn-hero-primary:hover { background: var(--eg-mid); color: #fff; transform: translateY(-2px); }

    .btn-hero-secondary {
      background: transparent;
      color: var(--eg-dark);
      border: 2px solid var(--eg-dark);
      padding: .72rem 2rem;
      border-radius: .6rem;
      font-weight: 600;
      transition: all .2s;
    }
    .btn-hero-secondary:hover { background: var(--eg-dark); color: #fff; transform: translateY(-2px); }

    .btn-payment {
    background-color: #28a745; /* Success Green */
    color: white;
    padding: 10px 20px;
    border: none;             /* Removes default button border */
    border-radius: 5px;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;          /* Makes the mouse turn into a hand */
    transition: background 0.3s ease;
}

    .btn-payment:hover {
    background-color: #218838; /* Darker green on hover */
}

    .btn-payment:active {
    transform: scale(0.98);    /* Slight "click" effect */
}

    /* ── Loan service cards ── */
    #loan-services { background: #f8faf9; padding: 5rem 1.5rem; }
    .section-title { font-size: clamp(1.4rem, 3vw, 2rem); font-weight: 700; color: var(--eg-dark); letter-spacing: .5px; }

    .loan-card {
      background: #fff;
      border: 1px solid #dceee8;
      border-radius: 1.25rem;
      overflow: hidden;
      cursor: pointer;
      transition: transform .25s, box-shadow .25s;
      position: relative;
    }
    .loan-card:hover { transform: translateY(-6px); box-shadow: 0 12px 32px rgba(0,54,49,.13); }

    /* ── Loan card image container: fixed height, clips overflow ── */
    .loan-card-thumb {
      width: 100%;
      height: 180px;
      overflow: hidden;
      flex-shrink: 0;
      display: block;
      background: #f0faf6;   /* fallback bg while image loads */
    }
    /* ── The <img> fills the container 100% x 100% with no gaps.
           object-fit: cover keeps aspect ratio and crops to fill;
           object-position tuned per card to keep the subject centred. ── */
    .loan-card-thumb img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      object-position: center center;
      display: block;
    }
    /* Personal Loan — lady with laptop, crop from top-centre */
    .loan-card-thumb.thumb-personal img { object-position: center 20%; }
    /* Car Loan — full scene, dead-centre works */
    .loan-card-thumb.thumb-car      img { object-position: center center; }
    /* Home Loan — couple, dead-centre */
    .loan-card-thumb.thumb-home     img { object-position: center center; }
    /* Multi-Purpose Loan — face top, crop from top */
    .loan-card-thumb.thumb-mpl      img { object-position: center 15%; }

    .loan-card-body { padding: 1.25rem; }
    .loan-card-title { font-weight: 700; color: var(--eg-dark); font-size: 1.05rem; margin-bottom: .35rem; }
    .loan-card-desc  { color: #666; font-size: .92rem; margin: 0; }

    .loan-card.disabled {
      opacity: .5;
      cursor: not-allowed;
      pointer-events: none;
      filter: grayscale(50%);
    }
    .loan-card.disabled::after {
      content: 'Application Pending';
      position: absolute; top: 50%; left: 50%;
      transform: translate(-50%, -50%);
      background: rgba(0,0,0,.8);
      color: #fff;
      padding: .6rem 1.2rem;
      border-radius: .5rem;
      font-weight: 700;
      font-size: .85rem;
      z-index: 10;
    }

    /* ── Dashboard ── */
    #loan-dashboard { background: #fff; padding: 5rem 1.5rem; }
    .dashboard-title { font-size: clamp(1.4rem, 3vw, 2rem); font-weight: 700; color: var(--eg-dark); margin-bottom: 1.5rem; }

    .stat-card {
      background: #f0faf6;
      border: 1px solid #c4e8da;
      border-radius: 1rem;
      padding: 1.5rem 1.25rem;
      text-align: center;
    }
    .stat-label { font-size: .85rem; color: #6c757d; text-transform: uppercase; letter-spacing: .8px; margin-bottom: .4rem; }
    .stat-value { font-size: 2rem; font-weight: 800; color: var(--eg-dark); margin: 0; }

    /* ── Loan table ── */
    .loan-table-wrapper {
      border: 1px solid #dceee8;
      border-radius: 1rem;
      overflow: hidden;
    }
    .loan-table-wrapper table { margin: 0; }
    .loan-table-wrapper thead th { background: var(--eg-dark); color: #fff; font-size: .8rem; text-transform: uppercase; letter-spacing: .6px; border: none; padding: .9rem 1rem; }
    .loan-table-wrapper tbody td { padding: .85rem 1rem; font-size: .92rem; border-color: #e8f4ee; vertical-align: middle; }
    .loan-table-wrapper tbody tr:hover td { background: #f0faf6; }

    .loan-footer {
      background: #f8faf9;
      border-top: 1px solid #dceee8;
      padding: 1rem 1.25rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: .75rem;
    }
    .loan-footer p { margin: 0; font-size: .9rem; color: #555; }
    .payment-button {
      background: var(--eg-dark);
      color: #fff;
      padding: .55rem 1.5rem;
      border-radius: .5rem;
      font-weight: 600;
      text-decoration: none;
      font-size: .9rem;
      transition: background .2s;
    }
    .payment-button:hover { background: var(--eg-mid); color: #fff; }

    /* ── Notifications sidebar ── */
    .notifications-panel {
      background: #f8faf9;
      border: 1px solid #dceee8;
      border-radius: 1rem;
      padding: 1.5rem;
      height: 100%;
    }
    .notifications-panel h2 { font-size: 1.1rem; font-weight: 700; color: var(--eg-dark); margin-bottom: .75rem; }

    .notification-btn {
      position: relative;
      background: var(--eg-dark);
      color: #fff;
      border: none;
      padding: .65rem 1.5rem;
      border-radius: .6rem;
      cursor: pointer;
      font-size: .95rem;
      margin-top: 1rem;
      transition: background .2s, transform .15s;
      font-weight: 500;
      width: 100%;
    }
    .notification-btn:hover { background: var(--eg-mid); transform: translateY(-2px); }
    .notification-badge {
      position: absolute;
      top: -8px; right: -8px;
      background: #ff4444;
      color: #fff;
      border-radius: 50%;
      min-width: 24px; height: 24px;
      display: flex; align-items: center; justify-content: center;
      font-size: 12px; font-weight: 700;
      border: 2px solid #fff;
      animation: pulse 2s infinite;
    }
    @keyframes pulse { 0%,100%{transform:scale(1)} 50%{transform:scale(1.1)} }

    /* ── Notification Modal ── */
    .notification-modal {
      display: none; position: fixed; z-index: 1050;
      left: 0; top: 0; width: 100%; height: 100%;
      background: rgba(0,0,0,.55);
      animation: fadeIn .3s;
    }
    .notification-modal-content {
      background: #fefefe;
      margin: 3% auto;
      border-radius: .9rem;
      width: 90%; max-width: 700px; max-height: 85vh;
      overflow: hidden;
      box-shadow: 0 8px 32px rgba(0,0,0,.2);
      animation: slideDown .35s ease-out;
    }
    .notification-modal-header {
      background: linear-gradient(135deg, var(--eg-dark) 0%, var(--eg-mid) 100%);
      color: #fff; padding: 1.4rem 1.5rem;
      display: flex; justify-content: space-between; align-items: center;
      border-bottom: 3px solid var(--eg-light);
    }
    .notification-modal-header h2 { margin: 0; font-size: 1.25rem; display: flex; align-items: center; gap: 10px; }
    .notification-close { color: #fff; font-size: 1.8rem; font-weight: 700; cursor: pointer; background: none; border: none; line-height: 1; padding: 0; transition: transform .2s; }
    .notification-close:hover { transform: rotate(90deg); }
    .notification-modal-body { padding: 1.5rem; max-height: 65vh; overflow-y: auto; background: #f8f9fa; }

    .notification-header-text { background: #fff; padding: 1rem; border-radius: .5rem; margin-bottom: 1.25rem; border-left: 4px solid var(--eg-dark); }
    .notification-header-text h3 { margin: 0; color: var(--eg-dark); font-size: 1rem; }

    .notification-item {
      background: #fff;
      border-left: 5px solid var(--eg-dark);
      padding: 1.2rem;
      margin-bottom: 1rem;
      border-radius: .5rem;
      transition: all .25s;
      box-shadow: 0 2px 8px rgba(0,0,0,.06);
      line-height: 1.5;
    }
    .notification-item:hover { transform: translateX(6px); box-shadow: 0 4px 16px rgba(0,0,0,.1); }
    .notification-item.approved { border-left-color: #4CAF50; background: linear-gradient(to right, #e8f5e9 0%, #fff 12%); }
    .notification-item.active   { border-left-color: #2e7d32; background: linear-gradient(to right, #c8e6c9 0%, #fff 12%); }
    .notification-item.rejected { border-left-color: #f44336; background: linear-gradient(to right, #ffebee 0%, #fff 12%); }
    .notification-item h3 { margin: 0 0 .75rem; color: var(--eg-dark); font-size: 1.05rem; display: flex; align-items: center; gap: 8px; }
    .notification-item.approved h3 { color: #4CAF50; }
    .notification-item.active h3   { color: #2e7d32; }
    .notification-item.rejected h3 { color: #c62828; }
    .status-badge { display: inline-block; padding: 3px 10px; border-radius: 1rem; font-size: .8rem; font-weight: 700; }
    .status-badge.approved { background: #4CAF50; color: #fff; }
    .status-badge.active   { background: #2e7d32; color: #fff; }
    .status-badge.rejected { background: #f44336; color: #fff; }
    .notification-item p { margin: .4rem 0; color: #555; font-size: .92rem; }
    .notification-item p strong { color: var(--eg-dark); font-weight: 600; display: inline-block; min-width: 160px; }
    .notification-divider { height: 1px; background: linear-gradient(to right, transparent, #ddd, transparent); margin: .6rem 0; }
    .notification-empty { text-align: center; padding: 3rem 1rem; color: #999; }
    .notification-empty i { font-size: 3.5rem; color: #ddd; margin-bottom: 1rem; display: block; }
    .notification-timestamp { font-size: .82rem; color: #888; font-style: italic; margin-top: .6rem; }

    .pdf-actions { margin-top: .9rem; padding-top: .6rem; border-top: 1px solid #eee; display: flex; gap: .5rem; flex-wrap: wrap; }
    .download-btn, .generate-pdf-btn {
      padding: .45rem .9rem; border-radius: .4rem; font-size: .85rem;
      transition: all .2s; white-space: nowrap; cursor: pointer;
    }
    .download-btn { background: #007bff; color: #fff; text-decoration: none; display: inline-block; border: none; }
    .download-btn:hover { background: #0056b3; transform: translateY(-1px); }
    .generate-pdf-btn { background: #6c757d; color: #fff; border: none; }
    .generate-pdf-btn:hover { background: #545b62; transform: translateY(-1px); }

    @keyframes fadeIn  { from{opacity:0}  to{opacity:1} }
    @keyframes slideDown { from{transform:translateY(-80px);opacity:0} to{transform:translateY(0);opacity:1} }

    /* ── Footer ── */
    footer { background: var(--eg-dark); color: #cde8e1; padding: 3.5rem 1.5rem 1.5rem; }
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

    /* ── Responsive tweaks ── */
    @media (max-width: 768px) {
      .hero { padding: 6rem 1.2rem 3rem; }
      .hero-image { margin-top: 2rem; text-align: center; }
      .loan-table-wrapper { overflow-x: auto; }
    }
  </style>
</head>
<body>

<?php include 'header.php'; ?>

<!-- ═══════════════ HERO ═══════════════ -->
<section id="home" class="hero">
  <div class="container">
    <div class="row align-items-center">
      <div class="col-lg-6 mb-4 mb-lg-0">
        <h1 class="hero-title">EVERGREEN <span style="color:#00796b;">TRUST AND SAVINGS</span></h1>
        <h2 class="hero-subtitle mb-3">LOAN SERVICES</h2>
        <p class="hero-description mb-4">
          Bring your plans to life. Enjoy low interest rates and choose the financing option that suits your needs.
        </p>
        <div class="d-flex flex-wrap gap-3">
          <a href="#loan-services"   class="btn-hero-primary text-decoration-none">Apply for Loan</a>
          <a href="#loan-dashboard"  class="btn-hero-secondary text-decoration-none">Go to Dashboard</a>
        </div>
      </div>
      <div class="col-lg-6 hero-image text-center">
        <img src="pictures/landing_page.png" alt="Apply for a Loan Easily" class="img-fluid">
      </div>
    </div>
  </div>
</section>

<!-- ═══════════════ LOAN SERVICES ═══════════════ -->
<section id="loan-services">
  <div class="container">
    <h2 class="section-title text-center mb-5">LOAN SERVICES WE OFFER</h2>
    <div class="row g-4">

      <div class="col-sm-6 col-xl-3">
        <div class="loan-card h-100" onclick="window.location.href='Loan_AppForm.php?loanType=Personal%20Loan'">
          <div class="loan-card-thumb thumb-personal">
            <img src="pictures/personalloan.png" alt="Personal Loan">
          </div>
          <div class="loan-card-body">
            <p class="loan-card-title">Personal Loan</p>
            <p class="loan-card-desc">Stop worrying and bring your plans to life.</p>
          </div>
        </div>
      </div>

      <div class="col-sm-6 col-xl-3">
        <div class="loan-card h-100" onclick="window.location.href='Loan_AppForm.php?loanType=Car%20Loan'">
          <div class="loan-card-thumb thumb-car">
            <img src="pictures/carloan.png" alt="Car Loan">
          </div>
          <div class="loan-card-body">
            <p class="loan-card-title">Car Loan</p>
            <p class="loan-card-desc">Drive your new car with low rates and fast approval.</p>
          </div>
        </div>
      </div>

      <div class="col-sm-6 col-xl-3">
        <div class="loan-card h-100" onclick="window.location.href='Loan_AppForm.php?loanType=Home%20Loan'">
          <div class="loan-card-thumb thumb-home">
            <img src="pictures/housingloan.png" alt="Home Loan">
          </div>
          <div class="loan-card-body">
            <p class="loan-card-title">Home Loan</p>
            <p class="loan-card-desc">Take the first step to your new home.</p>
          </div>
        </div>
      </div>

      <div class="col-sm-6 col-xl-3">
        <div class="loan-card h-100" onclick="window.location.href='Loan_AppForm.php?loanType=Multi-Purpose%20Loan'">
          <div class="loan-card-thumb thumb-mpl">
            <img src="pictures/mpl.png" alt="Multi-Purpose Loan">
          </div>
          <div class="loan-card-body">
            <p class="loan-card-title">Multi-Purpose Loan</p>
            <p class="loan-card-desc">Use your property to fund your various needs.</p>
          </div>
        </div>
      </div>

    </div>
  </div>
</section>

<!-- ═══════════════ LOAN DASHBOARD ═══════════════ -->
<section id="loan-dashboard">
  <div class="container">
    <h2 class="dashboard-title">Loan Dashboard</h2>

    <!-- Stats row -->
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
      <div class="col-4">
        <div class="stat-card">
          <p class="stat-label">Closed</p>
          <p class="stat-value" id="closedLoansCount">0</p>
        </div>
      </div>
    </div>

    <!-- Table + Notifications -->
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
                  <th>Next Due</th>
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
                Make a Payment
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
</section>

<!-- ═══════════════ NOTIFICATION MODAL ═══════════════ -->
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

<!-- ═══════════════ FOOTER ═══════════════ -->
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
        <a href="#">Privacy Policy</a>
        <a href="#">Terms and Agreements</a>
        <a href="#">FAQs</a>
        <a href="#">About Us</a>
      </div>
      <p class="mb-0">Member FDIC. Equal Housing Lender. Evergreen Bank, N.A.</p>
    </div>
  </div>
</footer>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- ══════════════════════════════════════════════════
     ALL ORIGINAL JAVASCRIPT LOGIC — UNTOUCHED
═════════════════════════════════════════════════════ -->
<script>
let allLoans = [];
let allNotifications = [];

document.addEventListener("DOMContentLoaded", async function () {
  const tbody               = document.getElementById('loanTableBody');
  const activeLoansCount    = document.getElementById('activeLoansCount');
  const pendingLoansCount   = document.getElementById('pendingLoansCount');
  const closedLoansCount    = document.getElementById('closedLoansCount');
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
        if (loan.approved_at && (loan.status === 'Approved' || loan.status === 'Active')) {
          allNotifications.push({ id: loan.id, type: 'approved', loan_type: loan.loan_type, loan_amount: loan.loan_amount, loan_terms: loan.loan_terms, monthly_payment: loan.monthly_payment, remarks: loan.remarks, timestamp: loan.approved_at, pdf_path: loan.pdf_approved || null });
        }
        if (loan.status === 'Active' && loan.approved_at) {
          allNotifications.push({ id: loan.id, type: 'active', loan_type: loan.loan_type, loan_amount: loan.loan_amount, loan_terms: loan.loan_terms, monthly_payment: loan.monthly_payment, next_payment_due: loan.next_payment_due, remarks: loan.remarks, timestamp: loan.approved_at, pdf_path: loan.pdf_active || null });
        }
        if (loan.status === 'Rejected' && loan.rejected_at) {
          allNotifications.push({ id: loan.id, type: 'rejected', loan_type: loan.loan_type, loan_amount: loan.loan_amount, loan_terms: loan.loan_terms, rejection_remarks: loan.rejection_remarks, timestamp: loan.rejected_at, pdf_path: loan.pdf_rejected || null });
        }
      });
      allNotifications.sort((a, b) => new Date(b.timestamp) - new Date(a.timestamp));

      const hasPendingLoan = loans.some(loan => loan.status === 'Pending');
      const loanCards = document.querySelectorAll('.loan-card');
      if (hasPendingLoan) {
        loanCards.forEach(card => { card.classList.add('disabled'); card.style.position = 'relative'; card.onclick = null; });
      } else {
        loanCards.forEach(card => {
          card.classList.remove('disabled');
          const loanType = card.querySelector('.loan-card-title').textContent;
          let urlLoanType = loanType === 'Housing Loan' ? 'Home Loan' : loanType === 'Multipurpose Loan' ? 'Multi-Purpose Loan' : loanType;
          card.onclick = () => window.location.href = `Loan_AppForm.php?loanType=${encodeURIComponent(urlLoanType)}`;
        });
      }

      loans.sort((a, b) => {
        const getDate = l => new Date(l.status === 'Active' && l.approved_at ? l.approved_at : l.status === 'Rejected' && l.rejected_at ? l.rejected_at : l.status === 'Approved' && l.approved_at ? l.approved_at : l.created_at || 0);
        return getDate(b) - getDate(a);
      });

      tbody.innerHTML = '';

      if (loans.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:20px;">No loans found. Apply for a loan to get started!</td></tr>';
        activeLoansCount.textContent = '0'; pendingLoansCount.textContent = '0'; closedLoansCount.textContent = '0'; nextPaymentDate.textContent = '-';
        notificationMessage.textContent = 'No loans yet. Apply for a loan above!';
        notificationBadge.style.display = 'none';
        return;
      }

      let activeCount = 0, pendingCount = 0, closedCount = 0, approvedCount = 0, rejectedCount = 0, earliestDueDate = null;

      loans.forEach((loan) => {
        if      (loan.status === 'Active')   { activeCount++; if (loan.next_payment_due) { const d = new Date(loan.next_payment_due); if (!earliestDueDate || d < earliestDueDate) earliestDueDate = d; } }
        else if (loan.status === 'Approved') { approvedCount++; }
        else if (loan.status === 'Pending')  { pendingCount++; }
        else if (loan.status === 'Rejected') { closedCount++; rejectedCount++; }
        else if (loan.status === 'Closed')   { closedCount++; }

        let displayStatus = loan.status, statusStyle = '';
        if      (loan.status === 'Active')   { statusStyle = 'style="color:#2e7d32;font-weight:bold;"'; }
        else if (loan.status === 'Approved') { displayStatus = 'Approved - Awaiting Claim'; statusStyle = 'style="color:#4CAF50;font-weight:bold;"'; }
        else if (loan.status === 'Rejected') { statusStyle = 'style="color:#f44336;font-weight:bold;"'; }
        else if (loan.status === 'Pending')  { statusStyle = 'style="color:#FF9800;font-weight:bold;"'; }

        let nextPayment = '-';
        if      (loan.status === 'Active' && loan.next_payment_due) { nextPayment = new Date(loan.next_payment_due).toLocaleDateString('en-US', { year:'numeric', month:'short', day:'numeric' }); }
        else if (loan.status === 'Approved') { if (loan.approved_at) { const cd = new Date(loan.approved_at); cd.setDate(cd.getDate() + 30); nextPayment = 'Claim by: ' + cd.toLocaleDateString('en-US', { year:'numeric', month:'short', day:'numeric' }); } else { nextPayment = 'Awaiting Claim'; } }
        else if (loan.status === 'Pending')  { nextPayment = 'Pending Approval'; }
        else if (loan.status === 'Rejected') { nextPayment = 'N/A'; }

        tbody.insertAdjacentHTML('beforeend', `
          <tr>
            <td>${loan.id}</td>
            <td>${loan.loan_type || 'N/A'}</td>
            <td>₱${parseFloat(loan.loan_amount).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2})}</td>
            <td>₱${parseFloat(loan.monthly_payment||0).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2})}</td>
            <td ${statusStyle}>${displayStatus}</td>
            <td>${nextPayment}</td>
          </tr>
        `);
      });

      activeLoansCount.textContent  = activeCount;
      pendingLoansCount.textContent = pendingCount;
      closedLoansCount.textContent  = closedCount;
      nextPaymentDate.textContent   = earliestDueDate ? earliestDueDate.toLocaleDateString('en-US',{year:'numeric',month:'long',day:'numeric'}) : '-';

      const notificationCount = allNotifications.length;
      notificationBadge.textContent   = notificationCount;
      notificationBadge.style.display = notificationCount > 0 ? 'flex' : 'none';

      if      (approvedCount > 0 && activeCount > 0 && rejectedCount > 0) notificationMessage.innerHTML = `You have <strong>${approvedCount}</strong> approved (awaiting claim), <strong>${activeCount}</strong> active, and <strong>${rejectedCount}</strong> rejected loan${rejectedCount>1?'s':''}.`;
      else if (approvedCount > 0 && activeCount > 0) notificationMessage.innerHTML = `You have <strong>${approvedCount}</strong> loan${approvedCount>1?'s':''} awaiting claim and <strong>${activeCount}</strong> active loan${activeCount>1?'s':''}.`;
      else if (approvedCount > 0) notificationMessage.innerHTML = `🎉 Congratulations! You have <strong>${approvedCount}</strong> approved loan${approvedCount>1?'s':''}. Please claim within 30 days!`;
      else if (activeCount > 0 && rejectedCount > 0) notificationMessage.innerHTML = `You have <strong>${activeCount}</strong> active and <strong>${rejectedCount}</strong> rejected loan${rejectedCount>1?'s':''}.`;
      else if (activeCount > 0) notificationMessage.innerHTML = `You have <strong>${activeCount}</strong> active loan${activeCount>1?'s':''}.`;
      else if (rejectedCount > 0) notificationMessage.innerHTML = `You have <strong>${rejectedCount}</strong> rejected loan${rejectedCount>1?'s':''}.`;
      else if (pendingCount > 0) notificationMessage.innerHTML = `You have <strong>${pendingCount}</strong> pending application${pendingCount>1?'s':''}.`;
      else notificationMessage.textContent = 'All your loans are settled. Great job!';

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
      const icons  = { approved:'✅', active:'🎉', rejected:'❌' };
      const labels = { approved:'Loan Approved - Awaiting Claim', active:'Loan Activated', rejected:'Loan Rejected' };
      const icon   = icons[notif.type]  || '';
      const label  = labels[notif.type] || '';
      const formattedDate = new Date(notif.timestamp).toLocaleDateString('en-US',{ year:'numeric', month:'long', day:'numeric', hour:'2-digit', minute:'2-digit' });

      let importantDate = '', importantDateLabel = '';
      if (notif.type === 'approved') { const d = new Date(notif.timestamp); d.setDate(d.getDate()+30); importantDate = d.toLocaleDateString('en-US',{year:'numeric',month:'long',day:'numeric'}); importantDateLabel = 'Claim Deadline'; }
      else if (notif.type === 'active' && notif.next_payment_due) { importantDate = new Date(notif.next_payment_due).toLocaleDateString('en-US',{year:'numeric',month:'long',day:'numeric'}); importantDateLabel = 'Next Payment Due'; }

      const remarksText = notif.type === 'rejected' ? (notif.rejection_remarks||'') : (notif.remarks||'');
      const pdfButton   = notif.pdf_path
        ? `<a href="download_pdf.php?file=${encodeURIComponent(notif.pdf_path.replace('uploads/',''))}" class="download-btn" download><i class="fas fa-download"></i> Download PDF</a>`
        : `<button class="generate-pdf-btn" onclick="generatePDF(${notif.id}, '${notif.type}', this)"><i class="fas fa-file-pdf"></i> Generate PDF</button>`;

      html += `
        <div class="notification-item ${notif.type}">
          <h3>${icon} ${label} <span class="status-badge ${notif.type}">${label}</span></h3>
          <div class="notification-divider"></div>
          <p><strong>Loan ID:</strong> ${notif.id}</p>
          <p><strong>Loan Type:</strong> ${notif.loan_type||'N/A'}</p>
          <p><strong>Loan Amount:</strong> ₱${parseFloat(notif.loan_amount).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2})}</p>
          <p><strong>Term:</strong> ${notif.loan_terms||'N/A'}</p>
          ${notif.monthly_payment ? `<p><strong>Monthly Payment:</strong> ₱${parseFloat(notif.monthly_payment).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2})}</p>` : ''}
          ${importantDate  ? `<p><strong>${importantDateLabel}:</strong> ${importantDate}</p>` : ''}
          ${remarksText    ? `<p><strong>Remarks:</strong> ${remarksText}</p>` : ''}
          ${notif.type === 'approved' ? '<p style="color:#f57c00;font-weight:bold;"><i class="fas fa-exclamation-triangle"></i> Please visit our bank within 30 days to claim your loan!</p>' : ''}
          <p class="notification-timestamp"><i class="fas fa-clock"></i> ${formattedDate}</p>
          <div class="pdf-actions">${pdfButton}</div>
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
    var raw = xhr.responseText;
    console.log('[generatePDF] HTTP status:', xhr.status);
    console.log('[generatePDF] Response Content-Type:', xhr.getResponseHeader('Content-Type'));
    console.log('[generatePDF] Raw response (first 500 chars):', raw.substring(0, 500));

    var trimmed = raw.trim();
    if (trimmed.charAt(0) === '<') {
      var phpErr = trimmed.match(/(?:Fatal error|Parse error|Warning|Notice)[^<]{0,300}/i);
      var msg = phpErr ? phpErr[0].trim() : 'Server returned HTML instead of JSON (HTTP ' + xhr.status + ').\nCheck F12 > Console for the full response.';
      alert('PDF Error:\n' + msg);
      btn.innerHTML = orig; btn.disabled = false;
      return;
    }
    var data;
    try { data = JSON.parse(trimmed); } catch (e) { alert('PDF Error: Invalid JSON.\nRaw: ' + trimmed.substring(0, 200)); btn.innerHTML = orig; btn.disabled = false; return; }
    if (!data.success) { alert('PDF Error: ' + (data.error || 'Unknown error')); btn.innerHTML = orig; btn.disabled = false; return; }

    var xhr2 = new XMLHttpRequest();
    xhr2.open('POST', 'update_loan_pdf.php', true);
    xhr2.withCredentials = true;
    xhr2.setRequestHeader('Content-Type', 'application/json');
    xhr2.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr2.onload = function() {
      var url = 'download_pdf.php?file=' + encodeURIComponent(data.filename);
      btn.outerHTML = '<a href="' + url + '" class="download-btn" download><i class="fas fa-download"></i> Download PDF</a>';
      allNotifications.forEach(function(n) { if (n.id === loanId && n.type === type) n.pdf_path = 'uploads/' + data.filename; });
    };
    xhr2.onerror = function() {
      console.warn('update_loan_pdf.php failed — PDF still generated OK');
      var url = 'download_pdf.php?file=' + encodeURIComponent(data.filename);
      btn.outerHTML = '<a href="' + url + '" class="download-btn" download><i class="fas fa-download"></i> Download PDF</a>';
    };
    xhr2.send(JSON.stringify({ loan_id: loanId, pdf_path: data.filename, type: type }));
  };
  xhr.onerror = function() { alert('PDF Error: Network request failed. Check your connection.'); btn.innerHTML = orig; btn.disabled = false; };
  xhr.send();
}

const urlParams = new URLSearchParams(window.location.search);
if (urlParams.get('scrollTo') === 'dashboard') {
  setTimeout(() => {
    document.getElementById('loan-dashboard')?.scrollIntoView({ behavior:'smooth', block:'start' });
    window.history.replaceState({}, document.title, window.location.pathname);
  }, 500);
}
</script>

</body>
</html>