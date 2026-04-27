<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// ── DB connection ────────────────────────────────────────────────
$dbhost = 'localhost';
$dbname = 'loandb';
$dbuser = 'root';
$dbpass = '';

try {
    $pdo = new PDO(
        "mysql:host=$dbhost;dbname=$dbname;charset=utf8mb4",
        $dbuser,
        $dbpass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("DB error: " . $e->getMessage());
}

// ── Handle AJAX contact update ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_update'])) {
    header('Content-Type: application/json');

    $newEmail   = trim($_POST['email']   ?? '');
    $newContact = trim($_POST['contact'] ?? '');
    $userId     = $_SESSION['user_id'];
    $errors     = [];

    if ($newEmail !== '' && !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email address.';
    }
    if ($newContact !== '' && !preg_match('/^[0-9+\-\s]{7,20}$/', $newContact)) {
        $errors[] = 'Invalid contact number.';
    }

    if (!empty($errors)) {
        echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE users SET user_email = ?, contact_number = ? WHERE id = ?");
    $stmt->execute([$newEmail, $newContact, $userId]);

    echo json_encode(['success' => true, 'email' => $newEmail, 'contact' => $newContact]);
    exit;
}

// ── Fetch user from DB ───────────────────────────────────────────
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// ── Build display values ─────────────────────────────────────────
$firstName  = $user['first_name']       ?? '';
$middleName = $user['middle_name']      ?? '';
$surname    = $user['surname']          ?? '';
$fullName   = trim("$firstName " . ($middleName ? "$middleName " : '') . $surname);

$streetAddress    = $user['address']           ?? '';
$barangayName     = $user['barangay_name']     ?? '';
$municipalityName = $user['municipality_name'] ?? '';
$provinceName     = $user['province_name']     ?? '';

$email         = $user['user_email']      ?? '';
$contact       = $user['contact_number'] ?? '';
$birthday      = $user['birthday']       ?? '';
$accountNumber = $user['account_number'] ?? '';

// Birthday: format nicely
$birthdayDisplay = '';
if (!empty($birthday)) {
    $bdate = DateTime::createFromFormat('Y-m-d', $birthday);
    $birthdayDisplay = $bdate ? $bdate->format('F j, Y') : $birthday;
}

// Avatar initials (first + last name initial)
$nameParts    = array_filter(explode(' ', trim($fullName)));
$firstInitial = strtoupper(mb_substr(reset($nameParts) ?: 'U', 0, 1));
$lastInitial  = strtoupper(mb_substr(end($nameParts)   ?: '',  0, 1));
$userInitials = $firstInitial . ($lastInitial !== $firstInitial ? $lastInitial : '');

// Encode for JS
$jsUser = json_encode([
    'full_name'      => $fullName,
    'account_number' => $accountNumber,
    'email'          => $email,
    'contact'        => $contact,
    'birthday'       => $birthdayDisplay,
    'street'         => $streetAddress,
    'barangay'       => $barangayName,
    'municipality'   => $municipalityName,
    'province'       => $provinceName,
    'initials'       => $userInitials,
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>My Profile – Evergreen Trust and Savings</title>
  <link rel="icon" type="image/png" href="pictures/logo.png"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"/>

  <style>
    :root {
      --eg-dark:    #003631;
      --eg-mid:     #005a4d;
      --eg-light:   #00796b;
      --eg-accent:  #1db57a;
      --eg-bg:      #f0faf6;
      --eg-surface: #e8f5f0;
      --eg-border:  #c4e8da;
      --eg-text:    #2d3748;
      --eg-muted:   #6b7280;
      --eg-error:   #dc2626;
      --nav-h:      64px;
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: var(--eg-bg);
      color: var(--eg-text);
      min-height: 100vh;
      padding-top: var(--nav-h);
    }

    /* ── Page Hero Banner ── */
    .page-hero {
      background: linear-gradient(135deg, var(--eg-dark) 0%, var(--eg-mid) 100%);
      color: #fff;
      padding: 3rem 1.5rem 2.5rem;
      text-align: center;
    }
    .page-hero h1 {
      font-size: clamp(1.6rem, 4vw, 2.4rem);
      font-weight: 800;
      margin-bottom: .5rem;
    }
    .page-hero p {
      font-size: 1rem;
      opacity: .85;
      max-width: 520px;
      margin: 0 auto;
    }

    /* ── Page content wrapper ── */
    .page-content {
      max-width: 860px;
      margin: 0 auto;
      padding: 2rem 1.25rem 4rem;
    }

    /* ── Back button ── */
    .back-btn {
      background: #fff;
      color: var(--eg-dark);
      border: 2px solid var(--eg-dark);
      border-radius: 8px;
      padding: 9px 20px;
      font-weight: 700;
      font-size: .9rem;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      transition: all .2s;
      margin-bottom: 1.5rem;
    }
    .back-btn:hover { background: var(--eg-dark); color: #fff; }

    /* ── Profile Hero Card ── */
    .profile-hero-card {
      background: linear-gradient(135deg, var(--eg-dark) 0%, var(--eg-mid) 100%);
      border-radius: 16px;
      padding: 2rem;
      margin-bottom: 1.5rem;
      position: relative;
      overflow: hidden;
      box-shadow: 0 8px 30px rgba(0,54,49,.2);
    }
    .profile-hero-card::before {
      content: '';
      position: absolute;
      width: 260px; height: 260px;
      border-radius: 50%;
      border: 50px solid rgba(255,255,255,.05);
      top: -80px; right: -50px;
      pointer-events: none;
    }
    .profile-hero-card::after {
      content: '';
      position: absolute;
      width: 150px; height: 150px;
      border-radius: 50%;
      border: 35px solid rgba(255,255,255,.04);
      bottom: -50px; left: 30px;
      pointer-events: none;
    }
    .hero-inner {
      position: relative; z-index: 1;
      display: flex; align-items: center;
      gap: 1.25rem; flex-wrap: wrap;
    }
    .hero-avatar {
      width: 70px; height: 70px;
      border-radius: 50%;
      background: rgba(255,255,255,.15);
      border: 3px solid rgba(255,255,255,.3);
      display: flex; align-items: center; justify-content: center;
      font-size: 1.5rem; font-weight: 700;
      color: white; flex-shrink: 0;
    }
    .hero-text { flex: 1; min-width: 0; }
    .hero-name {
      font-size: 1.4rem; font-weight: 800;
      color: white; margin-bottom: 4px;
      word-break: break-word;
    }
    .hero-acct {
      font-size: .8rem;
      color: rgba(255,255,255,.65);
      letter-spacing: 1px;
      display: flex; align-items: center; gap: 5px;
    }
    .hero-badge {
      background: rgba(255,255,255,.12);
      border: 1px solid rgba(255,255,255,.22);
      color: rgba(255,255,255,.9);
      font-size: .72rem; font-weight: 700;
      padding: 5px 14px; border-radius: 99px;
      letter-spacing: .5px;
      white-space: nowrap;
      align-self: flex-start;
    }

    /* ── Info Cards ── */
    .info-card {
      background: #fff;
      border-radius: 14px;
      border: 1px solid var(--eg-border);
      margin-bottom: 1.25rem;
      overflow: hidden;
      box-shadow: 0 2px 14px rgba(0,54,49,.07);
      transition: box-shadow .2s;
    }
    .info-card:hover { box-shadow: 0 4px 20px rgba(0,54,49,.12); }

    .card-head {
      display: flex; align-items: center; gap: .65rem;
      padding: 1rem 1.5rem;
      background: var(--eg-surface);
      border-bottom: 1px solid var(--eg-border);
    }
    .card-head-icon {
      width: 32px; height: 32px;
      border-radius: 8px;
      background: #fff;
      border: 1px solid var(--eg-border);
      display: flex; align-items: center; justify-content: center;
      color: var(--eg-light);
      font-size: .9rem; flex-shrink: 0;
    }
    .card-head-title {
      font-size: .75rem; font-weight: 700;
      text-transform: uppercase; letter-spacing: 1px;
      color: var(--eg-dark);
    }
    .readonly-tag {
      margin-left: auto;
      display: inline-flex; align-items: center; gap: 3px;
      font-size: .68rem; color: var(--eg-muted);
      background: #fff; border-radius: 99px;
      padding: 3px 10px;
      border: 1px solid var(--eg-border);
    }
    .editable-tag {
      margin-left: auto;
      display: inline-flex; align-items: center; gap: 3px;
      font-size: .68rem; color: var(--eg-light);
      background: #fff; border-radius: 99px;
      padding: 3px 10px;
      border: 1px solid var(--eg-border);
    }

    .info-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
    }
    .info-cell {
      padding: 1.1rem 1.5rem;
      border-bottom: 1px solid #f0faf6;
      border-right: 1px solid #f0faf6;
    }
    .info-cell:nth-child(even) { border-right: none; }
    .info-cell:last-child { border-bottom: none; }
    .info-cell:nth-last-child(2):nth-child(odd) { border-bottom: none; }
    .info-cell.span-full { grid-column: 1 / -1; border-right: none; }
    .info-cell.span-full:last-child { border-bottom: none; }
    .info-cell.span-full:nth-last-child(2) { border-bottom: 1px solid #f0faf6; }

    .info-label {
      font-size: .7rem; font-weight: 700;
      text-transform: uppercase; letter-spacing: .8px;
      color: var(--eg-muted); margin-bottom: 5px;
    }
    .info-value {
      font-size: .95rem; font-weight: 500;
      color: var(--eg-text);
      word-break: break-word;
      line-height: 1.45;
    }
    .info-value.mono {
      font-family: 'Courier New', monospace;
      font-size: .95rem; font-weight: 700;
      color: var(--eg-dark); letter-spacing: 1.5px;
    }
    .info-value.empty { color: #b0c4bc; font-style: italic; font-weight: 400; }

    /* ── Account toggle ── */
    .acct-row { display: flex; align-items: center; gap: .5rem; }
    .acct-text { flex: 1; min-width: 0; }
    .btn-visibility {
      background: none; border: none; padding: 0;
      color: var(--eg-muted); font-size: 1rem;
      cursor: pointer; display: flex; align-items: center;
      transition: color .18s; flex-shrink: 0;
    }
    .btn-visibility:hover { color: var(--eg-dark); }

    /* ── Editable cell ── */
    .editable-cell-inner { display: flex; align-items: center; gap: .5rem; }
    .editable-val { flex: 1; min-width: 0; }
    .btn-edit-field {
      background: none; border: none; padding: 0;
      color: var(--eg-muted); font-size: .9rem;
      cursor: pointer; display: flex; align-items: center;
      transition: color .18s; flex-shrink: 0;
    }
    .btn-edit-field:hover { color: var(--eg-light); }

    .edit-input {
      width: 100%;
      border: 1.5px solid var(--eg-border);
      border-radius: 8px; padding: .42rem .7rem;
      font-family: 'Segoe UI', sans-serif;
      font-size: .9rem; color: var(--eg-text);
      background: #f9fbfa; outline: none;
      transition: border-color .18s, box-shadow .18s;
    }
    .edit-input:focus { border-color: var(--eg-light); box-shadow: 0 0 0 3px rgba(0,121,107,.12); background: white; }
    .edit-input.error { border-color: var(--eg-error); }

    .edit-actions { display: flex; gap: .4rem; margin-top: .5rem; }
    .btn-save-edit {
      flex: 1; padding: .38rem .7rem;
      background: var(--eg-dark); color: white;
      border: none; border-radius: 8px;
      font-family: 'Segoe UI', sans-serif;
      font-size: .82rem; font-weight: 700;
      cursor: pointer;
      transition: background .18s, transform .12s;
      display: flex; align-items: center; justify-content: center; gap: 4px;
    }
    .btn-save-edit:hover:not(:disabled) { background: var(--eg-mid); transform: translateY(-1px); }
    .btn-save-edit:disabled { opacity: .6; cursor: not-allowed; }

    .btn-cancel-edit {
      padding: .38rem .7rem;
      background: #f0f5f2; color: var(--eg-text);
      border: 1.5px solid var(--eg-border); border-radius: 8px;
      font-family: 'Segoe UI', sans-serif;
      font-size: .82rem; font-weight: 600;
      cursor: pointer; transition: background .18s;
    }
    .btn-cancel-edit:hover { background: var(--eg-surface); }

    .field-error {
      font-size: .72rem; color: var(--eg-error);
      margin-top: 4px; display: flex; align-items: center; gap: 3px;
    }

    /* ── Toast ── */
    .toast-wrap {
      position: fixed; bottom: 1.5rem; left: 50%;
      transform: translateX(-50%) translateY(20px);
      z-index: 9999; pointer-events: none;
      opacity: 0; transition: opacity .25s, transform .25s;
    }
    .toast-wrap.show { opacity: 1; transform: translateX(-50%) translateY(0); }
    .toast-inner {
      display: flex; align-items: center; gap: .55rem;
      padding: .75rem 1.3rem; border-radius: 12px;
      font-size: .88rem; font-weight: 700;
      white-space: nowrap; box-shadow: 0 8px 28px rgba(0,0,0,.15);
    }
    .toast-inner.success { background: var(--eg-dark); color: white; }
    .toast-inner.error { background: #fef2f2; color: var(--eg-error); border: 1px solid #fecaca; }

    /* ── Security card ── */
    .pw-card-body { padding: 1.4rem 1.5rem; }
    .pw-row { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: .85rem; }
    .pw-label-text { font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .8px; color: var(--eg-muted); margin-bottom: 6px; }
    .pw-dots { display: flex; gap: 5px; align-items: center; }
    .pw-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--eg-dark); }

    .btn-change-pw {
      display: inline-flex; align-items: center; gap: 7px;
      padding: .6rem 1.2rem;
      background: var(--eg-dark); color: white;
      border: none; border-radius: 8px;
      font-family: 'Segoe UI', sans-serif;
      font-size: .88rem; font-weight: 700;
      cursor: pointer; text-decoration: none;
      white-space: nowrap;
      transition: background .2s, transform .15s, box-shadow .2s;
      box-shadow: 0 2px 8px rgba(0,54,49,.2);
    }
    .btn-change-pw:hover {
      background: var(--eg-mid); color: white;
      transform: translateY(-2px);
      box-shadow: 0 5px 16px rgba(0,54,49,.28);
    }
    .pw-note {
      margin-top: .8rem; font-size: .78rem; color: var(--eg-muted);
      line-height: 1.55; display: flex; align-items: flex-start; gap: 5px;
    }

    /* ── Footer ── */
    footer { background: var(--eg-dark); color: #cde8e1; padding: 2rem 1.5rem 1rem; }
    .footer-logo { width: 80px; margin-bottom: .75rem; }
    .footer-tagline { font-size: .87rem; color: #9abfba; line-height: 1.6; }
    .footer-col h3 { color: #fff; font-size: .9rem; font-weight: 700; text-transform: uppercase; letter-spacing: .8px; margin-bottom: .75rem; }
    .footer-col a { color: #9abfba; text-decoration: none; font-size: .87rem; display: block; margin-bottom: .4rem; transition: color .15s; }
    .footer-col a:hover { color: #fff; }
    .social-links a { display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px; border-radius: 50%; background: rgba(255,255,255,.1); color: #fff; margin-right: .4rem; font-size: .85rem; transition: background .2s; }
    .social-links a:hover { background: rgba(255,255,255,.25); }
    .footer-divider { border-color: rgba(255,255,255,.1); margin: 1.5rem 0; }
    .footer-bottom { font-size: .8rem; color: #7aada6; }
    .footer-bottom a { color: #9abfba; text-decoration: none; }
    .footer-bottom a:hover { color: #fff; }

    /* ── Responsive ── */
    @media (max-width: 600px) {
      body { --nav-h: 58px; }
      .page-hero { padding: 2.2rem 1rem 1.8rem; }
      .page-content { padding: 1.5rem .9rem 3rem; }
      .profile-hero-card { padding: 1.4rem 1.2rem; }
      .hero-avatar { width: 56px; height: 56px; font-size: 1.2rem; }
      .hero-name { font-size: 1.15rem; }
      .hero-badge { display: none; }
      .info-grid { grid-template-columns: 1fr; }
      .info-cell { border-right: none !important; }
      .info-cell:nth-last-child(2):nth-child(odd) { border-bottom: 1px solid #f0faf6 !important; }
      .info-cell:last-child { border-bottom: none !important; }
      .card-head { padding: .85rem 1.1rem; }
      .info-cell { padding: .95rem 1.1rem; }
      .pw-card-body { padding: 1.1rem; }
    }

    @keyframes spin { to { transform: rotate(360deg); } }
    .spin { display: inline-block; animation: spin .7s linear infinite; }
  </style>
</head>
<body>

<?php include 'header.php'; ?>

<!-- Hero Banner -->
<div class="page-hero">
  <h1><i class="bi bi-person-circle me-2"></i>My Profile</h1>
  <p>View and manage your personal account information</p>
</div>

<div class="page-content">
  <a href="index.php" class="back-btn"><i class="bi bi-arrow-left-circle"></i> Back to Home</a>
  <div id="profile-root"></div>
</div>

<!-- Footer -->
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
        <h3>Legal</h3>
        <a href="Privacy.php">Privacy Policy</a>
        <a href="Terms.php">Terms &amp; Agreements</a>
        <a href="FAQs.php">FAQs</a>
        <a href="AboutUs.php">About Us</a>
      </div>
    </div>
    <hr class="footer-divider">
    <div class="d-flex flex-wrap justify-content-between align-items-center footer-bottom gap-2">
      <p class="mb-0">&copy; <?= date('Y') ?> Evergreen Bank. All rights reserved.</p>
      <p class="mb-0">Member FDIC. Equal Housing Lender. Evergreen Bank, N.A.</p>
    </div>
  </div>
</footer>

<!-- Toast container -->
<div class="toast-wrap" id="toast-wrap">
  <div class="toast-inner" id="toast-inner"></div>
</div>

<!-- React 18 + Babel -->
<script src="https://unpkg.com/react@18/umd/react.production.min.js"></script>
<script src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js"></script>
<script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>

<script type="text/babel">
/* ── PHP → JS data ─────────────────────────────────────────── */
const USER = <?= $jsUser ?>;

/* ── Toast helper ── */
function showToast(message, type = 'success') {
  const wrap  = document.getElementById('toast-wrap');
  const inner = document.getElementById('toast-inner');
  inner.className = `toast-inner ${type}`;
  inner.innerHTML = `<i class="bi ${type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-circle-fill'}"></i> ${message}`;
  wrap.classList.add('show');
  clearTimeout(wrap._tid);
  wrap._tid = setTimeout(() => wrap.classList.remove('show'), 3200);
}

function CardHead({ icon, title, badge }) {
  return (
    <div className="card-head">
      <div className="card-head-icon"><i className={`bi ${icon}`}/></div>
      <span className="card-head-title">{title}</span>
      {badge === 'readonly' && (
        <span className="readonly-tag">
          <i className="bi bi-lock-fill" style={{ fontSize: '.6rem' }}/> Read only
        </span>
      )}
      {badge === 'editable' && (
        <span className="editable-tag">
          <i className="bi bi-pencil-fill" style={{ fontSize: '.6rem' }}/> Editable
        </span>
      )}
    </div>
  );
}

function InfoCell({ label, value, mono, spanFull }) {
  const blank = !value || String(value).trim() === '';
  return (
    <div className={`info-cell${spanFull ? ' span-full' : ''}`}>
      <div className="info-label">{label}</div>
      <div className={`info-value${mono ? ' mono' : ''}${blank ? ' empty' : ''}`}>
        {blank ? 'Not provided' : value}
      </div>
    </div>
  );
}

function AccountNumberCell({ value }) {
  const [visible, setVisible] = React.useState(false);
  const blank = !value || String(value).trim() === '';
  const masked = blank ? '' : '•'.repeat(Math.max(0, value.length - 4)) + value.slice(-4);
  return (
    <div className="info-cell span-full">
      <div className="info-label">Account Number</div>
      <div className="acct-row">
        <div className={`info-value mono acct-text${blank ? ' empty' : ''}`}>
          {blank ? 'Not provided' : (visible ? value : masked)}
        </div>
        {!blank && (
          <button className="btn-visibility" onClick={() => setVisible(v => !v)}
            title={visible ? 'Hide account number' : 'Show account number'}>
            <i className={`bi ${visible ? 'bi-eye-slash-fill' : 'bi-eye-fill'}`}/>
          </button>
        )}
      </div>
    </div>
  );
}

function EditableCell({ label, initialValue, fieldKey, onSaved }) {
  const [editing,  setEditing]  = React.useState(false);
  const [inputVal, setInputVal] = React.useState(initialValue);
  const [current,  setCurrent]  = React.useState(initialValue);
  const [saving,   setSaving]   = React.useState(false);
  const [error,    setError]    = React.useState('');
  const inputRef = React.useRef(null);

  React.useEffect(() => { if (editing && inputRef.current) inputRef.current.focus(); }, [editing]);

  function startEdit() { setInputVal(current); setError(''); setEditing(true); }
  function cancelEdit() { setEditing(false); setError(''); }

  function validate(val) {
    if (fieldKey === 'email' && val && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) return 'Enter a valid email address.';
    if (fieldKey === 'contact' && val && !/^[0-9+\-\s]{7,20}$/.test(val)) return 'Enter a valid contact number.';
    return '';
  }

  async function saveEdit() {
    const trimmed = inputVal.trim();
    const validErr = validate(trimmed);
    if (validErr) { setError(validErr); return; }
    setSaving(true); setError('');
    try {
      const body = new FormData();
      body.append('ajax_update', '1');
      body.append('email',   fieldKey === 'email'   ? trimmed : (window.__profileEmail   || ''));
      body.append('contact', fieldKey === 'contact' ? trimmed : (window.__profileContact || ''));
      const res  = await fetch(window.location.href, { method: 'POST', body });
      const data = await res.json();
      if (data.success) {
        setCurrent(trimmed);
        window.__profileEmail   = data.email;
        window.__profileContact = data.contact;
        onSaved && onSaved(fieldKey, trimmed);
        setEditing(false);
        showToast(`${label} updated successfully.`, 'success');
      } else {
        setError(data.message || 'Failed to save. Please try again.');
        showToast(data.message || 'Update failed.', 'error');
      }
    } catch {
      setError('Network error. Please try again.');
      showToast('Network error. Please try again.', 'error');
    }
    setSaving(false);
  }

  const blank = !current || String(current).trim() === '';
  return (
    <div className="info-cell">
      <div className="info-label">{label}</div>
      {!editing ? (
        <div className="editable-cell-inner">
          <div className={`info-value editable-val${blank ? ' empty' : ''}`}>{blank ? 'Not provided' : current}</div>
          <button className="btn-edit-field" onClick={startEdit} title={`Edit ${label}`}>
            <i className="bi bi-pencil"/>
          </button>
        </div>
      ) : (
        <div>
          <input ref={inputRef} className={`edit-input${error ? ' error' : ''}`}
            type={fieldKey === 'email' ? 'email' : 'tel'}
            value={inputVal}
            onChange={e => { setInputVal(e.target.value); setError(''); }}
            onKeyDown={e => { if (e.key === 'Enter') saveEdit(); if (e.key === 'Escape') cancelEdit(); }}
            placeholder={label} disabled={saving}/>
          {error && <div className="field-error"><i className="bi bi-exclamation-circle-fill"/>{error}</div>}
          <div className="edit-actions">
            <button className="btn-save-edit" onClick={saveEdit} disabled={saving}>
              {saving ? <><i className="bi bi-arrow-repeat spin"/>Saving…</> : <><i className="bi bi-check-lg"/>Save</>}
            </button>
            <button className="btn-cancel-edit" onClick={cancelEdit} disabled={saving}>Cancel</button>
          </div>
        </div>
      )}
    </div>
  );
}

function ProfilePage() {
  const [emailVal,   setEmailVal]   = React.useState(USER.email   || '');
  const [contactVal, setContactVal] = React.useState(USER.contact || '');
  React.useEffect(() => { window.__profileEmail = emailVal; window.__profileContact = contactVal; }, []);

  function handleSaved(field, val) {
    if (field === 'email')   { setEmailVal(val);   window.__profileEmail   = val; }
    if (field === 'contact') { setContactVal(val); window.__profileContact = val; }
  }

  return (
    <>
      {/* Profile Hero */}
      <div className="profile-hero-card">
        <div className="hero-inner">
          <div className="hero-avatar">{USER.initials || 'U'}</div>
          <div className="hero-text">
            <div className="hero-name">{USER.full_name || 'Account Holder'}</div>
            <div className="hero-acct">
              <i className="bi bi-credit-card" style={{ fontSize: '.7rem' }}/>
              {USER.account_number ? '•••• ' + USER.account_number.slice(-4) : '—'}
            </div>
          </div>
          <div className="hero-badge"><i className="bi bi-patch-check-fill me-1"/>Verified Member</div>
        </div>
      </div>

      {/* Personal Information */}
      <div className="info-card">
        <CardHead icon="bi-person-fill" title="Personal Information" badge="readonly"/>
        <div className="info-grid">
          <InfoCell label="Full Name" value={USER.full_name}/>
          <InfoCell label="Birthday"  value={USER.birthday}/>
          <AccountNumberCell value={USER.account_number}/>
        </div>
      </div>

      {/* Address */}
      <div className="info-card">
        <CardHead icon="bi-geo-alt-fill" title="Address" badge="readonly"/>
        <div className="info-grid">
          <InfoCell label="House No. / Street / Unit" value={USER.street}       spanFull/>
          <InfoCell label="Barangay"                  value={USER.barangay}/>
          <InfoCell label="Municipality / City"       value={USER.municipality}/>
          <InfoCell label="Province"                  value={USER.province}     spanFull/>
        </div>
      </div>

      {/* Contact Details */}
      <div className="info-card">
        <CardHead icon="bi-telephone-fill" title="Contact Details" badge="editable"/>
        <div className="info-grid">
          <EditableCell label="Email Address"  initialValue={emailVal}   fieldKey="email"   onSaved={handleSaved}/>
          <EditableCell label="Contact Number" initialValue={contactVal} fieldKey="contact" onSaved={handleSaved}/>
        </div>
      </div>

      {/* Security */}
      <div className="info-card">
        <CardHead icon="bi-key-fill" title="Security"/>
        <div className="pw-card-body">
          <div className="pw-row">
            <div>
              <div className="pw-label-text">Password</div>
              <div className="pw-dots">{[0,1,2,3,4,5,6,7].map(i => <div key={i} className="pw-dot"/>)}</div>
            </div>
            <a href="Forgotpass.php" className="btn-change-pw">
              <i className="bi bi-pencil-square"/>Change Password
            </a>
          </div>
          <div className="pw-note">
            <i className="bi bi-info-circle"/>
            Clicking "Change Password" will take you to the password reset page.
          </div>
        </div>
      </div>
    </>
  );
}

ReactDOM.createRoot(document.getElementById('profile-root')).render(<ProfilePage/>);
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
