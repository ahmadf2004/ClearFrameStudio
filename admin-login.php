<?php
require 'session-init.php';
require 'db.php';
require 'csrf.php';
require 'login-throttle.php';

// Real admin authentication against the admins table, with
// brute-force throttling (shared with login.php via
// login-throttle.php — this used to have its own separate, one-off
// throttle implementation with a different table shape, which is why
// it broke when login_attempts got created for login.php's version
// instead; now there's exactly one throttle system, not two).
// Still worth considering: 2FA for admin accounts, for defense in
// depth beyond just password + throttling.

$errors = [];
$values = ['identifier' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $values['identifier'] = trim($_POST['identifier'] ?? '');
    $password             = $_POST['password'] ?? '';

    if ($values['identifier'] === '') {
        $errors[] = 'Please enter your admin username or email.';
    }
    if ($password === '') {
        $errors[] = 'Please enter your password.';
    }

    if (empty($errors)) {
        // Admin login shares the same throttle table/logic as
        // customer login, but under its own 'admin' scope so the two
        // never share a counter — and with its own tighter limits,
        // since an admin account is a much higher-value target.
        $lockedMinutes = login_throttle_check($pdo, 'admin', $values['identifier']);

        if ($lockedMinutes !== null) {
            $errors[] = "Too many failed attempts. Please try again in {$lockedMinutes} minute" . ($lockedMinutes === 1 ? '' : 's') . '.';
        } else {
            $stmt = $pdo->prepare('SELECT * FROM admins WHERE username = ? OR email = ? LIMIT 1');
            $stmt->execute([$values['identifier'], $values['identifier']]);
            $admin = $stmt->fetch();

            // Timing-safe even when no admin matches: password_verify()
            // always runs, against a real bcrypt hash either way, so a
            // nonexistent username/email doesn't reject measurably
            // faster than a wrong password for a real one.
            $hashToCheck = $admin['password_hash'] ?? password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
            $passwordOk  = password_verify($password, $hashToCheck);

            if ($admin && $passwordOk) {
                login_throttle_clear($pdo, 'admin', $values['identifier']);

                if (password_needs_rehash($admin['password_hash'], PASSWORD_DEFAULT)) {
                    $newHash = password_hash($password, PASSWORD_DEFAULT);
                    $pdo->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')
                        ->execute([$newHash, $admin['id']]);
                }

                // Regenerate the session ID on every successful login —
                // stops session fixation (an attacker who set a known
                // session ID on the victim before login can't reuse it
                // to hijack the now-logged-in session afterward).
                session_regenerate_id(true);
                $_SESSION['is_admin']   = true;
                $_SESSION['admin_id']   = $admin['id'];
                $_SESSION['admin_name'] = $admin['username'];
                header('Location: admin-dashboard.php');
                exit;
            } else {
                login_throttle_record_failure($pdo, 'admin', $values['identifier']);
                $errors[] = 'Incorrect username/email or password.';
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
<title>Admin Login — Clear Frame Studio</title>
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
    --line:#D8DBDE;
    --line-soft:#E7E9EB;
    --alert:#9B3B3B;
  }

  *{box-sizing:border-box;}
  html,body{height:100%;}
  body{
    margin:0;
    background:var(--paper);
    color:var(--ink);
    font-family:'Inter',sans-serif;
    -webkit-font-smoothing:antialiased;
    display:flex;
    align-items:center;
    justify-content:center;
    min-height:100vh;
    position:relative;
    overflow-x:hidden;
    overflow-y:auto;
    padding:32px;
  }
  a{color:inherit;text-decoration:none;}

    .bg-photo{
    position:fixed;
    inset:0;
    z-index:0;
    background:url('auth-bg.jpg') center/cover no-repeat;
  }
  .bg-overlay{
    position:fixed;
    inset:0;
    z-index:0;
    background:linear-gradient(135deg, rgba(28,37,48,0.82), rgba(62,92,118,0.66));
  }

  .card{
    position:relative;
    z-index:1;
    width:100%;
    max-width:420px;
    background:var(--paper);
    border:1px solid var(--line);
    padding:52px 44px 44px;
  }

  .corner{
    position:absolute;
    width:26px;
    height:26px;
    stroke:var(--frame-blue);
    stroke-width:1.6;
    fill:none;
  }
  .corner path{
    stroke-dasharray:56;
    stroke-dashoffset:56;
    animation:draw 0.8s ease forwards;
  }
  .corner.tl{ top:-1px; left:-1px; }
  .corner.tr{ top:-1px; right:-1px; transform:scaleX(-1); }
  .corner.bl{ bottom:-1px; left:-1px; transform:scaleY(-1); }
  .corner.br{ bottom:-1px; right:-1px; transform:scale(-1,-1); }
  .corner.tr path{ animation-delay:0.08s; }
  .corner.bl path{ animation-delay:0.16s; }
  .corner.br path{ animation-delay:0.24s; }
  @keyframes draw{ to{ stroke-dashoffset:0; } }
  @media (prefers-reduced-motion: reduce){ .corner path{ animation:none; stroke-dashoffset:0; } }

  .logo{ display:flex; justify-content:center; margin-bottom:28px; }
  .logo img{ height:38px; width:auto; }

  .back-link{
    display:inline-flex;
    align-items:center;
    gap:6px;
    font-size:12.5px;
    font-weight:600;
    color:var(--muted);
    margin-bottom:22px;
    padding-bottom:2px;
    border-bottom:1px solid transparent;
  }
  .back-link svg{ width:14px; height:14px; stroke:var(--muted); }
  .back-link:hover{ color:var(--frame-blue-deep); border-bottom-color:var(--amber); }
  .back-link:hover svg{ stroke:var(--frame-blue-deep); }

  .eyebrow-line{
    display:flex;
    align-items:center;
    justify-content:center;
    gap:10px;
    margin-bottom:16px;
  }
  .eyebrow-line .dash{ width:22px; height:1px; background:var(--alert); }
  .eyebrow-line span{ font-size:11.5px; letter-spacing:0.1em; color:var(--muted); }

  .card h1{
    font-family:'Space Grotesk',sans-serif;
    font-weight:600;
    font-size:22px;
    text-align:center;
    margin:0 0 28px;
  }

  .field{ margin-bottom:20px; }
  .field label{
    display:block;
    font-size:13px;
    font-weight:600;
    margin-bottom:7px;
  }
  .field input{
    width:100%;
    padding:12px 14px;
    background:var(--line-soft);
    border:1px solid transparent;
    border-radius:2px;
    font-family:'Inter',sans-serif;
    font-size:14px;
    color:var(--ink);
    outline:none;
    transition:border-color 0.2s ease, background 0.2s ease;
  }
  .field input:focus{ border-color:var(--frame-blue); background:var(--paper); }

  .field-meta{
    display:flex;
    justify-content:flex-end;
    margin:-10px 0 22px;
  }
  .field-meta a{
    font-size:12.5px;
    font-weight:600;
    color:var(--frame-blue-deep);
    border-bottom:1px solid transparent;
  }
  .field-meta a:hover{ border-bottom-color:var(--amber); color:var(--frame-blue); }

  .btn{
    display:flex;
    align-items:center;
    justify-content:center;
    width:100%;
    padding:13px 20px;
    font-size:14px;
    font-weight:600;
    border-radius:2px;
    border:1px solid transparent;
    cursor:pointer;
    background:var(--frame-blue-deep);
    color:var(--paper);
    transition:background 0.2s ease;
  }
  .btn:hover{ background:var(--frame-blue); }
  .btn:focus-visible{ outline:2px solid var(--frame-blue); outline-offset:2px; }

  .switch{
    text-align:center;
    font-size:13px;
    color:var(--muted);
    margin-top:24px;
  }
  .switch a{
    font-weight:600;
    color:var(--frame-blue-deep);
    border-bottom:1px solid transparent;
  }
  .switch a:hover{ border-bottom-color:var(--amber); color:var(--frame-blue); }

  .notice{
    background:#EAF3EC;
    border:1px solid #BEDCC4;
    color:#2C5E38;
    padding:12px 16px;
    font-size:13px;
    margin-bottom:22px;
  }
  .error-list{
    background:#FBEAEA;
    border:1px solid #E2B4B4;
    color:#7A2020;
    padding:12px 16px;
    font-size:13px;
    margin-bottom:22px;
  }
  .error-list ul{ margin:6px 0 0; padding-left:18px; }

  @media (max-width:480px){ .card{ padding:40px 26px 32px; } }
</style>
</head>
<body>

<div class="bg-photo" aria-hidden="true"></div>
<div class="bg-overlay" aria-hidden="true"></div>

<div class="card">
  <svg class="corner tl" viewBox="0 0 26 26"><path d="M1 14V1H14"/></svg>
  <svg class="corner tr" viewBox="0 0 26 26"><path d="M1 14V1H14"/></svg>
  <svg class="corner bl" viewBox="0 0 26 26"><path d="M1 14V1H14"/></svg>
  <svg class="corner br" viewBox="0 0 26 26"><path d="M1 14V1H14"/></svg>

  <a href="welcome.php" class="back-link">
    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M15 18l-6-6 6-6"/>
    </svg>
    Back to Welcome
  </a>

  <div class="logo">
    <img src="clearframe-logo.png" alt="Clear Frame Studio">
  </div>

  <div class="eyebrow-line">
    <span class="dash"></span>
    <span>RESTRICTED ACCESS</span>
    <span class="dash"></span>
  </div>
  <h1>Admin Login</h1>
  <p style="text-align:center; font-size:12.5px; color:var(--muted); margin:-18px 0 24px;">
    For Clear Frame Studio staff only.
  </p>

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

  <form method="post" novalidate>
    <?php echo csrf_field(); ?>
    <div class="field">
      <label for="identifier">Username or email</label>
      <input type="text" id="identifier" name="identifier" value="<?php echo htmlspecialchars($values['identifier']); ?>" required>
    </div>
    <div class="field">
      <label for="password">Password</label>
      <input type="password" id="password" name="password" required>
    </div>
    <button type="submit" class="btn">Login</button>
  </form>
</div>

</body>
</html>
