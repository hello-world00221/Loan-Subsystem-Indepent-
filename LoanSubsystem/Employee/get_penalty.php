<?php
/**
 * get_penalty.php
 * ──────────────────────────────────────────────────────────────────────────────
 * JSON API used by Dashboard.php and Payment.php to fetch the active penalty
 * for the logged-in user's loans.
 *
 * Returns: JSON array of { loan_id, penalty_amount, total_balance_with_penalty,
 *                           months_overdue, penalty_rate }
 * ──────────────────────────────────────────────────────────────────────────────
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_email'])) {
    echo json_encode(['error' => 'Unauthorized', 'penalties' => []]);
    exit;
}

$host   = 'localhost';
$dbname = 'loandb';
$dbuser = 'root';
$dbpass = '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $dbuser, $dbpass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    // Fetch active penalties for this user
    $stmt = $pdo->prepare("
        SELECT
            lpen.loan_application_id  AS loan_id,
            lpen.penalty_amount,
            lpen.total_balance_with_penalty,
            lpen.months_overdue,
            lpen.penalty_rate,
            lpen.original_balance,
            lpen.status               AS penalty_status
        FROM   loan_penalties lpen
        WHERE  lpen.user_email = ?
          AND  lpen.status = 'Active'
    ");
    $stmt->execute([$_SESSION['user_email']]);
    $rows = $stmt->fetchAll();

    // Key by loan_id for easy JS lookup
    $penaltyMap = [];
    foreach ($rows as $row) {
        $penaltyMap[(int)$row['loan_id']] = [
            'penalty_amount'             => (float)$row['penalty_amount'],
            'total_balance_with_penalty' => (float)$row['total_balance_with_penalty'],
            'months_overdue'             => (int)$row['months_overdue'],
            'penalty_rate'               => (float)$row['penalty_rate'],
            'original_balance'           => (float)$row['original_balance'],
            'penalty_status'             => $row['penalty_status'],
        ];
    }

    echo json_encode(['penalties' => $penaltyMap]);

} catch (PDOException $e) {
    echo json_encode(['error' => 'DB error', 'penalties' => []]);
}