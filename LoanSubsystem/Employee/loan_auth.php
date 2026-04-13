<?php
/**
 * loan_auth.php — Shared authentication helper
 *
 * Include this file in any endpoint that needs to accept BOTH:
 *   Format A – Legacy admin login  (user_id + role/user_role === 'admin')
 *   Format B – Loan Officer login  (officer_id set, admin_id NOT set)
 *
 * This mirrors the exact same logic used in admin_header.php so that
 * every endpoint stays in sync with the portal's access rules.
 *
 * Usage (at the top of any protected PHP file, BEFORE headers):
 *   require_once __DIR__ . '/loan_auth.php';
 *   loan_auth_require();          // dies with JSON 403 on failure
 *
 * For files that are not JSON endpoints (e.g. pages that redirect):
 *   loan_auth_require(false);     // redirects to login.php on failure
 */

if (!function_exists('loan_auth_check')) {

    /**
     * Returns true if the current session belongs to an authorised user
     * (admin OR loan officer), false otherwise.
     */
    function loan_auth_check(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // ── Format A: legacy login.php admin ─────────────────────────────
        $isLegacyAdmin = (
            isset($_SESSION['user_id']) &&
            in_array(
                strtolower(trim($_SESSION['role'] ?? $_SESSION['user_role'] ?? '')),
                ['admin'],
                true
            )
        );

        // ── Format B: Employeelogin.php → Loan Officer ────────────────────
        // officer_id must be present AND admin_id must be absent / null
        $isLoanOfficer = (
            !empty($_SESSION['officer_id']) &&
            (empty($_SESSION['admin_id']) || $_SESSION['admin_id'] === null)
        );

        return $isLegacyAdmin || $isLoanOfficer;
    }

    /**
     * Enforce authentication.
     *
     * @param bool $jsonResponse  true  → send HTTP 403 + JSON error body
     *                            false → redirect to login.php
     */
    function loan_auth_require(bool $jsonResponse = true): void
    {
        if (loan_auth_check()) {
            return; // authorised — do nothing
        }

        if ($jsonResponse) {
            if (ob_get_length()) {
                ob_end_clean();
            }
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Access denied. Please log in as an authorised staff member.']);
            exit;
        } else {
            session_destroy();
            header('Location: login.php');
            exit;
        }
    }

    /**
     * Convenience: returns the display name of the currently logged-in user,
     * regardless of which session format is active.
     */
    function loan_auth_user_name(): string
    {
        return $_SESSION['user_name']
            ?? $_SESSION['officer_name']
            ?? $_SESSION['full_name']
            ?? $_SESSION['admin_name']
            ?? 'Staff';
    }

    /**
     * Convenience: returns the numeric user/officer ID.
     */
    function loan_auth_user_id(): int
    {
        return (int)(
            $_SESSION['user_id']
            ?? $_SESSION['officer_id']
            ?? 0
        );
    }
}