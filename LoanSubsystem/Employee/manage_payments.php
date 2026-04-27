<?php
session_start();

// ── Auth guard — Super Admin only ────────────────────────────────────────────
if (isset($_SESSION['officer_id']) && !isset($_SESSION['admin_id'])) {
    header("Location: ../Loan/adminindex.php");
    exit;
}
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../Loan/login.php");
    exit;
}

// ── DB connection ────────────────────────────────────────────────────────────
$host   = 'localhost';
$dbname = 'loandb';
$dbuser = 'root';
$dbpass = '';

// ── Filters from GET ─────────────────────────────────────────────────────────
$filterEmail      = trim($_GET['email']       ?? '');
$filterMethod     = trim($_GET['method']      ?? '');
$filterStatus     = trim($_GET['status']      ?? '');
$filterFrom       = trim($_GET['from']        ?? '');
$filterTo         = trim($_GET['to']          ?? '');
$filterLoanId     = intval($_GET['loan_id']   ?? 0);
$filterBorrower   = trim($_GET['borrower']    ?? '');
$filterLoanStatus = trim($_GET['loan_status'] ?? '');
$page             = max(1, intval($_GET['page'] ?? 1));
$perPage          = 20;
$offset           = ($page - 1) * $perPage;

// ── Data ─────────────────────────────────────────────────────────────────────
$payments    = [];
$stats       = [
    'total'         => 0,
    'totalAmount'   => 0,
    'online'        => 0,
    'cheque'        => 0,
    'today'         => 0,
    'todayAmount'   => 0,
    // ── KEY: fullyPaid counts loan_applications with status='Closed'
    'fullyPaid'     => 0,
    // ── NEW: Total amount collected from Closed loans
    'fullyPaidAmt'  => 0,
];
$totalRows   = 0;
$totalPages  = 1;
$tableExists = false;
$error       = null;

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $dbuser, $dbpass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    $tableExists = !empty(
        $pdo->query("SHOW TABLES LIKE 'loan_payments'")->fetchAll()
    );

    if ($tableExists) {

        // ── CATCH-UP FIX: Auto-close loans that are fully paid ───────────────
        $pdo->exec("
            UPDATE loan_applications la
            INNER JOIN (
                SELECT lp.loan_application_id, SUM(lp.amount) AS total_paid
                FROM   loan_payments lp
                WHERE  lp.status = 'Completed'
                GROUP  BY lp.loan_application_id
            ) paid ON paid.loan_application_id = la.id
            SET    la.status = 'Closed', la.next_payment_due = NULL
            WHERE  la.status IN ('Active', 'Approved')
              AND  paid.total_paid >= la.loan_amount
        ");

        // ── Global payment stats (from loan_payments table) ──────────────────
        $s = $pdo->query("
            SELECT
                COUNT(*)                                               AS total,
                COALESCE(SUM(amount),0)                               AS totalAmount,
                SUM(payment_method = 'online')                        AS online,
                SUM(payment_method = 'cheque')                        AS cheque,
                SUM(DATE(payment_date) = CURDATE())                   AS today,
                COALESCE(SUM(CASE WHEN DATE(payment_date)=CURDATE()
                                  THEN amount ELSE 0 END),0)          AS todayAmount
            FROM loan_payments
            WHERE status = 'Completed'
        ")->fetch();
        if ($s) {
            $stats['total']       = (int)$s['total'];
            $stats['totalAmount'] = round(floatval($s['totalAmount']), 2);
            $stats['online']      = (int)$s['online'];
            $stats['cheque']      = (int)$s['cheque'];
            $stats['today']       = (int)$s['today'];
            $stats['todayAmount'] = round(floatval($s['todayAmount']), 2);
        }

        // ── Count of Closed (fully paid) loans & their total settled amounts ─
        $fp = $pdo->query("
            SELECT
                COUNT(*) AS cnt,
                COALESCE(SUM(la.loan_amount), 0) AS total_loan_amount
            FROM loan_applications la
            WHERE la.status = 'Closed'
        ")->fetch();
        $stats['fullyPaid']    = (int)($fp['cnt']               ?? 0);
        $stats['fullyPaidAmt'] = round(floatval($fp['total_loan_amount'] ?? 0), 2);

        // ── Build WHERE ───────────────────────────────────────────────────────
        $where  = [];
        $params = [];

        if ($filterEmail) {
            $where[] = "lp.user_email LIKE :email";
            $params[':email'] = '%' . $filterEmail . '%';
        }
        if ($filterBorrower) {
            $where[] = "(COALESCE(lp.borrower_name,'') LIKE :borrower
                         OR u.full_name  LIKE :borrower2
                         OR lb.full_name LIKE :borrower3)";
            $params[':borrower']  = '%' . $filterBorrower . '%';
            $params[':borrower2'] = '%' . $filterBorrower . '%';
            $params[':borrower3'] = '%' . $filterBorrower . '%';
        }
        if ($filterMethod && in_array($filterMethod, ['online','cheque'], true)) {
            $where[] = "lp.payment_method = :method";
            $params[':method'] = $filterMethod;
        }
        if ($filterStatus) {
            $where[] = "lp.status = :status";
            $params[':status'] = $filterStatus;
        }
        if ($filterLoanStatus) {
            $where[] = "la.status = :loan_status";
            $params[':loan_status'] = $filterLoanStatus;
        }
        if ($filterFrom) {
            $where[] = "DATE(lp.payment_date) >= :from_date";
            $params[':from_date'] = $filterFrom;
        }
        if ($filterTo) {
            $where[] = "DATE(lp.payment_date) <= :to_date";
            $params[':to_date'] = $filterTo;
        }
        if ($filterLoanId > 0) {
            $where[] = "lp.loan_application_id = :loan_id";
            $params[':loan_id'] = $filterLoanId;
        }

        $whereSQL = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        // ── Count ─────────────────────────────────────────────────────────────
        $cStmt = $pdo->prepare("
            SELECT COUNT(*) AS cnt, COALESCE(SUM(lp.amount),0) AS filtered_amount
            FROM   loan_payments lp
            JOIN   loan_applications la ON lp.loan_application_id = la.id
            LEFT JOIN users u           ON u.user_email = lp.user_email
            LEFT JOIN loan_borrowers lb ON lb.loan_application_id = la.id
            $whereSQL
        ");
        $cStmt->execute($params);
        $crow       = $cStmt->fetch();
        $totalRows  = (int)$crow['cnt'];
        $totalPages = $totalRows > 0 ? (int)ceil($totalRows / $perPage) : 1;

        // ── Paginated rows ────────────────────────────────────────────────────
        $pStmt = $pdo->prepare("
            SELECT
                lp.id,
                lp.loan_application_id,
                lp.user_email,
                lp.amount,
                lp.payment_method,
                lp.transaction_ref,
                lp.payment_date,
                lp.created_at,
                lp.status                                     AS payment_status,
                lp.ip_address,
                lp.user_agent,
                lp.notes,
                lp.processed_by,
                la.status                                     AS loan_status,
                la.loan_amount,
                COALESCE(lt.name, CONCAT('Loan #', lp.loan_application_id)) AS loan_type,
                lt.code                                       AS loan_type_code,
                COALESCE(lp.borrower_name, u.full_name, lb.full_name, lp.user_email) AS borrower_name,
                COALESCE(lp.account_number, u.account_number, lb.account_number)     AS account_number,
                u.contact_number,
                (SELECT COALESCE(SUM(lp2.amount),0)
                 FROM   loan_payments lp2
                 WHERE  lp2.loan_application_id = lp.loan_application_id
                   AND  lp2.status = 'Completed') AS total_paid_for_loan
            FROM   loan_payments lp
            JOIN   loan_applications la ON lp.loan_application_id = la.id
            LEFT JOIN loan_types lt     ON lt.id = la.loan_type_id
            LEFT JOIN users u           ON u.user_email = lp.user_email
            LEFT JOIN loan_borrowers lb ON lb.loan_application_id = la.id
            $whereSQL
            ORDER  BY lp.payment_date DESC
            LIMIT  :lim OFFSET :off
        ");
        foreach ($params as $k => $v) $pStmt->bindValue($k, $v);
        $pStmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
        $pStmt->bindValue(':off', $offset,  PDO::PARAM_INT);
        $pStmt->execute();
        $payments = $pStmt->fetchAll();
    }

} catch (PDOException $e) {
    $error = "Database error: " . htmlspecialchars($e->getMessage());
}

// ── Session info ─────────────────────────────────────────────────────────────
$adminName     = htmlspecialchars($_SESSION['admin_name']            ?? 'Staff User');
$adminRole     = htmlspecialchars($_SESSION['admin_role']            ?? 'SuperAdmin');
$adminEmpNum   = htmlspecialchars($_SESSION['admin_employee_number'] ?? '');
$adminInitials = implode('', array_map(
    fn($w) => strtoupper($w[0]),
    array_slice(explode(' ', strip_tags($adminName)), 0, 2)
));

$navItems = [
    ['label' => 'Dashboard',          'href' => 'Employeedashboard.php', 'icon' => 'bi-speedometer2'],
    ['label' => 'Account Management', 'href' => 'add_officer.php',       'icon' => 'bi-person-gear'],
    ['label' => 'Audit Logs',         'href' => 'audit_logs.php',        'icon' => 'bi-journal-text'],
    ['label' => 'Loan Penalties',     'href' => 'loan_penalty.php',      'icon' => 'bi-exclamation-triangle-fill'],
    ['label' => 'Manage Payments',    'href' => 'manage_payments.php',   'icon' => 'bi-credit-card-2-front'],
];
$activeNav = 'Manage Payments';

function pgUrl(int $pg): string {
    $p = $_GET;
    $p['page'] = $pg;
    return '?' . http_build_query($p);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Evergreen – Manage Payments</title>
  <link rel="icon" type="image/png" href="pictures/logo.png"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css"/>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>

  <style>
    :root {
      --eg-forest:    #0a3b2f;
      --eg-deep:      #062620;
      --eg-mid:       #1a6b55;
      --eg-light:     #e8f4ef;
      --eg-cream:     #f7f3ee;
      --eg-gold:      #c9a84c;
      --eg-gold-l:    #e8c96b;
      --eg-text:      #1c2b25;
      --eg-muted:     #6b8c7e;
      --eg-border:    #d4e6de;
      --eg-bg:        #f4f8f6;
      --eg-card:      #ffffff;
      --eg-sidebar-w: 262px;
      --eg-topbar-h:  62px;
    }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'DM Sans', sans-serif; background: var(--eg-bg); color: var(--eg-text); min-height: 100vh; }

    /* ══ SIDEBAR ══ */
    .eg-sidebar { position: fixed; top: 0; left: 0; width: var(--eg-sidebar-w); height: 100vh; background: linear-gradient(180deg, var(--eg-deep) 0%, var(--eg-forest) 60%, #0e4535 100%); z-index: 1040; display: flex; flex-direction: column; transform: translateX(-100%); transition: transform 0.28s cubic-bezier(.4,0,.2,1); box-shadow: 4px 0 28px rgba(6,38,32,0.35); }
    .eg-sidebar.open { transform: translateX(0); }
    @media (min-width: 992px) { .eg-sidebar { transform: translateX(0); } .eg-main { margin-left: var(--eg-sidebar-w); } }
    .eg-sidebar-logo { display: flex; align-items: center; gap: 10px; padding: 20px 22px 16px; border-bottom: 1px solid rgba(255,255,255,0.08); text-decoration: none; }
    .eg-sidebar-logo-icon { width: 36px; height: 36px; background: var(--eg-gold); border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .eg-sidebar-logo-icon img { height: 22px; width: auto; filter: brightness(0) saturate(100%) invert(10%) sepia(40%) saturate(800%) hue-rotate(105deg) brightness(40%); }
    .eg-sidebar-logo-text { font-family: 'Playfair Display', serif; font-size: 17px; font-weight: 700; color: #fff; letter-spacing: .8px; line-height: 1.1; }
    .eg-sidebar-logo-sub  { font-size: 10px; color: rgba(255,255,255,0.45); letter-spacing: .3px; }
    .eg-nav-toggle-btn { display: flex; align-items: center; gap: 8px; width: 100%; background: none; border: none; color: rgba(255,255,255,0.50); padding: 12px 22px; font-size: 10.5px; font-weight: 700; letter-spacing: 1.5px; cursor: pointer; transition: color .2s; text-transform: uppercase; font-family: 'DM Sans', sans-serif; }
    .eg-nav-toggle-btn:hover { color: var(--eg-gold-l); }
    .eg-nav-toggle-btn .chevron { margin-left: auto; transition: transform .25s; }
    .eg-nav-toggle-btn.collapsed .chevron { transform: rotate(-90deg); }
    .eg-nav-collapse { overflow: hidden; max-height: 600px; transition: max-height .3s ease; }
    .eg-nav-collapse.hidden { max-height: 0; }
    .eg-nav-item { display: flex; align-items: center; gap: 10px; padding: 11px 22px 11px 30px; color: rgba(255,255,255,0.60); text-decoration: none; font-size: 14px; font-weight: 500; transition: background .18s, color .18s; border-left: 3px solid transparent; font-family: 'DM Sans', sans-serif; }
    .eg-nav-item:hover  { background: rgba(255,255,255,0.07); color: #fff; }
    .eg-nav-item.active { color: var(--eg-gold-l); border-left-color: var(--eg-gold); background: rgba(201,168,76,0.10); }
    .eg-nav-item i { font-size: 16px; width: 20px; text-align: center; }
    .eg-sidebar-footer { margin-top: auto; border-top: 1px solid rgba(255,255,255,0.08); padding: 16px 22px; }
    .eg-sidebar-footer-user { display: flex; align-items: center; gap: 10px; }
    .eg-sidebar-avatar { width: 34px; height: 34px; border-radius: 50%; background: var(--eg-gold); display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; color: var(--eg-deep); flex-shrink: 0; }
    .eg-sidebar-uname { font-size: 13px; font-weight: 600; color: #fff; line-height: 1.2; }
    .eg-sidebar-urole  { font-size: 11px; color: var(--eg-gold-l); }
    .eg-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.50); z-index: 1039; }
    .eg-overlay.show { display: block; }
    @media (min-width: 992px) { .eg-overlay { display: none !important; } }

    /* ══ TOP BAR ══ */
    .eg-topbar { position: sticky; top: 0; height: var(--eg-topbar-h); background: linear-gradient(90deg, var(--eg-deep) 0%, var(--eg-forest) 100%); display: flex; align-items: center; justify-content: space-between; padding: 0 26px; z-index: 1030; box-shadow: 0 2px 16px rgba(6,38,32,0.28); }
    .eg-topbar-left { display: flex; align-items: center; gap: 14px; }
    .eg-hamburger { background: none; border: none; color: rgba(255,255,255,0.80); font-size: 22px; cursor: pointer; padding: 4px 8px; border-radius: 6px; transition: color .2s, background .2s; display: none; }
    @media (max-width: 991px) { .eg-hamburger { display: flex; } }
    .eg-hamburger:hover { color: var(--eg-gold-l); background: rgba(255,255,255,0.08); }
    .eg-topbar-brand { display: none; }
    @media (max-width: 991px) { .eg-topbar-brand { display: block; } }
    .eg-topbar-brand .eg-tb-name { font-family: 'Playfair Display', serif; color: #fff; font-size: 16px; font-weight: 700; }
    .eg-topbar-brand .eg-tb-page { font-size: 11px; color: rgba(255,255,255,0.50); }
    .eg-breadcrumb { display: flex; align-items: center; gap: 6px; font-size: 13px; color: rgba(255,255,255,0.55); }
    @media (max-width: 991px) { .eg-breadcrumb { display: none; } }
    .eg-breadcrumb .bc-sep { opacity: .4; }
    .eg-breadcrumb .bc-active { color: #fff; font-weight: 600; }
    .eg-profile-wrap { position: relative; }
    .eg-profile-btn { display: flex; align-items: center; gap: 10px; background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.14); border-radius: 10px; padding: 6px 14px 6px 8px; color: #fff; cursor: pointer; transition: background .2s; font-family: 'DM Sans', sans-serif; }
    .eg-profile-btn:hover { background: rgba(255,255,255,0.15); }
    .eg-avatar { width: 32px; height: 32px; border-radius: 50%; background: var(--eg-gold); display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; color: var(--eg-deep); flex-shrink: 0; }
    .eg-profile-info { text-align: left; }
    .eg-profile-name { font-size: 13px; font-weight: 600; line-height: 1.2; }
    .eg-profile-role { font-size: 11px; color: var(--eg-gold-l); line-height: 1.2; }
    .eg-profile-dropdown { position: absolute; top: calc(100% + 8px); right: 0; background: #fff; border-radius: 12px; box-shadow: 0 8px 32px rgba(6,38,32,0.18); min-width: 190px; overflow: hidden; z-index: 2000; display: none; animation: dropIn .18s ease; border: 1px solid var(--eg-border); }
    .eg-profile-dropdown.show { display: block; }
    @keyframes dropIn { from{opacity:0;transform:translateY(-6px)} to{opacity:1;transform:translateY(0)} }
    .eg-profile-dropdown .dd-header { padding: 14px 16px 10px; border-bottom: 1px solid var(--eg-border); }
    .eg-profile-dropdown .dd-header .dd-name { font-size: 13.5px; font-weight: 700; color: var(--eg-text); }
    .eg-profile-dropdown .dd-header .dd-empnum { font-size: 11px; color: var(--eg-muted); }
    .eg-profile-dropdown a { display: flex; align-items: center; gap: 8px; padding: 10px 16px; color: var(--eg-text); text-decoration: none; font-size: 13.5px; transition: background .15s; }
    .eg-profile-dropdown a:hover { background: var(--eg-bg); }
    .eg-profile-dropdown a i { width: 18px; color: var(--eg-muted); }
    .eg-profile-dropdown .divider { height: 1px; background: var(--eg-border); margin: 4px 0; }
    .eg-profile-dropdown a.logout-link { color: #c0392b; }
    .eg-profile-dropdown a.logout-link i { color: #c0392b; }

    /* ══ MAIN ══ */
    .eg-main { min-height: 100vh; transition: margin-left .28s; }
    .eg-content { padding: 30px 30px 56px; }
    .eg-page-header { margin-bottom: 28px; }
    .eg-page-title { font-family: 'Playfair Display', serif; font-size: 28px; font-weight: 700; color: var(--eg-forest); letter-spacing: -.2px; }
    .eg-page-sub   { font-size: 13.5px; color: var(--eg-muted); margin-top: 3px; }

    /* ══ STAT CARDS ══ */
    .eg-stat-card { background: var(--eg-card); border-radius: 16px; padding: 22px 24px; box-shadow: 0 1px 6px rgba(10,59,47,0.06),0 4px 16px rgba(10,59,47,0.04); border: 1.5px solid var(--eg-border); transition: border-color .2s, box-shadow .2s, transform .2s; height: 100%; position: relative; overflow: hidden; }
    .eg-stat-card::before { content: ''; position: absolute; width: 80px; height: 80px; border-radius: 50%; background: var(--eg-light); opacity: .6; top: -20px; right: -20px; }
    .eg-stat-card:hover { transform: translateY(-2px); box-shadow: 0 6px 24px rgba(10,59,47,0.10); }
    .eg-stat-card.highlight { background: linear-gradient(135deg, var(--eg-forest) 0%, var(--eg-mid) 100%); border-color: transparent; }
    .eg-stat-card.highlight .eg-stat-label, .eg-stat-card.highlight .eg-stat-sub { color: rgba(255,255,255,0.65); }
    .eg-stat-card.highlight .eg-stat-num  { color: #fff; }
    .eg-stat-card.highlight::before       { background: rgba(255,255,255,0.08); opacity: 1; }
    .eg-stat-card.gold-card   { border-color: rgba(201,168,76,0.35); background: linear-gradient(135deg,#fdfaf3 0%,#fff9ed 100%); }
    .eg-stat-card.gold-card .eg-stat-num  { color: #8a6000; }
    .eg-stat-card.blue-card   { border-color: rgba(26,107,85,0.25); background: linear-gradient(135deg,#f0fdf8 0%,#e8f7f2 100%); }
    .eg-stat-card.blue-card .eg-stat-num  { color: var(--eg-mid); }
    /* ── KEY: paid-card styling for fully paid loans counter ── */
    .eg-stat-card.paid-card {
      border-color: rgba(201,168,76,0.50);
      background: linear-gradient(135deg, #0a3b2f 0%, #1a6b55 100%);
    }
    .eg-stat-card.paid-card::before { background: rgba(255,255,255,0.08); opacity: 1; }
    .eg-stat-card.paid-card .eg-stat-label { color: rgba(232,201,107,0.80); }
    .eg-stat-card.paid-card .eg-stat-num   { color: var(--eg-gold-l); }
    .eg-stat-card.paid-card .eg-stat-sub   { color: rgba(232,201,107,0.60); }
    .eg-stat-card.paid-card .eg-stat-icon  { background: rgba(255,255,255,0.15); }
    .eg-stat-card.paid-card .eg-stat-icon i { color: var(--eg-gold-l); }

    .eg-stat-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; background: var(--eg-light); margin-bottom: 14px; position: relative; z-index: 1; }
    .eg-stat-icon i { font-size: 18px; color: var(--eg-forest); }
    .eg-stat-card.highlight .eg-stat-icon { background: rgba(255,255,255,0.15); }
    .eg-stat-card.highlight .eg-stat-icon i { color: var(--eg-gold-l); }
    .eg-stat-card.gold-card .eg-stat-icon  { background: rgba(201,168,76,0.15); }
    .eg-stat-card.gold-card .eg-stat-icon i { color: var(--eg-gold); }
    .eg-stat-card.blue-card .eg-stat-icon  { background: rgba(26,107,85,0.12); }
    .eg-stat-card.blue-card .eg-stat-icon i { color: var(--eg-mid); }
    .eg-stat-label { font-size: 11.5px; color: var(--eg-muted); font-weight: 600; text-transform: uppercase; letter-spacing: .6px; margin-bottom: 6px; position: relative; z-index: 1; }
    .eg-stat-num   { font-size: 34px; font-weight: 800; color: var(--eg-forest); line-height: 1; margin-bottom: 4px; position: relative; z-index: 1; }
    .eg-stat-num.sm { font-size: 22px; }
    .eg-stat-sub   { font-size: 13px; color: var(--eg-muted); position: relative; z-index: 1; }

    /* ══ FILTER PANEL ══ */
    .eg-filter-panel { background: var(--eg-card); border: 1.5px solid var(--eg-border); border-radius: 14px; padding: 18px 20px; margin-bottom: 24px; }
    .eg-filter-panel .filter-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .7px; color: var(--eg-muted); margin-bottom: 5px; display: block; }
    .eg-filter-input, .eg-filter-select { width: 100%; padding: 9px 12px; border: 1.5px solid var(--eg-border); border-radius: 9px; font-family: 'DM Sans', sans-serif; font-size: 13.5px; color: var(--eg-text); background: var(--eg-bg); outline: none; transition: border-color .2s, box-shadow .2s; }
    .eg-filter-select { padding-right: 32px; -webkit-appearance: none; appearance: none; cursor: pointer; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='7' viewBox='0 0 10 7'%3E%3Cpath fill='none' stroke='%236b8c7e' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round' d='M1 1l4 4 4-4'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 10px center; }
    .eg-filter-input:focus, .eg-filter-select:focus { border-color: var(--eg-forest); box-shadow: 0 0 0 3px rgba(10,59,47,0.08); background: white; }
    .btn-filter { display: inline-flex; align-items: center; gap: 7px; background: linear-gradient(135deg,var(--eg-forest),var(--eg-mid)); color: #fff; border: none; border-radius: 9px; padding: 10px 20px; font-size: 13.5px; font-weight: 600; cursor: pointer; font-family: 'DM Sans',sans-serif; transition: all .2s; }
    .btn-filter:hover { transform: translateY(-1px); box-shadow: 0 4px 14px rgba(10,59,47,0.22); }
    .btn-clear  { display: inline-flex; align-items: center; gap: 6px; background: #f0f0f0; color: #555; border: none; border-radius: 9px; padding: 10px 16px; font-size: 13.5px; font-weight: 600; cursor: pointer; font-family: 'DM Sans',sans-serif; text-decoration: none; transition: background .15s; }
    .btn-clear:hover { background: #e0e0e0; color: #333; }

    /* ══ TABLE ══ */
    .eg-table-card { background: var(--eg-card); border-radius: 16px; box-shadow: 0 1px 6px rgba(10,59,47,0.06); border: 1.5px solid var(--eg-border); overflow: hidden; }
    .eg-table-header { padding: 16px 22px; border-bottom: 1px solid var(--eg-border); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; }
    .eg-table-header-title { font-family: 'Playfair Display',serif; font-size: 18px; font-weight: 700; color: var(--eg-forest); }
    .eg-table-header-sub { font-size: 12.5px; color: var(--eg-muted); margin-top: 2px; }
    .eg-table-meta { font-size: 12.5px; color: var(--eg-muted); }

    .eg-table { width: 100%; border-collapse: collapse; }
    .eg-table thead th { background: #f4f8f6; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .7px; color: var(--eg-muted); padding: 13px 18px; border-bottom: 1.5px solid var(--eg-border); white-space: nowrap; }
    .eg-table tbody tr { border-bottom: 1px solid #eef4f0; transition: background .15s; }
    .eg-table tbody tr:last-child { border-bottom: none; }
    .eg-table tbody tr:hover { background: #f8fcfa; }
    /* ── KEY: Green highlight for fully paid rows ── */
    .eg-table tbody tr.row-fully-paid { background: #f0faf6; }
    .eg-table tbody tr.row-fully-paid:hover { background: #e4f5ed; }
    .eg-table tbody td { padding: 13px 18px; font-size: 13.5px; color: var(--eg-text); vertical-align: middle; }

    .eg-borrower-cell { display: flex; align-items: center; gap: 10px; }
    .eg-borrower-avatar { width: 32px; height: 32px; border-radius: 50%; background: var(--eg-light); border: 1.5px solid var(--eg-border); display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; color: var(--eg-forest); flex-shrink: 0; }
    .row-fully-paid .eg-borrower-avatar { background: #c8e6d4; border-color: #a0d4b8; }
    .eg-borrower-name    { font-weight: 600; font-size: 13.5px; color: var(--eg-text); }
    .eg-borrower-account { font-size: 11.5px; color: var(--eg-muted); font-family: 'Courier New', monospace; }

    .eg-badge { display: inline-flex; align-items: center; gap: 5px; padding: 4px 11px; border-radius: 20px; font-size: 12px; font-weight: 700; letter-spacing: .3px; }
    .eg-badge.completed { background: var(--eg-light); color: var(--eg-forest); border: 1px solid var(--eg-border); }
    .eg-badge.completed::before { content: '●'; font-size: 8px; color: var(--eg-mid); }
    .eg-badge.pending { background: #fef9ec; color: #92640a; border: 1px solid #f5e0a0; }
    .eg-badge.pending::before { content: '●'; font-size: 8px; color: #d4a017; }
    .eg-badge.failed  { background: #fef0ef; color: #c0392b; border: 1px solid #f5c6c3; }
    .eg-badge.failed::before  { content: '●'; font-size: 8px; color: #c0392b; }

    /* ── KEY: Loan status badges ── */
    .loan-status-badge { display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; letter-spacing: .3px; }
    .loan-status-badge.closed {
      background: linear-gradient(90deg, #0a3b2f, #1a6b55);
      color: #e8c96b;
      border: 1px solid rgba(201,168,76,0.35);
    }
    .loan-status-badge.closed::before { content: '✓'; font-size: 10px; }
    .loan-status-badge.active   { background: #e8f4ef; color: var(--eg-forest); border: 1px solid var(--eg-border); }
    .loan-status-badge.active::before { content: '●'; font-size: 7px; color: var(--eg-mid); }
    .loan-status-badge.approved { background: #e8eeff; color: #1a4fce; border: 1px solid #c8d8f8; }
    .loan-status-badge.approved::before { content: '●'; font-size: 7px; color: #1a4fce; }
    .loan-status-badge.rejected { background: #fef0ef; color: #c0392b; border: 1px solid #f5c6c3; }
    .loan-status-badge.rejected::before { content: '●'; font-size: 7px; color: #c0392b; }

    /* ── KEY: Paid in full chip shown inside borrower cell ── */
    .paid-in-full-chip {
      display: inline-flex; align-items: center; gap: 4px;
      background: linear-gradient(90deg, #0a3b2f, #1a6b55);
      color: #e8c96b; font-size: 10px; font-weight: 700;
      padding: 2px 8px; border-radius: 6px; letter-spacing: .4px;
      text-transform: uppercase; margin-top: 3px;
    }

    .method-pill { display: inline-flex; align-items: center; gap: 5px; padding: 4px 11px; border-radius: 8px; font-size: 12px; font-weight: 600; }
    .method-pill.online { background: #eef4ff; color: #1a4fce; border: 1px solid #c8d8f8; }
    .method-pill.cheque { background: #fff5e6; color: #b06000; border: 1px solid #f5ddb0; }
    .code-pill { font-size: 10.5px; font-weight: 700; background: rgba(10,59,47,0.08); color: var(--eg-forest); padding: .15rem .5rem; border-radius: 5px; letter-spacing: .4px; text-transform: uppercase; }
    .txn-ref { font-family: 'Courier New', monospace; font-size: 12px; color: var(--eg-muted); word-break: break-all; }
    .txn-ref-id { font-size: 11px; color: #bbb; }

    .ua-wrap { position: relative; cursor: help; }
    .ua-short { font-size: 11px; color: var(--eg-muted); max-width: 120px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .ua-tooltip { display: none; position: absolute; bottom: calc(100% + 6px); left: 0; background: #1c2b25; color: #fff; padding: 7px 10px; border-radius: 8px; font-size: 11px; white-space: normal; min-width: 200px; max-width: 320px; z-index: 100; line-height: 1.4; }
    .ua-wrap:hover .ua-tooltip { display: block; }

    /* ══ PAGINATION ══ */
    .pg-wrap { display: flex; align-items: center; justify-content: space-between; padding: 14px 22px; border-top: 1px solid var(--eg-border); flex-wrap: wrap; gap: 10px; }
    .pg-info  { font-size: 13px; color: var(--eg-muted); }
    .pg-btns  { display: flex; gap: 5px; flex-wrap: wrap; }
    .pg-btn { display: inline-flex; align-items: center; justify-content: center; min-width: 34px; height: 34px; padding: 0 8px; border-radius: 8px; border: 1.5px solid var(--eg-border); background: #fff; color: var(--eg-text); font-size: 13px; font-weight: 600; text-decoration: none; transition: all .15s; }
    .pg-btn:hover   { background: var(--eg-light); border-color: var(--eg-mid); color: var(--eg-forest); }
    .pg-btn.active  { background: var(--eg-forest); border-color: var(--eg-forest); color: #fff; }
    .pg-btn.disabled{ opacity: .4; pointer-events: none; }

    .eg-empty { text-align: center; padding: 56px 20px; color: var(--eg-muted); }
    .eg-empty i { font-size: 44px; margin-bottom: 14px; display: block; opacity: .35; }
    .eg-empty p { font-size: 14px; }
    .eg-alert-error { background: #fdf0ef; border: 1px solid #f5c6c3; color: #c0392b; border-radius: 12px; padding: 14px 18px; font-size: 14px; margin-bottom: 24px; display: flex; align-items: center; gap: 10px; }
    .eg-alert-warn  { background: #fefbec; border: 1px solid #f5e0a0; color: #7a5200; border-radius: 12px; padding: 14px 18px; font-size: 14px; margin-bottom: 24px; display: flex; align-items: center; gap: 10px; }

    .quick-filter-bar { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 20px; }
    .qf-chip { display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 20px; font-size: 12.5px; font-weight: 600; text-decoration: none; transition: all .18s; border: 1.5px solid var(--eg-border); background: #fff; color: var(--eg-muted); }
    .qf-chip:hover { border-color: var(--eg-mid); color: var(--eg-forest); background: var(--eg-light); }
    .qf-chip.active { background: linear-gradient(90deg, var(--eg-forest), var(--eg-mid)); color: #e8c96b; border-color: transparent; }

    @media (max-width: 575px) { .eg-content { padding: 18px 14px 40px; } .eg-stat-num { font-size: 26px; } }
  </style>
</head>
<body>

<div class="eg-overlay" id="egOverlay" onclick="closeSidebar()"></div>

<!-- ══ SIDEBAR ══════════════════════════════════════════════════════════════ -->
<aside class="eg-sidebar" id="egSidebar">
  <a href="Employeedashboard.php" class="eg-sidebar-logo">
    <div class="eg-sidebar-logo-icon">
      <img src="pictures/logo.png" alt="Evergreen Logo"/>
    </div>
    <div>
      <div class="eg-sidebar-logo-text">EVERGREEN</div>
      <div class="eg-sidebar-logo-sub">Trust &amp; Savings</div>
    </div>
  </a>

  <div style="padding:10px 0;flex:1;">
    <button class="eg-nav-toggle-btn" id="navToggleBtn" onclick="toggleNav()">
      <i class="bi bi-grid-fill" style="font-size:11px;"></i>
      Navigation
      <i class="bi bi-chevron-down chevron" style="font-size:10px;"></i>
    </button>
    <div class="eg-nav-collapse" id="navCollapse">
      <?php foreach ($navItems as $item): ?>
        <a href="<?= htmlspecialchars($item['href']) ?>"
           class="eg-nav-item<?= $item['label'] === $activeNav ? ' active' : '' ?>">
          <i class="bi <?= htmlspecialchars($item['icon']) ?>"></i>
          <?= htmlspecialchars($item['label']) ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="eg-sidebar-footer">
    <div class="eg-sidebar-footer-user">
      <div class="eg-sidebar-avatar"><?= htmlspecialchars($adminInitials) ?></div>
      <div>
        <div class="eg-sidebar-uname"><?= $adminName ?></div>
        <div class="eg-sidebar-urole"><?= $adminRole ?></div>
      </div>
    </div>
  </div>
</aside>

<!-- ══ MAIN ═════════════════════════════════════════════════════════════════ -->
<div class="eg-main">

  <header class="eg-topbar">
    <div class="eg-topbar-left">
      <button class="eg-hamburger" onclick="toggleSidebar()">
        <i class="bi bi-list" id="hamburgerIcon"></i>
      </button>
      <div class="eg-topbar-brand">
        <div class="eg-tb-name">EVERGREEN</div>
        <div class="eg-tb-page">Manage Payments</div>
      </div>
      <div class="eg-breadcrumb">
        <span>Staff Portal</span>
        <span class="bc-sep"><i class="bi bi-chevron-right" style="font-size:10px;"></i></span>
        <a href="Employeedashboard.php" style="color:rgba(255,255,255,0.55);text-decoration:none;">Dashboard</a>
        <span class="bc-sep"><i class="bi bi-chevron-right" style="font-size:10px;"></i></span>
        <span class="bc-active">Manage Payments</span>
      </div>
    </div>

    <div class="eg-profile-wrap">
      <button class="eg-profile-btn" onclick="toggleProfileDropdown()">
        <div class="eg-avatar"><?= htmlspecialchars($adminInitials) ?></div>
        <div class="eg-profile-info">
          <div class="eg-profile-name"><?= $adminName ?></div>
          <div class="eg-profile-role"><?= $adminRole ?></div>
        </div>
        <i class="bi bi-chevron-down ms-1" style="font-size:11px;opacity:.7;"></i>
      </button>
      <div class="eg-profile-dropdown" id="profileDropdown">
        <div class="dd-header">
          <div class="dd-name"><?= $adminName ?></div>
          <div class="dd-empnum"><?= $adminEmpNum ?></div>
        </div>
        <div class="divider"></div>
        <a href="logout.php" class="logout-link">
          <i class="bi bi-box-arrow-right"></i> Sign Out
        </a>
      </div>
    </div>
  </header>

  <main class="eg-content">

    <div class="eg-page-header">
      <h1 class="eg-page-title">Manage Payments</h1>
      <p class="eg-page-sub">All borrower repayments &mdash; <?= number_format($stats['total']) ?> total transactions &nbsp;&middot;&nbsp; ₱<?= number_format($stats['totalAmount'],2) ?> collected</p>
    </div>

    <?php if ($error): ?>
    <div class="eg-alert-error"><i class="bi bi-exclamation-circle-fill"></i> <?= $error ?></div>
    <?php endif; ?>

    <?php if (!$tableExists && !$error): ?>
    <div class="eg-alert-warn">
      <i class="bi bi-exclamation-triangle-fill"></i>
      The <strong>loan_payments</strong> table does not exist yet.
    </div>
    <?php endif; ?>

    <!-- ── STAT CARDS ── -->
    <div class="row g-3 mb-4">
      <div class="col-6 col-sm-4 col-xl-2">
        <div class="eg-stat-card highlight">
          <div class="eg-stat-icon"><i class="bi bi-receipt-cutoff"></i></div>
          <div class="eg-stat-label">Total Transactions</div>
          <div class="eg-stat-num"><?= number_format($stats['total']) ?></div>
          <div class="eg-stat-sub">All time</div>
        </div>
      </div>
      <div class="col-6 col-sm-4 col-xl-2">
        <div class="eg-stat-card blue-card">
          <div class="eg-stat-icon"><i class="bi bi-cash-coin"></i></div>
          <div class="eg-stat-label">Total Collected</div>
          <div class="eg-stat-num sm">₱<?= number_format($stats['totalAmount'], 2) ?></div>
          <div class="eg-stat-sub">All completed</div>
        </div>
      </div>
      <div class="col-6 col-sm-4 col-xl-2">
        <div class="eg-stat-card">
          <div class="eg-stat-icon"><i class="bi bi-globe"></i></div>
          <div class="eg-stat-label">Online Payments</div>
          <div class="eg-stat-num"><?= number_format($stats['online']) ?></div>
          <div class="eg-stat-sub">Card</div>
        </div>
      </div>
      <div class="col-6 col-sm-4 col-xl-2">
        <div class="eg-stat-card gold-card">
          <div class="eg-stat-icon"><i class="bi bi-bank2"></i></div>
          <div class="eg-stat-label">Cheque Payments</div>
          <div class="eg-stat-num"><?= number_format($stats['cheque']) ?></div>
          <div class="eg-stat-sub">Bank cheque</div>
        </div>
      </div>
      <div class="col-6 col-sm-4 col-xl-2">
        <div class="eg-stat-card">
          <div class="eg-stat-icon"><i class="bi bi-calendar-check"></i></div>
          <div class="eg-stat-label">Today's Count</div>
          <div class="eg-stat-num"><?= number_format($stats['today']) ?></div>
          <div class="eg-stat-sub"><?= date('M d, Y') ?></div>
        </div>
      </div>
      <!-- ── KEY: Fully Paid / Closed Loans counter card ── -->
      <div class="col-6 col-sm-4 col-xl-2">
        <div class="eg-stat-card paid-card">
          <div class="eg-stat-icon"><i class="bi bi-patch-check-fill"></i></div>
          <div class="eg-stat-label">Fully Paid Loans</div>
          <div class="eg-stat-num"><?= number_format($stats['fullyPaid']) ?></div>
          <div class="eg-stat-sub">
            <?php if ($stats['fullyPaidAmt'] > 0): ?>
              ₱<?= number_format($stats['fullyPaidAmt'], 0) ?> settled
            <?php else: ?>
              Status: Closed
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- ── QUICK FILTER BAR ── -->
    <div class="quick-filter-bar">
      <a href="manage_payments.php"
         class="qf-chip <?= (!$filterLoanStatus && !$filterStatus) ? 'active' : '' ?>">
        <i class="bi bi-grid-fill"></i> All Payments
      </a>
      <a href="?loan_status=Closed"
         class="qf-chip <?= ($filterLoanStatus === 'Closed') ? 'active' : '' ?>">
        <i class="bi bi-patch-check-fill"></i>
        Fully Paid / Closed
        <?php if ($stats['fullyPaid'] > 0): ?>
          <span style="background:rgba(255,255,255,.25);border-radius:10px;padding:1px 7px;font-size:11px;">
            <?= $stats['fullyPaid'] ?>
          </span>
        <?php endif; ?>
      </a>
      <a href="?status=Completed"
         class="qf-chip <?= ($filterStatus === 'Completed' && !$filterLoanStatus) ? 'active' : '' ?>">
        <i class="bi bi-check-circle-fill"></i> Completed Only
      </a>
      <a href="?method=online"
         class="qf-chip <?= ($filterMethod === 'online' && !$filterLoanStatus && !$filterStatus) ? 'active' : '' ?>">
        <i class="bi bi-globe"></i> Online
      </a>
      <a href="?method=cheque"
         class="qf-chip <?= ($filterMethod === 'cheque' && !$filterLoanStatus && !$filterStatus) ? 'active' : '' ?>">
        <i class="bi bi-bank2"></i> Cheque
      </a>
    </div>

    <!-- ── FILTER PANEL ── -->
    <div class="eg-filter-panel">
      <form method="GET" action="">
        <div class="row g-3 align-items-end">
          <div class="col-12 col-sm-6 col-md-3">
            <label class="filter-label">Borrower Name</label>
            <input type="text" name="borrower" class="eg-filter-input"
                   placeholder="Search name…" value="<?= htmlspecialchars($filterBorrower) ?>">
          </div>
          <div class="col-12 col-sm-6 col-md-3">
            <label class="filter-label">Email</label>
            <input type="text" name="email" class="eg-filter-input"
                   placeholder="Search email…" value="<?= htmlspecialchars($filterEmail) ?>">
          </div>
          <div class="col-6 col-md-2">
            <label class="filter-label">Method</label>
            <select name="method" class="eg-filter-select">
              <option value="">All Methods</option>
              <option value="online" <?= $filterMethod==='online'?'selected':'' ?>>Online</option>
              <option value="cheque" <?= $filterMethod==='cheque'?'selected':'' ?>>Cheque</option>
            </select>
          </div>
          <div class="col-6 col-md-2">
            <label class="filter-label">Txn Status</label>
            <select name="status" class="eg-filter-select">
              <option value="">All Status</option>
              <option value="Completed" <?= $filterStatus==='Completed'?'selected':'' ?>>Completed</option>
              <option value="Pending"   <?= $filterStatus==='Pending'  ?'selected':'' ?>>Pending</option>
              <option value="Failed"    <?= $filterStatus==='Failed'   ?'selected':'' ?>>Failed</option>
            </select>
          </div>
          <div class="col-6 col-md-2">
            <label class="filter-label">Loan Status</label>
            <select name="loan_status" class="eg-filter-select">
              <option value="">All Loans</option>
              <option value="Active"   <?= $filterLoanStatus==='Active'  ?'selected':'' ?>>Active</option>
              <option value="Approved" <?= $filterLoanStatus==='Approved'?'selected':'' ?>>Approved</option>
              <option value="Closed"   <?= $filterLoanStatus==='Closed'  ?'selected':'' ?>>Closed / Paid</option>
            </select>
          </div>
          <div class="col-6 col-md-2">
            <label class="filter-label">From Date</label>
            <input type="date" name="from" class="eg-filter-input" value="<?= htmlspecialchars($filterFrom) ?>">
          </div>
          <div class="col-6 col-md-2">
            <label class="filter-label">To Date</label>
            <input type="date" name="to" class="eg-filter-input" value="<?= htmlspecialchars($filterTo) ?>">
          </div>
          <div class="col-6 col-md-2">
            <label class="filter-label">Loan App ID</label>
            <input type="number" name="loan_id" class="eg-filter-input"
                   placeholder="e.g. 5" value="<?= $filterLoanId > 0 ? $filterLoanId : '' ?>">
          </div>
          <div class="col-12 col-md-2 d-flex gap-2">
            <button type="submit" class="btn-filter"><i class="bi bi-search"></i> Search</button>
            <a href="manage_payments.php" class="btn-clear"><i class="bi bi-x-lg"></i></a>
          </div>
        </div>
      </form>
    </div>

    <!-- ── PAYMENTS TABLE ── -->
    <div class="eg-table-card">
      <div class="eg-table-header">
        <div>
          <div class="eg-table-header-title">Payment Records</div>
          <div class="eg-table-header-sub">
            <?php if ($filterBorrower || $filterEmail || $filterMethod || $filterStatus || $filterFrom || $filterTo || $filterLoanId || $filterLoanStatus): ?>
              Filtered: <?= number_format($totalRows) ?> record(s) &nbsp;&middot;&nbsp;
              <a href="manage_payments.php" style="color:var(--eg-muted);font-size:12px;">
                <i class="bi bi-x-circle me-1"></i>Clear filters
              </a>
            <?php else: ?>
              <?= number_format($totalRows) ?> total record(s)
            <?php endif; ?>
          </div>
        </div>
        <div class="eg-table-meta">Page <?= $page ?> of <?= $totalPages ?></div>
      </div>

      <?php if (empty($payments)): ?>
        <div class="eg-empty">
          <i class="bi bi-receipt"></i>
          <p>
            <?= $tableExists
              ? 'No payment records match your current filters.'
              : 'No records yet — the loan_payments table is empty.' ?>
          </p>
        </div>

      <?php else: ?>
      <div style="overflow-x:auto;">
        <table class="eg-table">
          <thead>
            <tr>
              <th style="width:50px;">#</th>
              <th>Transaction Ref</th>
              <th>Borrower</th>
              <th>Loan</th>
              <th>Amount</th>
              <th>Method</th>
              <th>Date &amp; Time</th>
              <th>Txn Status</th>
              <th>Loan Status</th>
              <th>IP / Agent</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($payments as $p):
              $initials = '';
              foreach (array_slice(explode(' ', trim($p['borrower_name'] ?? '')), 0, 2) as $w) {
                  $initials .= strtoupper($w[0] ?? '');
              }
              $payStatusLower  = strtolower($p['payment_status'] ?? 'completed');
              $loanStatusRaw   = $p['loan_status'] ?? 'Active';
              $loanStatusLower = strtolower($loanStatusRaw);
              $methodLower     = strtolower($p['payment_method']);

              // ── KEY: Is this loan fully paid? ───────────────────────────
              $isFullyPaid     = ($loanStatusLower === 'closed');

              $payDt    = $p['payment_date'] ? date('M d, Y', strtotime($p['payment_date'])) : '—';
              $payTm    = $p['payment_date'] ? date('H:i:s',  strtotime($p['payment_date'])) : '';
              $ua       = $p['user_agent'] ?? null;
              $uaShort  = $ua ? (strlen($ua) > 28 ? substr($ua, 0, 28) . '…' : $ua) : '—';
              $rowClass = $isFullyPaid ? ' row-fully-paid' : '';
            ?>
            <tr class="<?= $rowClass ?>">
              <td class="txn-ref-id">#<?= (int)$p['id'] ?></td>

              <td>
                <div class="txn-ref"><?= htmlspecialchars($p['transaction_ref']) ?></div>
                <div class="txn-ref-id">Loan App #<?= (int)$p['loan_application_id'] ?></div>
              </td>

              <td>
                <div class="eg-borrower-cell">
                  <div class="eg-borrower-avatar"><?= htmlspecialchars($initials ?: '?') ?></div>
                  <div>
                    <div class="eg-borrower-name"><?= htmlspecialchars($p['borrower_name'] ?? '—') ?></div>
                    <div class="eg-borrower-account"><?= htmlspecialchars($p['account_number'] ?? $p['user_email']) ?></div>
                    <?php if ($isFullyPaid): ?>
                      <!-- ── KEY: Paid in Full chip ── -->
                      <div class="paid-in-full-chip">
                        <i class="bi bi-patch-check-fill" style="font-size:9px;"></i>
                        Paid in Full
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              </td>

              <td>
                <div style="font-weight:600;font-size:13px;color:var(--eg-text);"><?= htmlspecialchars($p['loan_type'] ?? '—') ?></div>
                <?php if (!empty($p['loan_type_code'])): ?>
                  <span class="code-pill"><?= htmlspecialchars($p['loan_type_code']) ?></span>
                <?php endif; ?>
                <?php if (!empty($p['loan_amount'])): ?>
                  <div class="txn-ref-id" style="margin-top:2px;">Principal: ₱<?= number_format(floatval($p['loan_amount']), 2) ?></div>
                <?php endif; ?>
              </td>

              <td style="font-weight:700;font-size:14px;color:var(--eg-forest);white-space:nowrap;">
                ₱<?= number_format(floatval($p['amount']), 2) ?>
                <?php if ($isFullyPaid && !empty($p['total_paid_for_loan'])): ?>
                  <!-- ── KEY: Show cumulative total for closed loans ── -->
                  <div style="font-size:11px;color:var(--eg-mid);font-weight:500;margin-top:2px;">
                    Cumul: ₱<?= number_format(floatval($p['total_paid_for_loan']), 2) ?>
                  </div>
                <?php endif; ?>
              </td>

              <td>
                <span class="method-pill <?= $methodLower ?>">
                  <i class="bi bi-<?= $methodLower === 'online' ? 'globe' : 'bank2' ?>"></i>
                  <?= ucfirst($methodLower) ?>
                </span>
              </td>

              <td style="white-space:nowrap;">
                <div style="font-weight:600;font-size:13px;"><?= $payDt ?></div>
                <div style="font-size:12px;color:var(--eg-muted);"><i class="bi bi-clock me-1" style="font-size:10px;"></i><?= $payTm ?></div>
              </td>

              <td>
                <span class="eg-badge <?= $payStatusLower ?>"><?= htmlspecialchars($p['payment_status']) ?></span>
              </td>

              <!-- ── KEY: Loan status column — shows Closed/Paid with gold badge ── -->
              <td>
                <span class="loan-status-badge <?= $loanStatusLower ?>">
                  <?= $isFullyPaid ? 'Closed / Paid' : htmlspecialchars($loanStatusRaw) ?>
                </span>
              </td>

              <td>
                <div style="font-size:11.5px;color:var(--eg-muted);font-family:'Courier New',monospace;">
                  <?= htmlspecialchars($p['ip_address'] ?? '—') ?>
                </div>
                <?php if ($ua): ?>
                <div class="ua-wrap">
                  <div class="ua-short"><?= htmlspecialchars($uaShort) ?></div>
                  <div class="ua-tooltip"><?= htmlspecialchars($ua) ?></div>
                </div>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <?php if ($totalPages > 1): ?>
      <div class="pg-wrap">
        <div class="pg-info">
          Showing <?= number_format(($page - 1) * $perPage + 1) ?>–<?= number_format(min($page * $perPage, $totalRows)) ?> of <?= number_format($totalRows) ?>
        </div>
        <div class="pg-btns">
          <a href="<?= pgUrl(1) ?>"         class="pg-btn <?= $page <= 1 ? 'disabled' : '' ?>"><i class="bi bi-chevron-double-left"></i></a>
          <a href="<?= pgUrl($page - 1) ?>" class="pg-btn <?= $page <= 1 ? 'disabled' : '' ?>"><i class="bi bi-chevron-left"></i></a>
          <?php
          $start = max(1, $page - 2);
          $end   = min($totalPages, $page + 2);
          for ($pg = $start; $pg <= $end; $pg++): ?>
            <a href="<?= pgUrl($pg) ?>" class="pg-btn <?= $pg === $page ? 'active' : '' ?>"><?= $pg ?></a>
          <?php endfor; ?>
          <a href="<?= pgUrl($page + 1) ?>"  class="pg-btn <?= $page >= $totalPages ? 'disabled' : '' ?>"><i class="bi bi-chevron-right"></i></a>
          <a href="<?= pgUrl($totalPages) ?>" class="pg-btn <?= $page >= $totalPages ? 'disabled' : '' ?>"><i class="bi bi-chevron-double-right"></i></a>
        </div>
      </div>
      <?php endif; ?>

      <?php endif; ?>
    </div>

  </main>
</div>

<script>
  function toggleSidebar() {
    const sidebar = document.getElementById('egSidebar');
    const overlay = document.getElementById('egOverlay');
    const icon    = document.getElementById('hamburgerIcon');
    const isOpen  = sidebar.classList.toggle('open');
    overlay.classList.toggle('show', isOpen);
    icon.className = isOpen ? 'bi bi-x-lg' : 'bi bi-list';
  }
  function closeSidebar() {
    document.getElementById('egSidebar').classList.remove('open');
    document.getElementById('egOverlay').classList.remove('show');
    document.getElementById('hamburgerIcon').className = 'bi bi-list';
  }
  function toggleNav() {
    document.getElementById('navToggleBtn').classList.toggle('collapsed');
    document.getElementById('navCollapse').classList.toggle('hidden');
  }
  function toggleProfileDropdown() {
    document.getElementById('profileDropdown').classList.toggle('show');
  }
  document.addEventListener('click', function(e) {
    const wrap = document.querySelector('.eg-profile-wrap');
    if (wrap && !wrap.contains(e.target)) {
      document.getElementById('profileDropdown').classList.remove('show');
    }
  });
</script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
</body>
</html>