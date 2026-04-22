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
                 FROM users WHERE id = ? AND user_email = ? LIMIT 1"
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

// ─── CONNECT TO loandb ────────────────────────────────────────────────────────
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
} catch (PDOException $e) {
    die("Database connection failed. Please contact admin.");
}

// ─── BUILD $currentUser FROM SESSION + DB ────────────────────────────────────
$currentUser = null;

try {
    $stmt = $pdo->prepare(
        "SELECT full_name, user_email, contact_number, account_number
         FROM users WHERE user_email = ? LIMIT 1"
    );
    $stmt->execute([$_SESSION['user_email']]);
    $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

if (!$currentUser) {
    $currentUser = [
        'full_name'      => $_SESSION['user_name'] ?? $_SESSION['full_name'] ?? '',
        'user_email'     => $_SESSION['user_email'],
        'contact_number' => '',
        'account_number' => '',
    ];
}

$currentUser['email'] = $currentUser['user_email'] ?? $currentUser['email'] ?? $_SESSION['user_email'];

// ─── FETCH LOAN TYPES FROM loandb ─────────────────────────────────────────────
$loanTypes = [];
try {
    $lt = $pdo->query("SELECT id, name FROM loan_types WHERE is_active = 1 ORDER BY name");
    $loanTypes = $lt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Could not load loan types. Please contact admin.");
}

// ─── FETCH VALID ID TYPES FROM loandb ────────────────────────────────────────
$validIdTypes = [];
try {
    $vi = $pdo->query("SELECT id, valid_id_type FROM loan_valid_id ORDER BY valid_id_type");
    $validIdTypes = $vi->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Loan Application Form</title>
  <link rel="icon" type="logo/png" href="pictures/logo.png" />

  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <!-- Original stylesheet -->
  <link rel="stylesheet" href="Loan_AppForm.css" />

  <style>
    :root {
      --eg-dark:  #003631;
      --eg-mid:   #005a4d;
      --eg-light: #00796b;
    }

    /* ── Page wrapper ── */
    .page-content { background: #f4f8f6; min-height: 100vh; padding: 2.5rem 0 4rem; }

    /* ── Main card ── */
    .form-card {
      background: #fff;
      border-radius: 1.25rem;
      border: 1px solid #dceee8;
      padding: 2.5rem;
    }
    .form-card h1 { font-size: 1.75rem; font-weight: 800; color: var(--eg-dark); margin-bottom: .25rem; }
    .form-card .subtitle { color: #6c757d; font-size: .95rem; margin-bottom: 2rem; }

    /* ── Section headings ── */
    .section-heading {
      font-size: 1rem;
      font-weight: 700;
      color: var(--eg-dark);
      text-transform: uppercase;
      letter-spacing: .7px;
      padding-bottom: .5rem;
      border-bottom: 2px solid #dceee8;
      margin-bottom: 1.25rem;
    }

    /* ── Form controls ── */
    .form-label { font-size: .875rem; font-weight: 600; color: #444; margin-bottom: .35rem; }
    .form-label .required { color: #dc3545; margin-left: 2px; }

    .form-control, .form-select {
      border: 1.5px solid #cde3da;
      border-radius: .6rem;
      padding: .65rem .9rem;
      font-size: .92rem;
      color: #333;
      transition: border-color .2s, box-shadow .2s;
    }
    .form-control:focus, .form-select:focus {
      border-color: var(--eg-mid);
      box-shadow: 0 0 0 3px rgba(0,90,77,.12);
      outline: none;
    }
    .form-control[readonly] { background: #f3f8f6; color: #555; }
    textarea.form-control { resize: vertical; min-height: 100px; }

    .validation-message { font-size: .78rem; color: #dc3545; display: block; margin-top: .25rem; min-height: .9rem; }

    /* ── File input ── */
    .form-control[type="file"] { padding: .5rem .75rem; }
    .file-hint { font-size: .78rem; color: #888; margin-bottom: .3rem; display: block; }

    /* ── ID format hint (green) ── */
    .id-format-hint {
      font-size: .78rem;
      color: var(--eg-light);
      font-weight: 600;
      display: block;
      margin-top: .3rem;
      min-height: .9rem;
      transition: opacity .2s;
    }

    /* ── Action buttons ── */
    .btn-back {
      background: transparent;
      color: var(--eg-dark);
      border: 2px solid var(--eg-dark);
      padding: .65rem 2rem;
      border-radius: .6rem;
      font-weight: 600;
      transition: all .2s;
    }
    .btn-back:hover { background: var(--eg-dark); color: #fff; }

    .btn-submit {
      background: var(--eg-dark);
      color: #fff;
      border: none;
      padding: .65rem 2.5rem;
      border-radius: .6rem;
      font-weight: 600;
      transition: background .2s, transform .15s;
    }
    .btn-submit:hover { background: var(--eg-mid); transform: translateY(-2px); }

    /* ── Progress sidebar ── */
    .progress-card {
      background: #fff;
      border: 1px solid #dceee8;
      border-radius: 1.25rem;
      padding: 1.75rem 1.5rem;
      position: sticky;
      top: 6rem;
    }
    .progress-card h3 { font-size: .95rem; font-weight: 700; color: var(--eg-dark); text-transform: uppercase; letter-spacing: .7px; margin-bottom: 1.5rem; }

    .progress-step {
      display: flex;
      align-items: center;
      gap: .75rem;
      padding: .75rem 1rem;
      border-radius: .6rem;
      margin-bottom: .5rem;
      font-size: .9rem;
      color: #555;
      transition: background .2s;
    }

    /* Default circle = yellow (section not yet completed) */
    .progress-step .circle {
      width: 28px; height: 28px;
      border-radius: 50%;
      background: #f5c518;
      border: 2px solid #e0a800;
      flex-shrink: 0;
      transition: all .3s ease;
      display: flex; align-items: center; justify-content: center;
      position: relative;
      overflow: hidden;
    }

    /* Active step (currently scrolled to) — green, no shine yet */
    .progress-step.active { background: #f0faf6; color: var(--eg-dark); font-weight: 600; }
    .progress-step.active .circle { background: #28a745; border-color: #1e7e34; }

    /* Completed step — green with ✓ and shine sweep animation */
    .progress-step.completed { color: var(--eg-dark); font-weight: 600; }
    .progress-step.completed .circle {
      background: #28a745;
      border-color: #1e7e34;
      animation: circlePulse 2s ease-in-out infinite;
    }
    .progress-step.completed .circle::before {
      content: '';
      position: absolute;
      top: -50%; left: -75%;
      width: 50%; height: 200%;
      background: rgba(255,255,255,0.55);
      transform: skewX(-20deg);
      animation: shineSwipe 2s ease-in-out infinite;
    }
    .progress-step.completed .circle::after {
      content: '✓';
      color: #fff;
      font-size: .75rem;
      font-weight: 700;
      position: relative;
      z-index: 1;
    }

    @keyframes shineSwipe {
      0%   { left: -75%; opacity: 0; }
      30%  { opacity: 1; }
      60%  { left: 125%; opacity: 0; }
      100% { left: 125%; opacity: 0; }
    }
    @keyframes circlePulse {
      0%, 100% { box-shadow: 0 0 0 0 rgba(40,167,69,0.5); }
      50%       { box-shadow: 0 0 0 5px rgba(40,167,69,0); }
    }

    /* ── Modal backdrop ── */
    .modal-overlay {
      display: none;
      position: fixed; inset: 0;
      background: rgba(0,0,0,.55);
      z-index: 9999;
      align-items: center;
      justify-content: center;
    }
    .modal-overlay.visible { display: flex; }
    .blur-background { filter: blur(4px); pointer-events: none; user-select: none; }

    /* ── Terms modal ── */
    .modal-box {
      background: #fff;
      border-radius: 1.1rem;
      width: 90%;
      max-width: 560px;
      overflow: hidden;
      animation: slideUp .3s ease-out;
      box-shadow: 0 16px 48px rgba(0,0,0,.22);
    }
    .modal-box-header {
      background: linear-gradient(135deg, var(--eg-dark) 0%, var(--eg-mid) 100%);
      color: #fff;
      padding: 1.5rem 1.75rem;
      display: flex;
      align-items: center;
      gap: .9rem;
      border-bottom: 2px solid rgba(255,255,255,0.15);
    }
    .modal-box-header img { width: 42px; height: 42px; border-radius: 50%; background: rgba(255,255,255,.15); padding: 4px; }
    .modal-box-header h2 {
      margin: 0;
      font-size: 1.2rem;
      font-weight: 800;
      color: #ffffff;
      text-shadow: 0 1px 3px rgba(0,0,0,0.4);
      letter-spacing: .3px;
    }
    .modal-box-header p  {
      margin: 0;
      font-size: .82rem;
      color: #d4f0e8;
      opacity: 1;
    }

    .modal-box-body { padding: 1.5rem 1.75rem; }

    /* FIX 2: Terms body — pure white background + dark text for clear readability */
    .terms-body {
      max-height: 280px;
      overflow-y: auto;
      border: 1.5px solid #b2d8cb;
      border-radius: .5rem;
      padding: 1rem 1.25rem;
      background: #ffffff;
      font-size: .88rem;
      color: #222;
      line-height: 1.6;
      margin-bottom: 1.25rem;
    }
    .terms-body h3 { font-size: .92rem; font-weight: 700; color: var(--eg-dark); margin: .9rem 0 .3rem; }
    .terms-body p  { margin: 0 0 .6rem; color: #333; }
    .terms-body::-webkit-scrollbar { width: 6px; }
    .terms-body::-webkit-scrollbar-track { background: #f0f0f0; border-radius: 6px; }
    .terms-body::-webkit-scrollbar-thumb { background: #aacfc4; border-radius: 6px; }

    .acceptance-text { font-size: .82rem; color: #777; margin-bottom: 1rem; }

    .btn-accept {
      background: var(--eg-dark); color: #fff;
      border: none; padding: .65rem 1.75rem;
      border-radius: .55rem; font-weight: 600;
      transition: background .2s;
    }
    .btn-accept:hover { background: var(--eg-mid); }
    .btn-accept:disabled { opacity: .65; }

    .btn-decline {
      background: transparent; color: #666;
      border: 1.5px solid #ccc; padding: .65rem 1.5rem;
      border-radius: .55rem; font-weight: 600;
      transition: all .2s;
    }
    .btn-decline:hover { border-color: #999; color: #333; }

    /* ── Confirmation view ── */
    .confirm-view { padding: 2.5rem 2rem; text-align: center; }
    .confirm-view h2 { color: var(--eg-dark); font-size: 1.35rem; font-weight: 700; margin: 1rem 0 .5rem; }
    .confirm-view p  { color: #666; font-size: .92rem; }
    .reference-box {
      background: #f0faf6;
      border: 1px solid #c4e8da;
      border-radius: .6rem;
      padding: .85rem 1.25rem;
      margin: 1rem auto;
      max-width: 320px;
      font-size: .9rem;
      color: #444;
    }
    .btn-dashboard {
      background: var(--eg-dark); color: #fff;
      border: none; padding: .7rem 2rem;
      border-radius: .6rem; font-weight: 600;
      transition: background .2s; margin-top: .75rem;
      cursor: pointer;
    }
    .btn-dashboard:hover { background: var(--eg-mid); }

    @keyframes fadeIn   { from{opacity:0} to{opacity:1} }
    @keyframes slideUp  { from{transform:translateY(40px);opacity:0} to{transform:translateY(0);opacity:1} }

    /* ── Responsive ── */
    @media (max-width: 767px) {
      .form-card { padding: 1.5rem 1.25rem; }
      .progress-card { position: static; margin-bottom: 1.5rem; }
    }
  </style>
</head>
<body>

<?php include 'header.php'; ?>

<div class="page-content" id="pageContent">
  <div class="container">
    <div class="row g-4">

      <!-- ── PROGRESS SIDEBAR (shows above form on mobile) ── -->
      <div class="col-lg-3 order-lg-2">
        <div class="progress-card" id="progressCard">
          <h3>Application Progress</h3>
          <div class="progress-step active" id="progress-account">
            <span class="circle"></span>
            <span>Account Information</span>
          </div>
          <div class="progress-step" id="progress-loan">
            <span class="circle"></span>
            <span>Loan Details</span>
          </div>
          <div class="progress-step" id="progress-support">
            <span class="circle"></span>
            <span>Supporting Details</span>
          </div>
        </div>
      </div>

      <!-- ── FORM ── -->
      <div class="col-lg-9 order-lg-1">
        <div class="form-card">
          <h1>Loan Application Form</h1>
          <p class="subtitle">Please review your account and loan details below.</p>

          <form id="loanForm" action="submit_loan.php" method="POST" enctype="multipart/form-data" novalidate>

            <!-- SECTION 1: ACCOUNT INFORMATION -->
            <section id="step-account-info" class="mb-4">
              <p class="section-heading">1. Account Information</p>
              <div class="row g-3">

                <div class="col-sm-6">
                  <label class="form-label" for="full_name">Full Name <span class="required">*</span></label>
                  <input type="text" class="form-control" name="full_name" id="full_name"
                         value="<?= htmlspecialchars($currentUser['full_name']) ?>"
                         placeholder="Full Name (e.g., John Doe)" required readonly />
                  <span class="validation-message" id="name-error"></span>
                </div>

                <div class="col-sm-6">
                  <label class="form-label" for="account_number">Account Number <span class="required">*</span></label>
                  <input type="text" class="form-control" name="account_number" id="account_number"
                         value="<?= htmlspecialchars($currentUser['account_number']) ?>"
                         placeholder="Account Number (10 digits)" required readonly />
                  <span class="validation-message" id="account-error"></span>
                </div>

                <div class="col-sm-6">
                  <label class="form-label" for="contact_number">Contact Number <span class="required">*</span></label>
                  <input type="tel" class="form-control" name="contact_number" id="contact_number"
                         value="<?= htmlspecialchars($currentUser['contact_number']) ?>"
                         placeholder="Contact Number (+63...)" required readonly />
                  <span class="validation-message" id="contact-error"></span>
                </div>

                <div class="col-sm-6">
                  <label class="form-label" for="email">Email Address <span class="required">*</span></label>
                  <input type="email" class="form-control" name="email" id="email"
                         value="<?= htmlspecialchars($currentUser['email']) ?>"
                         placeholder="Email Address" required readonly />
                  <span class="validation-message" id="email-error"></span>
                </div>

              </div>
            </section>

            <!-- SECTION 2: LOAN DETAILS -->
            <section id="step-loan-details" class="mb-4">
              <p class="section-heading">2. Loan Details</p>
              <div class="row g-3">

                <div class="col-sm-6">
                  <label class="form-label" for="loan_type">Loan Type <span class="required">*</span></label>
                  <select class="form-select" name="loan_type_id" id="loan_type" required>
                    <option value="">Select Loan Type</option>
                    <?php foreach ($loanTypes as $type): ?>
                      <option value="<?= (int)$type['id'] ?>"><?= htmlspecialchars($type['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <span class="validation-message" id="loan-type-error"></span>
                </div>

                <div class="col-sm-6">
                  <label class="form-label" for="loan_terms">Loan Term <span class="required">*</span></label>
                  <select class="form-select" name="loan_terms" id="loan_terms" required>
                    <option value="">Select Loan Terms</option>
                    <option value="6 Months">6 Months</option>
                    <option value="12 Months">12 Months</option>
                    <option value="18 Months">18 Months</option>
                    <option value="24 Months">24 Months</option>
                    <option value="30 Months">30 Months</option>
                    <option value="36 Months">36 Months</option>
                  </select>
                  <span class="validation-message" id="loan-terms-error"></span>
                </div>

                <div class="col-sm-6">
                  <label class="form-label" for="loan_amount">Loan Amount <span class="required">*</span></label>
                  <input type="number" class="form-control" name="loan_amount" id="loan_amount"
                         placeholder="Loan Amount (Min ₱5,000)" min="5000" step="0.01" required />
                  <span class="validation-message" id="amount-error"></span>
                </div>

                <div class="col-12">
                  <label class="form-label" for="purpose">Purpose of Loan <span class="required">*</span></label>
                  <textarea class="form-control" name="purpose" id="purpose"
                            placeholder="Describe the purpose of your loan" required></textarea>
                  <span class="validation-message" id="purpose-error"></span>
                </div>

              </div>
            </section>

            <!-- SECTION 3: SUPPORTING DETAILS -->
            <section id="step-supporting-details" class="mb-4">
              <p class="section-heading">3. Supporting Details</p>
              <div class="row g-3">

                <div class="col-sm-6">
                  <label class="form-label" for="loan_valid_id_type">Valid ID Type <span class="required">*</span></label>
                  <select class="form-select" name="loan_valid_id_type" id="loan_valid_id_type" required>
                    <option value="">Select Valid ID</option>
                    <?php if (!empty($validIdTypes)): ?>
                      <?php foreach ($validIdTypes as $idType): ?>
                        <option value="<?= (int)$idType['id'] ?>" data-id-name="<?= htmlspecialchars(strtolower($idType['valid_id_type'])) ?>">
                          <?= htmlspecialchars($idType['valid_id_type']) ?>
                        </option>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <option value="" disabled>No ID types available — contact admin</option>
                    <?php endif; ?>
                  </select>
                  <span class="validation-message" id="valid-id-type-error"></span>
                </div>

                <div class="col-sm-6">
                  <label class="form-label" for="valid_id_number">ID Number <span class="required">*</span></label>
                  <input type="text" class="form-control" name="valid_id_number" id="valid_id_number"
                         placeholder="Select a Valid ID type first" maxlength="150" required />
                  <span class="id-format-hint" id="id-format-hint"></span>
                  <span class="validation-message" id="valid-id-number-error"></span>
                </div>

                <div class="col-sm-12 col-md-4">
                  <label class="form-label" for="attachment">Upload Valid ID <span class="required">*</span></label>
                  <span class="file-hint">JPG, JPEG, PNG, PDF, DOC, DOCX (Max 5MB)</span>
                  <input type="file" class="form-control" name="attachment" id="attachment"
                         accept=".jpg,.jpeg,.png,.pdf,.doc,.docx" required />
                  <span class="validation-message" id="attachment-error"></span>
                </div>

                <div class="col-sm-12 col-md-4">
                  <label class="form-label" for="proof_of_income">Proof of Income / Payslip <span class="required">*</span></label>
                  <span class="file-hint">JPG, JPEG, PNG, PDF, DOC, DOCX (Max 5MB)</span>
                  <input type="file" class="form-control" name="proof_of_income" id="proof_of_income"
                         accept=".jpg,.jpeg,.png,.pdf,.doc,.docx" required />
                  <span class="validation-message" id="proof-income-error"></span>
                </div>

                <div class="col-sm-12 col-md-4">
                  <label class="form-label" for="coe_document">Certificate of Employment (COE) <span class="required">*</span></label>
                  <span class="file-hint">PDF, DOC, DOCX only (Max 5MB)</span>
                  <input type="file" class="form-control" name="coe_document" id="coe_document"
                         accept=".pdf,.doc,.docx" required />
                  <span class="validation-message" id="coe-error"></span>
                </div>

              </div>
            </section>

            <!-- Actions -->
            <div class="d-flex flex-wrap gap-3 justify-content-end pt-2">
              <button class="btn-back" type="button" onclick="location.href='index.php'">
                <i class="fas fa-arrow-left me-2"></i>Back
              </button>
              <button type="submit" class="btn-submit">
                Submit Application <i class="fas fa-paper-plane ms-2"></i>
              </button>
            </div>

          </form>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- ═══════════════ MODAL ═══════════════ -->
<div id="combined-modal" class="modal-overlay" role="dialog" aria-modal="true">
  <div class="modal-box">

    <!-- Terms view -->
    <div id="terms-view">
      <div class="modal-box-header">
        <div>
          <img src="pictures/logo.png" alt="Evergreen Logo">
        </div>
        <div>
          <h2>Terms and Agreement</h2>
          <p>Please review carefully before proceeding</p>
        </div>
      </div>
      <div class="modal-box-body">
        <div class="terms-body">
          <h3>1. Overview</h3>
          <p>By using Evergreen Bank services, you agree to these Terms and our Privacy Policy.</p>
          <h3>2. Account Terms</h3>
          <p>You must provide accurate, current, and complete account information.</p>
          <h3>3. Privacy and Data Protection</h3>
          <p>We take privacy seriously and implement reasonable security measures.</p>
          <h3>4. Fees and Charges</h3>
          <p>Fees are deducted automatically as outlined in our Fee Schedule.</p>
          <h3>5. Security Measures</h3>
          <p>We employ strong authentication methods and monitor accounts for suspicious activity.</p>
          <h3>6. Dispute Resolution</h3>
          <p>Any disputes shall be resolved under binding arbitration according to applicable law.</p>
        </div>
        <p class="acceptance-text">By clicking "I Accept", you acknowledge that you have read and agree to these Terms.</p>
        <div class="d-flex gap-3 justify-content-end">
          <button class="btn-decline" onclick="closeModal()">I Decline</button>
          <button class="btn-accept" onclick="acceptTerms()">I Accept</button>
        </div>
      </div>
    </div>

    <!-- Confirmation view -->
    <div id="confirmation-view" class="confirm-view" style="display:none;">
      <img src="pictures/check.png" alt="Success" style="width:90px;height:90px;">
      <h2>Loan Application Submitted Successfully!</h2>
      <p>Your loan request has been received. You will receive an update soon.</p>
      <div class="reference-box">
        <strong>Reference No:</strong> <span id="ref-number">—</span><br>
        <strong>Date:</strong> <span id="ref-date">—</span>
      </div>
      <button class="btn-dashboard" onclick="location.href='Dashboard.php'">Go To Dashboard</button>
    </div>

  </div>
</div>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Original external JS (kept as-is) -->
<script src="loan_appform.js"></script>

<!-- ══════════════════════════════════════════════════
     ALL ORIGINAL JAVASCRIPT LOGIC — UNTOUCHED
═════════════════════════════════════════════════════ -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Auto-select loan type from URL param ?loanType=Personal%20Loan
    const urlParams = new URLSearchParams(window.location.search);
    const loanTypeName = urlParams.get('loanType');
    if (loanTypeName) {
        const loanSelect = document.getElementById('loan_type');
        for (let option of loanSelect.options) {
            if (option.text.trim() === decodeURIComponent(loanTypeName).trim()) {
                option.selected = true;
                break;
            }
        }
    }

    // ── File validation ──────────────────────────────────────────────────────
    const validIdInput = document.getElementById('attachment');
    const proofInput   = document.getElementById('proof_of_income');
    const coeInput     = document.getElementById('coe_document');

    const maxFileSize  = 5 * 1024 * 1024;
    const allFileTypes = [
        'image/jpeg', 'image/jpg', 'image/png',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];
    const coeTypes = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];

    function validateFile(input, allowedTypes, errorId) {
        const file      = input.files[0];
        const errorSpan = document.getElementById(errorId);
        if (!file) return true;
        if (!allowedTypes.includes(file.type)) {
            errorSpan.textContent = 'Invalid file type. Please upload an allowed format.';
            input.value = '';
            return false;
        }
        if (file.size > maxFileSize) {
            errorSpan.textContent = 'File size exceeds 5MB. Please upload a smaller file.';
            input.value = '';
            return false;
        }
        errorSpan.textContent = '';
        return true;
    }

    validIdInput.addEventListener('change', () => validateFile(validIdInput, allFileTypes, 'attachment-error'));
    proofInput.addEventListener('change',   () => validateFile(proofInput,   allFileTypes, 'proof-income-error'));
    coeInput.addEventListener('change',     () => validateFile(coeInput,     coeTypes,     'coe-error'));

    // ── Progress step completion tracking ───────────────────────────────────
    const completedSections = new Set();

    // Declare sections first so updateProgressSteps can reference it
    const sections = [
        { sectionId: 'step-account-info',       progressId: 'progress-account' },
        { sectionId: 'step-loan-details',        progressId: 'progress-loan'    },
        { sectionId: 'step-supporting-details',  progressId: 'progress-support' },
    ];

    function isSectionComplete(sectionId) {
        const section = document.getElementById(sectionId);
        if (!section) return false;
        const inputs = section.querySelectorAll('input[required], select[required], textarea[required]');
        for (const input of inputs) {
            if (input.type === 'file') {
                if (!input.files || input.files.length === 0) return false;
            } else {
                if (!input.value || input.value.trim() === '') return false;
            }
        }
        return inputs.length > 0;
    }

    function updateProgressSteps() {
        sections.forEach(s => {
            const stepEl = document.getElementById(s.progressId);
            if (!stepEl) return;
            if (isSectionComplete(s.sectionId)) {
                completedSections.add(s.progressId);
                stepEl.classList.remove('active');
                stepEl.classList.add('completed');
            } else {
                completedSections.delete(s.progressId);
                stepEl.classList.remove('completed');
            }
        });
    }

    // Listen for any input changes across the whole form to re-evaluate completion
    document.getElementById('loanForm').addEventListener('input', updateProgressSteps);
    document.getElementById('loanForm').addEventListener('change', updateProgressSteps);

    // ── Progress step scroll highlighting (only for non-completed steps) ─────
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const match = sections.find(s => s.sectionId === entry.target.id);
                if (match) {
                    sections.forEach(s => {
                        const el = document.getElementById(s.progressId);
                        // Only remove active; never remove completed
                        if (el && !completedSections.has(s.progressId)) {
                            el.classList.remove('active');
                        }
                    });
                    const activeEl = document.getElementById(match.progressId);
                    // Only set active if section is not already completed
                    if (activeEl && !completedSections.has(match.progressId)) {
                        activeEl.classList.add('active');
                    }
                }
            }
        });
    }, { threshold: 0.4 });

    sections.forEach(s => {
        const el = document.getElementById(s.sectionId);
        if (el) observer.observe(el);
    });
});

// Show Terms modal on valid form submit
document.getElementById('loanForm').addEventListener('submit', function (e) {
    e.preventDefault();
    if (this.checkValidity()) {
        const modal = document.getElementById('combined-modal');
        document.getElementById('terms-view').style.display = 'block';
        document.getElementById('confirmation-view').style.display = 'none';
        modal.classList.add('visible');
        document.getElementById('pageContent').classList.add('blur-background');
        document.body.style.overflow = 'hidden';
    } else {
        this.reportValidity();
    }
});

// Accept terms → submit via fetch
async function acceptTerms() {
    const form      = document.getElementById('loanForm');
    const formData  = new FormData(form);
    const acceptBtn = document.querySelector('.btn-accept');
    const origText  = acceptBtn.textContent;

    acceptBtn.disabled    = true;
    acceptBtn.textContent = 'Submitting…';

    try {
        const response = await fetch('submit_loan.php', { method: 'POST', body: formData });
        const result   = await response.json();

        if (result.success) {
            // Populate reference details first
            if (result.loan_id) {
                document.getElementById('ref-number').textContent =
                    'LOAN-' + String(result.loan_id).padStart(6, '0');
            }
            document.getElementById('ref-date').textContent =
                new Date().toLocaleDateString('en-US', { year:'numeric', month:'long', day:'numeric' });

            // Hide terms view, show confirmation view
            const termsView   = document.getElementById('terms-view');
            const confirmView = document.getElementById('confirmation-view');
            termsView.style.setProperty('display', 'none', 'important');
            confirmView.style.setProperty('display', 'block', 'important');

            // Remove blur from page, keep modal open and on top
            document.getElementById('pageContent').classList.remove('blur-background');
            const modal = document.getElementById('combined-modal');
            modal.style.setProperty('display', 'flex', 'important');
            modal.style.setProperty('z-index', '9999', 'important');
            modal.classList.add('visible');
            document.body.style.overflow = 'hidden';

        } else {
            alert('❌ Error: ' + result.error);
            acceptBtn.disabled    = false;
            acceptBtn.textContent = origText;
        }
    } catch (error) {
        console.error('Submission error:', error);
        alert('❌ An error occurred while submitting your application. Please try again.');
        acceptBtn.disabled    = false;
        acceptBtn.textContent = origText;
    }
}

function closeModal() {
    document.getElementById('combined-modal').classList.remove('visible');
    document.getElementById('pageContent').classList.remove('blur-background');
    document.body.style.overflow = 'auto';
}
</script>

<!-- ══════════════════════════════════════════════════
     ID NUMBER FORMAT VALIDATION PER VALID ID TYPE
═════════════════════════════════════════════════════ -->
<script>
(function () {
    // ── Format definitions keyed by lowercase valid_id_type name ─────────────
    // Handles all 12 IDs in loan_valid_id (including duplicate "Postal ID" at id=2 and id=10)
    const idFormats = {
        "driver's license": {
            pattern:     /^[A-Z]\d{2}-\d{2}-\d{6}$/,
            hint:        "📋 Format: A00-00-000000 (e.g., N01-23-456789)",
            placeholder: "e.g., N01-23-456789",
            errorMsg:    "Invalid format. Expected: A00-00-000000 (e.g., N01-23-456789)"
        },
        "postal id": {
            pattern:     /^\d{4}-\d{7}-\d$/,
            hint:        "📋 Format: 0000-0000000-0 (e.g., 1234-5678901-2)",
            placeholder: "e.g., 1234-5678901-2",
            errorMsg:    "Invalid format. Expected: 0000-0000000-0 (e.g., 1234-5678901-2)"
        },
        "gsis": {
            pattern:     /^\d{11}$/,
            hint:        "📋 Format: 11-digit number (e.g., 12345678901)",
            placeholder: "e.g., 12345678901",
            errorMsg:    "Invalid format. Expected: 11 consecutive digits (e.g., 12345678901)"
        },
        "nbi clearance": {
            pattern:     /^[A-Z]{2}\d{2}-\d{5}[A-Z]$/,
            hint:        "📋 Format: AA00-00000A (e.g., MA23-12345B)",
            placeholder: "e.g., MA23-12345B",
            errorMsg:    "Invalid format. Expected: AA00-00000A (e.g., MA23-12345B)"
        },
        "passport": {
            pattern:     /^[A-Z]{2}\d{7}$/,
            hint:        "📋 Format: 2 letters + 7 digits (e.g., AA1234567)",
            placeholder: "e.g., AA1234567",
            errorMsg:    "Invalid format. Expected: 2 letters + 7 digits (e.g., AA1234567)"
        },
        "national id": {
            pattern:     /^\d{4}-\d{7}-\d$/,
            hint:        "📋 Format: 0000-0000000-0 (e.g., 0001-2345678-9)",
            placeholder: "e.g., 0001-2345678-9",
            errorMsg:    "Invalid format. Expected: 0000-0000000-0 (e.g., 0001-2345678-9)"
        },
        "umid": {
            pattern:     /^\d{4}-\d{7}-\d$/,
            hint:        "📋 Format: 0000-0000000-0 (e.g., 0001-2345678-9)",
            placeholder: "e.g., 0001-2345678-9",
            errorMsg:    "Invalid format. Expected: 0000-0000000-0 (e.g., 0001-2345678-9)"
        },
        "voter's id": {
            pattern:     /^\d{4}-\d{5}-[A-Z]{2}$/,
            hint:        "📋 Format: 0000-00000-AA (e.g., 1234-56789-AB)",
            placeholder: "e.g., 1234-56789-AB",
            errorMsg:    "Invalid format. Expected: 0000-00000-AA (e.g., 1234-56789-AB)"
        },
        "prc id": {
            pattern:     /^\d{7}$/,
            hint:        "📋 Format: 7-digit number (e.g., 1234567)",
            placeholder: "e.g., 1234567",
            errorMsg:    "Invalid format. Expected: 7 consecutive digits (e.g., 1234567)"
        },
        "philhealth id": {
            pattern:     /^\d{2}-\d{9}-\d$/,
            hint:        "📋 Format: 00-000000000-0 (e.g., 12-345678901-2)",
            placeholder: "e.g., 12-345678901-2",
            errorMsg:    "Invalid format. Expected: 00-000000000-0 (e.g., 12-345678901-2)"
        },
        "senior citizen id": {
            pattern:     /^SC-\d{4}-\d{6}$/,
            hint:        "📋 Format: SC-0000-000000 (e.g., SC-2024-123456)",
            placeholder: "e.g., SC-2024-123456",
            errorMsg:    "Invalid format. Expected: SC-0000-000000 (e.g., SC-2024-123456)"
        }
    };

    const idTypeSelect  = document.getElementById('loan_valid_id_type');
    const idNumberInput = document.getElementById('valid_id_number');
    const idHint        = document.getElementById('id-format-hint');
    const idError       = document.getElementById('valid-id-number-error');

    // ── Resolve format key from the selected option's data-id-name attribute ──
    function getFormat() {
        const selected = idTypeSelect.options[idTypeSelect.selectedIndex];
        if (!selected || !selected.value) return null;
        // Use the data-id-name attribute (lowercased PHP value) for exact matching
        const key = (selected.getAttribute('data-id-name') || selected.text.trim().toLowerCase());
        return idFormats[key] || null;
    }

    // ── Update hint + placeholder when ID type changes ────────────────────────
    function applyFormat() {
        const fmt = getFormat();
        idError.textContent = '';
        idNumberInput.value = '';

        if (fmt) {
            idHint.textContent        = fmt.hint;
            idNumberInput.placeholder = fmt.placeholder;
        } else {
            idHint.textContent        = '';
            idNumberInput.placeholder = 'Select a Valid ID type first';
        }
    }

    // ── Validate the ID number field ─────────────────────────────────────────
    function validateIdNumber() {
        const fmt = getFormat();
        const val = idNumberInput.value.trim().toUpperCase();

        // No format rule for this ID type — skip pattern check
        if (!fmt) {
            idError.textContent = '';
            return true;
        }

        if (!val) {
            idError.textContent = '⚠ ID Number is required.';
            return false;
        }

        if (!fmt.pattern.test(val)) {
            idError.textContent = '⚠ ' + fmt.errorMsg;
            return false;
        }

        idError.textContent = '';
        return true;
    }

    // ── Auto-uppercase as user types + live validation ─────────────────────
    idNumberInput.addEventListener('input', function () {
        const pos   = this.selectionStart;
        this.value  = this.value.toUpperCase();
        this.setSelectionRange(pos, pos);
        if (this.value.trim().length > 0) validateIdNumber();
        else idError.textContent = '';
    });

    idNumberInput.addEventListener('blur', validateIdNumber);

    // ── Reset when ID type changes ─────────────────────────────────────────
    idTypeSelect.addEventListener('change', applyFormat);

    // ── Block form submission if ID number format is invalid ──────────────
    // Runs in CAPTURE phase (true) so it fires before the existing submit handler
    document.getElementById('loanForm').addEventListener('submit', function (e) {
        if (!validateIdNumber()) {
            e.preventDefault();
            e.stopImmediatePropagation();
            idNumberInput.focus();
            idNumberInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }, true);

})();
</script>

</body>
</html>