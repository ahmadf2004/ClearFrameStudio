<?php
session_start();
require 'db.php';

// Gate: bounce anyone without a valid admin session back to login.
if (empty($_SESSION['is_admin'])) {
    header('Location: admin-login.php');
    exit;
}

$adminName = $_SESSION['admin_name'] ?? 'Admin';

// ---- Live stats ----
$productsCount = (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
$requestsCount = (int) $pdo->query('SELECT COUNT(*) FROM custom_requests')->fetchColumn();
$newRequestsCount = (int) $pdo->query("SELECT COUNT(*) FROM custom_requests WHERE status = 'new'")->fetchColumn();
$usersCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$pendingPaymentsCount = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending_verification'")->fetchColumn();

$stats = [
    ['label' => 'Products live',       'value' => $productsCount],
    ['label' => 'Custom requests',     'value' => $requestsCount],
    ['label' => 'New requests',        'value' => $newRequestsCount],
    ['label' => 'Registered users',    'value' => $usersCount],
    ['label' => 'Payments to review',  'value' => $pendingPaymentsCount],
];

// ---- Recent custom requests ----
$recentRequests = $pdo->query(
    'SELECT name, product_type, status, created_at FROM custom_requests
     ORDER BY created_at DESC LIMIT 5'
)->fetchAll();

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
<title>Admin Dashboard — Clear Frame Studio</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<style>
  :root{
    --paper:#FAFAF7;
    --ink:#1C2530;
    --muted:#6B7480;
    --frame-blue:#3E5C76;
    --frame-blue-deep:#26384A;
    --amber:#E8A13D;
    --alert:#9B3B3B;
    --line:#D8DBDE;
    --line-soft:#E7E9EB;
    --max:1160px;
  }

  *{box-sizing:border-box;}
  body{
    margin:0;
    background:var(--paper);
    color:var(--ink);
    font-family:'Inter',sans-serif;
    -webkit-font-smoothing:antialiased;
  }
  a{color:inherit;text-decoration:none;}
  table{border-collapse:collapse;width:100%;}

  .wrap{ max-width:var(--max); margin:0 auto; padding:0 32px; }

  /* ---------- Header ---------- */
  header{
    border-bottom:1px solid var(--line);
    background:var(--paper);
  }
  .header-inner{
    max-width:var(--max);
    margin:0 auto;
    padding:18px 32px;
    display:flex;
    align-items:center;
    justify-content:space-between;
  }
  .logo{ display:flex; align-items:center; }
  .logo-img{ height:40px; width:auto; display:block; }

  .header-right{
    display:flex;
    align-items:center;
    gap:20px;
  }
  .admin-chip{
    display:flex;
    align-items:center;
    gap:8px;
    font-size:13px;
    color:var(--muted);
  }
  .admin-chip .dot{
    width:7px;
    height:7px;
    border-radius:50%;
    background:var(--alert);
  }
  .logout-link{
    font-size:13px;
    font-weight:600;
    color:var(--frame-blue-deep);
    padding-bottom:2px;
    border-bottom:1px solid transparent;
  }
  .logout-link:hover{ border-bottom-color:var(--amber); color:var(--frame-blue); }

  /* ---------- Page head ---------- */
  .page-head{
    max-width:var(--max);
    margin:0 auto;
    padding:48px 32px 8px;
  }
  .eyebrow-line{ display:flex; align-items:center; gap:10px; margin-bottom:16px; }
  .eyebrow-line .dash{ width:22px; height:1px; background:var(--alert); }
  .eyebrow-line span{ font-size:12px; letter-spacing:0.1em; color:var(--muted); }

  .page-head h1{
    font-family:'Space Grotesk',sans-serif;
    font-weight:600;
    font-size:clamp(24px, 3vw, 32px);
    margin:0 0 8px;
  }
  .page-head p{
    color:var(--muted);
    font-size:14px;
    margin:0;
  }

  /* ---------- Stats ---------- */
  .stats{
    max-width:var(--max);
    margin:0 auto;
    padding:36px 32px 0;
    display:grid;
    grid-template-columns:repeat(5, 1fr);
    gap:1px;
    background:var(--line);
    border:1px solid var(--line);
  }
  .stat-card{
    background:var(--paper);
    padding:28px 26px;
  }
  .stat-card .value{
    font-family:'Space Grotesk',sans-serif;
    font-size:30px;
    font-weight:600;
    color:var(--frame-blue-deep);
  }
  .stat-card .label{
    font-size:13px;
    color:var(--muted);
    margin-top:6px;
  }

  /* ---------- Sections ---------- */
  .section{
    max-width:var(--max);
    margin:0 auto;
    padding:48px 32px;
  }
  .section-head{
    display:flex;
    align-items:baseline;
    justify-content:space-between;
    margin-bottom:22px;
    gap:16px;
    flex-wrap:wrap;
  }
  .section-head h2{
    font-family:'Space Grotesk',sans-serif;
    font-size:19px;
    font-weight:600;
    margin:0;
  }
  .section-head a{
    font-size:13px;
    font-weight:600;
    color:var(--frame-blue-deep);
    border-bottom:1px solid var(--frame-blue-deep);
    padding-bottom:2px;
  }
  .section-head a:hover{ color:var(--frame-blue); border-color:var(--frame-blue); }

  .panel{
    border:1px solid var(--line);
  }

  table th, table td{
    text-align:left;
    padding:14px 20px;
    font-size:13.5px;
    border-bottom:1px solid var(--line-soft);
  }
  table th{
    font-size:11.5px;
    letter-spacing:0.06em;
    color:var(--muted);
    font-weight:600;
    background:var(--line-soft);
  }
  table tr:last-child td{ border-bottom:none; }

  .empty-state{
    padding:44px 24px;
    text-align:center;
    color:var(--muted);
    font-size:14px;
  }

  .badge{ display:inline-flex; padding:4px 12px; border-radius:999px; font-size:11px; font-weight:600; letter-spacing:0.02em; white-space:nowrap; }
  .badge-pending{ background:#FCEFD1; color:#8A5A00; }
  .badge-review{ background:#D9EEF2; color:#1B6E7D; }
  .badge-closed{ background:#DFF2E1; color:#276B33; }

  .quick-actions{
    display:grid;
    grid-template-columns:repeat(2, 1fr);
    gap:1px;
    background:var(--line);
    border:1px solid var(--line);
  }
  .quick-actions a{
    background:var(--paper);
    padding:26px 24px;
    display:block;
  }
  .quick-actions a:hover{ background:var(--line-soft); }
  .quick-actions .tag{
    font-size:11px;
    font-weight:600;
    letter-spacing:0.08em;
    color:var(--frame-blue);
    display:block;
    margin-bottom:8px;
  }
  .quick-actions h3{
    font-family:'Space Grotesk',sans-serif;
    font-size:16px;
    font-weight:600;
    margin:0 0 6px;
  }
  .quick-actions p{
    font-size:13px;
    color:var(--muted);
    margin:0;
  }

  /* ---------- Responsive ---------- */
  @media (max-width:900px){
    .stats{ grid-template-columns:repeat(2, 1fr); }
    .quick-actions{ grid-template-columns:1fr; }
  }
  @media (max-width:560px){
    .stats{ grid-template-columns:1fr; }
    table{ font-size:12.5px; }
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
  <div class="eyebrow-line">
    <span class="dash"></span>
    <span>ADMIN DASHBOARD</span>
  </div>
  <h1>Welcome back, <?php echo htmlspecialchars($adminName); ?></h1>
  <p>Here's what's happening across the shop.</p>
</div>

<div class="stats">
  <?php foreach ($stats as $stat): ?>
    <div class="stat-card">
      <div class="value"><?php echo htmlspecialchars($stat['value']); ?></div>
      <div class="label"><?php echo htmlspecialchars($stat['label']); ?></div>
    </div>
  <?php endforeach; ?>
</div>

<div class="section">
  <div class="section-head">
    <h2>Recent custom requests</h2>
    <a href="manage-requests.php">View all</a>
  </div>
  <div class="panel">
    <?php if (empty($recentRequests)): ?>
      <div class="empty-state">
        No custom requests yet — submissions from the Custom page will show up here.
      </div>
    <?php else: ?>
      <table>
        <thead>
          <tr><th>Name</th><th>Product type</th><th>Status</th><th>Submitted</th></tr>
        </thead>
        <tbody>
          <?php foreach ($recentRequests as $req): ?>
            <tr>
              <td><?php echo htmlspecialchars($req['name']); ?></td>
              <td><?php echo htmlspecialchars($req['product_type'] ?: '—'); ?></td>
              <td><?php echo statusBadge($req['status']); ?></td>
              <td><?php echo date('M j, g:ia', strtotime($req['created_at'])); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<div class="section">
  <div class="section-head">
    <h2>Quick actions</h2>
  </div>
  <div class="quick-actions">
    <a href="verify-payments.php">
      <span class="tag">PAYMENTS</span>
      <h3>Verify payments<?php echo $pendingPaymentsCount > 0 ? " ($pendingPaymentsCount)" : ''; ?></h3>
      <p>Review uploaded bank transfer receipts and approve or reject them.</p>
    </a>
    <a href="manage-products.php">
      <span class="tag">CATALOG</span>
      <h3>Manage products</h3>
      <p>Add, edit, or retire PowerPoint, Canva, e-module, and icon listings.</p>
    </a>
    <a href="manage-requests.php">
      <span class="tag">REQUESTS</span>
      <h3>Custom requests</h3>
      <p>Review submissions and update their status.</p>
    </a>
    <a href="manage-customers.php">
      <span class="tag">CUSTOMERS</span>
      <h3>Manage customers</h3>
      <p>View and remove registered customer accounts.</p>
    </a>
    <a href="add-admin.php">
      <span class="tag">STAFF</span>
      <h3>Add admin</h3>
      <p>Create another account with full admin access.</p>
    </a>
  </div>
</div>

</body>
</html>
