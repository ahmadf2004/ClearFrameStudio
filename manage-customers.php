<?php
session_start();
require 'db.php';
require 'csrf.php';

if (empty($_SESSION['is_admin'])) {
    header('Location: admin-login.php');
    exit;
}
$adminName = $_SESSION['admin_name'] ?? 'Admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    csrf_verify();
    $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
    $stmt->execute([(int) $_POST['delete_id']]);
    header('Location: manage-customers.php');
    exit;
}

$customers = $pdo->query(
    'SELECT * FROM users ORDER BY created_at DESC'
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Customers — Clear Frame Studio</title>
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

  .page-head{ max-width:var(--max); margin:0 auto; padding:48px 32px 8px; display:flex; align-items:flex-end; justify-content:space-between; gap:20px; flex-wrap:wrap; }
  .back-link{
    display:inline-flex; align-items:center; gap:6px; font-size:12.5px; font-weight:600;
    color:var(--muted); margin-bottom:20px; padding-bottom:2px; border-bottom:1px solid transparent;
  }
  .back-link svg{ width:14px; height:14px; stroke:var(--muted); }
  .back-link:hover{ color:var(--frame-blue-deep); border-bottom-color:var(--amber); }
  .back-link:hover svg{ stroke:var(--frame-blue-deep); }

  .page-head h1{ font-family:'Space Grotesk',sans-serif; font-weight:600; font-size:clamp(24px, 3vw, 32px); margin:0 0 8px; }
  .page-head p{ color:var(--muted); font-size:14px; margin:0; }
  .page-head .count{ font-size:13px; color:var(--muted); }

  .section{ max-width:var(--max); margin:0 auto; padding:20px 32px 100px; }
  .panel{ border:1px solid var(--line); }

  table th, table td{ text-align:left; padding:14px 20px; font-size:13.5px; border-bottom:1px solid var(--line-soft); vertical-align:middle; }
  table th{ font-size:11.5px; letter-spacing:0.06em; color:var(--muted); font-weight:600; background:var(--line-soft); }
  table tr:last-child td{ border-bottom:none; }

  .empty-state{ padding:44px 24px; text-align:center; color:var(--muted); font-size:14px; }

  .del-btn{
    background:none; border:1px solid var(--alert); color:var(--alert);
    font-size:12.5px; font-weight:600; padding:7px 14px; border-radius:2px; cursor:pointer;
    font-family:'Inter',sans-serif;
  }
  .del-btn:hover{ background:var(--alert); color:var(--paper); }

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
  <div>
    <a href="admin-dashboard.php" class="back-link">
      <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
      Back to Dashboard
    </a>
    <h1>Manage Customers</h1>
    <p>Everyone who has registered an account.</p>
  </div>
  <span class="count"><?php echo count($customers); ?> total</span>
</div>

<div class="section">
  <div class="panel">
    <?php if (empty($customers)): ?>
      <div class="empty-state">No registered customers yet.</div>
    <?php else: ?>
      <table>
        <thead>
          <tr><th>Name</th><th>Email</th><th>Registered</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($customers as $c): ?>
            <tr>
              <td><?php echo htmlspecialchars($c['full_name']); ?></td>
              <td><?php echo htmlspecialchars($c['email']); ?></td>
              <td><?php echo date('d M Y', strtotime($c['created_at'])); ?></td>
              <td>
                <form method="post" onsubmit="return confirm('Remove this customer account? This cannot be undone.');">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="delete_id" value="<?php echo $c['id']; ?>">
                  <button type="submit" class="del-btn">Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

</body>
</html>
