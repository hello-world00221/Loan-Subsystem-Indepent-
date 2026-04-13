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

// ── DB config ────────────────────────────────────────────────────
$DB_HOST = 'localhost';
$DB_NAME = 'loandb';
$DB_USER = 'root';
$DB_PASS = '';

function getDB($host, $name, $user, $pass): PDO {
    return new PDO(
        "mysql:host=$host;dbname=$name;charset=utf8mb4",
        $user, $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
}

// ── Send OTP email ───────────────────────────────────────────────
function sendResetPin(
    string $toEmail, string $toName, string $pin,
    string $host, int $port, string $user, string $pass,
    string $from, string $fromName
): bool|string {
    $mail = new PHPMailer(true);
    try {
        $mail->SMTPDebug  = SMTP::DEBUG_OFF;
        $mail->isSMTP();
        $mail->Host       = $host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $user;
        $mail->Password   = $pass;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $port;
        $mail->SMTPOptions = ['ssl' => [
            'verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true
        ]];
        $mail->Timeout = 60;
        $mail->setFrom($from, $fromName);
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = 'Evergreen – Password Reset Code';
        $mail->Body    = getResetEmailBody($toName, $pin);
        $mail->AltBody = "Hello {$toName},\n\nYour password reset code: {$pin}\n\nExpires in 10 minutes.\n\nIf you did not request this, ignore this email.";
        $mail->send();
        return true;
    } catch (Exception $e) {
        return $mail->ErrorInfo ?: $e->getMessage();
    }
}

function getResetEmailBody(string $name, string $pin): string {
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
              We received a request to reset your password. Use the code below — valid for <strong>10 minutes</strong>.
            </p>
            <table width="100%" cellpadding="0" cellspacing="0">
              <tr>
                <td align="center" style="padding:10px 0 28px;">
                  <div style="display:inline-block;background:#fff8e1;border:2px dashed #f9a825;border-radius:12px;padding:22px 52px;">
                    <p style="margin:0 0 8px;color:#888;font-size:12px;text-transform:uppercase;letter-spacing:1.5px;">Reset Code</p>
                    <p style="margin:0;font-size:44px;font-weight:800;letter-spacing:14px;color:#0a3b2f;">{$pin}</p>
                  </div>
                </td>
              </tr>
            </table>
            <p style="color:#e53935;font-size:13px;font-weight:600;">⚠ If you did not request a password reset, please ignore this email or contact support immediately.</p>
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

// ════════════════════════════════════════════════════════════════
//  Determine current step
// ════════════════════════════════════════════════════════════════
$step   = $_SESSION['fp_step'] ?? 1;
$error  = '';
$notice = '';

// ── STEP 1 POST: verify identity ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_identity') {
    $account_number = trim($_POST['account_number'] ?? '');
    $user_email     = trim($_POST['user_email']     ?? '');

    if (empty($account_number) || empty($user_email)) {
        $error = "Both fields are required.";
    } elseif (!filter_var($user_email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        try {
            $pdo  = getDB($DB_HOST, $DB_NAME, $DB_USER, $DB_PASS);
            $stmt = $pdo->prepare(
                "SELECT id, full_name FROM users WHERE account_number = ? AND user_email = ? LIMIT 1"
            );
            $stmt->execute([$account_number, $user_email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $pin = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                unset($_SESSION['fp_step'], $_SESSION['fp_user_id'],
                      $_SESSION['fp_email'], $_SESSION['fp_name'],
                      $_SESSION['fp_pin'],   $_SESSION['fp_pin_expires'],
                      $_SESSION['fp_attempts']);
                $_SESSION['fp_step']        = 2;
                $_SESSION['fp_user_id']     = $user['id'];
                $_SESSION['fp_email']       = $user_email;
                $_SESSION['fp_name']        = $user['full_name'];
                $_SESSION['fp_pin']         = $pin;
                $_SESSION['fp_pin_expires'] = time() + 600;
                $_SESSION['fp_attempts']    = 0;

                $result = sendResetPin(
                    $user_email, $user['full_name'], $pin,
                    $MAIL_HOST, $MAIL_PORT, $MAIL_USERNAME,
                    $MAIL_PASSWORD, $MAIL_FROM, $MAIL_FROM_NAME
                );

                if ($result === true || $result === '') {
                    $step   = 2;
                    $notice = "A 6-digit reset code was sent to <strong>" . htmlspecialchars($user_email) . "</strong>.";
                } else {
                    $error = "Could not send email: " . $result;
                    unset($_SESSION['fp_step']);
                }
            } else {
                $error = "No account found matching that email and account number.";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// ── STEP 2 POST: verify OTP ─────────────────────────────────────
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_otp') {
    $entered = trim(implode('', $_POST['otp_digit'] ?? []));

    if ($step !== 2) {
        $error = "Session expired. Please start again.";
        $step  = 1;
        unset($_SESSION['fp_step']);
    } elseif (time() > ($_SESSION['fp_pin_expires'] ?? 0)) {
        $error = "Your code has expired. Please start over.";
        $step  = 1;
        unset($_SESSION['fp_step'], $_SESSION['fp_pin'], $_SESSION['fp_pin_expires'], $_SESSION['fp_attempts']);
    } else {
        $_SESSION['fp_attempts']++;
        if ($_SESSION['fp_attempts'] > 5) {
            $error = "Too many incorrect attempts. Please start over.";
            $step  = 1;
            unset($_SESSION['fp_step'], $_SESSION['fp_pin'], $_SESSION['fp_pin_expires'], $_SESSION['fp_attempts']);
        } elseif ($entered === $_SESSION['fp_pin']) {
            $_SESSION['fp_step'] = 3;
            $step                = 3;
            unset($_SESSION['fp_pin']);
        } else {
            $remaining = 5 - $_SESSION['fp_attempts'];
            $error = "Incorrect code. {$remaining} attempt(s) remaining.";
        }
    }
}

// ── STEP 2 POST: resend OTP ──────────────────────────────────────
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'resend_otp') {
    if (($step === 2) && !empty($_SESSION['fp_email'])) {
        $pin = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $_SESSION['fp_pin']         = $pin;
        $_SESSION['fp_pin_expires'] = time() + 600;
        $_SESSION['fp_attempts']    = 0;

        $result = sendResetPin(
            $_SESSION['fp_email'], $_SESSION['fp_name'], $pin,
            $MAIL_HOST, $MAIL_PORT, $MAIL_USERNAME,
            $MAIL_PASSWORD, $MAIL_FROM, $MAIL_FROM_NAME
        );

        $notice = ($result === true || $result === '')
            ? "A new code was sent to <strong>" . htmlspecialchars($_SESSION['fp_email']) . "</strong>."
            : "Failed to resend: " . $result;
    }
}

// ── STEP 3 POST: change password ────────────────────────────────
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $password         = $_POST['password']         ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($step !== 3 || empty($_SESSION['fp_user_id'])) {
        $error = "Session expired. Please start again.";
        $step  = 1;
        unset($_SESSION['fp_step']);
    } else {
        $pw_errors = [];
        if (strlen($password) < 8)                      $pw_errors[] = "at least 8 characters";
        if (!preg_match('/[A-Z]/', $password))           $pw_errors[] = "one uppercase letter";
        if (!preg_match('/[0-9]/', $password))           $pw_errors[] = "one number";
        if (!preg_match('/[^a-zA-Z0-9]/', $password))   $pw_errors[] = "one special character";
        if ($password !== $confirm_password)             $pw_errors[] = "passwords must match";

        if (!empty($pw_errors)) {
            $error = "Password requires: " . implode(', ', $pw_errors) . ".";
        } else {
            try {
                $pdo  = getDB($DB_HOST, $DB_NAME, $DB_USER, $DB_PASS);
                $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $stmt->execute([
                    password_hash($password, PASSWORD_BCRYPT),
                    $_SESSION['fp_user_id']
                ]);
                foreach (array_keys($_SESSION) as $k) {
                    if (str_starts_with($k, 'fp_')) unset($_SESSION[$k]);
                }
                $_SESSION['fp_step'] = 4;
                $step = 4;
            } catch (PDOException $e) {
                $error = "Database error: " . $e->getMessage();
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $step = $_SESSION['fp_step'] ?? 1;
}

if (isset($_GET['restart'])) {
    foreach (array_keys($_SESSION) as $k) {
        if (str_starts_with($k, 'fp_')) unset($_SESSION[$k]);
    }
    header('Location: forgotpass.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Forgot Password – Evergreen Trust and Savings</title>

  <!-- Bootstrap 5 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

  <style>
    :root {
      --eg-dark:    #0a3b2f;
      --eg-mid:     #1a6b5a;
      --eg-light:   #e8f5e9;
      --eg-accent:  #43a047;
      --eg-warn:    #f9a825;
      --eg-danger:  #e53935;
      --eg-bg:      #f4f8f6;
      --eg-card-bg: #ffffff;
      --eg-text:    #2d4a3e;
      --eg-muted:   #6c8a7e;
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

    /* ── Page background decoration ── */
    .page-wrapper {
      flex: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 2.5rem 1rem 3rem;
      background:
        radial-gradient(ellipse 60% 40% at 10% 0%, rgba(10,59,47,0.08) 0%, transparent 70%),
        radial-gradient(ellipse 50% 35% at 90% 100%, rgba(67,160,71,0.06) 0%, transparent 70%),
        var(--eg-bg);
    }

    /* ── Stepper ── */
    .stepper-wrap {
      width: 100%;
      max-width: 460px;
      margin-bottom: 1.75rem;
    }
    .stepper {
      display: flex;
      align-items: flex-start;
      justify-content: center;
      position: relative;
    }
    .stepper::before {
      content: '';
      position: absolute;
      top: 17px;
      left: calc(50% - 120px);
      width: 240px;
      height: 2px;
      background: #d0ddd8;
      z-index: 0;
    }
    .step-item {
      flex: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      position: relative;
      z-index: 1;
      max-width: 120px;
    }
    .step-bubble {
      width: 36px; height: 36px;
      border-radius: 50%;
      background: #dce8e3;
      color: #8fa89f;
      display: flex; align-items: center; justify-content: center;
      font-weight: 700; font-size: 0.85rem;
      border: 2.5px solid #dce8e3;
      transition: all 0.3s ease;
    }
    .step-item.active .step-bubble {
      background: var(--eg-dark);
      color: #fff;
      border-color: var(--eg-dark);
      box-shadow: 0 0 0 4px rgba(10,59,47,0.15);
    }
    .step-item.done .step-bubble {
      background: var(--eg-accent);
      color: #fff;
      border-color: var(--eg-accent);
    }
    .step-label {
      font-size: 0.68rem;
      margin-top: 0.4rem;
      color: #8fa89f;
      font-weight: 600;
      text-align: center;
      letter-spacing: 0.3px;
    }
    .step-item.active .step-label { color: var(--eg-dark); }
    .step-item.done  .step-label  { color: var(--eg-accent); }

    /* ── Card ── */
    .eg-card {
      width: 100%;
      max-width: 460px;
      background: var(--eg-card-bg);
      border-radius: 16px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.04), 0 8px 32px rgba(10,59,47,0.10);
      padding: 2.25rem 2rem;
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
    }
    .card-title {
      font-family: 'DM Serif Display', serif;
      font-size: 1.6rem;
      color: var(--eg-dark);
      margin-bottom: 0.4rem;
    }
    .card-subtitle {
      font-size: 0.875rem;
      color: var(--eg-muted);
      line-height: 1.6;
      margin-bottom: 1.75rem;
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

    /* ── Form elements ── */
    .form-label {
      font-weight: 600;
      font-size: 0.825rem;
      color: var(--eg-text);
      margin-bottom: 0.4rem;
      letter-spacing: 0.2px;
    }
    .form-control {
      border: 1.5px solid #d0ddd8;
      border-radius: 10px;
      padding: 0.7rem 0.9rem;
      font-size: 0.925rem;
      color: var(--eg-text);
      background: #fafcfb;
      transition: border-color 0.2s, box-shadow 0.2s;
    }
    .form-control:focus {
      border-color: var(--eg-dark);
      box-shadow: 0 0 0 3px rgba(10,59,47,0.12);
      background: #fff;
      outline: none;
    }
    .input-group .form-control { border-right: none; border-radius: 10px 0 0 10px; }
    .input-group .btn-eye {
      border: 1.5px solid #d0ddd8;
      border-left: none;
      border-radius: 0 10px 10px 0;
      background: #fafcfb;
      color: var(--eg-muted);
      padding: 0 0.85rem;
      transition: color 0.2s, background 0.2s;
    }
    .input-group .btn-eye:hover { color: var(--eg-dark); background: #f0f4f2; }
    .input-group:focus-within .btn-eye {
      border-color: var(--eg-dark);
      box-shadow: 0 0 0 3px rgba(10,59,47,0.12);
      box-shadow: inset 0 0 0 0;
    }

    /* ── OTP inputs ── */
    .otp-row {
      display: flex;
      gap: 0.6rem;
      justify-content: center;
      margin: 0.75rem 0 1.5rem;
    }
    .otp-digit {
      width: 50px; height: 58px;
      border: 2px solid #d0ddd8;
      border-radius: 12px;
      text-align: center;
      font-size: 1.6rem;
      font-weight: 700;
      color: var(--eg-dark);
      background: #fafcfb;
      transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
      caret-color: var(--eg-dark);
    }
    .otp-digit:focus {
      outline: none;
      border-color: var(--eg-dark);
      box-shadow: 0 0 0 3px rgba(10,59,47,0.13);
      background: #fff;
    }
    @media (max-width: 400px) {
      .otp-digit { width: 42px; height: 50px; font-size: 1.3rem; }
      .otp-row   { gap: 0.4rem; }
    }

    /* ── Password strength ── */
    .strength-track {
      height: 5px;
      background: #e0e8e4;
      border-radius: 99px;
      overflow: hidden;
      margin-top: 0.5rem;
    }
    .strength-fill {
      height: 100%;
      width: 0;
      border-radius: 99px;
      transition: width 0.35s ease, background-color 0.35s ease;
    }
    .strength-label {
      font-size: 0.75rem;
      font-weight: 700;
      margin-top: 0.3rem;
      min-height: 1rem;
    }
    .strength-rules {
      display: flex;
      flex-wrap: wrap;
      gap: 0.35rem;
      margin-top: 0.5rem;
    }
    .rule {
      font-size: 0.7rem;
      padding: 0.2rem 0.6rem;
      border-radius: 99px;
      background: #edf2ef;
      color: #8fa89f;
      font-weight: 600;
      transition: all 0.2s;
    }
    .rule.met { background: #e0f5e1; color: #2e7d32; }

    .confirm-feedback {
      font-size: 0.75rem;
      font-weight: 600;
      margin-top: 0.35rem;
      min-height: 1rem;
    }
    .confirm-feedback.match    { color: #2e7d32; }
    .confirm-feedback.mismatch { color: var(--eg-danger); }
    #confirm_password.match    { border-color: var(--eg-accent); background: #f5fdf5; }
    #confirm_password.mismatch { border-color: var(--eg-danger); background: #fff8f8; }

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
      margin-top: 0.25rem;
    }
    .btn-eg-primary:hover {
      background: #082e24;
      box-shadow: 0 4px 16px rgba(10,59,47,0.28);
      transform: translateY(-1px);
    }
    .btn-eg-primary:active { transform: translateY(0); }

    .btn-eg-generate {
      padding: 0.7rem 0.9rem;
      background: #e8f5e9;
      color: var(--eg-dark);
      border: 1.5px solid #a5d6a7;
      border-radius: 10px;
      font-size: 0.8rem;
      font-weight: 700;
      cursor: pointer;
      white-space: nowrap;
      flex-shrink: 0;
      transition: background 0.2s;
    }
    .btn-eg-generate:hover { background: #c8e6c9; }

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
    .btn-link-eg:hover { color: #082e24; }

    /* ── Bottom links ── */
    .bottom-links {
      text-align: center;
      margin-top: 1.25rem;
      font-size: 0.825rem;
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

    /* ── Timer ── */
    .timer-text {
      font-size: 0.78rem;
      color: var(--eg-muted);
      text-align: center;
      margin-top: 0.75rem;
    }
    #countdown { font-weight: 700; color: var(--eg-dark); }

    /* ── Success ── */
    .success-icon-wrap {
      width: 80px; height: 80px;
      background: linear-gradient(135deg, var(--eg-dark), var(--eg-mid));
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto 1.25rem;
      box-shadow: 0 8px 24px rgba(10,59,47,0.28);
      animation: popIn 0.5s cubic-bezier(0.34,1.56,0.64,1);
    }
    @keyframes popIn {
      from { opacity: 0; transform: scale(0.5); }
      to   { opacity: 1; transform: scale(1); }
    }
    .success-icon-wrap i { font-size: 2.2rem; color: #fff; }

    #copy-toast {
      font-size: 0.72rem;
      color: #2e7d32;
      margin-top: 0.3rem;
      display: none;
    }

    /* ── Divider ── */
    .eg-divider {
      border: none;
      border-top: 1px solid #e8f0ec;
      margin: 1.25rem 0;
    }

    /* ── Responsive tweaks ── */
    @media (max-width: 480px) {
      .eg-card { padding: 1.75rem 1.25rem; border-radius: 12px; }
      .card-title { font-size: 1.35rem; }
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

  <?php if ($step < 4): ?>
  <!-- ── Stepper ── -->
  <div class="stepper-wrap">
    <div class="stepper">
      <?php
        $labels = ['Verify Identity', 'Enter Code', 'New Password'];
        foreach ($labels as $i => $label):
          $n   = $i + 1;
          $cls = $n < $step ? 'done' : ($n === $step ? 'active' : '');
          $icon = $n < $step
            ? '<i class="bi bi-check-lg" style="font-size:1rem;"></i>'
            : $n;
      ?>
        <div class="step-item <?= $cls ?>">
          <div class="step-bubble"><?= $icon ?></div>
          <div class="step-label"><?= $label ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── Card ── -->
  <div class="eg-card">

    <?php if ($step === 1): ?>
    <!-- ══ STEP 1 – Verify Identity ══ -->
    <div class="card-eyebrow">Account Recovery</div>
    <div class="card-title">Forgot Password?</div>
    <p class="card-subtitle">Enter your registered bank account number and email address to receive a reset code.</p>

    <?php if ($error): ?>
      <div class="eg-alert eg-alert-error">
        <i class="bi bi-exclamation-circle-fill"></i>
        <span><?= htmlspecialchars($error) ?></span>
      </div>
    <?php endif; ?>
    <?php if ($notice): ?>
      <div class="eg-alert eg-alert-success">
        <i class="bi bi-check-circle-fill"></i>
        <span><?= $notice ?></span>
      </div>
    <?php endif; ?>

    <form method="POST" action="forgotpass.php">
      <input type="hidden" name="action" value="verify_identity">

      <div class="mb-3">
        <label for="account_number" class="form-label">Bank Account Number</label>
        <div class="input-group">
          <span class="input-group-text bg-white border-end-0" style="border:1.5px solid #d0ddd8;border-right:none;border-radius:10px 0 0 10px;">
            <i class="bi bi-credit-card text-muted"></i>
          </span>
          <input type="text" class="form-control border-start-0" id="account_number" name="account_number"
                 placeholder="e.g. 1234567890"
                 inputmode="numeric" maxlength="20"
                 style="border-left:none;border-radius:0 10px 10px 0;"
                 value="<?= htmlspecialchars($_POST['account_number'] ?? '') ?>" required>
        </div>
      </div>

      <div class="mb-3">
        <label for="user_email" class="form-label">Registered Email Address</label>
        <div class="input-group">
          <span class="input-group-text bg-white border-end-0" style="border:1.5px solid #d0ddd8;border-right:none;border-radius:10px 0 0 10px;">
            <i class="bi bi-envelope text-muted"></i>
          </span>
          <input type="email" class="form-control border-start-0" id="user_email" name="user_email"
                 placeholder="you@example.com"
                 style="border-left:none;border-radius:0 10px 10px 0;"
                 value="<?= htmlspecialchars($_POST['user_email'] ?? '') ?>" required>
        </div>
      </div>

      <button type="submit" class="btn-eg-primary mt-2">
        <i class="bi bi-send-fill me-2"></i>Send Reset Code
      </button>
    </form>

    <hr class="eg-divider">
    <div class="bottom-links">
      <span>Remembered your password? <a href="login.php">Sign in</a></span>
    </div>

    <?php elseif ($step === 2): ?>
    <!-- ══ STEP 2 – OTP Entry ══ -->
    <div class="card-eyebrow">Verification</div>
    <div class="card-title">Check Your Inbox</div>
    <p class="card-subtitle">
      We sent a 6-digit code to<br>
      <strong style="color:var(--eg-dark);"><?= htmlspecialchars($_SESSION['fp_email'] ?? '') ?></strong>
    </p>

    <?php if ($error): ?>
      <div class="eg-alert eg-alert-error">
        <i class="bi bi-exclamation-circle-fill"></i>
        <span><?= htmlspecialchars($error) ?></span>
      </div>
    <?php endif; ?>
    <?php if ($notice): ?>
      <div class="eg-alert eg-alert-success">
        <i class="bi bi-check-circle-fill"></i>
        <span><?= $notice ?></span>
      </div>
    <?php endif; ?>

    <form method="POST" action="forgotpass.php" id="otp-form">
      <input type="hidden" name="action" value="verify_otp">
      <div class="otp-row">
        <?php for ($i = 0; $i < 6; $i++): ?>
          <input type="text" class="otp-digit" name="otp_digit[]"
                 maxlength="1" inputmode="numeric" pattern="\d"
                 autocomplete="off" required>
        <?php endfor; ?>
      </div>
      <button type="submit" class="btn-eg-primary">
        <i class="bi bi-shield-check me-2"></i>Verify Code
      </button>
    </form>

    <div class="timer-text mt-3">
      <i class="bi bi-clock me-1"></i>Code expires in <span id="countdown">10:00</span>
    </div>

    <hr class="eg-divider">
    <div class="bottom-links">
      <span>
        Didn't receive it?&nbsp;
        <form method="POST" action="forgotpass.php" style="display:inline;">
          <input type="hidden" name="action" value="resend_otp">
          <button type="submit" class="btn-link-eg">Resend code</button>
        </form>
      </span>
      <span><a href="forgotpass.php?restart=1"><i class="bi bi-arrow-left me-1"></i>Start over</a></span>
    </div>

    <?php elseif ($step === 3): ?>
    <!-- ══ STEP 3 – New Password ══ -->
    <div class="card-eyebrow">Almost There</div>
    <div class="card-title">Set New Password</div>
    <p class="card-subtitle">Choose a strong, unique password to secure your account.</p>

    <?php if ($error): ?>
      <div class="eg-alert eg-alert-error">
        <i class="bi bi-exclamation-circle-fill"></i>
        <span><?= htmlspecialchars($error) ?></span>
      </div>
    <?php endif; ?>

    <form method="POST" action="forgotpass.php">
      <input type="hidden" name="action" value="change_password">

      <div class="mb-3">
        <label class="form-label">New Password</label>
        <div class="d-flex gap-2 align-items-start">
          <div class="flex-grow-1">
            <div class="input-group">
              <input type="password" class="form-control" id="password" name="password"
                     placeholder="Create a strong password"
                     oninput="checkStrength(this.value); checkConfirm()" required>
              <button type="button" class="btn-eye" onclick="toggleVis('password',this)">
                <i class="bi bi-eye"></i>
              </button>
            </div>
            <div class="strength-track"><div class="strength-fill" id="strength-bar"></div></div>
            <div class="strength-label" id="strength-label"></div>
            <div class="strength-rules">
              <span class="rule" id="rule-len">8+ chars</span>
              <span class="rule" id="rule-upper">Uppercase</span>
              <span class="rule" id="rule-num">Number</span>
              <span class="rule" id="rule-special">Special char</span>
            </div>
            <div id="copy-toast"><i class="bi bi-clipboard-check me-1"></i>Password copied!</div>
          </div>
          <button type="button" class="btn-eg-generate mt-1" onclick="generatePassword()">
            <i class="bi bi-lightning-fill me-1"></i>Generate
          </button>
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label">Confirm New Password</label>
        <div class="input-group">
          <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                 placeholder="Re-enter your password"
                 oninput="checkConfirm()" required>
          <button type="button" class="btn-eye" onclick="toggleVis('confirm_password',this)">
            <i class="bi bi-eye"></i>
          </button>
        </div>
        <div class="confirm-feedback" id="confirm-feedback"></div>
      </div>

      <button type="submit" class="btn-eg-primary">
        <i class="bi bi-lock-fill me-2"></i>Save New Password
      </button>
    </form>

    <?php elseif ($step === 4): ?>
    <!-- ══ STEP 4 – Success ══ -->
    <div class="text-center">
      <div class="success-icon-wrap">
        <i class="bi bi-check-lg"></i>
      </div>
      <div class="card-title text-center">Password Updated!</div>
      <p class="card-subtitle" style="margin-bottom:1.75rem;">
        Your password has been reset successfully.<br>You can now log in with your new credentials.
      </p>
      <a href="login.php" class="btn-eg-primary" style="display:block;text-decoration:none;text-align:center;">
        <i class="bi bi-box-arrow-in-right me-2"></i>Go to Login
      </a>
      <div class="timer-text mt-3">
        Redirecting in <span id="redir-count" style="font-weight:700;color:var(--eg-dark);">5</span>s…
      </div>
    </div>

    <?php endif; ?>
  </div><!-- /.eg-card -->
</div><!-- /.page-wrapper -->

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
/* ── OTP: auto-advance, backspace, paste ── */
(function () {
  const digits = document.querySelectorAll('.otp-digit');
  if (!digits.length) return;
  digits.forEach((el, i) => {
    el.addEventListener('input', e => {
      const val = e.target.value.replace(/\D/g, '');
      e.target.value = val.slice(-1);
      if (val && i < digits.length - 1) digits[i + 1].focus();
    });
    el.addEventListener('keydown', e => {
      if (e.key === 'Backspace' && !el.value && i > 0) digits[i - 1].focus();
    });
  });
  if (digits[0]) {
    digits[0].addEventListener('paste', e => {
      e.preventDefault();
      const pasted = (e.clipboardData || window.clipboardData)
        .getData('text').replace(/\D/g, '').slice(0, 6);
      pasted.split('').forEach((ch, idx) => { if (digits[idx]) digits[idx].value = ch; });
      const next = [...digits].findIndex(d => !d.value);
      (digits[next] || digits[digits.length - 1]).focus();
    });
  }
})();

/* ── OTP countdown ── */
(function () {
  const el = document.getElementById('countdown');
  if (!el) return;
  const expires = <?= json_encode($_SESSION['fp_pin_expires'] ?? (time() + 600)) ?>;
  function tick() {
    const left = expires - Math.floor(Date.now() / 1000);
    if (left <= 0) { el.textContent = 'Expired'; el.style.color = 'var(--eg-danger)'; return; }
    const m = String(Math.floor(left / 60)).padStart(2, '0');
    const s = String(left % 60).padStart(2, '0');
    el.textContent = m + ':' + s;
    setTimeout(tick, 1000);
  }
  tick();
})();

/* ── Redirect countdown ── */
(function () {
  const el = document.getElementById('redir-count');
  if (!el) return;
  let n = 5;
  const t = setInterval(() => {
    n--; el.textContent = n;
    if (n <= 0) { clearInterval(t); location.href = 'login.php'; }
  }, 1000);
})();

/* ── Password strength ── */
function checkStrength(val) {
  const bar = document.getElementById('strength-bar');
  const lbl = document.getElementById('strength-label');
  if (!bar) return;
  const r = {
    len:     val.length >= 8,
    upper:   /[A-Z]/.test(val),
    num:     /[0-9]/.test(val),
    special: /[^a-zA-Z0-9]/.test(val)
  };
  ['len','upper','num','special'].forEach(k =>
    document.getElementById('rule-' + k)?.classList.toggle('met', r[k])
  );
  const s = Object.values(r).filter(Boolean).length;
  const L = [
    {w:'0%',  c:'#e0e8e4', t:'',          tc:'#999'},
    {w:'25%', c:'#e53935', t:'Weak',       tc:'#e53935'},
    {w:'50%', c:'#fb8c00', t:'Fair',       tc:'#fb8c00'},
    {w:'75%', c:'#fdd835', t:'Good',       tc:'#f9a825'},
    {w:'100%',c:'#43a047', t:'Strong ✓',   tc:'#2e7d32'}
  ];
  const lv = val.length === 0 ? L[0] : L[s];
  bar.style.width = lv.w;
  bar.style.backgroundColor = lv.c;
  lbl.textContent = lv.t;
  lbl.style.color = lv.tc;
}

/* ── Confirm match ── */
function checkConfirm() {
  const p = document.getElementById('password');
  const c = document.getElementById('confirm_password');
  const f = document.getElementById('confirm-feedback');
  if (!c || !c.value) {
    c?.classList.remove('match','mismatch');
    if (f) f.textContent = '';
    return;
  }
  const ok = c.value === p.value;
  c.classList.toggle('match', ok);
  c.classList.toggle('mismatch', !ok);
  f.className = 'confirm-feedback ' + (ok ? 'match' : 'mismatch');
  f.textContent = ok ? '✓ Passwords match' : '✗ Passwords do not match';
}

/* ── Toggle visibility ── */
function toggleVis(id, btn) {
  const f = document.getElementById(id);
  f.type = f.type === 'password' ? 'text' : 'password';
  const icon = btn.querySelector('i');
  if (icon) icon.className = f.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
}

/* ── Generate password ── */
function generatePassword() {
  const U = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
        Lo = 'abcdefghijklmnopqrstuvwxyz',
        N = '0123456789',
        S = '!@#$%^&*()_+-=[]{}|;:,.<>?',
        A = U + Lo + N + S;
  const rnd = s => s[Math.floor(Math.random() * s.length)];
  let p = [rnd(U), rnd(Lo), rnd(N), rnd(S)];
  for (let i = 4; i < 14; i++) p.push(rnd(A));
  p = p.sort(() => Math.random() - .5).join('');
  ['password','confirm_password'].forEach(id => {
    const f = document.getElementById(id);
    if (f) { f.value = p; f.type = 'text'; }
  });
  checkStrength(p); checkConfirm();
  navigator.clipboard?.writeText(p).then(() => {
    const t = document.getElementById('copy-toast');
    if (t) { t.style.display = 'block'; setTimeout(() => t.style.display = 'none', 3000); }
  });
}
</script>
</body>
</html>