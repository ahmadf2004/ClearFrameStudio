<?php
session_start();
require 'db.php';
require 'csrf.php';

if (empty($_SESSION['is_admin'])) {
    header('Location: admin-login.php');
    exit;
}
$adminName = $_SESSION['admin_name'] ?? 'Admin';

// Approve or reject from the detail view
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    csrf_verify();
    $id = (int) $_POST['id'];

    if (isset($_POST['approve'])) {
        $paymentRef = 'BANK-' . strtoupper(bin2hex(random_bytes(4)));
        $pdo->prepare(
            "UPDATE orders SET status = 'paid', payment_ref = ?, paid_at = NOW() WHERE id = ? AND status = 'pending_verification'"
        )->execute([$paymentRef, $id]);
    } elseif (isset($_POST['reject'])) {
        $reason = trim($_POST['rejection_reason'] ?? '');
        if ($reason === '') {
            $reason = 'The uploaded proof could not be verified against our records.';
        }
        $pdo->prepare(
            "UPDATE orders SET status = 'rejected', rejection_reason = ? WHERE id = ? AND status = 'pending_verification'"
        )->execute([$reason, $id]);
    }

    header('Location: verify-payments.php?id=' . $id);
    exit;
}

$viewId = isset($_GET['id']) ? (int) $_GET['id'] : null;
$order = null;

if ($viewId) {
    $stmt = $pdo->prepare(
        'SELECT orders.*, users.full_name, users.email AS user_email
         FROM orders
         JOIN users ON users.id = orders.user_id
         WHERE orders.id = ?'
    );
    $stmt->execute([$viewId]);
    $order = $stmt->fetch();

    if ($order) {
        $itemsStmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ?');
        $itemsStmt->execute([$viewId]);
        $orderItems = $itemsStmt->fetchAll();
    }
}

// Queue: everything currently awaiting review, oldest first so the
// longest-waiting customer gets looked at first.
$queue = $pdo->query(
    "SELECT orders.*, users.full_name, users.email AS user_email
     FROM orders
     JOIN users ON users.id = orders.user_id
     WHERE orders.status = 'pending_verification'
     ORDER BY orders.proof_submitted_at ASC"
)->fetchAll();

function paymentStatusBadge($status) {
    $map = [
        'pending'               => ['Awaiting Transfer',    '#6B7480', '#E7E9EB'],
        'pending_verification'  => ['Pending Review',       '#8A5A00', '#FCEFD1'],
        'paid'                  => ['Paid',                 '#276B33', '#DFF2E1'],
        'rejected'              => ['Rejected',              '#7A2020', '#FBEAEA'],
        'cancelled'             => ['Cancelled',             '#6B7480', '#E7E9EB'],
    ];
    [$label, $color, $bg] = $map[$status] ?? [$status, '#6B7480', '#E7E9EB'];
    return "<span class=\"badge\" style=\"background:$bg; color:$color;\">$label</span>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verify Payments — Clear Frame Studio</title>
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
  .empty-state{ padding:44px 24px; text-align:center; color:var(--muted); font-size:14px; }

  /* ---------- Detail view ---------- */
  .detail-grid{ display:grid; grid-template-columns:1fr 1fr; gap:28px; align-items:start; }
  @media (max-width:860px){ .detail-grid{ grid-template-columns:1fr; } }
  .detail-panel{ border:1px solid var(--line); padding:36px; }
  .detail-panel .field{ margin-bottom:20px; }
  .detail-panel .label{ font-size:11.5px; font-weight:600; letter-spacing:0.06em; color:var(--muted); margin-bottom:6px; }
  .detail-panel .value{ font-size:14.5px; line-height:1.6; }
  .item-row{ display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid var(--line-soft); font-size:13.5px; }
  .item-row:last-of-type{ border-bottom:none; }
  .total-row{ display:flex; justify-content:space-between; padding-top:14px; margin-top:6px; border-top:1px solid var(--line); font-size:17px; font-weight:600; font-family:'Space Grotesk',sans-serif; }

  .proof-preview{ border:1px solid var(--line); background:var(--line-soft); display:flex; align-items:center; justify-content:center; min-height:260px; overflow:hidden; }
  .proof-preview img{ max-width:100%; display:block; }
  .proof-preview .pdf-link{ padding:40px; text-align:center; font-size:14px; font-weight:600; color:var(--frame-blue-deep); }

  textarea{
    width:100%; padding:12px 14px; background:var(--line-soft); border:1px solid transparent; border-radius:2px;
    font-family:'Inter',sans-serif; font-size:14px; color:var(--ink); outline:none; resize:vertical; min-height:70px;
  }
  textarea:focus{ border-color:var(--frame-blue); background:var(--paper); }

  .btn{
    display:inline-flex; align-items:center; justify-content:center;
    padding:12px 22px; font-size:13.5px; font-weight:600; border-radius:2px;
    border:1px solid transparent; cursor:pointer;
  }
  .btn-approve{ background:#276B33; color:var(--paper); }
  .btn-approve:hover{ background:#1f5529; }
  .btn-reject{ background:none; border-color:var(--alert); color:var(--alert); }
  .btn-reject:hover{ background:var(--alert); color:var(--paper); }

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
  <h1><?php echo $order ? 'Review Payment' : 'Verify Payments'; ?></h1>
  <p><?php echo $order ? 'Check the proof against the order, then approve or reject it.' : 'Orders waiting on proof-of-payment review, oldest first.'; ?></p>
</div>

<div class="section">

  <?php if ($order): ?>

    <?php if (!in_array($order['status'], ['pending_verification'], true)): ?>
      <div class="panel" style="padding:24px; margin-bottom:24px;">
        This order is no longer pending review — current status: <?php echo paymentStatusBadge($order['status']); ?>
      </div>
    <?php endif; ?>

    <div class="detail-grid">
      <div class="detail-panel">
        <div class="field">
          <div class="label">CUSTOMER</div>
          <div class="value"><?php echo htmlspecialchars($order['full_name']); ?> — <?php echo htmlspecialchars($order['user_email']); ?></div>
        </div>
        <div class="field">
          <div class="label">ORDER</div>
          <div class="value">#<?php echo $order['id']; ?> &middot; <?php echo paymentStatusBadge($order['status']); ?></div>
        </div>
        <?php if ($order['payment_note']): ?>
          <div class="field">
            <div class="label">CUSTOMER'S NOTE</div>
            <div class="value"><?php echo htmlspecialchars($order['payment_note']); ?></div>
          </div>
        <?php endif; ?>
        <div class="field">
          <div class="label">SUBMITTED</div>
          <div class="value"><?php echo $order['proof_submitted_at'] ? date('M j, Y \a\t g:ia', strtotime($order['proof_submitted_at'])) : '—'; ?></div>
        </div>

        <div class="field" style="margin-top:28px;">
          <div class="label">ITEMS</div>
          <?php foreach ($orderItems as $item): ?>
            <div class="item-row">
              <span><?php echo htmlspecialchars($item['product_name']); ?></span>
              <span>RM <?php echo number_format((float) $item['price'], 2); ?></span>
            </div>
          <?php endforeach; ?>
          <div class="total-row"><span>Total</span><span>RM <?php echo number_format((float) $order['total_amount'], 2); ?></span></div>
        </div>

        <?php if ($order['status'] === 'pending_verification'): ?>
          <div style="display:flex; gap:12px; margin-top:30px; flex-wrap:wrap;">
            <form method="post" style="margin:0;">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="id" value="<?php echo $order['id']; ?>">
              <button type="submit" name="approve" value="1" class="btn btn-approve">Approve &amp; Mark Paid</button>
            </form>
          </div>

          <form method="post" style="margin-top:14px;">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="id" value="<?php echo $order['id']; ?>">
            <label style="display:block; font-size:12.5px; font-weight:600; margin-bottom:6px;">Rejection reason (shown to customer)</label>
            <textarea name="rejection_reason" placeholder="e.g. Amount doesn't match the order total"></textarea>
            <button type="submit" name="reject" value="1" class="btn btn-reject" style="margin-top:10px;">Reject</button>
          </form>
        <?php elseif ($order['rejection_reason']): ?>
          <div class="field" style="margin-top:20px;">
            <div class="label">REJECTION REASON ON FILE</div>
            <div class="value"><?php echo htmlspecialchars($order['rejection_reason']); ?></div>
          </div>
        <?php endif; ?>
      </div>

      <div>
        <div class="field" style="margin-bottom:10px;"><div class="label">PROOF OF PAYMENT</div></div>
        <?php
          $ext = $order['payment_proof'] ? strtolower(pathinfo($order['payment_proof'], PATHINFO_EXTENSION)) : null;
        ?>
        <div class="proof-preview">
          <?php if (!$order['payment_proof']): ?>
            <span style="color:var(--muted); font-size:13px;">No file on record.</span>
          <?php elseif ($ext === 'pdf'): ?>
            <a href="view-payment-proof.php?order_id=<?php echo $order['id']; ?>" target="_blank" class="pdf-link">Open PDF receipt &rarr;</a>
          <?php else: ?>
            <a href="view-payment-proof.php?order_id=<?php echo $order['id']; ?>" target="_blank">
              <img src="view-payment-proof.php?order_id=<?php echo $order['id']; ?>" alt="Payment proof">
            </a>
          <?php endif; ?>
        </div>
      </div>
    </div>

  <?php else: ?>

    <div class="panel">
      <?php if (empty($queue)): ?>
        <div class="empty-state">Nothing waiting on review right now.</div>
      <?php else: ?>
        <table>
          <thead>
            <tr><th>Order</th><th>Customer</th><th>Total</th><th>Submitted</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($queue as $o): ?>
              <tr>
                <td>#<?php echo $o['id']; ?></td>
                <td><?php echo htmlspecialchars($o['full_name']); ?></td>
                <td>RM <?php echo number_format((float) $o['total_amount'], 2); ?></td>
                <td><?php echo $o['proof_submitted_at'] ? date('d M Y, g:ia', strtotime($o['proof_submitted_at'])) : '—'; ?></td>
                <td><a href="verify-payments.php?id=<?php echo $o['id']; ?>" class="view-btn">Review</a></td>
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
