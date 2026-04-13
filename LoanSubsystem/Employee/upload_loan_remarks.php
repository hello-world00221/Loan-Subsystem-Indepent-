<?php
/**
 * upload_loan_remarks.php
 *
 * Accepts a loan ID + remarks string and updates the rejection_remarks
 * (or a general notes field) for a given loan application.
 *
 * Auth: Admin (Format A) OR Loan Officer (Format B) — mirrors admin_header.php.
 */

ob_start();
session_start();
header('Content-Type: application/json; charset=utf-8');

// ─── Unified auth (admin OR loan officer) ────────────────────────────────────
require_once __DIR__ . '/loan_auth.php';
loan_auth_require(true); // sends JSON 403 on failure

// ─── Connect to loandb ────────────────────────────────────────────────────────
$conn = new mysqli("localhost", "root", "", "loandb");
$conn->set_charset("utf8mb4");
if ($conn->connect_error) {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $conn->connect_error]);
    exit;
}

// ─── Parse input ─────────────────────────────────────────────────────────────
$input   = json_decode(file_get_contents('php://input'), true) ?? [];
$loan_id = (int) ($input['loan_id'] ?? 0);
$remarks = trim($input['remarks']   ?? '');

if ($loan_id <= 0) {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Invalid loan ID']);
    exit;
}

if (empty($remarks)) {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Remarks cannot be empty']);
    exit;
}

// ─── Verify loan exists ───────────────────────────────────────────────────────
$check = $conn->prepare("SELECT id, status FROM loan_applications WHERE id = ? LIMIT 1");
if (!$check) {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'DB prepare failed: ' . $conn->error]);
    exit;
}
$check->bind_param("i", $loan_id);
$check->execute();
$loan = $check->get_result()->fetch_assoc();
$check->close();

if (!$loan) {
    ob_end_clean();
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Loan not found']);
    exit;
}

// ─── Resolve acting user ─────────────────────────────────────────────────────
$admin_name    = loan_auth_user_name();
$admin_user_id = loan_auth_user_id();
$timestamp     = date('Y-m-d H:i:s');

// ─── Upsert into loan_rejections (remarks stored here regardless of status) ──
// If a rejection row exists, update remarks. Otherwise insert a placeholder row.
$upsert = $conn->prepare("
    INSERT INTO loan_rejections
        (loan_application_id, rejected_by, rejected_by_user_id, rejected_at, rejection_remarks)
    VALUES (?, ?, ?, NOW(), ?)
    ON DUPLICATE KEY UPDATE
        rejection_remarks   = VALUES(rejection_remarks),
        rejected_by         = VALUES(rejected_by),
        rejected_by_user_id = VALUES(rejected_by_user_id),
        rejected_at         = NOW()
");

if (!$upsert) {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Prepare failed: ' . $conn->error]);
    exit;
}

$upsert->bind_param("isis", $loan_id, $admin_name, $admin_user_id, $remarks);

if ($upsert->execute()) {
    $upsert->close();
    $conn->close();
    ob_end_clean();
    echo json_encode([
        'success'   => true,
        'message'   => 'Remarks updated successfully',
        'loan_id'   => $loan_id,
        'remarks'   => $remarks,
        'updated_by'=> $admin_name,
        'updated_at'=> $timestamp,
    ]);
} else {
    $err = $upsert->error;
    $upsert->close();
    $conn->close();
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Update failed: ' . $err]);
}
exit;