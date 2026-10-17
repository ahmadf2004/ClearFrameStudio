<?php
session_start();
require 'db.php';

// ============================================================
// PROTECTED DOWNLOAD
// ============================================================
// This is the actual security-relevant piece of the marketplace: a
// download works ONLY if:
//   1. The visitor is logged in
//   2. The order_item they're asking for belongs to THEIR account
//      (not just any order — this exact customer's order)
//   3. The order that item belongs to has status = 'paid'
//
// Files live in product-files/, which has its own .htaccess denying
// all direct web access (created by manage-products.php on first
// upload) — so even someone who guesses or leaks a raw file path
// can't fetch it directly. The ONLY way to get a file's bytes is
// through this script, after passing all three checks above.
//
// Because access depends on the buyer's own logged-in session (not on
// a secret token in the URL), a copied download link is useless to
// anyone else — it just bounces them to the login page or a 403.
// ============================================================

if (empty($_SESSION['is_user'])) {
    header('Location: login.php');
    exit;
}

$userId      = (int) $_SESSION['user_id'];
$orderItemId = (int) ($_GET['item'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT order_items.*, orders.status AS order_status, orders.user_id AS order_user_id
     FROM order_items
     JOIN orders ON orders.id = order_items.order_id
     WHERE order_items.id = ?'
);
$stmt->execute([$orderItemId]);
$item = $stmt->fetch();

if (!$item || (int) $item['order_user_id'] !== $userId) {
    // Same message whether the item doesn't exist or belongs to
    // someone else — don't give an attacker a way to tell the
    // difference between "no such item" and "not yours".
    http_response_code(403);
    die('You do not have access to this download.');
}

if ($item['order_status'] !== 'paid') {
    http_response_code(403);
    die('This order has not been paid for yet.');
}

if (!$item['product_id']) {
    die('This product is no longer available (it was removed after you purchased it). Please contact support.');
}

$stmt = $pdo->prepare('SELECT file_path FROM products WHERE id = ?');
$stmt->execute([$item['product_id']]);
$product = $stmt->fetch();

if (!$product || !$product['file_path']) {
    die('No file has been attached to this product yet — please contact support.');
}

// basename() is defense in depth: file_path comes from our own database
// (never from the request), but this guarantees it can only ever point at a
// file directly inside product-files/, never somewhere else on disk.
$filePath = __DIR__ . '/product-files/' . basename($product['file_path']);
if (!is_file($filePath)) {
    die('The file could not be found on the server. Please contact support.');
}

// Audit log: who downloaded what, from which IP, and when.
$pdo->prepare('INSERT INTO download_logs (user_id, order_item_id, ip_address) VALUES (?, ?, ?)')
    ->execute([$userId, $orderItemId, $_SERVER['REMOTE_ADDR'] ?? 'unknown']);

$base = trim(preg_replace('/[^A-Za-z0-9 _-]/', '', $item['product_name']));
if ($base === '') {
    $base = 'download'; // e.g. a product name made entirely of non-ASCII characters
}
$downloadName = $base . '.' . pathinfo($filePath, PATHINFO_EXTENSION);

// Discard anything buffered so far so it can't get mixed into the file bytes.
while (ob_get_level() > 0) {
    ob_end_clean();
}

// octet-stream + attachment forces a download rather than the browser
// trying to render the file, and nosniff stops it second-guessing the type.
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . filesize($filePath));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($filePath);
exit;
