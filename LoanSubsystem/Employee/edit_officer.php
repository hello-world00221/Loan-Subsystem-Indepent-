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

$pdo     = null;
$officer = null;
$error   = null;
$success = null;

$officerId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$officerId) {
    header("Location: Employeedashboard.php");
    exit;
}

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

    // ── Handle form submission ───────────────────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        if ($action === 'update') {
            $fullName       = trim($_POST['full_name']       ?? '');
            $employeeNumber = trim($_POST['employee_number'] ?? '');
            $email          = trim($_POST['officer_email']   ?? '');
            $role           = trim($_POST['role']            ?? '');
            $status         = trim($_POST['status']          ?? '');
            $newPassword    = trim($_POST['new_password']    ?? '');

            if (!$fullName || !$employeeNumber || !$email || !$role || !$status) {
                $error = "All required fields must be filled.";
            } else {
                if ($newPassword) {
                    $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare(
                        "UPDATE officers SET full_name=?, employee_number=?, officer_email=?, role=?, status=?, password_hash=? WHERE id=?"
                    );
                    $stmt->execute([$fullName, $employeeNumber, $email, $role, $status, $hashedPassword, $officerId]);
                } else {
                    $stmt = $pdo->prepare(
                        "UPDATE officers SET full_name=?, employee_number=?, officer_email=?, role=?, status=? WHERE id=?"
                    );
                    $stmt->execute([$fullName, $employeeNumber, $email, $role, $status, $officerId]);
                }
                $success = "Officer account updated successfully.";
            }
        }

        if ($action === 'toggle_status') {
            $newStatus = $_POST['new_status'] ?? '';
            if (in_array($newStatus, ['Active', 'Inactive'])) {
                $stmt = $pdo->prepare("UPDATE officers SET status=? WHERE id=?");
                $stmt->execute([$newStatus, $officerId]);
                // Redirect to GET so page re-fetches the updated row from DB
                $msg = urlencode("Account status changed to $newStatus.");
                header("Location: edit_officer.php?id=$officerId&success=" . $msg);
                exit;
            }
        }
    }

    // ── Fetch officer ────────────────────────────────────────────────────────
    $stmt    = $pdo->prepare("SELECT * FROM officers WHERE id=?");
    $stmt->execute([$officerId]);
    $officer = $stmt->fetch();

    if (!$officer) {
        header("Location: Employeedashboard.php");
        exit;
    }

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

// ── Pass PHP data to React ────────────────────────────────────────────────────
$officerJson = $officer ? json_encode([
    'id'              => $officer['id'],
    'full_name'       => $officer['full_name'],
    'employee_number' => $officer['employee_number'],
    'officer_email'   => $officer['officer_email'],
    'role'            => $officer['role'],
    'status'          => $officer['status'],
    'created_at'      => $officer['created_at'],
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) : 'null';

// Also pick up success message passed via GET redirect (from toggle_status)
if (!$success && isset($_GET['success'])) {
    $success = htmlspecialchars(urldecode($_GET['success']));
}

$flashSuccess = $success ? json_encode($success) : 'null';
$flashError   = $error   ? json_encode($error)   : 'null';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Evergreen – Edit Officer</title>
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
      --eg-danger-bg: #fdf0ef;
      --eg-danger-bd: #f5c6c3;
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

    /* ══ REACT MOUNT ═══════════════════════════════════════════════════════ */
    #edit-officer-root { width: 100%; }
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
      <a href="add_officer.php" class="eg-nav-item active"><i class="bi bi-person-gear"></i> Account Management</a>
      <a href="#audit-logs" class="eg-nav-item"><i class="bi bi-journal-text"></i> Audit Logs</a>
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
        <div class="eg-tb-page">Edit Officer</div>
      </div>
      <div class="eg-breadcrumb">
        <span>Staff Portal</span>
        <span class="bc-sep"><i class="bi bi-chevron-right" style="font-size:10px;"></i></span>
        <a href="Employeedashboard.php" style="color:rgba(255,255,255,0.55);text-decoration:none;">Dashboard</a>
        <span class="bc-sep"><i class="bi bi-chevron-right" style="font-size:10px;"></i></span>
        <span class="bc-active">Edit Officer</span>
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
    <div id="edit-officer-root"></div>
  </main>
</div>

<!-- ══ Bootstrap JS ══════════════════════════════════════════════════════════ -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>

<!-- ══ React + Babel ══════════════════════════════════════════════════════════ -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/react/18.2.0/umd/react.production.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/react-dom/18.2.0/umd/react-dom.production.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/babel-standalone/7.23.2/babel.min.js"></script>

<!-- ══ PHP → JS data bridge ══════════════════════════════════════════════════ -->
<script>
  window.EG_OFFICER    = <?= $officerJson ?>;
  window.EG_OFFICER_ID = <?= (int)$officerId ?>;
  window.EG_FLASH_OK   = <?= $flashSuccess ?? 'null' ?>;
  window.EG_FLASH_ERR  = <?= $flashError ?? 'null' ?>;
</script>

<!-- ══ Sidebar / Profile vanilla JS ═════════════════════════════════════════ -->
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

<!-- ══ React Application ══════════════════════════════════════════════════════ -->
<script type="text/babel">
const { useState, useEffect, useRef } = React;

/* ── Design tokens (mirrors PHP CSS vars) ─────────────────────────────── */
const T = {
  forest:  '#0a3b2f',
  deep:    '#062620',
  mid:     '#1a6b55',
  light:   '#e8f4ef',
  gold:    '#c9a84c',
  goldL:   '#e8c96b',
  text:    '#1c2b25',
  muted:   '#6b8c7e',
  border:  '#d4e6de',
  bg:      '#f4f8f6',
  card:    '#ffffff',
  danger:  '#c0392b',
  dangerBg:'#fdf0ef',
  dangerBd:'#f5c6c3',
  success: '#1a6b55',
  successBg:'#e8f4ef',
  successBd:'#b5d6c8',
};

/* ── Utility ────────────────────────────────────────────────────────────── */
function initials(name = '') {
  return name.trim().split(' ').slice(0, 2).map(w => w[0]?.toUpperCase() ?? '').join('');
}
function formatDate(dt) {
  if (!dt) return '—';
  return new Date(dt).toLocaleDateString('en-US', { year:'numeric', month:'short', day:'2-digit' });
}

/* ── Sub-components ─────────────────────────────────────────────────────── */

function Avatar({ name, size = 44, fontSize = 15, bg = T.light, color = T.forest }) {
  return (
    <div style={{
      width: size, height: size, borderRadius: '50%',
      background: bg, color, display: 'flex',
      alignItems: 'center', justifyContent: 'center',
      fontWeight: 800, fontSize, flexShrink: 0,
      border: `2px solid ${T.border}`, fontFamily: 'DM Sans, sans-serif',
    }}>
      {initials(name)}
    </div>
  );
}

function StatusBadge({ status }) {
  const active = status?.toLowerCase() === 'active';
  return (
    <span style={{
      display: 'inline-flex', alignItems: 'center', gap: 5,
      padding: '4px 13px', borderRadius: 20,
      fontSize: 12, fontWeight: 700, letterSpacing: .3,
      background: active ? T.light : '#f3f4f6',
      color: active ? T.forest : '#6b7280',
      border: `1px solid ${active ? T.border : '#e5e7eb'}`,
    }}>
      <span style={{ fontSize: 8, color: active ? T.mid : '#9ca3af' }}>●</span>
      {status}
    </span>
  );
}

function Alert({ type, html, onClose }) {
  const isErr = type === 'error';
  return (
    <div style={{
      display: 'flex', alignItems: 'flex-start', gap: 10,
      background: isErr ? T.dangerBg : T.successBg,
      border: `1px solid ${isErr ? T.dangerBd : T.successBd}`,
      color: isErr ? T.danger : T.success,
      borderRadius: 12, padding: '13px 16px', marginBottom: 22,
      fontSize: 14, animation: 'fadeSlideIn .25s ease',
    }}>
      <i className={`bi ${isErr ? 'bi-exclamation-circle-fill' : 'bi-check-circle-fill'}`}
         style={{ flexShrink: 0, marginTop: 1 }}></i>
      <span dangerouslySetInnerHTML={{ __html: html }} style={{ flex: 1 }} />
      {onClose && (
        <button onClick={onClose} style={{
          background: 'none', border: 'none', cursor: 'pointer',
          color: 'inherit', fontSize: 16, opacity: .6, padding: 0, lineHeight: 1,
        }}>×</button>
      )}
    </div>
  );
}

function FormField({ label, required, hint, children }) {
  return (
    <div style={{ marginBottom: 20 }}>
      <label style={{
        display: 'block', fontSize: 12, fontWeight: 700,
        textTransform: 'uppercase', letterSpacing: .6,
        color: T.muted, marginBottom: 7,
      }}>
        {label} {required && <span style={{ color: T.danger }}>*</span>}
      </label>
      {children}
      {hint && <div style={{ fontSize: 11.5, color: T.muted, marginTop: 5 }}>{hint}</div>}
    </div>
  );
}

const inputStyle = (focused) => ({
  width: '100%', padding: '10px 14px',
  border: `1.5px solid ${focused ? T.forest : T.border}`,
  borderRadius: 9, fontFamily: 'DM Sans, sans-serif',
  fontSize: 14, color: T.text, background: focused ? '#fff' : T.bg,
  outline: 'none', transition: 'border-color .2s, box-shadow .2s',
  boxShadow: focused ? `0 0 0 3px rgba(10,59,47,0.08)` : 'none',
});

function TextInput({ value, onChange, placeholder, type = 'text', name, required }) {
  const [focused, setFocused] = useState(false);
  return (
    <input
      type={type} name={name} value={value} required={required}
      placeholder={placeholder}
      onChange={e => onChange(e.target.value)}
      onFocus={() => setFocused(true)}
      onBlur={() => setFocused(false)}
      style={inputStyle(focused)}
    />
  );
}

function SelectInput({ value, onChange, name, options }) {
  const [focused, setFocused] = useState(false);
  return (
    <select
      name={name} value={value}
      onChange={e => onChange(e.target.value)}
      onFocus={() => setFocused(true)}
      onBlur={() => setFocused(false)}
      style={{
        ...inputStyle(focused),
        WebkitAppearance: 'none', appearance: 'none', cursor: 'pointer',
        backgroundImage: `url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='7' viewBox='0 0 10 7'%3E%3Cpath fill='none' stroke='%236b8c7e' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round' d='M1 1l4 4 4-4'/%3E%3C/svg%3E")`,
        backgroundRepeat: 'no-repeat', backgroundPosition: 'right 12px center',
        paddingRight: 34,
      }}
    >
      {options.map(o => (
        <option key={o.value} value={o.value}>{o.label}</option>
      ))}
    </select>
  );
}

/* ── Confirm Modal ──────────────────────────────────────────────────────── */
function ConfirmModal({ open, title, message, confirmLabel, confirmColor, onConfirm, onCancel }) {
  if (!open) return null;
  return (
    <div style={{
      position: 'fixed', inset: 0, background: 'rgba(6,38,32,0.55)',
      zIndex: 9999, display: 'flex', alignItems: 'center', justifyContent: 'center',
      padding: 20, animation: 'fadeIn .15s ease',
    }}>
      <div style={{
        background: '#fff', borderRadius: 18, padding: '32px 30px',
        maxWidth: 420, width: '100%', boxShadow: '0 16px 56px rgba(6,38,32,0.22)',
        animation: 'scaleIn .18s ease',
      }}>
        <div style={{ textAlign: 'center', marginBottom: 20 }}>
          <div style={{
            width: 52, height: 52, borderRadius: '50%', margin: '0 auto 14px',
            background: confirmColor === T.danger ? T.dangerBg : T.light,
            display: 'flex', alignItems: 'center', justifyContent: 'center',
          }}>
            <i className={`bi ${confirmColor === T.danger ? 'bi-exclamation-triangle-fill' : 'bi-check2-circle'}`}
               style={{ fontSize: 22, color: confirmColor }}></i>
          </div>
          <div style={{ fontFamily: 'Playfair Display, serif', fontSize: 20, fontWeight: 700, color: T.forest, marginBottom: 8 }}>
            {title}
          </div>
          <div style={{ fontSize: 14, color: T.muted, lineHeight: 1.6 }}>{message}</div>
        </div>
        <div style={{ display: 'flex', gap: 10 }}>
          <button onClick={onCancel} style={{
            flex: 1, padding: '10px 0', border: `1.5px solid ${T.border}`,
            borderRadius: 9, background: '#fff', color: T.muted, fontWeight: 600,
            fontSize: 14, cursor: 'pointer', fontFamily: 'DM Sans, sans-serif', transition: 'background .2s',
          }}>Cancel</button>
          <button onClick={onConfirm} style={{
            flex: 1, padding: '10px 0', border: 'none',
            borderRadius: 9, background: confirmColor, color: '#fff', fontWeight: 700,
            fontSize: 14, cursor: 'pointer', fontFamily: 'DM Sans, sans-serif',
            boxShadow: `0 3px 12px ${confirmColor}44`,
          }}>{confirmLabel}</button>
        </div>
      </div>
    </div>
  );
}

/* ── Main App ────────────────────────────────────────────────────────────── */
function EditOfficerApp() {
  const raw = window.EG_OFFICER;
  const officerId = window.EG_OFFICER_ID;

  const [form, setForm] = useState({
    full_name:       raw?.full_name       ?? '',
    employee_number: raw?.employee_number ?? '',
    officer_email:   raw?.officer_email   ?? '',
    role:            raw?.role            ?? 'LoanOfficer',
    status:          raw?.status          ?? 'Active',
    new_password:    '',
    confirm_password:'',
  });

  const [showPassword, setShowPassword]     = useState(false);
  const [showConfirm, setShowConfirm]       = useState(false);
  const [pwFocused, setPwFocused]           = useState(false);
  const [cfFocused, setCfFocused]           = useState(false);
  const [alert, setAlert]                   = useState(null);
  const [submitting, setSubmitting]         = useState(false);
  const [modal, setModal]                   = useState(null);
  const formRef                             = useRef(null);
  const hiddenStatusRef                     = useRef(null);

  /* Flash from PHP on page load */
  useEffect(() => {
    if (window.EG_FLASH_OK) setAlert({ type: 'success', msg: window.EG_FLASH_OK });
    if (window.EG_FLASH_ERR) setAlert({ type: 'error', msg: window.EG_FLASH_ERR });
  }, []);

  function set(field) {
    return (val) => setForm(f => ({ ...f, [field]: val }));
  }

  function validateForm() {
    if (!form.full_name.trim())       return 'Full name is required.';
    if (!form.employee_number.trim()) return 'Employee number is required.';
    if (!form.officer_email.trim())   return 'Email is required.';
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.officer_email)) return 'Invalid email format.';
    if (form.new_password && form.new_password.length < 8) return 'Password must be at least 8 characters.';
    if (form.new_password !== form.confirm_password) return 'Passwords do not match.';
    return null;
  }

  function handleSave(e) {
    e.preventDefault();
    const err = validateForm();
    if (err) { setAlert({ type: 'error', msg: err }); return; }

    setModal({
      title: 'Save Changes',
      message: "Are you sure you want to update this officer\u2019s account details?",
      confirmLabel: 'Yes, Save',
      confirmColor: T.forest,
      onConfirm: () => { setModal(null); submitUpdate(); },
    });
  }

  // Submit update — uses a real hidden-form POST so PHP receives the data
  // properly (fetch/AJAX was not persisting to DB reliably).
  function submitUpdate() {
    setSubmitting(true);
    const f = document.createElement('form');
    f.method = 'POST';
    f.action = window.location.href;
    const fields = {
      action:          'update',
      full_name:       form.full_name,
      employee_number: form.employee_number,
      officer_email:   form.officer_email,
      role:            form.role,
      status:          form.status,
      new_password:    form.new_password,
    };
    Object.entries(fields).forEach(([name, value]) => {
      const inp = document.createElement('input');
      inp.type  = 'hidden';
      inp.name  = name;
      inp.value = value ?? '';
      f.appendChild(inp);
    });
    document.body.appendChild(f);
    f.submit();
  }

  function handleToggleStatus() {
    const current = form.status;
    const target  = current === 'Active' ? 'Inactive' : 'Active';
    const isDeact = target === 'Inactive';

    setModal({
      title: isDeact ? 'Deactivate Account' : 'Activate Account',
      message: isDeact
        ? `This will deactivate the account for "${form.full_name}". They will no longer be able to log in until reactivated.`
        : `This will restore access for "${form.full_name}". They will be able to log in again.`,
      confirmLabel: isDeact ? 'Deactivate' : 'Activate',
      confirmColor: isDeact ? T.danger : T.mid,
      onConfirm: () => { setModal(null); submitToggle(target); },
    });
  }

  // Submit status toggle — real form POST so PHP updates the DB and page
  // reloads with the fresh status from the database.
  function submitToggle(newStatus) {
    setSubmitting(true);
    const f = document.createElement('form');
    f.method = 'POST';
    f.action = window.location.href;
    [['action', 'toggle_status'], ['new_status', newStatus]].forEach(([name, value]) => {
      const inp = document.createElement('input');
      inp.type  = 'hidden';
      inp.name  = name;
      inp.value = value;
      f.appendChild(inp);
    });
    document.body.appendChild(f);
    f.submit();
  }

  const isActive   = form.status === 'Active';
  const avatarInits = initials(form.full_name || raw?.full_name || '?');
  const pwStrength = (() => {
    const p = form.new_password;
    if (!p) return null;
    let s = 0;
    if (p.length >= 8) s++;
    if (/[A-Z]/.test(p)) s++;
    if (/[0-9]/.test(p)) s++;
    if (/[^A-Za-z0-9]/.test(p)) s++;
    return s;
  })();

  const strengthLabel = ['Weak', 'Fair', 'Good', 'Strong'];
  const strengthColor = ['#e74c3c', '#e67e22', '#f1c40f', T.mid];

  return (
    <>
      <style>{`
        @keyframes fadeSlideIn { from { opacity:0; transform:translateY(-8px); } to { opacity:1; transform:translateY(0); } }
        @keyframes fadeIn      { from { opacity:0; } to { opacity:1; } }
        @keyframes scaleIn     { from { opacity:0; transform:scale(.94); } to { opacity:1; transform:scale(1); } }
        .eg-btn-primary { transition: transform .15s, box-shadow .15s !important; }
        .eg-btn-primary:hover:not(:disabled) { transform: translateY(-1px) !important; box-shadow: 0 6px 18px rgba(10,59,47,0.28) !important; }
        .eg-btn-primary:active { transform: translateY(0) !important; }
        .eg-card-hover { transition: box-shadow .2s !important; }
        .eg-card-hover:hover { box-shadow: 0 4px 20px rgba(10,59,47,0.09) !important; }
      `}</style>

      {/* Confirm Modal */}
      {modal && (
        <ConfirmModal
          open={true}
          title={modal.title}
          message={modal.message}
          confirmLabel={modal.confirmLabel}
          confirmColor={modal.confirmColor}
          onConfirm={modal.onConfirm}
          onCancel={() => setModal(null)}
        />
      )}

      {/* Page Header */}
      <div style={{ marginBottom: 28, display: 'flex', alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', gap: 12 }}>
        <div>
          <h1 style={{ fontFamily: 'Playfair Display, serif', fontSize: 26, fontWeight: 700, color: T.forest, letterSpacing: -.2, margin: 0 }}>
            Edit Officer
          </h1>
          <p style={{ fontSize: 13.5, color: T.muted, marginTop: 3, margin: 0 }}>
            Manage account details and access status
          </p>
        </div>
        <a href="Employeedashboard.php" style={{
          display: 'inline-flex', alignItems: 'center', gap: 7,
          background: T.bg, border: `1.5px solid ${T.border}`, color: T.muted,
          borderRadius: 9, padding: '8px 16px', fontSize: 13.5, fontWeight: 600,
          textDecoration: 'none', transition: 'all .2s',
        }}>
          <i className="bi bi-arrow-left"></i> Back
        </a>
      </div>

      {/* Alert */}
      {alert && (
        <Alert type={alert.type} html={alert.msg} onClose={() => setAlert(null)} />
      )}

      <div className="row g-4">

        {/* ── LEFT: Profile Card ───────────────────────────────────────── */}
        <div className="col-12 col-lg-4">

          {/* Profile Summary */}
          <div className="eg-card-hover" style={{
            background: T.card, borderRadius: 16,
            border: `1.5px solid ${T.border}`,
            boxShadow: '0 1px 6px rgba(10,59,47,0.06)',
            overflow: 'hidden', marginBottom: 20,
          }}>
            {/* Card top banner */}
            <div style={{
              height: 72,
              background: `linear-gradient(135deg, ${T.deep} 0%, ${T.forest} 60%, #0e4535 100%)`,
            }} />
            <div style={{ padding: '0 24px 24px', textAlign: 'center', marginTop: -32 }}>
              <div style={{
                width: 64, height: 64, borderRadius: '50%',
                background: T.gold, display: 'flex', alignItems: 'center', justifyContent: 'center',
                fontWeight: 800, fontSize: 22, color: T.deep,
                border: '3px solid #fff', margin: '0 auto 12px',
                boxShadow: '0 4px 14px rgba(201,168,76,0.30)',
                fontFamily: 'DM Sans, sans-serif',
              }}>
                {avatarInits}
              </div>
              <div style={{ fontWeight: 700, fontSize: 16, color: T.text, marginBottom: 3 }}>
                {form.full_name || '—'}
              </div>
              <div style={{ fontSize: 12, color: T.muted, fontFamily: 'Courier New, monospace', marginBottom: 10 }}>
                {form.employee_number || '—'}
              </div>
              <div style={{ marginBottom: 14 }}>
                <StatusBadge status={form.status} />
              </div>

              {/* Role badge */}
              <div style={{
                display: 'inline-block', padding: '4px 13px', borderRadius: 6,
                fontSize: 12, fontWeight: 600,
                background: form.role?.toLowerCase().includes('super')
                  ? 'rgba(10,59,47,0.10)' : 'rgba(201,168,76,0.12)',
                color: form.role?.toLowerCase().includes('super')
                  ? T.forest : '#8a6000',
                border: `1px solid ${form.role?.toLowerCase().includes('super') ? 'rgba(10,59,47,0.20)' : 'rgba(201,168,76,0.30)'}`,
              }}>
                {form.role || 'LoanOfficer'}
              </div>

              {/* Divider */}
              <div style={{ height: 1, background: T.border, margin: '18px 0' }} />

              <div style={{ fontSize: 12.5, color: T.muted, textAlign: 'left' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 8 }}>
                  <span>Account Created</span>
                  <span style={{ fontWeight: 600, color: T.text }}>{formatDate(raw?.created_at)}</span>
                </div>
                <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                  <span>Email</span>
                  <span style={{ fontWeight: 600, color: T.text, fontSize: 12, wordBreak: 'break-all' }}>
                    {form.officer_email || '—'}
                  </span>
                </div>
              </div>
            </div>
          </div>

          {/* Status Toggle Card */}
          <div className="eg-card-hover" style={{
            background: isActive ? T.dangerBg : T.successBg,
            border: `1.5px solid ${isActive ? T.dangerBd : T.successBd}`,
            borderRadius: 16, padding: '20px 22px',
            boxShadow: '0 1px 6px rgba(10,59,47,0.06)',
          }}>
            <div style={{ display: 'flex', alignItems: 'flex-start', gap: 12, marginBottom: 16 }}>
              <div style={{
                width: 38, height: 38, borderRadius: 10, flexShrink: 0,
                background: isActive ? 'rgba(192,57,43,0.12)' : T.light,
                display: 'flex', alignItems: 'center', justifyContent: 'center',
              }}>
                <i className={`bi ${isActive ? 'bi-person-x-fill' : 'bi-person-check-fill'}`}
                   style={{ fontSize: 17, color: isActive ? T.danger : T.mid }}></i>
              </div>
              <div>
                <div style={{ fontWeight: 700, fontSize: 14, color: isActive ? T.danger : T.forest, marginBottom: 3 }}>
                  {isActive ? 'Deactivate Account' : 'Activate Account'}
                </div>
                <div style={{ fontSize: 12.5, color: T.muted, lineHeight: 1.5 }}>
                  {isActive
                    ? 'Officer will lose login access immediately.'
                    : 'Officer will regain login access.'}
                </div>
              </div>
            </div>
            <button
              onClick={handleToggleStatus}
              disabled={submitting}
              style={{
                width: '100%', padding: '10px 0',
                background: isActive ? T.danger : T.mid,
                border: 'none', borderRadius: 9, color: '#fff',
                fontWeight: 700, fontSize: 13.5, cursor: 'pointer',
                fontFamily: 'DM Sans, sans-serif',
                boxShadow: `0 2px 10px ${isActive ? 'rgba(192,57,43,0.25)' : 'rgba(26,107,85,0.20)'}`,
                opacity: submitting ? 0.7 : 1, transition: 'opacity .2s, transform .15s',
                display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 6,
              }}
            >
              <i className={`bi ${isActive ? 'bi-person-x' : 'bi-person-check'}`}></i>
              {isActive ? 'Deactivate Account' : 'Activate Account'}
            </button>
          </div>
        </div>

        {/* ── RIGHT: Edit Form ─────────────────────────────────────────── */}
        <div className="col-12 col-lg-8">
          <form onSubmit={handleSave}>
            {/* Hidden fields for PHP */}
            <input type="hidden" name="action" value="update" />

            {/* Personal Info Section */}
            <div style={{
              background: T.card, borderRadius: 16,
              border: `1.5px solid ${T.border}`,
              boxShadow: '0 1px 6px rgba(10,59,47,0.06)',
              padding: '24px 26px', marginBottom: 20,
            }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 22 }}>
                <div style={{ width: 32, height: 32, borderRadius: 8, background: T.light, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                  <i className="bi bi-person-fill" style={{ color: T.forest, fontSize: 15 }}></i>
                </div>
                <div>
                  <div style={{ fontFamily: 'Playfair Display, serif', fontSize: 16, fontWeight: 700, color: T.forest }}>Personal Information</div>
                  <div style={{ fontSize: 12, color: T.muted }}>Basic account details</div>
                </div>
              </div>

              <div className="row g-3">
                <div className="col-12">
                  <FormField label="Full Name" required>
                    <TextInput
                      name="full_name"
                      value={form.full_name}
                      onChange={set('full_name')}
                      placeholder="e.g. Juan dela Cruz"
                      required
                    />
                  </FormField>
                </div>
                <div className="col-12 col-sm-6">
                  <FormField label="Employee Number" required hint="Unique identifier, e.g. EMP-00123">
                    <TextInput
                      name="employee_number"
                      value={form.employee_number}
                      onChange={set('employee_number')}
                      placeholder="EMP-00001"
                      required
                    />
                  </FormField>
                </div>
                <div className="col-12 col-sm-6">
                  <FormField label="Email Address" required>
                    <TextInput
                      type="email"
                      name="officer_email"
                      value={form.officer_email}
                      onChange={set('officer_email')}
                      placeholder="officer@evergreen.com"
                      required
                    />
                  </FormField>
                </div>
              </div>
            </div>

            {/* Role & Status Section */}
            <div style={{
              background: T.card, borderRadius: 16,
              border: `1.5px solid ${T.border}`,
              boxShadow: '0 1px 6px rgba(10,59,47,0.06)',
              padding: '24px 26px', marginBottom: 20,
            }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 22 }}>
                <div style={{ width: 32, height: 32, borderRadius: 8, background: 'rgba(201,168,76,0.12)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                  <i className="bi bi-shield-fill" style={{ color: T.gold, fontSize: 15 }}></i>
                </div>
                <div>
                  <div style={{ fontFamily: 'Playfair Display, serif', fontSize: 16, fontWeight: 700, color: T.forest }}>Role &amp; Access</div>
                  <div style={{ fontSize: 12, color: T.muted }}>Permissions and account status</div>
                </div>
              </div>

              <div className="row g-3">
                <div className="col-12 col-sm-6">
                  <FormField label="Role" required>
                    <SelectInput
                      name="role"
                      value={form.role}
                      onChange={set('role')}
                      options={[
                        { value: 'LoanOfficer', label: 'Loan Officer' },
                        { value: 'SuperAdmin',  label: 'Super Admin' },
                        { value: 'Admin',       label: 'Admin' },
                      ]}
                    />
                  </FormField>
                </div>
                <div className="col-12 col-sm-6">
                  <FormField label="Status" required hint="Inactive accounts cannot log in.">
                    <SelectInput
                      name="status"
                      value={form.status}
                      onChange={set('status')}
                      options={[
                        { value: 'Active',   label: 'Active' },
                        { value: 'Inactive', label: 'Inactive' },
                      ]}
                    />
                  </FormField>
                </div>
              </div>
            </div>

            {/* Password Section */}
            <div style={{
              background: T.card, borderRadius: 16,
              border: `1.5px solid ${T.border}`,
              boxShadow: '0 1px 6px rgba(10,59,47,0.06)',
              padding: '24px 26px', marginBottom: 24,
            }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 22 }}>
                <div style={{ width: 32, height: 32, borderRadius: 8, background: T.light, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                  <i className="bi bi-lock-fill" style={{ color: T.forest, fontSize: 15 }}></i>
                </div>
                <div>
                  <div style={{ fontFamily: 'Playfair Display, serif', fontSize: 16, fontWeight: 700, color: T.forest }}>Change Password</div>
                  <div style={{ fontSize: 12, color: T.muted }}>Leave blank to keep current password</div>
                </div>
              </div>

              <div className="row g-3">
                <div className="col-12 col-sm-6">
                  <FormField label="New Password" hint="Min. 8 characters">
                    <div style={{ position: 'relative' }}>
                      <input
                        type={showPassword ? 'text' : 'password'}
                        name="new_password"
                        value={form.new_password}
                        onChange={e => set('new_password')(e.target.value)}
                        onFocus={() => setPwFocused(true)}
                        onBlur={() => setPwFocused(false)}
                        placeholder="New password"
                        style={{ ...inputStyle(pwFocused), paddingRight: 40 }}
                      />
                      <button type="button" onClick={() => setShowPassword(p => !p)} style={{
                        position: 'absolute', right: 12, top: '50%', transform: 'translateY(-50%)',
                        background: 'none', border: 'none', color: T.muted, cursor: 'pointer', fontSize: 15, padding: 0,
                      }}>
                        <i className={`bi ${showPassword ? 'bi-eye-slash' : 'bi-eye'}`}></i>
                      </button>
                    </div>
                    {/* Strength bar */}
                    {pwStrength !== null && (
                      <div style={{ marginTop: 8 }}>
                        <div style={{ display: 'flex', gap: 4, marginBottom: 4 }}>
                          {[0,1,2,3].map(i => (
                            <div key={i} style={{
                              flex: 1, height: 4, borderRadius: 2,
                              background: i < pwStrength ? strengthColor[pwStrength - 1] : T.border,
                              transition: 'background .3s',
                            }} />
                          ))}
                        </div>
                        <div style={{ fontSize: 11, color: strengthColor[pwStrength - 1], fontWeight: 700 }}>
                          {strengthLabel[pwStrength - 1]}
                        </div>
                      </div>
                    )}
                  </FormField>
                </div>
                <div className="col-12 col-sm-6">
                  <FormField label="Confirm Password">
                    <div style={{ position: 'relative' }}>
                      <input
                        type={showConfirm ? 'text' : 'password'}
                        value={form.confirm_password}
                        onChange={e => set('confirm_password')(e.target.value)}
                        onFocus={() => setCfFocused(true)}
                        onBlur={() => setCfFocused(false)}
                        placeholder="Repeat password"
                        style={{
                          ...inputStyle(cfFocused),
                          paddingRight: 40,
                          borderColor: form.confirm_password && form.confirm_password !== form.new_password
                            ? T.danger : (cfFocused ? T.forest : T.border),
                        }}
                      />
                      <button type="button" onClick={() => setShowConfirm(p => !p)} style={{
                        position: 'absolute', right: 12, top: '50%', transform: 'translateY(-50%)',
                        background: 'none', border: 'none', color: T.muted, cursor: 'pointer', fontSize: 15, padding: 0,
                      }}>
                        <i className={`bi ${showConfirm ? 'bi-eye-slash' : 'bi-eye'}`}></i>
                      </button>
                    </div>
                    {form.confirm_password && form.confirm_password !== form.new_password && (
                      <div style={{ fontSize: 11.5, color: T.danger, marginTop: 5 }}>
                        <i className="bi bi-x-circle-fill" style={{ marginRight: 4 }}></i>
                        Passwords do not match
                      </div>
                    )}
                    {form.confirm_password && form.confirm_password === form.new_password && form.new_password && (
                      <div style={{ fontSize: 11.5, color: T.mid, marginTop: 5 }}>
                        <i className="bi bi-check-circle-fill" style={{ marginRight: 4 }}></i>
                        Passwords match
                      </div>
                    )}
                  </FormField>
                </div>
              </div>
            </div>

            {/* Action Buttons */}
            <div style={{ display: 'flex', gap: 12, flexWrap: 'wrap' }}>
              <button
                type="submit"
                disabled={submitting}
                className="eg-btn-primary"
                style={{
                  display: 'inline-flex', alignItems: 'center', gap: 8,
                  background: `linear-gradient(135deg, ${T.forest} 0%, ${T.mid} 100%)`,
                  color: '#fff', border: 'none', borderRadius: 10,
                  padding: '11px 28px', fontSize: 14, fontWeight: 700,
                  cursor: 'pointer', fontFamily: 'DM Sans, sans-serif',
                  boxShadow: '0 2px 10px rgba(10,59,47,0.20)',
                  opacity: submitting ? 0.7 : 1, transition: 'opacity .2s',
                }}
              >
                {submitting
                  ? <><i className="bi bi-arrow-repeat" style={{ animation: 'spin 1s linear infinite' }}></i> Saving…</>
                  : <><i className="bi bi-floppy-fill" style={{ fontSize: 13 }}></i> Save Changes</>
                }
              </button>

              <a href="Employeedashboard.php" style={{
                display: 'inline-flex', alignItems: 'center', gap: 7,
                background: '#fff', border: `1.5px solid ${T.border}`,
                color: T.muted, borderRadius: 10, padding: '11px 24px',
                fontSize: 14, fontWeight: 600, textDecoration: 'none',
                transition: 'all .2s',
              }}>
                <i className="bi bi-x-lg" style={{ fontSize: 12 }}></i>
                Cancel
              </a>
            </div>

            <style>{`
              @keyframes spin { to { transform: rotate(360deg); } }
            `}</style>
          </form>
        </div>
      </div>
    </>
  );
}

ReactDOM.createRoot(document.getElementById('edit-officer-root')).render(<EditOfficerApp />);
</script>
</body>
</html>