<?php
session_start();
require 'db.php';

// ============================================================
// PROTECTED DELIVERABLE DOWNLOAD
// ============================================================
// Serves the finished file an admin uploaded for a custom request
// (see manage-requests.php). Same shape as download.php: access
// depends on the viewer's own session, not a secret in the URL.
//
// Gate logic:
//   - The request must belong to the logged-in customer (or the
//     viewer is a logged-in admin, who can always preview it).
//   - If the request IS linked to an order (it went through the
//     cart -> custom request -> checkout flow), that order must be
//     'paid' before the customer can download — mirrors the same
//     rule regular product downloads already follow, so a finished
//     file can't be fetched by a customer whose payment was never
//     actually verified, even if an admin uploaded it early.
//   - If the request has NO linked order (submitted outside the
//     cart flow, e.g. straight from the Custom page), there's
//     nothing to check payment against, so the admin's decision to
//     upload the file is the only gate.
// ============================================================

$requestId = (int) ($_GET['request_id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM custom_requests WHERE id = ?');
$stmt->execute([$requestId]);
$request = $stmt->fetch();

if (!$request || !$request['deliverable_file']) {
    http_response_code(404);
    die('No file available for this request.');
}

$isAdmin = !empty($_SESSION['is_admin']);
$isOwner = !empty($_SESSION['is_user']) && (int) ($_SESSION['user_id'] ?? 0) === (int) $request['user_id'];

if (!$isAdmin && !$isOwner) {
    http_response_code(403);
    die('You do not have access to this file.');
}

if (!$isAdmin) {
    // Customer path: if this request has a linked order, it must be
    // paid. Admins bypass this so they can preview/QA the file
    // regardless of payment state.
    $orderStmt = $pdo->prepare('SELECT status FROM orders WHERE custom_request_id = ?');
    $orderStmt->execute([$requestId]);
    $linkedOrder = $orderStmt->fetch();

    if ($linkedOrder && $linkedOrder['status'] !== 'paid') {
        http_response_code(403);
        die('This download unlocks once your payment has been verified.');
    }
}

// basename() is defense in depth: deliverable_file comes from our own
// database (never the request), but this guarantees it can only ever
// point at a file directly inside uploads/deliverables/.
$filePath = __DIR__ . '/uploads/deliverables/' . basename($request['deliverable_file']);
if (!is_file($filePath)) {
    die('The file could not be found on the server. Please contact support.');
}

$base = trim(preg_replace('/[^A-Za-z0-9 _-]/', '', $request['product_type'] ?: 'deliverable'));
if ($base === '') {
    $base = 'deliverable';
}
$downloadName = $base . '.' . pathinfo($filePath, PATHINFO_EXTENSION);

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . filesize($filePath));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($filePath);
exit;
