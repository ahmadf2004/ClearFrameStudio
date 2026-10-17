<?php
session_start();
require 'db.php';
require 'csrf.php';

// ============================================================
// PAYMENT SIMULATOR — stands in for ToyyibPay
// ============================================================
// This page exists because there's no ToyyibPay sandbox account set
// up yet. It deliberately mimics the SHAPE of a real integration so
// swapping in the real thing later is a small, isolated change:
//
//   Real flow would be:
//   1. checkout.php calls ToyyibPay's createBill API (POST to
//      https://toyyibpay.com/index.php/api/createBill) with the
//      order amount and a callback URL, gets back a payment URL,
//      and redirects the customer THERE instead of to this page.
//   2. The customer pays on ToyyibPay's actual site.
//   3. ToyyibPay calls YOUR server back-to-server (a webhook, hitting
//      something like payment-callback.php) to confirm payment
//      actually succeeded — this callback is what marks the order as
//      paid, not anything the browser does.
//   4. The customer is redirected back to a "thank you" page, but
//      that redirect is just for UX — it does NOT mark the order
//      paid, because a redirect can be faked by anyone typing the
//      URL. Only the server-to-server callback in step 3 can be
//      trusted to actually confirm payment.
//
// IMPORTANT SECURITY NOTE: this simulator breaks rule #3/#4 on
// purpose, because there's no real gateway to call back from. The
// "Simulate Successful Payment" button below marks the order paid
// directly from a browser click, with NOTHING confirming money
// actually changed hands. That is fine for a local demo, but it is
// exactly the mistake a real e-commerce integration must never make.
// Flag this clearly in your report as a known simplification.
// ============================================================

if (empty($_SESSION['is_user'])) {
    header('Location: login.php');
    exit;
}

$userId  = $_SESSION['user_id'];
$orderId = (int) ($_GET['order_id'] ?? $_POST['order_id'] ?? 0);

// Ownership check, same pattern as my-requests.php — a customer can
// only act on their OWN order, never one they guess the ID of.
$stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ? AND user_id = ?');
$stmt->execute([$orderId, $userId]);
$order = $stmt->fetch();

if (!$order) {
    die('Order not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $order['status'] === 'pending') {
    csrf_verify();

    if (isset($_POST['simulate_pay'])) {
        $fakeRef = 'SIM-' . strtoupper(bin2hex(random_bytes(4)));
        $pdo->prepare('UPDATE orders SET status = ?, payment_ref = ?, paid_at = NOW() WHERE id = ?')
            ->execute(['paid', $fakeRef, $orderId]);
        header('Location: order-confirmation.php?order_id=' . $orderId);
        exit;
    }

    if (isset($_POST['simulate_cancel'])) {
        $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute(['cancelled', $orderId]);
        header('Location: cart.php');
        exit;
    }
}

// If this order isn't pending anymore (already paid/cancelled), just
// send them to the confirmation page instead of showing a stale form.
if ($order['status'] !== 'pending') {
    header('Location: order-confirmation.php?order_id=' . $orderId);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Payment — Clear Frame Studio</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<style>
  :root{
    --paper:#FAFAF7; --ink:#1C2530; --muted:#6B7480;
    --frame-blue:#3E5C76; --frame-blue-deep:#26384A; --amber:#E8A13D; --line:#D8DBDE;
  }
  *{box-sizing:border-box;}
  html,body{height:100%;}
  body{
    margin:0; background:var(--paper); color:var(--ink); font-family:'Inter',sans-serif;
    display:flex; align-items:center; justify-content:center; min-height:100vh; padding:32px;
  }
  a{ color:inherit; text-decoration:none; }
  .card{ width:100%; max-width:440px; border:1px solid var(--line); padding:44px; }
  .sim-badge{
    display:inline-block; background:#FCEFD1; color:#8A5A00; font-size:11px; font-weight:700;
    letter-spacing:0.06em; padding:5px 12px; border-radius:999px; margin-bottom:20px;
  }
  h1{ font-family:'Space Grotesk',sans-serif; font-size:22px; margin:0 0 6px; }
  .amount{ font-family:'Space Grotesk',sans-serif; font-size:32px; font-weight:600; margin:18px 0 6px; }
  p.muted{ color:var(--muted); font-size:13.5px; margin:0 0 24px; }
  .btn{ display:flex; align-items:center; justify-content:center; width:100%; padding:13px 20px; font-size:14px; font-weight:600; border-radius:2px; border:1px solid transparent; cursor:pointer; }
  .btn-primary{ background:var(--frame-blue-deep); color:var(--paper); margin-bottom:10px; }
  .btn-primary:hover{ background:var(--frame-blue); }
  .btn-secondary{ background:transparent; color:var(--muted); border-color:var(--line); }
  .btn-secondary:hover{ border-color:#9B3B3B; color:#9B3B3B; }
</style>
</head>
<body>

<div class="card">
  <span class="sim-badge">SIMULATED GATEWAY — DEV MODE</span>
  <h1>Confirm payment</h1>
  <p class="muted">This stands in for the real ToyyibPay checkout page.</p>
  <div class="amount">RM <?php echo number_format((float) $order['total_amount'], 2); ?></div>
  <p class="muted">Order #<?php echo $order['id']; ?></p>

  <form method="post">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
    <button type="submit" name="simulate_pay" value="1" class="btn btn-primary">Simulate Successful Payment</button>
    <button type="submit" name="simulate_cancel" value="1" class="btn btn-secondary">Cancel</button>
  </form>
</div>

</body>
</html>
