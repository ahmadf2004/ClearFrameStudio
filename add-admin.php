<?php
session_start();
require 'db.php';
require 'csrf.php';

// Gate: only an already-logged-in admin can reach this page.
if (empty($_SESSION['is_admin'])) {
    header('Location: admin-login.php');
    exit;
}

$adminName = $_SESSION['admin_name'] ?? 'Admin';
$adminId   = $_SESSION['admin_id'] ?? null;

$errors = [];
$deleteError = '';
$done = false;
$values = ['username' => '', 'email' => ''];

// Delete an admin (but never yourself, while logged in as that account)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    csrf_verify();
    $deleteId = (int) $_POST['delete_id'];
    if ($adminId !== null && $deleteId === (int) $adminId) {
        $deleteError = "You can't delete the admin account you're currently logged in as.";
    } else {
        $stmt = $pdo->prepare('DELETE FROM admins WHERE id = ?');
        $stmt->execute([$deleteId]);
        header('Location: add-admin.php');
        exit;
    }
}

// Add a new admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_admin'])) {
    csrf_verify();
    $values['username'] = trim($_POST['username'] ?? '');
    $values['email']    = trim($_POST['email'] ?? '');
    $password            = $_POST['password'] ?? '';
    $confirm             = $_POST['confirm_password'] ?? '';

    if ($values['username'] === '') {
        $errors[] = 'Please enter a username.';
    }
    if ($values['email'] === '' || !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO admins (username, email, password_hash) VALUES (?, ?, ?)'
            );
            $stmt->execute([
                $values['username'],
                $values['email'],
                password_hash($password, PASSWORD_DEFAULT),
            ]);
            header('Location: add-admin.php');
            exit;
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $errors[] = 'That username or email is already registered as an admin.';
            } else {
                $errors[] = 'Something went wrong creating the account. Please try again.';
            }
        }
    }
}

$admins = $pdo->query('SELECT * FROM admins ORDER BY created_at DESC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Admin — Clear Frame Studio</title>
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
  table{ border-collapse:collapse; width:100%; }

  .section-head{ margin-bottom:16px; }
  .section-head h2{ font-family:'Space Grotesk',sans-serif; font-size:17px; font-weight:600; margin:0; }

  .panel{ border:1px solid var(--line); }
  table th, table td{ text-align:left; padding:14px 20px; font-size:13.5px; border-bottom:1px solid var(--line-soft); vertical-align:middle; }
  table th{ font-size:11.5px; letter-spacing:0.06em; color:var(--muted); font-weight:600; background:var(--line-soft); }
  table tr:last-child td{ border-bottom:none; }

  .del-btn{
    background:none; border:1px solid var(--alert); color:var(--alert);
    font-size:12.5px; font-weight:600; padding:7px 14px; border-radius:2px; cursor:pointer;
    font-family:'Inter',sans-serif;
  }
  .del-btn:hover{ background:var(--alert); color:var(--paper); }

  header{ border-bottom:1px solid var(--line); }
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
  .header-right{ display:flex; align-items:center; gap:20px; }
  .admin-chip{ display:flex; align-items:center; gap:8px; font-size:13px; color:var(--muted); }
  .admin-chip .dot{ width:7px; height:7px; border-radius:50%; background:var(--alert); }
  .logout-link{
    font-size:13px; font-weight:600; color:var(--frame-blue-deep);
    padding-bottom:2px; border-bottom:1px solid transparent;
  }
  .logout-link:hover{ border-bottom-color:var(--amber); color:var(--frame-blue); }

  .page-head{ max-width:var(--max); margin:0 auto; padding:48px 32px 8px; }
  .back-link{
    display:inline-flex; align-items:center; gap:6px;
    font-size:12.5px; font-weight:600; color:var(--muted);
    margin-bottom:20px; padding-bottom:2px; border-bottom:1px solid transparent;
  }
  .back-link svg{ width:14px; height:14px; stroke:var(--muted); }
  .back-link:hover{ color:var(--frame-blue-deep); border-bottom-color:var(--amber); }
  .back-link:hover svg{ stroke:var(--frame-blue-deep); }

  .eyebrow-line{ display:flex; align-items:center; gap:10px; margin-bottom:16px; }
  .eyebrow-line .dash{ width:22px; height:1px; background:var(--alert); }
  .eyebrow-line span{ font-size:12px; letter-spacing:0.1em; color:var(--muted); }

  .page-head h1{
    font-family:'Space Grotesk',sans-serif;
    font-weight:600;
    font-size:clamp(24px, 3vw, 32px);
    margin:0 0 8px;
  }
  .page-head p{ color:var(--muted); font-size:14px; margin:0; }

  .form-section{ max-width:var(--max); margin:0 auto; padding:36px 32px 100px; }

  .form-panel{
    position:relative;
    border:1px solid var(--line);
    padding:44px;
    max-width:520px;
  }
  .field{ margin-bottom:22px; }
  .field label{ display:block; font-size:13px; font-weight:600; margin-bottom:7px; }
  .field input{
    width:100%; padding:12px 14px;
    background:var(--line-soft); border:1px solid transparent; border-radius:2px;
    font-family:'Inter',sans-serif; font-size:14px; color:var(--ink);
    outline:none; transition:border-color 0.2s ease, background 0.2s ease;
  }
  .field input:focus{ border-color:var(--frame-blue); background:var(--paper); }

  .btn{
    display:inline-flex; align-items:center; justify-content:center;
    padding:13px 26px; font-size:14px; font-weight:600; border-radius:2px;
    border:1px solid transparent; cursor:pointer;
    background:var(--frame-blue-deep); color:var(--paper);
    transition:background 0.2s ease;
  }
  .btn:hover{ background:var(--frame-blue); }

  .notice{
    background:#EAF3EC; border:1px solid #BEDCC4; color:#2C5E38;
    padding:14px 18px; font-size:13.5px; margin-bottom:22px; max-width:520px;
  }
  .error-list{
    background:#FBEAEA; border:1px solid #E2B4B4; color:#7A2020;
    padding:14px 18px; font-size:13.5px; margin-bottom:22px; max-width:520px;
  }
  .error-list ul{ margin:6px 0 0; padding-left:18px; }
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
    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M15 18l-6-6 6-6"/>
    </svg>
    Back to Dashboard
  </a>
  <div class="eyebrow-line">
    <span class="dash"></span>
    <span>STAFF ONLY</span>
  </div>
  <h1>Add a new admin</h1>
  <p>Creates another account with full admin access. Only give this to people you trust.</p>
</div>

<div class="form-section">

  <?php if ($deleteError): ?>
    <div class="error-list"><?php echo htmlspecialchars($deleteError); ?></div>
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

  <div class="section-head"><h2>Add a new admin</h2></div>
  <form class="form-panel" method="post" novalidate>
    <?php echo csrf_field(); ?>
    <div class="field">
      <label for="username">Username</label>
      <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($values['username']); ?>" required>
    </div>
    <div class="field">
      <label for="email">Email</label>
      <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($values['email']); ?>" required>
    </div>
    <div class="field">
      <label for="password">Password</label>
      <input type="password" id="password" name="password" required>
    </div>
    <div class="field">
      <label for="confirm_password">Confirm password</label>
      <input type="password" id="confirm_password" name="confirm_password" required>
    </div>
    <button type="submit" name="add_admin" value="1" class="btn">Create admin account</button>
  </form>

  <div class="section-head" style="margin-top:48px;"><h2>Current admins (<?php echo count($admins); ?>)</h2></div>
  <div class="panel">
    <table>
      <thead>
        <tr><th>Username</th><th>Email</th><th>Added</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($admins as $a): ?>
          <tr>
            <td>
              <?php echo htmlspecialchars($a['username']); ?>
              <?php if ((int) $a['id'] === (int) $adminId): ?>
                <span style="color:var(--muted); font-size:12px;">(you)</span>
              <?php endif; ?>
            </td>
            <td><?php echo htmlspecialchars($a['email']); ?></td>
            <td><?php echo date('d M Y', strtotime($a['created_at'])); ?></td>
            <td>
              <?php if ((int) $a['id'] === (int) $adminId): ?>
                <span style="color:var(--muted); font-size:12.5px;">—</span>
              <?php else: ?>
                <form method="post" onsubmit="return confirm('Remove this admin account? This cannot be undone.');">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="delete_id" value="<?php echo $a['id']; ?>">
                  <button type="submit" class="del-btn">Delete</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

</div>

</body>
</html>
