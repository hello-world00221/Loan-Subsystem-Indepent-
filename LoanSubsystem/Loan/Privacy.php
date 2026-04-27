<?php 
session_start();
include 'header.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// DB connection
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

// Fetch user full name
$stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$full_name = $user['full_name'] ?? 'User';
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy – Evergreen Trust and Savings</title>
    <link rel="icon" type="image/png" href="pictures/logo.png"/>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        :root {
            --eg-dark:   #003631;
            --eg-mid:    #005a4d;
            --eg-light:  #00796b;
            --eg-accent: #1db57a;
            --eg-bg:     #f0faf6;
            --eg-surface:#e8f5f0;
            --eg-border: #c4e8da;
            --nav-h:     64px;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background-color: var(--eg-bg);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
            padding-top: var(--nav-h);
        }

        /* ── Hero Banner ── */
        .page-hero {
            background: linear-gradient(135deg, var(--eg-dark) 0%, var(--eg-mid) 100%);
            color: #fff;
            padding: 3.5rem 1.5rem 2.8rem;
            text-align: center;
        }
        .page-hero h1 { font-size: clamp(1.7rem, 4vw, 2.6rem); font-weight: 800; margin-bottom: .6rem; }
        .page-hero p  { font-size: 1.05rem; opacity: .88; max-width: 620px; margin: 0 auto; }

        /* ── Content ── */
        .page-content { max-width: 860px; margin: 0 auto; padding: 2rem 1.25rem 4rem; }

        .back-btn {
            background: #fff; color: var(--eg-dark);
            border: 2px solid var(--eg-dark); border-radius: 8px;
            padding: 9px 22px; font-weight: 700; font-size: .92rem;
            text-decoration: none; display: inline-flex; align-items: center; gap: 8px;
            transition: all .2s; margin-bottom: 1.5rem;
        }
        .back-btn:hover { background: var(--eg-dark); color: #fff; }

        .privacy-intro {
            background: var(--eg-surface);
            border-left: 4px solid var(--eg-light);
            border-radius: 0 10px 10px 0;
            padding: 14px 18px; margin-bottom: 1.5rem;
            font-size: .96rem; color: var(--eg-dark); line-height: 1.6;
        }

        .effective-date {
            background: #fff8e1; border: 1px solid #ffe082;
            border-radius: 8px; padding: 9px 16px;
            font-size: .86rem; color: #7a5800;
            display: inline-flex; align-items: center; gap: 8px;
            margin-bottom: 1.5rem;
        }

        /* ── Section Cards ── */
        .section-card {
            background: #fff; border: 1px solid var(--eg-border);
            border-radius: 14px; box-shadow: 0 2px 14px rgba(0,54,49,.07);
            padding: 1.75rem; margin-bottom: 1.1rem;
            transition: box-shadow .2s;
        }
        .section-card:hover { box-shadow: 0 4px 20px rgba(0,54,49,.12); }

        .section-card h2 {
            color: var(--eg-dark); font-size: 1.1rem; font-weight: 700;
            margin-bottom: 14px; display: flex; align-items: center; gap: 10px;
        }
        .section-card h2 i { color: var(--eg-light); }
        .section-card p { font-size: .95rem; color: #444; line-height: 1.65; margin-bottom: .75rem; }
        .section-card p:last-child { margin-bottom: 0; }

        /* ── Data type mini-cards ── */
        .data-type-card {
            background: var(--eg-surface);
            border: 1px solid var(--eg-border);
            border-radius: 11px; padding: 1.1rem; height: 100%;
            transition: box-shadow .2s;
        }
        .data-type-card:hover { box-shadow: 0 3px 12px rgba(0,54,49,.1); }
        .data-type-card .data-icon { font-size: 1.4rem; color: var(--eg-light); margin-bottom: 10px; }
        .data-type-card h4 { font-size: .9rem; font-weight: 700; color: var(--eg-dark); margin-bottom: 8px; }
        .data-type-card ul { font-size: .85rem; color: #555; padding-left: 16px; margin-bottom: 0; }
        .data-type-card ul li { margin-bottom: 4px; }
        .data-type-card ul li::marker { color: var(--eg-accent); }

        /* ── Security measures ── */
        .sec-icon-box {
            width: 44px; height: 44px; border-radius: 10px;
            background: var(--eg-surface); border: 1px solid var(--eg-border);
            display: flex; align-items: center; justify-content: center;
            color: var(--eg-light); font-size: 1.15rem; flex-shrink: 0;
        }

        /* ── Rights pills ── */
        .rights-pill {
            display: inline-flex; align-items: center; gap: 6px;
            background: var(--eg-surface); border: 1px solid var(--eg-border);
            color: var(--eg-dark); border-radius: 20px;
            padding: 6px 14px; font-size: .86rem; font-weight: 600; margin: 4px;
            transition: background .2s;
        }
        .rights-pill:hover { background: var(--eg-border); }

        .list-styled { padding-left: 1.25rem; margin-bottom: 0; }
        .list-styled li { margin-bottom: 8px; font-size: .93rem; color: #444; line-height: 1.6; }
        .list-styled li::marker { color: var(--eg-accent); }

        /* ── Footer ── */
        footer { background: var(--eg-dark); color: #cde8e1; padding: 2rem 1.5rem 1rem; }
        .footer-logo { width: 80px; margin-bottom: .75rem; }
        .footer-tagline { font-size: .87rem; color: #9abfba; line-height: 1.6; }
        .footer-col h3 { color: #fff; font-size: .9rem; font-weight: 700; text-transform: uppercase; letter-spacing: .8px; margin-bottom: .75rem; }
        .footer-col a { color: #9abfba; text-decoration: none; font-size: .87rem; display: block; margin-bottom: .4rem; transition: color .15s; }
        .footer-col a:hover { color: #fff; }
        .social-links a { display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px; border-radius: 50%; background: rgba(255,255,255,.1); color: #fff; margin-right: .4rem; font-size: .85rem; transition: background .2s; }
        .social-links a:hover { background: rgba(255,255,255,.25); }
        .footer-divider { border-color: rgba(255,255,255,.1); margin: 1.5rem 0; }
        .footer-bottom { font-size: .8rem; color: #7aada6; }
        .footer-bottom a { color: #9abfba; text-decoration: none; }
        .footer-bottom a:hover { color: #fff; }

        @media (max-width: 480px) {
            body { --nav-h: 58px; }
            .page-hero { padding: 2.5rem 1rem 2rem; }
            .page-content { padding: 1.5rem .9rem 3rem; }
            .section-card { padding: 1.25rem; }
        }
    </style>
</head>
<body>


<!-- Hero -->
<div class="page-hero">
    <h1><i class="bi bi-shield-fill-check me-2"></i>Privacy Policy</h1>
    <p>We are committed to protecting your personal and financial information. Learn how we collect, use, and safeguard your data.</p>
</div>

<div class="page-content">

    <a href="index.php" class="back-btn"><i class="bi bi-arrow-left-circle"></i> Back to Home</a>

    <div class="privacy-intro">
        <i class="bi bi-shield-check me-2"></i>
        The <strong>Loan Subsystem</strong> of Evergreen Trust and Savings Management System values your privacy and is fully committed to protecting your personal and financial information in accordance with applicable data protection laws and institutional policies.
    </div>

    <div class="effective-date">
        <i class="bi bi-calendar3"></i> Effective Date: January 1, <?= date('Y') ?>
    </div>

    <!-- Section 1 -->
    <div class="section-card">
        <h2><i class="bi bi-database-fill"></i> 1. Information We Collect</h2>
        <p class="mb-3">We collect only the information necessary to process your loan application and manage your account.</p>
        <div class="row g-3">
            <div class="col-md-4">
                <div class="data-type-card">
                    <div class="data-icon"><i class="bi bi-person-fill"></i></div>
                    <h4>Personal Details</h4>
                    <ul>
                        <li>Full name</li>
                        <li>Date of birth</li>
                        <li>Home &amp; mailing address</li>
                        <li>Contact number &amp; email</li>
                        <li>Government-issued ID</li>
                    </ul>
                </div>
            </div>
            <div class="col-md-4">
                <div class="data-type-card">
                    <div class="data-icon"><i class="bi bi-cash-coin"></i></div>
                    <h4>Financial Information</h4>
                    <ul>
                        <li>Monthly income</li>
                        <li>Employment details</li>
                        <li>Loan application data</li>
                        <li>Collateral information</li>
                        <li>Credit history (internal)</li>
                    </ul>
                </div>
            </div>
            <div class="col-md-4">
                <div class="data-type-card">
                    <div class="data-icon"><i class="bi bi-receipt"></i></div>
                    <h4>Transaction Records</h4>
                    <ul>
                        <li>Payment history</li>
                        <li>Repayment schedules</li>
                        <li>Outstanding balances</li>
                        <li>Penalty records</li>
                        <li>Disbursement logs</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Section 2 -->
    <div class="section-card">
        <h2><i class="bi bi-gear-fill"></i> 2. How We Use Your Information</h2>
        <ul class="list-styled">
            <li>To evaluate and process loan applications accurately and efficiently.</li>
            <li>To manage your loan account, repayment schedule, and outstanding balance.</li>
            <li>To send payment reminders, account notifications, and system updates.</li>
            <li>To generate financial reports and ensure institutional compliance.</li>
            <li>To improve system performance, security, and user experience.</li>
            <li>To comply with legal and regulatory obligations of the institution.</li>
        </ul>
    </div>

    <!-- Section 3 -->
    <div class="section-card">
        <h2><i class="bi bi-lock-fill"></i> 3. Data Protection &amp; Security</h2>
        <p>We implement strict technical and organizational measures to protect your data:</p>
        <div class="row g-3 mt-1">
            <?php
            $measures = [
                ["bi-shield-lock-fill","Data Encryption",         "All sensitive data is encrypted at rest and in transit using industry-standard protocols."],
                ["bi-person-badge-fill","Role-Based Access Control","Only authorized personnel with defined roles can access specific data within the system."],
                ["bi-journal-check",   "Audit Logging",            "All system access and data modifications are logged for traceability and accountability."],
                ["bi-wifi-off",        "Secure Authentication",    "Multi-step login procedures protect user accounts from unauthorized access."],
            ];
            foreach ($measures as $m):
            ?>
            <div class="col-md-6">
                <div class="d-flex gap-3 align-items-start">
                    <div class="sec-icon-box"><i class="bi <?= $m[0] ?>"></i></div>
                    <div>
                        <div class="fw-bold" style="color:var(--eg-dark);font-size:.92rem;"><?= $m[1] ?></div>
                        <div style="font-size:.87rem;color:#555;margin-top:3px;"><?= $m[2] ?></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Section 4 -->
    <div class="section-card">
        <h2><i class="bi bi-share-fill"></i> 4. Sharing of Information</h2>
        <p>We do <strong>not</strong> sell, trade, or rent your personal information to any third party. Your data may only be disclosed under the following limited circumstances:</p>
        <ul class="list-styled">
            <li>When required by law, court order, or government regulatory authority.</li>
            <li>For official financial processing with partner institutions or clearing bodies, as necessary.</li>
            <li>With express written consent from the data subject (the borrower or authorized account holder).</li>
        </ul>
    </div>

    <!-- Section 5 -->
    <div class="section-card">
        <h2><i class="bi bi-person-fill-check"></i> 5. Your Rights as a Data Subject</h2>
        <p>Under applicable data privacy regulations, you have the right to:</p>
        <div class="mt-2">
            <span class="rights-pill"><i class="bi bi-eye"></i> Access your data</span>
            <span class="rights-pill"><i class="bi bi-pencil"></i> Correct inaccurate data</span>
            <span class="rights-pill"><i class="bi bi-trash3"></i> Request data deletion</span>
            <span class="rights-pill"><i class="bi bi-x-circle"></i> Object to processing</span>
            <span class="rights-pill"><i class="bi bi-download"></i> Data portability</span>
            <span class="rights-pill"><i class="bi bi-bell-slash"></i> Withdraw consent</span>
        </div>
        <p class="mt-3 mb-0" style="font-size:.88rem;color:#666;">
            To exercise any of these rights, please contact the Data Privacy Officer of Evergreen Trust and Savings Management System through your institution's designated contact channels.
        </p>
    </div>

    <!-- Section 6 -->
    <div class="section-card">
        <h2><i class="bi bi-archive-fill"></i> 6. Data Retention</h2>
        <p>Your personal and financial data will be retained for as long as your account is active and for a period thereafter as required by law or institutional policy (typically 5 to 10 years after account closure). Data no longer needed will be securely deleted or anonymized.</p>
    </div>

    <!-- Section 7 -->
    <div class="section-card">
        <h2><i class="bi bi-arrow-repeat"></i> 7. Changes to This Policy</h2>
        <p>This Privacy Policy may be updated periodically to reflect changes in our practices, technology, or legal requirements. Users will be notified of significant changes through the system interface. Continued use of the system after such notification constitutes your acceptance of the updated policy.</p>
    </div>


</div>

<!-- Footer -->
<footer>
    <div class="container">
        <div class="row g-4 mb-4">
            <div class="col-lg-4 col-md-6">
                <img src="pictures/logo.png" alt="Evergreen Bank" class="footer-logo">
                <p class="footer-tagline">Secure. Invest. Achieve. Your trusted financial partner for a prosperous future.</p>
                <div class="social-links mt-3">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-linkedin-in"></i></a>
                </div>
            </div>
            <div class="col-lg-2 col-md-6 footer-col">
                <h3>Products</h3>
                <a href="#">Credit Cards</a>
                <a href="#">Debit Cards</a>
                <a href="#">Prepaid Cards</a>
            </div>
            <div class="col-lg-3 col-md-6 footer-col">
                <h3>Services</h3>
                <a href="#">Home Loans</a>
                <a href="#">Personal Loans</a>
                <a href="#">Auto Loans</a>
                <a href="#">Multipurpose Loans</a>
                <a href="#">Website Banking</a>
            </div>
            <div class="col-lg-3 col-md-6 footer-col">
                <h3>Contact Us</h3>
                <p style="font-size:.87rem; color:#9abfba;"><i class="fas fa-phone-alt me-2"></i>1-800-EVERGREEN</p>
                <p style="font-size:.87rem; color:#9abfba;"><i class="fas fa-envelope me-2"></i>support@evergreenbank.com</p>
                <p style="font-size:.87rem; color:#9abfba;"><i class="fas fa-map-marker-alt me-2"></i>123 Financial District, Suite 500, New York, NY 10004</p>
            </div>
        </div>
        <hr class="footer-divider">
        <div class="d-flex flex-wrap justify-content-between align-items-center footer-bottom gap-2">
            <p class="mb-0">&copy; <?= date('Y') ?> Evergreen Bank. All rights reserved.</p>
            <div class="d-flex flex-wrap gap-3">
                <a href="Privacy.php">Privacy Policy</a>
                <a href="Terms.php">Terms and Agreements</a>
                <a href="FAQs.php">FAQs</a>
                <a href="AboutUs.php">About Us</a>
            </div>
            <p class="mb-0">Member FDIC. Equal Housing Lender. Evergreen Bank, N.A.</p>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
