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
    <title>FAQs – Evergreen Trust and Savings</title>
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
        .page-hero p  { font-size: 1.05rem; opacity: .88; max-width: 600px; margin: 0 auto; }

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
            margin-bottom: 16px; display: flex; align-items: center; gap: 10px;
        }
        .section-card h2 i { color: var(--eg-light); }

        /* ── Category badge ── */
        .category-badge {
            display: inline-flex; align-items: center; gap: 6px;
            background: var(--eg-surface); border: 1px solid var(--eg-border);
            color: var(--eg-dark); border-radius: 20px;
            padding: 5px 14px; font-size: .82rem; font-weight: 700;
            margin-bottom: 14px;
        }

        /* ── Accordion ── */
        .accordion-button {
            font-weight: 600; color: var(--eg-dark);
            background: #fff; font-size: .95rem;
        }
        .accordion-button:not(.collapsed) {
            background-color: var(--eg-surface);
            color: var(--eg-dark);
            box-shadow: none;
        }
        .accordion-button:focus { box-shadow: 0 0 0 3px rgba(0,121,107,.2); }
        .accordion-item {
            border: 1px solid var(--eg-border) !important;
            border-radius: 9px !important;
            margin-bottom: 9px;
            overflow: hidden;
        }
        .accordion-body {
            font-size: .93rem; color: #444; line-height: 1.65;
            background: #fff;
        }

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
            .accordion-button { font-size: .88rem; }
        }
    </style>
</head>
<body>


<!-- Hero -->
<div class="page-hero">
    <h1><i class="bi bi-patch-question-fill me-2"></i>Frequently Asked Questions</h1>
    <p>Find answers to the most common questions about our Loan Subsystem services and processes.</p>
</div>

<div class="page-content">

    <a href="index.php" class="back-btn"><i class="bi bi-arrow-left-circle"></i> Back to Home</a>

    <!-- Loan Application -->
    <div class="section-card">
        <div class="category-badge"><i class="bi bi-file-earmark-text"></i> Loan Application</div>
        <h2><i class="bi bi-clipboard2-fill"></i> Applying for a Loan</h2>
        <div class="accordion" id="faqApp">
            <div class="accordion-item">
                <h3 class="accordion-header">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                        How do I apply for a loan?
                    </button>
                </h3>
                <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqApp">
                    <div class="accordion-body">
                        Log in to your account and fill out the <strong>Loan Application Form</strong>. Provide accurate personal and financial information, select your preferred loan type and amount, attach the required documents, and click <strong>Submit</strong>. Our staff will review your application and contact you with the outcome.
                    </div>
                </div>
            </div>
            <div class="accordion-item">
                <h3 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                        What documents are required for a loan application?
                    </button>
                </h3>
                <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqApp">
                    <div class="accordion-body">
                        Commonly required documents include: a valid government-issued ID, proof of income (payslip or certificate of employment), proof of billing address, and any co-maker or collateral documents depending on the loan type. Specific requirements may vary per loan category.
                    </div>
                </div>
            </div>
            <div class="accordion-item">
                <h3 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                        What types of loans are available?
                    </button>
                </h3>
                <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqApp">
                    <div class="accordion-body">
                        We offer <strong>Personal Loans</strong>, <strong>Car Loans</strong>, <strong>Home Loans</strong>, and <strong>Multi-Purpose Loans</strong>. Each type has its own eligibility criteria, interest rate, and repayment terms configured by your institution.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Approval -->
    <div class="section-card">
        <div class="category-badge"><i class="bi bi-check2-square"></i> Approval Process</div>
        <h2><i class="bi bi-check-circle-fill"></i> Loan Evaluation &amp; Approval</h2>
        <div class="accordion" id="faqApproval">
            <div class="accordion-item">
                <h3 class="accordion-header">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                        How long does approval take?
                    </button>
                </h3>
                <div id="faq4" class="accordion-collapse collapse show" data-bs-parent="#faqApproval">
                    <div class="accordion-body">
                        Approval time depends on the completeness of your application and the evaluation process. Typical processing takes <strong>3 to 5 business days</strong>. You will receive a notification once a decision has been made on your application.
                    </div>
                </div>
            </div>
            <div class="accordion-item">
                <h3 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq5">
                        What are the grounds for loan disapproval?
                    </button>
                </h3>
                <div id="faq5" class="accordion-collapse collapse" data-bs-parent="#faqApproval">
                    <div class="accordion-body">
                        Loans may be disapproved due to incomplete documentation, insufficient income relative to the requested amount, an existing delinquent loan, or failure to meet eligibility criteria. You may re-apply once the identified issues are resolved.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Payments -->
    <div class="section-card">
        <div class="category-badge"><i class="bi bi-cash-coin"></i> Payments &amp; Repayments</div>
        <h2><i class="bi bi-credit-card-fill"></i> Payments &amp; Repayment</h2>
        <div class="accordion" id="faqPay">
            <div class="accordion-item">
                <h3 class="accordion-header">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq6">
                        Can I track my loan status and payment history?
                    </button>
                </h3>
                <div id="faq6" class="accordion-collapse collapse show" data-bs-parent="#faqPay">
                    <div class="accordion-body">
                        Yes. Once logged in, you can view real-time updates on your loan status, outstanding balance, payment due dates, and complete payment history from your <strong>Loan Dashboard</strong>.
                    </div>
                </div>
            </div>
            <div class="accordion-item">
                <h3 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq7">
                        What happens if I miss a payment?
                    </button>
                </h3>
                <div id="faq7" class="accordion-collapse collapse" data-bs-parent="#faqPay">
                    <div class="accordion-body">
                        Missed payments may incur <strong>penalty charges</strong> as defined by your loan agreement and may negatively affect your loan standing within the institution. Please contact our support team immediately if you anticipate difficulty meeting a due date.
                    </div>
                </div>
            </div>
            <div class="accordion-item">
                <h3 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq8">
                        Can I pay my loan ahead of schedule?
                    </button>
                </h3>
                <div id="faq8" class="accordion-collapse collapse" data-bs-parent="#faqPay">
                    <div class="accordion-body">
                        Yes, early or advance payments are accepted. Any overpayment will be applied to the next due period or deducted from the principal balance, depending on your institution's policy. Contact your loan officer for details on prepayment terms.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Security -->
    <div class="section-card">
        <div class="category-badge"><i class="bi bi-shield-check"></i> Security &amp; Access</div>
        <h2><i class="bi bi-shield-lock-fill"></i> Security &amp; Account Access</h2>
        <div class="accordion" id="faqSec">
            <div class="accordion-item">
                <h3 class="accordion-header">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq9">
                        Is my data secure?
                    </button>
                </h3>
                <div id="faq9" class="accordion-collapse collapse show" data-bs-parent="#faqSec">
                    <div class="accordion-body">
                        Yes. The system uses <strong>secure authentication</strong>, <strong>data encryption</strong>, and <strong>Role-Based Access Control (RBAC)</strong> to ensure that only authorized personnel can access your records. All transactions are logged for audit purposes.
                    </div>
                </div>
            </div>
            <div class="accordion-item">
                <h3 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq10">
                        What should I do if I forget my password?
                    </button>
                </h3>
                <div id="faq10" class="accordion-collapse collapse" data-bs-parent="#faqSec">
                    <div class="accordion-body">
                        Use the <strong>Forgot Password</strong> link on the login page to request a password reset. A reset link will be sent to your registered email address. If you continue to experience access issues, contact your system administrator.
                    </div>
                </div>
            </div>
        </div>
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
