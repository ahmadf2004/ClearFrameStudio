<?php
session_start();
require 'db.php';
require 'csrf.php';

if (empty($_SESSION['is_admin'])) {
    header('Location: admin-login.php');
    exit;
}
$adminName = $_SESSION['admin_name'] ?? 'Admin';

// Manual status override — for cases like a refund, or fixing an
// order after a payment issue that the simulator/gateway didn't
// resolve cleanly. Normal orders move from pending -> paid on their
// own via payment-simulate.php; this is for the exceptions.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    csrf_verify();
    $id     = (int) $_POST['id'];
    $status = $_POST['status'];
    if (in_array($status, ['pending', 'paid', 'failed', 'cancelled'], true)) {
        $stmt = $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?');
        $stmt->execute([$status, $id]);
    }
    header('Location: manage-orders.php?id=' . $id);
    exit;
}

$viewId = isset($_GET['id']) ? (int) $_GET['id'] : null;
$order  = null;
$items  = [];
$linkedRequest = null;

if ($viewId) {
    $stmt = $pdo->prepare(
        'SELECT orders.*, users.full_name, users.email
         FROM orders
         JOIN users ON users.id = orders.user_id
         WHERE orders.id = ?'
    );
    $stmt->execute([$viewId]);
    $order = $stmt->fetch();

    if ($order) {
        $itemsStmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ?');
        $itemsStmt->execute([$viewId]);
        $items = $itemsStmt->fetchAll();

        if ($order['custom_request_id']) {
            $reqStmt = $pdo->prepare('SELECT id, product_type, status FROM custom_requests WHERE id = ?');
            $reqStmt->execute([$order['custom_request_id']]);
            $linkedRequest = $reqStmt->fetch();
        }
    }
}

$orders = $pdo->query(
    'SELECT orders.*, users.full_name, users.email
     FROM orders
     JOIN users ON users.id = orders.user_id
     ORDER BY orders.created_at DESC'
)->fetchAll();

$statusLabels = [
    'pending'   => ['Pending Payment', '#8A5A00', '#FCEFD1'],
    'paid'      => ['Paid', '#276B33', '#DFF2E1'],
    'failed'    => ['Failed', '#7A2020', '#FBEAEA'],
    'cancelled' => ['Cancelled', '#6B7480', '#E7E9EB'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Orders — Clear Frame Studio</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<style>
  :root{
    --paper:#FAFAF7; --ink:#1C2530; --muted:#6B7480;
    --frame-blue:#3E5C76; --frame-blue-deep:#26384A; --amber:#E8A13D; --alert:#9B3B3B;
    --line:#D8DBDE; --line-soft:#E7E9EB; --max:1160px;
  }
  *{box-sizing:border-box;}
  body{ margin:0; background:var(--paper); color:var(--ink); font-family:'Inter',sans-serif; -webkit-font-smoothing:antialiased; }
  a{ color:inherit; text-decoration:none; }
  table{ border-collapse:collapse; width:100%; }

  header{ border-bottom:1px solid var(--line); }
  .header-inner{ max-width:var(--max); margin:0 auto; padding:18px 32px; display:flex; align-items:center; justify-content:space-between; }
  .logo{ display:flex; align-items:center; }
  .logo-img{ height:40px; width:auto; display:block; }
  .header-right{ display:flex; align-items:center; gap:20px; }
  .admin-chip{ display:flex; align-items:center; gap:8px; font-size:13px; color:var(--muted); }
  .admin-chip .dot{ width:7px; height:7px; border-radius:50%; background:var(--alert); }
  .logout-link{ font-size:13px; font-weight:600; color:var(--frame-blue-deep); padding-bottom:2px; border-bottom:1px solid transparent; }
  .logout-link:hover{ border-bottom-color:var(--amber); color:var(--frame-blue); }

  .page-head{ max-width:var(--max); margin:0 auto; padding:48px 32px 8px; }
  .back-link{
    display:inline-flex; align-items:center; gap:6px; font-size:12.5px; font-weight:600;
    color:var(--muted); margin-bottom:20px; padding-bottom:2px; border-bottom:1px solid transparent;
  }
  .back-link svg{ width:14px; height:14px; stroke:var(--muted); }
  .back-link:hover{ color:var(--frame-blue-deep); border-bottom-color:var(--amber); }
  .back-link:hover svg{ stroke:var(--frame-blue-deep); }

  .page-head h1{ font-family:'Space Grotesk',sans-serif; font-weight:600; font-size:clamp(24px, 3vw, 32px); margin:0 0 8px; }
  .page-head p{ color:var(--muted); font-size:14px; margin:0; }

  .section{ max-width:var(--max); margin:0 auto; padding:36px 32px 100px; }
  .panel{ border:1px solid var(--line); }

  table th, table td{ text-align:left; padding:14px 20px; font-size:13.5px; border-bottom:1px solid var(--line-soft); vertical-align:middle; }
  table th{ font-size:11.5px; letter-spacing:0.06em; color:var(--muted); font-weight:600; background:var(--line-soft); }
  table tr:last-child td{ border-bottom:none; }

  .badge{ display:inline-flex; padding:4px 12px; border-radius:999px; font-size:11px; font-weight:600; letter-spacing:0.02em; white-space:nowrap; }
  .view-btn{
    display:inline-flex; align-items:center; justify-content:center;
    padding:7px 16px; font-size:12.5px; font-weight:600;
    border:1px solid var(--frame-blue-deep); color:var(--frame-blue-deep); border-radius:2px;
  }
  .view-btn:hover{ background:var(--frame-blue-deep); color:var(--paper); }
  .empty-state{ padding:56px 24px; text-align:center; color:var(--muted); font-size:14px; }

  /* ---------- Detail view ---------- */
  .detail-panel{ border:1px solid var(--line); padding:40px; max-width:680px; }
  .detail-panel .field{ margin-bottom:22px; }
  .detail-panel .label{ font-size:11.5px; font-weight:600; letter-spacing:0.06em; color:var(--muted); margin-bottom:6px; }
  .detail-panel .value{ font-size:14.5px; line-height:1.6; }
  .item-row{ display:flex; justify-content:space-between; padding:10px 0; border-bottom:1px solid var(--line-soft); font-size:14px; }
  .item-row:last-of-type{ border-bottom:none; }
  .total-row{ display:flex; justify-content:space-between; padding-top:14px; margin-top:6px; border-top:1px solid var(--line); font-size:17px; font-weight:600; font-family:'Space Grotesk',sans-serif; }

  select.status-select{
    padding:10px 12px; font-size:14px; border:1px solid var(--line); border-radius:2px;
    background:var(--paper); color:var(--ink); font-family:'Inter',sans-serif;
  }
  .btn{
    display:inline-flex; align-items:center; justify-content:center;
    padding:12px 22px; font-size:13.5px; font-weight:600; border-radius:2px;
    border:1px solid transparent; cursor:pointer;
    background:var(--frame-blue-deep); color:var(--paper); transition:background 0.2s ease;
  }
  .btn:hover{ background:var(--frame-blue); }

  @media (max-width:700px){
    table{ font-size:12.5px; }
    table th, table td{ padding:10px 12px; }
  }
</style>
</head>
<body>

<header>
  <div class="header-inner">
    <a href="admin-dashboard.php" class="logo" aria-label="Clear Frame Studio admin">
      <img src="clearframe-logo.png" alt="Clear Frame Studio" class="logo-img">
    </a>
    <div class="header-right">
      <span class="admin-chip"><span class="dot"></span> <?php echo htmlspecialchars($adminName); ?></span>
      <a href="admin-logout.php" class="logout-link">Log out</a>
    </div>
  </div>
</header>

<div class="page-head">
  <a href="admin-dashboard.php" class="back-link">
    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
    Back to Dashboard
  </a>
  <h1><?php echo $order ? 'Order Detail' : 'Manage Orders'; ?></h1>
  <p><?php echo $order ? 'Review this order and, if needed, override its status.' : 'Every order placed through the shop.'; ?></p>
</div>

<div class="section">

  <?php if ($viewId && !$order): ?>
    <div class="panel"><div class="empty-state">That order wasn't found.</div></div>

  <?php elseif ($order): ?>

    <?php [$label, $color, $bg] = $statusLabels[$order['status']] ?? [$order['status'], '#6B7480', '#E7E9EB']; ?>
    <div class="detail-panel">
      <div class="field">
        <div class="label">CUSTOMER</div>
        <div class="value"><?php echo htmlspecialchars($order['full_name']); ?> — <?php echo htmlspecialchars($order['email']); ?></div>
      </div>
      <div class="field">
        <div class="label">STATUS</div>
        <div class="value"><span class="badge" style="background:<?php echo $bg; ?>; color:<?php echo $color; ?>;"><?php echo htmlspecialchars($label); ?></span></div>
      </div>
      <?php if ($order['payment_ref']): ?>
        <div class="field">
          <div class="label">PAYMENT REFERENCE</div>
          <div class="value"><?php echo htmlspecialchars($order['payment_ref']); ?></div>
        </div>
      <?php endif; ?>
      <?php if ($linkedRequest): ?>
        <div class="field">
          <div class="label">LINKED CUSTOM REQUEST</div>
          <div class="value">
            <a href="manage-requests.php?id=<?php echo $linkedRequest['id']; ?>" style="color:var(--frame-blue-deep); font-weight:600;">
              Request #<?php echo $linkedRequest['id']; ?> — <?php echo htmlspecialchars($linkedRequest['product_type'] ?: 'Custom request'); ?>
            </a>
          </div>
        </div>
      <?php endif; ?>

      <div class="field">
        <div class="label">ITEMS</div>
        <?php foreach ($items as $item): ?>
          <div class="item-row">
            <span><?php echo htmlspecialchars($item['product_name']); ?></span>
            <span>RM <?php echo number_format((float) $item['price'], 2); ?></span>
          </div>
        <?php endforeach; ?>
        <div class="total-row"><span>Total</span><span>RM <?php echo number_format((float) $order['total_amount'], 2); ?></span></div>
      </div>

      <div class="field">
        <div class="label">PLACED</div>
        <div class="value"><?php echo date('M j, Y \a\t g:ia', strtotime($order['created_at'])); ?></div>
      </div>
      <?php if ($order['paid_at']): ?>
        <div class="field">
          <div class="label">PAID</div>
          <div class="value"><?php echo date('M j, Y \a\t g:ia', strtotime($order['paid_at'])); ?></div>
        </div>
      <?php endif; ?>

      <form method="post" style="display:flex; gap:12px; align-items:center; margin-top:30px;">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="id" value="<?php echo $order['id']; ?>">
        <select name="status" class="status-select">
          <option value="pending" <?php echo $order['status'] === 'pending' ? 'selected' : ''; ?>>Pending Payment</option>
          <option value="paid" <?php echo $order['status'] === 'paid' ? 'selected' : ''; ?>>Paid</option>
          <option value="failed" <?php echo $order['status'] === 'failed' ? 'selected' : ''; ?>>Failed</option>
          <option value="cancelled" <?php echo $order['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
        </select>
        <button type="submit" name="update_status" value="1" class="btn">Update status</button>
      </form>
    </div>

  <?php else: ?>

    <div class="panel">
      <?php if (empty($orders)): ?>
        <div class="empty-state">No orders yet.</div>
      <?php else: ?>
        <table>
          <thead>
            <tr><th>Order</th><th>Customer</th><th>Status</th><th>Total</th><th>Placed</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($orders as $o): ?>
              <?php [$oLabel, $oColor, $oBg] = $statusLabels[$o['status']] ?? [$o['status'], '#6B7480', '#E7E9EB']; ?>
              <tr>
                <td>#<?php echo $o['id']; ?></td>
                <td><?php echo htmlspecialchars($o['full_name']); ?></td>
                <td><span class="badge" style="background:<?php echo $oBg; ?>; color:<?php echo $oColor; ?>;"><?php echo htmlspecialchars($oLabel); ?></span></td>
                <td>RM <?php echo number_format((float) $o['total_amount'], 2); ?></td>
                <td><?php echo date('d M Y', strtotime($o['created_at'])); ?></td>
                <td><a href="manage-orders.php?id=<?php echo $o['id']; ?>" class="view-btn">View</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

  <?php endif; ?>

</div>

</body>
</html>
