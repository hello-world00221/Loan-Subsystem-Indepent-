<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FAQs – Evergreen Trust and Savings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --eg-dark:   #003631;
            --eg-mid:    #005a4d;
            --eg-light:  #00796b;
            --eg-accent: #1db57a;
            --eg-bg:     #f0faf6;
            --eg-surface:#e8f5f0;
            --eg-border: #c4e8da;
        }
        body {
            background-color: var(--eg-bg);
            font-family: 'Segoe UI', Arial, sans-serif;
            color: #333;
        }
        .hero-banner {
            background: linear-gradient(135deg, var(--eg-dark) 0%, var(--eg-mid) 100%);
            color: #fff; padding: 60px 20px 44px; text-align: center;
        }
        .hero-banner h1 {
            font-size: clamp(1.8rem, 4vw, 2.6rem);
            font-weight: 800; margin-bottom: 10px;
        }
        .hero-banner p { font-size: 1.05rem; opacity: .88; max-width: 600px; margin: 0 auto; }

        .section-card {
            background: #fff;
            border: 1px solid var(--eg-border);
            border-radius: 14px;
            box-shadow: 0 2px 14px rgba(0,54,49,.07);
            padding: 30px; margin-bottom: 22px;
        }
        .section-card h2 {
            color: var(--eg-dark); font-size: 1.2rem; font-weight: 700;
            margin-bottom: 18px; display: flex; align-items: center; gap: 10px;
        }
        .section-card h2 i { color: var(--eg-light); }

        /* Category badge */
        .category-badge {
            display: inline-flex; align-items: center; gap: 6px;
            background: var(--eg-surface);
            border: 1px solid var(--eg-border);
            color: var(--eg-dark);
            border-radius: 20px; padding: 5px 14px;
            font-size: .82rem; font-weight: 700;
            margin-bottom: 14px;
        }

        /* Accordion overrides to match green palette */
        .accordion-button {
            font-weight: 600; color: var(--eg-dark);
            background: #fff;
        }
        .accordion-button:not(.collapsed) {
            background-color: var(--eg-surface);
            color: var(--eg-dark);
            box-shadow: none;
        }
        .accordion-button::after {
            filter: none;
        }
        .accordion-button:focus { box-shadow: 0 0 0 3px rgba(0,121,107,.2); }
        .accordion-item {
            border: 1px solid var(--eg-border) !important;
            border-radius: 9px !important;
            margin-bottom: 9px; overflow: hidden;
        }
        .accordion-body { font-size: .94rem; color: #444; line-height: 1.65; }

        .back-btn {
            background: #fff; color: var(--eg-dark);
            border: 2px solid var(--eg-dark); border-radius: 8px;
            padding: 9px 24px; font-weight: 700; font-size: .94rem;
            text-decoration: none;
            display: inline-flex; align-items: center; gap: 8px;
            transition: all .2s;
        }
        .back-btn:hover { background: var(--eg-dark); color: #fff; }
        footer {
            background: var(--eg-dark); color: #cde8e1;
            text-align: center; padding: 20px;
            font-size: .88rem; margin-top: 40px;
        }
        footer a { color: #9abfba; text-decoration: none; }
        footer a:hover { color: #fff; }
    </style>
</head>
<body>

<div class="hero-banner">
    <h1><i class="bi bi-patch-question-fill me-2"></i>Frequently Asked Questions</h1>
    <p>Find answers to the most common questions about our Loan Subsystem services and processes.</p>
</div>

<div class="container py-4" style="max-width:860px;">

    <div class="mb-4">
        <a href="index.php" class="back-btn"><i class="bi bi-arrow-left-circle"></i> Back to Home</a>
    </div>

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

    <div class="text-center mt-2 mb-4">
        <a href="index.php" class="back-btn"><i class="bi bi-arrow-left-circle"></i> Back to Home</a>
    </div>
</div>

<footer>
    &copy; <?= date('Y') ?> Evergreen Trust and Savings Management System &mdash; Loan Subsystem. All rights reserved.
    &nbsp;|&nbsp;<a href="Privacy.php">Privacy Policy</a>
    &nbsp;|&nbsp;<a href="Terms.php">Terms</a>
    &nbsp;|&nbsp;<a href="AboutUs.php">About Us</a>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>