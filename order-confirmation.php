<?php
session_start();
require 'db.php';

if (empty($_SESSION['is_user'])) {
    header('Location: login.php');
    exit;
}

$userId  = $_SESSION['user_id'];
$orderId = (int) ($_GET['order_id'] ?? 0);

// Ownership check — same pattern as everywhere else in this project:
// filter by user_id in the query itself, not just "is someone logged in".
$stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ? AND user_id = ?');
$stmt->execute([$orderId, $userId]);
$order = $stmt->fetch();

if (!$order) {
    die('Order not found.');
}

$itemsStmt = $pdo->prepare(
    'SELECT order_items.*, products.file_path
     FROM order_items
     LEFT JOIN products ON products.id = order_items.product_id
     WHERE order_items.order_id = ?'
);
$itemsStmt->execute([$orderId]);
$items = $itemsStmt->fetchAll();

$linkedRequest = null;
if ($order['custom_request_id']) {
    $stmt = $pdo->prepare('SELECT * FROM custom_requests WHERE id = ? AND user_id = ?');
    $stmt->execute([$order['custom_request_id'], $userId]);
    $linkedRequest = $stmt->fetch();
}

function requestStatusBadge($status) {
    $map = [
        'new'         => ['Pending',    '#8A5A00', '#FCEFD1'],
        'in_progress' => ['In Review',  '#1B6E7D', '#D9EEF2'],
        'completed'   => ['Completed',  '#276B33', '#DFF2E1'],
    ];
    [$label, $color, $bg] = $map[$status] ?? [$status, '#6B7480', '#E7E9EB'];
    return "<span class=\"status-badge\" style=\"background:$bg; color:$color;\">$label</span>";
}

$statusLabels = [
    'pending'               => ['Awaiting Transfer', '#8A5A00', '#FCEFD1'],
    'pending_verification'  => ['Pending Review', '#8A5A00', '#FCEFD1'],
    'paid'                  => ['Paid', '#276B33', '#DFF2E1'],
    'rejected'              => ['Payment Rejected', '#7A2020', '#FBEAEA'],
    'failed'                => ['Failed', '#7A2020', '#FBEAEA'],
    'cancelled'             => ['Cancelled', '#6B7480', '#E7E9EB'],
];
[$statusLabel, $statusColor, $statusBg] = $statusLabels[$order['status']] ?? [$order['status'], '#6B7480', '#E7E9EB'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Order #<?php echo $order['id']; ?> — Clear Frame Studio</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<style>
  :root{
    --paper:#FAFAF7; --ink:#1C2530; --muted:#6B7480;
    --frame-blue:#3E5C76; --frame-blue-deep:#26384A; --amber:#E8A13D; --line:#D8DBDE; --line-soft:#E7E9EB;
  }
  *{box-sizing:border-box;}
  body{ margin:0; background:var(--paper); color:var(--ink); font-family:'Inter',sans-serif; -webkit-font-smoothing:antialiased; }
  a{ color:inherit; text-decoration:none; }
  header{ border-bottom:1px solid var(--line); }
  .header-inner{ max-width:760px; margin:0 auto; padding:18px 32px; display:flex; align-items:center; justify-content:space-between; }
  .logo-img{ height:40px; width:auto; }
  .back-home{ display:flex; align-items:center; gap:6px; font-size:13px; font-weight:600; color:var(--frame-blue-deep); }
  .back-home svg{ width:15px; height:15px; stroke:var(--frame-blue-deep); }
  .back-home:hover{ color:var(--frame-blue); }
  .section{ max-width:760px; margin:0 auto; padding:56px 32px 100px; }
  .status-badge{ display:inline-flex; padding:5px 14px; border-radius:999px; font-size:12px; font-weight:700; letter-spacing:0.03em; margin-bottom:20px; }
  h1{ font-family:'Space Grotesk',sans-serif; font-size:26px; margin:0 0 8px; }
  p.muted{ color:var(--muted); font-size:14px; margin:0 0 32px; }
  .panel{ border:1px solid var(--line); padding:28px; margin-bottom:24px; }
  .item-row{ display:flex; justify-content:space-between; padding:12px 0; border-bottom:1px solid var(--line-soft); font-size:14px; }
  .item-row:last-of-type{ border-bottom:none; }
  .total-row{ display:flex; justify-content:space-between; padding-top:18px; margin-top:8px; border-top:1px solid var(--line); font-size:18px; font-weight:600; font-family:'Space Grotesk',sans-serif; }
  .dl-link{ font-size:12.5px; font-weight:600; color:var(--frame-blue-deep); border-bottom:1px solid var(--amber); padding-bottom:1px; }
  .dl-link:hover{ color:var(--frame-blue); }
  .meta-row{ display:flex; justify-content:space-between; font-size:13px; color:var(--muted); margin-bottom:8px; }
  .btn{ display:inline-flex; align-items:center; justify-content:center; padding:12px 24px; font-size:14px; font-weight:600; border-radius:2px; border:1px solid transparent; cursor:pointer; background:var(--frame-blue-deep); color:var(--paper); }
  .btn:hover{ background:var(--frame-blue); }
</style>
</head>
<body>

<header>
  <div class="header-inner">
    <a href="discover.php" class="logo" aria-label="Clear Frame Studio"><img src="clearframe-logo.png" alt="Clear Frame Studio" class="logo-img"></a>
    <a href="index.php" class="back-home">
      <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M15 18l-6-6 6-6"/>
      </svg>
      <span>Back to Home</span>
    </a>
  </div>
</header>

<div class="section">
  <span class="status-badge" style="background:<?php echo $statusBg; ?>; color:<?php echo $statusColor; ?>;">
    <?php echo htmlspecialchars($statusLabel); ?>
  </span>
  <h1><?php echo $order['status'] === 'paid' ? 'Thanks for your order!' : 'Order #' . $order['id']; ?></h1>
  <p class="muted">Order #<?php echo $order['id']; ?> — placed <?php echo date('M j, Y \a\t g:ia', strtotime($order['created_at'])); ?></p>

  <?php if ($order['status'] === 'pending'): ?>
    <div class="panel" style="background:#FCEFD1; border-color:#E8D9A6;">
      <p style="margin:0 0 14px; font-size:13.5px; color:#8A5A00;">This order hasn't been paid yet.</p>
      <a href="bank-transfer.php?order_id=<?php echo $order['id']; ?>" class="btn" style="margin-top:0;">Go to Bank Transfer</a>
    </div>
  <?php elseif ($order['status'] === 'pending_verification'): ?>
    <div class="panel" style="background:#D9EEF2; border-color:#B6DCE3;">
      <p style="margin:0 0 10px; font-size:13.5px; color:#1B6E7D;">We've received your proof of payment and it's waiting on admin review — this usually doesn't take long.</p>
      <a href="view-payment-proof.php?order_id=<?php echo $order['id']; ?>" target="_blank" class="dl-link">View the receipt you submitted &rarr;</a>
    </div>
  <?php elseif ($order['status'] === 'rejected'): ?>
    <div class="panel" style="background:#FBEAEA; border-color:#E2B4B4;">
      <p style="margin:0 0 6px; font-size:13.5px; color:#7A2020; font-weight:600;">Your proof of payment was rejected.</p>
      <p style="margin:0 0 14px; font-size:13.5px; color:#7A2020;"><?php echo htmlspecialchars($order['rejection_reason'] ?: 'Please check the details and submit a new receipt.'); ?></p>
      <a href="bank-transfer.php?order_id=<?php echo $order['id']; ?>" class="btn" style="margin-top:0;">Resubmit Proof of Payment</a>
    </div>
  <?php endif; ?>

  <?php if ($order['payment_ref'] || $order['paid_at']): ?>
    <div class="panel">
      <?php if ($order['payment_ref']): ?>
        <div class="meta-row"><span>Payment reference</span><span><?php echo htmlspecialchars($order['payment_ref']); ?></span></div>
      <?php endif; ?>
      <?php if ($order['paid_at']): ?>
        <div class="meta-row"><span>Paid on</span><span><?php echo date('M j, Y \a\t g:ia', strtotime($order['paid_at'])); ?></span></div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if ($linkedRequest): ?>
    <div class="panel">
      <div class="meta-row" style="align-items:center;">
        <span>Linked custom request</span>
        <?php echo requestStatusBadge($linkedRequest['status']); ?>
      </div>
      <div style="font-size:14.5px; font-weight:600; margin:6px 0 4px;">
        <?php echo htmlspecialchars($linkedRequest['product_type'] ?: 'Custom request'); ?>
      </div>
      <?php if ($linkedRequest['quote_amount'] !== null): ?>
        <div style="font-size:13px; color:var(--muted); margin-bottom:10px;">
          Quoted at RM <?php echo number_format((float) $linkedRequest['quote_amount'], 2); ?>
        </div>
      <?php endif; ?>
      <?php if ($linkedRequest['deliverable_file'] && $order['status'] === 'paid'): ?>
        <a href="download-request.php?request_id=<?php echo $linkedRequest['id']; ?>" class="dl-link" style="margin-right:18px;">Download your file &rarr;</a>
      <?php endif; ?>
      <a href="my-requests.php?id=<?php echo $linkedRequest['id']; ?>" class="dl-link">View request details &rarr;</a>
    </div>
  <?php endif; ?>

  <div class="panel">
    <?php foreach ($items as $item): ?>
      <div class="item-row">
        <span><?php echo htmlspecialchars($item['product_name']); ?></span>
        <span style="display:flex; align-items:center; gap:18px;">
          <?php if ($order['status'] === 'paid'): ?>
            <?php if ($item['file_path']): ?>
              <a href="download.php?item=<?php echo (int) $item['id']; ?>" class="dl-link">Download</a>
            <?php else: ?>
              <span style="color:var(--muted); font-size:12px;">File not available yet</span>
            <?php endif; ?>
          <?php endif; ?>
          <span>RM <?php echo number_format((float) $item['price'], 2); ?></span>
        </span>
      </div>
    <?php endforeach; ?>
    <div class="total-row"><span>Total</span><span>RM <?php echo number_format((float) $order['total_amount'], 2); ?></span></div>
  </div>

  <a href="my-orders.php" class="btn">View all my orders</a>
</div>

</body>
</html>
