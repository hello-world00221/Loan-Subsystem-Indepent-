<?php
/**
 * serve_file.php  (lives in LoanSubsystem/Employee/)
 *
 * Securely proxies uploaded documents stored in
 * LoanSubsystem/Loan/uploads/ to the browser.
 *
 * Usage:  serve_file.php?file=valid_id_xxx.png
 *
 * - Auth required (admin or loan officer)
 * - Only serves files from the Loan/uploads/ directory
 * - Prevents path traversal attacks
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/loan_auth.php';

// ─── Auth check ───────────────────────────────────────────────────────────────
if (!loan_auth_check()) {
    http_response_code(403);
    exit('Access denied.');
}

// ─── Get requested filename ───────────────────────────────────────────────────
$filename = isset($_GET['file']) ? basename($_GET['file']) : '';

if (empty($filename)) {
    http_response_code(400);
    exit('No file specified.');
}

// ─── Build absolute path to the file ─────────────────────────────────────────
// This file is in: LoanSubsystem/Employee/
// Files are in:    LoanSubsystem/Loan/uploads/
$loan_uploads = dirname(__DIR__) . '/Loan/uploads/';
$filepath     = $loan_uploads . $filename;

// ─── Security: ensure no path traversal ──────────────────────────────────────
$realpath       = realpath($filepath);
$real_uploaddir = realpath($loan_uploads);

if ($realpath === false || strpos($realpath, $real_uploaddir) !== 0) {
    http_response_code(403);
    exit('Invalid file path.');
}

// ─── Check file exists ────────────────────────────────────────────────────────
if (!file_exists($realpath)) {
    http_response_code(404);
    exit('File not found.');
}

// ─── Detect MIME type and stream the file ────────────────────────────────────
$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mimetype = $finfo->file($realpath);

// Fallback MIME map by extension
if (!$mimetype) {
    $ext      = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $mime_map = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'pdf'  => 'application/pdf',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];
    $mimetype = $mime_map[$ext] ?? 'application/octet-stream';
}

header('Content-Type: '        . $mimetype);
header('Content-Length: '      . filesize($realpath));
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Cache-Control: private, max-age=3600');

readfile($realpath);
exit;