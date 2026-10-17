<?php
require 'session-init.php';
require 'db.php';
require 'csrf.php';
require 'firebase-config.php';

$errors = [];
$values = ['name' => '', 'email' => ''];

// Redirected back here from google-login.php after a failed Google
// sign-in attempt started from this page — see that file for why the
// message stays generic.
if (isset($_GET['google_error'])) {
    $errors[] = 'Google sign-in failed. Please try again, or register with your email and password.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $values['name']  = trim($_POST['name'] ?? '');
    $values['email'] = trim($_POST['email'] ?? '');
    $password         = $_POST['password'] ?? '';
    $confirm          = $_POST['confirm_password'] ?? '';

    if ($values['name'] === '') {
        $errors[] = 'Please enter your full name.';
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
                'INSERT INTO users (full_name, email, password_hash) VALUES (?, ?, ?)'
            );
            $stmt->execute([
                $values['name'],
                $values['email'],
                password_hash($password, PASSWORD_DEFAULT),
            ]);
            header('Location: login.php?registered=1');
            exit;
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $errors[] = 'That email is already registered — try logging in instead.';
            } else {
                $errors[] = 'Something went wrong creating your account. Please try again.';
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
<title>Register — Clear Frame Studio</title>
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
  .eyebrow-line .dash{ width:22px; height:1px; background:var(--amber); }
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

  .divider{ display:flex; align-items:center; gap:14px; margin:22px 0; }
  .divider::before, .divider::after{ content:""; flex:1; height:1px; background:var(--line); }
  .divider span{ font-size:11px; letter-spacing:0.1em; color:var(--muted); }

  .btn-google{
    display:flex; align-items:center; justify-content:center; gap:10px;
    width:100%; padding:12px 20px; font-size:14px; font-weight:600;
    border-radius:2px; border:1px solid var(--line); cursor:pointer;
    background:var(--paper); color:var(--ink); font-family:'Inter',sans-serif;
  }
  .btn-google:hover{ border-color:var(--frame-blue); background:var(--line-soft); }
  .btn-google:disabled{ opacity:0.6; cursor:default; }

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
    <span>CREATE ACCOUNT</span>
    <span class="dash"></span>
  </div>
  <h1>Create your account</h1>

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
      <label for="name">Full name</label>
      <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($values['name']); ?>" required>
    </div>
    <div class="field">
      <label for="email">Email</label>
      <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($values['email']); ?>" required>
    </div>
    <div class="field">
      <label for="password">Password</label>
      <input type="password" id="password" name="password" required>
    </div>
    <div class="field" style="margin-bottom:26px;">
      <label for="confirm_password">Confirm password</label>
      <input type="password" id="confirm_password" name="confirm_password" required>
    </div>
    <button type="submit" class="btn">Register</button>
  </form>

  <div class="divider"><span>OR</span></div>

  <button type="button" class="btn btn-google" onclick="signInWithGoogle()" id="google-btn">
    <svg viewBox="0 0 48 48" width="18" height="18" aria-hidden="true"><path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.9 29.3 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.1 8 3l5.7-5.7C34.6 6 29.6 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.7-.4-3.5z"/><path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.6 16 19 13 24 13c3.1 0 5.8 1.1 8 3l5.7-5.7C34.6 6 29.6 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/><path fill="#4CAF50" d="M24 44c5.5 0 10.5-1.9 14.3-5.1l-6.6-5.6C29.6 35.3 26.9 36 24 36c-5.3 0-9.7-3.1-11.3-7.4l-6.6 5.1C9.6 39.6 16.3 44 24 44z"/><path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.3-2.3 4.3-4.2 5.7l6.6 5.6C40.1 36.6 44 30.9 44 24c0-1.3-.1-2.7-.4-3.5z"/></svg>
    Continue with Google
  </button>

  <form id="google-login-form" method="post" action="google-login.php" style="display:none;">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="return_to" value="register">
    <input type="hidden" name="id_token" id="google_id_token" value="">
  </form>

  <p class="switch">Already have an account? <a href="login.php">Login</a></p>
</div>

<script src="https://www.gstatic.com/firebasejs/10.12.2/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/10.12.2/firebase-auth-compat.js"></script>
<script>
  const firebaseConfig = {
    apiKey: "<?php echo addslashes(FIREBASE_API_KEY); ?>",
    authDomain: "<?php echo addslashes(FIREBASE_AUTH_DOMAIN); ?>",
    projectId: "<?php echo addslashes(FIREBASE_PROJECT_ID); ?>",
  };
  firebase.initializeApp(firebaseConfig);

  function signInWithGoogle() {
    const btn = document.getElementById('google-btn');
    btn.disabled = true;
    btn.textContent = 'Signing in…';

    const provider = new firebase.auth.GoogleAuthProvider();
    firebase.auth().signInWithPopup(provider)
      .then((result) => result.user.getIdToken())
      .then((idToken) => {
        document.getElementById('google_id_token').value = idToken;
        document.getElementById('google-login-form').submit();
      })
      .catch((error) => {
        console.error('Google sign-in error:', error);
        btn.disabled = false;
        btn.innerHTML = 'Continue with Google';
        if (error.code !== 'auth/popup-closed-by-user' && error.code !== 'auth/cancelled-popup-request') {
          alert('Google sign-in failed: ' + error.message);
        }
      });
  }
</script>

</body>
</html>
