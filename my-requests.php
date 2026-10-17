<?php
session_start();
require 'db.php';

// Gate: customers only, and only their own requests — see the
// ownership check below when viewing a single request's detail.
if (empty($_SESSION['is_user'])) {
    header('Location: login.php');
    exit;
}

$userId   = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? '';

$viewId  = isset($_GET['id']) ? (int) $_GET['id'] : null;
$request = null;

if ($viewId) {
    // IMPORTANT: filter by user_id as well as id. Without this, any
    // logged-in customer could view any other customer's request just
    // by changing the number in the URL (an IDOR — insecure direct
    // object reference). Scoping every query to $userId is what
    // makes this an "each customer sees only their own data" page
    // instead of a security hole.
    $stmt = $pdo->prepare('SELECT * FROM custom_requests WHERE id = ? AND user_id = ?');
    $stmt->execute([$viewId, $userId]);
    $request = $stmt->fetch();

    // Viewing the detail is what "reads" the update — clear the flag,
    // still scoped to this user's own request only.
    if ($request && $request['has_unread_update']) {
        $pdo->prepare('UPDATE custom_requests SET has_unread_update = 0 WHERE id = ? AND user_id = ?')
            ->execute([$viewId, $userId]);
        $request['has_unread_update'] = 0;
    }

    // An order is linked back to this request when it was placed via
    // the "custom request -> checkout" flow (see checkout.php).
    $linkedOrder = null;
    if ($request) {
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE custom_request_id = ? AND user_id = ?');
        $stmt->execute([$viewId, $userId]);
        $linkedOrder = $stmt->fetch();
    }
}

$orderStatusLabels = [
    'pending'   => ['Pending Payment', '#8A5A00', '#FCEFD1'],
    'paid'      => ['Paid', '#276B33', '#DFF2E1'],
    'failed'    => ['Failed', '#7A2020', '#FBEAEA'],
    'cancelled' => ['Cancelled', '#6B7480', '#E7E9EB'],
];

$requests = $pdo->prepare(
    'SELECT custom_requests.*, orders.id AS order_id, orders.status AS order_status
     FROM custom_requests
     LEFT JOIN orders ON orders.custom_request_id = custom_requests.id
     WHERE custom_requests.user_id = ?
     ORDER BY custom_requests.created_at DESC'
);
$requests->execute([$userId]);
$requests = $requests->fetchAll();

function statusBadge($status) {
    $map = [
        'new'         => ['Pending',    'badge-pending'],
        'in_progress' => ['In Review',  'badge-review'],
        'completed'   => ['Completed',  'badge-closed'],
    ];
    [$label, $class] = $map[$status] ?? [$status, 'badge-pending'];
    return "<span class=\"badge $class\">$label</span>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Requests — Clear Frame Studio</title>
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
  table{ border-collapse:collapse; width:100%; }

  header{ border-bottom:1px solid var(--line); position:sticky; top:0; background:var(--paper); z-index:20; }
  .header-inner{ max-width:var(--max); margin:0 auto; padding:18px 32px; display:flex; align-items:center; justify-content:space-between; }
  .logo{ display:flex; align-items:center; }
  .logo-img{ height:40px; width:auto; display:block; }
  .header-right{ display:flex; align-items:center; gap:20px; font-size:13px; font-weight:600; }
  .header-right a:hover{ color:var(--frame-blue); }

  .page-head{ max-width:var(--max); margin:0 auto; padding:48px 32px 8px; }
  .back-link{
    display:inline-flex; align-items:center; gap:6px; font-size:12.5px; font-weight:600;
    color:var(--muted); margin-bottom:20px; padding-bottom:2px; border-bottom:1px solid transparent;
  }
  .back-link svg{ width:14px; height:14px; stroke:var(--muted); }
  .back-link:hover{ color:var(--frame-blue-deep); border-bottom-color:var(--amber); }
  .back-link:hover svg{ stroke:var(--frame-blue-deep); }

  .eyebrow-line{ display:flex; align-items:center; gap:10px; margin-bottom:16px; }
  .eyebrow-line .dash{ width:22px; height:1px; background:var(--amber); }
  .eyebrow-line span{ font-size:12px; letter-spacing:0.1em; color:var(--muted); }

  .page-head h1{ font-family:'Space Grotesk',sans-serif; font-weight:600; font-size:clamp(24px, 3vw, 32px); margin:0 0 8px; }
  .page-head p{ color:var(--muted); font-size:14px; margin:0; }

  .section{ max-width:var(--max); margin:0 auto; padding:36px 32px 100px; }
  .panel{ border:1px solid var(--line); }

  table th, table td{ text-align:left; padding:14px 20px; font-size:13.5px; border-bottom:1px solid var(--line-soft); vertical-align:middle; }
  table th{ font-size:11.5px; letter-spacing:0.06em; color:var(--muted); font-weight:600; background:var(--line-soft); }
  table tr:last-child td{ border-bottom:none; }

  .badge{ display:inline-flex; padding:4px 12px; border-radius:999px; font-size:11px; font-weight:600; letter-spacing:0.02em; white-space:nowrap; }
  .badge-pending{ background:#FCEFD1; color:#8A5A00; }
  .badge-review{ background:#D9EEF2; color:#1B6E7D; }
  .badge-closed{ background:#DFF2E1; color:#276B33; }
  .new-update{
    display:inline-flex; align-items:center; gap:5px;
    font-size:11px; font-weight:700; letter-spacing:0.03em;
    color:#9B3B3B; margin-left:8px;
  }
  .new-update .dot{ width:6px; height:6px; border-radius:50%; background:#9B3B3B; }

  .view-btn{
    display:inline-flex; align-items:center; justify-content:center;
    padding:7px 16px; font-size:12.5px; font-weight:600;
    border:1px solid var(--frame-blue-deep); color:var(--frame-blue-deep); border-radius:2px;
  }
  .view-btn:hover{ background:var(--frame-blue-deep); color:var(--paper); }

  .empty-state{ padding:56px 24px; text-align:center; color:var(--muted); font-size:14px; }
  .empty-state a{ font-weight:600; color:var(--frame-blue-deep); }

  .detail-panel{ border:1px solid var(--line); padding:40px; max-width:640px; }
  .detail-panel .field{ margin-bottom:22px; }
  .detail-panel .label{ font-size:11.5px; font-weight:600; letter-spacing:0.06em; color:var(--muted); margin-bottom:6px; }
  .detail-panel .value{ font-size:14.5px; line-height:1.6; }
  .detail-panel .value.request-text{ white-space:pre-wrap; }

  .error-list{ background:#FBEAEA; border:1px solid #E2B4B4; color:#7A2020; padding:14px 18px; font-size:13.5px; margin-bottom:22px; max-width:640px; }

  @media (max-width:700px){
    table{ font-size:12.5px; }
    table th, table td{ padding:10px 12px; }
  }
</style>
</head>
<body>

<header>
  <div class="header-inner">
    <a href="index.php" class="logo" aria-label="Clear Frame Studio home">
      <img src="clearframe-logo.png" alt="Clear Frame Studio" class="logo-img">
    </a>
    <div class="header-right">
      <span style="color:var(--muted); font-weight:400;"><?php echo htmlspecialchars($userName); ?></span>
      <a href="logout.php">Logout</a>
    </div>
  </div>
</header>

<div class="page-head">
  <a href="index.php" class="back-link">
    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
    Back to Home
  </a>
  <div class="eyebrow-line">
    <span class="dash"></span>
    <span>YOUR ACCOUNT</span>
  </div>
  <h1><?php echo $request ? 'Request Detail' : 'My Requests'; ?></h1>
  <p><?php echo $request ? 'Status and details for this custom request.' : 'Every custom request you\'ve submitted, and where it stands.'; ?></p>
</div>

<div class="section">

  <?php if ($viewId && !$request): ?>
    <div class="error-list">That request wasn't found, or doesn't belong to your account.</div>
  <?php endif; ?>

  <?php if ($request): ?>

    <div class="detail-panel">
      <div class="field">
        <div class="label">STATUS</div>
        <div class="value"><?php echo statusBadge($request['status']); ?></div>
      </div>
      <div class="field">
        <div class="label">PRODUCT TYPE</div>
        <div class="value"><?php echo htmlspecialchars($request['product_type'] ?: '—'); ?></div>
      </div>
      <?php if ($request['quote_amount'] !== null): ?>
        <div class="field">
          <div class="label">QUOTE</div>
          <div class="value" style="font-size:20px; font-weight:600; font-family:'Space Grotesk',sans-serif; color:var(--frame-blue-deep);">
            RM <?php echo number_format((float) $request['quote_amount'], 2); ?>
          </div>
          <?php if ($request['quote_notes']): ?>
            <div class="value" style="color:var(--muted); margin-top:4px;"><?php echo htmlspecialchars($request['quote_notes']); ?></div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
      <?php if ($request['deliverable_file']): ?>
        <div class="field">
          <div class="label">YOUR DELIVERABLE</div>
          <?php
            $deliverableUnlocked = !$linkedOrder || $linkedOrder['status'] === 'paid';
          ?>
          <?php if ($deliverableUnlocked): ?>
            <div class="value">
              <a href="download-request.php?request_id=<?php echo $request['id']; ?>" style="color:var(--frame-blue-deep); font-weight:600;">Download your file &rarr;</a>
            </div>
          <?php else: ?>
            <div class="value" style="color:var(--muted);">
              Your file is ready — it'll unlock here as soon as your payment is verified.
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
      <div class="field">
        <div class="label">YOUR REQUEST</div>
        <div class="value request-text"><?php echo htmlspecialchars($request['request_text']); ?></div>
      </div>
      <?php if ($request['reference_file']): ?>
        <div class="field">
          <div class="label">REFERENCE FILE YOU UPLOADED</div>
          <div class="value">
            <a href="uploads/custom-requests/<?php echo htmlspecialchars($request['reference_file']); ?>" target="_blank" style="color:var(--frame-blue-deep); font-weight:600;">View file</a>
          </div>
        </div>
      <?php endif; ?>
      <div class="field">
        <div class="label">SUBMITTED</div>
        <div class="value"><?php echo date('M j, Y \a\t g:ia', strtotime($request['created_at'])); ?></div>
      </div>
      <?php if ($linkedOrder): ?>
        <div class="field">
          <div class="label">LINKED ORDER</div>
          <div class="value" style="display:flex; align-items:center; gap:10px;">
            <?php [$oLabel, $oColor, $oBg] = $orderStatusLabels[$linkedOrder['status']] ?? [$linkedOrder['status'], '#6B7480', '#E7E9EB']; ?>
            <a href="order-confirmation.php?order_id=<?php echo $linkedOrder['id']; ?>" style="color:var(--frame-blue-deep); font-weight:600;">Order #<?php echo $linkedOrder['id']; ?></a>
            <span class="badge" style="background:<?php echo $oBg; ?>; color:<?php echo $oColor; ?>;"><?php echo htmlspecialchars($oLabel); ?></span>
          </div>
        </div>
      <?php endif; ?>
      <a href="my-requests.php" class="back-link" style="margin-top:8px;">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
        Back to all requests
      </a>
    </div>

  <?php else: ?>

    <div class="panel">
      <?php if (empty($requests)): ?>
        <div class="empty-state">
          You haven't submitted any custom requests yet.
        </div>
      <?php else: ?>
        <table>
          <thead>
            <tr><th>Product Type</th><th>Status</th><th>Order</th><th>Quote</th><th>Submitted</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($requests as $req): ?>
              <tr>
                <td>
                  <?php echo htmlspecialchars($req['product_type'] ?: '—'); ?>
                  <?php if ($req['has_unread_update']): ?>
                    <span class="new-update"><span class="dot"></span>NEW UPDATE</span>
                  <?php endif; ?>
                </td>
                <td><?php echo statusBadge($req['status']); ?></td>
                <td>
                  <?php if ($req['order_id']): ?>
                    <?php [$oLabel, $oColor, $oBg] = $orderStatusLabels[$req['order_status']] ?? [$req['order_status'], '#6B7480', '#E7E9EB']; ?>
                    <a href="order-confirmation.php?order_id=<?php echo $req['order_id']; ?>" style="color:var(--frame-blue-deep); font-weight:600; margin-right:6px;">#<?php echo $req['order_id']; ?></a>
                    <span class="badge" style="background:<?php echo $oBg; ?>; color:<?php echo $oColor; ?>;"><?php echo htmlspecialchars($oLabel); ?></span>
                  <?php else: ?>
                    <span style="color:var(--muted);">—</span>
                  <?php endif; ?>
                </td>
                <td><?php echo $req['quote_amount'] !== null ? 'RM ' . number_format((float) $req['quote_amount'], 2) : '—'; ?></td>
                <td><?php echo date('d M Y', strtotime($req['created_at'])); ?></td>
                <td><a href="my-requests.php?id=<?php echo $req['id']; ?>" class="view-btn">View</a></td>
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
