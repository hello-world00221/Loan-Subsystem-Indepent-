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
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,400&display=swap" rel="stylesheet"/>

  <style>
    /* ═══════════════════════════════════════════════════════════
       VARIABLES  — identical palette to register-account.php
    ═══════════════════════════════════════════════════════════ */
    :root {
      --eg-dark:   #0a3b2f;
      --eg-mid:    #1a6b5a;
      --eg-deep:   #082e24;
      --eg-accent: #43a047;
      --eg-light:  #e8f5e9;
      --eg-bg:     #f4f8f6;
      --eg-text:   #2d4a3e;
      --eg-muted:  #6c8a7e;
      --eg-border: #d4e6de;
      --nav-h:     64px;
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'DM Sans', sans-serif;
      background: var(--eg-bg);
      color: var(--eg-text);
      min-height: 100vh;
      padding-top: var(--nav-h);
    }

    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-track { background: #eef4f1; }
    ::-webkit-scrollbar-thumb { background: #b0ccc4; border-radius: 3px; }

    /* ── Page shell ── */
    .profile-page {
      max-width: 860px;
      margin: 0 auto;
      padding: 2.25rem 1.25rem 5rem;
      animation: fadeUp .45s cubic-bezier(.22,1,.36,1) both;
    }
    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(20px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    /* ── Page header row ── */
    .page-header {
      display: flex;
      align-items: center;
      gap: 1rem;
      margin-bottom: 1.75rem;
    }
    .back-btn {
      width: 38px; height: 38px;
      border-radius: 10px;
      background: white;
      border: 1.5px solid var(--eg-border);
      display: flex; align-items: center; justify-content: center;
      color: var(--eg-dark);
      text-decoration: none;
      font-size: 1rem;
      flex-shrink: 0;
      transition: all .2s;
      box-shadow: 0 1px 4px rgba(10,59,47,.07);
    }
    .back-btn:hover {
      background: var(--eg-dark);
      color: white;
      border-color: var(--eg-dark);
      box-shadow: 0 3px 10px rgba(10,59,47,.2);
    }
    .page-header-text h1 {
      font-family: 'Playfair Display', serif;
      font-size: 1.65rem;
      font-weight: 700;
      color: var(--eg-dark);
      line-height: 1.2;
    }
    .page-header-text p {
      font-size: .82rem;
      color: var(--eg-muted);
      margin-top: 2px;
    }

    /* ── Hero banner ── */
    .profile-hero {
      background: linear-gradient(135deg, var(--eg-deep) 0%, var(--eg-dark) 55%, #164d3f 100%);
      border-radius: 18px;
      padding: 1.85rem 2rem;
      margin-bottom: 1.25rem;
      position: relative;
      overflow: hidden;
      box-shadow: 0 8px 30px rgba(10,59,47,.22);
    }
    /* decorative circles */
    .profile-hero::before {
      content: '';
      position: absolute;
      width: 280px; height: 280px;
      border-radius: 50%;
      border: 55px solid rgba(255,255,255,.05);
      top: -85px; right: -55px;
      pointer-events: none;
    }
    .profile-hero::after {
      content: '';
      position: absolute;
      width: 170px; height: 170px;
      border-radius: 50%;
      border: 38px solid rgba(255,255,255,.04);
      bottom: -55px; left: 36px;
      pointer-events: none;
    }
    .hero-inner {
      position: relative; z-index: 1;
      display: flex; align-items: center;
      gap: 1.35rem; flex-wrap: wrap;
    }
    .hero-avatar {
      width: 72px; height: 72px;
      border-radius: 50%;
      background: rgba(255,255,255,.14);
      border: 3px solid rgba(255,255,255,.28);
      display: flex; align-items: center; justify-content: center;
      font-size: 1.6rem; font-weight: 700;
      color: white; flex-shrink: 0;
      letter-spacing: .5px;
      backdrop-filter: blur(6px);
    }
    .hero-text { flex: 1; min-width: 0; }
    .hero-name {
      font-family: 'Playfair Display', serif;
      font-size: 1.45rem; font-weight: 700;
      color: white;
      word-break: break-word;
      margin-bottom: 5px;
    }
    .hero-acct {
      font-family: 'Courier New', monospace;
      font-size: .78rem;
      color: rgba(255,255,255,.62);
      letter-spacing: 1.4px;
      display: flex; align-items: center; gap: 5px;
    }
    .hero-badge {
      background: rgba(255,255,255,.12);
      border: 1px solid rgba(255,255,255,.2);
      color: rgba(255,255,255,.85);
      font-size: .7rem; font-weight: 600;
      padding: 4px 13px;
      border-radius: 99px;
      letter-spacing: .6px;
      white-space: nowrap;
      backdrop-filter: blur(4px);
      align-self: flex-start;
    }

    /* ── Info cards ── */
    .info-card {
      background: white;
      border-radius: 14px;
      border: 1px solid var(--eg-border);
      margin-bottom: 1.15rem;
      overflow: hidden;
      box-shadow: 0 1px 4px rgba(10,59,47,.05);
      transition: box-shadow .2s;
    }
    .info-card:hover { box-shadow: 0 4px 18px rgba(10,59,47,.09); }

    /* card header strip */
    .card-head {
      display: flex; align-items: center; gap: .6rem;
      padding: .9rem 1.4rem;
      background: #fafcfb;
      border-bottom: 1px solid var(--eg-border);
    }
    .card-head-icon {
      width: 30px; height: 30px;
      border-radius: 8px;
      background: var(--eg-light);
      display: flex; align-items: center; justify-content: center;
      color: var(--eg-dark);
      font-size: .85rem; flex-shrink: 0;
    }
    .card-head-title {
      font-size: .72rem; font-weight: 700;
      text-transform: uppercase; letter-spacing: 1px;
      color: var(--eg-muted);
    }
    .readonly-tag {
      margin-left: auto;
      display: inline-flex; align-items: center; gap: 3px;
      font-size: .65rem; color: var(--eg-muted);
      background: #f0f5f2; border-radius: 99px;
      padding: 2px 8px;
      border: 1px solid var(--eg-border);
      white-space: nowrap;
    }

    /* info grid of cells */
    .info-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
    }
    .info-cell {
      padding: 1.05rem 1.4rem;
      border-bottom: 1px solid #f0f5f2;
      border-right: 1px solid #f0f5f2;
    }
    .info-cell:nth-child(even)                  { border-right: none; }
    .info-cell:last-child                        { border-bottom: none; }
    .info-cell:nth-last-child(2):nth-child(odd)  { border-bottom: none; }

    .info-cell.span-full {
      grid-column: 1 / -1;
      border-right: none;
    }
    /* when a span-full is the last row its bottom border must also go */
    .info-cell.span-full:last-child              { border-bottom: none; }
    /* when span-full sits just before the last cell */
    .info-cell.span-full:nth-last-child(2)       { border-bottom: 1px solid #f0f5f2; }

    .info-label {
      font-size: .68rem; font-weight: 700;
      text-transform: uppercase; letter-spacing: .9px;
      color: var(--eg-muted);
      margin-bottom: 5px;
    }
    .info-value {
      font-size: .93rem; font-weight: 500;
      color: var(--eg-text);
      word-break: break-word;
      line-height: 1.45;
    }
    .info-value.mono {
      font-family: 'Courier New', monospace;
      font-size: .98rem; font-weight: 700;
      color: var(--eg-dark);
      letter-spacing: 1.5px;
    }
    .info-value.empty {
      color: #b0c4bc;
      font-style: italic;
      font-weight: 400;
    }

    /* ── Security / password card ── */
    .pw-card-body { padding: 1.35rem 1.4rem; }
    .pw-row {
      display: flex; align-items: center;
      justify-content: space-between;
      flex-wrap: wrap; gap: .85rem;
    }
    .pw-label-text {
      font-size: .68rem; font-weight: 700;
      text-transform: uppercase; letter-spacing: .9px;
      color: var(--eg-muted); margin-bottom: 6px;
    }
    .pw-dots { display: flex; gap: 5px; align-items: center; }
    .pw-dot  {
      width: 8px; height: 8px;
      border-radius: 50%;
      background: var(--eg-text);
    }

    /* "Change Password" button — links to Forgotpass.php */
    .btn-change-pw {
      display: inline-flex; align-items: center; gap: 6px;
      padding: .55rem 1.15rem;
      background: var(--eg-dark);
      color: white;
      border: none; border-radius: 9px;
      font-family: 'DM Sans', sans-serif;
      font-size: .85rem; font-weight: 600;
      cursor: pointer;
      text-decoration: none;
      white-space: nowrap;
      transition: background .2s, transform .15s, box-shadow .2s;
      box-shadow: 0 2px 8px rgba(10,59,47,.2);
    }
    .btn-change-pw:hover {
      background: var(--eg-mid);
      color: white;
      transform: translateY(-1px);
      box-shadow: 0 5px 16px rgba(10,59,47,.28);
      text-decoration: none;
    }
    .pw-note {
      margin-top: .8rem;
      font-size: .76rem;
      color: var(--eg-muted);
      line-height: 1.55;
      display: flex; align-items: flex-start; gap: 5px;
    }
    .pw-note i { flex-shrink: 0; margin-top: 2px; }

    /* ══════════════════════════════════════════
       RESPONSIVE
    ══════════════════════════════════════════ */
    @media (max-width: 600px) {
      body { --nav-h: 58px; }

      .profile-page  { padding: 1.4rem .9rem 3.5rem; }
      .profile-hero  { padding: 1.35rem 1.25rem; }
      .hero-avatar   { width: 58px; height: 58px; font-size: 1.2rem; }
      .hero-name     { font-size: 1.15rem; }
      .hero-badge    { display: none; }

      /* collapse 2-col grid to 1-col on mobile */
      .info-grid                            { grid-template-columns: 1fr; }
      .info-cell                            { border-right: none !important; }
      .info-cell:nth-last-child(2):nth-child(odd) { border-bottom: 1px solid #f0f5f2 !important; }
      .info-cell:last-child                 { border-bottom: none !important; }
    }

    @media (max-width: 380px) {
      .page-header-text h1 { font-size: 1.3rem; }
      .card-head           { padding: .8rem 1rem; }
      .info-cell           { padding: .9rem 1rem; }
      .pw-card-body        { padding: 1.1rem 1rem; }
    }
  </style>
</head>
<body>

<?php include 'header.php'; ?>

<div class="profile-page" id="profile-root"></div>

<!-- React 18 + Babel (same stack as register-account.php) -->
<script src="https://unpkg.com/react@18/umd/react.production.min.js"></script>
<script src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js"></script>
<script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>

<script type="text/babel">
/* ── PHP → JS data ─────────────────────────────────────────── */
const USER = <?= $jsUser ?>;

/* ── Reusable: one read-only info cell ─────────────────────── */
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

/* ── Reusable: card header strip ───────────────────────────── */
function CardHead({ icon, title, showLock = true }) {
  return (
    <div className="card-head">
      <div className="card-head-icon"><i className={`bi ${icon}`}/></div>
      <span className="card-head-title">{title}</span>
      {showLock && (
        <span className="readonly-tag">
          <i className="bi bi-lock-fill" style={{ fontSize: '.6rem' }}/>
          Read only
        </span>
      )}
    </div>
  );
}

/* ── Main profile page ─────────────────────────────────────── */
function ProfilePage() {
  return (
    <>
      {/* ─── Page header ─── */}
      <div className="page-header">
        <a href="index.php" className="back-btn" title="Back to Home">
          <i className="bi bi-arrow-left"/>
        </a>
        <div className="page-header-text">
          <h1>My Profile</h1>
          <p>Your account information</p>
        </div>
      </div>

      {/* ─── Hero banner ─── */}
      <div className="profile-hero">
        <div className="hero-inner">
          <div className="hero-avatar">{USER.initials || 'U'}</div>
          <div className="hero-text">
            <div className="hero-name">{USER.full_name || 'Account Holder'}</div>
            <div className="hero-acct">
              <i className="bi bi-credit-card" style={{ fontSize: '.7rem' }}/>
              {USER.account_number || '—'}
            </div>
          </div>
          <div className="hero-badge">
            <i className="bi bi-patch-check-fill me-1"/>Verified Member
          </div>
        </div>
      </div>

      {/* ─── Personal Information ─── */}
      <div className="info-card">
        <CardHead icon="bi-person-fill" title="Personal Information"/>
        <div className="info-grid">
          <InfoCell label="Full Name"      value={USER.full_name}/>
          <InfoCell label="Birthday"       value={USER.birthday}/>
          <InfoCell label="Account Number" value={USER.account_number} mono spanFull/>
        </div>
      </div>

      {/* ─── Address ─── */}
      <div className="info-card">
        <CardHead icon="bi-geo-alt-fill" title="Address"/>
        <div className="info-grid">
          <InfoCell label="House No. / Street / Unit" value={USER.street}       spanFull/>
          <InfoCell label="Barangay"                  value={USER.barangay}/>
          <InfoCell label="Municipality / City"       value={USER.municipality}/>
          <InfoCell label="Province"                  value={USER.province}     spanFull/>
        </div>
      </div>

      {/* ─── Contact Details ─── */}
      <div className="info-card">
        <CardHead icon="bi-telephone-fill" title="Contact Details"/>
        <div className="info-grid">
          <InfoCell label="Email Address"  value={USER.email}/>
          <InfoCell label="Contact Number" value={USER.contact}/>
        </div>
      </div>

      {/* ─── Security ─── */}
      <div className="info-card">
        <CardHead icon="bi-key-fill" title="Security" showLock={false}/>
        <div className="pw-card-body">
          <div className="pw-row">
            <div>
              <div className="pw-label-text">Password</div>
              <div className="pw-dots">
                {[0,1,2,3,4,5,6,7].map(i => (
                  <div key={i} className="pw-dot"/>
                ))}
              </div>
            </div>
            {/* ↓ redirects directly to Forgotpass.php — no in-page modal */}
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