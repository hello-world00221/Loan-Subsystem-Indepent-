<?php
session_start();

// ── Auth guard ───────────────────────────────────────────────────────────────
// If already fully authenticated, redirect away from this page.
if (isset($_SESSION['admin_id'])) {
    header('Location: Employeedashboard.php');
    exit;
}
if (isset($_SESSION['officer_id'])) {
    header('Location: ../Loan/adminindex.php');
    exit;
}

// Must have passed first factor on login.php
if (empty($_SESSION['pending_second_factor']) || empty($_SESSION['pending_officer_id'])) {
    header('Location: ../Loan/login.php');
    exit;
}

$error        = '';
$loginSuccess = false;
$redirectTo   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputEmpNum = trim($_POST['employee_id'] ?? '');
    $inputPass   = $_POST['password']         ?? '';

    if (empty($inputEmpNum) || empty($inputPass)) {
        $error = "Please fill in all fields.";
    } else {
        $host = 'localhost'; $dbname = 'loandb'; $dbuser = 'root'; $dbpass = '';
        try {
            $pdo = new PDO(
                "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
                $dbuser, $dbpass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            $stmt = $pdo->prepare(
                "SELECT id, full_name, officer_email, password_hash,
                        employee_number, role, status, contact_number, created_at
                 FROM officers WHERE id = ? LIMIT 1"
            );
            $stmt->execute([$_SESSION['pending_officer_id']]);
            $officer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$officer) {
                $error = "Officer account not found. Please start over.";
            } elseif ($officer['employee_number'] !== $inputEmpNum) {
                $error = "Incorrect Employee ID. Please try again.";
            } elseif (!password_verify($inputPass, $officer['password_hash'])) {
                $error = "Incorrect password. Please try again.";
            } elseif ($officer['status'] !== 'Active') {
                $error = "Your account is inactive. Contact your administrator.";
            } else {
                // ── Clear all pending session flags ───────────────────────
                unset(
                    $_SESSION['pending_second_factor'],
                    $_SESSION['pending_officer_id'],
                    $_SESSION['pending_officer_name'],
                    $_SESSION['pending_officer_email'],
                    $_SESSION['pending_officer_empnum'],
                    $_SESSION['pending_officer_role']
                );

                $role = strtolower(trim($officer['role']));

                if ($role === 'superadmin' || $role === 'super admin') {
                    // ── SuperAdmin session ────────────────────────────────
                    // Keys must match exactly what Employeedashboard.php reads
                    $_SESSION['admin_id']              = $officer['id'];
                    $_SESSION['admin_name']            = $officer['full_name'];
                    $_SESSION['admin_email']           = $officer['officer_email'];
                    $_SESSION['admin_employee_number'] = $officer['employee_number'];
                    $_SESSION['admin_role']            = $officer['role'];
                    $_SESSION['loan_officer_id']       = $officer['employee_number'];

                    header('Location: Employeedashboard.php');
                    exit;

                } else {
                    // ── Loan Officer session ──────────────────────────────
                    // FIX: set ALL session keys that adminindex.php / admin_header.php
                    // may check, so the auth guard there passes correctly.
                    $_SESSION['officer_id']              = $officer['id'];
                    $_SESSION['officer_name']            = $officer['full_name'];
                    $_SESSION['officer_email']           = $officer['officer_email'];
                    $_SESSION['officer_employee_number'] = $officer['employee_number'];
                    $_SESSION['officer_role']            = $officer['role'];
                    // loan_officer_id is the key used inside adminindex.php
                    // for display in the loans table — must be set to employee_number
                    $_SESSION['loan_officer_id']         = $officer['employee_number'];

                    $_SESSION['role'] === 'loan_officer';
                    // FIX: use an absolute path relative to   the document root
                    // to avoid path resolution issues from the Employee/ folder.
                    // Adjust this path to match your actual folder structure.
                    header('Location: ../Employee/adminindex.php');
                    exit;
                }
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

$errorJson       = json_encode($error);
$officerNameJson = json_encode($_SESSION['pending_officer_name'] ?? '');
$empNumJson      = json_encode($_SESSION['pending_officer_empnum'] ?? '');
$roleJson        = json_encode($_SESSION['pending_officer_role'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Staff Verification – Evergreen Trust and Savings</title>
  <link rel="icon" type="image/png" href="pictures/logo.png"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    :root {
      --eg-forest:#0a3b2f;--eg-deep:#062620;--eg-mid:#1a6b55;--eg-cream:#f7f3ee;
      --eg-gold:#c9a84c;--eg-text:#1c2b25;--eg-muted:#6b8c7e;--eg-border:#d4e6de;
      --eg-error:#c0392b;--eg-err-bg:#fdf0ef;
    }
    *{box-sizing:border-box;margin:0;padding:0;}

    body {
      font-family:'DM Sans',sans-serif;
      background:var(--eg-cream);
      /* FIXED: removed overflow:hidden — allow scrolling on tall content */
      min-height:100vh;
      min-height:100dvh;
      display:flex;
      align-items:center;
      justify-content:center;
      overflow-x:hidden;
      overflow-y:auto;
      /* FIXED: padding so card never kisses screen edges vertically */
      padding:24px 0;
    }

    .bg-mesh{position:fixed;inset:0;z-index:0;background:radial-gradient(ellipse 80% 60% at 20% 10%,rgba(10,59,47,0.18) 0%,transparent 70%),radial-gradient(ellipse 50% 50% at 90% 90%,rgba(26,107,85,0.12) 0%,transparent 70%),var(--eg-cream);}
    .bg-circle{position:fixed;border-radius:50%;opacity:0.06;background:var(--eg-forest);animation:drift 18s ease-in-out infinite alternate;}
    .bg-circle:nth-child(1){width:500px;height:500px;top:-120px;left:-100px;animation-delay:0s;}
    .bg-circle:nth-child(2){width:300px;height:300px;bottom:-80px;right:-60px;animation-delay:-6s;}
    @keyframes drift{from{transform:translate(0,0) scale(1);}to{transform:translate(20px,30px) scale(1.05);}}

    .verify-card{
      position:relative;z-index:1;
      width:100%;max-width:920px;
      /* FIXED: margin keeps card off screen edges on all sides */
      margin:0 24px;
      border-radius:20px;
      overflow:hidden;
      box-shadow:0 20px 60px rgba(10,59,47,0.18);
      display:flex;
      min-height:560px;
      animation:cardReveal 0.7s cubic-bezier(0.22,1,0.36,1) both;
    }
    @keyframes cardReveal{from{opacity:0;transform:translateY(28px) scale(0.97);}to{opacity:1;transform:translateY(0) scale(1);}}

    .brand-panel{width:42%;background:linear-gradient(155deg,var(--eg-deep) 0%,var(--eg-forest) 45%,var(--eg-mid) 100%);
      padding:52px 40px;display:flex;flex-direction:column;justify-content:space-between;position:relative;overflow:hidden;}
    .brand-panel::before{content:'';position:absolute;width:320px;height:320px;border-radius:50%;
      border:60px solid rgba(255,255,255,0.04);top:-80px;right:-80px;}
    .brand-panel::after{content:'';position:absolute;width:200px;height:200px;border-radius:50%;
      border:40px solid rgba(255,255,255,0.04);bottom:-40px;left:-40px;}
    .brand-logo{display:flex;align-items:center;gap:12px;z-index:1;}
    .brand-logo img{height:38px;width:auto;}
    .brand-logo-text{font-family:'Playfair Display',serif;color:white;font-size:20px;font-weight:700;letter-spacing:1px;}

    .officer-id-card{background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.14);
      border-radius:16px;padding:22px;z-index:1;margin-top:auto;}
    .officer-card-label{font-size:10px;text-transform:uppercase;letter-spacing:2px;color:rgba(255,255,255,0.45);margin-bottom:14px;}
    .officer-avatar{width:56px;height:56px;background:rgba(255,255,255,0.12);border-radius:50%;
      display:flex;align-items:center;justify-content:center;border:2px solid rgba(255,255,255,0.18);margin-bottom:12px;}
    .officer-avatar i{font-size:24px;color:rgba(255,255,255,0.8);}
    .officer-name{color:#fff;font-size:16px;font-weight:600;margin-bottom:4px;}
    .officer-role{display:inline-block;background:rgba(201,168,76,0.2);border:1px solid rgba(201,168,76,0.4);
      color:var(--eg-gold);font-size:10px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;
      border-radius:99px;padding:3px 10px;margin-bottom:14px;}
    .officer-empnum{background:rgba(0,0,0,0.25);border-radius:8px;padding:10px 14px;
      display:flex;align-items:center;justify-content:space-between;}
    .officer-empnum-label{font-size:9px;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,0.4);}
    .officer-empnum-value{font-family:'Courier New',monospace;font-size:15px;font-weight:700;color:#fff;letter-spacing:2px;}
    .strip{height:3px;border-radius:2px;margin-top:14px;background:linear-gradient(90deg,var(--eg-gold),#e8c96b);}

    .security-steps{z-index:1;margin-top:28px;}
    .security-steps-title{font-size:11px;font-weight:600;letter-spacing:1.5px;text-transform:uppercase;
      color:rgba(255,255,255,0.4);margin-bottom:12px;}
    .step-row{display:flex;align-items:center;gap:10px;margin-bottom:10px;}
    .step-num{width:22px;height:22px;border-radius:50%;background:rgba(255,255,255,0.1);
      border:1px solid rgba(255,255,255,0.2);display:flex;align-items:center;justify-content:center;
      font-size:11px;font-weight:700;color:var(--eg-gold);flex-shrink:0;}
    .step-num.done{background:rgba(67,160,71,0.25);border-color:rgba(67,160,71,0.5);color:#a5d6a7;}
    .step-text{font-size:12px;color:rgba(255,255,255,0.5);line-height:1.4;}
    .step-text.done{color:rgba(165,214,167,0.8);text-decoration:line-through;}

    .form-panel{width:58%;background:white;padding:52px 48px;display:flex;flex-direction:column;justify-content:center;}
    .shield-badge{width:52px;height:52px;background:linear-gradient(135deg,var(--eg-forest),var(--eg-mid));
      border-radius:14px;display:flex;align-items:center;justify-content:center;margin-bottom:20px;
      box-shadow:0 4px 16px rgba(10,59,47,0.25);}
    .shield-badge i{font-size:24px;color:white;}
    .form-heading p.overline{font-size:11px;font-weight:600;letter-spacing:2.5px;text-transform:uppercase;color:var(--eg-mid);margin-bottom:8px;}
    .form-heading h2{font-family:'Playfair Display',serif;font-size:28px;font-weight:700;color:var(--eg-text);margin-bottom:6px;}
    .form-heading p.subtitle{font-size:13.5px;color:var(--eg-muted);line-height:1.6;margin-bottom:28px;}
    .alert-eg{border-radius:10px;font-size:13.5px;padding:12px 16px;margin-bottom:24px;
      display:flex;align-items:flex-start;gap:10px;animation:fadeIn 0.3s ease both;}
    .alert-error{background:var(--eg-err-bg);border:1px solid #f5c6c3;color:var(--eg-error);}
    @keyframes fadeIn{from{opacity:0;transform:translateY(-6px);}to{opacity:1;transform:translateY(0);}}
    .field-group{margin-bottom:22px;}
    .field-group label{display:block;font-size:13px;font-weight:600;color:var(--eg-text);margin-bottom:8px;letter-spacing:0.2px;}
    .input-wrap{position:relative;display:flex;align-items:center;}
    .input-icon{position:absolute;left:14px;color:var(--eg-muted);font-size:16px;pointer-events:none;transition:color 0.2s;}
    .field-group input{width:100%;padding:13px 42px;border:1.5px solid var(--eg-border);border-radius:10px;
      font-family:'DM Sans',sans-serif;font-size:14.5px;color:var(--eg-text);background:#f7f3ee;
      transition:border-color 0.2s,background 0.2s,box-shadow 0.2s;outline:none;}
    .field-group input:focus{border-color:var(--eg-forest);background:white;box-shadow:0 0 0 3px rgba(10,59,47,0.08);}
    .field-group input.is-error{border-color:var(--eg-error);}
    .field-group .input-wrap:focus-within .input-icon{color:var(--eg-forest);}
    .toggle-eye{position:absolute;right:13px;background:none;border:none;cursor:pointer;color:var(--eg-muted);
      font-size:17px;padding:2px;line-height:1;transition:color 0.2s;}
    .toggle-eye:hover{color:var(--eg-forest);}
    .btn-verify{width:100%;padding:14px;background:linear-gradient(135deg,var(--eg-forest) 0%,var(--eg-mid) 100%);
      color:white;border:none;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:15px;font-weight:600;
      letter-spacing:0.4px;cursor:pointer;transition:all 0.25s;margin-top:4px;display:flex;align-items:center;justify-content:center;gap:8px;}
    .btn-verify:hover{transform:translateY(-1px);box-shadow:0 8px 20px rgba(10,59,47,0.30);}
    .btn-verify:disabled{background:#b0bec5;cursor:not-allowed;transform:none;}
    .back-link{text-align:center;margin-top:24px;font-size:13px;color:var(--eg-muted);}
    .back-link a{color:var(--eg-forest);font-weight:600;text-decoration:none;}
    .back-link a:hover{text-decoration:underline;}

    @keyframes shake{0%,100%{transform:translateX(0)}20%{transform:translateX(-8px)}40%{transform:translateX(8px)}60%{transform:translateX(-5px)}80%{transform:translateX(5px)}}
    .shake{animation:shake 0.45s ease both;}

    /* ════════════════════════════════════════
       RESPONSIVE BREAKPOINTS
       ════════════════════════════════════════ */

    /* Tablet landscape */
    @media(max-width:900px){
      .verify-card{ max-width:720px; }
      .brand-panel{ width:40%; padding:40px 28px; }
      .form-panel{ width:60%; padding:40px 32px; }
    }

    /* Tablet portrait / stacked layout */
    @media(max-width:700px){
      body{
        align-items:center;
        padding:20px 0;
      }
      .verify-card{
        flex-direction:column;
        max-width:480px;
        margin:0 16px;
        min-height:auto;
        border-radius:16px;
      }
      .brand-panel{
        width:100%;
        padding:28px 24px;
        min-height:auto;
        /* Compact horizontal layout on mobile */
        flex-direction:row;
        align-items:center;
        gap:16px;
        justify-content:flex-start;
      }
      .officer-id-card{ display:none; }
      .security-steps{ display:none; }
      .form-panel{ width:100%; padding:32px 24px; }
      .form-heading h2{ font-size:24px; }
    }

    /* Mobile */
    @media(max-width:600px){
      body{ padding:16px 0; align-items:center; }
      .verify-card{
        margin:0 12px;
        border-radius:14px;
        min-height:auto;
      }
      .brand-panel{ padding:20px 20px 18px; }
      .form-panel{ padding:24px 20px 28px; }
      .form-heading h2{ font-size:22px; }
      .field-group input{ font-size:14px; padding:12px 42px; }
    }

    /* Small mobile */
    @media(max-width:480px){
      body{ padding:12px 0; }
      .verify-card{
        margin:0 10px;
        border-radius:12px;
        width:calc(100% - 20px);
      }
      .brand-panel{ padding:16px 16px 14px; gap:12px; }
      .brand-logo img{ height:30px; }
      .brand-logo-text{ font-size:16px; }
      .form-panel{ padding:20px 16px 24px; }
      .form-heading h2{ font-size:20px; }
      .btn-verify{ font-size:14px; padding:13px; }
    }

    /* Very small */
    @media(max-width:360px){
      .brand-logo img{ height:26px; }
      .brand-logo-text{ font-size:14px; }
      .form-panel{ padding:18px 14px 22px; }
      .field-group input{ font-size:13.5px; }
    }

    /* Landscape mobile */
    @media(max-height:500px) and (max-width:900px){
      body{ align-items:flex-start; padding:12px 0 20px; }
      .verify-card{
        flex-direction:row;
        margin:0 12px;
        min-height:auto;
        width:calc(100% - 24px);
        border-radius:12px;
      }
      .brand-panel{
        width:36%; padding:20px 18px;
        flex-direction:column; justify-content:center;
      }
      .officer-id-card{ display:none; }
      .security-steps{ display:none; }
      .form-panel{ width:64%; padding:20px 24px; justify-content:flex-start; }
      .form-heading{ margin-bottom:12px; }
      .field-group{ margin-bottom:14px; }
    }
  </style>
</head>
<body>
<div class="bg-mesh"></div>
<div class="bg-circle"></div>
<div class="bg-circle"></div>

<div id="root"></div>

<script src="https://unpkg.com/react@18/umd/react.production.min.js"></script>
<script src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js"></script>
<script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>

<script type="text/babel">
  const { useState, useRef, useEffect } = React;
  const PHP_ERROR  = <?= $errorJson ?>;
  const PHP_NAME   = <?= $officerNameJson ?>;
  const PHP_EMPNUM = <?= $empNumJson ?>;
  const PHP_ROLE   = <?= $roleJson ?>;

  function EmployeeLoginApp() {
    const [empId,    setEmpId]    = useState('');
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
      if (!empId.trim() || !password) {
        e.preventDefault();
        setError("Please fill in all fields.");
        return;
      }
      setLoading(true);
    }

    const isSuperAdmin = PHP_ROLE && (PHP_ROLE.toLowerCase().includes('superadmin') || PHP_ROLE.toLowerCase().includes('super admin'));

    return (
      <div className="verify-card" ref={formRef}>
        {/* Brand/Left Panel */}
        <div className="brand-panel">
          <div className="brand-logo">
            <img src="pictures/logo.png" alt="Evergreen Logo"/>
            <span className="brand-logo-text">EVERGREEN</span>
          </div>

          {PHP_NAME && (
            <div className="officer-id-card">
              <div className="officer-card-label">Authenticating</div>
              <div className="officer-avatar"><i className="bi bi-person-fill"></i></div>
              <div className="officer-name">{PHP_NAME}</div>
              <div className="officer-role">{PHP_ROLE || 'Staff'}</div>
              <div className="officer-empnum">
                <div>
                  <div className="officer-empnum-label">Employee No.</div>
                  <div className="officer-empnum-value">{PHP_EMPNUM || '—'}</div>
                </div>
                <i className="bi bi-shield-lock" style={{color:'#c9a84c',fontSize:'18px'}}></i>
              </div>
              <div className="strip"></div>
            </div>
          )}

          <div className="security-steps">
            <div className="security-steps-title">Security Steps</div>
            <div className="step-row">
              <div className="step-num done"><i className="bi bi-check" style={{fontSize:'12px'}}></i></div>
              <div className="step-text done">Email &amp; password verified</div>
            </div>
            <div className="step-row">
              <div className="step-num">2</div>
              <div className="step-text">Enter Employee ID &amp; confirm password</div>
            </div>
          </div>
        </div>

        {/* Form Panel */}
        <div className="form-panel">
          <div className="shield-badge"><i className="bi bi-shield-lock-fill"></i></div>
          <div className="form-heading">
            <p className="overline">Two-Factor Verification</p>
            <h2>Confirm Your Identity</h2>
            <p className="subtitle">
              Enter your Employee ID and re-confirm your password to access the
              {isSuperAdmin ? ' Admin' : ' Staff'} Dashboard.
            </p>
          </div>

          {error && (
            <div className="alert-eg alert-error">
              <i className="bi bi-exclamation-circle-fill" style={{marginTop:'1px'}}></i>
              <span>{error}</span>
            </div>
          )}

          <form method="POST" action="" onSubmit={handleSubmit}>
            <div className="field-group">
              <label htmlFor="employee_id">Employee ID</label>
              <div className="input-wrap">
                <i className="bi bi-person-badge input-icon"></i>
                <input type="text" id="employee_id" name="employee_id" required
                  placeholder="e.g. EMP-00123" value={empId}
                  onChange={e => { setEmpId(e.target.value); setError(''); }}
                  className={error ? 'is-error' : ''} autoFocus/>
              </div>
            </div>
            <div className="field-group">
              <label htmlFor="password">Confirm Password</label>
              <div className="input-wrap">
                <i className="bi bi-lock input-icon"></i>
                <input type={showPass ? 'text' : 'password'} id="password" name="password" required
                  placeholder="Re-enter your password" value={password}
                  onChange={e => { setPassword(e.target.value); setError(''); }}
                  className={error ? 'is-error' : ''}/>
                <button type="button" className="toggle-eye" tabIndex={-1}
                  onClick={() => setShowPass(v => !v)}>
                  <i className={`bi ${showPass ? 'bi-eye-slash' : 'bi-eye'}`}></i>
                </button>
              </div>
            </div>
            <button type="submit" className="btn-verify" disabled={loading}>
              {loading
                ? <><span className="spinner-border spinner-border-sm me-2" role="status"></span>Verifying…</>
                : <><i className="bi bi-shield-check"></i> Verify &amp; Enter Dashboard</>}
            </button>
          </form>

          <div className="back-link">
            <a href="../Loan/login.php"><i className="bi bi-arrow-left me-1"></i>Back to login</a>
          </div>
        </div>
      </div>
    );
  }

  ReactDOM.createRoot(document.getElementById('root')).render(<EmployeeLoginApp />);
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>