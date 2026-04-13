<?php
session_start();

// ── PHPMailer ────────────────────────────────────────────────────
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

// ── DB connection ────────────────────────────────────────────────
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
//  AJAX endpoints
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

// ── Fetch provinces ──────────────────────────────────────────────
$provinces = [];
try {
    $provinces = $pdo->query(
        "SELECT id, province_name FROM provinces ORDER BY province_name ASC"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// ── Employee Number generator ─────────────────────────────────────
function generateEmployeeNumber(PDO $pdo): string {
    try {
        $stmt = $pdo->query(
            "SELECT employee_number FROM officers
             WHERE employee_number LIKE 'LO-%'
             ORDER BY CAST(SUBSTRING(employee_number, 4) AS UNSIGNED) DESC
             LIMIT 1"
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $num = $row ? (int) substr($row['employee_number'], 3) + 1 : 1;
    } catch (PDOException $e) {
        $num = random_int(2, 9999999);
    }
    return 'LO-' . str_pad((string)$num, 7, '0', STR_PAD_LEFT);
}

function isEmployeeNumberTaken(PDO $pdo, string $empNum): bool {
    try {
        $stmt = $pdo->prepare("SELECT id FROM officers WHERE employee_number = ?");
        $stmt->execute([$empNum]);
        return (bool)$stmt->fetch();
    } catch (PDOException $e) {
        return false;
    }
}

function getUniqueEmployeeNumber(PDO $pdo): string {
    $empNum = generateEmployeeNumber($pdo);
    while (isEmployeeNumberTaken($pdo, $empNum)) {
        $num   = (int) substr($empNum, 3) + 1;
        $empNum = 'LO-' . str_pad((string)$num, 7, '0', STR_PAD_LEFT);
    }
    return $empNum;
}

$displayed_employee_number = getUniqueEmployeeNumber($pdo);

// ── PIN verification email ────────────────────────────────────────
function sendPinEmail(
    string $toEmail, string $toName, string $pin,
    string $host, int $port, string $user, string $pass,
    string $from, string $fromName
): bool|string {
    $mail = new PHPMailer(true);
    try {
        $mail->SMTPDebug   = SMTP::DEBUG_OFF;
        $mail->isSMTP();
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
        $mail->Subject = 'Your Evergreen Officer Email Verification Code';
        $mail->Body    = getPinEmailBody($toName, $pin);
        $mail->AltBody = "Hello {$toName},\n\nYour verification code is: {$pin}\n\nThis code expires in 10 minutes. Do not share it with anyone.";
        $mail->send();
        return true;
    } catch (Exception $e) {
        return $mail->ErrorInfo ?: 'Mail send failed.';
    }
}

function getPinEmailBody(string $name, string $pin): string {
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
              Your Loan Officer account is being created. Please enter the 6-digit code below to verify this email address.
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
            <p style="color:#888;font-size:13px;">
              Never share this code with anyone. If you did not request this, please contact your administrator immediately.
            </p>
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
//  POST handler  — validate → store in session → send PIN → redirect
// ════════════════════════════════════════════════════════════════
$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $first_name       = trim($_POST['first_name']       ?? '');
    $middle_name      = trim($_POST['middle_name']      ?? '');
    $surname          = trim($_POST['surname']          ?? '');
    $full_name        = trim("$first_name $middle_name $surname");
    $address          = trim($_POST['address']          ?? '');
    $province_id      = (int)($_POST['province_id']     ?? 0);
    $municipality_id  = (int)($_POST['municipality_id'] ?? 0);
    $barangay_id      = (int)($_POST['barangay_id']     ?? 0);
    $officer_email    = trim($_POST['officer_email']    ?? '');
    $contact_number   = trim($_POST['contact_number']   ?? '');
    $birthday         = trim($_POST['birthday']         ?? '');
    $role             = 'Loan Officer';
    $password         = $_POST['password']              ?? '';
    $confirm_password = $_POST['confirm_password']      ?? '';
    $employee_number  = trim($_POST['employee_number']  ?? '');

    // Resolve location names
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

    // ── Validate ─────────────────────────────────────────────────
    if (empty($first_name))     $errors[] = "First name is required.";
    if (empty($surname))        $errors[] = "Surname is required.";
    if (empty($address))        $errors[] = "Street/House address is required.";
    if ($province_id === 0)     $errors[] = "Province is required.";
    if ($municipality_id === 0) $errors[] = "Municipality/City is required.";
    if ($barangay_id === 0)     $errors[] = "Barangay is required.";
    if (empty($officer_email) || !filter_var($officer_email, FILTER_VALIDATE_EMAIL))
        $errors[] = "A valid email address is required.";
    if (empty($contact_number) || !preg_match('/^[0-9+\-\s()]{7,20}$/', $contact_number))
        $errors[] = "Please enter a valid contact number.";
    if (empty($birthday))       $errors[] = "Birthday is required.";
    if (strlen($password) < 8)  $errors[] = "Password must be at least 8 characters.";
    if (!preg_match('/[A-Z]/', $password))
        $errors[] = "Password must contain at least one uppercase letter.";
    if (!preg_match('/[0-9]/', $password))
        $errors[] = "Password must contain at least one number.";
    if (!preg_match('/[^a-zA-Z0-9]/', $password))
        $errors[] = "Password must contain at least one special character.";
    if ($password !== $confirm_password)
        $errors[] = "Passwords do not match.";
    if (empty($employee_number))
        $errors[] = "Employee number is missing. Please refresh the page.";

    if (empty($errors)) {
        try {
            // Check duplicate email before even sending the PIN
            $chkEmail = $pdo->prepare("SELECT id FROM officers WHERE officer_email = ?");
            $chkEmail->execute([$officer_email]);
            if ($chkEmail->fetch()) {
                $errors[] = "An officer with that email already exists.";
            }

            // Ensure employee number is still unique at submission time
            if (isEmployeeNumberTaken($pdo, $employee_number)) {
                $employee_number = getUniqueEmployeeNumber($pdo);
            }

            if (empty($errors)) {
                $password_hash = password_hash($password, PASSWORD_BCRYPT);
                $pin           = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);

                // ── Store everything in session — no DB write yet ──
                $_SESSION['pending_officer'] = [
                    'employee_number'   => $employee_number,
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
                    'officer_email'     => $officer_email,
                    'contact_number'    => $contact_number,
                    'birthday'          => $birthday,
                    'role'              => $role,
                    'password_hash'     => $password_hash,
                    'plain_password'    => $password,   // kept only for the welcome email after verification
                    'pin'               => $pin,
                    'pin_expires'       => time() + 600,
                    'attempts'          => 0,
                ];

                // ── Send PIN email ────────────────────────────────
                $mailResult = sendPinEmail(
                    $officer_email, $first_name, $pin,
                    $MAIL_HOST, $MAIL_PORT,
                    $MAIL_USERNAME, $MAIL_PASSWORD,
                    $MAIL_FROM, $MAIL_FROM_NAME
                );

                if ($mailResult !== true) {
                    // Mail failed — clear session, show error on form
                    unset($_SESSION['pending_officer']);
                    $errors[] = "Could not send verification email: $mailResult. Please check the email address and try again.";
                } else {
                    // ── Redirect to verification page ─────────────
                    header('Location: employee_verify-email.php');
                    exit;
                }
            }
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

// ── Pass data back to React (for repopulating form on error) ─────
$errorsJson         = json_encode($errors);
$successJson        = json_encode($success);
$firstNameJson      = json_encode($_POST['first_name']       ?? '');
$middleNameJson     = json_encode($_POST['middle_name']      ?? '');
$surnameJson        = json_encode($_POST['surname']          ?? '');
$addressJson        = json_encode($_POST['address']          ?? '');
$provinceIdJson     = json_encode((int)($_POST['province_id']     ?? 0));
$municipalityIdJson = json_encode((int)($_POST['municipality_id'] ?? 0));
$barangayIdJson     = json_encode((int)($_POST['barangay_id']     ?? 0));
$emailJson          = json_encode($_POST['officer_email']    ?? '');
$contactJson        = json_encode($_POST['contact_number']   ?? '');
$birthdayJson       = json_encode($_POST['birthday']         ?? '');
$employeeNumberJson = json_encode($_POST['employee_number']  ?? $displayed_employee_number);
$provincesJson      = json_encode($provinces);

// Surface a friendly "too many attempts" error from the verify page
$queryError = '';
if (isset($_GET['error']) && $_GET['error'] === 'toomany') {
    $queryError = 'Too many incorrect attempts. Please fill in the form again to get a new code.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Add Officer – Evergreen Trust and Savings</title>
  <link rel="icon" type="image/png" href="pictures/logo.png" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"/>
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
      --eg-cream:  #f4f8f6;
      --eg-text:   #2d4a3e;
      --eg-muted:  #6c8a7e;
      --eg-border: #d4e6de;
      --eg-error:  #c0392b;
      --eg-err-bg: #fdf0ef;
      --eg-gold:   #c8a84b;
      --eg-gold-l: #e8c96b;
    }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'DM Sans', sans-serif;
      background: var(--eg-bg);
      min-height: 100vh; display: flex; flex-direction: column;
    }

    /* ── Top bar ── */
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
      width: 36px; height: 36px; background: var(--eg-gold);
      border-radius: 8px; display: flex; align-items: center; justify-content: center;
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

    /* ── Layout ── */
    .page-body { display: flex; flex: 1; min-height: calc(100vh - 65px); }
    .form-side {
      width: 560px; min-width: 380px; background: white;
      padding: 30px 40px 48px; overflow-y: auto;
      display: flex; flex-direction: column;
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

    /* ── Verification flow notice ── */
    .verify-notice {
      background: linear-gradient(135deg, #f0faf3 0%, #e8f5e9 100%);
      border: 1.5px solid #a5d6a7; border-radius: 10px;
      padding: 12px 16px; margin-bottom: 16px;
      display: flex; align-items: flex-start; gap: 10px;
      font-size: 12.5px; color: #2e7d32;
      animation: fadeIn .4s ease both;
    }
    .verify-notice i { font-size: 15px; flex-shrink: 0; margin-top: 1px; }
    .verify-notice strong { display: block; margin-bottom: 2px; font-size: 13px; }

    /* ── Employee number card ── */
    .emp-card {
      background: linear-gradient(135deg, #0a3b2f 0%, #1a6b5a 100%);
      border-radius: 14px; padding: 18px 20px; margin-bottom: 20px;
      display: flex; align-items: center; gap: 14px;
      position: relative; overflow: hidden;
      box-shadow: 0 4px 20px rgba(10,59,47,0.25);
      animation: fadeIn .4s ease both;
    }
    .emp-card::before {
      content: ''; position: absolute;
      width: 160px; height: 160px; border-radius: 50%;
      border: 30px solid rgba(255,255,255,0.06);
      top: -50px; right: -30px;
    }
    .emp-card::after {
      content: ''; position: absolute;
      width: 90px; height: 90px; border-radius: 50%;
      border: 20px solid rgba(255,255,255,0.05);
      bottom: -20px; left: 140px;
    }
    .emp-icon {
      width: 46px; height: 46px; background: rgba(255,255,255,0.15);
      border-radius: 12px; display: flex; align-items: center; justify-content: center;
      flex-shrink: 0; border: 1px solid rgba(255,255,255,0.2); position: relative; z-index: 1;
    }
    .emp-icon i { color: var(--eg-gold-l); font-size: 20px; }
    .emp-info { flex: 1; min-width: 0; position: relative; z-index: 1; }
    .emp-label {
      font-size: 10px; font-weight: 700; color: rgba(255,255,255,0.55);
      letter-spacing: 1.5px; text-transform: uppercase; margin-bottom: 4px;
    }
    .emp-number {
      font-size: 24px; font-weight: 700; color: #fff;
      letter-spacing: 4px; font-family: 'Courier New', monospace; word-break: break-all;
    }
    .emp-note { font-size: 10.5px; color: rgba(255,255,255,0.50); margin-top: 3px; }
    .emp-badge {
      background: var(--eg-gold); color: var(--eg-dark);
      font-size: 10px; font-weight: 700; padding: 4px 10px;
      border-radius: 20px; letter-spacing: .5px;
      white-space: nowrap; flex-shrink: 0; position: relative; z-index: 1;
    }

    /* ── Alert ── */
    .alert-eg {
      border-radius: 10px; font-size: 13px; padding: 12px 16px; margin-bottom: 16px;
      background: var(--eg-err-bg); border: 1px solid #f5c6c3; color: var(--eg-error);
      display: flex; gap: 10px; align-items: flex-start;
      animation: fadeIn .3s ease both;
    }
    .alert-warn {
      border-radius: 10px; font-size: 13px; padding: 12px 16px; margin-bottom: 16px;
      background: #fff8e1; border: 1px solid #ffe082; color: #e65100;
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
    .field-wrap input, .field-wrap select {
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
    .field-wrap input:focus, .field-wrap select:focus {
      border-color: var(--eg-dark); background: white;
      box-shadow: 0 0 0 3px rgba(10,59,47,0.13);
    }
    .field-wrap select:disabled { opacity:.5; cursor:not-allowed; background-color:#f0f0f0; }
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
    input.field-ok, select.field-ok  { border-color: var(--eg-accent) !important; background: #fafffe !important; }
    input.field-err, select.field-err { border-color: var(--eg-error) !important; }

    /* ── Submit button ── */
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

    .back-row { text-align:center; margin-top:18px; font-size:13px; color:var(--eg-muted); }
    .back-row a { color: var(--eg-dark); font-weight:600; text-decoration:none; }
    .back-row a:hover { text-decoration:underline; }

    /* ── Hero side ── */
    .hero-side {
      flex: 1;
      background: linear-gradient(145deg, #020f0b 0%, #051a10 40%, #0a3b2f 100%);
      display: flex; flex-direction: column; align-items: center; justify-content: center;
      padding: 60px 48px; position: relative; overflow: hidden;
      animation: fadeHero .8s ease both;
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

    /* Flow steps on hero */
    .flow-steps {
      width: min(340px, 90%);
      display: flex; flex-direction: column; gap: 0;
      position: relative; z-index: 1;
    }
    .flow-step {
      display: flex; align-items: flex-start; gap: 14px;
      padding: 16px 20px;
      background: rgba(255,255,255,0.05);
      border: 1px solid rgba(255,255,255,0.08);
      position: relative;
    }
    .flow-step:first-child { border-radius: 14px 14px 0 0; }
    .flow-step:last-child  { border-radius: 0 0 14px 14px; }
    .flow-step:not(:last-child)::after {
      content: ''; position: absolute;
      left: 31px; bottom: -1px; width: 2px; height: 1px;
      background: rgba(255,255,255,0.15); z-index: 2;
    }
    .flow-step-num {
      width: 28px; height: 28px; border-radius: 50%; flex-shrink: 0;
      background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2);
      display: flex; align-items: center; justify-content: center;
      font-size: 12px; font-weight: 700; color: var(--eg-gold-l);
      margin-top: 1px;
    }
    .flow-step-body {}
    .flow-step-title { color: #fff; font-size: 13px; font-weight: 600; margin-bottom: 3px; }
    .flow-step-desc  { color: rgba(255,255,255,0.50); font-size: 11.5px; line-height: 1.5; }

    /* ID card mockup */
    .id-card-mockup {
      width: min(340px, 90%);
      background: linear-gradient(135deg, #0a3b2f, #1a6b5a);
      border-radius: 18px; padding: 24px;
      box-shadow: 0 32px 64px rgba(0,0,0,0.5);
      position: relative; z-index: 1;
      border: 1px solid rgba(255,255,255,0.1); overflow: hidden;
      margin-bottom: 20px;
    }
    .id-card-mockup::before {
      content: ''; position: absolute; inset: 0;
      background: radial-gradient(ellipse at 20% 20%, rgba(255,255,255,0.08) 0%, transparent 60%);
    }
    .id-card-top { display: flex; align-items: center; gap: 10px; margin-bottom: 20px; position: relative; z-index: 1; }
    .id-card-logo {
      width: 32px; height: 32px; background: var(--eg-gold);
      border-radius: 8px; display: flex; align-items: center; justify-content: center;
    }
    .id-card-logo img {
      height: 20px; width: auto;
      filter: brightness(0) saturate(100%) invert(10%) sepia(40%) saturate(800%) hue-rotate(105deg) brightness(40%);
    }
    .id-card-org { color: white; font-family: 'Playfair Display', serif; font-size: 14px; font-weight: 700; }
    .id-card-sub { color: rgba(255,255,255,0.55); font-size: 10px; }
    .id-card-avatar {
      width: 64px; height: 64px; background: rgba(255,255,255,0.12);
      border-radius: 50%; display: flex; align-items: center; justify-content: center;
      margin: 0 auto 14px; border: 2px solid rgba(255,255,255,0.2); position: relative; z-index: 1;
    }
    .id-card-avatar i { color: rgba(255,255,255,0.6); font-size: 28px; }
    .id-card-name { color: white; font-size: 16px; font-weight: 700; text-align: center; margin-bottom: 4px; position: relative; z-index: 1; }
    .id-card-role { color: var(--eg-gold-l); font-size: 11px; font-weight: 600; letter-spacing: 1px; text-align: center; text-transform: uppercase; margin-bottom: 16px; position: relative; z-index: 1; }
    .id-card-number {
      background: rgba(0,0,0,0.25); border-radius: 8px; padding: 10px 14px;
      display: flex; align-items: center; justify-content: space-between; position: relative; z-index: 1;
    }
    .id-card-num-label { color: rgba(255,255,255,0.5); font-size: 9px; text-transform: uppercase; letter-spacing: 1px; }
    .id-card-num-value { color: white; font-family: 'Courier New', monospace; font-size: 14px; font-weight: 700; letter-spacing: 3px; }
    .id-card-strip { height: 5px; margin-top: 14px; border-radius: 3px; background: linear-gradient(90deg, var(--eg-gold) 0%, var(--eg-gold-l) 100%); position: relative; z-index: 1; }

    @keyframes shake {
      0%,100%{transform:translateX(0)}
      20%{transform:translateX(-8px)} 40%{transform:translateX(8px)}
      60%{transform:translateX(-5px)} 80%{transform:translateX(5px)}
    }
    .shake { animation: shake 0.45s ease both; }

    @media (max-width:960px) { .hero-side{display:none;} .form-side{width:100%;border-right:none;} }
    @media (max-width:540px) {
      .form-side{padding:22px 16px 40px;}
      .row-fields.cols-2,.row-fields.cols-3{grid-template-columns:1fr;}
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

<div class="page-body">
  <!-- ── Form side (React mount) ── -->
  <div class="form-side" id="root"></div>

  <!-- ── Hero side ── -->
  <div class="hero-side">
    <div class="hero-content">
      <h2>Staff Registration</h2>
      <h1>Add Officer</h1>
      <p>Fill in the officer's details below. A verification code will be sent to their email before the account is created.</p>

      <div class="id-card-mockup">
        <div class="id-card-top">
          <div class="id-card-logo"><img src="pictures/logo.png" alt="Evergreen Logo" /></div>
          <div>
            <div class="id-card-org">EVERGREEN</div>
            <div class="id-card-sub">Trust and Savings Bank</div>
          </div>
        </div>
        <div class="id-card-avatar"><i class="bi bi-person-fill"></i></div>
        <div class="id-card-name">New Officer</div>
        <div class="id-card-role">Loan Officer</div>
        <div class="id-card-number">
          <div>
            <div class="id-card-num-label">Employee No.</div>
            <div class="id-card-num-value">LO-0000001</div>
          </div>
          <i class="bi bi-shield-check" style="color:var(--eg-gold-l);font-size:18px;"></i>
        </div>
        <div class="id-card-strip"></div>
      </div>

      <!-- Registration flow steps -->
      <div class="flow-steps">
        <div class="flow-step">
          <div class="flow-step-num">1</div>
          <div class="flow-step-body">
            <div class="flow-step-title">Fill in officer details</div>
            <div class="flow-step-desc">Personal info, address, contact, and initial password.</div>
          </div>
        </div>
        <div class="flow-step">
          <div class="flow-step-num">2</div>
          <div class="flow-step-body">
            <div class="flow-step-title">Email verification sent</div>
            <div class="flow-step-desc">A 6-digit code is emailed to the officer's address.</div>
          </div>
        </div>
        <div class="flow-step">
          <div class="flow-step-num">3</div>
          <div class="flow-step-body">
            <div class="flow-step-title">Account created &amp; credentials emailed</div>
            <div class="flow-step-desc">After verification, the account goes live and login details are sent.</div>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- React + Babel -->
<script src="https://unpkg.com/react@18/umd/react.production.min.js"></script>
<script src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js"></script>
<script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>

<script type="text/babel">
const { useState, useEffect, useRef } = React;

const PHP_ERRORS      = <?= $errorsJson ?>;
const PHP_SUCCESS     = <?= $successJson ?>;
const QUERY_ERROR     = <?= json_encode($queryError) ?>;
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
const INIT_EMP_NUM    = <?= $employeeNumberJson ?>;
const PROVINCES       = <?= $provincesJson ?>;

function checkPwStrength(v) {
  return {
    len:     v.length >= 8,
    upper:   /[A-Z]/.test(v),
    num:     /[0-9]/.test(v),
    special: /[^a-zA-Z0-9]/.test(v),
  };
}

function StrengthBar({ password }) {
  const r     = checkPwStrength(password);
  const score = Object.values(r).filter(Boolean).length;
  const levels = [
    {w:'0%',   c:'#e0e0e0', t:'',          tc:'#999'   },
    {w:'25%',  c:'#e53935', t:'Weak',       tc:'#e53935'},
    {w:'50%',  c:'#fb8c00', t:'Fair',       tc:'#fb8c00'},
    {w:'75%',  c:'#fdd835', t:'Good',       tc:'#f9a825'},
    {w:'100%', c:'#43a047', t:'Strong ✓',  tc:'#2e7d32'},
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

function validateEmail(v)   { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v); }
function validateContact(v) { return /^[0-9+\-\s()]{7,20}$/.test(v); }

function AddOfficerApp() {
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
  const [email,          setEmail]          = useState(INIT_EMAIL);
  const [contact,        setContact]        = useState(INIT_CONTACT);
  const [birthday,       setBirthday]       = useState(INIT_BIRTHDAY);
  const [password,       setPassword]       = useState('');
  const [confirmPw,      setConfirmPw]      = useState('');
  const [showPw,         setShowPw]         = useState(false);
  const [showCpw,        setShowCpw]        = useState(false);
  const [loading,        setLoading]        = useState(false);
  const [errors,         setErrors]         = useState(
    QUERY_ERROR ? [QUERY_ERROR] : (PHP_ERRORS || [])
  );
  const [employeeNumber] = useState(INIT_EMP_NUM);
  const formRef = useRef(null);

  const [t, setT] = useState({
    firstName:false, surname:false, address:false,
    province:false, municipality:false, barangay:false,
    email:false, contact:false, birthday:false,
    password:false, confirmPw:false,
  });
  const touch    = (field) => setT(prev => ({...prev, [field]: true}));
  const touchAll = () => setT({
    firstName:true, surname:true, address:true,
    province:true, municipality:true, barangay:true,
    email:true, contact:true, birthday:true,
    password:true, confirmPw:true,
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
    fetch(`add_officer.php?action=get_municipalities&province_id=${provinceId}`)
      .then(r => r.json())
      .then(data => { setMunicipalities(data.map(m => ({ id: m.id, name: m.municipality_name }))); setLoadingMuni(false); })
      .catch(() => { setMunicipalities([]); setLoadingMuni(false); });
  }, [provinceId]);

  useEffect(() => {
    if (!municipalityId) { setBarangays([]); setBarangayId(0); return; }
    setLoadingBrgy(true); setBarangayId(0);
    fetch(`add_officer.php?action=get_barangays&municipality_id=${municipalityId}`)
      .then(r => { const ct = r.headers.get('content-type') || ''; if (!ct.includes('application/json')) throw new Error(); return r.json(); })
      .then(data => { setBarangays(data.map(b => ({ id: b.id, name: b.barangay_name }))); setLoadingBrgy(false); })
      .catch(() => { setBarangays([]); setLoadingBrgy(false); });
  }, [municipalityId]);

  useEffect(() => { if (INIT_PROVINCE && INIT_MUNI && municipalities.length > 0) setMunicipalityId(INIT_MUNI); }, [municipalities]);
  useEffect(() => { if (INIT_MUNI && INIT_BARANGAY && barangays.length > 0) setBarangayId(INIT_BARANGAY); }, [barangays]);

  const confirmMatch = confirmPw.length > 0 && confirmPw === password;
  const confirmMiss  = confirmPw.length > 0 && confirmPw !== password;
  const clearErrors  = () => setErrors([]);

  function fieldCls(field, extra) {
    if (!t[field]) return extra || '';
    return (v[field] ? 'field-ok' : 'field-err') + (extra ? ' ' + extra : '');
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
    if (errs.length) { e.preventDefault(); setErrors(errs); return; }
    setLoading(true);
  }

  return (
    <div ref={formRef} style={{flex:1, display:'flex', flexDirection:'column'}}>
      <div className="form-title">Add Loan Officer</div>
      <div className="form-subtitle">
        Complete the form and submit. A 6-digit code will be emailed to the officer to verify their address before the account is created.
      </div>

      {/* Verification flow notice */}
      <div className="verify-notice">
        <i className="bi bi-envelope-check-fill"/>
        <div>
          <strong>Email verification required</strong>
          After submitting, a one-time code is sent to the officer's email. The account is only created once the code is confirmed.
        </div>
      </div>

      <div className="emp-card">
        <div className="emp-icon"><i className="bi bi-person-badge-fill"/></div>
        <div className="emp-info">
          <div className="emp-label">Employee Number</div>
          <div className="emp-number">{employeeNumber}</div>
          <div className="emp-note">Auto-assigned · Cannot be changed</div>
        </div>
        <div className="emp-badge">AUTO</div>
      </div>

      {errors.length > 0 && (
        <div className="alert-eg">
          <i className="bi bi-exclamation-circle-fill" style={{marginTop:1}}/>
          <div>{errors.map((e, i) => <div key={i}>{e}</div>)}</div>
        </div>
      )}

      <form method="POST" action="add_officer.php" onSubmit={handleSubmit} style={{flex:1}}>
        <input type="hidden" name="employee_number" value={employeeNumber}/>
        <input type="hidden" name="role" value="Loan Officer"/>

        <div className="section-divider">Personal Information</div>
        <div className="row-fields cols-2">
          <div className="field-wrap">
            <label>First Name *</label>
            <input type="text" name="first_name" required placeholder="Juan"
              value={firstName}
              onChange={e => { setFirstName(e.target.value); clearErrors(); }}
              onBlur={() => touch('firstName')}
              className={fieldCls('firstName')}/>
            <FieldHint touched={t.firstName} valid={v.firstName} errorMsg="First name is required."/>
          </div>
          <div className="field-wrap">
            <label>Middle Name <span style={{fontWeight:400,opacity:.6}}>(optional)</span></label>
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
            <FieldHint touched={t.surname} valid={v.surname} errorMsg="Surname is required."/>
          </div>
        </div>

        <div className="section-divider">Address</div>
        <div className="row-fields cols-1">
          <div className="field-wrap">
            <label>House No. / Street / Unit *</label>
            <input type="text" name="address" required placeholder="e.g. 29 Sinforosa St."
              value={address}
              onChange={e => { setAddress(e.target.value); clearErrors(); }}
              onBlur={() => touch('address')}
              className={fieldCls('address')}/>
            <FieldHint touched={t.address} valid={v.address} errorMsg="Street/house address is required." okMsg="Address noted!"/>
          </div>
        </div>
        <div className="row-fields cols-2">
          <div className="field-wrap">
            <label>Province *</label>
            <div className="select-wrap">
              <select name="province_id" required value={provinceId}
                onChange={e => { setProvinceId(Number(e.target.value)); clearErrors(); touch('province'); }}
                onBlur={() => touch('province')}
                className={fieldCls('province')}>
                <option value={0}>— Select Province —</option>
                {PROVINCES.map(p => <option key={p.id} value={p.id}>{p.province_name}</option>)}
              </select>
            </div>
            <FieldHint touched={t.province} valid={v.province} errorMsg="Please select a province." okMsg="Province selected!"/>
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
            <FieldHint touched={t.municipality} valid={v.municipality} errorMsg="Please select a municipality." okMsg="Municipality selected!"/>
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
            <FieldHint touched={t.barangay} valid={v.barangay} errorMsg="Please select a barangay." okMsg="Barangay selected!"/>
          </div>
        </div>

        <div className="section-divider">Contact Details</div>
        <div className="row-fields cols-2">
          <div className="field-wrap">
            <label>Email *</label>
            <input type="email" name="officer_email" required placeholder="officer@example.com"
              value={email}
              onChange={e => { setEmail(e.target.value); clearErrors(); }}
              onBlur={() => touch('email')}
              className={fieldCls('email')}/>
            <FieldHint touched={t.email} valid={v.email} errorMsg="Enter a valid email address." okMsg="Valid email!"/>
          </div>
          <div className="field-wrap">
            <label>Contact Number *</label>
            <input type="tel" name="contact_number" required placeholder="0927 379 2682"
              value={contact}
              onChange={e => { setContact(e.target.value); clearErrors(); }}
              onBlur={() => touch('contact')}
              className={fieldCls('contact')}/>
            <FieldHint touched={t.contact} valid={v.contact} errorMsg="Enter a valid contact number." okMsg="Valid number!"/>
          </div>
        </div>
        <div className="row-fields cols-1">
          <div className="field-wrap">
            <label>Birthday *</label>
            <input type="date" name="birthday" required
              value={birthday}
              onChange={e => { setBirthday(e.target.value); clearErrors(); touch('birthday'); }}
              onBlur={() => touch('birthday')}
              className={fieldCls('birthday')}/>
            <FieldHint touched={t.birthday} valid={v.birthday} errorMsg="Birthday is required." okMsg="Birthday recorded!"/>
          </div>
        </div>

        <div className="section-divider">Initial Password</div>
        <div className="row-fields cols-2">
          <div className="field-wrap">
            <label>Temporary Password *</label>
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
                <i className="bi bi-exclamation-circle-fill"/>8+ chars, uppercase, number &amp; special char.
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
            {confirmMatch && <div className="field-hint hint-ok"><i className="bi bi-check-circle-fill"/>Passwords match!</div>}
            {confirmMiss  && <div className="field-hint hint-error"><i className="bi bi-exclamation-circle-fill"/>Passwords do not match.</div>}
            {!confirmMatch && !confirmMiss && t.confirmPw && confirmPw.length === 0 && (
              <div className="field-hint hint-error"><i className="bi bi-exclamation-circle-fill"/>Please confirm the password.</div>
            )}
          </div>
        </div>

        <button type="submit"
          className={`btn-create${loading ? ' loading' : ''}`}
          disabled={loading}>
          <span className="spinner"/>
          <span className="btn-text">
            <i className="bi bi-send-fill" style={{marginRight:6}}/>
            {loading ? 'Sending Verification…' : 'SEND VERIFICATION CODE'}
          </span>
        </button>
      </form>

      <div className="back-row">
        <a href="Employeedashboard.php">← Back to Dashboard</a>
      </div>
    </div>
  );
}

ReactDOM.createRoot(document.getElementById('root')).render(<AddOfficerApp />);
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>