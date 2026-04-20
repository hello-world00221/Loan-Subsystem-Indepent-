<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us – Evergreen Trust and Savings</title>
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
            color: #fff;
            padding: 60px 20px 44px;
            text-align: center;
        }
        .hero-banner h1 {
            font-size: clamp(1.8rem, 4vw, 2.6rem);
            font-weight: 800;
            margin-bottom: 10px;
        }
        .hero-banner p {
            font-size: 1.05rem;
            opacity: .88;
            max-width: 600px;
            margin: 0 auto;
        }
        .section-card {
            background: #fff;
            border: 1px solid var(--eg-border);
            border-radius: 14px;
            box-shadow: 0 2px 14px rgba(0,54,49,.07);
            padding: 30px;
            margin-bottom: 22px;
        }
        .section-card h2 {
            color: var(--eg-dark);
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .section-card h2 i { color: var(--eg-light); }
        .stat-box {
            background: var(--eg-surface);
            border: 1px solid var(--eg-border);
            border-radius: 12px;
            padding: 20px 14px;
            text-align: center;
        }
        .stat-box .stat-num  { font-size: 1.9rem; font-weight: 800; color: var(--eg-dark); }
        .stat-box .stat-label { font-size: .83rem; color: #555; margin-top: 3px; }
        .feature-icon {
            width: 50px; height: 50px;
            border-radius: 11px;
            background: var(--eg-surface);
            border: 1px solid var(--eg-border);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem; color: var(--eg-light);
            flex-shrink: 0;
        }
        .team-card {
            background: var(--eg-surface);
            border: 1px solid var(--eg-border);
            border-radius: 12px;
            padding: 20px 14px;
            text-align: center;
        }
        .team-avatar {
            width: 58px; height: 58px;
            border-radius: 50%;
            background: var(--eg-dark);
            color: #fff; font-size: 1.2rem; font-weight: 700;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 11px;
        }
        .back-btn {
            background: #fff;
            color: var(--eg-dark);
            border: 2px solid var(--eg-dark);
            border-radius: 8px;
            padding: 9px 24px;
            font-weight: 700; font-size: .94rem;
            text-decoration: none;
            display: inline-flex; align-items: center; gap: 8px;
            transition: all .2s;
        }
        .back-btn:hover { background: var(--eg-dark); color: #fff; }
        .check-icon { color: var(--eg-accent); }
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
    <h1><i class="bi bi-bank2 me-2"></i>About Us</h1>
    <p>Learn about the Evergreen Trust and Savings Loan Subsystem — who we are, what we do, and how we serve you.</p>
</div>

<div class="container py-4" style="max-width:940px;">

    <div class="mb-4">
        <a href="index.php" class="back-btn"><i class="bi bi-arrow-left-circle"></i> Back to Home</a>
    </div>

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <?php
        $stats = [["5,000+","Loans Processed"],["98%","Client Satisfaction"],["24/7","System Availability"],["100%","Secure & Encrypted"]];
        foreach ($stats as $s):
        ?>
        <div class="col-6 col-md-3">
            <div class="stat-box">
                <div class="stat-num"><?= $s[0] ?></div>
                <div class="stat-label"><?= $s[1] ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Who We Are -->
    <div class="section-card">
        <h2><i class="bi bi-info-circle-fill"></i> Who We Are</h2>
        <p>Welcome to the <strong>Loan Subsystem</strong>, a core component of the <strong>Evergreen Trust and Savings Management System</strong>. Our platform is designed to simplify, automate, and enhance the entire loan management process — from application to approval, monitoring, and repayment.</p>
        <p class="mb-0">Whether you are a financial officer managing dozens of accounts or a borrower tracking your repayment schedule, our system is built to meet your needs with clarity and precision.</p>
    </div>

    <!-- Mission & Vision -->
    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <div class="section-card h-100">
                <h2><i class="bi bi-bullseye"></i> Our Mission</h2>
                <p class="mb-0">To provide a reliable and efficient digital solution that empowers financial institutions to manage loan services with accuracy, transparency, and speed — reducing manual processes while improving customer experience and operational productivity.</p>
            </div>
        </div>
        <div class="col-md-6">
            <div class="section-card h-100">
                <h2><i class="bi bi-eye-fill"></i> Our Vision</h2>
                <p class="mb-0">We envision a modern financial environment where loan processing is seamless, secure, and accessible — helping organizations deliver better financial services through technology and continuous innovation.</p>
            </div>
        </div>
    </div>

    <!-- What We Do -->
    <div class="section-card">
        <h2><i class="bi bi-gear-fill"></i> What We Do</h2>
        <div class="row g-3 mt-1">
            <?php
            $services = [
                ["bi-file-earmark-text-fill","Loan Application Management","Capture and process borrower information and loan requests through a structured digital form."],
                ["bi-check2-circle","Loan Evaluation & Approval","Streamline approval workflows with structured decision-making tools and configurable criteria."],
                ["bi-calendar2-check-fill","Amortization & Payment Tracking","Monitor repayments, generate schedules, and track outstanding balances in real time."],
                ["bi-shield-lock-fill","Secure User Access (RBAC)","Role-Based Access Control ensures proper permissions for administrators, officers, and borrowers."],
                ["bi-bar-chart-line-fill","Reporting & Analytics","Generate accurate reports, export loan histories, and gain insights into portfolio performance."],
                ["bi-bell-fill","Automated Notifications","Remind borrowers of due dates and alert staff of pending approvals or overdue accounts automatically."],
            ];
            foreach ($services as $s):
            ?>
            <div class="col-md-6">
                <div class="d-flex gap-3 align-items-start">
                    <div class="feature-icon"><i class="bi <?= $s[0] ?>"></i></div>
                    <div>
                        <div class="fw-bold mb-1" style="color:var(--eg-dark);font-size:.93rem;"><?= $s[1] ?></div>
                        <div style="font-size:.87rem;color:#555;"><?= $s[2] ?></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Key Features -->
    <div class="section-card">
        <h2><i class="bi bi-stars"></i> Key Features</h2>
        <div class="row g-2">
            <?php
            $features = [
                "User-friendly interface for staff and administrators",
                "Real-time loan status and payment updates",
                "Secure data handling and multi-layer authentication",
                "Integration-ready with existing core banking systems",
                "Customizable workflows for different loan types",
                "Mobile-responsive design accessible from any device",
                "Audit trail and activity logging for compliance",
                "Configurable interest rate and penalty computation",
            ];
            foreach ($features as $f):
            ?>
            <div class="col-md-6">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-check-circle-fill check-icon"></i>
                    <span style="font-size:.92rem;"><?= $f ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Our Team -->
    <div class="section-card">
        <h2><i class="bi bi-people-fill"></i> Our Team</h2>
        <p class="mb-4">Developed and maintained by a dedicated team of developers, financial system designers, and QA specialists committed to delivering high-quality, scalable, and secure solutions for the modern financial institution.</p>
        <div class="row g-3">
            <?php
            $team = [
                ["SD","System Developer","Handles core backend logic, database management, and API integrations."],
                ["FA","Financial Analyst","Ensures loan computation models align with institutional and regulatory standards."],
                ["UX","UI/UX Designer","Designs intuitive interfaces that improve staff efficiency and borrower experience."],
                ["QA","QA Engineer","Tests and validates all features to ensure reliability, security, and performance."],
            ];
            foreach ($team as $t):
            ?>
            <div class="col-6 col-md-3">
                <div class="team-card">
                    <div class="team-avatar"><?= $t[0] ?></div>
                    <div class="fw-bold" style="font-size:.92rem;color:var(--eg-dark);"><?= $t[1] ?></div>
                    <div style="font-size:.81rem;color:#666;margin-top:5px;"><?= $t[2] ?></div>
                </div>
            </div>
            <?php endforeach; ?>
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
    &nbsp;|&nbsp;<a href="FAQs.php">FAQs</a>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>