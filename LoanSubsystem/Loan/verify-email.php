<?php
session_start();

// ── Load PHPMailer ───────────────────────────────────────────────
require 'PHPMailer-7.0.0/src/Exception.php';
require 'PHPMailer-7.0.0/src/PHPMailer.php';
require 'PHPMailer-7.0.0/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// ── Mail config ──────────────────────────────────────────────────
$MAIL_HOST      = 'smtp.gmail.com';
$MAIL_PORT      = 587;
$MAIL_USERNAME  = 'franciscarpeso@gmail.com';
$MAIL_PASSWORD  = 'bwobttvnbpqvzimv';
$MAIL_FROM      = 'franciscarpeso@gmail.com';
$MAIL_FROM_NAME = 'Evergreen Trust and Savings';

// Redirect if no pending registration in session
if (empty($_SESSION['pending_reg'])) {
    header('Location: register-account.php');
    exit;
}

$reg          = $_SESSION['pending_reg'];
$error        = '';
$info         = '';
$show_success = false;

// ── Resend PIN ───────────────────────────────────────────────────
if (isset($_POST['resend'])) {
    $pin = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $_SESSION['pending_reg']['pin']         = $pin;
    $_SESSION['pending_reg']['pin_expires'] = time() + 600;
    $_SESSION['pending_reg']['attempts']    = 0;
    $reg = $_SESSION['pending_reg'];

    $mail = new PHPMailer(true);
    try {
        $mail->SMTPDebug   = SMTP::DEBUG_OFF;
        $mail->isSMTP();
        $mail->Host        = $MAIL_HOST;
        $mail->SMTPAuth    = true;
        $mail->Username    = $MAIL_USERNAME;
        $mail->Password    = $MAIL_PASSWORD;
        $mail->SMTPSecure  = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port        = $MAIL_PORT;
        $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];
        $mail->Timeout     = 30;
        $mail->setFrom($MAIL_FROM, $MAIL_FROM_NAME);
        $mail->addAddress($reg['user_email'], $reg['first_name']);
        $mail->isHTML(true);
        $mail->Subject = 'Your New Evergreen Verification Code';
        $mail->Body    = getEmailBody($reg['first_name'], $pin);
        $mail->AltBody = "Hello {$reg['first_name']},\n\nYour new code is: {$pin}\n\nExpires in 10 minutes.";
        $mail->send();
        $info = "A new code was sent to " . htmlspecialchars($reg['user_email']) . ".";
    } catch (Exception $e) {
        $error = "Could not resend: " . $mail->ErrorInfo;
    }
}

// ── Verify PIN ───────────────────────────────────────────────────
if (isset($_POST['verify'])) {
    if (!empty($_POST['pin']) && strlen(trim($_POST['pin'])) === 6) {
        $entered = trim($_POST['pin']);
    } else {
        $entered = trim(
            ($_POST['d1'] ?? '') . ($_POST['d2'] ?? '') . ($_POST['d3'] ?? '') .
            ($_POST['d4'] ?? '') . ($_POST['d5'] ?? '') . ($_POST['d6'] ?? '')
        );
    }

    if ($reg['attempts'] >= 5) {
        session_destroy();
        header('Location: register-account.php?error=toomany');
        exit;
    }

    if (time() > $reg['pin_expires']) {
        $error = "Your code has expired. Please request a new one below.";
    } elseif ($entered !== $reg['pin']) {
        $_SESSION['pending_reg']['attempts']++;
        $reg['attempts']++;
        $left  = 5 - $reg['attempts'];
        $error = "Incorrect code. {$left} attempt(s) remaining.";
    } else {
        $dbhost = 'localhost'; $dbname = 'loandb'; $dbuser = 'root'; $dbpass = '';
        try {
            $pdo = new PDO(
                "mysql:host=$dbhost;dbname=$dbname;charset=utf8mb4",
                $dbuser, $dbpass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $stmt = $pdo->prepare("
                INSERT INTO users (
                    first_name, middle_name, surname, full_name,
                    address, province_id, province_name, municipality_id, municipality_name,
                    barangay_id, barangay_name, birthday, user_email,
                    contact_number, password_hash, account_number
                ) VALUES (
                    :first_name, :middle_name, :surname, :full_name,
                    :address, :province_id, :province_name, :municipality_id, :municipality_name,
                    :barangay_id, :barangay_name, :birthday, :user_email,
                    :contact_number, :password_hash, :account_number
                )
            ");
            $stmt->execute([
                ':first_name'        => $reg['first_name'],
                ':middle_name'       => $reg['middle_name']       ?? null,
                ':surname'           => $reg['surname'],
                ':full_name'         => $reg['full_name'],
                ':address'           => $reg['address'],
                ':province_id'       => $reg['province_id'],
                ':province_name'     => $reg['province_name'],
                ':municipality_id'   => $reg['municipality_id'],
                ':municipality_name' => $reg['municipality_name'],
                ':barangay_id'       => $reg['barangay_id'],
                ':barangay_name'     => $reg['barangay_name'],
                ':birthday'          => $reg['birthday'],
                ':user_email'        => $reg['user_email'],
                ':contact_number'    => $reg['contact_number'],
                ':password_hash'     => $reg['password_hash'],
                ':account_number'    => $reg['account_number'],
            ]);
            session_destroy();
            $show_success = true;
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// ── Email HTML helper ────────────────────────────────────────────
function getEmailBody(string $name, string $pin): string {
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"/></head>
<body style="margin:0;padding:0;background:#f4f4f4;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;padding:40px 0;">
    <tr><td align="center">
      <table width="520" cellpadding="0" cellspacing="0"
             style="background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,.10);">
        <tr>
          <td style="background:#0a3b2f;padding:28px 32px;text-align:center;">
            <h1 style="color:#fff;margin:0;font-size:22px;">🌿 EVERGREEN</h1>
            <p style="color:#a8d5b5;margin:6px 0 0;font-size:13px;">Trust and Savings Bank</p>
          </td>
        </tr>
        <tr>
          <td style="padding:36px 40px;">
            <p style="color:#2d4a3e;font-size:16px;margin:0 0 12px;">Hello, <strong>{$name}</strong> 👋</p>
            <p style="color:#555;font-size:14px;line-height:1.7;margin:0 0 24px;">
              Here is your new 6-digit verification code. Valid for <strong>10 minutes</strong>.
            </p>
            <table width="100%" cellpadding="0" cellspacing="0">
              <tr>
                <td align="center" style="padding:10px 0 28px;">
                  <div style="display:inline-block;background:#f0faf3;border:2px dashed #43a047;border-radius:12px;padding:22px 52px;">
                    <p style="margin:0 0 8px;color:#888;font-size:12px;text-transform:uppercase;letter-spacing:1.5px;">Verification Code</p>
                    <p style="margin:0;font-size:44px;font-weight:800;letter-spacing:14px;color:#0a3b2f;">{$pin}</p>
                  </div>
                </td>
              </tr>
            </table>
            <p style="color:#888;font-size:13px;">Never share this code with anyone.</p>
          </td>
        </tr>
        <tr>
          <td style="background:#f9f5f0;padding:20px 40px;text-align:center;border-top:1px solid #e8e0d8;">
            <p style="color:#aaa;font-size:12px;margin:0;">&copy; 2025 Evergreen Trust and Savings &middot; Automated message</p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
}

function maskEmail(string $email): string {
    [$local, $domain] = explode('@', $email, 2);
    return mb_substr($local, 0, 1) . str_repeat('*', max(3, mb_strlen($local) - 1)) . '@' . $domain;
}

$maskedEmail  = maskEmail($reg['user_email']);
$secondsLeft  = max(0, $reg['pin_expires'] - time());
$expired      = ($secondsLeft === 0 && !$show_success);
$attemptsDone = (int)$reg['attempts'];
$displayName  = !empty($reg['first_name']) ? $reg['first_name'] : ($reg['full_name'] ?? 'there');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Verify Email – Evergreen Trust and Savings</title>

  <!-- Bootstrap 5 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

  <style>
    :root {
      --eg-dark:   #0a3b2f;
      --eg-mid:    #1a6b5a;
      --eg-light:  #e8f5e9;
      --eg-accent: #43a047;
      --eg-danger: #e53935;
      --eg-bg:     #f4f8f6;
      --eg-text:   #2d4a3e;
      --eg-muted:  #6c8a7e;
    }

    *, *::before, *::after { box-sizing: border-box; }

    body {
      background: var(--eg-bg);
      font-family: 'DM Sans', sans-serif;
      color: var(--eg-text);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    /* ── Navbar ── */
    .eg-navbar {
      background: var(--eg-dark);
      padding: 0.75rem 1.5rem;
    }
    .eg-navbar .brand-name {
      font-family: 'DM Serif Display', serif;
      font-size: 1.25rem;
      color: #fff;
      letter-spacing: 1px;
    }
    .eg-navbar .brand-sub {
      font-size: 0.7rem;
      color: #a8d5b5;
      letter-spacing: 0.5px;
    }
    .eg-navbar .logo-img { height: 36px; }

    /* ── Page wrapper ── */
    .page-wrapper {
      flex: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 2.5rem 1rem 3rem;
      background:
        radial-gradient(ellipse 60% 40% at 10% 0%, rgba(10,59,47,0.08) 0%, transparent 70%),
        radial-gradient(ellipse 50% 35% at 90% 100%, rgba(67,160,71,0.06) 0%, transparent 70%),
        var(--eg-bg);
    }

    /* ── Card ── */
    .eg-card {
      width: 100%;
      max-width: 460px;
      background: #fff;
      border-radius: 16px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.04), 0 8px 32px rgba(10,59,47,0.10);
      padding: 2.5rem 2rem;
      animation: fadeUp 0.35s ease;
    }
    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(16px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    .card-eyebrow {
      font-size: 0.7rem;
      font-weight: 700;
      letter-spacing: 1.5px;
      text-transform: uppercase;
      color: var(--eg-accent);
      margin-bottom: 0.35rem;
      text-align: center;
    }
    .card-title {
      font-family: 'DM Serif Display', serif;
      font-size: 1.6rem;
      color: var(--eg-dark);
      text-align: center;
      margin-bottom: 0.5rem;
    }
    .card-subtitle {
      font-size: 0.875rem;
      color: var(--eg-muted);
      line-height: 1.7;
      text-align: center;
      margin-bottom: 1.5rem;
    }

    /* ── Email badge ── */
    .email-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      background: #f0faf3;
      border: 1px solid #a5d6a7;
      border-radius: 99px;
      padding: 0.35rem 0.85rem;
      font-size: 0.82rem;
      font-weight: 600;
      color: var(--eg-dark);
      margin-bottom: 1.5rem;
    }

    /* ── Alerts ── */
    .eg-alert {
      padding: 0.75rem 1rem;
      border-radius: 10px;
      font-size: 0.85rem;
      line-height: 1.5;
      margin-bottom: 1.25rem;
      display: flex;
      align-items: flex-start;
      gap: 0.5rem;
    }
    .eg-alert-error   { color: #b71c1c; background: #fff5f5; border: 1px solid #ffcdd2; }
    .eg-alert-success { color: #1b5e20; background: #f0faf3; border: 1px solid #a5d6a7; }
    .eg-alert i { font-size: 1rem; flex-shrink: 0; margin-top: 1px; }

    /* ── Attempts dots ── */
    .attempts-row {
      display: flex;
      justify-content: center;
      gap: 0.45rem;
      margin-bottom: 1.25rem;
    }
    .attempt-dot {
      width: 10px; height: 10px;
      border-radius: 50%;
      background: #dce8e3;
      transition: background 0.2s;
    }
    .attempt-dot.used { background: var(--eg-danger); }

    /* ── OTP inputs ── */
    .otp-row {
      display: flex;
      gap: 0.6rem;
      justify-content: center;
      margin-bottom: 1.5rem;
    }
    .pin-digit {
      width: 52px; height: 60px;
      border: 2px solid #d0ddd8;
      border-radius: 12px;
      text-align: center;
      font-size: 1.65rem;
      font-weight: 700;
      color: var(--eg-dark);
      background: #fafcfb;
      transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
      caret-color: var(--eg-dark);
    }
    .pin-digit:focus {
      outline: none;
      border-color: var(--eg-dark);
      box-shadow: 0 0 0 3px rgba(10,59,47,0.13);
      background: #fff;
    }
    .pin-digit.filled  { border-color: var(--eg-accent); background: #f0faf3; }
    .pin-digit.error   { border-color: var(--eg-danger); background: #fff8f8; animation: shake 0.3s ease; }
    @keyframes shake {
      0%,100% { transform: translateX(0); }
      25%     { transform: translateX(-4px); }
      75%     { transform: translateX(4px); }
    }
    @media (max-width: 400px) {
      .pin-digit { width: 42px; height: 52px; font-size: 1.3rem; }
      .otp-row   { gap: 0.35rem; }
    }

    /* ── Timer ── */
    .timer-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      background: #f4f8f6;
      border: 1px solid #d0ddd8;
      border-radius: 99px;
      padding: 0.3rem 0.85rem;
      font-size: 0.78rem;
      font-weight: 600;
      color: var(--eg-muted);
      margin-bottom: 1.25rem;
    }
    .timer-badge #countdown { color: var(--eg-dark); font-weight: 700; }
    .timer-badge.expired    { border-color: #ffcdd2; background: #fff5f5; }
    .timer-badge.expired #countdown { color: var(--eg-danger); }

    /* ── Primary button ── */
    .btn-eg-primary {
      width: 100%;
      padding: 0.8rem 1rem;
      background: var(--eg-dark);
      color: #fff;
      border: none;
      border-radius: 10px;
      font-size: 0.95rem;
      font-weight: 700;
      letter-spacing: 0.3px;
      cursor: pointer;
      transition: background 0.25s, transform 0.15s, box-shadow 0.25s;
      box-shadow: 0 2px 8px rgba(10,59,47,0.18);
      margin-bottom: 0.75rem;
    }
    .btn-eg-primary:hover:not(:disabled) {
      background: #082e24;
      box-shadow: 0 4px 16px rgba(10,59,47,0.28);
      transform: translateY(-1px);
    }
    .btn-eg-primary:disabled {
      background: #b0bec5;
      cursor: not-allowed;
      transform: none;
      box-shadow: none;
    }

    .btn-link-eg {
      background: none;
      border: none;
      color: var(--eg-dark);
      font-weight: 700;
      font-size: 0.85rem;
      cursor: pointer;
      text-decoration: underline;
      padding: 0;
    }
    .btn-link-eg:hover:not(:disabled) { color: #082e24; }
    .btn-link-eg:disabled { color: #aaa; cursor: not-allowed; text-decoration: none; }

    /* ── Bottom links ── */
    .bottom-links {
      text-align: center;
      margin-top: 0.75rem;
      font-size: 0.82rem;
      color: var(--eg-muted);
      display: flex;
      flex-direction: column;
      gap: 0.5rem;
    }
    .bottom-links a {
      color: var(--eg-dark);
      text-decoration: none;
      font-weight: 700;
    }
    .bottom-links a:hover { text-decoration: underline; }

    .resend-timer {
      font-size: 0.72rem;
      color: var(--eg-muted);
      margin-top: 0.25rem;
    }

    /* ── Divider ── */
    .eg-divider {
      border: none;
      border-top: 1px solid #e8f0ec;
      margin: 1.25rem 0;
    }

    /* ── Success modal ── */
    @keyframes fadeIn  { from { opacity:0; } to { opacity:1; } }
    @keyframes slideUp { from { opacity:0; transform: translateY(30px) scale(.9); } to { opacity:1; transform: translateY(0) scale(1); } }
    @keyframes draw    { to   { stroke-dashoffset: 0; } }
    @keyframes pulse   { 0%,100% { box-shadow: 0 0 0 0 rgba(10,59,47,.4); } 50% { box-shadow: 0 0 0 20px rgba(10,59,47,0); } }

    .modal-overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0,54,49,0.88);
      backdrop-filter: blur(8px);
      align-items: center;
      justify-content: center;
      z-index: 10000;
      animation: fadeIn 0.4s ease;
    }
    .modal-overlay.show { display: flex; }

    .modal-box {
      background: #fff;
      padding: 3rem 2.5rem;
      border-radius: 20px;
      box-shadow: 0 25px 80px rgba(0,0,0,0.4);
      max-width: 480px;
      width: 90%;
      text-align: center;
      animation: slideUp 0.5s cubic-bezier(0.34,1.56,0.64,1);
    }

    .modal-check-wrap {
      width: 96px; height: 96px;
      background: linear-gradient(135deg, var(--eg-dark) 0%, var(--eg-mid) 100%);
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto 1.75rem;
      box-shadow: 0 10px 30px rgba(10,59,47,0.3);
      animation: pulse 2s ease 0.9s infinite;
    }
    .modal-check-wrap svg path {
      stroke-dasharray: 50;
      stroke-dashoffset: 50;
      animation: draw 0.5s ease 0.7s forwards;
    }

    .modal-box h3 {
      font-family: 'DM Serif Display', serif;
      color: var(--eg-dark);
      font-size: 1.85rem;
      margin-bottom: 0.75rem;
    }
    .modal-box p {
      color: #666;
      font-size: 1rem;
      line-height: 1.65;
      margin-bottom: 1.5rem;
    }
    .modal-note {
      background: #f0f9f8;
      border-left: 4px solid var(--eg-mid);
      padding: 1rem 1.25rem;
      border-radius: 8px;
      margin-bottom: 1.5rem;
      text-align: left;
    }
    .modal-note p { color: var(--eg-dark); font-size: 0.875rem; margin: 0; line-height: 1.6; }
    .modal-countdown { color: #999; font-size: 0.825rem; margin-bottom: 1.25rem; }
    .modal-countdown span { color: var(--eg-dark); font-weight: 700; }
    .btn-modal {
      background: linear-gradient(135deg, var(--eg-dark) 0%, var(--eg-mid) 100%);
      color: #fff;
      border: none;
      padding: 0.85rem 2.5rem;
      border-radius: 10px;
      font-size: 0.95rem;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.3s;
      box-shadow: 0 4px 15px rgba(10,59,47,0.3);
      letter-spacing: 0.4px;
    }
    .btn-modal:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(10,59,47,0.4);
    }

    /* ── Responsive ── */
    @media (max-width: 480px) {
      .eg-card { padding: 1.75rem 1.25rem; border-radius: 12px; }
      .card-title { font-size: 1.35rem; }
      .modal-box { padding: 2rem 1.5rem; }
    }
  </style>
</head>
<body>

<!-- ── Navbar ── -->
<nav class="eg-navbar d-flex align-items-center gap-3">
  <img src="pictures/logo.png" alt="Evergreen Logo" class="logo-img">
  <div>
    <div class="brand-name">EVERGREEN</div>
    <div class="brand-sub">Trust and Savings Bank</div>
  </div>
</nav>

<!-- ── Page Wrapper ── -->
<div class="page-wrapper">

  <?php if (!$show_success): ?>
  <div class="eg-card">

    <div class="card-eyebrow">Email Verification</div>
    <div class="card-title">Verify Your Email</div>
    <p class="card-subtitle">
      We sent a 6-digit code to your registered address.
    </p>

    <!-- Email badge -->
    <div class="text-center">
      <div class="email-badge">
        <i class="bi bi-envelope-check-fill"></i>
        <?= htmlspecialchars($maskedEmail) ?>
      </div>
    </div>

    <?php if ($error): ?>
      <div class="eg-alert eg-alert-error">
        <i class="bi bi-exclamation-circle-fill"></i>
        <span><?= htmlspecialchars($error) ?></span>
      </div>
    <?php endif; ?>
    <?php if ($info): ?>
      <div class="eg-alert eg-alert-success">
        <i class="bi bi-check-circle-fill"></i>
        <span><?= htmlspecialchars($info) ?></span>
      </div>
    <?php endif; ?>

    <!-- Attempt dots -->
    <div class="attempts-row">
      <?php for ($i = 0; $i < 5; $i++): ?>
        <div class="attempt-dot <?= $i < $attemptsDone ? 'used' : '' ?>"></div>
      <?php endfor; ?>
    </div>

    <form method="POST" action="verify-email.php" id="verify-form">
      <input type="hidden" name="pin" id="pin-hidden">

      <div class="otp-row" id="pin-row">
        <input type="text" class="pin-digit" id="digit-1" name="d1" maxlength="1" inputmode="numeric" autocomplete="one-time-code" <?= $expired ? 'disabled' : '' ?>>
        <input type="text" class="pin-digit" id="digit-2" name="d2" maxlength="1" inputmode="numeric" <?= $expired ? 'disabled' : '' ?>>
        <input type="text" class="pin-digit" id="digit-3" name="d3" maxlength="1" inputmode="numeric" <?= $expired ? 'disabled' : '' ?>>
        <input type="text" class="pin-digit" id="digit-4" name="d4" maxlength="1" inputmode="numeric" <?= $expired ? 'disabled' : '' ?>>
        <input type="text" class="pin-digit" id="digit-5" name="d5" maxlength="1" inputmode="numeric" <?= $expired ? 'disabled' : '' ?>>
        <input type="text" class="pin-digit" id="digit-6" name="d6" maxlength="1" inputmode="numeric" <?= $expired ? 'disabled' : '' ?>>
      </div>

      <!-- Timer badge -->
      <div class="text-center mb-3">
        <div class="timer-badge <?= $expired ? 'expired' : '' ?>" id="timer-wrap">
          <i class="bi bi-clock"></i>
          <?php if ($expired): ?>
            Code <span id="countdown">expired</span> — request a new one below.
          <?php else: ?>
            Expires in <span id="countdown"><?= gmdate('i:s', $secondsLeft) ?></span>
          <?php endif; ?>
        </div>
      </div>

      <button type="submit" name="verify" class="btn-eg-primary" id="btn-verify"
              <?= ($expired || $attemptsDone >= 5) ? 'disabled' : '' ?>>
        <i class="bi bi-shield-check me-2"></i>Verify Code
      </button>
    </form>

    <hr class="eg-divider">

    <form method="POST" action="verify-email.php" id="resend-form">
      <div class="text-center">
        <button type="submit" name="resend" class="btn-link-eg" id="btn-resend">
          <i class="bi bi-arrow-repeat me-1"></i>Didn't receive it? Resend code
        </button>
        <div class="resend-timer" id="resend-timer"></div>
      </div>
    </form>

    <div class="bottom-links mt-3">
      <span>Wrong email? <a href="register-account.php"><i class="bi bi-arrow-left me-1"></i>Go back &amp; edit</a></span>
    </div>

  </div><!-- /.eg-card -->

  <?php else: ?>
  <!-- Slim card while modal animates in -->
  <div class="eg-card text-center py-5">
    <div style="font-size:2.5rem;margin-bottom:0.75rem;">✅</div>
    <p style="color:var(--eg-dark);font-weight:600;">Account created! Redirecting…</p>
  </div>
  <?php endif; ?>

</div><!-- /.page-wrapper -->

<!-- ── Success Modal ── -->
<div class="modal-overlay <?= $show_success ? 'show' : '' ?>" id="success-modal">
  <div class="modal-box">
    <div class="modal-check-wrap">
      <svg width="48" height="48" viewBox="0 0 50 50">
        <path d="M 10 25 L 20 35 L 40 15"
              stroke="white" stroke-width="4"
              fill="none" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </div>
    <h3>Account Created!</h3>
    <p>
      Welcome to <strong style="color:var(--eg-dark);">Evergreen Trust and Savings</strong>,
      <strong style="color:var(--eg-dark);"><?= htmlspecialchars($displayName) ?></strong>!<br>
      Your email has been verified and your account is ready to use.
    </p>
    <div class="modal-note">
      <p>
        <strong><i class="bi bi-check-circle-fill me-1" style="color:var(--eg-accent);"></i>You're all set</strong><br>
        <span style="color:#666;">You can now log in with your registered email and password.</span>
      </p>
    </div>
    <p class="modal-countdown">
      Redirecting to login in <span id="modal-countdown">3</span> seconds…
    </p>
    <button class="btn-modal" onclick="window.location.href='login.php'">
      <i class="bi bi-box-arrow-in-right me-2"></i>Go to Login Now
    </button>
  </div>
</div>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
<?php if (!$show_success): ?>

const digits    = Array.from(document.querySelectorAll('.pin-digit'));
const hidden    = document.getElementById('pin-hidden');
const btnVerify = document.getElementById('btn-verify');

digits.forEach((input, idx) => {
  input.addEventListener('keypress', e => {
    if (!/[0-9]/.test(e.key)) e.preventDefault();
  });
  input.addEventListener('input', () => {
    input.value = input.value.replace(/\D/g, '').slice(-1);
    input.classList.toggle('filled', input.value !== '');
    if (input.value && idx < digits.length - 1) digits[idx + 1].focus();
    syncHidden();
  });
  input.addEventListener('keydown', e => {
    if (e.key === 'Backspace' && !input.value && idx > 0) {
      digits[idx - 1].value = '';
      digits[idx - 1].classList.remove('filled');
      digits[idx - 1].focus();
      syncHidden();
    }
  });
  input.addEventListener('paste', e => {
    e.preventDefault();
    const pasted = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g,'').slice(0,6);
    pasted.split('').forEach((ch, i) => {
      if (digits[i]) { digits[i].value = ch; digits[i].classList.add('filled'); }
    });
    digits[Math.min(pasted.length, digits.length - 1)].focus();
    syncHidden();
  });
});

function syncHidden() {
  hidden.value = digits.map(d => d.value).join('');
}

<?php if ($error && strpos($error, 'Incorrect') !== false): ?>
digits.forEach(d => { d.classList.add('error'); d.value = ''; d.classList.remove('filled'); });
setTimeout(() => { digits.forEach(d => d.classList.remove('error')); digits[0].focus(); }, 400);
<?php endif; ?>

// Countdown timer
let seconds     = <?= (int)$secondsLeft ?>;
const countEl   = document.getElementById('countdown');
const timerWrap = document.getElementById('timer-wrap');

if (seconds > 0 && countEl) {
  const tick = setInterval(() => {
    seconds--;
    if (seconds <= 0) {
      clearInterval(tick);
      timerWrap.classList.add('expired');
      timerWrap.innerHTML = '<i class="bi bi-clock"></i> Code <span>expired</span> — request a new one below.';
      btnVerify.disabled = true;
      digits.forEach(d => d.disabled = true);
    } else {
      const m = String(Math.floor(seconds / 60)).padStart(2, '0');
      const s = String(seconds % 60).padStart(2, '0');
      countEl.textContent = m + ':' + s;
    }
  }, 1000);
}

// Resend cooldown
const btnResend   = document.getElementById('btn-resend');
const resendTimer = document.getElementById('resend-timer');
function startResendCooldown() {
  let left = 30;
  btnResend.disabled = true;
  resendTimer.textContent = 'You can request a new code in ' + left + 's';
  const cd = setInterval(() => {
    left--;
    resendTimer.textContent = 'You can request a new code in ' + left + 's';
    if (left <= 0) { clearInterval(cd); btnResend.disabled = false; resendTimer.textContent = ''; }
  }, 1000);
}
startResendCooldown();
document.getElementById('resend-form').addEventListener('submit', startResendCooldown);

<?php else: ?>
// Success modal redirect countdown
let countdown = 3;
const cdEl    = document.getElementById('modal-countdown');
const cdTick  = setInterval(() => {
  countdown--;
  if (cdEl) cdEl.textContent = countdown;
  if (countdown <= 0) { clearInterval(cdTick); window.location.href = 'login.php'; }
}, 1000);
<?php endif; ?>
</script>
</body>
</html>