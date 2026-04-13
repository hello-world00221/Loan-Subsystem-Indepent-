<?php
// ─── Start session if not already started ────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ─── DEBUG: Uncomment these lines temporarily to see what session keys exist ──
// echo '<pre>'; print_r($_SESSION); echo '</pre>'; die();

// ─── Role-based access guard ──────────────────────────────────────────────────
//
// VALID SESSION FORMATS ACCEPTED:
//
//   Format A – Legacy login.php path:
//       $_SESSION['user_id'] exists
//       AND ($_SESSION['role'] OR $_SESSION['user_role']) === 'admin'
//
//   Format B – Employeelogin.php → Loan Officer path:
//       $_SESSION['officer_id'] exists
//       AND $_SESSION['admin_id'] does NOT exist
//       (Loan Officers have officer_id set but NOT admin_id)
//
//   Format C – Employeelogin.php → SuperAdmin path:
//       $_SESSION['admin_id'] exists  →  BLOCKED here on purpose
//       SuperAdmins go to Employeedashboard.php, never adminindex.php

// Check Format A
$_isLegacyAdmin = (
    isset($_SESSION['user_id']) &&
    in_array(
        strtolower(trim($_SESSION['role'] ?? $_SESSION['user_role'] ?? '')),
        ['admin'],
        true
    )
);

// Check Format B — Loan Officer via Employeelogin.php
// officer_id must exist AND admin_id must NOT exist
$_isLoanOfficer = (
    !empty($_SESSION['officer_id']) &&
    (empty($_SESSION['admin_id']) || $_SESSION['admin_id'] === null)
);

// If neither format matches → reject
if (!$_isLegacyAdmin && !$_isLoanOfficer) {
    session_destroy();
    header('Location: login.php');
    exit();
}

// ─── Normalise display name ───────────────────────────────────────────────────
if (empty($_SESSION['user_name'])) {
    $_SESSION['user_name'] = $_SESSION['officer_name']
                          ?? $_SESSION['full_name']
                          ?? $_SESSION['admin_name']
                          ?? 'Staff';
}

// ─── Normalise loan_officer_id ────────────────────────────────────────────────
if (empty($_SESSION['loan_officer_id'])) {
    $_SESSION['loan_officer_id'] = $_SESSION['officer_employee_number']
                                ?? $_SESSION['admin_employee_number']
                                ?? 'LO-0001';
}

// ─── Build display values ─────────────────────────────────────────────────────
$_headerUserName  = htmlspecialchars($_SESSION['user_name'] ?? 'Staff');
$_headerUserRole  = 'Loan Officer';
$_headerEmpNum    = htmlspecialchars($_SESSION['loan_officer_id'] ?? '');

// Initials from name (up to 2 words)
$_headerInitials  = '';
foreach (array_slice(explode(' ', trim($_SESSION['user_name'] ?? 'Staff')), 0, 2) as $_w) {
    $_headerInitials .= strtoupper($_w[0] ?? '');
}
$_headerInitials  = htmlspecialchars($_headerInitials ?: 'ST');

$_currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="icon" type="image/png" href="pictures/logo.png" />

  <!-- Bootstrap + Icons + Fonts (same stack as Employeedashboard) -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet" />

  <style>
    /* ══════════════════════════════════════════════════════════════════════
       DESIGN TOKENS  (mirrors Employeedashboard.php)
    ═══════════════════════════════════════════════════════════════════════ */
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
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'DM Sans', sans-serif;
      background: var(--eg-bg);
      color: var(--eg-text);
      min-height: 100vh;
    }

    /* ══ SIDEBAR ══════════════════════════════════════════════════════════ */
    .eg-sidebar {
      position: fixed; top: 0; left: 0;
      width: var(--eg-sidebar-w);
      height: 100vh;
      background: linear-gradient(180deg, var(--eg-deep) 0%, var(--eg-forest) 60%, #0e4535 100%);
      z-index: 1040;
      display: flex; flex-direction: column;
      transform: translateX(-100%);
      transition: transform 0.28s cubic-bezier(.4,0,.2,1);
      box-shadow: 4px 0 28px rgba(6,38,32,0.35);
    }
    .eg-sidebar.open { transform: translateX(0); }
    @media (min-width: 992px) {
      .eg-sidebar { transform: translateX(0); }
      .eg-main    { margin-left: var(--eg-sidebar-w); }
    }

    /* Logo block */
    .eg-sidebar-logo {
      display: flex; align-items: center; gap: 10px;
      padding: 20px 22px 16px;
      border-bottom: 1px solid rgba(255,255,255,0.08);
      text-decoration: none;
    }
    .eg-sidebar-logo-icon {
      width: 36px; height: 36px;
      background: var(--eg-gold);
      border-radius: 8px;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }
    .eg-sidebar-logo-icon img {
      height: 22px; width: auto;
      filter: brightness(0) saturate(100%) invert(10%) sepia(40%) saturate(800%)
              hue-rotate(105deg) brightness(40%);
    }
    .eg-sidebar-logo-text {
      font-family: 'Playfair Display', serif;
      font-size: 17px; font-weight: 700;
      color: #fff; letter-spacing: .8px; line-height: 1.1;
    }
    .eg-sidebar-logo-sub {
      font-size: 10px; color: rgba(255,255,255,0.45); letter-spacing: .3px;
    }

    /* Collapsible nav toggle */
    .eg-nav-toggle-btn {
      display: flex; align-items: center; gap: 8px;
      width: 100%; background: none; border: none;
      color: rgba(255,255,255,0.50); padding: 12px 22px;
      font-size: 10.5px; font-weight: 700; letter-spacing: 1.5px;
      cursor: pointer; transition: color .2s;
      text-transform: uppercase; font-family: 'DM Sans', sans-serif;
    }
    .eg-nav-toggle-btn:hover { color: var(--eg-gold-l); }
    .eg-nav-toggle-btn .chevron { margin-left: auto; transition: transform .25s; }
    .eg-nav-toggle-btn.collapsed .chevron { transform: rotate(-90deg); }

    .eg-nav-collapse {
      overflow: hidden; max-height: 500px;
      transition: max-height .3s ease;
    }
    .eg-nav-collapse.hidden { max-height: 0; }

    /* Nav items */
    .eg-nav-item {
      display: flex; align-items: center; gap: 10px;
      padding: 11px 22px 11px 30px;
      color: rgba(255,255,255,0.60);
      text-decoration: none; font-size: 14px; font-weight: 500;
      transition: background .18s, color .18s;
      border-left: 3px solid transparent;
      font-family: 'DM Sans', sans-serif;
    }
    .eg-nav-item:hover { background: rgba(255,255,255,0.07); color: #fff; }
    .eg-nav-item.active {
      color: var(--eg-gold-l);
      border-left-color: var(--eg-gold);
      background: rgba(201,168,76,0.10);
    }
    .eg-nav-item i { font-size: 16px; width: 20px; text-align: center; }

    /* Sidebar footer / user badge */
    .eg-sidebar-footer {
      margin-top: auto;
      border-top: 1px solid rgba(255,255,255,0.08);
      padding: 16px 22px;
    }
    .eg-sidebar-footer-user { display: flex; align-items: center; gap: 10px; }
    .eg-sidebar-avatar {
      width: 34px; height: 34px; border-radius: 50%;
      background: var(--eg-gold);
      display: flex; align-items: center; justify-content: center;
      font-size: 13px; font-weight: 700; color: var(--eg-deep);
      flex-shrink: 0;
    }
    .eg-sidebar-uname { font-size: 13px; font-weight: 600; color: #fff; line-height: 1.2; }
    .eg-sidebar-urole { font-size: 11px; color: var(--eg-gold-l); }

    /* Mobile overlay */
    .eg-overlay {
      display: none; position: fixed; inset: 0;
      background: rgba(0,0,0,0.50); z-index: 1039;
    }
    .eg-overlay.show { display: block; }
    @media (min-width: 992px) { .eg-overlay { display: none !important; } }

    /* ══ TOP BAR ══════════════════════════════════════════════════════════ */
    .eg-topbar {
      position: sticky; top: 0;
      height: var(--eg-topbar-h);
      background: linear-gradient(90deg, var(--eg-deep) 0%, var(--eg-forest) 100%);
      display: flex; align-items: center; justify-content: space-between;
      padding: 0 26px; z-index: 1030;
      box-shadow: 0 2px 16px rgba(6,38,32,0.28);
    }
    .eg-topbar-left { display: flex; align-items: center; gap: 14px; }

    .eg-hamburger {
      background: none; border: none;
      color: rgba(255,255,255,0.80); font-size: 22px;
      cursor: pointer; padding: 4px 8px; border-radius: 6px;
      transition: color .2s, background .2s; display: none;
    }
    @media (max-width: 991px) { .eg-hamburger { display: flex; } }
    .eg-hamburger:hover { color: var(--eg-gold-l); background: rgba(255,255,255,0.08); }

    .eg-topbar-brand { display: none; }
    @media (max-width: 991px) { .eg-topbar-brand { display: block; } }
    .eg-topbar-brand .eg-tb-name {
      font-family: 'Playfair Display', serif;
      color: #fff; font-size: 16px; font-weight: 700;
    }
    .eg-topbar-brand .eg-tb-page { font-size: 11px; color: rgba(255,255,255,0.50); }

    .eg-breadcrumb {
      display: flex; align-items: center; gap: 6px;
      font-size: 13px; color: rgba(255,255,255,0.55);
    }
    @media (max-width: 991px) { .eg-breadcrumb { display: none; } }
    .eg-breadcrumb .bc-sep { opacity: 0.4; }
    .eg-breadcrumb .bc-active { color: #fff; font-weight: 600; }

    /* Top-bar right: datetime + profile */
    .eg-topbar-right { display: flex; align-items: center; gap: 16px; }

    .eg-datetime {
      display: flex; flex-direction: column; align-items: flex-end;
      color: rgba(255,255,255,0.70); font-size: 12px; line-height: 1.4;
    }
    .eg-datetime strong { font-size: 13px; color: #fff; font-weight: 600; }
    @media (max-width: 640px) { .eg-datetime { display: none; } }

    .eg-profile-wrap { position: relative; }
    .eg-profile-btn {
      display: flex; align-items: center; gap: 10px;
      background: rgba(255,255,255,0.08);
      border: 1px solid rgba(255,255,255,0.14);
      border-radius: 10px; padding: 6px 14px 6px 8px;
      color: #fff; cursor: pointer; transition: background .2s;
      font-family: 'DM Sans', sans-serif;
    }
    .eg-profile-btn:hover { background: rgba(255,255,255,0.15); }
    .eg-avatar {
      width: 32px; height: 32px; border-radius: 50%;
      background: var(--eg-gold);
      display: flex; align-items: center; justify-content: center;
      font-size: 12px; font-weight: 700; color: var(--eg-deep); flex-shrink: 0;
    }
    .eg-profile-info { text-align: left; }
    .eg-profile-name { font-size: 13px; font-weight: 600; line-height: 1.2; }
    .eg-profile-role { font-size: 11px; color: var(--eg-gold-l); line-height: 1.2; }

    .eg-profile-dropdown {
      position: absolute; top: calc(100% + 8px); right: 0;
      background: #fff; border-radius: 12px;
      box-shadow: 0 8px 32px rgba(6,38,32,0.18);
      min-width: 190px; overflow: hidden; z-index: 2000;
      display: none;
      animation: dropIn .18s ease;
      border: 1px solid var(--eg-border);
    }
    .eg-profile-dropdown.show { display: block; }
    @keyframes dropIn {
      from { opacity: 0; transform: translateY(-6px); }
      to   { opacity: 1; transform: translateY(0); }
    }
    .eg-profile-dropdown .dd-header {
      padding: 14px 16px 10px;
      border-bottom: 1px solid var(--eg-border);
    }
    .eg-profile-dropdown .dd-header .dd-name {
      font-size: 13.5px; font-weight: 700; color: var(--eg-text);
    }
    .eg-profile-dropdown .dd-header .dd-empnum { font-size: 11px; color: var(--eg-muted); }
    .eg-profile-dropdown a {
      display: flex; align-items: center; gap: 8px;
      padding: 10px 16px; color: var(--eg-text);
      text-decoration: none; font-size: 13.5px;
      transition: background .15s;
    }
    .eg-profile-dropdown a:hover { background: var(--eg-bg); }
    .eg-profile-dropdown a i { width: 18px; color: var(--eg-muted); }
    .eg-profile-dropdown .divider { height: 1px; background: var(--eg-border); margin: 4px 0; }
    .eg-profile-dropdown a.logout-link { color: #c0392b; }
    .eg-profile-dropdown a.logout-link i { color: #c0392b; }

    /* ══ MAIN WRAPPER ═════════════════════════════════════════════════════ */
    .eg-main { min-height: 100vh; transition: margin-left .28s; }
    .eg-content { padding: 30px 30px 48px; }

    @media (max-width: 575px) {
      .eg-content { padding: 18px 14px 36px; }
    }
  </style>
</head>
<body>

<div class="eg-overlay" id="egOverlay" onclick="closeSidebar()"></div>

<!-- ══ SIDEBAR ═══════════════════════════════════════════════════════════════ -->
<aside class="eg-sidebar" id="egSidebar">

  <a href="adminindex.php" class="eg-sidebar-logo">
    <div class="eg-sidebar-logo-icon">
      <img src="pictures/logo.png" alt="Evergreen Logo" />
    </div>
    <div>
      <div class="eg-sidebar-logo-text">EVERGREEN</div>
      <div class="eg-sidebar-logo-sub">Trust &amp; Savings</div>
    </div>
  </a>

  <div style="padding: 10px 0; flex:1;">
    <button class="eg-nav-toggle-btn" id="navToggleBtn" onclick="toggleNav()">
      <i class="bi bi-grid-fill" style="font-size:11px;"></i>
      Navigation
      <i class="bi bi-chevron-down chevron" style="font-size:10px;"></i>
    </button>
    <div class="eg-nav-collapse" id="navCollapse">
      <a href="adminindex.php"
         class="eg-nav-item<?= ($_currentPage === 'adminindex.php') ? ' active' : '' ?>">
        <i class="bi bi-speedometer2"></i> Dashboard
      </a>
      <a href="adminapplications.php"
         class="eg-nav-item<?= ($_currentPage === 'adminapplications.php') ? ' active' : '' ?>">
        <i class="bi bi-file-earmark-text"></i> Loan Applications
      </a>
    </div>
  </div>

  <div class="eg-sidebar-footer">
    <div class="eg-sidebar-footer-user">
      <div class="eg-sidebar-avatar"><?= $_headerInitials ?></div>
      <div>
        <div class="eg-sidebar-uname"><?= $_headerUserName ?></div>
        <div class="eg-sidebar-urole"><?= $_headerUserRole ?></div>
      </div>
    </div>
  </div>

</aside>

<!-- ══ MAIN WRAPPER ════════════════════════════════════════════════════════════ -->
<div class="eg-main" id="egMain">

  <!-- TOP BAR -->
  <header class="eg-topbar" id="main-header">
    <div class="eg-topbar-left">
      <button class="eg-hamburger" onclick="toggleSidebar()" aria-label="Toggle sidebar">
        <i class="bi bi-list" id="hamburgerIcon"></i>
      </button>
      <div class="eg-topbar-brand">
        <div class="eg-tb-name">EVERGREEN</div>
        <div class="eg-tb-page">
          <?= ($_currentPage === 'adminindex.php') ? 'Dashboard' : 'Loan Applications' ?>
        </div>
      </div>
      <div class="eg-breadcrumb">
        <span>Loan Officer Portal</span>
        <span class="bc-sep"><i class="bi bi-chevron-right" style="font-size:10px;"></i></span>
        <span class="bc-active">
          <?= ($_currentPage === 'adminindex.php') ? 'Dashboard' : 'Loan Applications' ?>
        </span>
      </div>
    </div>

    <div class="eg-topbar-right">
      <div class="eg-datetime">
        <strong id="currentTime"><?= date("h:i:s A") ?></strong>
        <span id="currentDate"><?= date("Y/m/d") ?></span>
      </div>

      <div class="eg-profile-wrap">
        <button class="eg-profile-btn" onclick="toggleProfileDropdown()" id="profileToggleBtn">
          <div class="eg-avatar"><?= $_headerInitials ?></div>
          <div class="eg-profile-info">
            <div class="eg-profile-name"><?= $_headerUserName ?></div>
            <div class="eg-profile-role"><?= $_headerUserRole ?></div>
          </div>
          <i class="bi bi-chevron-down ms-1" style="font-size:11px;opacity:.7;"></i>
        </button>

        <div class="eg-profile-dropdown" id="profileDropdown">
          <div class="dd-header">
            <div class="dd-name"><?= $_headerUserName ?></div>
            <div class="dd-empnum"><?= $_headerEmpNum ?></div>
          </div>
          <a href="logout.php" class="logout-link">
            <i class="bi bi-box-arrow-right"></i> Sign Out
          </a>
        </div>
      </div>
    </div>
  </header>

  <!-- PAGE CONTENT goes below (adminindex.php inserts <main> here) -->

  <script>
    /* ── Sidebar ── */
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

    /* ── Collapsible nav ── */
    function toggleNav() {
      document.getElementById('navToggleBtn').classList.toggle('collapsed');
      document.getElementById('navCollapse').classList.toggle('hidden');
    }

    /* ── Profile dropdown ── */
    function toggleProfileDropdown() {
      document.getElementById('profileDropdown').classList.toggle('show');
    }
    document.addEventListener('click', function (e) {
      const wrap = document.querySelector('.eg-profile-wrap');
      if (wrap && !wrap.contains(e.target)) {
        document.getElementById('profileDropdown').classList.remove('show');
      }
    });

    /* ── Live clock (Manila time) ── */
    function updateTime() {
      const now  = new Date();
      const opts = { timeZone: 'Asia/Manila', hour12: true };
      const timeEl = document.getElementById('currentTime');
      const dateEl = document.getElementById('currentDate');
      if (timeEl) timeEl.textContent = now.toLocaleTimeString('en-PH', opts);
      if (dateEl) dateEl.textContent = now.toLocaleDateString('en-PH', { timeZone: 'Asia/Manila' });
    }
    setInterval(updateTime, 1000);
    updateTime();
  </script>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>