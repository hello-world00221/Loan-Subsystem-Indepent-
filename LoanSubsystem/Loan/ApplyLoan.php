<?php
session_start();

// ─── AUTH GUARD ───────────────────────────────────────────────────────────────
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
  <title>Apply for a Loan – Evergreen Trust and Savings</title>
  <link rel="icon" type="logo/png" href="pictures/logo.png" />

  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <!-- Original stylesheet -->
  <link rel="stylesheet" href="style.css">

  <style>
    :root {
      --eg-dark:   #003631;
      --eg-mid:    #005a4d;
      --eg-light:  #00796b;
      --eg-accent: #1db57a;
    }

    body {
      background: #f0faf6;
    }

    /* ── Page hero banner ── */
    .apply-hero {
      background: linear-gradient(135deg, var(--eg-dark) 0%, var(--eg-mid) 60%, var(--eg-light) 100%);
      padding: 5.5rem 1.5rem 2rem;
      text-align: center;
      color: #fff;
      position: relative;
      overflow: hidden;
    }
    .apply-hero::before {
      content: '';
      position: absolute;
      inset: 0;
      background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
    }
    .apply-hero h1 {
      font-size: clamp(1.8rem, 4vw, 3rem);
      font-weight: 800;
      letter-spacing: -0.5px;
      margin-bottom: .5rem;
      position: relative;
    }
    .apply-hero p {
      font-size: 1.1rem;
      color: rgba(255,255,255,.8);
      max-width: 520px;
      margin: 0 auto;
      position: relative;
    }

    /* ── Loan Services Section ── */
    #loan-services {
      padding: 4rem 1.5rem 5rem;
    }
    .section-title {
      font-size: clamp(1.3rem, 3vw, 1.75rem);
      font-weight: 700;
      color: var(--eg-dark);
      letter-spacing: .5px;
    }

    /* ── Loan Cards ── */
    .loan-card {
      background: #fff;
      border: 1px solid #dceee8;
      border-radius: 1.25rem;
      overflow: hidden;
      cursor: pointer;
      transition: transform .25s, box-shadow .25s;
      position: relative;
      height: 100%;
    }
    .loan-card:hover {
      transform: translateY(-8px);
      box-shadow: 0 16px 40px rgba(0,54,49,.15);
    }

    .loan-card-thumb {
      width: 100%;
      height: 190px;
      overflow: hidden;
      flex-shrink: 0;
      display: block;
      background: #f0faf6;
    }
    .loan-card-thumb img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      object-position: center center;
      display: block;
      transition: transform .4s ease;
    }
    .loan-card:hover .loan-card-thumb img {
      transform: scale(1.05);
    }

    .loan-card-thumb.thumb-personal img { object-position: center 20%; }
    .loan-card-thumb.thumb-car      img { object-position: center center; }
    .loan-card-thumb.thumb-home     img { object-position: center center; }
    .loan-card-thumb.thumb-mpl      img { object-position: center 15%; }

    .loan-card-body {
      padding: 1.4rem 1.25rem 1.25rem;
    }
    .loan-card-title {
      font-weight: 700;
      color: var(--eg-dark);
      font-size: 1.08rem;
      margin-bottom: .4rem;
    }
    .loan-card-desc {
      color: #666;
      font-size: .92rem;
      margin: 0 0 1rem;
    }
    .loan-card-cta {
      display: inline-flex;
      align-items: center;
      gap: .4rem;
      font-size: .88rem;
      font-weight: 600;
      color: var(--eg-mid);
      text-decoration: none;
      transition: gap .2s, color .2s;
    }
    .loan-card:hover .loan-card-cta {
      gap: .7rem;
      color: var(--eg-dark);
    }

    /* Pending overlay */
    .loan-card.disabled {
      opacity: .5;
      cursor: not-allowed;
      pointer-events: none;
      filter: grayscale(50%);
    }
    .loan-card.disabled::after {
      content: 'Application Pending';
      position: absolute; top: 50%; left: 50%;
      transform: translate(-50%, -50%);
      background: rgba(0,0,0,.8);
      color: #fff;
      padding: .6rem 1.2rem;
      border-radius: .5rem;
      font-weight: 700;
      font-size: .85rem;
      z-index: 10;
      white-space: nowrap;
    }

    /* ── Info strip ── */
    .info-strip {
      background: var(--eg-dark);
      color: #fff;
      padding: 2.5rem 1.5rem;
    }
    .info-strip .info-item {
      text-align: center;
    }
    .info-strip .info-icon {
      font-size: 2rem;
      color: var(--eg-accent);
      margin-bottom: .5rem;
    }
    .info-strip .info-value {
      font-size: 1.4rem;
      font-weight: 800;
      color: #fff;
    }
    .info-strip .info-label {
      font-size: .82rem;
      color: rgba(255,255,255,.6);
      text-transform: uppercase;
      letter-spacing: .8px;
    }

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

    @media (max-width: 768px) {
      .apply-hero { padding: 6rem 1.2rem 3rem; }
    }
  </style>
</head>
<body>

<?php include 'header.php'; ?>

<!-- ═══════════════ PAGE HERO ═══════════════ -->
<div class="apply-hero">
  <h1>Apply for a Loan</h1>
  <p>Choose the loan product that best fits your needs and get started today.</p>
</div>

<!-- ═══════════════ LOAN SERVICES ═══════════════ -->
<section id="loan-services">
  <div class="container">
    <h2 class="section-title text-center mb-2">LOAN SERVICES WE OFFER</h2>
    <p class="text-center text-muted mb-5" style="font-size:.95rem;">Select a loan type below to begin your application.</p>

    <div class="row g-4" id="loanCardsRow">

      <div class="col-sm-6 col-xl-3">
        <div class="loan-card" onclick="window.location.href='Loan_AppForm.php?loanType=Personal%20Loan'">
          <div class="loan-card-thumb thumb-personal">
            <img src="pictures/personalloan.png" alt="Personal Loan">
          </div>
          <div class="loan-card-body">
            <p class="loan-card-title">Personal Loan</p>
            <p class="loan-card-desc">Stop worrying and bring your plans to life.</p>
            <span class="loan-card-cta">Apply Now <i class="fas fa-arrow-right"></i></span>
          </div>
        </div>
      </div>

      <div class="col-sm-6 col-xl-3">
        <div class="loan-card" onclick="window.location.href='Loan_AppForm.php?loanType=Vehicle%20Loan'">
          <div class="loan-card-thumb thumb-car">
            <img src="pictures/VehicleLoan.png" alt="Vehicle Loan">
          </div>
          <div class="loan-card-body">
            <p class="loan-card-title">Vehicle Loan</p>
            <p class="loan-card-desc">Drive your new car with low rates and fast approval.</p>
            <span class="loan-card-cta">Apply Now <i class="fas fa-arrow-right"></i></span>
          </div>
        </div>
      </div>

      <div class="col-sm-6 col-xl-3">
        <div class="loan-card" onclick="window.location.href='Loan_AppForm.php?loanType=Housing%20Loan'">
          <div class="loan-card-thumb thumb-home">
            <img src="pictures/housingloan.jpg" alt="Housing Loan">
          </div>
          <div class="loan-card-body">
            <p class="loan-card-title">Housing Loan</p>
            <p class="loan-card-desc">Take the first step to your new home.</p>
            <span class="loan-card-cta">Apply Now <i class="fas fa-arrow-right"></i></span>
          </div>
        </div>
      </div>

      <div class="col-sm-6 col-xl-3">
        <div class="loan-card" onclick="window.location.href='Loan_AppForm.php?loanType=Multi-Purpose%20Loan'">
          <div class="loan-card-thumb thumb-mpl">
            <img src="pictures/mpl.png" alt="Multi-Purpose Loan">
          </div>
          <div class="loan-card-body">
            <p class="loan-card-title">Multi-Purpose Loan</p>
            <p class="loan-card-desc">Use your property to fund your various needs.</p>
            <span class="loan-card-cta">Apply Now <i class="fas fa-arrow-right"></i></span>
          </div>
        </div>
      </div>
      
      <div class="col-sm-6 col-xl-3">
        <div class="loan-card" onclick="window.location.href='Loan_AppForm.php?loanType=Medical%20Loan'">
          <div class="loan-card-thumb thumb-medical">
            <img src="pictures/medical.jpg" alt="Medical Loan">
          </div>
          <div class="loan-card-body">
            <p class="loan-card-title">Medical Loan</p>
            <p class="loan-card-desc">Get the care you need without the financial stress.</p>
            <span class="loan-card-cta">Apply Now <i class="fas fa-arrow-right"></i></span>
          </div>
        </div>
      </div>  

      <div class="col-sm-6 col-xl-3">
        <div class="loan-card" onclick="window.location.href='Loan_AppForm.php?loanType=Education%20Loan'">
          <div class="loan-card-thumb thumb-education">
            <img src="pictures/images.jpg" alt="Education Loan">
          </div>
          <div class="loan-card-body">
            <p class="loan-card-title">Education Loan</p>
            <p class="loan-card-desc">Invest in your future with our flexible education loans.</p>
            <span class="loan-card-cta">Apply Now <i class="fas fa-arrow-right"></i></span>
          </div>
        </div>
      </div> 
      
      <div class="col-sm-6 col-xl-3">
        <div class="loan-card" onclick="window.location.href='Loan_AppForm.php?loanType=Salary%20Loan'">
          <div class="loan-card-thumb thumb-salary">
            <img src="pictures/salary.jpg" alt="Salary Loan">
          </div>
          <div class="loan-card-body">
            <p class="loan-card-title">Salary Loan</p>
            <p class="loan-card-desc">Get the funds you need to meet your financial obligations.</p>
            <span class="loan-card-cta">Apply Now <i class="fas fa-arrow-right"></i></span>
          </div>
        </div>
      </div> 

      <div class="col-sm-6 col-xl-3">
        <div class="loan-card" onclick="window.location.href='Loan_AppForm.php?loanType=Emergency%20Loan'">
          <div class="loan-card-thumb thumb-emergency">
            <img src="pictures/emergency.jpg" alt="Emergency Loan">
          </div>
          <div class="loan-card-body">
            <p class="loan-card-title">Emergency Loan</p>
            <p class="loan-card-desc">Get the funds you need for unexpected expenses.</p>
            <span class="loan-card-cta">Apply Now <i class="fas fa-arrow-right"></i></span>
          </div>
        </div>
      </div>

      <div class="col-sm-6 col-xl-3">
        <div class="loan-card" onclick="window.location.href='Loan_AppForm.php?loanType=Appliance%20Loan'">
          <div class="loan-card-thumb thumb-appliance">
            <img src="pictures/appliance.png" alt="Appliance Loan">
          </div>
          <div class="loan-card-body">
            <p class="loan-card-title">Appliance Loan</p>
            <p class="loan-card-desc">Get the funds you need to purchase essential appliances.</p>
            <span class="loan-card-cta">Apply Now <i class="fas fa-arrow-right"></i></span>
          </div>
        </div>
      </div>

    </div>
  </div>
</section>

<!-- ═══════════════ INFO STRIP ═══════════════ -->
<div class="info-strip">
  <div class="container">
    <div class="row g-4">
      <div class="col-6 col-md-3 info-item">
        <div class="info-icon"><i class="fas fa-percentage"></i></div>
        <div class="info-value">As Low as 1%</div>
        <div class="info-label">Monthly Interest</div>
      </div>
      <div class="col-6 col-md-3 info-item">
        <div class="info-icon"><i class="fas fa-clock"></i></div>
        <div class="info-value">24–48 hrs</div>
        <div class="info-label">Approval Time</div>
      </div>
      <div class="col-6 col-md-3 info-item">
        <div class="info-icon"><i class="fas fa-file-alt"></i></div>
        <div class="info-value">Minimal Docs</div>
        <div class="info-label">Requirements</div>
      </div>
      <div class="col-6 col-md-3 info-item">
        <div class="info-icon"><i class="fas fa-shield-alt"></i></div>
        <div class="info-value">100% Secure</div>
        <div class="info-label">Your Data is Safe</div>
      </div>
    </div>
  </div>
</div>

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

<script>
// Check for pending loans and disable cards if needed
document.addEventListener("DOMContentLoaded", async function () {
  try {
    const response = await fetch('fetch_loan.php', { method: 'GET', credentials: 'include' });
    if (!response.ok) return;
    const loans = await response.json();
    if (loans.error) return;

    const hasPendingLoan = loans.some(loan => loan.status === 'Pending');
    const loanCards = document.querySelectorAll('.loan-card');

    if (hasPendingLoan) {
      loanCards.forEach(card => {
        card.classList.add('disabled');
        card.onclick = null;
      });
    }
  } catch (e) {
    console.error('Error checking loan status:', e);
  }
});
</script>

</body>
</html>