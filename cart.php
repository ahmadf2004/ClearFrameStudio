<?php
session_start();
require 'db.php';
require 'csrf.php';

$isLoggedIn = !empty($_SESSION['is_user']);

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = []; // array of product IDs, each appearing once
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = $_POST['action'] ?? '';
    $id     = (int) ($_POST['id'] ?? 0);

    if ($action === 'add' && $id > 0 && !in_array($id, $_SESSION['cart'], true)) {
        $_SESSION['cart'][] = $id;
    } elseif ($action === 'remove') {
        $_SESSION['cart'] = array_values(array_diff($_SESSION['cart'], [$id]));
    }

    header('Location: cart.php');
    exit;
}

// Look up current product details for whatever's in the cart. Prices
// shown here are always today's price, even if the product's price
// changed since it was added — the price is only "locked in" once an
// order is actually placed (see checkout.php).
$items = [];
$total = 0.0;
if (!empty($_SESSION['cart'])) {
    $placeholders = implode(',', array_fill(0, count($_SESSION['cart']), '?'));
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id IN ($placeholders)");
    $stmt->execute($_SESSION['cart']);
    $items = $stmt->fetchAll();
    foreach ($items as $item) {
        $total += (float) $item['price'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Your Cart — Clear Frame Studio</title>
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

  .page-head{ max-width:var(--max); margin:0 auto; padding:56px 32px 8px; }
  .page-head h1{ font-family:'Space Grotesk',sans-serif; font-weight:600; font-size:clamp(26px,4vw,36px); margin:0 0 8px; }
  .page-head p{ color:var(--muted); font-size:14px; margin:0; }

  .section{ max-width:var(--max); margin:0 auto; padding:36px 32px 100px; display:grid; grid-template-columns:1fr 320px; gap:32px; }
  @media (max-width:800px){ .section{ grid-template-columns:1fr; } }

  .panel{ border:1px solid var(--line); }
  .cart-row{ display:flex; align-items:center; justify-content:space-between; padding:20px 24px; border-bottom:1px solid var(--line-soft); gap:16px; }
  .cart-row:last-child{ border-bottom:none; }
  .cart-row .name{ font-weight:600; font-size:14.5px; }
  .cart-row .cat{ font-size:12px; color:var(--muted); text-transform:capitalize; }
  .cart-row .price{ font-family:'Space Grotesk',sans-serif; font-weight:600; font-size:15px; white-space:nowrap; }
  .remove-btn{ background:none; border:1px solid var(--line); color:var(--muted); font-size:12px; font-weight:600; padding:6px 12px; border-radius:2px; cursor:pointer; font-family:'Inter',sans-serif; }
  .remove-btn:hover{ border-color:#9B3B3B; color:#9B3B3B; }

  .empty-state{ padding:64px 24px; text-align:center; color:var(--muted); font-size:14px; }
  .empty-state a{ font-weight:600; color:var(--frame-blue-deep); }

  .summary{ border:1px solid var(--line); padding:28px; align-self:start; }
  .summary h2{ font-family:'Space Grotesk',sans-serif; font-size:17px; margin:0 0 20px; }
  .summary-row{ display:flex; justify-content:space-between; font-size:14px; margin-bottom:10px; }
  .summary-total{ display:flex; justify-content:space-between; font-size:18px; font-weight:600; font-family:'Space Grotesk',sans-serif; border-top:1px solid var(--line); padding-top:14px; margin-top:8px; }
  .btn{ display:flex; align-items:center; justify-content:center; width:100%; padding:13px 20px; font-size:14px; font-weight:600; border-radius:2px; border:1px solid transparent; cursor:pointer; background:var(--frame-blue-deep); color:var(--paper); margin-top:20px; }
  .btn:hover{ background:var(--frame-blue); }
  .notice{ background:#EAF3EC; border:1px solid #BEDCC4; color:#2C5E38; padding:12px 16px; font-size:13px; margin-top:16px; }
</style>
</head>
<body>

<header>
  <div class="header-inner">
    <a href="discover.php" class="logo" aria-label="Clear Frame Studio">
      <img src="clearframe-logo.png" alt="Clear Frame Studio" class="logo-img">
    </a>
    <a href="discover.php" class="back-home">
      <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
      <span>Continue browsing</span>
    </a>
  </div>
</header>

<div class="page-head">
  <h1>Your Cart</h1>
  <p><?php echo count($items); ?> item<?php echo count($items) === 1 ? '' : 's'; ?> in your cart.</p>
</div>

<div class="section">

  <div class="panel">
    <?php if (empty($items)): ?>
      <div class="empty-state">
        Your cart is empty.<br>
        <a href="discover.php">Browse the shop &rarr;</a>
      </div>
    <?php else: ?>
      <?php foreach ($items as $item): ?>
        <div class="cart-row">
          <div>
            <div class="name"><?php echo htmlspecialchars($item['name']); ?></div>
            <div class="cat"><?php echo htmlspecialchars($item['category']); ?></div>
          </div>
          <div style="display:flex; align-items:center; gap:16px;">
            <div class="price">RM <?php echo number_format((float) $item['price'], 2); ?></div>
            <form method="post">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="action" value="remove">
              <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
              <button type="submit" class="remove-btn">Remove</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div class="summary">
    <h2>Order Summary</h2>
    <div class="summary-row"><span>Subtotal</span><span>RM <?php echo number_format($total, 2); ?></span></div>
    <div class="summary-total"><span>Total</span><span>RM <?php echo number_format($total, 2); ?></span></div>

    <?php if (!empty($items)): ?>
      <?php if ($isLoggedIn): ?>
        <a href="custom.php?from=cart" class="btn">Proceed to Checkout</a>
      <?php else: ?>
        <a href="login.php" class="btn">Log in to Checkout</a>
        <div class="notice">You'll need an account to check out, so we can keep your order on file.</div>
      <?php endif; ?>
    <?php endif; ?>
  </div>

</div>

</body>
</html>
