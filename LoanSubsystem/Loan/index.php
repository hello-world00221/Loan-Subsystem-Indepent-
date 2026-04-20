<?php
session_start();

// ─── SESSION BRIDGE ───────────────────────────────────────────────────────────
if (!isset($_SESSION['user_email'])) {
    if (isset($_SESSION['user_id']) && isset($_SESSION['email'])) {

        $host   = "localhost";
        $dbuser = "root";
        $dbpass = "";
        $dbname = "loandb";

        try {
            $pdo = new PDO(
                "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
                $dbuser, $dbpass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            $stmt = $pdo->prepare(
                "SELECT id, full_name, user_email, role
                 FROM users
                 WHERE id = ? AND user_email = ?
                 LIMIT 1"
            );
            $stmt->execute([$_SESSION['user_id'], $_SESSION['email']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $_SESSION['user_email'] = $user['user_email'];
                $_SESSION['user_name']  = $user['full_name'];
                $_SESSION['user_role']  = strtolower($user['role']);
            } else {
                session_destroy();
                header('Location: login.php');
                exit();
            }

        } catch (PDOException $e) {
            session_destroy();
            header('Location: login.php?error=db');
            exit();
        }

    } else {
        header('Location: login.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Evergreen Trust and Savings</title>
  <link rel="icon" type="logo/png" href="pictures/logo.png" />

  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <!-- Original stylesheet (kept for non-Bootstrap custom styles) -->
  <link rel="stylesheet" href="style.css">

  <style>
    /* ── Brand tokens ── */
    :root {
      --eg-dark:   #003631;
      --eg-mid:    #005a4d;
      --eg-light:  #00796b;
      --eg-accent: #1db57a;
    }

    /* ── Hero ── */
    .hero {
      min-height: 90vh;
      display: flex;
      align-items: center;
      padding: 5rem 1.5rem 3rem;
      background: linear-gradient(135deg, #f0faf6 0%, #e8f5f0 100%);
    }
    .hero-title  { font-size: clamp(2rem, 5vw, 3.5rem); font-weight: 800; color: var(--eg-dark); line-height: 1.15; }
    .hero-subtitle { font-size: clamp(1.2rem, 3vw, 2rem); font-weight: 700; color: var(--eg-mid); }
    .hero-description { color: #555; font-size: 1.05rem; max-width: 520px; }
    .hero-image img { width: 100%; max-width: 520px; border-radius: 1.5rem; }

    .btn-hero-primary {
      background: var(--eg-dark);
      color: #fff;
      border: none;
      padding: .75rem 2rem;
      border-radius: .6rem;
      font-weight: 600;
      transition: background .2s, transform .15s;
    }
    .btn-hero-primary:hover { background: var(--eg-mid); color: #fff; transform: translateY(-2px); }

    .btn-hero-secondary {
      background: transparent;
      color: var(--eg-dark);
      border: 2px solid var(--eg-dark);
      padding: .72rem 2rem;
      border-radius: .6rem;
      font-weight: 600;
      transition: all .2s;
    }
    .btn-hero-secondary:hover { background: var(--eg-dark); color: #fff; transform: translateY(-2px); }

    /* ── Footer ── */
    footer { background: var(--eg-dark); color: #cde8e1; padding: 2rem 1.5rem 1rem; }
    .footer-logo { width: 90px; margin-bottom: .75rem; }
    .footer-tagline { font-size: .87rem; color: #9abfba; line-height: 1.6; }
    .footer-col h3 { color: #fff; font-size: .92rem; font-weight: 700; text-transform: uppercase; letter-spacing: .8px; margin-bottom: .75rem; }
    .footer-col a  { color: #9abfba; text-decoration: none; font-size: .87rem; display: block; margin-bottom: .4rem; transition: color .15s; }
    .footer-col a:hover { color: #fff; }
    .social-links a { display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px; border-radius: 50%; background: rgba(255,255,255,.1); color: #fff; margin-right: .4rem; font-size: .85rem; transition: background .2s; }
    .social-links a:hover { background: rgba(255,255,255,.25); }
    .footer-divider { border-color: rgba(255,255,255,.1); margin: 1.5rem 0; }
    .footer-bottom { font-size: .8rem; color: #7aada6; }
    .footer-bottom a { color: #9abfba; text-decoration: none; }
    .footer-bottom a:hover { color: #fff; }

    /* ── Responsive tweaks ── */
    @media (max-width: 768px) {
      .hero { padding: 6rem 1.2rem 3rem; }
      .hero-image { margin-top: 2rem; text-align: center; }
    }
  </style>
</head>
<body>

<?php include 'header.php'; ?>

<!-- ═══════════════ HERO ═══════════════ -->
<section id="home" class="hero">
  <div class="container">
    <div class="row align-items-center">
      <div class="col-lg-6 mb-4 mb-lg-0">
        <h1 class="hero-title">EVERGREEN <span style="color:#00796b;">TRUST AND SAVINGS</span></h1>
        <h2 class="hero-subtitle mb-3">LOAN SERVICES</h2>
        <p class="hero-description mb-4">
          Bring your plans to life. Enjoy low interest rates and choose the financing option that suits your needs.
        </p>
        <div class="d-flex flex-wrap gap-3">
          <a href="ApplyLoan.php"  class="btn-hero-primary text-decoration-none">Apply for Loan</a>
          <a href="Dashboard.php"  class="btn-hero-secondary text-decoration-none">Go to Dashboard</a>
        </div>
      </div>
      <div class="col-lg-6 hero-image text-center">
        <img src="pictures/landing_page.png" alt="Apply for a Loan Easily" class="img-fluid">
      </div>
    </div>
  </div>
</section>

<!-- ═══════════════ FOOTER ═══════════════ -->
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
      <p class="mb-0">&copy; 2025 Evergreen Bank. All rights reserved.</p>
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

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>