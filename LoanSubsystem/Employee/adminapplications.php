<!--adminapplications.php-->
<?php
session_start();
include 'admin_header.php';

// ─── Connect to loandb ────────────────────────────────────────────────────────
$conn = new mysqli("localhost", "root", "", "loandb");
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("DB Error: " . $conn->connect_error);
}

// ─── Status counts ────────────────────────────────────────────────────────────
$counts = ['Active' => 0, 'Pending' => 0, 'Approved' => 0, 'Rejected' => 0, 'Closed' => 0];
$statusResult = $conn->query("SELECT status, COUNT(*) AS total FROM loan_applications GROUP BY status");
if ($statusResult) {
    while ($row = $statusResult->fetch_assoc()) {
        $status = ucfirst(strtolower(trim($row['status'])));
        if (array_key_exists($status, $counts)) {
            $counts[$status] = (int)$row['total'];
        }
    }
}

// ─── Reusable JOIN query builder ─────────────────────────────────────────────
function buildLoanQuery(string $whereClause): string {
    return "
        SELECT
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
            COALESCE(lt.name, 'Unknown Type')   AS loan_type_name,
            COALESCE(lb.full_name, la.user_email) AS full_name,
            lb.account_number,
            lb.contact_number,
            lb.email,
            lb.job,
            lb.monthly_salary,
            lvi.valid_id_type,
            lvid.valid_id_number,
            lap.approved_at,
            lap.approved_by,
            lap.approved_by_user_id,
            lr.rejected_at,
            lr.rejected_by,
            lr.rejection_remarks,
            ld.file_name,
            ld.proof_of_income,
            ld.coe_document
        FROM loan_applications la
        LEFT JOIN loan_types lt        ON lt.id = la.loan_type_id
        LEFT JOIN loan_borrowers lb    ON lb.loan_application_id = la.id
        LEFT JOIN loan_valid_ids lvid  ON lvid.loan_application_id = la.id
        LEFT JOIN loan_valid_id lvi    ON lvi.id = lvid.loan_valid_id_type
        LEFT JOIN loan_approvals lap   ON lap.loan_application_id = la.id
        LEFT JOIN loan_rejections lr   ON lr.loan_application_id = la.id
        LEFT JOIN loan_documents ld    ON ld.loan_application_id = la.id
        $whereClause
    ";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Evergreen | Loan Applications</title>
  <link rel="icon" type="logo/png" href="pictures/logo.png" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <link rel="stylesheet" href="adminstyle.css" />

  <style>
    /* ── Brand colour tokens ── */
    :root {
      --brand-dark:   #003631;
      --brand-mid:    #005a52;
      --brand-light:  #e8f5e9;
      --pending-clr:  #e65100;
      --approved-clr: #2e7d32;
      --rejected-clr: #c62828;
      --active-clr:   #1565c0;
    }

    /* ── Page layout ── */
    main { padding: 1.5rem; }

    h1 {
      color: var(--brand-dark);
      font-size: 1.5rem;
      font-weight: 700;
      margin-bottom: 1.5rem;
    }

    /* ── Summary stat cards ── */
    .stat-card {
      border: none; border-radius: 12px;
      padding: 1rem 1.1rem;
      display: flex; align-items: center; gap: 0.75rem;
    }
    .stat-card .stat-icon {
      width: 44px; height: 44px; border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.1rem; flex-shrink: 0;
    }
    .stat-card .stat-value { font-size: 1.4rem; font-weight: 700; line-height: 1; }
    .stat-card .stat-label { font-size: 0.72rem; color: #6c757d; margin-top: 2px; }

    .stat-pending  { background: #fff3e0; }
    .stat-pending  .stat-icon { background: #ffe0b2; color: var(--pending-clr); }
    .stat-pending  .stat-value { color: var(--pending-clr); }

    .stat-approved { background: #e8f5e9; }
    .stat-approved .stat-icon { background: #c8e6c9; color: var(--approved-clr); }
    .stat-approved .stat-value { color: var(--approved-clr); }

    .stat-active   { background: #e3f2fd; }
    .stat-active   .stat-icon { background: #bbdefb; color: var(--active-clr); }
    .stat-active   .stat-value { color: var(--active-clr); }

    .stat-rejected { background: #ffebee; }
    .stat-rejected .stat-icon { background: #ffcdd2; color: var(--rejected-clr); }
    .stat-rejected .stat-value { color: var(--rejected-clr); }

    /* ── Section titles ── */
    .section-title {
      font-size: 1rem;
      font-weight: 700;
      color: var(--brand-dark);
      border-left: 4px solid var(--brand-dark);
      padding-left: 0.65rem;
      margin: 1.75rem 0 0.85rem;
      display: flex;
      align-items: center;
      flex-wrap: wrap;
      gap: 0.4rem;
    }

    /* ── Table card ── */
    .table-card {
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 1px 6px rgba(0,0,0,.08);
      overflow: hidden;
      margin-bottom: 1.75rem;
    }

    /* ── Responsive table wrapper ── */
    .table-responsive {
      width: 100%;
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
    }

    /* ── Table styles ── */
    .table-card table {
      width: 100%;
      min-width: 640px;
      border-collapse: collapse;
      font-size: 0.875rem;
      margin: 0;
    }
    .table-card thead th {
      background: var(--brand-dark);
      color: #fff; font-weight: 600;
      padding: 0.7rem 1rem;
      white-space: nowrap; border: none;
    }
    .table-card tbody td {
      padding: 0.65rem 1rem;
      border-bottom: 1px solid #f0f0f0;
      vertical-align: middle; color: #333;
    }
    .table-card tbody tr:last-child td { border-bottom: none; }
    .table-card tbody tr:hover { background: #f9fafb; }
    .empty-row td {
      text-align: center; color: #888;
      padding: 1.5rem !important;
    }

    /* ── Status badges ── */
    .badge-status {
      display: inline-block; padding: 0.28em 0.7em;
      border-radius: 20px; font-size: 0.73rem;
      font-weight: 600; letter-spacing: .3px;
      white-space: nowrap;
    }
    .badge-pending  { background: #fff3e0; color: var(--pending-clr); }
    .badge-approved { background: #e8f5e9; color: var(--approved-clr); }
    .badge-active   { background: #e3f2fd; color: var(--active-clr);   }
    .badge-rejected { background: #ffebee; color: var(--rejected-clr); }

    /* ── Action button ── */
    .btn-view {
      background: var(--brand-dark); color: #fff;
      border: none; border-radius: 7px;
      padding: 0.3rem 0.8rem; font-size: 0.78rem;
      cursor: pointer; transition: background .18s;
      white-space: nowrap; display: inline-flex;
      align-items: center; gap: 4px;
    }
    .btn-view:hover { background: var(--brand-mid); color: #fff; }

    /* ── Modal overlay ── */
    .modal {
      display: none; position: fixed; inset: 0;
      z-index: 1055; align-items: center; justify-content: center;
      background: rgba(0,0,0,.45); padding: 1rem;
    }
    .modal.show { display: flex; }

    /* ── Modal box ── */
    .status-modal {
      background: #fff; border-radius: 16px;
      width: 100%; max-width: 820px;
      max-height: 90vh; overflow-y: auto;
      position: relative;
      box-shadow: 0 8px 40px rgba(0,0,0,.18);
    }

    .modal-header-bar {
      background: var(--brand-dark); color: #fff;
      padding: 1rem 1.25rem;
      border-radius: 16px 16px 0 0;
      display: flex; align-items: center; justify-content: space-between;
      position: sticky; top: 0; z-index: 10;
    }
    .modal-header-bar h2 { margin: 0; font-size: 1rem; font-weight: 700; }

    .close-status {
      background: none; border: none;
      color: #fff; font-size: 1.3rem;
      cursor: pointer; line-height: 1; padding: 0;
    }
    .close-status:hover { opacity: .7; }

    .modal-body-pad { padding: 1.25rem; }

    /* ── Modal section heading ── */
    .modal-section-title {
      font-size: 0.78rem; font-weight: 700;
      letter-spacing: .6px; text-transform: uppercase;
      color: var(--brand-dark); margin: 1.1rem 0 0.6rem;
      padding-bottom: 0.3rem;
      border-bottom: 2px solid var(--brand-light);
    }

    /* ── Info grid ── */
    .info-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
      gap: 0.65rem 0.9rem;
    }
    .field label {
      display: block; font-size: 0.7rem; font-weight: 600;
      color: #6c757d; text-transform: uppercase;
      letter-spacing: .4px; margin-bottom: 3px;
    }
    .field input[type="text"] {
      width: 100%;
      background: #f8f9fa; border: 1px solid #dee2e6;
      border-radius: 7px; padding: 0.4rem 0.6rem;
      font-size: 0.85rem; color: #212529; outline: none;
    }

    /* ── Document view buttons ── */
    .view-doc-btn {
      background: var(--brand-light); color: var(--brand-dark);
      border: 1px solid #b2dfdb; border-radius: 7px;
      padding: 0.4rem 0.75rem; font-size: 0.78rem;
      cursor: pointer; transition: background .18s; width: 100%;
      display: inline-flex; align-items: center; gap: 6px;
    }
    .view-doc-btn:hover:not(:disabled) { background: #c8e6c9; }
    .view-doc-btn:disabled { opacity: .45; cursor: not-allowed; }

    /* ── Modal footer buttons ── */
    .modal-footer-bar {
      display: flex; justify-content: flex-end; gap: 0.6rem;
      padding: 0.9rem 1.25rem;
      border-top: 1px solid #f0f0f0;
      flex-wrap: wrap;
      position: sticky; bottom: 0;
      background: #fff; z-index: 10;
    }
    .back-status {
      background: #f0f0f0; color: #333; border: none;
      border-radius: 8px; padding: 0.48rem 1rem;
      font-size: 0.85rem; cursor: pointer;
    }
    .back-status:hover { background: #e0e0e0; }
    .approve-btn {
      background: var(--approved-clr); color: #fff;
      border: none; border-radius: 8px;
      padding: 0.48rem 1.1rem; font-size: 0.85rem;
      cursor: pointer; transition: background .18s;
    }
    .approve-btn:hover { background: #1b5e20; }
    .reject-btn {
      background: var(--rejected-clr); color: #fff;
      border: none; border-radius: 8px;
      padding: 0.48rem 1.1rem; font-size: 0.85rem;
      cursor: pointer; transition: background .18s;
    }
    .reject-btn:hover { background: #7f0000; }

    /* ════════════════════════════════════════
       RESPONSIVE
    ════════════════════════════════════════ */

    /* Tablet */
    @media (max-width: 768px) {
      main { padding: 1.1rem; }
      h1 { font-size: 1.25rem; margin-bottom: 1.1rem; }
      .info-grid { grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); }
    }

    /* Large mobile */
    @media (max-width: 600px) {
      main { padding: 0.9rem; }
      h1 { font-size: 1.1rem; }

      /* Stat cards: 2 per row */
      .row.g-3 > .col-6 { flex: 0 0 50%; max-width: 50%; }
      .stat-card { padding: 0.75rem 0.85rem; gap: 0.6rem; }
      .stat-card .stat-icon { width: 36px; height: 36px; font-size: 0.95rem; }
      .stat-card .stat-value { font-size: 1.2rem; }

      .info-grid { grid-template-columns: 1fr 1fr; gap: 0.5rem; }
      .modal-footer-bar { justify-content: stretch; gap: 0.5rem; }
      .modal-footer-bar button { flex: 1; text-align: center; justify-content: center; }
      .status-modal { border-radius: 12px; }
      .modal-header-bar { border-radius: 12px 12px 0 0; padding: 0.85rem 1rem; }
      .modal-header-bar h2 { font-size: 0.92rem; }
    }

    /* Small mobile */
    @media (max-width: 480px) {
      main { padding: 0.75rem; }
      .modal { padding: 0.5rem; }
      .modal-body-pad { padding: 0.9rem; }
      .info-grid { grid-template-columns: 1fr; }
      .field input[type="text"] { font-size: 0.82rem; padding: 0.35rem 0.5rem; }
    }

    /* Very small */
    @media (max-width: 360px) {
      main { padding: 0.6rem; }
      h1 { font-size: 1rem; }
      .stat-card .stat-value { font-size: 1.1rem; }
      .modal-footer-bar { padding: 0.75rem; }
    }
  </style>
</head>

<body>
<main>
  <h1><i class="fas fa-file-alt me-2"></i>Loan Applications Management</h1>

  <!-- ── Summary Stat Cards ── -->
  <div class="row g-3 mb-2">
    <div class="col-6 col-sm-6 col-md-3">
      <div class="stat-card stat-pending">
        <div class="stat-icon"><i class="fas fa-clock"></i></div>
        <div>
          <div class="stat-value"><?= $counts['Pending'] ?></div>
          <div class="stat-label">Pending</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-sm-6 col-md-3">
      <div class="stat-card stat-approved">
        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
        <div>
          <div class="stat-value"><?= $counts['Approved'] ?></div>
          <div class="stat-label">Approved</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-sm-6 col-md-3">
      <div class="stat-card stat-active">
        <div class="stat-icon"><i class="fas fa-bolt"></i></div>
        <div>
          <div class="stat-value"><?= $counts['Active'] ?></div>
          <div class="stat-label">Active</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-sm-6 col-md-3">
      <div class="stat-card stat-rejected">
        <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
        <div>
          <div class="stat-value"><?= $counts['Rejected'] ?></div>
          <div class="stat-label">Rejected</div>
        </div>
      </div>
    </div>
  </div>

  <!-- ── PENDING TABLE ── -->
  <h2 class="section-title">
    <i class="fas fa-clipboard-list me-1"></i>
    Pending Applications
    <span class="badge-status badge-pending"><?= $counts['Pending'] ?></span>
  </h2>

  <div class="table-card">
    <div class="table-responsive">
      <table>
        <thead>
          <tr>
            <th>Loan ID</th>
            <th>Client Name</th>
            <th>Loan Type</th>
            <th>Amount</th>
            <th>Loan Officer ID</th>
            <th>Time</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody id="pendingTableBody">
          <?php
          $result = $conn->query(buildLoanQuery("WHERE la.status = 'Pending' ORDER BY la.id DESC"));
          if ($result && $result->num_rows > 0):
              while ($row = $result->fetch_assoc()):
                  $applied_date = date("m/d/Y", strtotime($row['created_at'] ?? 'now'));
                  $applied_time = date("h:i A",  strtotime($row['created_at'] ?? 'now'));
          ?>
              <tr data-loan-id="<?= (int)$row['id'] ?>">
                <td><strong>#<?= htmlspecialchars($row['id']) ?></strong></td>
                <td><?= htmlspecialchars($row['full_name']) ?></td>
                <td><?= htmlspecialchars($row['loan_type_name']) ?></td>
                <td>₱<?= number_format($row['loan_amount'], 2) ?></td>
                <td><?= htmlspecialchars($_SESSION['loan_officer_id'] ?? 'LO-0001') ?></td>
                <td><?= $applied_date ?><br><small class="text-muted"><?= $applied_time ?></small></td>
                <td><span class="badge-status badge-pending">Pending</span></td>
                <td>
                  <button class="btn-view" onclick="viewLoanApplication(<?= (int)$row['id'] ?>, 'pending')">
                    <i class="fas fa-eye"></i>View
                  </button>
                </td>
              </tr>
          <?php endwhile;
          else: ?>
              <tr class="empty-row"><td colspan="8"><i class="fas fa-inbox me-2"></i>No pending loans</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ── APPROVED TABLE ── -->
  <h2 class="section-title">
    <i class="fas fa-check-circle me-1"></i>
    Approved Applications — Awaiting Claim
    <span class="badge-status badge-approved"><?= $counts['Approved'] ?></span>
  </h2>

  <div class="table-card">
    <div class="table-responsive">
      <table>
        <thead>
          <tr>
            <th>Loan ID</th>
            <th>Client Name</th>
            <th>Loan Type</th>
            <th>Amount</th>
            <th>Loan Officer ID</th>
            <th>Approved Date</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody id="approvedTableBody">
          <?php
          $result = $conn->query(buildLoanQuery("WHERE la.status = 'Approved' ORDER BY lap.approved_at DESC"));
          if ($result && $result->num_rows > 0):
              while ($row = $result->fetch_assoc()):
                  $approved_date = $row['approved_at'] ? date("m/d/Y", strtotime($row['approved_at'])) : '—';
                  $approved_time = $row['approved_at'] ? date("h:i A", strtotime($row['approved_at'])) : '';
          ?>
              <tr data-loan-id="<?= (int)$row['id'] ?>">
                <td><strong>#<?= htmlspecialchars($row['id']) ?></strong></td>
                <td><?= htmlspecialchars($row['full_name']) ?></td>
                <td><?= htmlspecialchars($row['loan_type_name']) ?></td>
                <td>₱<?= number_format($row['loan_amount'], 2) ?></td>
                <td><?= htmlspecialchars($_SESSION['loan_officer_id'] ?? 'LO-0001') ?></td>
                <td><?= $approved_date ?><br><small class="text-muted"><?= $approved_time ?></small></td>
                <td><span class="badge-status badge-approved">Approved</span></td>
                <td>
                  <button class="btn-view" onclick="viewLoanApplication(<?= (int)$row['id'] ?>, 'approved')">
                    <i class="fas fa-eye"></i>View
                  </button>
                </td>
              </tr>
          <?php endwhile;
          else: ?>
              <tr class="empty-row"><td colspan="8"><i class="fas fa-inbox me-2"></i>No approved loans awaiting claim</td></tr>
          <?php endif;
          $conn->close(); ?>
        </tbody>
      </table>
    </div>
  </div>
</main>

<!-- ── Application Details Modal ── -->
<div id="statusModal" class="modal">
  <div class="status-modal">

    <div class="modal-header-bar">
      <h2><i class="fas fa-file-invoice-dollar me-2"></i>Loan Application Details</h2>
      <button class="close-status" onclick="closeApplicationModal()"><i class="fas fa-times"></i></button>
    </div>

    <div class="modal-body-pad">

      <!-- Account Information -->
      <div class="modal-section-title">Account Information</div>
      <div class="info-grid">
        <div class="field"><label>Full Name</label>       <input type="text" id="modal-full-name"       readonly></div>
        <div class="field"><label>Account Number</label>  <input type="text" id="modal-account-number"  readonly></div>
        <div class="field"><label>Loan ID</label>         <input type="text" id="modal-loan-id"         readonly></div>
        <div class="field"><label>Contact Number</label>  <input type="text" id="modal-contact-number"  readonly></div>
        <div class="field"><label>Email Address</label>   <input type="text" id="modal-email"           readonly></div>
        <div class="field"><label>Job Title</label>       <input type="text" id="modal-job"             readonly></div>
        <div class="field"><label>Monthly Salary</label>  <input type="text" id="modal-monthly-salary"  readonly></div>
        <div class="field"><label>Date Applied</label>    <input type="text" id="modal-date-applied"    readonly></div>
      </div>

      <!-- Loan Details -->
      <div class="modal-section-title">Loan Details</div>
      <div class="info-grid">
        <div class="field"><label>Loan Type</label>       <input type="text" id="modal-loan-type"       readonly></div>
        <div class="field"><label>Loan Term</label>       <input type="text" id="modal-loan-term"       readonly></div>
        <div class="field"><label>Loan Amount</label>     <input type="text" id="modal-loan-amount"     readonly></div>
        <div class="field"><label>Purpose</label>         <input type="text" id="modal-purpose"         readonly></div>
        <div class="field"><label>Valid ID Type</label>   <input type="text" id="modal-valid-id-type"   readonly></div>
        <div class="field"><label>Valid ID Number</label> <input type="text" id="modal-valid-id-number" readonly></div>
      </div>

      <!-- Payment Summary -->
      <div class="modal-section-title">Payment Summary (20% Annual Interest)</div>
      <div class="info-grid">
        <div class="field"><label>Monthly Payment</label> <input type="text" id="modal-monthly-payment" readonly></div>
        <div class="field"><label>Total Payable</label>   <input type="text" id="modal-total-payable"   readonly></div>
        <div class="field"><label>Next Payment Due</label><input type="text" id="modal-next-payment"    readonly></div>
        <div class="field"><label>Status</label>          <input type="text" id="modal-status"          readonly></div>
      </div>

      <!-- Uploaded Documents -->
      <div class="modal-section-title">Uploaded Documents</div>
      <div class="row g-2 mt-1">
        <div class="col-12 col-sm-4">
          <div class="field">
            <label>Valid ID</label>
            <button type="button" id="view-valid-id-btn" class="view-doc-btn" onclick="viewDocument('valid_id')">
              <i class="fas fa-id-card"></i>View Document
            </button>
          </div>
        </div>
        <div class="col-12 col-sm-4">
          <div class="field">
            <label>Proof of Income</label>
            <button type="button" id="view-proof-income-btn" class="view-doc-btn" onclick="viewDocument('proof_of_income')">
              <i class="fas fa-file-invoice"></i>View Document
            </button>
          </div>
        </div>
        <div class="col-12 col-sm-4">
          <div class="field">
            <label>COE</label>
            <button type="button" id="view-coe-btn" class="view-doc-btn" onclick="viewDocument('coe_document')">
              <i class="fas fa-certificate"></i>View Document
            </button>
          </div>
        </div>
      </div>

    </div><!-- /modal-body-pad -->

    <!-- Action Buttons -->
    <div class="modal-footer-bar">
      <button class="back-status"  onclick="closeApplicationModal()"><i class="fas fa-arrow-left me-1"></i>Back</button>
      <button id="approve-btn" class="approve-btn" onclick="confirmAndApproveLoan()"><i class="fas fa-check me-1"></i>Approve</button>
      <button class="reject-btn"   onclick="confirmAndRejectLoan()"><i class="fas fa-times me-1"></i>Reject</button>
    </div>

  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>

<script>
  /* ── All original logic — completely untouched ── */

  let currentLoanId    = null;
  let currentLoanStage = 'pending';
  let currentValidId      = '';
  let currentProofIncome  = '';
  let currentCoeDocument  = '';
  let currentClientName   = '';

  function viewDocument(docType) {
    const map  = { valid_id: currentValidId, proof_of_income: currentProofIncome, coe_document: currentCoeDocument };
    const path = map[docType] || '';
    if (!path) { alert('No document uploaded for ' + docType.replace('_', ' ')); return; }
    window.open(path, '_blank');
  }

  function viewLoanApplication(loanId, stage) {
    currentLoanId    = loanId;
    currentLoanStage = stage;

    fetch('view_loan.php?id=' + loanId)
      .then(r => r.json())
      .then(data => {
        if (data.error) { alert(data.error); return; }

        currentClientName = data.full_name || '';

        document.getElementById('modal-full-name').value      = data.full_name       || '';
        document.getElementById('modal-account-number').value = data.account_number  || '';
        document.getElementById('modal-loan-id').value        = data.id              || '';
        document.getElementById('modal-contact-number').value = data.contact_number  || '';
        document.getElementById('modal-email').value          = data.email           || data.borrower_email || '';
        document.getElementById('modal-job').value            = data.job             || '';
        document.getElementById('modal-monthly-salary').value =
          '₱' + parseFloat(data.monthly_salary || 0).toLocaleString(undefined, {minimumFractionDigits: 2});
        document.getElementById('modal-date-applied').value   = data.created_at
          ? new Date(data.created_at).toLocaleDateString('en-US', {year:'numeric', month:'long', day:'numeric'})
          : 'N/A';

        document.getElementById('modal-loan-type').value       = data.loan_type       || '';
        document.getElementById('modal-loan-term').value       = data.loan_terms      || '';
        document.getElementById('modal-loan-amount').value     =
          '₱' + parseFloat(data.loan_amount || 0).toLocaleString(undefined, {minimumFractionDigits: 2});
        document.getElementById('modal-purpose').value         = data.purpose         || '';
        document.getElementById('modal-valid-id-type').value   = data.valid_id_type   || 'N/A';
        document.getElementById('modal-valid-id-number').value = data.valid_id_number || 'N/A';

        document.getElementById('modal-monthly-payment').value =
          '₱' + parseFloat(data.monthly_payment || 0).toLocaleString(undefined, {minimumFractionDigits: 2});
        document.getElementById('modal-total-payable').value   =
          '₱' + (parseFloat(data.loan_amount || 0) * 1.20).toLocaleString(undefined, {minimumFractionDigits: 2});
        document.getElementById('modal-next-payment').value    = data.next_payment_due
          ? new Date(data.next_payment_due).toLocaleDateString('en-US', {year:'numeric', month:'long', day:'numeric'})
          : 'N/A';
        document.getElementById('modal-status').value          = data.status || '';

        currentValidId     = data.file_name       || data.file_url || '';
        currentProofIncome = data.proof_of_income || '';
        currentCoeDocument = data.coe_document    || '';

        document.getElementById('view-valid-id-btn').disabled     = !currentValidId;
        document.getElementById('view-proof-income-btn').disabled = !currentProofIncome;
        document.getElementById('view-coe-btn').disabled          = !currentCoeDocument;

        const approveBtn = document.getElementById('approve-btn');
        approveBtn.innerHTML = stage === 'approved'
          ? '<i class="fas fa-bolt me-1"></i>Activate Loan'
          : '<i class="fas fa-check me-1"></i>Approve';

        document.getElementById('statusModal').style.display = 'flex';
        document.getElementById('statusModal').classList.add('show');
      })
      .catch(err => { console.error(err); alert('Failed to load loan details.'); });
  }

  function confirmAndApproveLoan() {
    if (!currentLoanId) return;
    if (currentLoanStage === 'pending') {
      if (confirm('Approve this loan for ' + currentClientName + '?\nClient must claim within 30 days.')) {
        updateLoanStatus(currentLoanId, 'Approved', 'first_approve');
      }
    } else if (currentLoanStage === 'approved') {
      if (confirm('Confirm that ' + currentClientName + ' has claimed the loan?\nThis will activate the loan.')) {
        updateLoanStatus(currentLoanId, 'Active', 'second_approve');
      }
    }
  }

  function confirmAndRejectLoan() {
    if (!currentLoanId) return;
    const remarks = prompt('Enter rejection reason (required):');
    if (!remarks || !remarks.trim()) { alert('Remarks are required for rejection.'); return; }
    if (confirm('Reject this loan for ' + currentClientName + '?')) {
      const action = currentLoanStage === 'approved' ? 'second_reject' : 'first_reject';
      updateLoanStatus(currentLoanId, 'Rejected', action, remarks);
    }
  }

  function updateLoanStatus(loanId, status, action, remarks) {
    remarks = remarks || '';
    fetch('./upload_loan_status.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({ loan_id: loanId, status, action, remarks })
    })
    .then(r => {
      if (r.status === 403) throw new Error('Access denied. Make sure you are logged in as admin.');
      if (r.status === 404) throw new Error('upload_loan_status.php not found.');
      if (!r.ok)            throw new Error('HTTP error: ' + r.status);
      const ct = r.headers.get('content-type');
      if (!ct || !ct.includes('application/json')) {
        return r.text().then(t => { throw new Error('Server error (non-JSON): ' + t.substring(0, 200)); });
      }
      return r.json();
    })
    .then(data => {
      if (data.success) {
        alert(data.message);
        const row = document.querySelector('tr[data-loan-id="' + loanId + '"]');
        if (row) row.remove();
        closeApplicationModal();
        location.reload();
      } else {
        alert('Update failed: ' + (data.error || 'Unknown error'));
      }
    })
    .catch(err => {
      console.error('updateLoanStatus error:', err);
      alert('Error: ' + err.message + '\n\nCheck console (F12) for details.');
    });
  }

  function closeApplicationModal() {
    const modal = document.getElementById('statusModal');
    modal.classList.remove('show');
    setTimeout(() => modal.style.display = 'none', 300);
  }

  window.onclick = function (e) {
    if (e.target.id === 'statusModal') closeApplicationModal();
  };
</script>
</body>
</html>