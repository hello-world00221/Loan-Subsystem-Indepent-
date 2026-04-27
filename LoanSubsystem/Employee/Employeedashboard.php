<?php
session_start();

// ── Auth guard ───────────────────────────────────────────────────────────────
if (isset($_SESSION['officer_id']) && !isset($_SESSION['admin_id'])) {
    header("Location: ../Loan/adminindex.php");
    exit;
}
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../Loan/login.php");
    exit;
}

// ── DB connection ────────────────────────────────────────────────────────────
$host   = 'localhost';
$dbname = 'loandb';
$dbuser = 'root';
$dbpass = '';

$pdo      = null;
$officers = [];
$stats    = ['totalOfficers' => 0, 'activeAccounts' => 0, 'inactiveAccounts' => 0];
$error    = null;

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $dbuser,
        $dbpass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    $stmt = $pdo->query(
        "SELECT id, employee_number, full_name, officer_email, role, status, created_at
         FROM officers
         ORDER BY created_at DESC"
    );
    $officers = $stmt->fetchAll();

    $stats['totalOfficers']    = count($officers);
    $stats['activeAccounts']   = count(array_filter($officers, fn($o) => strtolower($o['status']) === 'active'));
    $stats['inactiveAccounts'] = count(array_filter($officers, fn($o) => strtolower($o['status']) === 'inactive'));

} catch (PDOException $e) {
    $error = "Database error: " . htmlspecialchars($e->getMessage());
}

// ── Session info ─────────────────────────────────────────────────────────────
$adminName    = $_SESSION['admin_name']            ?? 'Staff User';
$adminRole    = $_SESSION['admin_role']            ?? 'SuperAdmin';
$adminEmpNum  = $_SESSION['admin_employee_number'] ?? '';

$adminName    = htmlspecialchars($adminName);
$adminRole    = htmlspecialchars($adminRole);
$adminInitials = implode('', array_map(
    fn($w) => strtoupper($w[0]),
    array_slice(explode(' ', $adminName), 0, 2)
));

// ── NAV — Manage Payments now sits AFTER Audit Logs ─────────────────────────
$navItems = [
    ['label' => 'Dashboard',          'href' => 'Employeedashboard.php', 'icon' => 'bi-speedometer2'],
    ['label' => 'Account Management', 'href' => 'add_officer.php',       'icon' => 'bi-person-gear'],
    ['label' => 'Audit Logs',         'href' => 'audit_logs.php',        'icon' => 'bi-journal-text'],
    ['label' => 'Loan Penalties', 'href' => 'loan_penalty.php', 'icon' => 'bi-exclamation-triangle-fill'],
    ['label' => 'Manage Payments',    'href' => 'manage_payments.php',   'icon' => 'bi-credit-card-2-front']
];

$activeNav = 'Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Evergreen – Employee Dashboard</title>
  <link rel="icon" type="image/png" href="pictures/logo.png" />

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet" />

  <style>
    :root {
      --eg-forest:   #0a3b2f;
      --eg-deep:     #062620;
      --eg-mid:      #1a6b55;
      --eg-light:    #e8f4ef;
      --eg-cream:    #f7f3ee;
      --eg-gold:     #c9a84c;
      --eg-gold-l:   #e8c96b;
      --eg-text:     #1c2b25;
      --eg-muted:    #6b8c7e;
      --eg-border:   #d4e6de;
      --eg-bg:       #f4f8f6;
      --eg-card:     #ffffff;
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
      filter: brightness(0) saturate(100%) invert(10%) sepia(40%) saturate(800%) hue-rotate(105deg) brightness(40%);
    }
    .eg-sidebar-logo-text {
      font-family: 'Playfair Display', serif;
      font-size: 17px; font-weight: 700;
      color: #fff; letter-spacing: .8px;
      line-height: 1.1;
    }
    .eg-sidebar-logo-sub {
      font-size: 10px; color: rgba(255,255,255,0.45);
      letter-spacing: .3px;
    }

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

    .eg-nav-collapse { overflow: hidden; max-height: 600px; transition: max-height .3s ease; }
    .eg-nav-collapse.hidden { max-height: 0; }

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

    /* ── Payment nav item gets a subtle green pill accent ── */
    .eg-nav-item.nav-payments {
      position: relative;
    }
    .eg-nav-item.nav-payments .nav-new-pill {
      display: inline-block;
      margin-left: auto;
      font-size: 9px;
      font-weight: 700;
      letter-spacing: .4px;
      background: rgba(201,168,76,0.25);
      color: var(--eg-gold-l);
      padding: 2px 7px;
      border-radius: 10px;
      text-transform: uppercase;
    }

    .eg-sidebar-footer {
      margin-top: auto;
      border-top: 1px solid rgba(255,255,255,0.08);
      padding: 16px 22px;
    }
    .eg-sidebar-footer-user {
      display: flex; align-items: center; gap: 10px;
    }
    .eg-sidebar-avatar {
      width: 34px; height: 34px; border-radius: 50%;
      background: var(--eg-gold);
      display: flex; align-items: center; justify-content: center;
      font-size: 13px; font-weight: 700; color: var(--eg-deep);
      flex-shrink: 0;
    }
    .eg-sidebar-uname { font-size: 13px; font-weight: 600; color: #fff; line-height: 1.2; }
    .eg-sidebar-urole { font-size: 11px; color: var(--eg-gold-l); }

    .eg-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.50); z-index: 1039; }
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
    .eg-topbar-brand .eg-tb-page {
      font-size: 11px; color: rgba(255,255,255,0.50);
    }

    .eg-breadcrumb {
      display: flex; align-items: center; gap: 6px;
      font-size: 13px; color: rgba(255,255,255,0.55);
    }
    @media (max-width: 991px) { .eg-breadcrumb { display: none; } }
    .eg-breadcrumb .bc-sep { opacity: 0.4; }
    .eg-breadcrumb .bc-active { color: #fff; font-weight: 600; }

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
    .eg-profile-dropdown .dd-header .dd-empnum {
      font-size: 11px; color: var(--eg-muted);
    }
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

    /* ══ MAIN CONTENT ══════════════════════════════════════════════════════ */
    .eg-main { min-height: 100vh; transition: margin-left .28s; }
    .eg-content { padding: 30px 30px 48px; }

    .eg-page-header { margin-bottom: 28px; }
    .eg-page-title {
      font-family: 'Playfair Display', serif;
      font-size: 28px; font-weight: 700;
      color: var(--eg-forest); letter-spacing: -.2px;
    }
    .eg-page-sub { font-size: 13.5px; color: var(--eg-muted); margin-top: 3px; }

    .eg-stat-card {
      background: var(--eg-card);
      border-radius: 16px;
      padding: 24px 26px;
      box-shadow: 0 1px 6px rgba(10,59,47,0.06), 0 4px 16px rgba(10,59,47,0.04);
      border: 1.5px solid var(--eg-border);
      transition: border-color .2s, box-shadow .2s, transform .2s;
      height: 100%;
      position: relative; overflow: hidden;
    }
    .eg-stat-card::before {
      content: ''; position: absolute;
      width: 80px; height: 80px; border-radius: 50%;
      background: var(--eg-light); opacity: 0.6;
      top: -20px; right: -20px;
    }
    .eg-stat-card:hover { transform: translateY(-2px); box-shadow: 0 6px 24px rgba(10,59,47,0.10); }
    .eg-stat-card.highlight {
      background: linear-gradient(135deg, var(--eg-forest) 0%, var(--eg-mid) 100%);
      border-color: transparent;
    }
    .eg-stat-card.highlight .eg-stat-label,
    .eg-stat-card.highlight .eg-stat-sub { color: rgba(255,255,255,0.65); }
    .eg-stat-card.highlight .eg-stat-num { color: #fff; }
    .eg-stat-card.highlight::before { background: rgba(255,255,255,0.08); opacity: 1; }
    .eg-stat-card.gold-card {
      border-color: rgba(201,168,76,0.35);
      background: linear-gradient(135deg, #fdfaf3 0%, #fff9ed 100%);
    }
    .eg-stat-card.gold-card .eg-stat-num { color: #8a6000; }
    .eg-stat-icon {
      width: 40px; height: 40px; border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      background: var(--eg-light); margin-bottom: 14px;
      position: relative; z-index: 1;
    }
    .eg-stat-icon i { font-size: 18px; color: var(--eg-forest); }
    .eg-stat-card.highlight .eg-stat-icon { background: rgba(255,255,255,0.15); }
    .eg-stat-card.highlight .eg-stat-icon i { color: var(--eg-gold-l); }
    .eg-stat-card.gold-card .eg-stat-icon { background: rgba(201,168,76,0.15); }
    .eg-stat-card.gold-card .eg-stat-icon i { color: var(--eg-gold); }
    .eg-stat-label {
      font-size: 11.5px; color: var(--eg-muted); font-weight: 600;
      text-transform: uppercase; letter-spacing: .6px; margin-bottom: 6px;
      position: relative; z-index: 1;
    }
    .eg-stat-num {
      font-size: 38px; font-weight: 800; color: var(--eg-forest);
      line-height: 1; margin-bottom: 4px; position: relative; z-index: 1;
    }
    .eg-stat-sub { font-size: 13px; color: var(--eg-muted); position: relative; z-index: 1; }

    .eg-section-header {
      display: flex; align-items: center; justify-content: space-between;
      margin: 34px 0 18px; flex-wrap: wrap; gap: 12px;
    }
    .eg-section-title-wrap .eg-section-title {
      font-family: 'Playfair Display', serif;
      font-size: 20px; font-weight: 700; color: var(--eg-forest);
    }
    .eg-section-title-wrap .eg-section-sub {
      font-size: 12.5px; color: var(--eg-muted); margin-top: 2px;
    }

    .eg-btn-add {
      display: inline-flex; align-items: center; gap: 7px;
      background: linear-gradient(135deg, var(--eg-forest) 0%, var(--eg-mid) 100%);
      color: #fff; border: none; border-radius: 10px;
      padding: 10px 20px; font-size: 13.5px; font-weight: 600;
      cursor: pointer; text-decoration: none;
      transition: all .2s; font-family: 'DM Sans', sans-serif;
      box-shadow: 0 2px 10px rgba(10,59,47,0.20);
    }
    .eg-btn-add:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(10,59,47,0.28); color: #fff; }

    .eg-table-card {
      background: var(--eg-card);
      border-radius: 16px;
      box-shadow: 0 1px 6px rgba(10,59,47,0.06);
      border: 1.5px solid var(--eg-border);
      overflow: hidden;
    }

    .eg-table-toolbar {
      padding: 16px 20px; border-bottom: 1px solid var(--eg-border);
      display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
    }
    .eg-search-wrap { position: relative; flex: 1; min-width: 180px; max-width: 320px; }
    .eg-search-wrap i {
      position: absolute; left: 12px; top: 50%; transform: translateY(-50%);
      color: var(--eg-muted); font-size: 14px;
    }
    .eg-search-input {
      width: 100%; padding: 9px 12px 9px 34px;
      border: 1.5px solid var(--eg-border); border-radius: 9px;
      font-family: 'DM Sans', sans-serif; font-size: 13.5px;
      color: var(--eg-text); background: var(--eg-bg);
      outline: none; transition: border-color .2s, box-shadow .2s;
    }
    .eg-search-input:focus { border-color: var(--eg-forest); box-shadow: 0 0 0 3px rgba(10,59,47,0.08); background: white; }

    .eg-filter-select {
      padding: 9px 32px 9px 12px;
      border: 1.5px solid var(--eg-border); border-radius: 9px;
      font-family: 'DM Sans', sans-serif; font-size: 13.5px;
      color: var(--eg-text); background: var(--eg-bg);
      outline: none; cursor: pointer;
      transition: border-color .2s; -webkit-appearance: none; appearance: none;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='7' viewBox='0 0 10 7'%3E%3Cpath fill='none' stroke='%236b8c7e' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round' d='M1 1l4 4 4-4'/%3E%3C/svg%3E");
      background-repeat: no-repeat; background-position: right 10px center;
    }
    .eg-filter-select:focus { border-color: var(--eg-forest); }

    .eg-table { width: 100%; border-collapse: collapse; }
    .eg-table thead th {
      background: #f4f8f6;
      font-size: 11px; font-weight: 700;
      text-transform: uppercase; letter-spacing: .7px;
      color: var(--eg-muted); padding: 13px 22px;
      border-bottom: 1.5px solid var(--eg-border);
      white-space: nowrap;
    }
    .eg-table tbody tr {
      border-bottom: 1px solid #eef4f0;
      transition: background .15s;
    }
    .eg-table tbody tr:last-child { border-bottom: none; }
    .eg-table tbody tr:hover { background: #f8fcfa; }
    .eg-table tbody td {
      padding: 14px 22px;
      font-size: 14px; color: var(--eg-text);
      vertical-align: middle;
    }

    .eg-officer-cell { display: flex; align-items: center; gap: 11px; }
    .eg-officer-avatar {
      width: 34px; height: 34px; border-radius: 50%;
      background: var(--eg-light); border: 1.5px solid var(--eg-border);
      display: flex; align-items: center; justify-content: center;
      font-size: 12px; font-weight: 700; color: var(--eg-forest);
      flex-shrink: 0;
    }
    .eg-officer-name { font-weight: 600; font-size: 14px; color: var(--eg-text); }
    .eg-officer-empnum { font-size: 11.5px; color: var(--eg-muted); font-family: 'Courier New', monospace; }

    .eg-badge {
      display: inline-flex; align-items: center; gap: 5px;
      padding: 4px 12px; border-radius: 20px;
      font-size: 12px; font-weight: 700; letter-spacing: .3px;
    }
    .eg-badge.active   { background: var(--eg-light); color: var(--eg-forest); border: 1px solid var(--eg-border); }
    .eg-badge.inactive { background: #f3f4f6; color: #6b7280; border: 1px solid #e5e7eb; }
    .eg-badge.active::before   { content: '●'; font-size: 8px; color: var(--eg-mid); }
    .eg-badge.inactive::before { content: '●'; font-size: 8px; color: #9ca3af; }

    .eg-role-badge {
      display: inline-block; padding: 3px 10px; border-radius: 6px;
      font-size: 11.5px; font-weight: 600; background: rgba(201,168,76,0.12);
      color: #8a6000; border: 1px solid rgba(201,168,76,0.30);
    }
    .eg-role-badge.superadmin {
      background: rgba(10,59,47,0.10); color: var(--eg-forest);
      border-color: rgba(10,59,47,0.20);
    }

    .eg-action-edit {
      display: inline-flex; align-items: center; gap: 5px;
      background: var(--eg-light); border: 1px solid var(--eg-border);
      color: var(--eg-forest); font-size: 12.5px; font-weight: 600;
      cursor: pointer; padding: 5px 12px; border-radius: 7px;
      transition: all .2s; text-decoration: none;
    }
    .eg-action-edit:hover { background: var(--eg-forest); color: #fff; border-color: var(--eg-forest); }

    .eg-action-more {
      background: none; border: none;
      color: var(--eg-muted); font-size: 18px;
      cursor: pointer; padding: 4px 6px; border-radius: 6px;
      transition: color .2s, background .2s; margin-left: 4px;
    }
    .eg-action-more:hover { color: var(--eg-forest); background: var(--eg-light); }

    .eg-alert-error {
      background: #fdf0ef; border: 1px solid #f5c6c3; color: #c0392b;
      border-radius: 12px; padding: 14px 18px; font-size: 14px;
      margin-bottom: 24px; display: flex; align-items: center; gap: 10px;
    }

    .eg-empty { text-align: center; padding: 56px 20px; color: var(--eg-muted); }
    .eg-empty i { font-size: 44px; margin-bottom: 14px; display: block; opacity: .35; }
    .eg-empty p { font-size: 14px; }

    @media (max-width: 575px) {
      .eg-content { padding: 18px 14px 36px; }
      .eg-stat-num { font-size: 30px; }
    }
  </style>
</head>
<body>

<div class="eg-overlay" id="egOverlay" onclick="closeSidebar()"></div>

<!-- ══ SIDEBAR ══════════════════════════════════════════════════════════════ -->
<aside class="eg-sidebar" id="egSidebar">

  <a href="Employeedashboard.php" class="eg-sidebar-logo">
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
      <?php foreach ($navItems as $item):
        $isPayments = ($item['label'] === 'Manage Payments');
        $extraClass = $isPayments ? ' nav-payments' : '';
      ?>
        <a
          href="<?= htmlspecialchars($item['href']) ?>"
          class="eg-nav-item<?= $item['label'] === $activeNav ? ' active' : '' ?><?= $extraClass ?>"
        >
          <i class="bi <?= htmlspecialchars($item['icon']) ?>"></i>
          <?= htmlspecialchars($item['label']) ?>
          <?php if ($isPayments): ?>
            <span class="nav-new-pill">New</span>
          <?php endif; ?>
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

<!-- ══ MAIN ══════════════════════════════════════════════════════════════════ -->
<div class="eg-main">

  <header class="eg-topbar">
    <div class="eg-topbar-left">
      <button class="eg-hamburger" onclick="toggleSidebar()" aria-label="Toggle sidebar">
        <i class="bi bi-list" id="hamburgerIcon"></i>
      </button>
      <div class="eg-topbar-brand">
        <div class="eg-tb-name">EVERGREEN</div>
        <div class="eg-tb-page">Dashboard</div>
      </div>
      <div class="eg-breadcrumb">
        <span>Staff Portal</span>
        <span class="bc-sep"><i class="bi bi-chevron-right" style="font-size:10px;"></i></span>
        <span class="bc-active">Dashboard</span>
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
          <div class="dd-empnum"><?= htmlspecialchars($adminEmpNum) ?></div>
        </div>
        <div class="divider"></div>
        <a href="logout.php" class="logout-link">
          <i class="bi bi-box-arrow-right"></i> Sign Out
        </a>
      </div>
    </div>
  </header>

  <main class="eg-content">

    <div class="eg-page-header">
      <h1 class="eg-page-title">Dashboard</h1>
      <p class="eg-page-sub">Welcome back, <?= $adminName ?>. Here's your overview.</p>
    </div>

    <?php if ($error): ?>
      <div class="eg-alert-error">
        <i class="bi bi-exclamation-circle-fill"></i>
        <?= $error ?>
      </div>
    <?php endif; ?>

    <!-- ── Stat Cards ── -->
    <div class="row g-3">
      <div class="col-12 col-sm-6 col-lg-4">
        <div class="eg-stat-card highlight">
          <div class="eg-stat-icon"><i class="bi bi-people-fill"></i></div>
          <div class="eg-stat-label">Total Officers</div>
          <div class="eg-stat-num"><?= $stats['totalOfficers'] ?></div>
          <div class="eg-stat-sub">All registered accounts</div>
        </div>
      </div>
      <div class="col-12 col-sm-6 col-lg-4">
        <div class="eg-stat-card">
          <div class="eg-stat-icon"><i class="bi bi-person-check-fill"></i></div>
          <div class="eg-stat-label">Active Accounts</div>
          <div class="eg-stat-num"><?= $stats['activeAccounts'] ?></div>
          <div class="eg-stat-sub">Currently operational</div>
        </div>
      </div>
      <div class="col-12 col-sm-6 col-lg-4">
        <div class="eg-stat-card gold-card">
          <div class="eg-stat-icon"><i class="bi bi-person-x-fill"></i></div>
          <div class="eg-stat-label">Inactive Accounts</div>
          <div class="eg-stat-num"><?= $stats['inactiveAccounts'] ?></div>
          <div class="eg-stat-sub">Pending reactivation</div>
        </div>
      </div>
    </div>

    <!-- ── Officers Table ── -->
    <div class="eg-section-header">
      <div class="eg-section-title-wrap">
        <div class="eg-section-title">Loan Officers &amp; Admins</div>
        <div class="eg-section-sub">Manage all registered officer accounts</div>
      </div>
    </div>

    <div class="eg-table-card">
      <div class="eg-table-toolbar">
        <div class="eg-search-wrap">
          <i class="bi bi-search"></i>
          <input
            type="text"
            class="eg-search-input"
            id="officerSearch"
            placeholder="Search by name or employee ID…"
            oninput="filterTable()"
          />
        </div>
        <select class="eg-filter-select" id="statusFilter" onchange="filterTable()">
          <option value="">All Status</option>
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </select>
      </div>

      <div style="overflow-x:auto;">
        <table class="eg-table" id="officersTable">
          <thead>
            <tr>
              <th>Officer</th>
              <th>Email</th>
              <th>Role</th>
              <th>Status</th>
              <th>Created</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody id="officersBody">
            <?php if (empty($officers)): ?>
              <tr>
                <td colspan="6">
                  <div class="eg-empty">
                    <i class="bi bi-people"></i>
                    <p>No officer accounts found.</p>
                  </div>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($officers as $officer):
                $initials = '';
                $parts    = explode(' ', trim($officer['full_name']));
                foreach (array_slice($parts, 0, 2) as $p) { $initials .= strtoupper($p[0] ?? ''); }
                $statusLower = strtolower($officer['status']);
                $roleLower   = strtolower($officer['role']);
                $isSA        = ($roleLower === 'superadmin' || $roleLower === 'super admin');
                $createdDate = $officer['created_at'] ? date('M d, Y', strtotime($officer['created_at'])) : '—';
              ?>
                <tr
                  data-name="<?= strtolower(htmlspecialchars($officer['full_name'])) ?>"
                  data-empnum="<?= strtolower(htmlspecialchars($officer['employee_number'])) ?>"
                  data-status="<?= $statusLower ?>"
                >
                  <td>
                    <div class="eg-officer-cell">
                      <div class="eg-officer-avatar"><?= htmlspecialchars($initials) ?></div>
                      <div>
                        <div class="eg-officer-name"><?= htmlspecialchars($officer['full_name']) ?></div>
                        <div class="eg-officer-empnum"><?= htmlspecialchars($officer['employee_number']) ?></div>
                      </div>
                    </div>
                  </td>
                  <td style="color:var(--eg-muted);"><?= htmlspecialchars($officer['officer_email']) ?></td>
                  <td>
                    <span class="eg-role-badge<?= $isSA ? ' superadmin' : '' ?>">
                      <?= htmlspecialchars($officer['role']) ?>
                    </span>
                  </td>
                  <td>
                    <span class="eg-badge <?= $statusLower ?>">
                      <?= htmlspecialchars($officer['status']) ?>
                    </span>
                  </td>
                  <td style="color:var(--eg-muted); font-size:13px;"><?= $createdDate ?></td>
                  <td>
                    <a href="edit_officer.php?id=<?= (int)$officer['id'] ?>" class="eg-action-edit">
                      <i class="bi bi-pencil-fill" style="font-size:11px;"></i> Edit
                    </a>
                    <button class="eg-action-more" title="More options">
                      <i class="bi bi-three-dots-vertical"></i>
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
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
  document.addEventListener('click', function (e) {
    const wrap = document.querySelector('.eg-profile-wrap');
    if (wrap && !wrap.contains(e.target)) {
      document.getElementById('profileDropdown').classList.remove('show');
    }
  });

  function filterTable() {
    const q      = document.getElementById('officerSearch').value.toLowerCase().trim();
    const status = document.getElementById('statusFilter').value.toLowerCase();
    const rows   = document.querySelectorAll('#officersBody tr[data-name]');
    rows.forEach(row => {
      const name   = row.dataset.name   || '';
      const empnum = row.dataset.empnum || '';
      const st     = row.dataset.status || '';
      const matchQ = !q || name.includes(q) || empnum.includes(q);
      const matchS = !status || st === status;
      row.style.display = (matchQ && matchS) ? '' : 'none';
    });
  }
</script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
</body>
</html>