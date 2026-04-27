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
    <title>About Us – Evergreen Trust and Savings</title>
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
        .page-content { max-width: 940px; margin: 0 auto; padding: 2rem 1.25rem 4rem; }

        .back-btn {
            background: #fff; color: var(--eg-dark);
            border: 2px solid var(--eg-dark); border-radius: 8px;
            padding: 9px 22px; font-weight: 700; font-size: .92rem;
            text-decoration: none; display: inline-flex; align-items: center; gap: 8px;
            transition: all .2s; margin-bottom: 1.5rem;
        }
        .back-btn:hover { background: var(--eg-dark); color: #fff; }

        /* ── Stat boxes ── */
        .stat-box {
            background: #fff;
            border: 1px solid var(--eg-border);
            border-radius: 12px;
            padding: 1.4rem 1rem;
            text-align: center;
            box-shadow: 0 2px 14px rgba(0,54,49,.07);
            transition: box-shadow .2s, transform .2s;
        }
        .stat-box:hover { box-shadow: 0 6px 20px rgba(0,54,49,.13); transform: translateY(-2px); }
        .stat-box .stat-num  { font-size: 2rem; font-weight: 800; color: var(--eg-dark); }
        .stat-box .stat-label { font-size: .85rem; color: #666; margin-top: 4px; }

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

        /* ── Feature icons ── */
        .feature-icon {
            width: 48px; height: 48px; border-radius: 11px;
            background: var(--eg-surface); border: 1px solid var(--eg-border);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem; color: var(--eg-light); flex-shrink: 0;
            transition: background .2s;
        }
        .feature-icon:hover { background: var(--eg-border); }

        /* ── Team cards ── */
        .team-card {
            background: var(--eg-surface);
            border: 1px solid var(--eg-border);
            border-radius: 12px; padding: 1.4rem 1rem;
            text-align: center;
            transition: box-shadow .2s, transform .2s;
        }
        .team-card:hover { box-shadow: 0 4px 16px rgba(0,54,49,.12); transform: translateY(-2px); }
        .team-avatar {
            width: 60px; height: 60px; border-radius: 50%;
            background: var(--eg-dark); color: #fff;
            font-size: 1.2rem; font-weight: 700;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 12px;
        }

        /* ── Check icons ── */
        .check-icon { color: var(--eg-accent); }

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
            .stat-box .stat-num { font-size: 1.6rem; }
        }
    </style>
</head>
<body>



<!-- Hero -->
<div class="page-hero">
    <h1><i class="bi bi-bank2 me-2"></i>About Us</h1>
    <p>Learn about the Evergreen Trust and Savings Loan Subsystem — who we are, what we do, and how we serve you.</p>
</div>

<div class="page-content">

    <a href="index.php" class="back-btn"><i class="bi bi-arrow-left-circle"></i> Back to Home</a>

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
        <p>Whether you are a financial officer managing dozens of accounts or a borrower tracking your repayment schedule, our system is built to meet your needs with clarity and precision.</p>
    </div>

    <!-- Mission & Vision -->
    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <div class="section-card h-100">
                <h2><i class="bi bi-bullseye"></i> Our Mission</h2>
                <p>To provide a reliable and efficient digital solution that empowers financial institutions to manage loan services with accuracy, transparency, and speed — reducing manual processes while improving customer experience and operational productivity.</p>
            </div>
        </div>
        <div class="col-md-6">
            <div class="section-card h-100">
                <h2><i class="bi bi-eye-fill"></i> Our Vision</h2>
                <p>We envision a modern financial environment where loan processing is seamless, secure, and accessible — helping organizations deliver better financial services through technology and continuous innovation.</p>
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
                    <i class="bi bi-check-circle-fill check-icon" style="flex-shrink:0;"></i>
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
