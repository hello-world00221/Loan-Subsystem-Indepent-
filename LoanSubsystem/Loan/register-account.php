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

// ── DB connection (shared) ───────────────────────────────────────
$dbhost = 'localhost'; $dbname = 'loandb'; $dbuser = 'root'; $dbpass = '';
$pdo = null;
try {
    $pdo = new PDO(
        "mysql:host=$dbhost;dbname=$dbname;charset=utf8mb4",
        $dbuser, $dbpass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    if (isset($_GET['action'])) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'DB connection failed: ' . $e->getMessage()]);
        exit;
    }
    die(json_encode(['error' => 'DB connection failed: ' . $e->getMessage()]));
}

// ════════════════════════════════════════════════════════════════
//  AJAX endpoints — handled FIRST, before any page logic
// ════════════════════════════════════════════════════════════════
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    while (ob_get_level()) ob_end_clean();

    if ($_GET['action'] === 'get_municipalities') {
        $province_id = (int)($_GET['province_id'] ?? 0);
        if ($province_id <= 0) { echo json_encode([]); exit; }
        $stmt = $pdo->prepare(
            "SELECT id, municipality_name FROM municipalities
             WHERE province_id = ? ORDER BY municipality_name ASC"
        );
        $stmt->execute([$province_id]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    if ($_GET['action'] === 'get_barangays') {
        $municipality_id = (int)($_GET['municipality_id'] ?? 0);
        if ($municipality_id <= 0) { echo json_encode([]); exit; }
        $stmt = $pdo->prepare(
            "SELECT id, barangay_name FROM barangays
             WHERE municipality_id = ? ORDER BY barangay_name ASC"
        );
        $stmt->execute([$municipality_id]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    echo json_encode([]);
    exit;
}

// ── Fetch provinces for initial dropdown ────────────────────────
$provinces = [];
try {
    $provinces = $pdo->query(
        "SELECT id, province_name FROM provinces ORDER BY province_name ASC"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table may not exist yet – graceful fallback
}

// ── 11-char account number generator ────────────────────────────
function generateAccountNumber(): string {
    return 'EG' . str_pad((string)random_int(0, 999999999), 9, '0', STR_PAD_LEFT);
}
function getUniqueAccountNumber(PDO $pdo): string {
    do {
        $acct = generateAccountNumber();
        $chk  = $pdo->prepare("SELECT id FROM users WHERE account_number = ?");
        $chk->execute([$acct]);
    } while ($chk->fetch());
    return $acct;
}
$displayed_account = getUniqueAccountNumber($pdo);

// ── Email helper ─────────────────────────────────────────────────
function sendVerificationPin(
    string $toEmail, string $toName, string $pin,
    string $host, int $port, string $user, string $pass,
    string $from, string $fromName
): bool|string {
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
            'verify_peer'      => false,
            'verify_peer_name' => false,
            'allow_self_signed'=> true,
        ]];
        $mail->Timeout = 60;
        $mail->setFrom($from, $fromName);
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = 'Your Evergreen Verification Code';
        $mail->Body    = getEmailBody($toName, $pin);
        $mail->AltBody = "Hello, {$toName}!\n\nYour code: {$pin}\n\nExpires in 10 minutes.";
        $mail->send();
        return true;
    } catch (Exception $e) {
        if ($mail->ErrorInfo === '') return true;
        return $mail->ErrorInfo;
    }
}

function getEmailBody(string $name, string $pin): string {
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
            <p style="color:#a8d5b5;margin:6px 0 0;font-size:13px;">Trust and Savings Bank</p>
          </td>
        </tr>
        <tr>
          <td style="padding:36px 40px;">
            <p style="color:#2d4a3e;font-size:16px;margin:0 0 12px;">Hello, <strong>{$name}</strong> 👋</p>
            <p style="color:#555;font-size:14px;line-height:1.7;margin:0 0 24px;">
              Thank you for registering with <strong>Evergreen Trust and Savings</strong>.
              Enter the code below to complete setup. Valid for <strong>10 minutes</strong>.
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
</body></html>
HTML;
}

// ════════════════════════════════════════════════════════════════
//  POST handler
// ════════════════════════════════════════════════════════════════
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $first_name       = trim($_POST['first_name']       ?? '');
    $middle_name      = trim($_POST['middle_name']      ?? '');
    $surname          = trim($_POST['surname']          ?? '');
    $full_name        = trim("$first_name $middle_name $surname");
    $address          = trim($_POST['address']          ?? '');
    $province_id      = (int)($_POST['province_id']     ?? 0);
    $municipality_id  = (int)($_POST['municipality_id'] ?? 0);
    $barangay_id      = (int)($_POST['barangay_id']     ?? 0);
    $user_email       = trim($_POST['user_email']       ?? '');
    $contact_number   = trim($_POST['contact_number']   ?? '');
    $birthday         = trim($_POST['birthday']         ?? '');
    $password         = $_POST['password']              ?? '';
    $confirm_password = $_POST['confirm_password']      ?? '';
    $account_number   = trim($_POST['account_number']   ?? '');

    $province_name = $municipality_name = $barangay_name = '';
    if ($province_id > 0) {
        $s = $pdo->prepare("SELECT province_name FROM provinces WHERE id = ?");
        $s->execute([$province_id]);
        $r = $s->fetch(PDO::FETCH_ASSOC);
        $province_name = $r['province_name'] ?? '';
    }
    if ($municipality_id > 0) {
        $s = $pdo->prepare("SELECT municipality_name FROM municipalities WHERE id = ?");
        $s->execute([$municipality_id]);
        $r = $s->fetch(PDO::FETCH_ASSOC);
        $municipality_name = $r['municipality_name'] ?? '';
    }
    if ($barangay_id > 0) {
        $s = $pdo->prepare("SELECT barangay_name FROM barangays WHERE id = ?");
        $s->execute([$barangay_id]);
        $r = $s->fetch(PDO::FETCH_ASSOC);
        $barangay_name = $r['barangay_name'] ?? '';
    }

    if (empty($first_name))       $errors[] = "First name is required.";
    if (empty($surname))          $errors[] = "Surname is required.";
    if (empty($address))          $errors[] = "Street/House address is required.";
    if ($province_id === 0)       $errors[] = "Province is required.";
    if ($municipality_id === 0)   $errors[] = "Municipality/City is required.";
    if ($barangay_id === 0)       $errors[] = "Barangay is required.";
    if (empty($user_email) || !filter_var($user_email, FILTER_VALIDATE_EMAIL))
        $errors[] = "A valid email address is required.";
    if (empty($contact_number) || !preg_match('/^[0-9+\-\s()]{7,20}$/', $contact_number))
        $errors[] = "Please enter a valid contact number.";
    if (empty($birthday))         $errors[] = "Birthday is required.";
    if (strlen($password) < 8)    $errors[] = "Password must be at least 8 characters.";
    if (!preg_match('/[A-Z]/', $password))
        $errors[] = "Password must contain at least one uppercase letter.";
    if (!preg_match('/[0-9]/', $password))
        $errors[] = "Password must contain at least one number.";
    if (!preg_match('/[^a-zA-Z0-9]/', $password))
        $errors[] = "Password must contain at least one special character.";
    if ($password !== $confirm_password)
        $errors[] = "Passwords do not match.";
    if (empty($account_number))
        $errors[] = "Account number is missing. Please refresh the page.";

    if (empty($errors)) {
        try {
            $chkEmail = $pdo->prepare("SELECT id FROM users WHERE user_email = ?");
            $chkEmail->execute([$user_email]);
            if ($chkEmail->fetch()) $errors[] = "An account with that email already exists.";

            $chkAcct = $pdo->prepare("SELECT id FROM users WHERE account_number = ?");
            $chkAcct->execute([$account_number]);
            if ($chkAcct->fetch()) $account_number = getUniqueAccountNumber($pdo);

            if (empty($errors)) {
                $pin = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                unset($_SESSION['pending_reg']);

                $_SESSION['pending_reg'] = [
                    'first_name'        => $first_name,
                    'middle_name'       => $middle_name,
                    'surname'           => $surname,
                    'full_name'         => $full_name,
                    'address'           => $address,
                    'province_id'       => $province_id,
                    'province_name'     => $province_name,
                    'municipality_id'   => $municipality_id,
                    'municipality_name' => $municipality_name,
                    'barangay_id'       => $barangay_id,
                    'barangay_name'     => $barangay_name,
                    'user_email'        => $user_email,
                    'contact_number'    => $contact_number,
                    'birthday'          => $birthday,
                    'password_hash'     => password_hash($password, PASSWORD_BCRYPT),
                    'account_number'    => $account_number,
                    'pin'               => $pin,
                    'pin_expires'       => time() + 600,
                    'attempts'          => 0,
                ];

                $result = sendVerificationPin(
                    $user_email, $first_name, $pin,
                    $MAIL_HOST, $MAIL_PORT, $MAIL_USERNAME,
                    $MAIL_PASSWORD, $MAIL_FROM, $MAIL_FROM_NAME
                );

                if ($result === true || $result === '') {
                    session_write_close();
                    header('Location: verify-email.php');
                    exit;
                } else {
                    $errors[] = "Failed to send verification email: " . $result;
                }
            }
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

$errorsJson         = json_encode($errors);
$firstNameJson      = json_encode($_POST['first_name']       ?? '');
$middleNameJson     = json_encode($_POST['middle_name']      ?? '');
$surnameJson        = json_encode($_POST['surname']          ?? '');
$addressJson        = json_encode($_POST['address']          ?? '');
$provinceIdJson     = json_encode((int)($_POST['province_id']     ?? 0));
$municipalityIdJson = json_encode((int)($_POST['municipality_id'] ?? 0));
$barangayIdJson     = json_encode((int)($_POST['barangay_id']     ?? 0));
$emailJson          = json_encode($_POST['user_email']       ?? '');
$contactJson        = json_encode($_POST['contact_number']   ?? '');
$birthdayJson       = json_encode($_POST['birthday']         ?? '');
$accountNumberJson  = json_encode($_POST['account_number']   ?? $displayed_account);
$provincesJson      = json_encode($provinces);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Register – Evergreen Trust and Savings</title>
  <link rel="icon" type="image/png" href="pictures/logo.png" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"/>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>

  <style>
    :root {
      --eg-dark:    #0a3b2f;
      --eg-mid:     #1a6b5a;
      --eg-deep:    #082e24;
      --eg-accent:  #43a047;
      --eg-light:   #e8f5e9;
      --eg-bg:      #f4f8f6;
      --eg-cream:   #f4f8f6;
      --eg-text:    #2d4a3e;
      --eg-muted:   #6c8a7e;
      --eg-border:  #d4e6de;
      --eg-error:   #c0392b;
      --eg-err-bg:  #fdf0ef;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }

    html, body {
      height: 100%;
    }

    body {
      font-family: 'DM Sans', sans-serif;
      background: var(--eg-bg);
      /* FIXED: Use min-height + flex column so the page can grow beyond viewport */
      min-height: 100vh;
      min-height: 100dvh;
      display: flex;
      flex-direction: column;
      overflow-x: hidden;
      overflow-y: auto;
    }

    /* ── Top bar ── */
    .top-bar {
      background: var(--eg-dark);
      border-bottom: 1px solid var(--eg-deep);
      padding: 14px 28px; display: flex; align-items: center; gap: 12px;
      position: sticky; top: 0; z-index: 100;
      box-shadow: 0 2px 12px rgba(10,59,47,0.22);
      flex-shrink: 0;
    }
    .top-bar img { height: 36px; }
    .top-bar-name {
      font-family: 'Playfair Display', serif; font-size: 17px; font-weight: 700;
      color: #ffffff; letter-spacing: .5px;
    }
    .top-bar-sub { font-size: 11px; color: rgba(255,255,255,0.65); letter-spacing: .3px; }

    /* ── Layout ── */
    /* FIXED: flex:1 so page-body fills all remaining vertical space */
    .page-body {
      display: flex;
      flex: 1;
      min-height: 0; /* allow flex children to shrink/scroll properly */
    }

    .form-side {
      width: 540px;
      min-width: 320px;
      background: white;
      /* FIXED: overflow-y scroll on form-side so it scrolls independently on desktop */
      overflow-y: auto;
      padding: 30px 40px 48px;
      display: flex;
      flex-direction: column;
      border-right: 1px solid var(--eg-border);
      animation: slideIn 0.55s cubic-bezier(0.22,1,0.36,1) both;
    }
    @keyframes slideIn {
      from { opacity:0; transform:translateX(-24px); }
      to   { opacity:1; transform:translateX(0); }
    }
    .form-title {
      font-family: 'Playfair Display', serif; font-size: 26px; font-weight: 700;
      color: var(--eg-text); margin-bottom: 4px;
    }
    .form-subtitle { font-size: 13px; color: var(--eg-muted); margin-bottom: 20px; line-height: 1.5; }

    /* ── Account number card ── */
    .acct-card {
      background: #f0faf3; border: 1.5px dashed var(--eg-dark); border-radius: 12px;
      padding: 13px 16px; margin-bottom: 20px;
      display: flex; align-items: center; gap: 13px;
      animation: fadeIn .4s ease both;
    }
    .acct-icon {
      width: 40px; height: 40px; background: var(--eg-dark);
      border-radius: 10px; display: flex; align-items: center;
      justify-content: center; flex-shrink: 0;
    }
    .acct-icon i { color: white; font-size: 18px; }
    .acct-info { flex: 1; min-width: 0; }
    .acct-label {
      font-size: 10px; font-weight: 700; color: var(--eg-muted);
      letter-spacing: 1.2px; text-transform: uppercase; margin-bottom: 3px;
    }
    .acct-number {
      font-size: 19px; font-weight: 700; color: var(--eg-dark);
      letter-spacing: 2px; font-family: 'Courier New', monospace; word-break: break-all;
    }
    .acct-note { font-size: 10.5px; color: var(--eg-muted); margin-top: 2px; }
    .acct-badge {
      background: var(--eg-dark); color: white; font-size: 10px; font-weight: 600;
      padding: 3px 9px; border-radius: 20px; letter-spacing: .5px;
      white-space: nowrap; flex-shrink: 0;
    }

    /* ── Alert ── */
    .alert-eg {
      border-radius: 10px; font-size: 13px; padding: 12px 16px; margin-bottom: 16px;
      background: var(--eg-err-bg); border: 1px solid #f5c6c3; color: var(--eg-error);
      display: flex; gap: 10px; align-items: flex-start;
      animation: fadeIn .3s ease both;
    }
    @keyframes fadeIn {
      from { opacity:0; transform:translateY(-6px); }
      to   { opacity:1; transform:translateY(0); }
    }

    /* ── Section divider ── */
    .section-divider {
      font-size: 10.5px; font-weight: 700; color: var(--eg-muted);
      letter-spacing: 1.2px; text-transform: uppercase;
      display: flex; align-items: center; gap: 10px;
      margin: 14px 0 11px;
    }
    .section-divider::before, .section-divider::after {
      content:''; flex:1; height:1px; background:var(--eg-border);
    }

    /* ── Field grid ── */
    .row-fields { display: grid; gap: 12px; margin-bottom: 12px; }
    .row-fields.cols-1 { grid-template-columns: 1fr; }
    .row-fields.cols-2 { grid-template-columns: 1fr 1fr; }
    .row-fields.cols-3 { grid-template-columns: 1fr 1fr 1fr; }

    .field-wrap label {
      display: block; font-size: 11px; font-weight: 600; color: var(--eg-muted);
      letter-spacing: .8px; text-transform: uppercase; margin-bottom: 5px;
    }
    .field-wrap input,
    .field-wrap select {
      width: 100%; padding: 10px 14px;
      border: 1.5px solid var(--eg-border); border-radius: 8px;
      font-family: 'DM Sans', sans-serif; font-size: 14px; color: var(--eg-text);
      background: var(--eg-bg);
      transition: border-color .2s, background .2s, box-shadow .2s;
      outline: none; -webkit-appearance: none; appearance: none;
    }
    .field-wrap select {
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='none' stroke='%236c8a7e' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round' d='M1 1l5 5 5-5'/%3E%3C/svg%3E");
      background-repeat: no-repeat; background-position: right 12px center;
      padding-right: 34px; cursor: pointer;
    }
    .field-wrap input:focus,
    .field-wrap select:focus {
      border-color: var(--eg-dark); background: white;
      box-shadow: 0 0 0 3px rgba(10,59,47,0.13);
    }
    .field-wrap select:disabled { opacity:.5; cursor:not-allowed; background-color:#f0f0f0; }
    .field-wrap input.is-error,
    .field-wrap select.is-error { border-color: var(--eg-error) !important; }
    .field-wrap input::placeholder { color: #b0c4bc; }

    .select-wrap { position: relative; }
    .select-spinner {
      display: none; position: absolute; right: 32px; top: 50%;
      transform: translateY(-50%); width: 13px; height: 13px;
      border: 2px solid var(--eg-border); border-top-color: var(--eg-dark);
      border-radius: 50%; animation: spin .55s linear infinite; pointer-events: none;
    }
    .select-wrap.is-loading .select-spinner { display: block; }

    .input-pw-wrap { position: relative; }
    .input-pw-wrap input { padding-right: 44px; }
    .pw-eye {
      position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
      background: none; border: none; cursor: pointer; color: var(--eg-muted);
      font-size: 16px; line-height: 1; padding: 0; transition: color .2s;
    }
    .pw-eye:hover { color: var(--eg-dark); }

    .strength-bar-wrap { height:5px; background:#e8eeed; border-radius:4px; margin-top:8px; overflow:hidden; }
    .strength-bar { height:100%; width:0; border-radius:4px; transition:width .3s,background-color .3s; }
    .strength-txt { font-size:11px; font-weight:600; margin-top:4px; }
    .strength-rules { display:flex; flex-wrap:wrap; gap:5px; margin-top:6px; }
    .rule { font-size:10.5px; padding:2px 8px; border-radius:20px; background:#f0f0f0; color:#999; transition:all .2s; }
    .rule.met { background:#e8f5e9; color:#2e7d32; }

    .confirm-fb { font-size:11.5px; font-weight:600; margin-top:5px; min-height:16px; }
    .confirm-fb.match    { color:#2e7d32; }
    .confirm-fb.mismatch { color:var(--eg-error); }
    input.match    { border-color: var(--eg-accent) !important; }
    input.mismatch { border-color: var(--eg-error) !important; }

    /* ── Inline field validation remarks ── */
    .field-hint {
      display: flex; align-items: center; gap: 5px;
      font-size: 11.5px; font-weight: 600; margin-top: 5px;
      min-height: 17px; animation: hintIn .2s ease both;
    }
    @keyframes hintIn {
      from { opacity:0; transform:translateY(-4px); }
      to   { opacity:1; transform:translateY(0); }
    }
    .field-hint.hint-error { color: var(--eg-error); }
    .field-hint.hint-ok    { color: #2e7d32; }
    .field-hint i          { font-size: 12px; flex-shrink:0; }
    input.field-ok,
    select.field-ok  { border-color: var(--eg-accent) !important; background: #fafffe !important; }
    input.field-err,
    select.field-err { border-color: var(--eg-error) !important; }

    .terms-row { display:flex; align-items:flex-start; gap:10px; margin:14px 0 6px; }
    .terms-row input[type="checkbox"] {
      width:16px; height:16px; margin-top:2px; accent-color: var(--eg-dark); flex-shrink:0;
    }
    .terms-row label { font-size:12.5px; color:var(--eg-muted); line-height:1.5; }
    .terms-row label a { color: var(--eg-dark); font-weight:600; text-decoration:none; }
    .terms-row label a:hover { text-decoration:underline; }

    .btn-create {
      width:100%; padding:13px;
      background: linear-gradient(135deg, var(--eg-deep) 0%, var(--eg-dark) 50%, var(--eg-mid) 100%);
      color:white; border:none; border-radius:9px;
      font-family:'DM Sans',sans-serif; font-size:15px; font-weight:600;
      letter-spacing:.4px; cursor:pointer; transition:all .25s; margin-top:8px;
      display:flex; align-items:center; justify-content:center; gap:8px;
      position:relative; overflow:hidden;
      box-shadow: 0 2px 8px rgba(10,59,47,0.18);
    }
    .btn-create:hover { transform:translateY(-1px); box-shadow:0 8px 20px rgba(10,59,47,0.30); }
    .btn-create:disabled { opacity:.7; cursor:not-allowed; transform:none; }
    .btn-create .spinner {
      display:none; width:17px; height:17px;
      border:2px solid rgba(255,255,255,.4); border-top-color:white;
      border-radius:50%; animation:spin .6s linear infinite; position:absolute;
    }
    .btn-create.loading .btn-text { opacity:0; }
    .btn-create.loading .spinner  { display:block; }
    @keyframes spin { to { transform:rotate(360deg); } }

    .login-row { text-align:center; margin-top:18px; font-size:13px; color:var(--eg-muted); }
    .login-row a { color: var(--eg-dark); font-weight:600; text-decoration:none; }
    .login-row a:hover { text-decoration:underline; }

    /* ── Hero ── */
    .hero-side {
      flex:1;
      background: linear-gradient(145deg, #020f0b 0%, #051a10 40%, #0a3b2f 100%);
      display:flex; flex-direction:column; align-items:center; justify-content:center;
      padding:60px 48px; position:relative; overflow:hidden;
      /* FIXED: sticky so hero stays in view while form scrolls on desktop */
      position: sticky;
      top: 0;
      height: calc(100vh - 65px); /* 65px = top-bar approximate height */
      animation:fadeHero .8s ease both;
    }
    @keyframes fadeHero { from{opacity:0;} to{opacity:1;} }
    .hero-side::before {
      content:''; position:absolute; width:420px; height:420px; border-radius:50%;
      border:60px solid rgba(255,255,255,0.05); top:-100px; right:-80px;
    }
    .hero-side::after {
      content:''; position:absolute; width:280px; height:280px; border-radius:50%;
      border:50px solid rgba(255,255,255,0.05); bottom:-60px; left:-60px;
    }
    .hero-content { position:relative; z-index:1; text-align:center; }
    .hero-content h2 {
      font-family:'DM Sans',sans-serif; color:rgba(255,255,255,0.75);
      font-size:20px; font-weight:400; letter-spacing:1px; margin-bottom:10px;
    }
    .hero-content h1 {
      font-family:'Playfair Display',serif; color:white;
      font-size:46px; font-weight:700; line-height:1.1; margin-bottom:16px;
    }
    .hero-content p {
      color:rgba(255,255,255,0.60); font-size:15px; line-height:1.6;
      max-width:340px; margin:0 auto 40px;
    }
    .laptop-mockup {
      width:min(380px,90%); background:#020f0b; border-radius:16px 16px 8px 8px;
      padding:12px 12px 0; box-shadow:0 32px 64px rgba(0,0,0,0.5);
      position:relative; z-index:1;
    }
    .laptop-screen {
      background: linear-gradient(135deg, #020f0b, #0a3b2f);
      border-radius:8px 8px 0 0; aspect-ratio:16/9;
      display:flex; align-items:center; justify-content:center;
      overflow:hidden; position:relative;
    }
    .laptop-screen-inner {
      color:rgba(255,255,255,0.9); font-size:18px;
      font-family:'Playfair Display',serif; font-weight:700; letter-spacing:1px; text-align:center;
    }
    .laptop-screen-inner span {
      display:block; font-size:11px; font-family:'DM Sans',sans-serif;
      font-weight:400; color:rgba(255,255,255,0.5); letter-spacing:2px; margin-top:4px;
    }
    .laptop-screen::before {
      content:''; position:absolute; inset:0;
      background:radial-gradient(ellipse at 30% 30%,rgba(255,255,255,0.08) 0%,transparent 60%);
    }
    .laptop-base { background:#051a10; height:14px; border-radius:0 0 4px 4px; margin:0 -4px; }
    .laptop-foot { height:8px; background:#020f0b; border-radius:0 0 8px 8px; margin:0 40px; }

    /* ── Modal ── */
    .modal-backdrop-custom {
      position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:1050;
      display:flex; align-items:center; justify-content:center; padding:20px;
    }
    .modal-box {
      background:white; border-radius:14px; width:100%; max-width:640px;
      max-height:80vh; display:flex; flex-direction:column;
      box-shadow:0 24px 60px rgba(0,0,0,.25);
      animation:popIn .3s cubic-bezier(0.22,1,0.36,1) both;
    }
    @keyframes popIn { from{opacity:0;transform:scale(0.94);} to{opacity:1;transform:scale(1);} }
    .modal-head {
      padding:20px 24px; border-bottom:1px solid var(--eg-border);
      display:flex; align-items:center; justify-content:space-between;
    }
    .modal-head h3 {
      font-family:'Playfair Display',serif; font-size:18px; font-weight:700; color:var(--eg-text); margin:0;
    }
    .modal-close {
      background:none; border:none; font-size:22px; cursor:pointer;
      color:var(--eg-muted); line-height:1; padding:0; transition:color .2s;
    }
    .modal-close:hover { color:var(--eg-error); }
    .modal-body-scroll { overflow-y:auto; padding:24px; font-size:13.5px; color:#444; line-height:1.75; }
    .modal-body-scroll h4 { font-size:14px; color: var(--eg-dark); font-weight:700; margin:18px 0 6px; }
    .modal-foot {
      padding:16px 24px; border-top:1px solid var(--eg-border);
      display:flex; justify-content:flex-end; gap:10px;
    }
    .btn-modal-accept {
      padding:10px 24px; background: var(--eg-dark); color:white; border:none;
      border-radius:8px; font-family:'DM Sans',sans-serif; font-size:14px; font-weight:600;
      cursor:pointer; transition:background .2s;
    }
    .btn-modal-accept:hover { background: var(--eg-mid); }
    .btn-modal-cancel {
      padding:10px 20px; background:none; color:var(--eg-muted);
      border:1.5px solid var(--eg-border); border-radius:8px;
      font-family:'DM Sans',sans-serif; font-size:14px; font-weight:500;
      cursor:pointer; transition:all .2s;
    }
    .btn-modal-cancel:hover { border-color:var(--eg-muted); }

    @keyframes shake {
      0%,100%{transform:translateX(0)}
      20%{transform:translateX(-8px)}
      40%{transform:translateX(8px)}
      60%{transform:translateX(-5px)}
      80%{transform:translateX(5px)}
    }
    .shake { animation: shake 0.45s ease both; }

    /* ════════════════════════════════════════
       RESPONSIVE BREAKPOINTS
       ════════════════════════════════════════ */

    /* Hide hero on tablets and below; form takes full width */
    @media (max-width: 900px) {
      .hero-side { display: none; }
      .form-side {
        width: 100%;
        min-width: 0;
        border-right: none;
        /* On mobile, form-side should NOT be a fixed-height scroller —
           let the page body itself scroll naturally */
        overflow-y: visible;
        max-width: 600px;
        margin: 0 auto;
      }
      .page-body {
        justify-content: center;
        /* Allow page-body to be as tall as content needs */
        min-height: 0;
        flex: unset;
      }
    }

    /* Tablet portrait */
    @media (max-width: 700px) {
      .form-side {
        padding: 24px 28px 48px;
        max-width: 100%;
      }
      .top-bar { padding: 12px 20px; }
    }

    /* Mobile */
    @media (max-width: 540px) {
      .form-side { padding: 22px 16px 48px; }
      .row-fields.cols-2,
      .row-fields.cols-3 { grid-template-columns: 1fr; }
      .acct-number { font-size: 16px; letter-spacing: 1px; }
      .form-title { font-size: 22px; }
    }

    /* Very small */
    @media (max-width: 360px) {
      .form-side { padding: 18px 12px 40px; }
      .top-bar img { height: 28px; }
      .top-bar-name { font-size: 15px; }
    }
  </style>
</head>
<body>

<div class="top-bar">
  <img src="pictures/logo.png" alt="Evergreen Logo"/>
  <div>
    <div class="top-bar-name">EVERGREEN</div>
    <div class="top-bar-sub">Secure. Invest. Achieve.</div>
  </div>
</div>

<div class="page-body">
  <div class="form-side" id="root"></div>

  <div class="hero-side">
    <div class="hero-content">
      <h2>Welcome to</h2>
      <h1>EVERGREEN</h1>
      <p>Sign up to create an account and start managing your finances with confidence.</p>
      <div class="laptop-mockup">
        <div class="laptop-screen">
          <div class="laptop-screen-inner">🌿 EVERGREEN<span>TRUST AND SAVINGS</span></div>
        </div>
        <div class="laptop-base"></div>
      </div>
      <div class="laptop-foot"></div>
    </div>
  </div>
</div>

<script src="https://unpkg.com/react@18/umd/react.production.min.js"></script>
<script src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js"></script>
<script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>

<script type="text/babel">
const { useState, useEffect, useRef } = React;

const PHP_ERRORS      = <?= $errorsJson ?>;
const INIT_FIRSTNAME  = <?= $firstNameJson ?>;
const INIT_MIDDLENAME = <?= $middleNameJson ?>;
const INIT_SURNAME    = <?= $surnameJson ?>;
const INIT_ADDRESS    = <?= $addressJson ?>;
const INIT_PROVINCE   = <?= $provinceIdJson ?>;
const INIT_MUNI       = <?= $municipalityIdJson ?>;
const INIT_BARANGAY   = <?= $barangayIdJson ?>;
const INIT_EMAIL      = <?= $emailJson ?>;
const INIT_CONTACT    = <?= $contactJson ?>;
const INIT_BIRTHDAY   = <?= $birthdayJson ?>;
const INIT_ACCT       = <?= $accountNumberJson ?>;
const PROVINCES       = <?= $provincesJson ?>;

const TERMS_TEXT = `Last Updated: October 29, 2025 | Document Version 2.1

1. Introduction
These Terms and Conditions govern your use of Evergreen Trust and Savings services, including our website and banking services. By registering, you agree to be bound by these Terms.

2. Account Use and Responsibilities
You are responsible for all transactions and activities that occur in connection with your Account. You agree to provide accurate information, keep your credentials secure, notify us of any unauthorized use, and use your Account only for lawful purposes.

3. Electronic Services
By using our Electronic Services, you agree to comply with all security procedures, maintain up-to-date security software, not leave your device unattended while logged in, log off after each session, and notify us of any suspected unauthorized access.

4. Fees and Charges
You agree to pay all fees and charges associated with your Account as outlined in our Fee Schedule, including monthly maintenance fees, transaction fees, overdraft fees, and wire transfer fees.

5. Security and Data Protection
We implement various security measures to protect your Account. You are responsible for keeping your credentials secure, using secure internet connections, and notifying us of any suspected security breach.

6. Limitation of Liability
To the maximum extent permitted by law, we shall not be liable for any direct, indirect, incidental, or consequential damages arising from your use of our services.

7. Account Termination
We may terminate or suspend your Account at any time without prior notice if you breach these Terms. You may terminate your Account by contacting customer service.

8. Changes to Terms
We may modify these Terms at any time and will notify you of material changes via our website, email, or mobile application.

9. Governing Law
These Terms shall be governed by and construed in accordance with the laws of the jurisdiction in which Evergreen is incorporated.`;

function checkPwStrength(v) {
  return {
    len:     v.length >= 8,
    upper:   /[A-Z]/.test(v),
    num:     /[0-9]/.test(v),
    special: /[^a-zA-Z0-9]/.test(v),
  };
}

function StrengthBar({ password }) {
  const r = checkPwStrength(password);
  const score = Object.values(r).filter(Boolean).length;
  const levels = [
    {w:'0%',  c:'#e0e0e0', t:'',          tc:'#999'   },
    {w:'25%', c:'#e53935', t:'Weak',       tc:'#e53935'},
    {w:'50%', c:'#fb8c00', t:'Fair',       tc:'#fb8c00'},
    {w:'75%', c:'#fdd835', t:'Good',       tc:'#f9a825'},
    {w:'100%',c:'#43a047', t:'Strong ✓',  tc:'#2e7d32'},
  ];
  const lv = password.length === 0 ? levels[0] : levels[score];
  return (
    <>
      <div className="strength-bar-wrap">
        <div className="strength-bar" style={{width:lv.w, backgroundColor:lv.c}}/>
      </div>
      {password.length > 0 && <div className="strength-txt" style={{color:lv.tc}}>{lv.t}</div>}
      <div className="strength-rules">
        <span className={`rule${r.len     ? ' met' : ''}`}>8+ chars</span>
        <span className={`rule${r.upper   ? ' met' : ''}`}>Uppercase</span>
        <span className={`rule${r.num     ? ' met' : ''}`}>Number</span>
        <span className={`rule${r.special ? ' met' : ''}`}>Special char</span>
      </div>
    </>
  );
}

function FieldHint({ touched, valid, errorMsg, okMsg }) {
  if (!touched) return <div className="field-hint"/>;
  if (valid)    return <div className="field-hint hint-ok"><i className="bi bi-check-circle-fill"/>{okMsg || 'Looks good!'}</div>;
  return <div className="field-hint hint-error"><i className="bi bi-exclamation-circle-fill"/>{errorMsg}</div>;
}

function TermsModal({ onAccept, onClose }) {
  return (
    <div className="modal-backdrop-custom" onClick={onClose}>
      <div className="modal-box" onClick={e => e.stopPropagation()}>
        <div className="modal-head">
          <h3>Terms &amp; Conditions</h3>
          <button className="modal-close" onClick={onClose}>&times;</button>
        </div>
        <div className="modal-body-scroll">
          {TERMS_TEXT.trim().split('\n\n').map((para, i) => {
            if (/^\d+\./.test(para)) {
              const [head, ...rest] = para.split('\n');
              return <div key={i}><h4>{head}</h4><p>{rest.join(' ')}</p></div>;
            }
            return <p key={i} style={{marginBottom:6}}>{para}</p>;
          })}
        </div>
        <div className="modal-foot">
          <button className="btn-modal-cancel" onClick={onClose}>Close</button>
          <button className="btn-modal-accept" onClick={onAccept}>I Agree</button>
        </div>
      </div>
    </div>
  );
}

function validateEmail(v) { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v); }
function validateContact(v) { return /^[0-9+\-\s()]{7,20}$/.test(v); }

// ── CHANGE 2: Clamp birthday year to max 4 digits ────────────────
function clampBirthdayYear(value) {
  if (!value) return value;
  const parts = value.split('-');
  if (parts.length >= 1 && parts[0].length > 4) {
    parts[0] = parts[0].slice(0, 4);
    return parts.join('-');
  }
  return value;
}

function RegisterApp() {
  const [firstName,      setFirstName]      = useState(INIT_FIRSTNAME);
  const [middleName,     setMiddleName]     = useState(INIT_MIDDLENAME);
  const [surname,        setSurname]        = useState(INIT_SURNAME);
  const [address,        setAddress]        = useState(INIT_ADDRESS);
  const [provinceId,     setProvinceId]     = useState(INIT_PROVINCE);
  const [municipalityId, setMunicipalityId] = useState(INIT_MUNI);
  const [barangayId,     setBarangayId]     = useState(INIT_BARANGAY);
  const [municipalities, setMunicipalities] = useState([]);
  const [barangays,      setBarangays]      = useState([]);
  const [loadingMuni,    setLoadingMuni]    = useState(false);
  const [loadingBrgy,    setLoadingBrgy]    = useState(false);
  const [email,     setEmail]     = useState(INIT_EMAIL);
  const [contact,   setContact]   = useState(INIT_CONTACT);
  const [birthday,  setBirthday]  = useState(INIT_BIRTHDAY);
  const [password,  setPassword]  = useState('');
  const [confirmPw, setConfirmPw] = useState('');
  const [showPw,    setShowPw]    = useState(false);
  const [showCpw,   setShowCpw]   = useState(false);
  const [agreed,    setAgreed]    = useState(false);
  const [showModal, setShowModal] = useState(false);
  const [loading,   setLoading]   = useState(false);
  const [errors,    setErrors]    = useState(PHP_ERRORS || []);
  const [accountNumber] = useState(INIT_ACCT);
  const formRef = useRef(null);

  const [t, setT] = useState({
    firstName:false, surname:false, address:false,
    province:false, municipality:false, barangay:false,
    email:false, contact:false, birthday:false,
    password:false, confirmPw:false, agreed:false,
  });
  const touch    = (field) => setT(prev => ({...prev, [field]: true}));
  const touchAll = () => setT({
    firstName:true, surname:true, address:true,
    province:true, municipality:true, barangay:true,
    email:true, contact:true, birthday:true,
    password:true, confirmPw:true, agreed:true,
  });

  const v = {
    firstName:    firstName.trim().length > 0,
    surname:      surname.trim().length > 0,
    address:      address.trim().length > 0,
    province:     provinceId > 0,
    municipality: municipalityId > 0,
    barangay:     barangayId > 0,
    email:        validateEmail(email),
    contact:      validateContact(contact),
    birthday:     birthday.length > 0,
    password:     password.length >= 8 && /[A-Z]/.test(password) && /[0-9]/.test(password) && /[^a-zA-Z0-9]/.test(password),
    confirmPw:    confirmPw.length > 0 && confirmPw === password,
    agreed:       agreed,
  };

  useEffect(() => {
    if (errors.length && formRef.current) {
      formRef.current.classList.add('shake');
      const tm = setTimeout(() => formRef.current?.classList.remove('shake'), 500);
      return () => clearTimeout(tm);
    }
  }, [errors]);

  useEffect(() => {
    if (!provinceId) {
      setMunicipalities([]); setMunicipalityId(0);
      setBarangays([]);      setBarangayId(0);
      return;
    }
    setLoadingMuni(true);
    setMunicipalityId(0); setBarangays([]); setBarangayId(0);
    fetch(`register-account.php?action=get_municipalities&province_id=${provinceId}`)
      .then(r => r.json())
      .then(data => {
        setMunicipalities(data.map(m => ({ id: m.id, name: m.municipality_name })));
        setLoadingMuni(false);
      })
      .catch(() => { setMunicipalities([]); setLoadingMuni(false); });
  }, [provinceId]);

  useEffect(() => {
    if (!municipalityId) { setBarangays([]); setBarangayId(0); return; }
    setLoadingBrgy(true); setBarangayId(0);
    fetch(`register-account.php?action=get_barangays&municipality_id=${municipalityId}`)
      .then(r => {
        const contentType = r.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) throw new Error('Non-JSON response from server');
        return r.json();
      })
      .then(data => {
        setBarangays(data.map(b => ({ id: b.id, name: b.barangay_name })));
        setLoadingBrgy(false);
      })
      .catch(err => { console.error('Barangay fetch error:', err); setBarangays([]); setLoadingBrgy(false); });
  }, [municipalityId]);

  useEffect(() => {
    if (INIT_PROVINCE && INIT_MUNI && municipalities.length > 0) setMunicipalityId(INIT_MUNI);
  }, [municipalities]);
  useEffect(() => {
    if (INIT_MUNI && INIT_BARANGAY && barangays.length > 0) setBarangayId(INIT_BARANGAY);
  }, [barangays]);

  const confirmMatch = confirmPw.length > 0 && confirmPw === password;
  const confirmMiss  = confirmPw.length > 0 && confirmPw !== password;
  const clearErrors  = () => setErrors([]);

  function fieldCls(field, extra) {
    if (!t[field]) return extra || '';
    return (v[field] ? 'field-ok' : 'field-err') + (extra ? ' ' + extra : '');
  }

  // ── CHANGE 2: Birthday onChange — clamps year to 4 digits ────────
  function handleBirthdayChange(e) {
    const clamped = clampBirthdayYear(e.target.value);
    setBirthday(clamped);
    clearErrors();
    touch('birthday');
  }

  function handleSubmit(e) {
    touchAll();
    const errs = [];
    if (!v.firstName)    errs.push("First name is required.");
    if (!v.surname)      errs.push("Surname is required.");
    if (!v.address)      errs.push("Street/House address is required.");
    if (!v.province)     errs.push("Province is required.");
    if (!v.municipality) errs.push("Municipality/City is required.");
    if (!v.barangay)     errs.push("Barangay is required.");
    if (!v.email)        errs.push("A valid email address is required.");
    if (!v.contact)      errs.push("Please enter a valid contact number.");
    if (!v.birthday)     errs.push("Birthday is required.");
    if (!v.password)     errs.push("Password must be 8+ chars with uppercase, number & special character.");
    if (!v.confirmPw)    errs.push("Passwords do not match.");
    if (!v.agreed)       errs.push("You must agree to the Terms and Conditions.");
    if (errs.length) { e.preventDefault(); setErrors(errs); return; }
    setLoading(true);
  }

  return (
    <div ref={formRef} style={{flex:1, display:'flex', flexDirection:'column'}}>
      <div className="form-title">Create an account</div>
      <div className="form-subtitle">Fill in your details to get started with Evergreen.</div>

      <div className="acct-card">
        <div className="acct-icon"><i className="bi bi-credit-card-2-front"/></div>
        <div className="acct-info">
          <div className="acct-label">Your Account Number</div>
          <div className="acct-number">{accountNumber}</div>
          <div className="acct-note">Auto-generated · Cannot be changed</div>
        </div>
        <div className="acct-badge">AUTO</div>
      </div>

      {errors.length > 0 && (
        <div className="alert-eg">
          <i className="bi bi-exclamation-circle-fill" style={{marginTop:1}}/>
          <div>{errors.map((e, i) => <div key={i}>{e}</div>)}</div>
        </div>
      )}

      <form method="POST" action="register-account.php" onSubmit={handleSubmit} style={{flex:1}}>
        <input type="hidden" name="account_number" value={accountNumber}/>

        {/* ── PERSONAL INFO ── */}
        <div className="section-divider">Personal Information</div>
        <div className="row-fields cols-2">
          <div className="field-wrap">
            <label>First Name *</label>
            <input type="text" name="first_name" required placeholder="Juan"
              value={firstName}
              onChange={e => { setFirstName(e.target.value); clearErrors(); }}
              onBlur={() => touch('firstName')}
              className={fieldCls('firstName')}/>
            <FieldHint touched={t.firstName} valid={v.firstName}
              errorMsg="First name is required." okMsg="Looks good!"/>
          </div>
          <div className="field-wrap">
            <label>Middle Name <span style={{fontWeight:400, opacity:.6}}>(optional)</span></label>
            <input type="text" name="middle_name" placeholder="Andrade"
              value={middleName} onChange={e => setMiddleName(e.target.value)}/>
          </div>
        </div>
        <div className="row-fields cols-1">
          <div className="field-wrap">
            <label>Surname *</label>
            <input type="text" name="surname" required placeholder="Dela Cruz"
              value={surname}
              onChange={e => { setSurname(e.target.value); clearErrors(); }}
              onBlur={() => touch('surname')}
              className={fieldCls('surname')}/>
            <FieldHint touched={t.surname} valid={v.surname}
              errorMsg="Surname is required." okMsg="Looks good!"/>
          </div>
        </div>

        {/* ── ADDRESS ── CHANGE 1: Order is now Province → Municipality → Barangay → House/Street */}
        <div className="section-divider">Address</div>

        <div className="row-fields cols-2">
          <div className="field-wrap">
            <label>Province *</label>
            <div className="select-wrap">
              <select name="province_id" required value={provinceId}
                onChange={e => { setProvinceId(Number(e.target.value)); clearErrors(); touch('province'); }}
                onBlur={() => touch('province')}
                className={fieldCls('province')}>
                <option value={0}>— Select Province —</option>
                {PROVINCES.map(p => (
                  <option key={p.id} value={p.id}>{p.province_name}</option>
                ))}
              </select>
            </div>
            <FieldHint touched={t.province} valid={v.province}
              errorMsg="Please select a province." okMsg="Province selected!"/>
          </div>

          <div className="field-wrap">
            <label>Municipality / City *</label>
            <div className={`select-wrap${loadingMuni ? ' is-loading' : ''}`}>
              <select name="municipality_id" value={municipalityId}
                onChange={e => { setMunicipalityId(Number(e.target.value)); clearErrors(); touch('municipality'); }}
                onBlur={() => touch('municipality')}
                disabled={!provinceId || loadingMuni}
                className={fieldCls('municipality')}>
                <option value={0}>{loadingMuni ? 'Loading…' : provinceId ? '— Select Municipality —' : '— Select province first —'}</option>
                {municipalities.map(o => <option key={o.id} value={o.id}>{o.name}</option>)}
              </select>
              <div className="select-spinner"/>
            </div>
            <FieldHint touched={t.municipality} valid={v.municipality}
              errorMsg="Please select a municipality/city." okMsg="Municipality selected!"/>
          </div>
        </div>

        <div className="row-fields cols-1">
          <div className="field-wrap">
            <label>Barangay *</label>
            <div className={`select-wrap${loadingBrgy ? ' is-loading' : ''}`}>
              <select name="barangay_id" value={barangayId}
                onChange={e => { setBarangayId(Number(e.target.value)); clearErrors(); touch('barangay'); }}
                onBlur={() => touch('barangay')}
                disabled={!municipalityId || loadingBrgy}
                className={fieldCls('barangay')}>
                <option value={0}>{loadingBrgy ? 'Loading…' : municipalityId ? '— Select Barangay —' : '— Select municipality first —'}</option>
                {barangays.map(o => <option key={o.id} value={o.id}>{o.name}</option>)}
              </select>
              <div className="select-spinner"/>
            </div>
            <FieldHint touched={t.barangay} valid={v.barangay}
              errorMsg="Please select a barangay." okMsg="Barangay selected!"/>
          </div>
        </div>

        <div className="row-fields cols-1">
          <div className="field-wrap">
            <label>House No. / Street / Unit *</label>
            <input type="text" name="address" required placeholder="e.g. 29 Sinforosa St."
              value={address}
              onChange={e => { setAddress(e.target.value); clearErrors(); }}
              onBlur={() => touch('address')}
              className={fieldCls('address')}/>
            <FieldHint touched={t.address} valid={v.address}
              errorMsg="Street/house address is required." okMsg="Address noted!"/>
          </div>
        </div>

        {/* ── CONTACT ── */}
        <div className="section-divider">Contact Details</div>
        <div className="row-fields cols-2">
          <div className="field-wrap">
            <label>Email *</label>
            <input type="email" name="user_email" required placeholder="example@gmail.com"
              value={email}
              onChange={e => { setEmail(e.target.value); clearErrors(); }}
              onBlur={() => touch('email')}
              className={fieldCls('email')}/>
            <FieldHint touched={t.email} valid={v.email}
              errorMsg="Enter a valid email address." okMsg="Valid email!"/>
          </div>
          <div className="field-wrap">
            <label>Contact Number *</label>
            <input type="tel" name="contact_number" required placeholder="0927 379 2682"
              value={contact}
              onChange={e => { setContact(e.target.value); clearErrors(); }}
              onBlur={() => touch('contact')}
              className={fieldCls('contact')}/>
            <FieldHint touched={t.contact} valid={v.contact}
              errorMsg="Enter a valid contact number." okMsg="Valid number!"/>
          </div>
        </div>
        <div className="row-fields cols-1">
          <div className="field-wrap">
            <label>Birthday *</label>
            {/*
              CHANGE 2: min/max attributes enforce a 4-digit year standard (1900-01-01 to 9999-12-31).
              The onChange handler additionally clamps the year in React state via clampBirthdayYear(),
              preventing any year value longer than 4 digits from being stored or submitted.
            */}
            <input type="date" name="birthday" required
              min="1900-01-01"
              max="9999-12-31"
              value={birthday}
              onChange={handleBirthdayChange}
              onBlur={() => touch('birthday')}
              className={fieldCls('birthday')}/>
            <FieldHint touched={t.birthday} valid={v.birthday}
              errorMsg="Birthday is required." okMsg="Birthday recorded!"/>
          </div>
        </div>

        {/* ── SECURITY ── */}
        <div className="section-divider">Security</div>
        <div className="row-fields cols-2">
          <div className="field-wrap">
            <label>Password *</label>
            <div className="input-pw-wrap">
              <input type={showPw ? 'text' : 'password'} name="password" required placeholder="Password"
                value={password}
                onChange={e => { setPassword(e.target.value); clearErrors(); }}
                onBlur={() => touch('password')}
                className={fieldCls('password')}/>
              <button type="button" className="pw-eye" onClick={() => setShowPw(pv => !pv)}>
                <i className={`bi ${showPw ? 'bi-eye-slash' : 'bi-eye'}`}/>
              </button>
            </div>
            <StrengthBar password={password}/>
            {t.password && !v.password && (
              <div className="field-hint hint-error" style={{marginTop:6}}>
                <i className="bi bi-exclamation-circle-fill"/>Must be 8+ chars, uppercase, number &amp; special char.
              </div>
            )}
            {t.password && v.password && (
              <div className="field-hint hint-ok" style={{marginTop:6}}>
                <i className="bi bi-check-circle-fill"/>Strong password!
              </div>
            )}
          </div>
          <div className="field-wrap">
            <label>Confirm Password *</label>
            <div className="input-pw-wrap">
              <input type={showCpw ? 'text' : 'password'} name="confirm_password" required
                placeholder="Confirm Password"
                value={confirmPw}
                onChange={e => { setConfirmPw(e.target.value); clearErrors(); }}
                onBlur={() => touch('confirmPw')}
                className={confirmMatch ? 'field-ok' : confirmMiss ? 'field-err' : t.confirmPw && !v.confirmPw ? 'field-err' : ''}/>
              <button type="button" className="pw-eye" onClick={() => setShowCpw(pv => !pv)}>
                <i className={`bi ${showCpw ? 'bi-eye-slash' : 'bi-eye'}`}/>
              </button>
            </div>
            {confirmMatch && (
              <div className="field-hint hint-ok"><i className="bi bi-check-circle-fill"/>Passwords match!</div>
            )}
            {confirmMiss && (
              <div className="field-hint hint-error"><i className="bi bi-exclamation-circle-fill"/>Passwords do not match.</div>
            )}
            {!confirmMatch && !confirmMiss && t.confirmPw && confirmPw.length === 0 && (
              <div className="field-hint hint-error"><i className="bi bi-exclamation-circle-fill"/>Please confirm your password.</div>
            )}
          </div>
        </div>

        {/* ── TERMS ── */}
        <div className="terms-row">
          <input type="checkbox" id="agree" checked={agreed}
            onChange={e => { setAgreed(e.target.checked); touch('agreed'); }}
            onBlur={() => touch('agreed')}/>
          <label htmlFor="agree">
            I agree with{' '}
            <a href="#" onClick={e => { e.preventDefault(); setShowModal(true); }}>Terms and Conditions</a>
          </label>
        </div>
        {t.agreed && !v.agreed && (
          <div className="field-hint hint-error" style={{marginBottom:4}}>
            <i className="bi bi-exclamation-circle-fill"/>You must agree to the Terms and Conditions.
          </div>
        )}
        {t.agreed && v.agreed && (
          <div className="field-hint hint-ok" style={{marginBottom:4}}>
            <i className="bi bi-check-circle-fill"/>Terms accepted!
          </div>
        )}

        <button type="submit" className={`btn-create${loading ? ' loading' : ''}`} disabled={loading}>
          <span className="spinner"/>
          <span className="btn-text">{loading ? 'Creating account…' : 'CREATE ACCOUNT'}</span>
        </button>
      </form>

      <div className="login-row">
        Already have an account? <a href="login.php">Login here</a>
      </div>

      {showModal && (
        <TermsModal
          onAccept={() => { setAgreed(true); touch('agreed'); setShowModal(false); }}
          onClose={() => setShowModal(false)}
        />
      )}
    </div>
  );
}

ReactDOM.createRoot(document.getElementById('root')).render(<RegisterApp />);
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>