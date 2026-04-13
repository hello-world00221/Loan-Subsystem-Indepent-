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

$approvals   = [];
$rejections  = [];
$stats       = ['totalApprovals' => 0, 'totalRejections' => 0, 'totalActions' => 0];
$officers    = [];
$error       = null;

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

    // ── Fetch approvals with officer info ────────────────────────────────────
    $stmt = $pdo->query(
        "SELECT
            la.id,
            la.loan_application_id,
            la.approved_by,
            la.approved_by_user_id,
            la.approved_at,
            o.full_name     AS officer_full_name,
            o.employee_number AS officer_emp_num,
            o.officer_email  AS officer_email,
            o.role           AS officer_role
         FROM loan_approvals la
         LEFT JOIN officers o ON o.id = la.approved_by_user_id
         ORDER BY la.approved_at DESC"
    );
    $approvals = $stmt->fetchAll();

    // ── Fetch rejections with officer info ───────────────────────────────────
    $stmt = $pdo->query(
        "SELECT
            lr.id,
            lr.loan_application_id,
            lr.rejected_by,
            lr.rejected_by_user_id,
            lr.rejected_at,
            lr.rejection_remarks,
            o.full_name      AS officer_full_name,
            o.employee_number AS officer_emp_num,
            o.officer_email   AS officer_email,
            o.role            AS officer_role
         FROM loan_rejections lr
         LEFT JOIN officers o ON o.id = lr.rejected_by_user_id
         ORDER BY lr.rejected_at DESC"
    );
    $rejections = $stmt->fetchAll();

    $stats['totalApprovals']  = count($approvals);
    $stats['totalRejections'] = count($rejections);
    $stats['totalActions']    = $stats['totalApprovals'] + $stats['totalRejections'];

    // ── Unique officers who acted ────────────────────────────────────────────
    $officerMap = [];
    foreach (array_merge($approvals, $rejections) as $row) {
        $uid = $row['approved_by_user_id'] ?? $row['rejected_by_user_id'] ?? null;
        $name = $row['officer_full_name'] ?? $row['approved_by'] ?? $row['rejected_by'] ?? null;
        if ($uid && $name && !isset($officerMap[$uid])) {
            $officerMap[$uid] = $name;
        }
    }
    $officers = $officerMap;

} catch (PDOException $e) {
    $error = "Database error: " . htmlspecialchars($e->getMessage());
}

// ── Session info ─────────────────────────────────────────────────────────────
$adminName    = htmlspecialchars($_SESSION['admin_name']            ?? 'Staff User');
$adminRole    = htmlspecialchars($_SESSION['admin_role']            ?? 'SuperAdmin');
$adminEmpNum  = htmlspecialchars($_SESSION['admin_employee_number'] ?? '');
$adminInitials = implode('', array_map(
    fn($w) => strtoupper($w[0]),
    array_slice(explode(' ', $adminName), 0, 2)
));

// ── Pass data to React ────────────────────────────────────────────────────────
function jsonSafe($val) {
    return json_encode($val, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
}

$approvalsJson  = jsonSafe($approvals);
$rejectionsJson = jsonSafe($rejections);
$statsJson      = jsonSafe($stats);
$officersJson   = jsonSafe($officers);
$flashError     = $error ? json_encode($error) : 'null';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Evergreen – Audit Logs</title>
  <link rel="icon" type="image/png" href="pictures/logo.png" />

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet" />

  <style>
    :root {
      --eg-forest:    #0a3b2f;
      --eg-deep:      #062620;
      --eg-mid:       #1a6b55;
      --eg-light:     #e8f4ef;
      --eg-gold:      #c9a84c;
      --eg-gold-l:    #e8c96b;
      --eg-text:      #1c2b25;
      --eg-muted:     #6b8c7e;
      --eg-border:    #d4e6de;
      --eg-bg:        #f4f8f6;
      --eg-card:      #ffffff;
      --eg-sidebar-w: 262px;
      --eg-topbar-h:  62px;
      --eg-approve:   #1a6b55;
      --eg-approve-bg:#e8f4ef;
      --eg-approve-bd:#b5d6c8;
      --eg-reject:    #c0392b;
      --eg-reject-bg: #fdf0ef;
      --eg-reject-bd: #f5c6c3;
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
      width: var(--eg-sidebar-w); height: 100vh;
      background: linear-gradient(180deg, var(--eg-deep) 0%, var(--eg-forest) 60%, #0e4535 100%);
      z-index: 1040; display: flex; flex-direction: column;
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
      width: 36px; height: 36px; background: var(--eg-gold);
      border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }
    .eg-sidebar-logo-icon img {
      height: 22px; width: auto;
      filter: brightness(0) saturate(100%) invert(10%) sepia(40%) saturate(800%) hue-rotate(105deg) brightness(40%);
    }
    .eg-sidebar-logo-text { font-family: 'Playfair Display', serif; font-size: 17px; font-weight: 700; color: #fff; letter-spacing: .8px; line-height: 1.1; }
    .eg-sidebar-logo-sub  { font-size: 10px; color: rgba(255,255,255,0.45); letter-spacing: .3px; }

    .eg-nav-toggle-btn {
      display: flex; align-items: center; gap: 8px; width: 100%;
      background: none; border: none; color: rgba(255,255,255,0.50);
      padding: 12px 22px; font-size: 10.5px; font-weight: 700; letter-spacing: 1.5px;
      cursor: pointer; transition: color .2s; text-transform: uppercase; font-family: 'DM Sans', sans-serif;
    }
    .eg-nav-toggle-btn:hover { color: var(--eg-gold-l); }
    .eg-nav-toggle-btn .chevron { margin-left: auto; transition: transform .25s; }
    .eg-nav-toggle-btn.collapsed .chevron { transform: rotate(-90deg); }

    .eg-nav-collapse { overflow: hidden; max-height: 500px; transition: max-height .3s ease; }
    .eg-nav-collapse.hidden { max-height: 0; }

    .eg-nav-item {
      display: flex; align-items: center; gap: 10px;
      padding: 11px 22px 11px 30px;
      color: rgba(255,255,255,0.60); text-decoration: none;
      font-size: 14px; font-weight: 500;
      transition: background .18s, color .18s;
      border-left: 3px solid transparent; font-family: 'DM Sans', sans-serif;
    }
    .eg-nav-item:hover { background: rgba(255,255,255,0.07); color: #fff; }
    .eg-nav-item.active { color: var(--eg-gold-l); border-left-color: var(--eg-gold); background: rgba(201,168,76,0.10); }
    .eg-nav-item i { font-size: 16px; width: 20px; text-align: center; }

    .eg-sidebar-footer { margin-top: auto; border-top: 1px solid rgba(255,255,255,0.08); padding: 16px 22px; }
    .eg-sidebar-footer-user { display: flex; align-items: center; gap: 10px; }
    .eg-sidebar-avatar {
      width: 34px; height: 34px; border-radius: 50%;
      background: var(--eg-gold); display: flex; align-items: center; justify-content: center;
      font-size: 13px; font-weight: 700; color: var(--eg-deep); flex-shrink: 0;
    }
    .eg-sidebar-uname { font-size: 13px; font-weight: 600; color: #fff; line-height: 1.2; }
    .eg-sidebar-urole { font-size: 11px; color: var(--eg-gold-l); }

    .eg-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.50); z-index: 1039; }
    .eg-overlay.show { display: block; }
    @media (min-width: 992px) { .eg-overlay { display: none !important; } }

    /* ══ TOP BAR ══════════════════════════════════════════════════════════ */
    .eg-topbar {
      position: sticky; top: 0; height: var(--eg-topbar-h);
      background: linear-gradient(90deg, var(--eg-deep) 0%, var(--eg-forest) 100%);
      display: flex; align-items: center; justify-content: space-between;
      padding: 0 26px; z-index: 1030; box-shadow: 0 2px 16px rgba(6,38,32,0.28);
    }
    .eg-topbar-left { display: flex; align-items: center; gap: 14px; }

    .eg-hamburger {
      background: none; border: none; color: rgba(255,255,255,0.80); font-size: 22px;
      cursor: pointer; padding: 4px 8px; border-radius: 6px;
      transition: color .2s, background .2s; display: none;
    }
    @media (max-width: 991px) { .eg-hamburger { display: flex; } }
    .eg-hamburger:hover { color: var(--eg-gold-l); background: rgba(255,255,255,0.08); }

    .eg-topbar-brand { display: none; }
    @media (max-width: 991px) { .eg-topbar-brand { display: block; } }
    .eg-topbar-brand .eg-tb-name { font-family: 'Playfair Display', serif; color: #fff; font-size: 16px; font-weight: 700; }
    .eg-topbar-brand .eg-tb-page { font-size: 11px; color: rgba(255,255,255,0.50); }

    .eg-breadcrumb { display: flex; align-items: center; gap: 6px; font-size: 13px; color: rgba(255,255,255,0.55); }
    @media (max-width: 991px) { .eg-breadcrumb { display: none; } }
    .eg-breadcrumb .bc-sep { opacity: 0.4; }
    .eg-breadcrumb .bc-active { color: #fff; font-weight: 600; }

    .eg-profile-wrap { position: relative; }
    .eg-profile-btn {
      display: flex; align-items: center; gap: 10px;
      background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.14);
      border-radius: 10px; padding: 6px 14px 6px 8px;
      color: #fff; cursor: pointer; transition: background .2s; font-family: 'DM Sans', sans-serif;
    }
    .eg-profile-btn:hover { background: rgba(255,255,255,0.15); }
    .eg-avatar { width: 32px; height: 32px; border-radius: 50%; background: var(--eg-gold); display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; color: var(--eg-deep); flex-shrink: 0; }
    .eg-profile-info { text-align: left; }
    .eg-profile-name { font-size: 13px; font-weight: 600; line-height: 1.2; }
    .eg-profile-role { font-size: 11px; color: var(--eg-gold-l); line-height: 1.2; }

    .eg-profile-dropdown {
      position: absolute; top: calc(100% + 8px); right: 0;
      background: #fff; border-radius: 12px; box-shadow: 0 8px 32px rgba(6,38,32,0.18);
      min-width: 190px; overflow: hidden; z-index: 2000; display: none;
      animation: dropIn .18s ease; border: 1px solid var(--eg-border);
    }
    .eg-profile-dropdown.show { display: block; }
    @keyframes dropIn { from { opacity:0; transform:translateY(-6px); } to { opacity:1; transform:translateY(0); } }
    .eg-profile-dropdown .dd-header { padding: 14px 16px 10px; border-bottom: 1px solid var(--eg-border); }
    .eg-profile-dropdown .dd-header .dd-name { font-size: 13.5px; font-weight: 700; color: var(--eg-text); }
    .eg-profile-dropdown .dd-header .dd-empnum { font-size: 11px; color: var(--eg-muted); }
    .eg-profile-dropdown a { display: flex; align-items: center; gap: 8px; padding: 10px 16px; color: var(--eg-text); text-decoration: none; font-size: 13.5px; transition: background .15s; }
    .eg-profile-dropdown a:hover { background: var(--eg-bg); }
    .eg-profile-dropdown a i { width: 18px; color: var(--eg-muted); }
    .eg-profile-dropdown .divider { height: 1px; background: var(--eg-border); margin: 4px 0; }
    .eg-profile-dropdown a.logout-link { color: #c0392b; }
    .eg-profile-dropdown a.logout-link i { color: #c0392b; }

    /* ══ MAIN ══════════════════════════════════════════════════════════════ */
    .eg-main { min-height: 100vh; transition: margin-left .28s; }
    .eg-content { padding: 30px 30px 48px; }
    @media (max-width: 575px) { .eg-content { padding: 18px 14px 36px; } }

    #audit-logs-root { width: 100%; }
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

  <div style="padding:10px 0;flex:1;">
    <button class="eg-nav-toggle-btn" id="navToggleBtn" onclick="toggleNav()">
      <i class="bi bi-grid-fill" style="font-size:11px;"></i>
      Navigation
      <i class="bi bi-chevron-down chevron" style="font-size:10px;"></i>
    </button>
    <div class="eg-nav-collapse" id="navCollapse">
      <a href="Employeedashboard.php" class="eg-nav-item"><i class="bi bi-speedometer2"></i> Dashboard</a>
      <a href="add_officer.php"       class="eg-nav-item"><i class="bi bi-person-gear"></i> Account Management</a>
      <a href="audit_logs.php"        class="eg-nav-item active"><i class="bi bi-journal-text"></i> Audit Logs</a>
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
        <div class="eg-tb-page">Audit Logs</div>
      </div>
      <div class="eg-breadcrumb">
        <span>Staff Portal</span>
        <span class="bc-sep"><i class="bi bi-chevron-right" style="font-size:10px;"></i></span>
        <a href="Employeedashboard.php" style="color:rgba(255,255,255,0.55);text-decoration:none;">Dashboard</a>
        <span class="bc-sep"><i class="bi bi-chevron-right" style="font-size:10px;"></i></span>
        <span class="bc-active">Audit Logs</span>
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
        <a href="profile.php"><i class="bi bi-person"></i> My Profile</a>
        <a href="settings.php"><i class="bi bi-gear"></i> Settings</a>
        <div class="divider"></div>
        <a href="logout.php" class="logout-link"><i class="bi bi-box-arrow-right"></i> Sign Out</a>
      </div>
    </div>
  </header>

  <main class="eg-content">
    <div id="audit-logs-root"></div>
  </main>
</div>

<!-- Bootstrap JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>

<!-- React + Babel -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/react/18.2.0/umd/react.production.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/react-dom/18.2.0/umd/react-dom.production.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/babel-standalone/7.23.2/babel.min.js"></script>

<!-- PHP → JS data bridge -->
<script>
  window.EG_APPROVALS  = <?= $approvalsJson ?>;
  window.EG_REJECTIONS = <?= $rejectionsJson ?>;
  window.EG_STATS      = <?= $statsJson ?>;
  window.EG_OFFICERS   = <?= $officersJson ?>;
  window.EG_FLASH_ERR  = <?= $flashError ?>;
</script>

<!-- Sidebar / Profile JS -->
<script>
  function toggleSidebar() {
    const s = document.getElementById('egSidebar');
    const o = document.getElementById('egOverlay');
    const i = document.getElementById('hamburgerIcon');
    const open = s.classList.toggle('open');
    o.classList.toggle('show', open);
    i.className = open ? 'bi bi-x-lg' : 'bi bi-list';
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
    if (wrap && !wrap.contains(e.target)) document.getElementById('profileDropdown').classList.remove('show');
  });
</script>

<!-- React Application -->
<script type="text/babel">
const { useState, useMemo, useRef } = React;

/* ── Design tokens ────────────────────────────────────────────────────── */
const T = {
  forest:    '#0a3b2f',
  deep:      '#062620',
  mid:       '#1a6b55',
  light:     '#e8f4ef',
  gold:      '#c9a84c',
  goldL:     '#e8c96b',
  text:      '#1c2b25',
  muted:     '#6b8c7e',
  border:    '#d4e6de',
  bg:        '#f4f8f6',
  card:      '#ffffff',
  approve:   '#1a6b55',
  approveBg: '#e8f4ef',
  approveBd: '#b5d6c8',
  reject:    '#c0392b',
  rejectBg:  '#fdf0ef',
  rejectBd:  '#f5c6c3',
};

/* ── Helpers ───────────────────────────────────────────────────────────── */
function initials(name = '') {
  return (name || '').trim().split(' ').slice(0, 2).map(w => (w[0] || '').toUpperCase()).join('') || '?';
}

function formatDateTime(dt) {
  if (!dt) return '—';
  const d = new Date(dt);
  return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: '2-digit' })
    + ' · '
    + d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
}

function timeAgo(dt) {
  if (!dt) return '';
  const diff = Math.floor((Date.now() - new Date(dt)) / 1000);
  if (diff < 60)   return `${diff}s ago`;
  if (diff < 3600) return `${Math.floor(diff/60)}m ago`;
  if (diff < 86400) return `${Math.floor(diff/3600)}h ago`;
  return `${Math.floor(diff/86400)}d ago`;
}

/* ── Stat Card ─────────────────────────────────────────────────────────── */
function StatCard({ icon, label, value, sub, variant }) {
  const isHighlight = variant === 'highlight';
  const isApprove   = variant === 'approve';
  const isReject    = variant === 'reject';

  const bg    = isHighlight ? `linear-gradient(135deg, ${T.deep} 0%, ${T.forest} 60%, #0e4535 100%)`
              : isApprove   ? `linear-gradient(135deg, #f0faf6 0%, #e3f5ee 100%)`
              : isReject    ? `linear-gradient(135deg, #fdf5f4 0%, #fdecea 100%)`
              : T.card;
  const bd    = isHighlight ? 'transparent'
              : isApprove   ? T.approveBd
              : isReject    ? T.rejectBd
              : T.border;
  const numC  = isHighlight ? '#fff'
              : isApprove   ? T.approve
              : isReject    ? T.reject
              : T.forest;
  const lblC  = isHighlight ? 'rgba(255,255,255,0.65)' : T.muted;
  const subC  = isHighlight ? 'rgba(255,255,255,0.55)' : T.muted;
  const iconBg = isHighlight ? 'rgba(255,255,255,0.15)'
               : isApprove   ? 'rgba(26,107,85,0.12)'
               : isReject    ? 'rgba(192,57,43,0.10)'
               : T.light;
  const iconC  = isHighlight ? T.goldL
               : isApprove   ? T.approve
               : isReject    ? T.reject
               : T.forest;

  return (
    <div style={{
      background: bg, borderRadius: 16, padding: '22px 24px',
      border: `1.5px solid ${bd}`,
      boxShadow: '0 1px 6px rgba(10,59,47,0.06), 0 4px 16px rgba(10,59,47,0.04)',
      position: 'relative', overflow: 'hidden', height: '100%',
      transition: 'transform .2s, box-shadow .2s',
    }}>
      <div style={{
        position: 'absolute', width: 80, height: 80, borderRadius: '50%',
        background: isHighlight ? 'rgba(255,255,255,0.06)' : 'rgba(10,59,47,0.04)',
        top: -20, right: -20,
      }} />
      <div style={{
        width: 38, height: 38, borderRadius: 10, background: iconBg,
        display: 'flex', alignItems: 'center', justifyContent: 'center',
        marginBottom: 14, position: 'relative', zIndex: 1,
      }}>
        <i className={`bi ${icon}`} style={{ fontSize: 17, color: iconC }}></i>
      </div>
      <div style={{ fontSize: 11, fontWeight: 700, textTransform: 'uppercase', letterSpacing: .7, color: lblC, marginBottom: 6, position: 'relative', zIndex: 1 }}>{label}</div>
      <div style={{ fontSize: 36, fontWeight: 800, color: numC, lineHeight: 1, marginBottom: 4, position: 'relative', zIndex: 1 }}>{value}</div>
      <div style={{ fontSize: 13, color: subC, position: 'relative', zIndex: 1 }}>{sub}</div>
    </div>
  );
}

/* ── Officer Avatar Cell ───────────────────────────────────────────────── */
function OfficerCell({ name, empNum, email, role }) {
  const isKnown = !!name;
  const displayName = name || 'Unknown Officer';
  return (
    <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
      <div style={{
        width: 34, height: 34, borderRadius: '50%', flexShrink: 0,
        background: isKnown ? T.light : '#f3f4f6',
        border: `1.5px solid ${isKnown ? T.border : '#e5e7eb'}`,
        display: 'flex', alignItems: 'center', justifyContent: 'center',
        fontSize: 12, fontWeight: 700,
        color: isKnown ? T.forest : '#9ca3af',
      }}>
        {initials(displayName)}
      </div>
      <div>
        <div style={{ fontWeight: 600, fontSize: 13.5, color: isKnown ? T.text : '#9ca3af' }}>{displayName}</div>
        {empNum && <div style={{ fontSize: 11, color: T.muted, fontFamily: 'Courier New, monospace' }}>{empNum}</div>}
        {!empNum && email && <div style={{ fontSize: 11, color: T.muted }}>{email}</div>}
      </div>
    </div>
  );
}

/* ── Remarks Modal ─────────────────────────────────────────────────────── */
function RemarksModal({ open, remarks, loanId, onClose }) {
  if (!open) return null;
  return (
    <div style={{
      position: 'fixed', inset: 0, background: 'rgba(6,38,32,0.55)',
      zIndex: 9999, display: 'flex', alignItems: 'center', justifyContent: 'center',
      padding: 20, animation: 'fadeIn .15s ease',
    }} onClick={onClose}>
      <div style={{
        background: '#fff', borderRadius: 18, padding: '28px 28px 24px',
        maxWidth: 480, width: '100%',
        boxShadow: '0 16px 56px rgba(6,38,32,0.22)',
        animation: 'scaleIn .18s ease',
      }} onClick={e => e.stopPropagation()}>
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 16 }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
            <div style={{
              width: 36, height: 36, borderRadius: 9,
              background: T.rejectBg, display: 'flex', alignItems: 'center', justifyContent: 'center',
            }}>
              <i className="bi bi-chat-square-text-fill" style={{ color: T.reject, fontSize: 16 }}></i>
            </div>
            <div>
              <div style={{ fontFamily: 'Playfair Display, serif', fontSize: 17, fontWeight: 700, color: T.forest }}>
                Rejection Remarks
              </div>
              <div style={{ fontSize: 11.5, color: T.muted }}>Loan Application #{loanId}</div>
            </div>
          </div>
          <button onClick={onClose} style={{
            background: T.bg, border: `1px solid ${T.border}`, borderRadius: 8,
            width: 32, height: 32, cursor: 'pointer', color: T.muted,
            display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 16,
          }}>×</button>
        </div>
        <div style={{
          background: T.rejectBg, border: `1px solid ${T.rejectBd}`,
          borderRadius: 10, padding: '14px 16px',
          fontSize: 14, color: T.text, lineHeight: 1.7,
          whiteSpace: 'pre-wrap', wordBreak: 'break-word',
        }}>
          {remarks || 'No remarks provided.'}
        </div>
      </div>
    </div>
  );
}

/* ── Log Table ─────────────────────────────────────────────────────────── */
function LogTable({ rows, type, searchQ, officerFilter, dateFilter }) {
  const [remarksModal, setRemarksModal] = useState(null);
  const [page, setPage] = useState(1);
  const PER_PAGE = 10;

  const isApprove = type === 'approve';

  const filtered = useMemo(() => {
    return rows.filter(row => {
      const name   = (row.officer_full_name || row.approved_by || row.rejected_by || '').toLowerCase();
      const loanId = String(row.loan_application_id || '');
      const empNum = (row.officer_emp_num || '').toLowerCase();
      const dt     = isApprove ? row.approved_at : row.rejected_at;
      const dateStr = dt ? dt.slice(0, 10) : '';

      const matchQ = !searchQ || name.includes(searchQ) || loanId.includes(searchQ) || empNum.includes(searchQ);
      const matchO = !officerFilter || String(row.approved_by_user_id || row.rejected_by_user_id) === officerFilter;
      const matchD = !dateFilter || dateStr === dateFilter;
      return matchQ && matchO && matchD;
    });
  }, [rows, searchQ, officerFilter, dateFilter]);

  const totalPages = Math.max(1, Math.ceil(filtered.length / PER_PAGE));
  const safePage   = Math.min(page, totalPages);
  const paged      = filtered.slice((safePage - 1) * PER_PAGE, safePage * PER_PAGE);

  const accentColor = isApprove ? T.approve : T.reject;
  const accentBg    = isApprove ? T.approveBg : T.rejectBg;
  const accentBd    = isApprove ? T.approveBd : T.rejectBd;

  return (
    <>
      <RemarksModal
        open={!!remarksModal}
        remarks={remarksModal?.remarks}
        loanId={remarksModal?.loanId}
        onClose={() => setRemarksModal(null)}
      />

      <div style={{
        background: T.card, borderRadius: 16,
        border: `1.5px solid ${T.border}`,
        boxShadow: '0 1px 6px rgba(10,59,47,0.06)',
        overflow: 'hidden',
      }}>
        {/* Table header strip */}
        <div style={{
          padding: '14px 20px',
          background: isApprove
            ? `linear-gradient(90deg, ${T.approveBg} 0%, #f0faf6 100%)`
            : `linear-gradient(90deg, ${T.rejectBg} 0%, #fdf5f4 100%)`,
          borderBottom: `1.5px solid ${accentBd}`,
          display: 'flex', alignItems: 'center', gap: 10,
        }}>
          <div style={{
            width: 30, height: 30, borderRadius: 8, flexShrink: 0,
            background: isApprove ? 'rgba(26,107,85,0.14)' : 'rgba(192,57,43,0.12)',
            display: 'flex', alignItems: 'center', justifyContent: 'center',
          }}>
            <i className={`bi ${isApprove ? 'bi-check-circle-fill' : 'bi-x-circle-fill'}`}
               style={{ color: accentColor, fontSize: 14 }}></i>
          </div>
          <span style={{ fontWeight: 700, fontSize: 13.5, color: accentColor }}>
            {isApprove ? 'Approved' : 'Rejected'} Loans
          </span>
          <span style={{
            marginLeft: 'auto',
            background: accentBg, border: `1px solid ${accentBd}`,
            color: accentColor, borderRadius: 20,
            padding: '2px 10px', fontSize: 12, fontWeight: 700,
          }}>
            {filtered.length} record{filtered.length !== 1 ? 's' : ''}
          </span>
        </div>

        <div style={{ overflowX: 'auto' }}>
          <table style={{ width: '100%', borderCollapse: 'collapse' }}>
            <thead>
              <tr style={{ background: T.bg }}>
                {['Loan App #', 'Loan Officer', isApprove ? 'Approved At' : 'Rejected At', isApprove ? '' : 'Remarks', 'Time Ago'].map((h, i) => (
                  h !== '' && (
                    <th key={i} style={{
                      padding: '12px 18px', fontSize: 10.5, fontWeight: 700,
                      textTransform: 'uppercase', letterSpacing: .7,
                      color: T.muted, borderBottom: `1.5px solid ${T.border}`,
                      whiteSpace: 'nowrap', textAlign: 'left',
                    }}>{h}</th>
                  )
                ))}
              </tr>
            </thead>
            <tbody>
              {paged.length === 0 ? (
                <tr>
                  <td colSpan={isApprove ? 4 : 5} style={{ textAlign: 'center', padding: '48px 20px', color: T.muted }}>
                    <i className="bi bi-inbox" style={{ fontSize: 36, display: 'block', marginBottom: 10, opacity: .3 }}></i>
                    <div style={{ fontSize: 14 }}>No records found.</div>
                  </td>
                </tr>
              ) : paged.map((row, idx) => {
                const dt        = isApprove ? row.approved_at : row.rejected_at;
                const officerName = row.officer_full_name || (isApprove ? row.approved_by : row.rejected_by);
                return (
                  <tr key={row.id} style={{
                    borderBottom: `1px solid #eef4f0`,
                    background: idx % 2 === 0 ? '#fff' : '#fafcfb',
                    transition: 'background .15s',
                  }}>
                    {/* Loan App # */}
                    <td style={{ padding: '13px 18px', verticalAlign: 'middle' }}>
                      <div style={{
                        display: 'inline-flex', alignItems: 'center', gap: 6,
                        background: accentBg, border: `1px solid ${accentBd}`,
                        borderRadius: 7, padding: '4px 10px',
                        fontSize: 12.5, fontWeight: 700, color: accentColor,
                        fontFamily: 'Courier New, monospace',
                      }}>
                        <i className={`bi ${isApprove ? 'bi-check2' : 'bi-x'}`} style={{ fontSize: 12 }}></i>
                        #{row.loan_application_id ?? '—'}
                      </div>
                    </td>

                    {/* Officer */}
                    <td style={{ padding: '13px 18px', verticalAlign: 'middle' }}>
                      <OfficerCell
                        name={officerName}
                        empNum={row.officer_emp_num}
                        email={row.officer_email}
                        role={row.officer_role}
                      />
                    </td>

                    {/* Timestamp */}
                    <td style={{ padding: '13px 18px', verticalAlign: 'middle' }}>
                      <div style={{ fontSize: 13, color: T.text, fontWeight: 500 }}>
                        {formatDateTime(dt).split(' · ')[0]}
                      </div>
                      <div style={{ fontSize: 11.5, color: T.muted, marginTop: 2 }}>
                        {formatDateTime(dt).split(' · ')[1]}
                      </div>
                    </td>

                    {/* Remarks (rejections only) */}
                    {!isApprove && (
                      <td style={{ padding: '13px 18px', verticalAlign: 'middle' }}>
                        {row.rejection_remarks ? (
                          <button onClick={() => setRemarksModal({ remarks: row.rejection_remarks, loanId: row.loan_application_id })} style={{
                            display: 'inline-flex', alignItems: 'center', gap: 5,
                            background: T.rejectBg, border: `1px solid ${T.rejectBd}`,
                            color: T.reject, borderRadius: 7, padding: '5px 11px',
                            fontSize: 12, fontWeight: 600, cursor: 'pointer',
                            fontFamily: 'DM Sans, sans-serif', transition: 'all .2s',
                          }}>
                            <i className="bi bi-eye-fill" style={{ fontSize: 11 }}></i> View
                          </button>
                        ) : (
                          <span style={{ fontSize: 12, color: '#ccc' }}>—</span>
                        )}
                      </td>
                    )}

                    {/* Time ago */}
                    <td style={{ padding: '13px 18px', verticalAlign: 'middle' }}>
                      <span style={{ fontSize: 12, color: T.muted, fontStyle: 'italic' }}>
                        {timeAgo(dt)}
                      </span>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>

        {/* Pagination */}
        {totalPages > 1 && (
          <div style={{
            padding: '14px 20px', borderTop: `1px solid ${T.border}`,
            display: 'flex', alignItems: 'center', justifyContent: 'space-between',
            flexWrap: 'wrap', gap: 10,
          }}>
            <div style={{ fontSize: 12.5, color: T.muted }}>
              Showing {(safePage - 1) * PER_PAGE + 1}–{Math.min(safePage * PER_PAGE, filtered.length)} of {filtered.length}
            </div>
            <div style={{ display: 'flex', gap: 6 }}>
              <button
                onClick={() => setPage(p => Math.max(1, p - 1))}
                disabled={safePage === 1}
                style={{
                  padding: '5px 12px', borderRadius: 7, cursor: safePage === 1 ? 'not-allowed' : 'pointer',
                  border: `1.5px solid ${T.border}`, background: '#fff', color: T.muted,
                  fontSize: 13, fontFamily: 'DM Sans, sans-serif', opacity: safePage === 1 ? .4 : 1,
                }}
              >‹ Prev</button>
              {Array.from({ length: totalPages }, (_, i) => i + 1)
                .filter(p => p === 1 || p === totalPages || Math.abs(p - safePage) <= 1)
                .reduce((acc, p, i, arr) => {
                  if (i > 0 && p - arr[i-1] > 1) acc.push('…');
                  acc.push(p);
                  return acc;
                }, [])
                .map((p, i) => p === '…'
                  ? <span key={`e${i}`} style={{ padding: '5px 4px', color: T.muted, fontSize: 13 }}>…</span>
                  : (
                    <button key={p} onClick={() => setPage(p)} style={{
                      padding: '5px 11px', borderRadius: 7, cursor: 'pointer',
                      border: `1.5px solid ${p === safePage ? accentColor : T.border}`,
                      background: p === safePage ? accentBg : '#fff',
                      color: p === safePage ? accentColor : T.muted,
                      fontSize: 13, fontWeight: p === safePage ? 700 : 400,
                      fontFamily: 'DM Sans, sans-serif',
                    }}>{p}</button>
                  )
                )}
              <button
                onClick={() => setPage(p => Math.min(totalPages, p + 1))}
                disabled={safePage === totalPages}
                style={{
                  padding: '5px 12px', borderRadius: 7, cursor: safePage === totalPages ? 'not-allowed' : 'pointer',
                  border: `1.5px solid ${T.border}`, background: '#fff', color: T.muted,
                  fontSize: 13, fontFamily: 'DM Sans, sans-serif', opacity: safePage === totalPages ? .4 : 1,
                }}
              >Next ›</button>
            </div>
          </div>
        )}
      </div>
    </>
  );
}

/* ── Main App ────────────────────────────────────────────────────────────── */
function AuditLogsApp() {
  const approvals  = window.EG_APPROVALS  || [];
  const rejections = window.EG_REJECTIONS || [];
  const stats      = window.EG_STATS      || {};
  const officerMap = window.EG_OFFICERS   || {};

  const [activeTab, setActiveTab]       = useState('all');
  const [searchQ, setSearchQ]           = useState('');
  const [officerFilter, setOfficerFilter] = useState('');
  const [dateFilter, setDateFilter]     = useState('');
  const [searchFocused, setSearchFocused] = useState(false);

  /* unified feed for "All" tab */
  const allRows = useMemo(() => {
    const a = approvals.map(r => ({ ...r, _type: 'approve', _at: r.approved_at }));
    const j = rejections.map(r => ({ ...r, _type: 'reject',  _at: r.rejected_at }));
    return [...a, ...j].sort((x, y) => new Date(y._at) - new Date(x._at));
  }, [approvals, rejections]);

  /* officer select options */
  const officerOptions = useMemo(() => {
    return Object.entries(officerMap).map(([id, name]) => ({ value: id, label: name }));
  }, [officerMap]);

  const tabDef = [
    { key: 'all',      label: 'All Activity',  icon: 'bi-activity',         count: stats.totalActions  },
    { key: 'approve',  label: 'Approvals',     icon: 'bi-check-circle-fill', count: stats.totalApprovals  },
    { key: 'reject',   label: 'Rejections',    icon: 'bi-x-circle-fill',     count: stats.totalRejections },
  ];

  const inputStyle = (focused) => ({
    padding: '9px 12px 9px 34px', border: `1.5px solid ${focused ? T.forest : T.border}`,
    borderRadius: 9, fontFamily: 'DM Sans, sans-serif', fontSize: 13.5,
    color: T.text, background: focused ? '#fff' : T.bg, outline: 'none',
    transition: 'border-color .2s, box-shadow .2s',
    boxShadow: focused ? `0 0 0 3px rgba(10,59,47,0.08)` : 'none',
    width: '100%',
  });

  return (
    <>
      <style>{`
        @keyframes fadeIn  { from { opacity:0; } to { opacity:1; } }
        @keyframes scaleIn { from { opacity:0; transform:scale(.94); } to { opacity:1; transform:scale(1); } }
        @keyframes slideUp { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:translateY(0); } }
        .al-tab-btn { transition: all .2s !important; }
        .al-tab-btn:hover { background: rgba(10,59,47,0.06) !important; }
        .al-row-hover:hover { background: #f5faf8 !important; }
      `}</style>

      {/* Error flash */}
      {window.EG_FLASH_ERR && (
        <div style={{
          display: 'flex', alignItems: 'center', gap: 10,
          background: T.rejectBg, border: `1px solid ${T.rejectBd}`,
          color: T.reject, borderRadius: 12, padding: '13px 16px',
          fontSize: 14, marginBottom: 22,
        }}>
          <i className="bi bi-exclamation-circle-fill"></i>
          {window.EG_FLASH_ERR}
        </div>
      )}

      {/* Page header */}
      <div style={{ marginBottom: 28 }}>
        <h1 style={{ fontFamily: 'Playfair Display, serif', fontSize: 28, fontWeight: 700, color: T.forest, letterSpacing: -.2, margin: 0 }}>
          Audit Logs
        </h1>
        <p style={{ fontSize: 13.5, color: T.muted, marginTop: 3, margin: '3px 0 0' }}>
          Complete record of all loan approvals and rejections by Loan Officers
        </p>
      </div>

      {/* Stat cards */}
      <div className="row g-3" style={{ marginBottom: 28 }}>
        <div className="col-12 col-sm-6 col-lg-4">
          <StatCard icon="bi-activity"           label="Total Actions"    value={stats.totalActions}    sub="All recorded events"          variant="highlight" />
        </div>
        <div className="col-12 col-sm-6 col-lg-4">
          <StatCard icon="bi-check-circle-fill"  label="Total Approvals"  value={stats.totalApprovals}  sub="Loans approved by officers"   variant="approve" />
        </div>
        <div className="col-12 col-sm-6 col-lg-4">
          <StatCard icon="bi-x-circle-fill"      label="Total Rejections" value={stats.totalRejections} sub="Loans rejected by officers"   variant="reject" />
        </div>
      </div>

      {/* Tabs + Filters card */}
      <div style={{
        background: T.card, borderRadius: 16,
        border: `1.5px solid ${T.border}`,
        boxShadow: '0 1px 6px rgba(10,59,47,0.06)',
        marginBottom: 20, overflow: 'hidden',
      }}>
        {/* Tab row */}
        <div style={{
          display: 'flex', borderBottom: `1.5px solid ${T.border}`,
          padding: '0 6px', gap: 2, overflowX: 'auto',
          scrollbarWidth: 'none',
        }}>
          {tabDef.map(tab => {
            const active = activeTab === tab.key;
            const col = tab.key === 'approve' ? T.approve : tab.key === 'reject' ? T.reject : T.forest;
            return (
              <button key={tab.key} onClick={() => setActiveTab(tab.key)}
                className="al-tab-btn"
                style={{
                  display: 'flex', alignItems: 'center', gap: 7,
                  padding: '13px 16px', background: 'none', border: 'none',
                  cursor: 'pointer', fontFamily: 'DM Sans, sans-serif',
                  fontSize: 13.5, fontWeight: active ? 700 : 500,
                  color: active ? col : T.muted,
                  borderBottom: active ? `2.5px solid ${col}` : '2.5px solid transparent',
                  marginBottom: -1.5, whiteSpace: 'nowrap', transition: 'color .2s',
                }}>
                <i className={`bi ${tab.icon}`} style={{ fontSize: 14 }}></i>
                {tab.label}
                <span style={{
                  background: active ? (tab.key === 'approve' ? T.approveBg : tab.key === 'reject' ? T.rejectBg : T.light) : T.bg,
                  color: active ? col : T.muted,
                  border: `1px solid ${active ? (tab.key === 'approve' ? T.approveBd : tab.key === 'reject' ? T.rejectBd : T.border) : T.border}`,
                  borderRadius: 20, padding: '1px 8px', fontSize: 11.5, fontWeight: 700,
                }}>
                  {tab.count ?? 0}
                </span>
              </button>
            );
          })}
        </div>

        {/* Filter toolbar */}
        <div style={{ padding: '14px 18px', display: 'flex', gap: 10, flexWrap: 'wrap', alignItems: 'center' }}>
          {/* Search */}
          <div style={{ position: 'relative', flex: 1, minWidth: 180, maxWidth: 300 }}>
            <i className="bi bi-search" style={{
              position: 'absolute', left: 11, top: '50%', transform: 'translateY(-50%)',
              color: T.muted, fontSize: 14, pointerEvents: 'none',
            }}></i>
            <input
              type="text"
              placeholder="Search officer or loan ID…"
              value={searchQ}
              onChange={e => setSearchQ(e.target.value.toLowerCase())}
              onFocus={() => setSearchFocused(true)}
              onBlur={() => setSearchFocused(false)}
              style={inputStyle(searchFocused)}
            />
          </div>

          {/* Officer filter */}
          <select value={officerFilter} onChange={e => setOfficerFilter(e.target.value)} style={{
            padding: '9px 30px 9px 12px', border: `1.5px solid ${T.border}`, borderRadius: 9,
            fontFamily: 'DM Sans, sans-serif', fontSize: 13.5, color: T.text, background: T.bg,
            outline: 'none', cursor: 'pointer', WebkitAppearance: 'none', appearance: 'none',
            backgroundImage: `url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='7' viewBox='0 0 10 7'%3E%3Cpath fill='none' stroke='%236b8c7e' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round' d='M1 1l4 4 4-4'/%3E%3C/svg%3E")`,
            backgroundRepeat: 'no-repeat', backgroundPosition: 'right 10px center',
          }}>
            <option value="">All Officers</option>
            {officerOptions.map(o => (
              <option key={o.value} value={o.value}>{o.label}</option>
            ))}
          </select>

          {/* Date filter */}
          <input
            type="date"
            value={dateFilter}
            onChange={e => setDateFilter(e.target.value)}
            style={{
              padding: '9px 12px', border: `1.5px solid ${T.border}`, borderRadius: 9,
              fontFamily: 'DM Sans, sans-serif', fontSize: 13.5, color: T.text,
              background: T.bg, outline: 'none', cursor: 'pointer',
            }}
          />

          {/* Clear filters */}
          {(searchQ || officerFilter || dateFilter) && (
            <button onClick={() => { setSearchQ(''); setOfficerFilter(''); setDateFilter(''); }} style={{
              display: 'inline-flex', alignItems: 'center', gap: 5,
              background: 'none', border: `1.5px solid ${T.border}`,
              borderRadius: 9, padding: '8px 14px', fontSize: 13, fontWeight: 600,
              color: T.muted, cursor: 'pointer', fontFamily: 'DM Sans, sans-serif',
              transition: 'all .2s',
            }}>
              <i className="bi bi-x-circle" style={{ fontSize: 13 }}></i> Clear
            </button>
          )}
        </div>
      </div>

      {/* ── ALL tab: combined timeline ─────────────────────────────────── */}
      {activeTab === 'all' && (
        <AllTimeline
          rows={allRows}
          searchQ={searchQ}
          officerFilter={officerFilter}
          dateFilter={dateFilter}
        />
      )}

      {/* ── APPROVE tab ───────────────────────────────────────────────── */}
      {activeTab === 'approve' && (
        <LogTable
          rows={approvals}
          type="approve"
          searchQ={searchQ}
          officerFilter={officerFilter}
          dateFilter={dateFilter}
        />
      )}

      {/* ── REJECT tab ────────────────────────────────────────────────── */}
      {activeTab === 'reject' && (
        <LogTable
          rows={rejections}
          type="reject"
          searchQ={searchQ}
          officerFilter={officerFilter}
          dateFilter={dateFilter}
        />
      )}
    </>
  );
}

/* ── All Timeline (combined view) ─────────────────────────────────────── */
function AllTimeline({ rows, searchQ, officerFilter, dateFilter }) {
  const [remarksModal, setRemarksModal] = useState(null);
  const [page, setPage] = useState(1);
  const PER_PAGE = 15;

  const filtered = useMemo(() => {
    return rows.filter(row => {
      const name   = (row.officer_full_name || row.approved_by || row.rejected_by || '').toLowerCase();
      const loanId = String(row.loan_application_id || '');
      const empNum = (row.officer_emp_num || '').toLowerCase();
      const dt     = row._at || '';
      const dateStr = dt ? dt.slice(0, 10) : '';
      const uid    = String(row.approved_by_user_id || row.rejected_by_user_id || '');
      const matchQ = !searchQ || name.includes(searchQ) || loanId.includes(searchQ) || empNum.includes(searchQ);
      const matchO = !officerFilter || uid === officerFilter;
      const matchD = !dateFilter || dateStr === dateFilter;
      return matchQ && matchO && matchD;
    });
  }, [rows, searchQ, officerFilter, dateFilter]);

  const totalPages = Math.max(1, Math.ceil(filtered.length / PER_PAGE));
  const safePage   = Math.min(page, totalPages);
  const paged      = filtered.slice((safePage - 1) * PER_PAGE, safePage * PER_PAGE);

  return (
    <>
      <RemarksModal
        open={!!remarksModal}
        remarks={remarksModal?.remarks}
        loanId={remarksModal?.loanId}
        onClose={() => setRemarksModal(null)}
      />

      <div style={{
        background: T.card, borderRadius: 16,
        border: `1.5px solid ${T.border}`,
        boxShadow: '0 1px 6px rgba(10,59,47,0.06)',
        overflow: 'hidden',
      }}>
        <div style={{ overflowX: 'auto' }}>
          <table style={{ width: '100%', borderCollapse: 'collapse' }}>
            <thead>
              <tr style={{ background: T.bg }}>
                {['Type', 'Loan App #', 'Loan Officer', 'Timestamp', 'Remarks', 'Time Ago'].map((h, i) => (
                  <th key={i} style={{
                    padding: '12px 18px', fontSize: 10.5, fontWeight: 700,
                    textTransform: 'uppercase', letterSpacing: .7, color: T.muted,
                    borderBottom: `1.5px solid ${T.border}`, whiteSpace: 'nowrap', textAlign: 'left',
                  }}>{h}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {paged.length === 0 ? (
                <tr>
                  <td colSpan={6} style={{ textAlign: 'center', padding: '48px 20px', color: T.muted }}>
                    <i className="bi bi-inbox" style={{ fontSize: 36, display: 'block', marginBottom: 10, opacity: .3 }}></i>
                    <div style={{ fontSize: 14 }}>No records found.</div>
                  </td>
                </tr>
              ) : paged.map((row, idx) => {
                const isApp  = row._type === 'approve';
                const accentColor = isApp ? T.approve : T.reject;
                const accentBg    = isApp ? T.approveBg : T.rejectBg;
                const accentBd    = isApp ? T.approveBd : T.rejectBd;
                const officerName = row.officer_full_name || (isApp ? row.approved_by : row.rejected_by);
                return (
                  <tr key={`${row._type}-${row.id}`} className="al-row-hover" style={{
                    borderBottom: `1px solid #eef4f0`,
                    background: idx % 2 === 0 ? '#fff' : '#fafcfb',
                  }}>
                    {/* Type */}
                    <td style={{ padding: '13px 18px', verticalAlign: 'middle' }}>
                      <span style={{
                        display: 'inline-flex', alignItems: 'center', gap: 5,
                        background: accentBg, border: `1px solid ${accentBd}`,
                        color: accentColor, borderRadius: 20,
                        padding: '4px 11px', fontSize: 12, fontWeight: 700,
                      }}>
                        <i className={`bi ${isApp ? 'bi-check2' : 'bi-x'}`} style={{ fontSize: 11 }}></i>
                        {isApp ? 'Approved' : 'Rejected'}
                      </span>
                    </td>

                    {/* Loan ID */}
                    <td style={{ padding: '13px 18px', verticalAlign: 'middle' }}>
                      <span style={{ fontFamily: 'Courier New, monospace', fontSize: 13, fontWeight: 700, color: T.text }}>
                        #{row.loan_application_id ?? '—'}
                      </span>
                    </td>

                    {/* Officer */}
                    <td style={{ padding: '13px 18px', verticalAlign: 'middle' }}>
                      <OfficerCell
                        name={officerName}
                        empNum={row.officer_emp_num}
                        email={row.officer_email}
                        role={row.officer_role}
                      />
                    </td>

                    {/* Timestamp */}
                    <td style={{ padding: '13px 18px', verticalAlign: 'middle' }}>
                      <div style={{ fontSize: 13, color: T.text, fontWeight: 500 }}>
                        {formatDateTime(row._at).split(' · ')[0]}
                      </div>
                      <div style={{ fontSize: 11.5, color: T.muted, marginTop: 2 }}>
                        {formatDateTime(row._at).split(' · ')[1]}
                      </div>
                    </td>

                    {/* Remarks */}
                    <td style={{ padding: '13px 18px', verticalAlign: 'middle' }}>
                      {!isApp && row.rejection_remarks ? (
                        <button onClick={() => setRemarksModal({ remarks: row.rejection_remarks, loanId: row.loan_application_id })} style={{
                          display: 'inline-flex', alignItems: 'center', gap: 5,
                          background: T.rejectBg, border: `1px solid ${T.rejectBd}`,
                          color: T.reject, borderRadius: 7, padding: '5px 11px',
                          fontSize: 12, fontWeight: 600, cursor: 'pointer',
                          fontFamily: 'DM Sans, sans-serif',
                        }}>
                          <i className="bi bi-eye-fill" style={{ fontSize: 11 }}></i> View
                        </button>
                      ) : (
                        <span style={{ fontSize: 12, color: '#ccc' }}>—</span>
                      )}
                    </td>

                    {/* Time ago */}
                    <td style={{ padding: '13px 18px', verticalAlign: 'middle' }}>
                      <span style={{ fontSize: 12, color: T.muted, fontStyle: 'italic' }}>
                        {timeAgo(row._at)}
                      </span>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>

        {/* Pagination */}
        {totalPages > 1 && (
          <div style={{
            padding: '14px 20px', borderTop: `1px solid ${T.border}`,
            display: 'flex', alignItems: 'center', justifyContent: 'space-between',
            flexWrap: 'wrap', gap: 10,
          }}>
            <div style={{ fontSize: 12.5, color: T.muted }}>
              Showing {(safePage - 1) * PER_PAGE + 1}–{Math.min(safePage * PER_PAGE, filtered.length)} of {filtered.length}
            </div>
            <div style={{ display: 'flex', gap: 6 }}>
              <button onClick={() => setPage(p => Math.max(1, p-1))} disabled={safePage===1} style={{
                padding: '5px 12px', borderRadius: 7, cursor: safePage===1 ? 'not-allowed' : 'pointer',
                border: `1.5px solid ${T.border}`, background: '#fff', color: T.muted,
                fontSize: 13, fontFamily: 'DM Sans, sans-serif', opacity: safePage===1 ? .4 : 1,
              }}>‹ Prev</button>
              {Array.from({ length: totalPages }, (_, i) => i + 1)
                .filter(p => p === 1 || p === totalPages || Math.abs(p - safePage) <= 1)
                .reduce((acc, p, i, arr) => {
                  if (i > 0 && p - arr[i-1] > 1) acc.push('…');
                  acc.push(p);
                  return acc;
                }, [])
                .map((p, i) => p === '…'
                  ? <span key={`e${i}`} style={{ padding: '5px 4px', color: T.muted, fontSize: 13 }}>…</span>
                  : <button key={p} onClick={() => setPage(p)} style={{
                      padding: '5px 11px', borderRadius: 7, cursor: 'pointer',
                      border: `1.5px solid ${p===safePage ? T.forest : T.border}`,
                      background: p===safePage ? T.light : '#fff',
                      color: p===safePage ? T.forest : T.muted,
                      fontSize: 13, fontWeight: p===safePage ? 700 : 400,
                      fontFamily: 'DM Sans, sans-serif',
                    }}>{p}</button>
                )}
              <button onClick={() => setPage(p => Math.min(totalPages, p+1))} disabled={safePage===totalPages} style={{
                padding: '5px 12px', borderRadius: 7, cursor: safePage===totalPages ? 'not-allowed' : 'pointer',
                border: `1.5px solid ${T.border}`, background: '#fff', color: T.muted,
                fontSize: 13, fontFamily: 'DM Sans, sans-serif', opacity: safePage===totalPages ? .4 : 1,
              }}>Next ›</button>
            </div>
          </div>
        )}
      </div>
    </>
  );
}

ReactDOM.createRoot(document.getElementById('audit-logs-root')).render(<AuditLogsApp />);
</script>
</body>
</html>