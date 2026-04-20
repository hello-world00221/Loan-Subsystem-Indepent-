<?php
require_once('fpdf/fpdf.php');

// ─── Chart image from POST ────────────────────────────────────────────────────
$chartImageData = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $chartImageData = $input['chartImage'] ?? null;
}

// ─── Validate report type — now includes 'closed' ────────────────────────────
if (!isset($_GET['type']) || !in_array($_GET['type'], ['all','active','approved','pending','rejected','closed'])) {
    echo json_encode(['error' => 'Invalid report type']);
    exit();
}

$report_type = $_GET['type'];

// ─── Admin info from session ──────────────────────────────────────────────────
$admin_name      = $_SESSION['user_name']       ?? $_SESSION['full_name']  ?? 'Loan Officer';
$loan_officer_id = $_SESSION['loan_officer_id'] ?? 'LO-0001';

// ─── Connect to loandb ────────────────────────────────────────────────────────
$conn = new mysqli("localhost", "root", "", "loandb");
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    echo json_encode(['error' => 'DB error: ' . $conn->connect_error]);
    exit();
}

// ─── WHERE clause ─────────────────────────────────────────────────────────────
$where_clause = '';
$report_title = 'All Loans';
switch ($report_type) {
    case 'active':   $where_clause = "WHERE la.status = 'Active'";   $report_title = 'Active Loans';                  break;
    case 'approved': $where_clause = "WHERE la.status = 'Approved'"; $report_title = 'Approved Loans (Awaiting Claim)'; break;
    case 'pending':  $where_clause = "WHERE la.status = 'Pending'";  $report_title = 'Pending Loans';                 break;
    case 'rejected': $where_clause = "WHERE la.status = 'Rejected'"; $report_title = 'Rejected Loans';                break;
    case 'closed':   $where_clause = "WHERE la.status = 'Closed'";   $report_title = 'Fully Paid / Closed Loans';     break;  // ← NEW label
}

// ─── Fetch loans ──────────────────────────────────────────────────────────────
$sql = "
    SELECT
        la.id                                       AS client_id,
        COALESCE(lb.full_name, la.user_email)       AS client_name,
        COALESCE(lt.name, 'Unknown')                AS loan_type,
        la.loan_amount,
        la.loan_terms,
        la.monthly_payment,
        la.status,
        la.created_at,
        la.next_payment_due,
        lap.approved_at,
        lr.rejected_at
    FROM loan_applications la
    LEFT JOIN loan_types     lt  ON lt.id  = la.loan_type_id
    LEFT JOIN loan_borrowers lb  ON lb.loan_application_id = la.id
    LEFT JOIN loan_approvals lap ON lap.loan_application_id = la.id
    LEFT JOIN loan_rejections lr ON lr.loan_application_id  = la.id
    $where_clause
    ORDER BY la.created_at DESC
";

$result = $conn->query($sql);
$loans  = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $loans[] = $row;
    }
}

// ─── Status counts (all statuses, used in the stats box) ─────────────────────
$counts = ['Active' => 0, 'Approved' => 0, 'Pending' => 0, 'Rejected' => 0, 'Closed' => 0];
$allLoansResult = $conn->query("SELECT status, COUNT(*) AS total FROM loan_applications GROUP BY status");
if ($allLoansResult) {
    while ($row = $allLoansResult->fetch_assoc()) {
        $status = ucfirst(strtolower(trim($row['status'])));
        if (array_key_exists($status, $counts)) {
            $counts[$status] = (int)$row['total'];
        }
    }
}

// ─── Total amount settled for closed loans ────────────────────────────────────
$closedAmtResult = $conn->query("
    SELECT COALESCE(SUM(loan_amount), 0) AS total_settled
    FROM loan_applications
    WHERE status = 'Closed'
");
$closedTotalAmount = 0;
if ($closedAmtResult) {
    $closedAmtRow      = $closedAmtResult->fetch_assoc();
    $closedTotalAmount = floatval($closedAmtRow['total_settled'] ?? 0);
}

$conn->close();

// ─── Generate PDF ─────────────────────────────────────────────────────────────
$pdf = new FPDF('L', 'mm', 'A4');
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 16);

$pdf->Cell(0, 15, 'EVERGREEN TRUST AND SAVINGS LOAN SERVICES', 0, 1, 'C');
$pdf->Ln(3);

$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, 'Loan Officer Report: ' . $report_title, 0, 1, 'L');
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 6, 'Reporting Period: ' . date('F Y'), 0, 1, 'L');
$pdf->Cell(0, 6, 'Prepared by: Loan Officer - ' . $admin_name, 0, 1, 'L');
$pdf->Cell(0, 6, 'Department: Loan Subsystem', 0, 1, 'L');
$pdf->Ln(8);

// ─── Pie chart ────────────────────────────────────────────────────────────────
if ($chartImageData) {
    $chartImageData = str_replace('data:image/png;base64,', '', $chartImageData);
    $chartImageData = str_replace(' ', '+', $chartImageData);
    $decodedImage   = base64_decode($chartImageData);

    $uploadDir = 'uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $tempChartPath = $uploadDir . 'temp_chart_' . time() . '.png';
    file_put_contents($tempChartPath, $decodedImage);

    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, 'Loan Portfolio Analytics', 0, 1, 'L');
    $pdf->Ln(2);

    $startY = $pdf->GetY();
    $pdf->Image($tempChartPath, 15, $startY, 90, 65);

    // ── Stats box: now includes Closed/Fully Paid row ──────────────────────
    $statsX = 115;
    $pdf->SetXY($statsX, $startY);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell(80, 8, 'Overall Portfolio Statistics:', 1, 1, 'C', true);

    $statRows = [
        'Active Loans:'          => $counts['Active'],
        'Approved (Awaiting):'   => $counts['Approved'],
        'Pending Review:'        => $counts['Pending'],
        'Rejected:'              => $counts['Rejected'],
        'Fully Paid / Closed:'   => $counts['Closed'],   // ← NEW row
    ];

    // Colour mapping for left border indicator
    $rowColors = [
        'Active Loans:'          => [10,  59, 47],   // dark green
        'Approved (Awaiting):'   => [76, 175, 80],   // green
        'Pending Review:'        => [255,152,  0],   // orange
        'Rejected:'              => [244, 67, 54],   // red
        'Fully Paid / Closed:'   => [201,168, 76],   // gold
    ];

    foreach ($statRows as $label => $value) {
        $pdf->SetXY($statsX, $pdf->GetY());
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(50, 7, $label, 1, 0, 'L');
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(30, 7, $value, 1, 1, 'C');
    }

    // Total row
    $pdf->SetXY($statsX, $pdf->GetY());
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetFillColor(220, 220, 220);
    $totalAll = array_sum($counts);
    $pdf->Cell(50, 7, 'Total Loans:', 1, 0, 'L', true);
    $pdf->Cell(30, 7, $totalAll,      1, 1, 'C', true);

    // ── NEW: Fully Paid amount highlight ───────────────────────────────────
    if ($counts['Closed'] > 0) {
        $pdf->SetXY($statsX, $pdf->GetY() + 2);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetFillColor(10, 59, 47);
        $pdf->SetTextColor(232, 201, 107);
        $pdf->Cell(80, 7, 'Total Settled (Closed): PHP ' . number_format($closedTotalAmount, 2), 1, 1, 'C', true);
        $pdf->SetTextColor(0, 0, 0); // reset
    }

    @unlink($tempChartPath);
    $pdf->SetY($startY + 72);
    $pdf->Ln(5);
}

// ─── Special banner for Closed report ─────────────────────────────────────────
if ($report_type === 'closed' && count($loans) > 0) {
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetFillColor(10, 59, 47);
    $pdf->SetTextColor(232, 201, 107);
    $pdf->Cell(0, 9, '  FULLY PAID / CLOSED LOANS — Total Settled: PHP ' . number_format($closedTotalAmount, 2) . '  (' . count($loans) . ' loans)', 1, 1, 'L', true);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFillColor(255, 255, 255);
    $pdf->Ln(3);
}

// ─── Table header ─────────────────────────────────────────────────────────────
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(200, 200, 200);
$w      = [18, 38, 28, 30, 22, 28, 30, 30, 24];
$header = ['Loan ID','Client Name','Loan Type','Amount','Term','Monthly Pmt','Total Payable','Status','Date'];
foreach ($header as $i => $h) {
    $pdf->Cell($w[$i], 8, $h, 1, 0, 'C', true);
}
$pdf->Ln();

// ─── Table rows ───────────────────────────────────────────────────────────────
$pdf->SetFont('Arial', '', 8);
foreach ($loans as $loan) {
    if ($pdf->GetY() > 180) {
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetFillColor(200, 200, 200);
        foreach ($header as $i => $h) $pdf->Cell($w[$i], 8, $h, 1, 0, 'C', true);
        $pdf->Ln();
        $pdf->SetFont('Arial', '', 8);
    }

    // Date column
    if (in_array($loan['status'], ['Active','Approved','Closed']) && !empty($loan['approved_at'])) {
        $display_date = date('m/d/Y', strtotime($loan['approved_at']));
    } elseif ($loan['status'] === 'Rejected' && !empty($loan['rejected_at'])) {
        $display_date = date('m/d/Y', strtotime($loan['rejected_at']));
    } else {
        $display_date = date('m/d/Y', strtotime($loan['created_at']));
    }

    // ── Highlight Closed rows with a light gold tint ──────────────────────
    $isClosed = (strtolower($loan['status']) === 'closed');
    if ($isClosed) {
        $pdf->SetFillColor(253, 248, 220);  // light gold
    } else {
        $pdf->SetFillColor(255, 255, 255);
    }

    $statusLabel = $isClosed ? 'Paid/Closed' : ucfirst($loan['status']);

    $data = [
        $loan['client_id'],
        substr($loan['client_name'], 0, 22),
        substr($loan['loan_type'],   0, 16),
        'PHP ' . number_format($loan['loan_amount'],     2),
        $loan['loan_terms'],
        'PHP ' . number_format($loan['monthly_payment'], 2),
        'PHP ' . number_format($loan['loan_amount'] * 1.20, 2),
        $statusLabel,
        $display_date
    ];

    $fill = $isClosed; // fill with gold tint for closed rows
    foreach ($data as $i => $cell) {
        $pdf->Cell($w[$i], 7, $cell, 1, 0, 'L', $fill);
    }
    $pdf->Ln();
}

// Reset fill color
$pdf->SetFillColor(255, 255, 255);

// ─── Summary ──────────────────────────────────────────────────────────────────
$pdf->Ln(8);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 8, 'Summary Statistics:', 0, 1);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 6, "Total Loans in Report: " . count($loans), 0, 1, 'L');

// ── NEW: Show total settled amount for closed reports ─────────────────────
if ($report_type === 'closed') {
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 6, "Total Principal Settled: PHP " . number_format($closedTotalAmount, 2), 0, 1, 'L');
    $pdf->SetFont('Arial', '', 10);
} elseif ($report_type === 'all' && $counts['Closed'] > 0) {
    $pdf->Cell(0, 6, "Fully Paid Loans: " . $counts['Closed'] . "  (PHP " . number_format($closedTotalAmount, 2) . " settled)", 0, 1, 'L');
}

$pdf->Cell(0, 6, "Report Generated: " . date('F j, Y \a\t g:i A'), 0, 1, 'L');

// ─── Notes ────────────────────────────────────────────────────────────────────
$pdf->Ln(4);
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 7, 'Important Notes:', 0, 1);
$pdf->SetFont('Arial', '', 9);

$notes = [
    "- This report is generated from the Evergreen Trust and Savings Loan Management System.",
    "- All monetary amounts are in Philippine Peso (PHP).",
    "- Interest rate applied: 20% per annum.",
    "- Approved loans require client to claim within 30 days.",
    "- 'Fully Paid / Closed' loans have been completely settled by the borrower.",
    "- For inquiries, contact the Loan Officer Department."
];
foreach ($notes as $note) {
    $pdf->Cell(0, 5, $note, 0, 1, 'L');
}

// ─── Footer ───────────────────────────────────────────────────────────────────
$pdf->SetY(-15);
$pdf->SetFont('Arial', 'I', 8);
$pdf->Cell(0, 10, 'Generated by Evergreen Trust and Savings - Page ' . $pdf->PageNo(), 0, 0, 'C');

// ─── Save PDF ─────────────────────────────────────────────────────────────────
$uploadDir = 'uploads/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$filename = "loan_report_{$report_type}_" . date('YmdHis') . ".pdf";
$fullPath = $uploadDir . $filename;
$pdf->Output('F', $fullPath);

echo json_encode(['success' => true, 'filename' => $fullPath]);
?>