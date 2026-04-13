<?php
session_start();
header('Content-Type: application/json');

// ─── Unified auth (admin OR loan officer) ────────────────────────────────────
// upload_loan_pdf.php is called by staff portals, so we accept both formats.
require_once __DIR__ . '/loan_auth.php';

// This endpoint is also called from the client-facing side (borrower downloads
// their own PDF). We accept authenticated staff OR a logged-in client.
// If neither staff auth nor client session exists, deny access.
$isStaff  = loan_auth_check();
$isClient = isset($_SESSION['user_email']);

if (!$isStaff && !$isClient) {
    http_response_code(403);
    exit(json_encode(['error' => 'Not logged in']));
}

// ─── Connect to loandb ────────────────────────────────────────────────────────
$conn = new mysqli("localhost", "root", "", "loandb");
$conn->set_charset("utf8mb4");
if ($conn->connect_error) {
    exit(json_encode(['error' => 'DB error: ' . $conn->connect_error]));
}

// ─── Input ────────────────────────────────────────────────────────────────────
$data         = json_decode(file_get_contents('php://input'), true);
$loan_id      = (int)($data['loan_id']  ?? 0);
$pdf_filename = trim($data['pdf_path']  ?? ''); // just the filename, no path prefix
$pdf_type     = trim($data['type']      ?? '');

if ($loan_id <= 0 || empty($pdf_filename) || empty($pdf_type)) {
    exit(json_encode(['error' => 'Invalid input — missing loan_id, pdf_path, or type']));
}

if (!in_array($pdf_type, ['approved', 'active', 'rejected'])) {
    exit(json_encode(['error' => 'Invalid type. Must be: approved, active, or rejected']));
}

// ─── Ownership / authorisation check ─────────────────────────────────────────
// Staff (admin or loan officer) may update any loan's PDF.
// Clients may only update PDFs belonging to their own loan.
$own = $conn->prepare("
    SELECT lb.email
    FROM loan_applications la
    LEFT JOIN loan_borrowers lb ON lb.loan_application_id = la.id
    WHERE la.id = ?
    LIMIT 1
");
$own->bind_param("i", $loan_id);
$own->execute();
$own_result = $own->get_result();

if ($own_result->num_rows === 0) {
    $own->close(); $conn->close();
    exit(json_encode(['error' => 'Loan not found']));
}

$own_row        = $own_result->fetch_assoc();
$borrower_email = $own_row['email'] ?? null;
$own->close();

// If the caller is a non-staff client, enforce ownership
if (!$isStaff) {
    if ($borrower_email !== null && $borrower_email !== $_SESSION['user_email']) {
        $conn->close();
        exit(json_encode(['error' => 'Unauthorized']));
    }
}

// ─── Build full path and verify file exists ───────────────────────────────────
// Files are stored in LoanSubsystem/Loan/uploads/
// This script lives in LoanSubsystem/Employee/
// So the correct absolute path is one level up, then into Loan/uploads/
$loan_uploads_dir = dirname(__DIR__) . '/Loan/uploads/';
$full_pdf_path    = 'uploads/' . basename($pdf_filename); // relative path stored in DB
$abs_pdf_path     = $loan_uploads_dir . basename($pdf_filename);

if (!file_exists($abs_pdf_path)) {
    $conn->close();
    exit(json_encode(['error' => 'PDF file not found on server: ' . $abs_pdf_path]));
}

// ─── Map type to column in loan_documents ─────────────────────────────────────
$column_map = [
    'approved' => 'pdf_approved',
    'active'   => 'pdf_active',
    'rejected' => 'pdf_rejected',
];
$column = $column_map[$pdf_type];

// ─── Upsert into loan_documents ───────────────────────────────────────────────
$sql = "
    INSERT INTO loan_documents (loan_application_id, {$column})
    VALUES (?, ?)
    ON DUPLICATE KEY UPDATE {$column} = VALUES({$column})
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    $conn->close();
    exit(json_encode(['error' => 'Prepare failed: ' . $conn->error]));
}

$stmt->bind_param("is", $loan_id, $full_pdf_path);

if ($stmt->execute()) {
    $stmt->close();
    $conn->close();
    exit(json_encode([
        'success'  => true,
        'type'     => $pdf_type,
        'column'   => $column,
        'pdf_path' => $full_pdf_path,
        'message'  => 'PDF path updated successfully'
    ]));
} else {
    $err = $stmt->error;
    $stmt->close();
    $conn->close();
    exit(json_encode(['error' => 'Update failed: ' . $err]));
}