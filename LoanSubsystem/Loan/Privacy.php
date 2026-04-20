<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy – Evergreen Trust and Savings</title>
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

        .privacy-intro {
            background: var(--eg-surface);
            border-left: 4px solid var(--eg-light);
            border-radius: 0 10px 10px 0;
            padding: 14px 18px; margin-bottom: 26px;
            font-size: .96rem; color: var(--eg-dark);
        }
        .effective-date {
            background: #fff8e1; border: 1px solid #ffe082;
            border-radius: 8px; padding: 9px 16px;
            font-size: .86rem; color: #7a5800;
            display: inline-flex; align-items: center; gap: 8px;
            margin-bottom: 22px;
        }

        /* Data type mini-cards */
        .data-type-card {
            background: var(--eg-surface);
            border: 1px solid var(--eg-border);
            border-radius: 11px; padding: 18px; height: 100%;
        }
        .data-type-card .data-icon { font-size: 1.5rem; color: var(--eg-light); margin-bottom: 10px; }
        .data-type-card h4 { font-size: .93rem; font-weight: 700; color: var(--eg-dark); margin-bottom: 8px; }
        .data-type-card ul  { font-size: .86rem; color: #555; padding-left: 16px; margin-bottom: 0; }

        /* Security measure mini-cards */
        .sec-icon-box {
            width: 44px; height: 44px;
            border-radius: 10px;
            background: var(--eg-surface);
            border: 1px solid var(--eg-border);
            display: flex; align-items: center; justify-content: center;
            color: var(--eg-light); font-size: 1.15rem; flex-shrink: 0;
        }

        /* Rights pills */
        .rights-pill {
            display: inline-flex; align-items: center; gap: 6px;
            background: var(--eg-surface); border: 1px solid var(--eg-border);
            color: var(--eg-dark); border-radius: 20px;
            padding: 6px 14px; font-size: .86rem; font-weight: 600; margin: 4px;
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
    <h1><i class="bi bi-shield-fill-check me-2"></i>Privacy Policy</h1>
    <p>We are committed to protecting your personal and financial information. Learn how we collect, use, and safeguard your data.</p>
</div>

<div class="container py-4" style="max-width:860px;">

    <div class="mb-4">
        <a href="index.php" class="back-btn"><i class="bi bi-arrow-left-circle"></i> Back to Home</a>
    </div>

    <div class="privacy-intro">
        <i class="bi bi-shield-check me-2"></i>
        The <strong>Loan Subsystem</strong> of Evergreen Trust and Savings Management System values your privacy and is fully committed to protecting your personal and financial information in accordance with applicable data protection laws and institutional policies.
    </div>

    <div class="effective-date">
        <i class="bi bi-calendar3"></i> Effective Date: January 1, <?= date('Y') ?>
    </div>

    <!-- Section 1 – Data Collected -->
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

    <!-- Section 2 – How We Use It -->
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

    <!-- Section 3 – Data Protection -->
    <div class="section-card">
        <h2><i class="bi bi-lock-fill"></i> 3. Data Protection &amp; Security</h2>
        <p>We implement strict technical and organizational measures to protect your data:</p>
        <div class="row g-3 mt-1">
            <?php
            $measures = [
                ["bi-shield-lock-fill","Data Encryption",        "All sensitive data is encrypted at rest and in transit using industry-standard protocols."],
                ["bi-person-badge-fill","Role-Based Access Control","Only authorized personnel with defined roles can access specific data within the system."],
                ["bi-journal-check",   "Audit Logging",           "All system access and data modifications are logged for traceability and accountability."],
                ["bi-wifi-off",        "Secure Authentication",   "Multi-step login procedures protect user accounts from unauthorized access."],
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

    <!-- Section 4 – Sharing -->
    <div class="section-card">
        <h2><i class="bi bi-share-fill"></i> 4. Sharing of Information</h2>
        <p>We do <strong>not</strong> sell, trade, or rent your personal information to any third party. Your data may only be disclosed under the following limited circumstances:</p>
        <ul class="list-styled">
            <li>When required by law, court order, or government regulatory authority.</li>
            <li>For official financial processing with partner institutions or clearing bodies, as necessary.</li>
            <li>With express written consent from the data subject (the borrower or authorized account holder).</li>
        </ul>
    </div>

    <!-- Section 5 – Your Rights -->
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

    <!-- Section 6 – Retention -->
    <div class="section-card">
        <h2><i class="bi bi-archive-fill"></i> 6. Data Retention</h2>
        <p class="mb-0">Your personal and financial data will be retained for as long as your account is active and for a period thereafter as required by law or institutional policy (typically 5 to 10 years after account closure). Data no longer needed will be securely deleted or anonymized.</p>
    </div>

    <!-- Section 7 – Changes -->
    <div class="section-card">
        <h2><i class="bi bi-arrow-repeat"></i> 7. Changes to This Policy</h2>
        <p class="mb-0">This Privacy Policy may be updated periodically to reflect changes in our practices, technology, or legal requirements. Users will be notified of significant changes through the system interface. Continued use of the system after such notification constitutes your acceptance of the updated policy.</p>
    </div>

    <div class="text-center mt-2 mb-4">
        <a href="index.php" class="back-btn"><i class="bi bi-arrow-left-circle"></i> Back to Home</a>
    </div>
</div>

<footer>
    &copy; <?= date('Y') ?> Evergreen Trust and Savings Management System &mdash; Loan Subsystem. All rights reserved.
    &nbsp;|&nbsp;<a href="Terms.php">Terms</a>
    &nbsp;|&nbsp;<a href="FAQs.php">FAQs</a>
    &nbsp;|&nbsp;<a href="AboutUs.php">About Us</a>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>