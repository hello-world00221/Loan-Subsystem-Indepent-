<?php
// ─── logout.php (LoanSubsystem/Employee/) ────────────────────────────────────
// Destroys the current session (works for both admin and loan officer formats)
// then redirects back to login.php in the same folder.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear all session variables
$_SESSION = [];

// Destroy the session cookie if one exists
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to login page
header("Location: /Evergreen-loan-main/LoanSubsystem/Loan/login.php?message=logged_out");
exit();
?>