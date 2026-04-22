<?php
$userInitials = 'U';
$displayName  = 'Guest';

$name = $_SESSION['user_name'] ?? $_SESSION['full_name'] ?? null;

if (!empty($name)) {
    $displayName  = $name;
    $nameParts    = explode(' ', trim($name));
    $firstInitial = strtoupper($nameParts[0][0] ?? 'U');
    $lastInitial  = strtoupper(end($nameParts)[0] ?? '');
    $userInitials = $firstInitial . $lastInitial;
}
?>

<style>
*, *::before, *::after {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    overflow-x: hidden;
}

/* ── Navigation ── */
nav {
    position: fixed;
    top: 0;
    width: 100%;
    background: #003631;
    padding: 0.85rem 5%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    z-index: 1000;
    box-shadow: 0 2px 10px rgba(0,0,0,0.15);
    min-height: 64px;
}

/* ── Logo ── */
.logo {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: white;
    font-size: 1.2rem;
    font-weight: bold;
    text-decoration: none;
    flex-shrink: 0;
}

.logo-icon {
    width: 46px;
    height: 46px;
    background: transparent;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    flex-shrink: 0;
}

.logo-icon img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    object-position: center;
    display: block;
    border-radius: 50%;
}

/* ── Nav links (desktop) ── */
.nav-links {
    display: flex;
    gap: 0.25rem;
    align-items: center;
}

.nav-links a {
    color: white;
    text-decoration: none;
    padding: 0.5rem 1rem;
    font-size: 0.95rem;
    transition: color 0.3s;
    position: relative;
    white-space: nowrap;
}

.nav-links a:hover {
    color: #F1B24A;
}

.nav-links a::after {
    content: '';
    position: absolute;
    bottom: -3px;
    left: 1rem;
    right: 1rem;
    height: 2px;
    background-color: #F1B24A;
    width: 0;
    transition: width 0.3s ease;
}

.nav-links a:hover::after {
    width: calc(100% - 2rem);
}

/* ── Dropdown (kept for compatibility) ── */
.dropdown { position: relative; }

.dropbtn {
    background: none;
    border: none;
    color: white;
    font-size: 0.95rem;
    cursor: pointer;
    padding: 0.5rem 1rem;
    transition: color 0.3s;
    white-space: nowrap;
}

.dropbtn:hover { color: #F1B24A; }

.dropdown-content {
    display: none;
    position: fixed;
    left: 0;
    top: 64px;
    width: 100vw;
    background-color: #D9D9D9;
    padding: 1.5rem 5%;
    box-shadow: 0 8px 16px rgba(0,0,0,0.15);
    z-index: 99;
    text-align: center;
}

.dropdown-content a {
    color: #003631;
    margin: 0 2rem;
    font-size: 1rem;
    text-decoration: none;
    display: inline-block;
    padding: 0.5rem 1rem;
    transition: all 0.3s ease;
    font-weight: 500;
}

.dropdown-content a:hover {
    color: #F1B24A;
    transform: translateY(-2px);
}

/* ── Right side: profile + hamburger ── */
.nav-right {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

/* ── Profile area ── */
.profile-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    position: relative;
}

.username-profile {
    background: transparent;
    color: #FFFFFF;
    text-decoration: none;
    display: flex;
    align-items: center;
    padding: 0.4rem 0.75rem;
    border-radius: 5px;
    font-weight: 500;
    font-size: 0.9rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 300px;
}

.username-profile:hover { color: #F1B24A; }

.profile-btn {
    width: 38px;
    height: 38px;
    background: transparent;
    border: none;
    padding: 0;
    cursor: pointer;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: opacity 0.2s ease;
    flex-shrink: 0;
}

.profile-btn:hover { opacity: 0.85; }

.profile-btn img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 50%;
    background-color: #003631;
    display: block;
}

.avatar {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background-color: rgba(255,255,255,0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    font-size: 14px;
    cursor: pointer;
    transition: opacity 0.2s ease;
    flex-shrink: 0;
}

.avatar:hover { opacity: 0.85; }

.profile-dropdown {
    display: none;
    position: absolute;
    right: 0;
    top: calc(100% + 8px);
    background: #D9D9D9;
    color: #003631;
    border-radius: 8px;
    box-shadow: 0 6px 18px rgba(0,0,0,0.12);
    min-width: 140px;
    z-index: 200;
}

.profile-dropdown a {
    display: block;
    padding: 0.65rem 1rem;
    color: #003631;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.9rem;
}

.profile-dropdown a:hover { background: rgba(0,0,0,0.06); }
.profile-dropdown.show { display: block; }

/* ── Hamburger button ── */
.hamburger {
    display: none;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    width: 38px;
    height: 38px;
    background: none;
    border: none;
    cursor: pointer;
    padding: 4px;
    gap: 5px;
    border-radius: 6px;
    transition: background 0.2s;
    flex-shrink: 0;
}

.hamburger:hover { background: rgba(255,255,255,0.1); }

.hamburger span {
    display: block;
    width: 22px;
    height: 2px;
    background: white;
    border-radius: 2px;
    transition: transform 0.3s ease, opacity 0.3s ease;
    transform-origin: center;
}

.hamburger.open span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
.hamburger.open span:nth-child(2) { opacity: 0; transform: scaleX(0); }
.hamburger.open span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }

/* ── Mobile drawer ── */
.mobile-menu {
    display: none;
    position: fixed;
    top: 64px;
    left: 0;
    width: 100%;
    background: #003631;
    z-index: 998;
    flex-direction: column;
    padding: 0.5rem 0 1rem;
    box-shadow: 0 8px 20px rgba(0,0,0,0.2);
    border-top: 1px solid rgba(255,255,255,0.08);
    transform: translateY(-8px);
    opacity: 0;
    transition: transform 0.25s ease, opacity 0.25s ease;
}

.mobile-menu.open {
    display: flex;
    transform: translateY(0);
    opacity: 1;
}

.mobile-menu a {
    color: white;
    text-decoration: none;
    padding: 0.85rem 5%;
    font-size: 1rem;
    font-weight: 500;
    border-bottom: 1px solid rgba(255,255,255,0.06);
    transition: background 0.2s, color 0.2s;
    display: flex;
    align-items: center;
}

.mobile-menu a:last-child { border-bottom: none; }
.mobile-menu a:hover { background: rgba(255,255,255,0.06); color: #F1B24A; }

.mobile-menu .mobile-logout {
    color: #F1B24A;
    margin-top: 0.25rem;
}

.mobile-user-greeting {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 1rem 5%;
    border-bottom: 1px solid rgba(255,255,255,0.12);
    margin-bottom: 0.25rem;
}

.mobile-user-greeting span {
    color: white;
    font-weight: 600;
    font-size: 0.95rem;
    word-break: break-word;
}

.mobile-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background-color: rgba(255,255,255,0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    font-size: 13px;
    flex-shrink: 0;
}

/* ── Overlay for mobile menu ── */
.nav-overlay {
    display: none;
    position: fixed;
    inset: 0;
    top: 64px;
    background: rgba(0,0,0,0.35);
    z-index: 997;
}

.nav-overlay.open { display: block; }

/* ════════════════════════════════════════
   RESPONSIVE BREAKPOINTS
   ════════════════════════════════════════ */

/* Tablet: 769px – 960px — compact nav links */
@media (max-width: 960px) {
    nav { padding: 0.85rem 4%; }
    .nav-links a { padding: 0.5rem 0.6rem; font-size: 0.88rem; }
    .username-profile { max-width: 200px; font-size: 0.85rem; padding: 0.4rem 0.5rem; }
}

/* Tablet portrait: hide nav links, show hamburger; keep username but shrink */
@media (max-width: 768px) {
    .nav-links { display: none; }
    .hamburger { display: flex; }
    .username-profile { max-width: 180px; font-size: 0.85rem; display: flex; }
}

/* Mobile: hide username text, show only avatar */
@media (max-width: 480px) {
    nav { padding: 0.75rem 4%; min-height: 58px; }
    .logo-icon { width: 38px; height: 38px; }
    .logo { font-size: 1.05rem; gap: 0.4rem; }
    .mobile-menu { top: 58px; }
    .nav-overlay { top: 58px; }
    .avatar { width: 34px; height: 34px; font-size: 12px; }
    .username-profile { display: none; }
}

/* Very small: 360px and below */
@media (max-width: 360px) {
    .logo { font-size: 0.95rem; }
    .logo-icon { width: 34px; height: 34px; }
    nav { padding: 0.7rem 3.5%; }
}
</style>

<!-- Dynamic Header -->
<nav id="mainNav">
    <a href="index.php" class="logo">
        <div class="logo-icon">
            <img src="pictures/logo.png" alt="Evergreen Logo">
        </div>
        <span>EVERGREEN</span>
    </a>

    <!-- Desktop nav links -->
    <div class="nav-links">
        <a href="index.php">Home</a>
        <a href="ApplyLoan.php">Loans</a>
        <a href="Dashboard.php">Dashboard</a>
        <a href="AboutUs.php">About Us</a>
    </div>

    <!-- Right side: profile + hamburger -->
    <div class="nav-right">
        <div class="profile-actions">
            <span class="username-profile"><?= htmlspecialchars($displayName) ?></span>

            <?php if ($displayName !== 'Guest'): ?>
                <button class="profile-btn" id="profileBtn" onclick="toggleProfileDropdown(event)" aria-expanded="false" aria-label="Account menu">
                    <div class="avatar"><?= htmlspecialchars($userInitials) ?></div>
                </button>
                
                <div class="profile-dropdown" id="profileDropdown" role="menu">
                    <a href="profile.php" role="menuitem">My Profile</a>
                    <a href="logout.php" role="menuitem">Logout</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Hamburger (mobile only) -->
        <button class="hamburger" id="hamburgerBtn" aria-label="Toggle navigation menu" aria-expanded="false">
            <span></span>
            <span></span>
            <span></span>
        </button>
    </div>
</nav>

<!-- Mobile drawer -->
<div class="mobile-menu" id="mobileMenu" role="navigation" aria-label="Mobile navigation">
    <?php if ($displayName !== 'Guest'): ?>
    <div class="mobile-user-greeting">
        <div class="mobile-avatar"><?= htmlspecialchars($userInitials) ?></div>
        <span><?= htmlspecialchars($displayName) ?></span>
    </div>
    <?php endif; ?>
    <a href="index.php">Home</a>
    <a href="ApplyLoan.php">Loans</a>
    <a href="Dashboard.php">Dashboard</a>
    <a href="AboutUs.php">About Us</a>
    <?php if ($displayName !== 'Guest'): ?>
        <a href="logout.php" class="mobile-logout">Logout</a>
    <?php endif; ?>
</div>

<!-- Overlay (closes mobile menu on tap) -->
<div class="nav-overlay" id="navOverlay" onclick="closeMobileMenu()"></div>

<script>
/* ── Hamburger / mobile menu ── */
function openMobileMenu() {
    document.getElementById('mobileMenu').classList.add('open');
    document.getElementById('navOverlay').classList.add('open');
    document.getElementById('hamburgerBtn').classList.add('open');
    document.getElementById('hamburgerBtn').setAttribute('aria-expanded', 'true');
    document.body.style.overflow = 'hidden';
}

function closeMobileMenu() {
    document.getElementById('mobileMenu').classList.remove('open');
    document.getElementById('navOverlay').classList.remove('open');
    document.getElementById('hamburgerBtn').classList.remove('open');
    document.getElementById('hamburgerBtn').setAttribute('aria-expanded', 'false');
    document.body.style.overflow = '';
}

document.getElementById('hamburgerBtn').addEventListener('click', function() {
    if (this.classList.contains('open')) {
        closeMobileMenu();
    } else {
        openMobileMenu();
        // Close profile dropdown if open
        var dd = document.getElementById('profileDropdown');
        if (dd) dd.classList.remove('show');
    }
});

/* ── Profile dropdown ── */
function toggleProfileDropdown(e) {
    e.stopPropagation();
    var dd  = document.getElementById('profileDropdown');
    var btn = document.getElementById('profileBtn');
    if (!dd) return;
    var isOpen = dd.classList.toggle('show');
    btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
}

window.addEventListener('click', function(e) {
    var dd  = document.getElementById('profileDropdown');
    var btn = document.getElementById('profileBtn');
    if (!dd) return;
    if (dd.classList.contains('show') && !e.composedPath().includes(dd) && e.target !== btn) {
        dd.classList.remove('show');
        if (btn) btn.setAttribute('aria-expanded', 'false');
    }
});

window.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        var dd  = document.getElementById('profileDropdown');
        var btn = document.getElementById('profileBtn');
        if (dd && dd.classList.contains('show')) {
            dd.classList.remove('show');
            if (btn) btn.setAttribute('aria-expanded', 'false');
        }
        closeMobileMenu();
    }
});

/* ── Close mobile menu on resize to desktop ── */
window.addEventListener('resize', function() {
    if (window.innerWidth > 768) {
        closeMobileMenu();
    }
});

/* ── Legacy dropdown (cards dropdown) ── */
function toggleDropdown() {
    var dropdown = document.getElementById("cardsDropdown");
    if (dropdown) {
        dropdown.style.display = dropdown.style.display === "block" ? "none" : "block";
    }
}

window.addEventListener("click", function(e) {
    if (!e.target.matches('.dropbtn')) {
        var dropdown = document.getElementById("cardsDropdown");
        if (dropdown && dropdown.style.display === "block") {
            dropdown.style.display = "none";
        }
    }
});
</script>