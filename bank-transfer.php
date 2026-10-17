<?php
session_start();
require 'db.php';
require 'csrf.php';
require 'bank-config.php';

// ============================================================
// MANUAL BANK TRANSFER + ADMIN VERIFICATION
// ============================================================
// This replaces a real payment gateway (ToyyibPay) with a manual
// workflow: the customer transfers money to the account shown below
// on their own banking app, uploads a screenshot/receipt as proof,
// and an admin reviews that proof in verify-payments.php before the
// order is actually marked paid. Nothing here moves real money or
// confirms a transfer happened automatically — the admin's manual
// approval IS the "payment confirmed" step, same role a webhook
// callback would play in a real gateway integration.
// ============================================================

if (empty($_SESSION['is_user'])) {
    header('Location: login.php');
    exit;
}

$userId  = $_SESSION['user_id'];
$orderId = (int) ($_GET['order_id'] ?? $_POST['order_id'] ?? 0);

// Ownership check — same pattern as everywhere else: scope the query
// to this user, never just "is someone logged in".
$stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ? AND user_id = ?');
$stmt->execute([$orderId, $userId]);
$order = $stmt->fetch();

if (!$order) {
    die('Order not found.');
}

// Already resolved — nothing to do here.
if ($order['status'] === 'paid' || $order['status'] === 'cancelled') {
    header('Location: order-confirmation.php?order_id=' . $orderId);
    exit;
}

// Once proof is submitted and awaiting admin review, this page is
// read-only until the admin either approves or rejects it.
if ($order['status'] === 'pending_verification') {
    header('Location: order-confirmation.php?order_id=' . $orderId);
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    if (isset($_POST['cancel_order'])) {
        $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute(['cancelled', $orderId]);
        header('Location: cart.php');
        exit;
    }

    if (isset($_POST['submit_proof'])) {
        $paymentNote = trim($_POST['payment_note'] ?? '');

        if (empty($_FILES['proof']['name'])) {
            $errors[] = 'Please upload a screenshot or PDF of your payment receipt.';
        } else {
            $tmpPath = $_FILES['proof']['tmp_name'];

            // Same pattern as custom.php's file upload: trust the
            // file's actual content (magic bytes), not the filename
            // or declared extension.
            $allowedTypes = [
                'pdf'  => ['application/pdf'],
                'png'  => ['image/png'],
                'jpg'  => ['image/jpeg'],
                'jpeg' => ['image/jpeg'],
            ];

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detectedMime = finfo_file($finfo, $tmpPath);
            finfo_close($finfo);

            $declaredExt = strtolower(pathinfo($_FILES['proof']['name'], PATHINFO_EXTENSION));
            $matchedExt = null;
            if (isset($allowedTypes[$declaredExt]) && in_array($detectedMime, $allowedTypes[$declaredExt], true)) {
                $matchedExt = $declaredExt;
            }

            if (!$matchedExt) {
                $errors[] = 'Proof must be a genuine PDF, PNG, or JPG file.';
            } else {
                $uploadDir = __DIR__ . '/uploads/payment-proofs/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                // Defense in depth: stops this folder from ever
                // executing anything as PHP, even if a disguised file
                // somehow passed the MIME check above.
                $htaccessPath = $uploadDir . '.htaccess';
                if (!file_exists($htaccessPath)) {
                    file_put_contents($htaccessPath,
                        "<IfModule mod_php.c>\n    php_flag engine off\n</IfModule>\n" .
                        "<IfModule mod_php8.c>\n    php_flag engine off\n</IfModule>\n" .
                        "<FilesMatch \"\\.(php|phtml|php3|php4|php5|phar)$\">\n" .
                        "    Require all denied\n" .
                        "</FilesMatch>\n"
                    );
                }

                $storedFileName = uniqid('proof_', true) . '.' . $matchedExt;
                if (!move_uploaded_file($tmpPath, $uploadDir . $storedFileName)) {
                    $errors[] = 'Something went wrong uploading your file. Please try again.';
                } else {
                    $pdo->prepare(
                        'UPDATE orders
                         SET status = ?, payment_proof = ?, payment_note = ?,
                             proof_submitted_at = NOW(), rejection_reason = NULL
                         WHERE id = ?'
                    )->execute(['pending_verification', $storedFileName, $paymentNote !== '' ? $paymentNote : null, $orderId]);

                    header('Location: order-confirmation.php?order_id=' . $orderId);
                    exit;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Bank Transfer — Clear Frame Studio</title>
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
  .page-head{ max-width:720px; margin:0 auto; padding:56px 32px 8px; }
  .page-head h1{ font-family:'Space Grotesk',sans-serif; font-weight:600; font-size:clamp(26px,4vw,32px); margin:0 0 8px; }
  .page-head p{ color:var(--muted); font-size:14px; margin:0; }
  .section{ max-width:720px; margin:0 auto; padding:36px 32px 100px; }

  .bank-panel{ border:1px solid var(--line); padding:28px; margin-bottom:24px; }
  .bank-panel h2{ font-family:'Space Grotesk',sans-serif; font-size:16px; margin:0 0 18px; }
  .bank-row{ display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid var(--line-soft); font-size:14px; gap:16px; }
  .bank-row:last-child{ border-bottom:none; }
  .bank-row .label{ color:var(--muted); }
  .bank-row .value{ font-weight:600; font-family:'Space Grotesk',sans-serif; }
  .amount-due{ font-size:26px; }

  .panel{ border:1px solid var(--line); padding:28px; }
  .field{ margin-bottom:22px; }
  .field label{ display:block; font-size:13px; font-weight:600; margin-bottom:8px; }
  .field .hint{ font-size:12px; color:var(--muted); margin-top:6px; }
  .field input[type="text"],
  .field textarea{
    width:100%; padding:12px 14px; background:var(--line-soft); border:1px solid transparent; border-radius:2px;
    font-family:'Inter',sans-serif; font-size:14px; color:var(--ink); outline:none;
  }
  .field input:focus, .field textarea:focus{ border-color:var(--frame-blue); background:var(--paper); }
  .field textarea{ resize:vertical; min-height:80px; }
  .file-drop{
    display:flex; align-items:center; gap:12px; padding:12px 14px;
    background:var(--line-soft); border:1px dashed var(--line); font-size:13px; color:var(--muted);
  }
  .file-drop input[type="file"]{ font-size:13px; max-width:100%; }

  .btn{ display:flex; align-items:center; justify-content:center; width:100%; padding:14px 20px; font-size:14px; font-weight:600; border-radius:2px; border:1px solid transparent; cursor:pointer; }
  .btn-primary{ background:var(--frame-blue-deep); color:var(--paper); margin-top:6px; }
  .btn-primary:hover{ background:var(--frame-blue); }
  .btn-secondary{ background:transparent; color:var(--muted); border-color:var(--line); margin-top:10px; }
  .btn-secondary:hover{ border-color:#9B3B3B; color:#9B3B3B; }

  .error-list{ background:#FBEAEA; border:1px solid #E2B4B4; color:#7A2020; padding:14px 18px; font-size:13.5px; margin-bottom:22px; }
  .error-list ul{ margin:6px 0 0; padding-left:18px; }
  .rejected-notice{ background:#FBEAEA; border:1px solid #E2B4B4; color:#7A2020; padding:14px 18px; font-size:13.5px; margin-bottom:24px; }
  .rejected-notice strong{ display:block; margin-bottom:4px; }
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
  <h1>Bank Transfer</h1>
  <p>Transfer the amount below, then upload your receipt so we can verify it.</p>
</div>

<div class="section">

  <?php if ($order['status'] === 'rejected'): ?>
    <div class="rejected-notice">
      <strong>Your last proof of payment was rejected.</strong>
      <?php echo htmlspecialchars($order['rejection_reason'] ?: 'Please check the details and submit a new receipt.'); ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($errors)): ?>
    <div class="error-list">
      Please fix the following:
      <ul>
        <?php foreach ($errors as $error): ?>
          <li><?php echo htmlspecialchars($error); ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div class="bank-panel">
    <h2>Transfer to</h2>
    <div class="bank-row"><span class="label">Bank</span><span class="value"><?php echo htmlspecialchars(BANK_NAME); ?></span></div>
    <div class="bank-row"><span class="label">Account number</span><span class="value"><?php echo htmlspecialchars(BANK_ACCOUNT_NUMBER); ?></span></div>
    <div class="bank-row"><span class="label">Account holder</span><span class="value"><?php echo htmlspecialchars(BANK_ACCOUNT_HOLDER); ?></span></div>
    <div class="bank-row"><span class="label">Reference</span><span class="value">ORDER-<?php echo $order['id']; ?></span></div>
    <div class="bank-row"><span class="label">Amount</span><span class="value amount-due">RM <?php echo number_format((float) $order['total_amount'], 2); ?></span></div>
  </div>

  <form class="panel" method="post" enctype="multipart/form-data">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">

    <div class="field">
      <label for="proof">Proof of payment</label>
      <div class="file-drop">
        <input type="file" id="proof" name="proof" accept=".pdf,.png,.jpg,.jpeg" required>
      </div>
      <p class="hint">A screenshot or PDF of your bank's transfer receipt. PNG, JPG, or PDF.</p>
    </div>

    <div class="field">
      <label for="payment_note">Note (optional)</label>
      <input type="text" id="payment_note" name="payment_note" placeholder="e.g. transferred from Maybank, 30 Sep 7:45pm">
    </div>

    <button type="submit" name="submit_proof" value="1" class="btn btn-primary">Submit Proof of Payment</button>
  </form>

  <form method="post" onsubmit="return confirm('Cancel this order?');">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
    <button type="submit" name="cancel_order" value="1" class="btn btn-secondary">Cancel order</button>
  </form>

</div>

</body>
</html>
