<?php
// Entry point for auth actions. Wire these up once register/login/admin
// pages exist — for now the buttons just link to their expected pages.
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Welcome — Clear Frame Studio</title>
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

  /* ---------- Background layer ---------- */
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

  /* ---------- Card ---------- */
  .card{
    position:relative;
    z-index:1;
    width:100%;
    max-width:440px;
    background:var(--paper);
    border:1px solid var(--line);
    padding:56px 44px 48px;
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
  @media (prefers-reduced-motion: reduce){
    .corner path{ animation:none; stroke-dashoffset:0; }
  }

  .logo{
    display:flex;
    justify-content:center;
    margin-bottom:32px;
  }
  .logo img{ height:40px; width:auto; }

  .card h1{
    font-family:'Space Grotesk',sans-serif;
    font-weight:600;
    font-size:24px;
    line-height:1.3;
    text-align:center;
    margin:0 0 10px;
  }

  .card p.lede{
    text-align:center;
    font-size:13.5px;
    color:var(--muted);
    line-height:1.6;
    margin:0 0 34px;
  }

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
    transition:background 0.2s ease, color 0.2s ease, border-color 0.2s ease;
  }

  .btn-row{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:12px;
    margin-bottom:26px;
  }

  .btn-primary{
    background:var(--frame-blue-deep);
    color:var(--paper);
  }
  .btn-primary:hover{ background:var(--frame-blue); }

  .btn-secondary{
    background:transparent;
    color:var(--ink);
    border-color:var(--ink);
  }
  .btn-secondary:hover{
    border-color:var(--frame-blue);
    color:var(--frame-blue);
  }

  .btn:focus-visible{
    outline:2px solid var(--frame-blue);
    outline-offset:2px;
  }

  .divider{
    display:flex;
    align-items:center;
    gap:14px;
    margin-bottom:22px;
  }
  .divider::before,
  .divider::after{
    content:"";
    flex:1;
    height:1px;
    background:var(--line);
  }
  .divider span{
    font-size:11px;
    letter-spacing:0.1em;
    color:var(--muted);
  }

  .admin-link{
    display:block;
    text-align:center;
    font-size:12.5px;
    font-weight:600;
    color:var(--muted);
    padding-bottom:2px;
    border-bottom:1px solid transparent;
  }
  .admin-link:hover{
    color:var(--frame-blue-deep);
    border-bottom-color:var(--amber);
  }

  @media (max-width:480px){
    .card{ padding:44px 26px 36px; }
  }
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

  <div class="logo">
    <img src="clearframe-logo.png" alt="Clear Frame Studio">
  </div>

  <h1>Welcome to Clear Frame Studio</h1>
  <p class="lede">Sign in to manage your templates and orders, or create an account to get started.</p>

  <div class="btn-row">
    <a href="register.php" class="btn btn-primary">Register</a>
    <a href="login.php" class="btn btn-secondary">Login</a>
  </div>

  <div class="divider"><span>STAFF ONLY</span></div>

  <a href="admin-login.php" class="admin-link">Admin Login</a>
</div>

</body>
</html>
