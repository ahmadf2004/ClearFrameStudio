<?php
// One-time setup script: creates your first admin account with a
// properly hashed password. Run this once in your browser, then
// DELETE this file — leaving it on a live site would let anyone
// create new admin accounts.

session_start();
require 'db.php';
require 'csrf.php';

$done = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $email === '' || strlen($password) < 8) {
        $error = 'Please fill in all fields (password must be at least 8 characters).';
    } else {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO admins (username, email, password_hash) VALUES (?, ?, ?)'
            );
            $stmt->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT)]);
            $done = true;
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $error = 'That username or email is already registered as an admin.';
            } else {
                $error = 'Something went wrong: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Seed Admin — Clear Frame Studio</title>
<style>
  body{ font-family:sans-serif; max-width:480px; margin:60px auto; padding:0 20px; color:#1C2530; }
  h1{ font-size:20px; }
  label{ display:block; margin:16px 0 6px; font-weight:600; font-size:14px; }
  input{ width:100%; padding:10px; box-sizing:border-box; font-size:14px; }
  button{ margin-top:20px; padding:12px 20px; background:#26384A; color:#fff; border:none; cursor:pointer; }
  .ok{ background:#EAF3EC; border:1px solid #BEDCC4; color:#2C5E38; padding:14px; }
  .err{ background:#FBEAEA; border:1px solid #E2B4B4; color:#7A2020; padding:14px; }
</style>
</head>
<body>

<h1>Create first admin account</h1>

<?php if ($done): ?>
  <p class="ok">Admin account created. You can now log in at <a href="admin-login.php">admin-login.php</a>.</p>
  <p><strong>Now delete this file (seed-admin.php) from your project folder.</strong></p>
<?php else: ?>

  <?php if ($error): ?><p class="err"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>

  <form method="post">
    <?php echo csrf_field(); ?>
    <label for="username">Username</label>
    <input type="text" id="username" name="username" required>

    <label for="email">Email</label>
    <input type="email" id="email" name="email" required>

    <label for="password">Password</label>
    <input type="password" id="password" name="password" required>

    <button type="submit">Create admin</button>
  </form>
<?php endif; ?>

</body>
</html>
