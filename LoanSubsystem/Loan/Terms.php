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
    <title>Terms and Agreement – Evergreen Trust and Savings</title>
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
        .page-hero h1 {
            font-size: clamp(1.7rem, 4vw, 2.6rem);
            font-weight: 800;
            margin-bottom: .6rem;
        }
        .page-hero p {
            font-size: 1.05rem;
            opacity: .88;
            max-width: 620px;
            margin: 0 auto;
        }

        /* ── Content ── */
        .page-content {
            max-width: 860px;
            margin: 0 auto;
            padding: 2rem 1.25rem 4rem;
        }

        /* ── Back button ── */
        .back-btn {
            background: #fff;
            color: var(--eg-dark);
            border: 2px solid var(--eg-dark);
            border-radius: 8px;
            padding: 9px 22px;
            font-weight: 700;
            font-size: .92rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all .2s;
            margin-bottom: 1.5rem;
        }
        .back-btn:hover { background: var(--eg-dark); color: #fff; }

        /* ── Intro notice ── */
        .terms-intro {
            background: var(--eg-surface);
            border-left: 4px solid var(--eg-light);
            border-radius: 0 10px 10px 0;
            padding: 14px 18px;
            margin-bottom: 1.5rem;
            font-size: .96rem;
            color: var(--eg-dark);
            line-height: 1.6;
        }

        /* ── Effective date ── */
        .effective-date {
            background: #fff8e1;
            border: 1px solid #ffe082;
            border-radius: 8px;
            padding: 9px 16px;
            font-size: .86rem;
            color: #7a5800;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 1.5rem;
        }

        /* ── Section Cards ── */
        .section-card {
            background: #fff;
            border: 1px solid var(--eg-border);
            border-radius: 14px;
            box-shadow: 0 2px 14px rgba(0,54,49,.07);
            padding: 1.75rem;
            margin-bottom: 1.1rem;
            transition: box-shadow .2s;
        }
        .section-card:hover { box-shadow: 0 4px 20px rgba(0,54,49,.12); }

        .section-card h2 {
            color: var(--eg-dark);
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .section-card h2 i { color: var(--eg-light); font-size: 1.05rem; }

        .section-card p { font-size: .95rem; color: #444; line-height: 1.65; margin-bottom: .75rem; }
        .section-card p:last-child { margin-bottom: 0; }

        .list-styled {
            padding-left: 1.25rem;
            margin-bottom: 0;
        }
        .list-styled li {
            margin-bottom: 8px;
            font-size: .93rem;
            color: #444;
            line-height: 1.6;
        }
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

        /* ── Responsive ── */
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
    <h1><i class="bi bi-file-earmark-text-fill me-2"></i>Terms and Agreement</h1>
    <p>Please read these terms carefully before using the Loan Subsystem. Your continued use constitutes acceptance.</p>
</div>

<div class="page-content">

    <a href="index.php" class="back-btn"><i class="bi bi-arrow-left-circle"></i> Back to Home</a>

    <div class="terms-intro">
        <i class="bi bi-info-circle-fill me-2"></i>
        By accessing and using the <strong>Loan Subsystem</strong> of Evergreen Trust and Savings Management System, you agree to comply with and be bound by the following terms and conditions. If you do not agree, please discontinue use of the system immediately.
    </div>

    <div class="effective-date">
        <i class="bi bi-calendar3"></i> Effective Date: January 1, <?= date('Y') ?>
    </div>

    <!-- Section 1 -->
    <div class="section-card">
        <h2><i class="bi bi-laptop"></i> 1. Use of the System</h2>
        <p>Access to and use of this system is granted strictly for authorized purposes within the institution. Users must:</p>
        <ul class="list-styled">
            <li>Provide accurate, complete, and up-to-date information in all forms and submissions.</li>
            <li>Maintain the confidentiality of their login credentials and never share account access with others.</li>
            <li>Refrain from unauthorized access, data manipulation, or any activity that disrupts system functionality.</li>
            <li>Use the system only within the scope of their assigned role and permissions.</li>
        </ul>
    </div>

    <!-- Section 2 -->
    <div class="section-card">
        <h2><i class="bi bi-file-earmark-check-fill"></i> 2. Loan Application &amp; Processing</h2>
        <p>All loan applications submitted through the system are subject to thorough review and evaluation by authorized loan officers. The system does <strong>not</strong> guarantee approval of any application.</p>
        <ul class="list-styled">
            <li>Submission of false or misleading information may result in immediate rejection and legal action.</li>
            <li>Approved loan amounts, terms, and interest rates are final as stated in the loan agreement.</li>
            <li>The institution reserves the right to modify loan terms in accordance with applicable regulations.</li>
        </ul>
    </div>

    <!-- Section 3 -->
    <div class="section-card">
        <h2><i class="bi bi-person-check-fill"></i> 3. User Responsibilities</h2>
        <ul class="list-styled">
            <li>Ensure the correctness and completeness of all data submitted through the system.</li>
            <li>Comply with the repayment schedule as outlined in the loan agreement.</li>
            <li>Promptly notify the institution of any changes in personal or financial information.</li>
            <li>Follow all institutional policies, internal procedures, and applicable laws and regulations.</li>
            <li>Report any unauthorized access to your account immediately to the system administrator.</li>
        </ul>
    </div>

    <!-- Section 4 -->
    <div class="section-card">
        <h2><i class="bi bi-cash-stack"></i> 4. Payment Obligations &amp; Penalties</h2>
        <p>Borrowers are required to meet repayment obligations on or before the specified due dates. Failure to comply will result in:</p>
        <ul class="list-styled">
            <li>Penalty charges as stipulated in the signed loan agreement will be applied.</li>
            <li>Delinquent accounts may be flagged and reported to relevant financial oversight bodies.</li>
            <li>Continued non-payment may result in legal collection proceedings.</li>
            <li>Outstanding balances will accrue interest as per the agreed schedule until fully settled.</li>
        </ul>
    </div>

    <!-- Section 5 -->
    <div class="section-card">
        <h2><i class="bi bi-exclamation-triangle-fill"></i> 5. Limitation of Liability</h2>
        <p>The system administrators, institution management, and development team are not liable for:</p>
        <ul class="list-styled">
            <li>Losses or damages caused by incorrect data entry by the user.</li>
            <li>System downtime caused by factors beyond the control of the institution (e.g., force majeure, infrastructure failure).</li>
            <li>Any unauthorized access resulting from the user's failure to secure their account credentials.</li>
        </ul>
        <p>The institution shall take reasonable measures to maintain system availability and data integrity at all times.</p>
    </div>

    <!-- Section 6 -->
    <div class="section-card">
        <h2><i class="bi bi-x-circle-fill"></i> 6. Account Suspension &amp; Termination</h2>
        <p>The institution reserves the right to suspend or permanently terminate a user's access under any of the following circumstances:</p>
        <ul class="list-styled">
            <li>Violation of any provision outlined in these Terms and Agreement.</li>
            <li>Submission of fraudulent information or documents.</li>
            <li>Repeated non-compliance with repayment obligations without valid justification.</li>
            <li>Any activity deemed harmful to the system, other users, or the institution.</li>
        </ul>
    </div>

    <!-- Section 7 -->
    <div class="section-card">
        <h2><i class="bi bi-arrow-repeat"></i> 7. Amendments to These Terms</h2>
        <p>These Terms and Agreement may be updated periodically to reflect changes in institutional policies, applicable laws, or system functionality. Users will be notified of significant updates through the system. Continued use of the system after notification constitutes acceptance of the revised terms.</p>
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
