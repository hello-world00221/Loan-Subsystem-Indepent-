<?php
// ─── Session must start BEFORE including admin_header.php ────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ─── Include header (contains auth guard + sidebar + topbar) ─────────────────
include 'admin_header.php';

// ─── Connect to loandb ────────────────────────────────────────────────────────
$conn = new mysqli("localhost", "root", "", "loandb");
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("DB Error: " . $conn->connect_error);
}

// ─── Loan status counts ───────────────────────────────────────────────────────
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
$totalLoans = array_sum($counts);
?>

<!-- Page-specific title (head already opened in admin_header.php) -->
<title>Evergreen | Loan Dashboard</title>
<link rel="stylesheet" href="adminstyle.css" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<style>
  /* ══ PAGE-LEVEL OVERRIDES (dashboard content only) ════════════════════════ */

  /* ── Stat cards ── */
  .eg-loan-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 18px;
    margin-bottom: 32px;
  }
  .eg-loan-card {
    background: var(--eg-card);
    border: 1.5px solid var(--eg-border);
    border-radius: 16px;
    padding: 22px 20px;
    box-shadow: 0 1px 6px rgba(10,59,47,0.06), 0 4px 16px rgba(10,59,47,0.04);
    cursor: pointer;
    transition: transform .2s, box-shadow .2s, border-color .2s;
    position: relative; overflow: hidden;
  }
  .eg-loan-card::before {
    content: ''; position: absolute;
    width: 70px; height: 70px; border-radius: 50%;
    top: -18px; right: -18px;
    background: var(--eg-light); opacity: .6;
  }
  .eg-loan-card:hover { transform: translateY(-3px); box-shadow: 0 6px 24px rgba(10,59,47,0.12); }
  .eg-loan-card.active-card { border-color: var(--eg-forest); }

  .eg-loan-card-icon {
    width: 38px; height: 38px; border-radius: 10px;
    background: var(--eg-light); display: flex;
    align-items: center; justify-content: center;
    margin-bottom: 14px; position: relative; z-index: 1;
  }
  .eg-loan-card-icon i { font-size: 17px; color: var(--eg-forest); }
  .eg-loan-card-label {
    font-size: 11px; font-weight: 700; text-transform: uppercase;
    letter-spacing: .6px; color: var(--eg-muted); margin-bottom: 6px;
    position: relative; z-index: 1;
  }
  .eg-loan-card-num {
    font-size: 34px; font-weight: 800; color: var(--eg-forest);
    line-height: 1; position: relative; z-index: 1;
  }

  /* colour variants */
  .eg-loan-card.c-all    { border-left: 4px solid #764ba2; }
  .eg-loan-card.c-all    .eg-loan-card-icon { background: rgba(118,75,162,.10); }
  .eg-loan-card.c-all    .eg-loan-card-icon i { color: #764ba2; }
  .eg-loan-card.c-all    .eg-loan-card-num  { color: #764ba2; }

  .eg-loan-card.c-active { border-left: 4px solid var(--eg-forest); }
  .eg-loan-card.c-active .eg-loan-card-icon { background: var(--eg-light); }
  .eg-loan-card.c-active .eg-loan-card-num  { color: var(--eg-forest); }

  .eg-loan-card.c-approved { border-left: 4px solid #4CAF50; }
  .eg-loan-card.c-approved .eg-loan-card-icon { background: rgba(76,175,80,.12); }
  .eg-loan-card.c-approved .eg-loan-card-icon i { color: #4CAF50; }
  .eg-loan-card.c-approved .eg-loan-card-num { color: #2e7d32; }

  .eg-loan-card.c-pending { border-left: 4px solid #FF9800; }
  .eg-loan-card.c-pending .eg-loan-card-icon { background: rgba(255,152,0,.12); }
  .eg-loan-card.c-pending .eg-loan-card-icon i { color: #FF9800; }
  .eg-loan-card.c-pending .eg-loan-card-num { color: #e65100; }

  .eg-loan-card.c-rejected { border-left: 4px solid #f44336; }
  .eg-loan-card.c-rejected .eg-loan-card-icon { background: rgba(244,67,54,.10); }
  .eg-loan-card.c-rejected .eg-loan-card-icon i { color: #f44336; }
  .eg-loan-card.c-rejected .eg-loan-card-num { color: #c62828; }

  /* ── Table card ── */
  .eg-table-card {
    background: var(--eg-card);
    border-radius: 16px;
    box-shadow: 0 1px 6px rgba(10,59,47,0.06);
    border: 1.5px solid var(--eg-border);
    overflow: hidden;
    margin-bottom: 32px;
  }
  .eg-table-card table {
    width: 100%; border-collapse: collapse;
  }
  .eg-table-card thead th {
    background: #f4f8f6;
    font-size: 11px; font-weight: 700;
    text-transform: uppercase; letter-spacing: .7px;
    color: var(--eg-muted); padding: 13px 20px;
    border-bottom: 1.5px solid var(--eg-border);
    white-space: nowrap;
  }
  .eg-table-card tbody tr {
    border-bottom: 1px solid #eef4f0;
    transition: background .15s;
  }
  .eg-table-card tbody tr:last-child { border-bottom: none; }
  .eg-table-card tbody tr:hover { background: #f8fcfa; }
  .eg-table-card tbody td {
    padding: 13px 20px; font-size: 13.5px;
    color: var(--eg-text); vertical-align: middle;
  }

  /* Status badges */
  .badge-status {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 4px 12px; border-radius: 20px;
    font-size: 11.5px; font-weight: 700; letter-spacing: .3px;
  }
  .badge-status::before { content: '●'; font-size: 8px; }
  .badge-status.pending  { background: rgba(255,152,0,.12); color: #e65100; border: 1px solid rgba(255,152,0,.30); }
  .badge-status.pending::before { color: #FF9800; }
  .badge-status.approved { background: rgba(76,175,80,.12); color: #2e7d32; border: 1px solid rgba(76,175,80,.30); }
  .badge-status.approved::before { color: #4CAF50; }
  .badge-status.active   { background: var(--eg-light); color: var(--eg-forest); border: 1px solid var(--eg-border); }
  .badge-status.active::before { color: var(--eg-mid); }
  .badge-status.rejected { background: rgba(244,67,54,.10); color: #c62828; border: 1px solid rgba(244,67,54,.25); }
  .badge-status.rejected::before { color: #f44336; }
  .badge-status.closed   { background: #f3f4f6; color: #6b7280; border: 1px solid #e5e7eb; }
  .badge-status.closed::before { color: #9ca3af; }

  /* View button */
  .btn-view {
    display: inline-flex; align-items: center; gap: 5px;
    background: var(--eg-light); border: 1px solid var(--eg-border);
    color: var(--eg-forest); font-size: 12px; font-weight: 600;
    cursor: pointer; padding: 5px 12px; border-radius: 7px;
    transition: all .2s; font-family: 'DM Sans', sans-serif;
  }
  .btn-view:hover { background: var(--eg-forest); color: #fff; border-color: var(--eg-forest); }

  /* ── Analytics section ── */
  .eg-analytics {
    background: var(--eg-card);
    border: 1.5px solid var(--eg-border);
    border-radius: 16px;
    padding: 28px;
    box-shadow: 0 1px 6px rgba(10,59,47,0.06);
    margin-bottom: 32px;
  }
  .eg-analytics-header {
    display: flex; align-items: center; gap: 10px;
    margin-bottom: 24px; color: var(--eg-forest);
  }
  .eg-analytics-header i  { font-size: 22px; }
  .eg-analytics-header h2 {
    margin: 0; font-size: 20px; font-weight: 700;
    font-family: 'Playfair Display', serif;
  }
  .eg-analytics-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px; align-items: center;
  }
  .eg-chart-wrapper {
    position: relative; height: 320px;
    display: flex; justify-content: center; align-items: center;
  }
  .eg-chart-stats {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
  }
  .eg-chart-stat {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    padding: 18px 16px; border-radius: 12px;
    border-left: 4px solid var(--eg-forest);
    transition: transform .2s, box-shadow .2s;
  }
  .eg-chart-stat:hover { transform: translateX(4px); box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
  .eg-chart-stat h4 {
    margin: 0 0 6px; font-size: 11px; font-weight: 700;
    text-transform: uppercase; letter-spacing: .6px; color: var(--eg-muted);
  }
  .eg-chart-stat p { margin: 0; font-size: 26px; font-weight: 800; }
  .eg-chart-stat.s-active   { border-left-color: var(--eg-forest); } .eg-chart-stat.s-active   p { color: var(--eg-forest); }
  .eg-chart-stat.s-approved { border-left-color: #4CAF50; }           .eg-chart-stat.s-approved p { color: #2e7d32; }
  .eg-chart-stat.s-pending  { border-left-color: #FF9800; }           .eg-chart-stat.s-pending  p { color: #e65100; }
  .eg-chart-stat.s-rejected { border-left-color: #f44336; }           .eg-chart-stat.s-rejected p { color: #c62828; }

  /* ── Report buttons ── */
  .eg-reports {
    background: var(--eg-card);
    border: 1.5px solid var(--eg-border);
    border-radius: 16px;
    padding: 28px;
    box-shadow: 0 1px 6px rgba(10,59,47,0.06);
    margin-bottom: 32px;
  }
  .eg-reports h3 {
    margin: 0 0 20px; color: var(--eg-forest);
    display: flex; align-items: center; gap: 8px;
    font-size: 18px; font-weight: 700;
    font-family: 'Playfair Display', serif;
  }
  .eg-report-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 14px;
  }
  .eg-report-btn {
    padding: 14px 20px; border: none; border-radius: 10px;
    font-size: 13.5px; font-weight: 600; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    gap: 8px; color: white;
    box-shadow: 0 4px 8px rgba(0,0,0,0.10);
    transition: transform .25s, box-shadow .25s;
    font-family: 'DM Sans', sans-serif;
  }
  .eg-report-btn:hover { transform: translateY(-3px); box-shadow: 0 6px 16px rgba(0,0,0,0.18); }
  .eg-report-btn:active { transform: translateY(-1px); }
  .eg-report-btn i { font-size: 16px; }
  .btn-r-all      { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
  .btn-r-active   { background: linear-gradient(135deg, var(--eg-forest) 0%, #1b5e20 100%); }
  .btn-r-approved { background: linear-gradient(135deg, #4CAF50 0%, var(--eg-forest) 100%); }
  .btn-r-pending  { background: linear-gradient(135deg, #FF9800 0%, #F57C00 100%); }
  .btn-r-rejected { background: linear-gradient(135deg, #f44336 0%, #c62828 100%); }

  /* ── Section header ── */
  .eg-section-header {
    display: flex; align-items: center; justify-content: space-between;
    margin: 32px 0 16px; flex-wrap: wrap; gap: 10px;
  }
  .eg-section-title {
    font-family: 'Playfair Display', serif;
    font-size: 20px; font-weight: 700; color: var(--eg-forest);
  }
  .eg-section-sub { font-size: 12.5px; color: var(--eg-muted); margin-top: 2px; }

  /* ── Modal ── */
  .modal {
    display: none; position: fixed; inset: 0; z-index: 3000;
    background: rgba(6,38,32,0.55);
    align-items: center; justify-content: center;
    padding: 20px;
    animation: fadeIn .2s ease;
  }
  .modal.show { display: flex; }
  @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
  .modal-content {
    background: var(--eg-card);
    border-radius: 16px;
    padding: 32px;
    max-width: 860px; width: 100%;
    max-height: 90vh; overflow-y: auto;
    box-shadow: 0 24px 64px rgba(6,38,32,0.28);
    border: 1.5px solid var(--eg-border);
  }
  .modal-content h2 {
    font-family: 'Playfair Display', serif;
    font-size: 22px; color: var(--eg-forest); margin-bottom: 8px;
  }
  .modal-content hr { border-color: var(--eg-border); margin: 16px 0; }
  .modal-content .details {
    display: grid; grid-template-columns: 1fr 1fr; gap: 24px;
  }
  .modal-content .column h3 {
    font-size: 15px; font-weight: 700; color: var(--eg-forest);
    margin-bottom: 12px; padding-bottom: 6px;
    border-bottom: 2px solid var(--eg-light);
  }
  .modal-content .column h4 {
    font-size: 13.5px; font-weight: 700; color: var(--eg-forest);
    margin: 14px 0 8px;
  }
  .modal-content p {
    font-size: 13.5px; margin-bottom: 8px; color: var(--eg-text);
  }
  .modal-content p strong { color: var(--eg-muted); font-weight: 600; margin-right: 4px; }
  .payment-summary {
    background: var(--eg-light); border-radius: 10px;
    padding: 14px 16px; margin: 14px 0;
    border: 1px solid var(--eg-border);
  }
  .payment-summary h4 {
    font-size: 12.5px; font-weight: 700; text-transform: uppercase;
    letter-spacing: .5px; color: var(--eg-muted); margin-bottom: 10px !important;
  }
  .view-doc-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px; border-radius: 8px;
    background: var(--eg-light); border: 1px solid var(--eg-border);
    color: var(--eg-forest); font-size: 13px; font-weight: 600;
    cursor: pointer; transition: all .2s; font-family: 'DM Sans', sans-serif;
  }
  .view-doc-btn:hover:not(:disabled) { background: var(--eg-forest); color: #fff; border-color: var(--eg-forest); }
  .view-doc-btn:disabled { opacity: 0.45; cursor: not-allowed; }
  .return-btn-container { text-align: right; margin-top: 24px; }
  #returnBtn {
    padding: 10px 28px; border-radius: 10px;
    background: linear-gradient(135deg, var(--eg-forest) 0%, var(--eg-mid) 100%);
    color: #fff; border: none; font-size: 14px; font-weight: 600;
    cursor: pointer; font-family: 'DM Sans', sans-serif;
    transition: transform .2s, box-shadow .2s;
    box-shadow: 0 2px 10px rgba(10,59,47,0.20);
  }
  #returnBtn:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(10,59,47,0.28); }

  @media (max-width: 768px) {
    .modal-content .details { grid-template-columns: 1fr; }
    .eg-analytics-grid { grid-template-columns: 1fr; }
  }
</style>

<!-- ══ MAIN ═══════════════════════════════════════════════════════════════════ -->
<main class="eg-content">

  <!-- Page heading -->
  <div style="margin-bottom:28px;">
    <h1 style="font-family:'Playfair Display',serif; font-size:28px; font-weight:700; color:var(--eg-forest); letter-spacing:-.2px;">
      Loan Dashboard
    </h1>
    <p style="font-size:13.5px; color:var(--eg-muted); margin-top:3px;">
      Welcome back, <?= htmlspecialchars($_SESSION['user_name'] ?? 'Staff') ?>. Here's your portfolio overview.
    </p>
  </div>

  <!-- ── Stat Cards ── -->
  <div class="eg-loan-cards">
    <div class="eg-loan-card c-all" onclick="filterLoans('All', this)">
      <div class="eg-loan-card-icon"><i class="bi bi-layers-fill"></i></div>
      <div class="eg-loan-card-label">All Loans</div>
      <div class="eg-loan-card-num"><?= $totalLoans ?></div>
    </div>
    <div class="eg-loan-card c-active" onclick="filterLoans('Active', this)">
      <div class="eg-loan-card-icon"><i class="bi bi-check-circle-fill"></i></div>
      <div class="eg-loan-card-label">Active</div>
      <div class="eg-loan-card-num"><?= $counts['Active'] ?></div>
    </div>
    <div class="eg-loan-card c-approved" onclick="filterLoans('Approved', this)">
      <div class="eg-loan-card-icon"><i class="bi bi-hand-thumbs-up-fill"></i></div>
      <div class="eg-loan-card-label">Awaiting Claim</div>
      <div class="eg-loan-card-num"><?= $counts['Approved'] ?></div>
    </div>
    <div class="eg-loan-card c-pending" onclick="filterLoans('Pending', this)">
      <div class="eg-loan-card-icon"><i class="bi bi-hourglass-split"></i></div>
      <div class="eg-loan-card-label">Pending</div>
      <div class="eg-loan-card-num"><?= $counts['Pending'] ?></div>
    </div>
    <div class="eg-loan-card c-rejected" onclick="filterLoans('Rejected', this)">
      <div class="eg-loan-card-icon"><i class="bi bi-x-circle-fill"></i></div>
      <div class="eg-loan-card-label">Rejected</div>
      <div class="eg-loan-card-num"><?= $counts['Rejected'] ?></div>
    </div>
  </div>

  <!-- ── Loan Records Table ── -->
  <div class="eg-section-header">
    <div>
      <div class="eg-section-title">All Loan Records</div>
      <div class="eg-section-sub">Click a status card above to filter</div>
    </div>
  </div>

  <div class="eg-table-card">
    <div style="overflow-x:auto;">
      <table>
        <thead>
          <tr>
            <th>Loan ID</th>
            <th>Client Name</th>
            <th>Loan Type</th>
            <th>Amount</th>
            <th>Officer ID</th>
            <th>Date</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody id="loansTableBody">
          <?php
          $result = $conn->query("
              SELECT
                  la.id,
                  la.loan_amount,
                  la.created_at,
                  la.status,
                  COALESCE(lb.full_name, la.user_email) AS full_name,
                  COALESCE(lt.name, 'Unknown Type')     AS loan_type_display
              FROM loan_applications la
              LEFT JOIN loan_borrowers lb ON lb.loan_application_id = la.id
              LEFT JOIN loan_types     lt ON lt.id = la.loan_type_id
              ORDER BY la.id DESC
          ");

          if ($result && $result->num_rows > 0):
              while ($row = $result->fetch_assoc()):
                  $date        = date("m/d/Y", strtotime($row['created_at'] ?? 'now'));
                  $time        = date("h:i A",  strtotime($row['created_at'] ?? 'now'));
                  $statusClass = strtolower($row['status']);
          ?>
            <tr data-status="<?= htmlspecialchars($row['status']) ?>">
              <td style="font-family:'Courier New',monospace; font-size:12.5px; color:var(--eg-muted);">
                #<?= htmlspecialchars($row['id']) ?>
              </td>
              <td style="font-weight:600;"><?= htmlspecialchars($row['full_name']) ?></td>
              <td><?= htmlspecialchars($row['loan_type_display']) ?></td>
              <td style="font-weight:600;">₱<?= number_format($row['loan_amount'], 2) ?></td>
              <td style="font-family:'Courier New',monospace; font-size:12.5px;">
                <?= htmlspecialchars($_SESSION['loan_officer_id'] ?? 'LO-0001') ?>
              </td>
              <td style="color:var(--eg-muted); font-size:13px;"><?= $date ?> <?= $time ?></td>
              <td>
                <span class="badge-status <?= $statusClass ?>">
                  <?= htmlspecialchars($row['status']) ?>
                </span>
              </td>
              <td>
                <button class="btn-view" onclick="viewLoanDetails(<?= (int)$row['id'] ?>)">
                  <i class="bi bi-eye-fill" style="font-size:11px;"></i> View
                </button>
              </td>
            </tr>
          <?php endwhile;
          else: ?>
            <tr>
              <td colspan="8" style="text-align:center; padding:48px; color:var(--eg-muted);">
                <i class="bi bi-inbox" style="font-size:36px; display:block; margin-bottom:10px; opacity:.35;"></i>
                No records found
              </td>
            </tr>
          <?php endif;
          $conn->close(); ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ── Analytics ── -->
  <div class="eg-analytics">
    <div class="eg-analytics-header">
      <i class="bi bi-pie-chart-fill"></i>
      <h2>Loan Portfolio Analytics</h2>
    </div>
    <div class="eg-analytics-grid">
      <div class="eg-chart-wrapper">
        <canvas id="loanPieChart"></canvas>
      </div>
      <div class="eg-chart-stats">
        <div class="eg-chart-stat s-active">
          <h4>Active Loans</h4>
          <p><?= $counts['Active'] ?></p>
        </div>
        <div class="eg-chart-stat s-approved">
          <h4>Awaiting Claim</h4>
          <p><?= $counts['Approved'] ?></p>
        </div>
        <div class="eg-chart-stat s-pending">
          <h4>Pending Review</h4>
          <p><?= $counts['Pending'] ?></p>
        </div>
        <div class="eg-chart-stat s-rejected">
          <h4>Rejected</h4>
          <p><?= $counts['Rejected'] ?></p>
        </div>
      </div>
    </div>
  </div>

  <!-- ── Report Buttons ── -->
  <div class="eg-reports">
    <h3><i class="bi bi-file-earmark-pdf-fill"></i> Generate Loan Reports</h3>
    <div class="eg-report-grid">
      <button class="eg-report-btn btn-r-all"      onclick="generateReport('all')">
        <i class="bi bi-file-earmark-text-fill"></i> All Loans
      </button>
      <button class="eg-report-btn btn-r-active"   onclick="generateReport('active')">
        <i class="bi bi-check-circle-fill"></i> Active Loans
      </button>
      <button class="eg-report-btn btn-r-approved" onclick="generateReport('approved')">
        <i class="bi bi-hand-thumbs-up-fill"></i> Approved Loans
      </button>
      <button class="eg-report-btn btn-r-pending"  onclick="generateReport('pending')">
        <i class="bi bi-hourglass-split"></i> Pending Loans
      </button>
      <button class="eg-report-btn btn-r-rejected" onclick="generateReport('rejected')">
        <i class="bi bi-x-circle-fill"></i> Rejected Loans
      </button>
    </div>
  </div>

</main>

<!-- ══ View Details Modal ═════════════════════════════════════════════════════ -->
<div id="viewLoanModal" class="modal">
  <div class="modal-content">
    <h2>Client Loan Details <span style="font-size:14px;font-weight:400;color:var(--eg-muted);">(View Only)</span></h2>
    <p style="font-size:13px;color:var(--eg-muted);">
      <strong>Loan Officer:</strong>
      <span style="font-family:'Courier New',monospace; color:var(--eg-forest);">
        <?= htmlspecialchars($_SESSION['loan_officer_id'] ?? 'LO-0001') ?>
      </span>
    </p>
    <hr>
    <div class="details">
      <div class="column">
        <h3>Account Details</h3>
        <p><strong>Full Name:</strong>       <span id="modal-full-name"></span></p>
        <p><strong>Account Number:</strong>  <span id="modal-account-number"></span></p>
        <p><strong>Loan ID:</strong>         <span id="modal-loan-id"></span></p>
        <p><strong>Contact Number:</strong>  <span id="modal-contact-number"></span></p>
        <p><strong>Email:</strong>           <span id="modal-email"></span></p>
        <p><strong>Job Title:</strong>       <span id="modal-job"></span></p>
        <p><strong>Monthly Salary:</strong> ₱<span id="modal-monthly-salary"></span></p>
        <p><strong>Valid ID Type:</strong>   <span id="modal-valid-id-type"></span></p>
        <p><strong>Valid ID Number:</strong> <span id="modal-valid-id-number"></span></p>
        <hr>
        <div id="approval-info" style="display:none;">
          <h4>Approval Information</h4>
          <p><strong>Approved By:</strong> <span id="modal-approved-by"></span></p>
          <p><strong>Approved At:</strong> <span id="modal-approved-at"></span></p>
        </div>
        <div id="rejection-info" style="display:none;">
          <h4>Rejection Information</h4>
          <p><strong>Rejected By:</strong>       <span id="modal-rejected-by"></span></p>
          <p><strong>Rejected At:</strong>       <span id="modal-rejected-at"></span></p>
          <p><strong>Rejection Remarks:</strong> <span id="modal-reject-remarks"></span></p>
        </div>
        <div style="margin-top:18px;">
          <h3>Uploaded Documents</h3>
          <div style="display:flex; flex-direction:column; gap:10px; margin-top:8px;">
            <button type="button" id="view-valid-id-btn"     class="view-doc-btn" onclick="viewDocument('valid_id')">
              <i class="bi bi-card-image"></i> Valid ID
            </button>
            <button type="button" id="view-proof-income-btn" class="view-doc-btn" onclick="viewDocument('proof_of_income')">
              <i class="bi bi-file-earmark-text"></i> Proof of Income
            </button>
            <button type="button" id="view-coe-btn"          class="view-doc-btn" onclick="viewDocument('coe_document')">
              <i class="bi bi-patch-check"></i> Certificate of Employment
            </button>
          </div>
        </div>
      </div>

      <div class="column">
        <h3>Loan Details</h3>
        <p><strong>Loan Type:</strong>    <span id="modal-loan-type"></span></p>
        <p><strong>Loan Amount:</strong> ₱<span id="modal-loan-amount"></span></p>
        <p><strong>Loan Term:</strong>    <span id="modal-loan-term"></span></p>
        <p><strong>Purpose:</strong>      <span id="modal-purpose"></span></p>
        <p><strong>Date Applied:</strong> <span id="modal-date-applied"></span></p>
        <div class="payment-summary">
          <h4>Payment Summary (20% Annual Interest)</h4>
          <p><strong>Monthly Payment:</strong> ₱<span id="modal-monthly-payment"></span></p>
          <p><strong>Total Payable:</strong>   ₱<span id="modal-total-payable"></span></p>
          <p><strong>Next Payment Due:</strong> <span id="modal-next-payment"></span></p>
        </div>
        <p>
          <strong>Status:</strong>
          <span id="modal-status-badge"></span>
        </p>
      </div>
    </div>

    <div class="return-btn-container">
      <button id="returnBtn" onclick="closeViewModal()">
        <i class="bi bi-arrow-left"></i> Return
      </button>
    </div>
  </div>
</div>

<script>
  const chartData = {
    active:   <?= $counts['Active']   ?>,
    approved: <?= $counts['Approved'] ?>,
    pending:  <?= $counts['Pending']  ?>,
    rejected: <?= $counts['Rejected'] ?>
  };

  document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('loanPieChart');
    if (ctx) {
      window.loanPieChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
          labels: ['Active', 'Awaiting Claim', 'Pending', 'Rejected'],
          datasets: [{
            data: [chartData.active, chartData.approved, chartData.pending, chartData.rejected],
            backgroundColor: ['#0a3b2f', '#4CAF50', '#FF9800', '#f44336'],
            borderWidth: 4,
            borderColor: '#ffffff',
            hoverOffset: 10
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          cutout: '62%',
          plugins: {
            legend: {
              position: 'bottom',
              labels: {
                padding: 20,
                font: { size: 13, weight: 'bold', family: 'DM Sans' },
                usePointStyle: true, pointStyle: 'circle'
              }
            },
            tooltip: {
              callbacks: {
                label: function (context) {
                  const total = context.dataset.data.reduce((a, b) => a + b, 0);
                  const pct   = total > 0 ? ((context.parsed / total) * 100).toFixed(1) : '0.0';
                  return ` ${context.label}: ${context.parsed} (${pct}%)`;
                }
              },
              backgroundColor: 'rgba(6,38,32,0.90)',
              padding: 12,
              titleFont: { family: 'DM Sans' },
              bodyFont: { family: 'DM Sans' }
            }
          }
        }
      });
    }
  });

  /* ── Filter rows by status ── */
  function filterLoans(status, cardEl) {
    document.querySelectorAll('.eg-loan-card').forEach(c => c.classList.remove('active-card'));
    if (cardEl) cardEl.classList.add('active-card');
    document.querySelectorAll('#loansTableBody tr').forEach(row => {
      row.style.display = (status === 'All' || row.dataset.status === status) ? '' : 'none';
    });
  }

  /* ── Document viewer ── */
  let currentValidId = '', currentProofIncome = '', currentCoeDocument = '';
  function viewDocument(docType) {
    const map  = { valid_id: currentValidId, proof_of_income: currentProofIncome, coe_document: currentCoeDocument };
    const path = map[docType] || '';
    if (!path) { alert('No document uploaded'); return; }
    window.open(path, '_blank');
  }

  /* ── View loan details ── */
  function viewLoanDetails(loanId) {
    fetch(`view_loan.php?id=${loanId}`)
      .then(r => r.json())
      .then(data => {
        if (data.error) return alert(data.error);

        document.getElementById('modal-full-name').textContent       = data.full_name       || '';
        document.getElementById('modal-account-number').textContent  = data.account_number  || '';
        document.getElementById('modal-loan-id').textContent         = data.id              || '';
        document.getElementById('modal-contact-number').textContent  = data.contact_number  || '';
        document.getElementById('modal-email').textContent           = data.email           || '';
        document.getElementById('modal-job').textContent             = data.job             || '';
        document.getElementById('modal-monthly-salary').textContent  = parseFloat(data.monthly_salary || 0).toLocaleString(undefined, {minimumFractionDigits:2});
        document.getElementById('modal-valid-id-type').textContent   = data.valid_id_type   || 'N/A';
        document.getElementById('modal-valid-id-number').textContent = data.valid_id_number || 'N/A';
        document.getElementById('modal-loan-type').textContent       = data.loan_type       || '';
        document.getElementById('modal-loan-amount').textContent     = parseFloat(data.loan_amount || 0).toLocaleString(undefined, {minimumFractionDigits:2});
        document.getElementById('modal-loan-term').textContent       = data.loan_terms      || '';
        document.getElementById('modal-purpose').textContent         = data.purpose         || '';
        document.getElementById('modal-date-applied').textContent    = data.created_at  ? new Date(data.created_at).toLocaleDateString()  : 'N/A';
        document.getElementById('modal-next-payment').textContent    = data.next_payment_due ? new Date(data.next_payment_due).toLocaleDateString() : 'N/A';
        document.getElementById('modal-monthly-payment').textContent = parseFloat(data.monthly_payment || 0).toLocaleString(undefined, {minimumFractionDigits:2});
        document.getElementById('modal-total-payable').textContent   = (parseFloat(data.loan_amount || 0) * 1.20).toLocaleString(undefined, {minimumFractionDigits:2});

        // Status badge
        const st  = data.status || '';
        const stL = st.toLowerCase();
        document.getElementById('modal-status-badge').innerHTML =
          `<span class="badge-status ${stL}">${st}</span>`;

        // Approval / rejection info
        const approvalInfo  = document.getElementById('approval-info');
        const rejectionInfo = document.getElementById('rejection-info');
        if ((stL === 'active' || stL === 'approved') && data.approved_by) {
          document.getElementById('modal-approved-by').textContent = data.approved_by;
          document.getElementById('modal-approved-at').textContent = new Date(data.approved_at).toLocaleString();
          approvalInfo.style.display  = 'block';
          rejectionInfo.style.display = 'none';
        } else if (stL === 'rejected' && data.rejected_by) {
          document.getElementById('modal-rejected-by').textContent    = data.rejected_by;
          document.getElementById('modal-rejected-at').textContent    = new Date(data.rejected_at).toLocaleString();
          document.getElementById('modal-reject-remarks').textContent = data.rejection_remarks || '—';
          rejectionInfo.style.display = 'block';
          approvalInfo.style.display  = 'none';
        } else {
          approvalInfo.style.display  = 'none';
          rejectionInfo.style.display = 'none';
        }

        currentValidId     = data.file_url        || '';
        currentProofIncome = data.proof_of_income || '';
        currentCoeDocument = data.coe_document    || '';

        document.getElementById('view-valid-id-btn').disabled     = !currentValidId;
        document.getElementById('view-proof-income-btn').disabled = !currentProofIncome;
        document.getElementById('view-coe-btn').disabled          = !currentCoeDocument;

        const modal = document.getElementById('viewLoanModal');
        modal.style.display = 'flex';
        setTimeout(() => modal.classList.add('show'), 10);
      })
      .catch(err => console.error('Error:', err));
  }

  function closeViewModal() {
    const modal = document.getElementById('viewLoanModal');
    modal.classList.remove('show');
    setTimeout(() => modal.style.display = 'none', 200);
  }
  window.addEventListener('click', function (e) {
    const modal = document.getElementById('viewLoanModal');
    if (e.target === modal) closeViewModal();
  });

  /* ── Generate PDF report ── */
  function generateReport(type) {
    const chartImage = window.loanPieChart ? window.loanPieChart.toBase64Image() : '';
    fetch(`generate_report.php?type=${type}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ chartImage })
    })
      .then(r => r.json())
      .then(data => {
        if (data.success) window.open(data.filename, '_blank');
        else alert('Error: ' + (data.error || 'Unknown error'));
      })
      .catch(() => alert('Failed to generate report'));
  }
</script>