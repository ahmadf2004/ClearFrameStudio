<?php
session_start();
require 'db.php';

if (empty($_SESSION['is_user'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];

$stmt = $pdo->prepare(
    'SELECT orders.*, custom_requests.id AS request_id, custom_requests.product_type AS request_product_type
     FROM orders
     LEFT JOIN custom_requests ON custom_requests.id = orders.custom_request_id
     WHERE orders.user_id = ?
     ORDER BY orders.created_at DESC'
);
$stmt->execute([$userId]);
$orders = $stmt->fetchAll();

$statusLabels = [
    'pending'               => ['Awaiting Transfer', '#8A5A00', '#FCEFD1'],
    'pending_verification'  => ['Pending Review', '#8A5A00', '#FCEFD1'],
    'paid'                  => ['Paid', '#276B33', '#DFF2E1'],
    'rejected'              => ['Payment Rejected', '#7A2020', '#FBEAEA'],
    'failed'                => ['Failed', '#7A2020', '#FBEAEA'],
    'cancelled'             => ['Cancelled', '#6B7480', '#E7E9EB'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Orders — Clear Frame Studio</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<style>
  :root{
    --paper:#FAFAF7; --ink:#1C2530; --muted:#6B7480;
    --frame-blue:#3E5C76; --frame-blue-deep:#26384A; --amber:#E8A13D; --line:#D8DBDE; --line-soft:#E7E9EB; --max:1160px;
  }
  *{box-sizing:border-box;}
  body{ margin:0; background:var(--paper); color:var(--ink); font-family:'Inter',sans-serif; -webkit-font-smoothing:antialiased; }
  a{ color:inherit; text-decoration:none; }
  table{ border-collapse:collapse; width:100%; }
  header{ border-bottom:1px solid var(--line); }
  .header-inner{ max-width:var(--max); margin:0 auto; padding:18px 32px; display:flex; align-items:center; justify-content:space-between; }
  .logo-img{ height:40px; width:auto; }
  .back-home{ display:flex; align-items:center; gap:6px; font-size:13px; font-weight:600; color:var(--frame-blue-deep); }
  .back-home svg{ width:15px; height:15px; stroke:var(--frame-blue-deep); }
  .back-home:hover{ color:var(--frame-blue); }
  .page-head{ max-width:var(--max); margin:0 auto; padding:56px 32px 8px; }
  .page-head h1{ font-family:'Space Grotesk',sans-serif; font-weight:600; font-size:clamp(24px,3vw,32px); margin:0 0 8px; }
  .page-head p{ color:var(--muted); font-size:14px; margin:0; }
  .section{ max-width:var(--max); margin:0 auto; padding:36px 32px 100px; }
  .panel{ border:1px solid var(--line); }
  table th, table td{ text-align:left; padding:14px 20px; font-size:13.5px; border-bottom:1px solid var(--line-soft); vertical-align:middle; }
  table th{ font-size:11.5px; letter-spacing:0.06em; color:var(--muted); font-weight:600; background:var(--line-soft); }
  table tr:last-child td{ border-bottom:none; }
  .badge{ display:inline-flex; padding:4px 12px; border-radius:999px; font-size:11px; font-weight:600; letter-spacing:0.02em; white-space:nowrap; }
  .view-btn{ display:inline-flex; align-items:center; justify-content:center; padding:7px 16px; font-size:12.5px; font-weight:600; border:1px solid var(--frame-blue-deep); color:var(--frame-blue-deep); border-radius:2px; }
  .view-btn:hover{ background:var(--frame-blue-deep); color:var(--paper); }
  .empty-state{ padding:56px 24px; text-align:center; color:var(--muted); font-size:14px; }
  .empty-state a{ font-weight:600; color:var(--frame-blue-deep); }
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

<div class="page-head">
  <h1>My Orders</h1>
  <p>Everything you've purchased from Clear Frame Studio.</p>
</div>

<div class="section">
  <div class="panel">
    <?php if (empty($orders)): ?>
      <div class="empty-state">You haven't placed any orders yet.<br><a href="discover.php">Browse the shop &rarr;</a></div>
    <?php else: ?>
      <table>
        <thead><tr><th>Order</th><th>Linked Request</th><th>Status</th><th>Total</th><th>Placed</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($orders as $order): ?>
            <?php [$label, $color, $bg] = $statusLabels[$order['status']] ?? [$order['status'], '#6B7480', '#E7E9EB']; ?>
            <tr>
              <td>#<?php echo $order['id']; ?></td>
              <td>
                <?php if ($order['request_id']): ?>
                  <a href="my-requests.php?id=<?php echo $order['request_id']; ?>" style="color:var(--frame-blue-deep); font-weight:600;">
                    <?php echo htmlspecialchars($order['request_product_type'] ?: 'Custom request'); ?>
                  </a>
                <?php else: ?>
                  <span style="color:var(--muted);">—</span>
                <?php endif; ?>
              </td>
              <td><span class="badge" style="background:<?php echo $bg; ?>; color:<?php echo $color; ?>;"><?php echo htmlspecialchars($label); ?></span></td>
              <td>RM <?php echo number_format((float) $order['total_amount'], 2); ?></td>
              <td><?php echo date('d M Y', strtotime($order['created_at'])); ?></td>
              <td><a href="order-confirmation.php?order_id=<?php echo $order['id']; ?>" class="view-btn">View</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

</body>
</html>
