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

// ── Redirect if no pending officer registration in session ───────
if (empty($_SESSION['pending_officer'])) {
    header('Location: add_officer.php');
    exit;
}

$reg          = $_SESSION['pending_officer'];
$error        = '';
$info         = '';
$show_success = false;

// ── Resend PIN ───────────────────────────────────────────────────
if (isset($_POST['resend'])) {
    $pin = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $_SESSION['pending_officer']['pin']         = $pin;
    $_SESSION['pending_officer']['pin_expires'] = time() + 600;
    $_SESSION['pending_officer']['attempts']    = 0;
    $reg = $_SESSION['pending_officer'];

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
        $mail->SMTPOptions = ['ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true,
        ]];
        $mail->Timeout = 30;
        $mail->setFrom($MAIL_FROM, $MAIL_FROM_NAME);
        $mail->addAddress($reg['officer_email'], $reg['first_name']);
        $mail->isHTML(true);
        $mail->Subject = 'Your New Evergreen Officer Verification Code';
        $mail->Body    = getVerifyEmailBody($reg['first_name'], $pin);
        $mail->AltBody = "Hello {$reg['first_name']},\n\nYour new verification code is: {$pin}\n\nExpires in 10 minutes.";
        $mail->send();
        $info = "A new verification code was sent to " . htmlspecialchars($reg['officer_email']) . ".";
    } catch (Exception $e) {
        $error = "Could not resend: " . $mail->ErrorInfo;
    }
}

// ── Verify PIN ───────────────────────────────────────────────────
if (isset($_POST['verify'])) {
    // Accept both combined hidden input and individual digit fields
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
        header('Location: add_officer.php?error=toomany');
        exit;
    }

    if (time() > $reg['pin_expires']) {
        $error = "Your code has expired. Please request a new one below.";
    } elseif ($entered !== $reg['pin']) {
        $_SESSION['pending_officer']['attempts']++;
        $reg['attempts']++;
        $left  = 5 - $reg['attempts'];
        $error = "Incorrect code. {$left} attempt(s) remaining.";
    } else {
        // ── PIN correct → insert officer into DB and send welcome email ──
        $dbhost = 'localhost'; $dbname = 'loandb'; $dbuser = 'root'; $dbpass = '';
        try {
            $pdo = new PDO(
                "mysql:host=$dbhost;dbname=$dbname;charset=utf8mb4",
                $dbuser, $dbpass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            // Final duplicate email guard
            $chk = $pdo->prepare("SELECT id FROM officers WHERE officer_email = ?");
            $chk->execute([$reg['officer_email']]);
            if ($chk->fetch()) {
                $error = "An officer with that email already exists. Please go back and use a different email.";
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO officers
                        (employee_number, first_name, middle_name, surname, full_name,
                         address, province_id, province_name, municipality_id, municipality_name,
                         barangay_id, barangay_name, officer_email, contact_number, birthday,
                         role, password_hash, status, created_at)
                    VALUES
                        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active', NOW())
                ");
                $stmt->execute([
                    $reg['employee_number'],
                    $reg['first_name'],
                    $reg['middle_name'] ?? null,
                    $reg['surname'],
                    $reg['full_name'],
                    $reg['address'],
                    $reg['province_id'],
                    $reg['province_name'],
                    $reg['municipality_id'],
                    $reg['municipality_name'],
                    $reg['barangay_id'],
                    $reg['barangay_name'],
                    $reg['officer_email'],
                    $reg['contact_number'],
                    $reg['birthday'],
                    $reg['role'],
                    $reg['password_hash'],
                ]);

                // Send welcome email with credentials (plain-text password stored in session for this purpose)
                sendOfficerWelcomeEmail(
                    $reg['officer_email'],
                    $reg['first_name'],
                    $reg['employee_number'],
                    $reg['plain_password'],
                    $MAIL_HOST, $MAIL_PORT,
                    $MAIL_USERNAME, $MAIL_PASSWORD,
                    $MAIL_FROM, $MAIL_FROM_NAME
                );

                $displayName = $reg['first_name'];
                session_destroy();
                $show_success = true;
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// ── Email helpers ────────────────────────────────────────────────
function getVerifyEmailBody(string $name, string $pin): string {
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
            <p style="color:#a8d5b5;margin:6px 0 0;font-size:13px;">Trust and Savings Bank — Staff Portal</p>
          </td>
        </tr>
        <tr>
          <td style="padding:36px 40px;">
            <p style="color:#2d4a3e;font-size:16px;margin:0 0 12px;">Hello, <strong>{$name}</strong> 👋</p>
            <p style="color:#555;font-size:14px;line-height:1.7;margin:0 0 24px;">
              Your officer account is almost ready. Enter the 6-digit code below to verify your email address.
              This code is valid for <strong>10 minutes</strong>.
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
            <p style="color:#888;font-size:13px;">Never share this code with anyone. If you did not request this, please contact your administrator.</p>
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

function sendOfficerWelcomeEmail(
    string $toEmail, string $toName, string $employeeNumber, string $tempPassword,
    string $host, int $port, string $user, string $pass,
    string $from, string $fromName
): void {
    $mail = new PHPMailer(true);
    try {
        $mail->SMTPDebug   = SMTP::DEBUG_OFF;
        $mail->isSMTP();
        $mail->SMTPKeepAlive = false;
        $mail->Host        = $host;
        $mail->SMTPAuth    = true;
        $mail->Username    = $user;
        $mail->Password    = $pass;
        $mail->SMTPSecure  = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port        = $port;
        $mail->SMTPOptions = ['ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true,
        ]];
        $mail->Timeout = 60;
        $mail->setFrom($from, $fromName);
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = 'Your Evergreen Officer Account Has Been Created';
        $mail->Body    = getWelcomeEmailBody($toName, $employeeNumber, $tempPassword);
        $mail->AltBody = "Hello {$toName},\n\nYour officer account has been created.\nEmployee No: {$employeeNumber}\nTemp Password: {$tempPassword}\n\nPlease log in and change your password immediately.";
        $mail->send();
    } catch (Exception $e) {
        // Silently fail — account is already created
    }
}

function getWelcomeEmailBody(string $name, string $empNum, string $tempPassword): string {
    return <<<HTML
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"/></head>
<body style="margin:0;padding:0;background:#f4f4f4;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;padding:40px 0;">
    <tr><td align="center">
      <table width="520" cellpadding="0" cellspacing="0"
             style="background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,.10);">
        <tr>
          <td style="background:#0a3b2f;padding:28px 32px;text-align:center;">
            <h1 style="color:#fff;margin:0;font-size:22px;">🌿 EVERGREEN</h1>
            <p style="color:#a8d5b5;margin:6px 0 0;font-size:13px;">Trust and Savings Bank — Staff Portal</p>
          </td>
        </tr>
        <tr>
          <td style="padding:36px 40px;">
            <p style="color:#2d4a3e;font-size:16px;margin:0 0 12px;">Hello, <strong>{$name}</strong> 👋</p>
            <p style="color:#555;font-size:14px;line-height:1.7;margin:0 0 24px;">
              Your Loan Officer account at <strong>Evergreen Trust and Savings</strong> has been created.
              Below are your login credentials. Please log in and <strong>change your password immediately</strong>.
            </p>
            <table width="100%" cellpadding="0" cellspacing="0">
              <tr>
                <td align="center" style="padding:10px 0 28px;">
                  <div style="background:#f0faf3;border:2px dashed #43a047;border-radius:12px;padding:22px 36px;display:inline-block;">
                    <p style="margin:0 0 16px;color:#888;font-size:12px;text-transform:uppercase;letter-spacing:1.5px;text-align:center;">Your Credentials</p>
                    <table>
                      <tr>
                        <td style="color:#555;font-size:13px;padding-right:12px;">Employee No.</td>
                        <td style="font-size:18px;font-weight:800;letter-spacing:4px;color:#0a3b2f;">{$empNum}</td>
                      </tr>
                      <tr><td colspan="2" style="height:10px;"></td></tr>
                      <tr>
                        <td style="color:#555;font-size:13px;padding-right:12px;">Temp Password</td>
                        <td style="font-size:18px;font-weight:800;letter-spacing:3px;color:#c62828;">{$tempPassword}</td>
                      </tr>
                    </table>
                  </div>
                </td>
              </tr>
            </table>
            <p style="color:#888;font-size:13px;">Never share these credentials. Change your password on first login.</p>
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
</body></html>
HTML;
}

function maskEmail(string $email): string {
    [$local, $domain] = explode('@', $email, 2);
    return mb_substr($local, 0, 1) . str_repeat('*', max(3, mb_strlen($local) - 1)) . '@' . $domain;
}

$maskedEmail  = maskEmail($reg['officer_email']);
$secondsLeft  = max(0, $reg['pin_expires'] - time());
$expired      = ($secondsLeft === 0 && !$show_success);
$attemptsDone = (int)$reg['attempts'];
$displayName  = $reg['first_name'] ?? ($reg['full_name'] ?? 'Officer');
$employeeNum  = $reg['employee_number'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Verify Officer Email – Evergreen Trust and Savings</title>
  <link rel="icon" type="image/png" href="pictures/logo.png"/>

  <!-- Bootstrap 5 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>

  <style>
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
      --eg-error:  #c0392b;
      --eg-gold:   #c8a84b;
      --eg-gold-l: #e8c96b;
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      background: var(--eg-bg);
      font-family: 'DM Sans', sans-serif;
      color: var(--eg-text);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    /* ── Top Bar ── */
    .top-bar {
      background: var(--eg-dark);
      border-bottom: 1px solid var(--eg-deep);
      padding: 14px 28px;
      display: flex; align-items: center; justify-content: space-between;
      position: sticky; top: 0; z-index: 100;
      box-shadow: 0 2px 12px rgba(10,59,47,0.22);
    }
    .top-bar-left { display: flex; align-items: center; gap: 12px; }
    .top-bar-logo-icon {
      width: 36px; height: 36px;
      background: var(--eg-gold);
      border-radius: 8px;
      display: flex; align-items: center; justify-content: center;
    }
    .top-bar-logo-icon img {
      height: 22px; width: auto;
      filter: brightness(0) saturate(100%) invert(10%) sepia(40%) saturate(800%) hue-rotate(105deg) brightness(40%);
    }
    .top-bar-name {
      font-family: 'Playfair Display', serif; font-size: 17px; font-weight: 700;
      color: #fff; letter-spacing: .5px;
    }
    .top-bar-sub { font-size: 11px; color: rgba(255,255,255,0.65); letter-spacing: .3px; }
    .top-bar-back {
      display: flex; align-items: center; gap: 7px;
      color: rgba(255,255,255,0.75); font-size: 13px; font-weight: 500;
      text-decoration: none; padding: 7px 14px;
      border: 1px solid rgba(255,255,255,0.18);
      border-radius: 8px; transition: all .2s;
    }
    .top-bar-back:hover { color: #fff; background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.35); }

    /* ── Page wrapper ── */
    .page-wrapper {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 2.5rem 1rem 3rem;
      background:
        radial-gradient(ellipse 60% 40% at 10% 0%, rgba(10,59,47,0.08) 0%, transparent 70%),
        radial-gradient(ellipse 50% 35% at 90% 100%, rgba(67,160,71,0.06) 0%, transparent 70%),
        var(--eg-bg);
    }

    /* ── Two-column layout ── */
    .verify-layout {
      display: flex;
      gap: 0;
      width: 100%;
      max-width: 900px;
      background: #fff;
      border-radius: 20px;
      overflow: hidden;
      box-shadow: 0 4px 8px rgba(0,0,0,0.04), 0 16px 48px rgba(10,59,47,0.12);
      animation: fadeUp 0.4s ease both;
    }
    @keyframes fadeUp {
      from { opacity:0; transform:translateY(20px); }
      to   { opacity:1; transform:translateY(0); }
    }

    /* ── Left panel (form) ── */
    .verify-form-panel {
      flex: 1;
      padding: 3rem 2.5rem;
      display: flex;
      flex-direction: column;
      min-width: 0;
    }

    /* ── Right panel (info) ── */
    .verify-info-panel {
      width: 320px;
      flex-shrink: 0;
      background: linear-gradient(150deg, #020f0b 0%, #051a10 35%, #0a3b2f 100%);
      padding: 3rem 2rem;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      position: relative;
      overflow: hidden;
    }
    .verify-info-panel::before {
      content: ''; position: absolute;
      width: 300px; height: 300px; border-radius: 50%;
      border: 50px solid rgba(255,255,255,0.04);
      top: -80px; right: -80px;
    }
    .verify-info-panel::after {
      content: ''; position: absolute;
      width: 200px; height: 200px; border-radius: 50%;
      border: 40px solid rgba(255,255,255,0.04);
      bottom: -50px; left: -50px;
    }
    .info-content { position: relative; z-index: 1; text-align: center; width: 100%; }

    .shield-icon-wrap {
      width: 72px; height: 72px;
      background: rgba(255,255,255,0.1);
      border-radius: 20px;
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto 1.5rem;
      border: 1px solid rgba(255,255,255,0.15);
      animation: pulseGlow 3s ease infinite;
    }
    @keyframes pulseGlow {
      0%,100% { box-shadow: 0 0 0 0 rgba(67,160,71,0.3); }
      50%      { box-shadow: 0 0 0 12px rgba(67,160,71,0); }
    }
    .shield-icon-wrap i { font-size: 32px; color: var(--eg-gold-l); }

    .info-title {
      font-family: 'Playfair Display', serif;
      color: #fff; font-size: 20px; font-weight: 700;
      margin-bottom: 0.5rem;
    }
    .info-subtitle {
      color: rgba(255,255,255,0.55); font-size: 13px; line-height: 1.6;
      margin-bottom: 2rem;
    }

    /* Officer ID card */
    .officer-id-card {
      background: rgba(255,255,255,0.06);
      border: 1px solid rgba(255,255,255,0.12);
      border-radius: 14px;
      padding: 18px;
      margin-bottom: 1.5rem;
      width: 100%;
    }
    .id-row {
      display: flex; align-items: center; gap: 10px;
      margin-bottom: 12px;
    }
    .id-avatar {
      width: 44px; height: 44px;
      background: rgba(255,255,255,0.1);
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      border: 1px solid rgba(255,255,255,0.15); flex-shrink: 0;
    }
    .id-avatar i { color: rgba(255,255,255,0.65); font-size: 20px; }
    .id-name { color: #fff; font-size: 14px; font-weight: 600; }
    .id-role { color: var(--eg-gold-l); font-size: 10px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; }
    .id-emp-row {
      background: rgba(0,0,0,0.2);
      border-radius: 8px; padding: 8px 12px;
      display: flex; align-items: center; justify-content: space-between;
    }
    .id-emp-label { color: rgba(255,255,255,0.45); font-size: 9px; text-transform: uppercase; letter-spacing: 1px; }
    .id-emp-value { color: #fff; font-family: 'Courier New', monospace; font-size: 13px; font-weight: 700; letter-spacing: 2px; }
    .id-strip { height: 4px; border-radius: 2px; margin-top: 12px;
      background: linear-gradient(90deg, var(--eg-gold) 0%, var(--eg-gold-l) 100%); }

    /* Steps */
    .steps-list { list-style: none; padding: 0; margin: 0; text-align: left; width: 100%; }
    .steps-list li {
      display: flex; align-items: flex-start; gap: 10px;
      color: rgba(255,255,255,0.55); font-size: 12px; line-height: 1.5;
      margin-bottom: 10px;
    }
    .step-num {
      width: 20px; height: 20px; border-radius: 50%; flex-shrink: 0;
      background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2);
      display: flex; align-items: center; justify-content: center;
      font-size: 10px; font-weight: 700; color: var(--eg-gold-l);
    }

    /* ── Form elements ── */
    .card-eyebrow {
      font-size: 0.7rem; font-weight: 700; letter-spacing: 1.5px;
      text-transform: uppercase; color: var(--eg-accent);
      margin-bottom: 0.35rem;
    }
    .card-title {
      font-family: 'Playfair Display', serif;
      font-size: 1.75rem; color: var(--eg-dark);
      margin-bottom: 0.5rem;
    }
    .card-subtitle {
      font-size: 0.875rem; color: var(--eg-muted);
      line-height: 1.7; margin-bottom: 1.5rem;
    }

    .email-badge {
      display: inline-flex; align-items: center; gap: 0.4rem;
      background: #f0faf3; border: 1px solid #a5d6a7;
      border-radius: 99px; padding: 0.35rem 0.85rem;
      font-size: 0.82rem; font-weight: 600; color: var(--eg-dark);
      margin-bottom: 1.5rem;
    }

    /* Alerts */
    .eg-alert {
      padding: 0.75rem 1rem; border-radius: 10px;
      font-size: 0.85rem; line-height: 1.5; margin-bottom: 1.25rem;
      display: flex; align-items: flex-start; gap: 0.5rem;
      animation: alertIn .3s ease both;
    }
    @keyframes alertIn {
      from { opacity:0; transform:translateY(-6px); }
      to   { opacity:1; transform:translateY(0); }
    }
    .eg-alert-error   { color: #b71c1c; background: #fff5f5; border: 1px solid #ffcdd2; }
    .eg-alert-success { color: #1b5e20; background: #f0faf3; border: 1px solid #a5d6a7; }
    .eg-alert i { font-size: 1rem; flex-shrink: 0; margin-top: 1px; }

    /* Attempts dots */
    .attempts-row {
      display: flex; gap: 0.45rem; margin-bottom: 1.25rem;
    }
    .attempt-dot {
      width: 10px; height: 10px; border-radius: 50%;
      background: #dce8e3; transition: background 0.2s;
    }
    .attempt-dot.used { background: var(--eg-error); }

    /* OTP inputs */
    .otp-row {
      display: flex; gap: 0.6rem; margin-bottom: 1.5rem;
    }
    .pin-digit {
      width: 56px; height: 64px;
      border: 2px solid var(--eg-border);
      border-radius: 12px; text-align: center;
      font-size: 1.75rem; font-weight: 700;
      color: var(--eg-dark); background: var(--eg-bg);
      transition: border-color .2s, box-shadow .2s, background .2s;
      caret-color: var(--eg-dark); flex: 1;
    }
    .pin-digit:focus {
      outline: none;
      border-color: var(--eg-dark);
      box-shadow: 0 0 0 3px rgba(10,59,47,0.13);
      background: #fff;
    }
    .pin-digit.filled  { border-color: var(--eg-accent); background: #f0faf3; }
    .pin-digit.error   { border-color: var(--eg-error); background: #fff8f8; animation: shake 0.35s ease; }
    @keyframes shake {
      0%,100% { transform: translateX(0); }
      25%     { transform: translateX(-5px); }
      75%     { transform: translateX(5px); }
    }

    /* Timer */
    .timer-badge {
      display: inline-flex; align-items: center; gap: 0.35rem;
      background: #f4f8f6; border: 1px solid var(--eg-border);
      border-radius: 99px; padding: 0.3rem 0.85rem;
      font-size: 0.78rem; font-weight: 600; color: var(--eg-muted);
      margin-bottom: 1.25rem;
    }
    .timer-badge #countdown { color: var(--eg-dark); font-weight: 700; }
    .timer-badge.expired { border-color: #ffcdd2; background: #fff5f5; }
    .timer-badge.expired #countdown { color: var(--eg-error); }

    /* Buttons */
    .btn-eg-primary {
      width: 100%; padding: 0.85rem 1rem;
      background: linear-gradient(135deg, var(--eg-deep) 0%, var(--eg-dark) 50%, var(--eg-mid) 100%);
      color: #fff; border: none; border-radius: 10px;
      font-family: 'DM Sans', sans-serif; font-size: 0.95rem; font-weight: 700;
      letter-spacing: 0.3px; cursor: pointer;
      transition: all 0.25s; margin-bottom: 0.75rem;
      box-shadow: 0 2px 8px rgba(10,59,47,0.18);
      display: flex; align-items: center; justify-content: center; gap: 8px;
    }
    .btn-eg-primary:hover:not(:disabled) {
      transform: translateY(-1px);
      box-shadow: 0 6px 20px rgba(10,59,47,0.28);
    }
    .btn-eg-primary:disabled {
      background: #b0bec5; cursor: not-allowed;
      transform: none; box-shadow: none;
    }

    .btn-link-eg {
      background: none; border: none;
      color: var(--eg-dark); font-weight: 700;
      font-family: 'DM Sans', sans-serif;
      font-size: 0.85rem; cursor: pointer;
      text-decoration: underline; padding: 0;
      transition: color .2s;
    }
    .btn-link-eg:hover:not(:disabled) { color: var(--eg-deep); }
    .btn-link-eg:disabled { color: #aaa; cursor: not-allowed; text-decoration: none; }

    .eg-divider { border: none; border-top: 1px solid #e8f0ec; margin: 1.25rem 0; }

    .resend-timer { font-size: 0.72rem; color: var(--eg-muted); margin-top: 0.25rem; }

    .bottom-links {
      text-align: center; margin-top: 0.75rem;
      font-size: 0.82rem; color: var(--eg-muted);
    }
    .bottom-links a { color: var(--eg-dark); text-decoration: none; font-weight: 700; }
    .bottom-links a:hover { text-decoration: underline; }

    /* ── Success modal ── */
    @keyframes fadeIn  { from{opacity:0;} to{opacity:1;} }
    @keyframes slideUp { from{opacity:0;transform:translateY(30px) scale(.9);} to{opacity:1;transform:translateY(0) scale(1);} }
    @keyframes draw    { to{stroke-dashoffset:0;} }
    @keyframes pulsing { 0%,100%{box-shadow:0 0 0 0 rgba(10,59,47,.4);} 50%{box-shadow:0 0 0 20px rgba(10,59,47,0);} }

    .modal-overlay {
      display: none; position: fixed; inset: 0;
      background: rgba(0,54,49,0.88);
      backdrop-filter: blur(8px);
      align-items: center; justify-content: center;
      z-index: 10000; animation: fadeIn 0.4s ease;
    }
    .modal-overlay.show { display: flex; }

    .modal-box {
      background: #fff; padding: 3rem 2.5rem;
      border-radius: 20px; box-shadow: 0 25px 80px rgba(0,0,0,0.4);
      max-width: 500px; width: 90%; text-align: center;
      animation: slideUp 0.5s cubic-bezier(0.34,1.56,0.64,1);
    }
    .modal-check-wrap {
      width: 96px; height: 96px;
      background: linear-gradient(135deg, var(--eg-dark) 0%, var(--eg-mid) 100%);
      border-radius: 50%; display: flex; align-items: center; justify-content: center;
      margin: 0 auto 1.75rem;
      box-shadow: 0 10px 30px rgba(10,59,47,0.3);
      animation: pulsing 2s ease 0.9s infinite;
    }
    .modal-check-wrap svg path {
      stroke-dasharray: 50; stroke-dashoffset: 50;
      animation: draw 0.5s ease 0.7s forwards;
    }
    .modal-box h3 {
      font-family: 'Playfair Display', serif;
      color: var(--eg-dark); font-size: 1.9rem; margin-bottom: 0.75rem;
    }
    .modal-box p { color: #666; font-size: 0.95rem; line-height: 1.65; margin-bottom: 1.25rem; }

    .modal-credentials {
      background: #f0faf3; border: 2px dashed #43a047;
      border-radius: 12px; padding: 20px 28px;
      margin-bottom: 1.25rem; text-align: left;
    }
    .modal-credentials .cred-label {
      font-size: 10px; text-transform: uppercase; letter-spacing: 1.5px;
      color: #888; margin-bottom: 12px; text-align: center;
    }
    .modal-credentials table { width: 100%; }
    .modal-credentials td { padding: 4px 0; }
    .modal-credentials .cred-key { color: #555; font-size: 12px; padding-right: 14px; white-space: nowrap; }
    .modal-credentials .cred-val { font-size: 16px; font-weight: 800; letter-spacing: 3px; color: var(--eg-dark); }
    .modal-credentials .cred-val.pw { color: #c62828; }

    .modal-note {
      background: #f0f9f8; border-left: 4px solid var(--eg-mid);
      padding: 0.9rem 1.1rem; border-radius: 8px;
      margin-bottom: 1.25rem; text-align: left;
    }
    .modal-note p { color: var(--eg-dark); font-size: 0.85rem; margin: 0; line-height: 1.6; }

    .modal-countdown { color: #999; font-size: 0.82rem; margin-bottom: 1.25rem; }
    .modal-countdown span { color: var(--eg-dark); font-weight: 700; }

    .btn-modal {
      background: linear-gradient(135deg, var(--eg-dark) 0%, var(--eg-mid) 100%);
      color: #fff; border: none; padding: 0.85rem 2.5rem;
      border-radius: 10px; font-size: 0.95rem; font-weight: 700;
      cursor: pointer; transition: all 0.3s;
      box-shadow: 0 4px 15px rgba(10,59,47,0.3); letter-spacing: 0.4px;
      font-family: 'DM Sans', sans-serif;
    }
    .btn-modal:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(10,59,47,0.4); }

    /* ── Responsive ── */
    @media (max-width: 768px) {
      .verify-info-panel { display: none; }
      .verify-form-panel { padding: 2rem 1.5rem; }
    }
    @media (max-width: 480px) {
      .pin-digit { height: 52px; font-size: 1.35rem; }
      .otp-row   { gap: 0.35rem; }
      .modal-box { padding: 2rem 1.25rem; }
    }
  </style>
</head>
<body>

<!-- ── Top Bar ── -->
<div class="top-bar">
  <div class="top-bar-left">
    <div class="top-bar-logo-icon">
      <img src="pictures/logo.png" alt="Evergreen Logo" />
    </div>
    <div>
      <div class="top-bar-name">EVERGREEN</div>
      <div class="top-bar-sub">Staff Management Portal</div>
    </div>
  </div>
  <a href="Employeedashboard.php" class="top-bar-back">
    <i class="bi bi-arrow-left"></i> Back to Dashboard
  </a>
</div>

<!-- ── Page Wrapper ── -->
<div class="page-wrapper">

  <?php if (!$show_success): ?>
  <div class="verify-layout">

    <!-- ── Left: Form ── -->
    <div class="verify-form-panel">

      <div class="card-eyebrow">Officer Email Verification</div>
      <div class="card-title">Verify Officer Email</div>
      <p class="card-subtitle">
        A 6-digit verification code was sent to the officer's email address.
        Enter it below to complete account creation.
      </p>

      <!-- Email badge -->
      <div>
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

      <form method="POST" action="employee_verify-email.php" id="verify-form">
        <input type="hidden" name="pin" id="pin-hidden">

        <div class="otp-row" id="pin-row">
          <input type="text" class="pin-digit" id="digit-1" name="d1" maxlength="1" inputmode="numeric" autocomplete="one-time-code" <?= $expired ? 'disabled' : '' ?>>
          <input type="text" class="pin-digit" id="digit-2" name="d2" maxlength="1" inputmode="numeric" <?= $expired ? 'disabled' : '' ?>>
          <input type="text" class="pin-digit" id="digit-3" name="d3" maxlength="1" inputmode="numeric" <?= $expired ? 'disabled' : '' ?>>
          <input type="text" class="pin-digit" id="digit-4" name="d4" maxlength="1" inputmode="numeric" <?= $expired ? 'disabled' : '' ?>>
          <input type="text" class="pin-digit" id="digit-5" name="d5" maxlength="1" inputmode="numeric" <?= $expired ? 'disabled' : '' ?>>
          <input type="text" class="pin-digit" id="digit-6" name="d6" maxlength="1" inputmode="numeric" <?= $expired ? 'disabled' : '' ?>>
        </div>

        <!-- Timer -->
        <div class="mb-3">
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
          <i class="bi bi-shield-check"></i>
          Verify &amp; Create Officer Account
        </button>
      </form>

      <hr class="eg-divider">

      <form method="POST" action="employee_verify-email.php" id="resend-form">
        <div class="text-center">
          <button type="submit" name="resend" class="btn-link-eg" id="btn-resend">
            <i class="bi bi-arrow-repeat me-1"></i>Didn't receive it? Resend code
          </button>
          <div class="resend-timer" id="resend-timer"></div>
        </div>
      </form>

      <div class="bottom-links mt-3">
        <span>Wrong details? <a href="add_officer.php"><i class="bi bi-arrow-left me-1"></i>Go back &amp; edit</a></span>
      </div>

    </div><!-- /.verify-form-panel -->

    <!-- ── Right: Info ── -->
    <div class="verify-info-panel">
      <div class="info-content">

        <div class="shield-icon-wrap">
          <i class="bi bi-person-badge-fill"></i>
        </div>
        <div class="info-title">Almost Done</div>
        <p class="info-subtitle">
          Verify this email to finalize the officer account setup.
        </p>

        <!-- Officer mini ID card -->
        <div class="officer-id-card">
          <div class="id-row">
            <div class="id-avatar"><i class="bi bi-person-fill"></i></div>
            <div>
              <div class="id-name"><?= htmlspecialchars($displayName) ?></div>
              <div class="id-role">Loan Officer</div>
            </div>
          </div>
          <div class="id-emp-row">
            <div>
              <div class="id-emp-label">Employee No.</div>
              <div class="id-emp-value"><?= htmlspecialchars($employeeNum) ?></div>
            </div>
            <i class="bi bi-shield-check" style="color:var(--eg-gold-l);font-size:18px;"></i>
          </div>
          <div class="id-strip"></div>
        </div>

        <!-- Steps -->
        <ul class="steps-list">
          <li>
            <div class="step-num">1</div>
            <span>Check the officer's inbox for the verification email from Evergreen.</span>
          </li>
          <li>
            <div class="step-num">2</div>
            <span>Enter the 6-digit code shown in that email into the fields on the left.</span>
          </li>
          <li>
            <div class="step-num">3</div>
            <span>On success, the account is created and credentials are auto-emailed to the officer.</span>
          </li>
        </ul>

      </div>
    </div><!-- /.verify-info-panel -->

  </div><!-- /.verify-layout -->

  <?php else: ?>
  <!-- Slim placeholder while modal animates -->
  <div style="background:#fff;border-radius:16px;padding:3rem 2rem;text-align:center;
              box-shadow:0 4px 24px rgba(10,59,47,0.12);animation:fadeUp .4s ease both;">
    <div style="font-size:2.5rem;margin-bottom:0.75rem;">✅</div>
    <p style="color:var(--eg-dark);font-weight:600;font-family:'DM Sans',sans-serif;">
      Officer account created! Redirecting…
    </p>
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
    <h3>Officer Account Created!</h3>
    <p>
      <strong style="color:var(--eg-dark);"><?= htmlspecialchars($displayName ?? 'The officer') ?></strong>
      has been successfully registered as a Loan Officer at
      <strong style="color:var(--eg-dark);">Evergreen Trust and Savings</strong>.
    </p>
    <div class="modal-note">
      <p>
        <strong><i class="bi bi-envelope-fill me-1" style="color:var(--eg-accent);"></i>Credentials Sent</strong><br>
        <span style="color:#666;">
          The officer's employee number and temporary password have been sent to their email address.
          They should log in and change their password immediately.
        </span>
      </p>
    </div>
    <p class="modal-countdown">
      Redirecting to dashboard in <span id="modal-countdown">5</span> seconds…
    </p>
    <button class="btn-modal" onclick="window.location.href='Employeedashboard.php'">
      <i class="bi bi-speedometer2 me-2"></i>Go to Dashboard
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
    const pasted = (e.clipboardData || window.clipboardData)
      .getData('text').replace(/\D/g, '').slice(0, 6);
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
// Shake + clear digits on wrong code
digits.forEach(d => { d.classList.add('error'); d.value = ''; d.classList.remove('filled'); });
hidden.value = '';
setTimeout(() => { digits.forEach(d => d.classList.remove('error')); digits[0].focus(); }, 400);
<?php endif; ?>

// ── Countdown timer ──
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

// ── Resend cooldown ──
const btnResend   = document.getElementById('btn-resend');
const resendTimer = document.getElementById('resend-timer');

function startResendCooldown(secs = 30) {
  let left = secs;
  btnResend.disabled = true;
  resendTimer.textContent = `You can request a new code in ${left}s`;
  const cd = setInterval(() => {
    left--;
    resendTimer.textContent = `You can request a new code in ${left}s`;
    if (left <= 0) {
      clearInterval(cd);
      btnResend.disabled = false;
      resendTimer.textContent = '';
    }
  }, 1000);
}

startResendCooldown(30);
document.getElementById('resend-form').addEventListener('submit', () => startResendCooldown(30));

<?php else: ?>
// ── Success redirect countdown ──
let countdown = 5;
const cdEl    = document.getElementById('modal-countdown');
const cdTick  = setInterval(() => {
  countdown--;
  if (cdEl) cdEl.textContent = countdown;
  if (countdown <= 0) {
    clearInterval(cdTick);
    window.location.href = 'Employeedashboard.php';
  }
}, 1000);
<?php endif; ?>
</script>
</body>
</html>