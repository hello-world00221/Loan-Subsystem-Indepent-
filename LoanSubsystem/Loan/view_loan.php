<?php
ob_start();
session_start();
header('Content-Type: application/json; charset=utf-8');

// ─── Unified auth (admin OR loan officer) ────────────────────────────────────
require_once __DIR__ . '/loan_auth.php';
loan_auth_require(true); // sends JSON 403 on failure

/* ---------------------------------------------
   VALIDATE INPUT
-----------------------------------------------*/
$loan_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($loan_id <= 0) {
    ob_clean();
    http_response_code(400);
    echo json_encode(['error' => 'Invalid loan ID']);
    exit;
}

/* ---------------------------------------------
   DATABASE CONNECTION — loandb
-----------------------------------------------*/
$conn = new mysqli("localhost", "root", "", "loandb");
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    ob_clean();
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

/* ---------------------------------------------
   MAIN QUERY
   Joins all normalized loandb tables to
   reconstruct the full flat application record.

   Tables:
     loan_applications → core fields
     loan_types        → loan type name
     loan_borrowers    → full_name, account_number,
                         contact_number, email,
                         job, monthly_salary
     loan_valid_ids    → loan_valid_id_type FK +
                         valid_id_number
     loan_valid_id     → valid_id_type label
     loan_approvals    → approved_at, approved_by
     loan_rejections   → rejected_at, rejected_by,
                         rejection_remarks
     loan_documents    → file_name, proof_of_income,
                         coe_document, pdf_*
-----------------------------------------------*/
$stmt = $conn->prepare("
    SELECT
        -- Core application
        la.id,
        la.user_id,
        la.user_email,
        la.loan_type_id,
        la.loan_terms,
        la.loan_amount,
        la.monthly_payment,
        la.next_payment_due,
        la.purpose,
        la.status,
        la.created_at,

        -- Loan type name (loan_types)
        COALESCE(lt.name, 'Unknown Type')        AS loan_type,

        -- Borrower profile (loan_borrowers)
        COALESCE(lb.full_name, la.user_email)    AS full_name,
        lb.account_number,
        lb.contact_number,
        lb.email                                 AS borrower_email,
        lb.job,
        lb.monthly_salary,

        -- Valid ID label (loan_valid_ids → loan_valid_id)
        lvi.valid_id_type,
        lvid.valid_id_number,

        -- Approval data (loan_approvals)
        lap.approved_at,
        lap.approved_by,

        -- Rejection data (loan_rejections)
        lr.rejected_at,
        lr.rejected_by,
        lr.rejection_remarks,

        -- Documents (loan_documents)
        ld.file_name,
        ld.proof_of_income,
        ld.coe_document,
        ld.pdf_approved,
        ld.pdf_active,
        ld.pdf_rejected

    FROM loan_applications la

    LEFT JOIN loan_types lt
        ON lt.id = la.loan_type_id

    LEFT JOIN loan_borrowers lb
        ON lb.loan_application_id = la.id

    LEFT JOIN loan_valid_ids lvid
        ON lvid.loan_application_id = la.id

    LEFT JOIN loan_valid_id lvi
        ON lvi.id = lvid.loan_valid_id_type

    LEFT JOIN loan_approvals lap
        ON lap.loan_application_id = la.id

    LEFT JOIN loan_rejections lr
        ON lr.loan_application_id = la.id

    LEFT JOIN loan_documents ld
        ON ld.loan_application_id = la.id

    WHERE la.id = ?
    LIMIT 1
");

if (!$stmt) {
    ob_clean();
    echo json_encode(['error' => 'Database query failed: ' . $conn->error]);
    exit;
}

$stmt->bind_param("i", $loan_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    ob_clean();
    http_response_code(404);
    echo json_encode(['error' => 'Loan application not found']);
    $stmt->close();
    $conn->close();
    exit;
}

$row = $result->fetch_assoc();
$stmt->close();
$conn->close();

/* ---------------------------------------------
   FORMAT FILE FIELDS
   ─────────────────────────────────────────────
   Files are physically stored in:
     LoanSubsystem/Loan/uploads/
   This file lives in:
     LoanSubsystem/Employee/view_loan.php

   PROBLEM: window.open() resolves relative URLs
   from the CURRENT PAGE URL in the browser, NOT
   from this PHP file's disk location. So using
   "../Loan/uploads/" causes the browser to visit
     Employee/../Loan/uploads/ → Employee/uploads/
   which doesn't exist → 404.

   FIX: Build an absolute URL path from the web
   root dynamically using DOCUMENT_ROOT so it
   always resolves to:
     /Evergreen-loan-main/LoanSubsystem/Loan/uploads/<file>
   regardless of which page calls this endpoint.
-----------------------------------------------*/

// Derive absolute web path to LoanSubsystem/Loan/uploads/
// __DIR__         = .../htdocs/Evergreen-loan-main/LoanSubsystem/Employee
// DOCUMENT_ROOT   = .../htdocs
// We strip DOCUMENT_ROOT to get the web path of this folder,
// go one level up (to LoanSubsystem), then append /Loan/uploads/
$_docRoot     = str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'], '/\\'));
$_thisDir     = str_replace('\\', '/', rtrim(__DIR__, '/\\'));
$_webThisDir  = '/' . ltrim(substr($_thisDir, strlen($_docRoot)), '/');
$_loanUploads = dirname($_webThisDir) . '/Loan/uploads/';
// e.g. $_loanUploads = /Evergreen-loan-main/LoanSubsystem/Loan/uploads/

function resolveUploadUrl(string $stored, string $loanUploads): string {
    if (empty($stored)) return '';
    return $loanUploads . basename($stored);
}

$row['file_url']        = resolveUploadUrl($row['file_name']       ?? '', $_loanUploads);
$row['proof_of_income'] = resolveUploadUrl($row['proof_of_income'] ?? '', $_loanUploads);
$row['coe_document']    = resolveUploadUrl($row['coe_document']    ?? '', $_loanUploads);
$row['pdf_approved']    = resolveUploadUrl($row['pdf_approved']    ?? '', $_loanUploads);
$row['pdf_active']      = resolveUploadUrl($row['pdf_active']      ?? '', $_loanUploads);
$row['pdf_rejected']    = resolveUploadUrl($row['pdf_rejected']    ?? '', $_loanUploads);

/* ---------------------------------------------
   FINAL JSON OUTPUT
-----------------------------------------------*/
ob_clean();
echo json_encode([
    'id'               => $row['id'],

    // Borrower fields (from loan_borrowers)
    'full_name'        => $row['full_name']        ?? '',
    'account_number'   => $row['account_number']   ?? '',
    'contact_number'   => $row['contact_number']   ?? '',
    'email'            => $row['borrower_email']   ?? $row['user_email'] ?? '',
    'job'              => $row['job']              ?? '',
    'monthly_salary'   => $row['monthly_salary']   ?? '0',

    // Core loan fields (from loan_applications)
    'loan_type'        => $row['loan_type']        ?? 'Unknown',
    'loan_amount'      => $row['loan_amount']      ?? '0',
    'loan_terms'       => $row['loan_terms']       ?? '',
    'purpose'          => $row['purpose']          ?? '',
    'created_at'       => $row['created_at']       ?? '',
    'monthly_payment'  => $row['monthly_payment']  ?? '0',
    'next_payment_due' => $row['next_payment_due'] ?? '',
    'status'           => $row['status']           ?? '',

    // Valid ID (from loan_valid_ids + loan_valid_id)
    'valid_id_type'    => $row['valid_id_type']    ?? 'N/A',
    'valid_id_number'  => $row['valid_id_number']  ?? '',

    // Approval data (from loan_approvals)
    'approved_by'      => $row['approved_by']      ?? '',
    'approved_at'      => $row['approved_at']      ?? '',

    // Rejection data (from loan_rejections)
    'rejected_by'      => $row['rejected_by']      ?? '',
    'rejected_at'      => $row['rejected_at']      ?? '',
    'rejection_remarks'=> $row['rejection_remarks'] ?? '',

    // Documents (from loan_documents) — URLs resolved to ../Loan/uploads/
    'file_url'         => $row['file_url'],
    'proof_of_income'  => $row['proof_of_income'],
    'coe_document'     => $row['coe_document'],
    'pdf_approved'     => $row['pdf_approved'],
    'pdf_active'       => $row['pdf_active'],
    'pdf_rejected'     => $row['pdf_rejected'],
]);

ob_end_flush();