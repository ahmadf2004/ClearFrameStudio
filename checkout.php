<?php
session_start();
require 'db.php';
require 'csrf.php';

if (empty($_SESSION['is_user'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];

if (empty($_SESSION['cart'])) {
    header('Location: cart.php');
    exit;
}

// If this checkout follows the "custom request -> checkout" flow, a
// pending request id was left in session by custom.php. Re-verify it
// actually belongs to this user before trusting it for anything — a
// session value is still user-influenced input.
$linkedRequest = null;
if (!empty($_SESSION['pending_custom_request_id'])) {
    $stmt = $pdo->prepare('SELECT id, product_type FROM custom_requests WHERE id = ? AND user_id = ?');
    $stmt->execute([$_SESSION['pending_custom_request_id'], $userId]);
    $linkedRequest = $stmt->fetch();
    if (!$linkedRequest) {
        unset($_SESSION['pending_custom_request_id']); // stale or not theirs — drop it
    }
}

$placeholders = implode(',', array_fill(0, count($_SESSION['cart']), '?'));
$stmt = $pdo->prepare("SELECT * FROM products WHERE id IN ($placeholders)");
$stmt->execute($_SESSION['cart']);
$items = $stmt->fetchAll();

$total = 0.0;
foreach ($items as $item) {
    $total += (float) $item['price'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    csrf_verify();

    // Re-fetch fresh from the DB right before creating the order — the
    // cart page's totals could be a few minutes stale (e.g. an admin
    // changed a price in another tab), so this recalculates from
    // scratch rather than trusting a hidden form field for the total.
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id IN ($placeholders)");
    $stmt->execute($_SESSION['cart']);
    $freshItems = $stmt->fetchAll();

    if (empty($freshItems)) {
        header('Location: cart.php');
        exit;
    }

    $freshTotal = 0.0;
    foreach ($freshItems as $item) {
        $freshTotal += (float) $item['price'];
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO orders (user_id, total_amount, status, custom_request_id) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId,
            $freshTotal,
            'pending',
            $linkedRequest ? $linkedRequest['id'] : null,
        ]);
        $orderId = $pdo->lastInsertId();

        $itemStmt = $pdo->prepare(
            'INSERT INTO order_items (order_id, product_id, product_name, price) VALUES (?, ?, ?, ?)'
        );
        foreach ($freshItems as $item) {
            $itemStmt->execute([$orderId, $item['id'], $item['name'], $item['price']]);
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        die('Something went wrong creating your order. Please try again.');
    }

    $_SESSION['cart'] = []; // clear the cart now that it's a real order
    unset($_SESSION['pending_custom_request_id']); // consumed — this order now carries the link

    header('Location: bank-transfer.php?order_id=' . $orderId);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Checkout — Clear Frame Studio</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<style>
  :root{
    --paper:#FAFAF7; --ink:#1C2530; --muted:#6B7480;
    --frame-blue:#3E5C76; --frame-blue-deep:#26384A; --amber:#E8A13D;
    --line:#D8DBDE; --line-soft:#E7E9EB; --max:1160px;
  }
  *{box-sizing:border-box;}
  body{ margin:0; background:var(--paper); color:var(--ink); font-family:'Inter',sans-serif; -webkit-font-smoothing:antialiased; }
  a{ color:inherit; text-decoration:none; }
  header{ border-bottom:1px solid var(--line); }
  .header-inner{ max-width:var(--max); margin:0 auto; padding:18px 32px; display:flex; align-items:center; justify-content:space-between; }
  .logo-img{ height:40px; width:auto; display:block; }
  .back-home{ display:flex; align-items:center; gap:6px; font-size:13px; font-weight:600; color:var(--frame-blue-deep); }
  .back-home svg{ width:15px; height:15px; stroke:var(--frame-blue-deep); }
  .page-head{ max-width:720px; margin:0 auto; padding:56px 32px 8px; }
  .page-head h1{ font-family:'Space Grotesk',sans-serif; font-weight:600; font-size:clamp(26px,4vw,32px); margin:0 0 8px; }
  .page-head p{ color:var(--muted); font-size:14px; margin:0; }
  .section{ max-width:720px; margin:0 auto; padding:36px 32px 100px; }
  .panel{ border:1px solid var(--line); padding:28px; }
  .item-row{ display:flex; justify-content:space-between; padding:12px 0; border-bottom:1px solid var(--line-soft); font-size:14px; }
  .item-row:last-of-type{ border-bottom:none; }
  .total-row{ display:flex; justify-content:space-between; padding-top:18px; margin-top:8px; border-top:1px solid var(--line); font-size:18px; font-weight:600; font-family:'Space Grotesk',sans-serif; }
  .btn{ display:flex; align-items:center; justify-content:center; width:100%; padding:14px 20px; font-size:14px; font-weight:600; border-radius:2px; border:1px solid transparent; cursor:pointer; background:var(--frame-blue-deep); color:var(--paper); margin-top:26px; }
  .btn:hover{ background:var(--frame-blue); }
  .notice{ background:#FCEFD1; border:1px solid #E8D9A6; color:#8A5A00; padding:12px 16px; font-size:13px; margin-bottom:24px; }
</style>
</head>
<body>

<header>
  <div class="header-inner">
    <a href="discover.php" class="logo" aria-label="Clear Frame Studio"><img src="clearframe-logo.png" alt="Clear Frame Studio" class="logo-img"></a>
    <a href="cart.php" class="back-home">
      <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
      <span>Back to Cart</span>
    </a>
  </div>
</header>

<div class="page-head">
  <h1>Checkout</h1>
  <p>Review your order before payment.</p>
</div>

<div class="section">
  <div class="notice">Payment is by bank transfer — you'll see our account details on the next step, and your order is marked paid once an admin verifies your uploaded receipt.</div>

  <?php if ($linkedRequest): ?>
    <div class="notice" style="background:#D9EEF2; border-color:#B6DCE3; color:#1B6E7D;">
      This order is linked to your custom request — <strong><?php echo htmlspecialchars($linkedRequest['product_type'] ?: 'Custom request'); ?></strong>. You'll see it together in My Orders and My Requests once it's placed.
    </div>
  <?php endif; ?>

  <div class="panel">
    <?php foreach ($items as $item): ?>
      <div class="item-row">
        <span><?php echo htmlspecialchars($item['name']); ?></span>
        <span>RM <?php echo number_format((float) $item['price'], 2); ?></span>
      </div>
    <?php endforeach; ?>
    <div class="total-row"><span>Total</span><span>RM <?php echo number_format($total, 2); ?></span></div>

    <form method="post">
      <?php echo csrf_field(); ?>
      <button type="submit" name="place_order" value="1" class="btn">Confirm &amp; Continue to Payment</button>
    </form>
  </div>
</div>

</body>
</html>
