<?php
session_start();
require 'db.php';

// Serves a payment-proof file (image/PDF) for exactly two audiences:
// the customer who owns the order, or a logged-in admin. Same
// "protected download" shape as download.php — access depends on the
// viewer's own session, not a secret in the URL, so a copied link is
// useless to anyone else.

$orderId = (int) ($_GET['order_id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order || !$order['payment_proof']) {
    http_response_code(404);
    die('Not found.');
}

$isOwner = !empty($_SESSION['is_user']) && (int) ($_SESSION['user_id'] ?? 0) === (int) $order['user_id'];
$isAdmin = !empty($_SESSION['is_admin']);

if (!$isOwner && !$isAdmin) {
    http_response_code(403);
    die('You do not have access to this file.');
}

// basename() is defense in depth: payment_proof comes from our own
// database (never the request), but this guarantees it can only ever
// point at a file directly inside uploads/payment-proofs/.
$filePath = __DIR__ . '/uploads/payment-proofs/' . basename($order['payment_proof']);
if (!is_file($filePath)) {
    die('The file could not be found on the server.');
}

$ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
$mimeMap = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'pdf' => 'application/pdf'];

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: ' . ($mimeMap[$ext] ?? 'application/octet-stream'));
header('Content-Disposition: inline; filename="proof-order-' . $order['id'] . '.' . $ext . '"');
header('Content-Length: ' . filesize($filePath));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($filePath);
exit;
