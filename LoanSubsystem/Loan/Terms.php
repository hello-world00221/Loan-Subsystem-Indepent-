<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms and Agreement – Evergreen Trust and Savings</title>
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
        body { background-color: var(--eg-bg); font-family: 'Segoe UI', Arial, sans-serif; color: #333; }
        .hero-banner {
            background: linear-gradient(135deg, var(--eg-dark) 0%, var(--eg-mid) 100%);
            color: #fff; padding: 60px 20px 44px; text-align: center;
        }
        .hero-banner h1 { font-size: clamp(1.8rem,4vw,2.6rem); font-weight: 800; margin-bottom: 10px; }
        .hero-banner p  { font-size: 1.05rem; opacity: .88; max-width: 620px; margin: 0 auto; }

        .section-card {
            background: #fff; border: 1px solid var(--eg-border);
            border-radius: 14px; box-shadow: 0 2px 14px rgba(0,54,49,.07);
            padding: 30px; margin-bottom: 22px;
        }
        .section-card h2 {
            color: var(--eg-dark); font-size: 1.15rem; font-weight: 700;
            margin-bottom: 12px; display: flex; align-items: center; gap: 10px;
        }
        .section-card h2 i { color: var(--eg-light); }

        /* Intro notice */
        .terms-intro {
            background: var(--eg-surface);
            border-left: 4px solid var(--eg-light);
            border-radius: 0 10px 10px 0;
            padding: 14px 18px; margin-bottom: 26px;
            font-size: .96rem; color: var(--eg-dark);
        }

        /* Effective date badge */
        .effective-date {
            background: #fff8e1; border: 1px solid #ffe082;
            border-radius: 8px; padding: 9px 16px;
            font-size: .86rem; color: #7a5800;
            display: inline-flex; align-items: center; gap: 8px;
            margin-bottom: 22px;
        }

        .list-styled li { margin-bottom: 8px; font-size: .94rem; }

        .back-btn {
            background: #fff; color: var(--eg-dark);
            border: 2px solid var(--eg-dark); border-radius: 8px;
            padding: 9px 24px; font-weight: 700; font-size: .94rem;
            text-decoration: none; display: inline-flex; align-items: center; gap: 8px;
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
    <h1><i class="bi bi-file-earmark-text-fill me-2"></i>Terms and Agreement</h1>
    <p>Please read these terms carefully before using the Loan Subsystem. Your continued use constitutes acceptance.</p>
</div>

<div class="container py-4" style="max-width:860px;">

    <div class="mb-4">
        <a href="index.php" class="back-btn"><i class="bi bi-arrow-left-circle"></i> Back to Home</a>
    </div>

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
        <p class="mb-0">The institution shall take reasonable measures to maintain system availability and data integrity at all times.</p>
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
        <p class="mb-0">These Terms and Agreement may be updated periodically to reflect changes in institutional policies, applicable laws, or system functionality. Users will be notified of significant updates through the system. Continued use of the system after notification constitutes acceptance of the revised terms.</p>
    </div>

    <div class="text-center mt-2 mb-4">
        <a href="index.php" class="back-btn"><i class="bi bi-arrow-left-circle"></i> Back to Home</a>
    </div>
</div>

<footer>
    &copy; <?= date('Y') ?> Evergreen Trust and Savings Management System &mdash; Loan Subsystem. All rights reserved.
    &nbsp;|&nbsp;<a href="Privacy.php">Privacy Policy</a>
    &nbsp;|&nbsp;<a href="FAQs.php">FAQs</a>
    &nbsp;|&nbsp;<a href="AboutUs.php">About Us</a>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>