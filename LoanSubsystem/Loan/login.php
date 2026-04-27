<?php
session_start();

if (isset($_SESSION['user_id']))     { header("Location: index.php");                    exit; }
if (isset($_SESSION['officer_id']))  { header("Location: ../Employee/Employeedashboard.php"); exit; }
if (isset($_SESSION['admin_id']))    { header("Location: ../Employee/Employeedashboard.php"); exit; }

$error        = "";
$loginSuccess = false;
$redirectTo   = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password']      ?? '';

    if (empty($email) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        $host   = 'localhost';
        $dbname = 'loandb';
        $dbuser = 'root';
        $dbpass = '';

        try {
            $pdo = new PDO(
                "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
                $dbuser, $dbpass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            // ── 1. Check officers / superadmin table first ────────────────
            $stmtOfficer = $pdo->prepare(
                "SELECT id, full_name, officer_email, password_hash, employee_number, role, status
                 FROM officers WHERE officer_email = ? LIMIT 1"
            );
            $stmtOfficer->execute([$email]);
            $officer = $stmtOfficer->fetch(PDO::FETCH_ASSOC);

            if ($officer && password_verify($password, $officer['password_hash'])) {
                if ($officer['status'] !== 'Active') {
                    $error = "Your account is inactive. Please contact your administrator.";
                } else {
                    $_SESSION['pending_officer_id']     = $officer['id'];
                    $_SESSION['pending_officer_name']   = $officer['full_name'];
                    $_SESSION['pending_officer_email']  = $officer['officer_email'];
                    $_SESSION['pending_officer_empnum'] = $officer['employee_number'];
                    $_SESSION['pending_officer_role']   = $officer['role'];
                    $_SESSION['pending_second_factor']  = true;
                    $loginSuccess = true;
                    $redirectTo   = "../Employee/Employeelogin.php";
                }
            } else {
                // ── 2. Check regular users table ──────────────────────────
                $stmtUser = $pdo->prepare(
                    "SELECT id, full_name, user_email, password_hash,
                            account_number, contact_number, created_at
                     FROM users WHERE user_email = ? LIMIT 1"
                );
                $stmtUser->execute([$email]);
                $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

                if (!$user) {
                    $error = "No account found with that email address.";
                } elseif (!password_verify($password, $user['password_hash'])) {
                    $error = "Incorrect password. Please try again.";
                } else {
                    $_SESSION['user_id']        = $user['id'];
                    $_SESSION['full_name']       = $user['full_name'];
                    $_SESSION['user_name']       = $user['full_name'];
                    $_SESSION['email']           = $user['user_email'];
                    $_SESSION['user_email']      = $user['user_email'];
                    $_SESSION['account_number']  = $user['account_number'];
                    $_SESSION['contact_number']  = $user['contact_number'] ?? '';
                    $_SESSION['created_at']      = $user['created_at'];
                    $loginSuccess = true;
                    $redirectTo   = "index.php";
                }
            }

        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

$errorJson        = json_encode($error);
$emailJson        = json_encode($_POST['email'] ?? '');
$loginSuccessJson = json_encode($loginSuccess);
$redirectJson     = json_encode($redirectTo);
$fullNameJson     = json_encode($_SESSION['pending_officer_name'] ?? $_SESSION['full_name'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Login – Evergreen Trust and Savings</title>
  <link rel="icon" type="image/png" href="pictures/logo.png" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet" />
  <style>
    :root {
      --eg-forest: #0a3b2f; --eg-deep: #062620; --eg-mid: #1a6b55;
      --eg-light: #e8f4ef;  --eg-cream: #f7f3ee; --eg-gold: #c9a84c;
      --eg-text:  #1c2b25;  --eg-muted: #6b8c7e; --eg-border: #d4e6de;
      --eg-error: #c0392b;  --eg-err-bg: #fdf0ef;
    }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    html { font-size: 16px; }

    body {
      font-family: 'DM Sans', sans-serif;
      background: var(--eg-cream);
      min-height: 100vh;
      min-height: 100dvh;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow-x: hidden;
      /* FIXED: always allow scrolling so card is never clipped on short viewports */
      overflow-y: auto;
      padding: 24px 0;
    }

    /* ── Background decorations ── */
    .bg-mesh {
      position: fixed; inset: 0; z-index: 0;
      background: radial-gradient(ellipse 80% 60% at 80% 10%, rgba(10,59,47,0.18) 0%, transparent 70%),
                  radial-gradient(ellipse 50% 50% at 10% 90%, rgba(26,107,85,0.12) 0%, transparent 70%),
                  var(--eg-cream);
    }
    .bg-circle {
      position: fixed; border-radius: 50%; opacity: 0.06; background: var(--eg-forest);
      animation: drift 18s ease-in-out infinite alternate;
    }
    .bg-circle:nth-child(1) { width:500px;height:500px;top:-120px;right:-100px;animation-delay:0s; }
    .bg-circle:nth-child(2) { width:300px;height:300px;bottom:-80px;left:-60px;animation-delay:-6s; }
    .bg-circle:nth-child(3) { width:200px;height:200px;top:55%;left:60%;animation-delay:-12s;opacity:0.04; }
    @keyframes drift { from{transform:translate(0,0) scale(1);} to{transform:translate(20px,30px) scale(1.05);} }

    /* ── Card ── */
    .login-card {
      position: relative; z-index: 1;
      width: 100%; max-width: 920px;
      margin: 0 24px;
      border-radius: 20px;
      overflow: hidden;
      box-shadow: 0 20px 60px rgba(10,59,47,0.18), 0 4px 20px rgba(10,59,47,0.10);
      display: flex;
      min-height: 560px;
      animation: cardReveal 0.7s cubic-bezier(0.22,1,0.36,1) both;
    }
    @keyframes cardReveal {
      from{opacity:0;transform:translateY(28px) scale(0.97);}
      to{opacity:1;transform:translateY(0) scale(1);}
    }

    /* ── Brand panel ── */
    .brand-panel {
      width: 45%;
      background: linear-gradient(155deg,var(--eg-deep) 0%,var(--eg-forest) 45%,var(--eg-mid) 100%);
      padding: 52px 44px;
      display: flex; flex-direction: column; justify-content: space-between;
      position: relative; overflow: hidden;
      flex-shrink: 0;
    }
    .brand-panel::before {
      content:''; position:absolute; width:320px; height:320px; border-radius:50%;
      border:60px solid rgba(255,255,255,0.04); top:-80px; right:-80px;
    }
    .brand-panel::after {
      content:''; position:absolute; width:200px; height:200px; border-radius:50%;
      border:40px solid rgba(255,255,255,0.04); bottom:-40px; left:-40px;
    }
    .brand-logo { display:flex; align-items:center; gap:12px; z-index:1; }
    .brand-logo img { height:38px; width:auto; }
    .brand-logo-text {
      font-family:'Playfair Display',serif; color:white;
      font-size:20px; font-weight:700; letter-spacing:1px;
    }
    .brand-body { z-index:1; }
    .brand-tagline {
      font-family:'Playfair Display',serif; color:rgba(255,255,255,0.95);
      font-size:30px; line-height:1.35; font-weight:600; margin-bottom:16px;
    }
    .brand-tagline span { color:var(--eg-gold); }
    .brand-desc { color:rgba(255,255,255,0.60); font-size:14px; line-height:1.7; font-weight:300; }
    .brand-footer { z-index:1; border-top:1px solid rgba(255,255,255,0.10); padding-top:20px; }
    .brand-features { list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:10px; }
    .brand-features li { display:flex; align-items:center; gap:10px; color:rgba(255,255,255,0.70); font-size:13px; font-weight:400; }
    .brand-features li i { color:var(--eg-gold); font-size:14px; flex-shrink:0; }

    /* ── Form panel ── */
    .form-panel {
      width: 55%;
      background: white;
      padding: 52px 48px;
      display: flex; flex-direction: column; justify-content: center;
    }
    .form-heading { margin-bottom:32px; }
    .form-heading p.overline {
      font-size:11px; font-weight:600; letter-spacing:2.5px;
      text-transform:uppercase; color:var(--eg-mid); margin-bottom:8px;
    }
    .form-heading h2 {
      font-family:'Playfair Display',serif; font-size:28px;
      font-weight:700; color:var(--eg-text); margin:0;
    }

    /* ── Alert ── */
    .alert-eg {
      border-radius:10px; font-size:13.5px; padding:12px 16px;
      margin-bottom:24px; display:flex; align-items:flex-start; gap:10px;
      animation:fadeIn 0.3s ease both;
    }
    .alert-error { background:var(--eg-err-bg); border:1px solid #f5c6c3; color:var(--eg-error); }
    @keyframes fadeIn { from{opacity:0;transform:translateY(-6px);} to{opacity:1;transform:translateY(0);} }

    /* ── Fields ── */
    .field-group { margin-bottom:22px; }
    .field-group label {
      display:block; font-size:13px; font-weight:600;
      color:var(--eg-text); margin-bottom:8px; letter-spacing:0.2px;
    }
    .input-wrap { position:relative; display:flex; align-items:center; }
    .input-icon {
      position:absolute; left:14px; color:var(--eg-muted);
      font-size:16px; pointer-events:none; transition:color 0.2s;
    }
    .field-group input {
      width:100%; padding:13px 42px;
      border:1.5px solid var(--eg-border); border-radius:10px;
      font-family:'DM Sans',sans-serif; font-size:14.5px; color:var(--eg-text);
      background:var(--eg-cream); transition:border-color 0.2s,background 0.2s,box-shadow 0.2s;
      outline:none;
    }
    .field-group input:focus {
      border-color:var(--eg-forest); background:white;
      box-shadow:0 0 0 3px rgba(10,59,47,0.08);
    }
    .field-group input.is-error { border-color:var(--eg-error); }
    .field-group .input-wrap:focus-within .input-icon { color:var(--eg-forest); }
    .toggle-eye {
      position:absolute; right:13px; background:none; border:none;
      cursor:pointer; color:var(--eg-muted); font-size:17px;
      padding:2px; line-height:1; transition:color 0.2s;
    }
    .toggle-eye:hover { color:var(--eg-forest); }
    .forgot-link { font-size:12.5px; color:var(--eg-mid); text-decoration:none; font-weight:600; }
    .forgot-link:hover { color:var(--eg-forest); text-decoration:underline; }

    /* ── Sign-in button ── */
    .btn-signin {
      width:100%; padding:14px;
      background:linear-gradient(135deg,var(--eg-forest) 0%,var(--eg-mid) 100%);
      color:white; border:none; border-radius:10px;
      font-family:'DM Sans',sans-serif; font-size:15px; font-weight:600;
      letter-spacing:0.4px; cursor:pointer; transition:all 0.25s;
      position:relative; overflow:hidden; margin-top:4px;
      display:flex; align-items:center; justify-content:center; gap:8px;
    }
    .btn-signin:hover { transform:translateY(-1px); box-shadow:0 8px 20px rgba(10,59,47,0.30); }
    .btn-signin:active { transform:translateY(0); }
    .btn-signin.loading .btn-text { opacity:0; }
    .btn-signin .spinner {
      display:none; width:18px; height:18px;
      border:2px solid rgba(255,255,255,0.4); border-top-color:white;
      border-radius:50%; animation:spin 0.6s linear infinite; position:absolute;
    }
    .btn-signin.loading .spinner { display:block; }
    @keyframes spin { to{transform:rotate(360deg);} }

    /* ── Register row ── */
    .register-row { text-align:center; margin-top:28px; font-size:13.5px; color:var(--eg-muted); }
    .register-row a { color:var(--eg-forest); font-weight:600; text-decoration:none; }
    .register-row a:hover { text-decoration:underline; }

    /* ── Modals ── */
    .eg-modal-overlay {
      position:fixed; inset:0; z-index:9999;
      background:rgba(6,38,32,0.55); backdrop-filter:blur(4px);
      display:flex; align-items:center; justify-content:center;
      opacity:0; visibility:hidden; transition:opacity 0.25s ease,visibility 0.25s ease;
      padding: 16px;
    }
    .eg-modal-overlay.active { opacity:1; visibility:visible; }
    .eg-modal {
      background:white; border-radius:18px; padding:40px 36px;
      max-width:400px; width:100%;
      text-align:center;
      box-shadow:0 24px 64px rgba(6,38,32,0.22), 0 4px 16px rgba(6,38,32,0.10);
      transform:translateY(20px) scale(0.96);
      transition:transform 0.3s cubic-bezier(0.22,1,0.36,1);
    }
    .eg-modal-overlay.active .eg-modal { transform:translateY(0) scale(1); }
    .eg-modal-icon {
      width:68px; height:68px; border-radius:50%;
      display:flex; align-items:center; justify-content:center;
      margin:0 auto 20px; font-size:30px;
    }
    .eg-modal-icon.success { background:#e8f4ef; color:var(--eg-forest); }
    .eg-modal-icon.error   { background:var(--eg-err-bg); color:var(--eg-error); }
    .eg-modal h3 { font-family:'Playfair Display',serif; font-size:22px; font-weight:700; color:var(--eg-text); margin-bottom:10px; }
    .eg-modal p  { font-size:14px; color:var(--eg-muted); line-height:1.65; margin-bottom:28px; }
    .eg-modal-btn {
      display:inline-flex; align-items:center; justify-content:center; gap:8px;
      padding:12px 28px; border-radius:10px; border:none; cursor:pointer;
      font-family:'DM Sans',sans-serif; font-size:14.5px; font-weight:600;
      transition:all 0.22s; width:100%;
    }
    .eg-modal-btn.success { background:linear-gradient(135deg,var(--eg-forest) 0%,var(--eg-mid) 100%); color:white; }
    .eg-modal-btn.success:hover { box-shadow:0 6px 18px rgba(10,59,47,0.28); transform:translateY(-1px); }
    .eg-modal-btn.error   { background:var(--eg-err-bg); color:var(--eg-error); border:1.5px solid #f5c6c3; }
    .eg-modal-btn.error:hover { background:#fbe8e7; transform:translateY(-1px); }
    .eg-modal-progress {
      height:3px; border-radius:2px;
      background:linear-gradient(90deg,var(--eg-forest),var(--eg-mid));
      margin-top:20px; width:0%; transition:width linear;
    }

    /* ── Shake animation ── */
    @keyframes shake {
      0%,100%{transform:translateX(0)} 20%{transform:translateX(-8px)}
      40%{transform:translateX(8px)} 60%{transform:translateX(-5px)} 80%{transform:translateX(5px)}
    }
    .shake { animation:shake 0.45s ease both; }

    /* ════════════════════════════════════════
       RESPONSIVE BREAKPOINTS
       ════════════════════════════════════════ */

    /* Tablet landscape / small desktop: 701px – 900px */
    @media (max-width: 900px) {
      .login-card { max-width: 720px; min-height: auto; }
      .brand-panel { width: 42%; padding: 40px 32px; }
      .brand-tagline { font-size: 26px; }
      .form-panel { width: 58%; padding: 40px 36px; }
    }

    /* Tablet portrait: 601px – 700px */
    @media (max-width: 700px) {
      body {
        align-items: center;
        padding: 20px 0;
      }
      .login-card {
        flex-direction: column;
        max-width: 480px;
        margin: 0 16px;
        border-radius: 16px;
        min-height: auto;
      }
      .brand-panel {
        width: 100%;
        padding: 32px 28px 28px;
        min-height: auto;
        flex-direction: row;
        align-items: center;
        justify-content: flex-start;
        gap: 24px;
      }
      .brand-body { flex: 1; }
      .brand-tagline { font-size: 22px; margin-bottom: 8px; }
      .brand-desc { font-size: 13px; }
      .brand-footer { display: none; }
      .form-panel { width: 100%; padding: 32px 28px; }
      .form-heading h2 { font-size: 24px; }
    }

    /* Mobile large: 481px – 600px */
    @media (max-width: 600px) {
      body {
        align-items: center;
        padding: 16px 0;
      }
      .login-card {
        flex-direction: column;
        max-width: 100%;
        margin: 0 12px;
        border-radius: 14px;
        min-height: auto;
      }
      .brand-panel {
        width: 100%;
        padding: 28px 24px 24px;
        flex-direction: row;
        align-items: center;
        gap: 16px;
      }
      .brand-logo img { height: 32px; }
      .brand-logo-text { font-size: 17px; }
      .brand-tagline { font-size: 20px; margin-bottom: 6px; }
      .brand-desc { display: none; }
      .brand-footer { display: none; }
      .form-panel { width: 100%; padding: 28px 24px 32px; }
      .form-heading { margin-bottom: 24px; }
      .form-heading h2 { font-size: 22px; }
      .field-group { margin-bottom: 18px; }
      .field-group input { padding: 12px 42px; font-size: 14px; }
      .register-row { margin-top: 20px; font-size: 13px; }
    }

    /* Mobile small: up to 480px */
    @media (max-width: 480px) {
      body {
        align-items: center;
        padding: 16px 0;
        /* Use min-height so body can grow taller than viewport on short screens */
        min-height: 100vh;
        min-height: 100dvh;
      }
      .login-card {
        margin: 0 10px;
        border-radius: 12px;
        /* Remove full-bleed stretch — keep card naturally sized */
        min-height: auto;
        width: calc(100% - 20px);
      }
      .brand-panel { padding: 20px 20px 18px; }
      .brand-tagline { font-size: 18px; }
      .form-panel { padding: 24px 20px 28px; }
      .form-heading h2 { font-size: 20px; }
      .btn-signin { font-size: 14px; padding: 13px; }
      .eg-modal { padding: 28px 20px; border-radius: 14px; }
      .eg-modal h3 { font-size: 19px; }
    }

    /* Very small screens: up to 360px */
    @media (max-width: 360px) {
      body { padding: 12px 0; }
      .login-card {
        margin: 0 8px;
        width: calc(100% - 16px);
        border-radius: 10px;
      }
      .brand-panel { padding: 16px 16px 14px; gap: 12px; }
      .brand-logo-text { font-size: 15px; }
      .brand-logo img { height: 28px; }
      .brand-tagline { font-size: 16px; }
      .form-panel { padding: 20px 16px 24px; }
      .field-group input { font-size: 13.5px; }
    }

    /* Landscape orientation on mobile — keep card visible without full-bleed */
    @media (max-height: 500px) and (max-width: 900px) {
      body {
        align-items: flex-start;
        padding: 12px 0 20px;
      }
      .login-card {
        flex-direction: row;
        margin: 0 12px;
        min-height: auto;
        width: calc(100% - 24px);
        border-radius: 12px;
      }
      .brand-panel {
        width: 38%; padding: 24px 20px;
        flex-direction: column; justify-content: center;
      }
      .brand-tagline { font-size: 18px; margin-bottom: 8px; }
      .brand-desc { display: none; }
      .brand-footer { display: none; }
      .form-panel { width: 62%; padding: 24px 28px; justify-content: flex-start; }
      .form-heading { margin-bottom: 16px; }
      .field-group { margin-bottom: 14px; }
      .register-row { margin-top: 14px; }
    }
  </style>
</head>
<body>

<div class="bg-mesh"></div>
<div class="bg-circle"></div>
<div class="bg-circle"></div>
<div class="bg-circle"></div>

<!-- SUCCESS MODAL -->
<div class="eg-modal-overlay" id="successModal">
  <div class="eg-modal">
    <div class="eg-modal-icon success"><i class="bi bi-check-lg"></i></div>
    <h3>Welcome back!</h3>
    <p id="successModalMsg">You have successfully signed in. Redirecting you now…</p>
    <button class="eg-modal-btn success" id="successBtn">
      <i class="bi bi-box-arrow-in-right"></i> Continue
    </button>
    <div class="eg-modal-progress" id="successProgress"></div>
  </div>
</div>

<!-- ERROR MODAL -->
<div class="eg-modal-overlay" id="errorModal">
  <div class="eg-modal">
    <div class="eg-modal-icon error"><i class="bi bi-exclamation-circle"></i></div>
    <h3>Sign-in failed</h3>
    <p id="errorModalMsg">The credentials you entered are incorrect. Please try again.</p>
    <button class="eg-modal-btn error" onclick="closeModal('errorModal')">
      <i class="bi bi-arrow-left"></i> Try again
    </button>
  </div>
</div>

<div id="root"></div>

<script src="https://unpkg.com/react@18/umd/react.production.min.js"></script>
<script src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js"></script>
<script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>

<script type="text/babel">
  const { useState, useEffect, useRef } = React;
  const PHP_ERROR    = <?= $errorJson ?>;
  const PHP_LOGIN_OK = <?= $loginSuccessJson ?>;
  const PHP_REDIRECT = <?= $redirectJson ?>;
  const PHP_NAME     = <?= $fullNameJson ?>;

  function LoginApp() {
    const [email,    setEmail]    = useState(<?= $emailJson ?>);
    const [password, setPassword] = useState('');
    const [showPass, setShowPass] = useState(false);
    const [error,    setError]    = useState(PHP_ERROR || '');
    const [loading,  setLoading]  = useState(false);
    const formRef = useRef(null);

    useEffect(() => {
      if (error && formRef.current) {
        formRef.current.classList.add('shake');
        const t = setTimeout(() => formRef.current?.classList.remove('shake'), 500);
        return () => clearTimeout(t);
      }
    }, [error]);

    function handleSubmit(e) {
      if (!email.trim() || !password) {
        e.preventDefault();
        setError("Please fill in all fields.");
        return;
      }
      setLoading(true);
    }

    return (
      <div className="login-card" ref={formRef}>
        <div className="brand-panel">
          <div className="brand-logo">
            <img src="pictures/logo.png" alt="Evergreen Logo" />
            <span className="brand-logo-text">EVERGREEN</span>
          </div>
          <div className="brand-body">
            <h1 className="brand-tagline">Secure.<br/>Invest.<br/><span>Achieve.</span></h1>
            <p className="brand-desc">Your trusted partner for personal and business financial growth.</p>
          </div>
          <div className="brand-footer">
            <ul className="brand-features">
              <li><i className="bi bi-shield-check"></i> Bank-grade security</li>
              <li><i className="bi bi-clock-history"></i> 24/7 account access</li>
              <li><i className="bi bi-graph-up-arrow"></i> Real-time loan tracking</li>
              <li><i className="bi bi-headset"></i> Dedicated support</li>
            </ul>
          </div>
        </div>
        <div className="form-panel">
          <div className="form-heading">
            <p className="overline">Member &amp; Staff Portal</p>
            <h2>Welcome back</h2>
          </div>
          {error && (
            <div className="alert-eg alert-error">
              <i className="bi bi-exclamation-circle-fill" style={{marginTop:'1px'}}></i>
              <span>{error}</span>
            </div>
          )}
          <form method="POST" action="" onSubmit={handleSubmit}>
            <div className="field-group">
              <label htmlFor="email">Email Address</label>
              <div className="input-wrap">
                <i className="bi bi-envelope input-icon"></i>
                <input type="email" id="email" name="email" required autoComplete="email"
                  placeholder="you@example.com" value={email}
                  onChange={e => { setEmail(e.target.value); setError(''); }}
                  className={error ? 'is-error' : ''} />
              </div>
            </div>
            <div className="field-group">
              <label htmlFor="password">Password</label>
              <div className="input-wrap">
                <i className="bi bi-lock input-icon"></i>
                <input type={showPass ? 'text' : 'password'} id="password" name="password" required
                  autoComplete="current-password" placeholder="Enter your password" value={password}
                  onChange={e => { setPassword(e.target.value); setError(''); }}
                  className={error ? 'is-error' : ''} />
                <button type="button" className="toggle-eye" tabIndex={-1}
                  onClick={() => setShowPass(v => !v)}>
                  <i className={`bi ${showPass ? 'bi-eye-slash' : 'bi-eye'}`}></i>
                </button>
              </div>
              <div className="d-flex justify-content-end mt-2">
                <a href="forgotpass.php" className="forgot-link">Forgot password?</a>
              </div>
            </div>
            <button type="submit" className={`btn-signin${loading ? ' loading' : ''}`} disabled={loading}>
              <span className="spinner"></span>
              <span className="btn-text">
                {loading ? 'Signing in…' : (<><i className="bi bi-box-arrow-in-right"></i> Sign In</>)}
              </span>
            </button>
          </form>
          <div className="register-row">
            Don't have an account? <a href="register-account.php">Create one here</a>
          </div>
        </div>
      </div>
    );
  }
  ReactDOM.createRoot(document.getElementById('root')).render(<LoginApp />);
</script>

<script>
  function closeModal(id) { document.getElementById(id).classList.remove('active'); }

  (function () {
    const loginOk  = <?= $loginSuccessJson ?>;
    const hasError = <?= $errorJson ?> !== '';
    const name     = <?= $fullNameJson ?>;
    const redirect = <?= $redirectJson ?>;

    if (loginOk) {
      const isEmployee = redirect && redirect !== 'index.php';
      let msg = isEmployee
        ? `Welcome back, ${name}! Please complete the second verification step.`
        : `Welcome back, ${name}! Redirecting you to your dashboard…`;
      document.getElementById('successModalMsg').textContent = msg;
      const btn = document.getElementById('successBtn');
      btn.innerHTML = isEmployee
        ? '<i class="bi bi-shield-lock"></i> Continue to Verification'
        : '<i class="bi bi-box-arrow-in-right"></i> Go to Dashboard';
      btn.onclick = () => window.location.href = redirect || 'index.php';
      document.getElementById('successModal').classList.add('active');
      const bar = document.getElementById('successProgress');
      const duration = 2500;
      bar.style.transition = 'width ' + duration + 'ms linear';
      requestAnimationFrame(() => { bar.style.width = '100%'; });
      setTimeout(() => { window.location.href = redirect || 'index.php'; }, duration);
    } else if (hasError) {
      document.getElementById('errorModalMsg').textContent = <?= $errorJson ?>;
      document.getElementById('errorModal').classList.add('active');
    }

    document.querySelectorAll('.eg-modal-overlay').forEach(function(overlay) {
      overlay.addEventListener('click', function(e) {
        if (e.target === overlay && overlay.id !== 'successModal')
          overlay.classList.remove('active');
      });
    });
  })();
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>