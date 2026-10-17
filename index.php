<?php
session_start();
$isLoggedIn = !empty($_SESSION['is_user']);
$userName   = $_SESSION['user_name'] ?? '';

$unreadCount = 0;
if ($isLoggedIn) {
    require 'db.php';
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM custom_requests WHERE user_id = ? AND has_unread_update = 1');
    $stmt->execute([$_SESSION['user_id']]);
    $unreadCount = (int) $stmt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Clear Frame Studio</title>
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
    --max:1160px;
  }

  *{box-sizing:border-box;}
  html{scroll-behavior:smooth;}
  body{
    margin:0;
    background:var(--paper);
    color:var(--ink);
    font-family:'Inter',sans-serif;
    -webkit-font-smoothing:antialiased;
  }

  a{color:inherit;text-decoration:none;}
  ul{list-style:none;margin:0;padding:0;}

  .wrap{
    max-width:var(--max);
    margin:0 auto;
    padding:0 32px;
  }

  /* ---------- Header ---------- */
  header{
    border-bottom:1px solid var(--line);
    background:var(--paper);
    position:sticky;
    top:0;
    z-index:20;
  }

  .header-inner{
    display:flex;
    align-items:center;
    justify-content:space-between;
    padding:18px 32px;
    max-width:var(--max);
    margin:0 auto;
  }

  .logo{
    display:flex;
    align-items:center;
  }

  .logo-img{
    height:44px;
    width:auto;
    display:block;
  }

  nav ul{
    display:flex;
    gap:40px;
  }

  nav a{
    font-size:13px;
    font-weight:600;
    letter-spacing:0.12em;
    color:var(--frame-blue-deep);
    position:relative;
    padding-bottom:6px;
  }

  nav a::after{
    content:"";
    position:absolute;
    left:0;
    bottom:0;
    width:100%;
    height:1.5px;
    background:var(--amber);
    transform:scaleX(0);
    transform-origin:left;
    transition:transform 0.25s ease;
  }

  nav a:hover::after,
  nav a:focus-visible::after{
    transform:scaleX(1);
  }

  nav a:focus-visible{
    outline:2px solid var(--frame-blue);
    outline-offset:4px;
  }

  nav a.nav-login{
    padding:9px 20px 8px;
    border:1px solid var(--frame-blue-deep);
    letter-spacing:0.04em;
  }
  nav a.nav-login::after{ display:none; }
  nav a.nav-login:hover{
    background:var(--frame-blue-deep);
    color:var(--paper);
  }

  .nav-toggle{
    display:none;
    background:none;
    border:1px solid var(--line);
    border-radius:4px;
    width:40px;
    height:36px;
    align-items:center;
    justify-content:center;
    cursor:pointer;
  }

  .nav-toggle span,
  .nav-toggle span::before,
  .nav-toggle span::after{
    content:"";
    display:block;
    width:18px;
    height:1.5px;
    background:var(--ink);
    position:relative;
  }
  .nav-toggle span::before{ position:absolute; top:-6px; }
  .nav-toggle span::after{ position:absolute; top:6px; }

  /* ---------- Hero ---------- */
  .hero{
    position:relative;
    padding:110px 32px 100px;
    overflow:hidden;
  }

  .hero-grid-bg{
    position:absolute;
    inset:0;
    background-image:radial-gradient(circle, var(--line) 1px, transparent 1px);
    background-size:28px 28px;
    -webkit-mask-image:linear-gradient(to bottom, transparent, black 15%, black 75%, transparent);
    mask-image:linear-gradient(to bottom, transparent, black 15%, black 75%, transparent);
    opacity:0.6;
  }

  /* Real photo behind the hero text, with a light wash on top so the
     dark heading/body text stays readable over any photo. */
  .hero-photo{
    position:absolute;
    inset:0;
    background:url('hero-bg.jpg') center/cover no-repeat;
  }
  .hero-photo::after{
    content:"";
    position:absolute;
    inset:0;
    background:linear-gradient(180deg,
      rgba(250,250,247,0.93) 0%,
      rgba(250,250,247,0.80) 45%,
      rgba(250,250,247,0.95) 100%);
  }

  .hero-frame{
    position:relative;
    max-width:var(--max);
    margin:0 auto;
    padding:64px 56px;
  }

  .corner{
    position:absolute;
    width:34px;
    height:34px;
    stroke:var(--frame-blue);
    stroke-width:1.6;
    fill:none;
  }
  .corner path{
    stroke-dasharray:70;
    stroke-dashoffset:70;
    animation:draw 0.9s ease forwards;
  }
  .corner.tl{ top:0; left:0; }
  .corner.tr{ top:0; right:0; transform:scaleX(-1); }
  .corner.bl{ bottom:0; left:0; transform:scaleY(-1); }
  .corner.br{ bottom:0; right:0; transform:scale(-1,-1); }
  .corner.tr path{ animation-delay:0.08s; }
  .corner.bl path{ animation-delay:0.16s; }
  .corner.br path{ animation-delay:0.24s; }

  @keyframes draw{
    to{ stroke-dashoffset:0; }
  }

  @media (prefers-reduced-motion: reduce){
    .corner path{ animation:none; stroke-dashoffset:0; }
  }

  .eyebrow-line{
    display:flex;
    align-items:center;
    gap:12px;
    margin-bottom:28px;
  }
  .eyebrow-line .dash{
    width:28px;
    height:1px;
    background:var(--amber);
  }
  .eyebrow-line span{
    font-size:13px;
    color:var(--muted);
    letter-spacing:0.04em;
  }

  .hero p.tagline{
    font-family:'Space Grotesk',sans-serif;
    font-style:italic;
    font-weight:500;
    font-size:15px;
    color:var(--frame-blue);
    margin:0 0 18px;
  }

  .hero h1{
    font-family:'Space Grotesk',sans-serif;
    font-weight:600;
    font-size:clamp(34px, 5.2vw, 60px);
    line-height:1.08;
    margin:0 0 26px;
    max-width:18ch;
    color:var(--ink);
  }

  .hero p.lede{
    font-size:17px;
    line-height:1.65;
    color:var(--muted);
    max-width:46ch;
    margin:0 0 40px;
  }

  .cta-row{
    display:flex;
    gap:16px;
    flex-wrap:wrap;
  }

  .btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    padding:14px 28px;
    font-size:14px;
    font-weight:600;
    border-radius:2px;
    border:1px solid transparent;
    cursor:pointer;
    transition:background 0.2s ease, color 0.2s ease, border-color 0.2s ease;
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
    outline-offset:3px;
  }

  /* ---------- Products ---------- */
  .products-detail{
    position:relative;
    padding:90px 32px 20px;
    border-top:1px solid var(--line);
    overflow:hidden;
  }
  .products-photo{
    position:absolute;
    inset:0;
    background:url('section-bg.jpg') center/cover no-repeat;
  }
  .products-photo::after{
    content:"";
    position:absolute;
    inset:0;
    background:linear-gradient(180deg,
      rgba(250,250,247,0.90) 0%,
      rgba(250,250,247,0.72) 50%,
      rgba(250,250,247,0.90) 100%);
  }
  .products-detail-inner{
    position:relative;
    max-width:var(--max);
    margin:0 auto;
  }
  .products-detail-eyebrow{
    display:block;
    font-size:12px;
    font-weight:600;
    letter-spacing:0.1em;
    color:var(--frame-blue);
    margin-bottom:32px;
  }
  .products-detail-grid{
    display:grid;
    grid-template-columns:repeat(3, 1fr);
    gap:1px;
    background:var(--line);
    border:1px solid var(--line);
  }
  .products-detail-card{
    position:relative;
    background:var(--paper);
    padding:34px 30px;
  }
  .products-detail-card .tick{
    position:absolute;
    top:20px;
    left:0;
    width:14px;
    height:1.5px;
    background:var(--amber);
  }
  .products-detail-card h3{
    font-family:'Space Grotesk',sans-serif;
    font-weight:600;
    font-size:18px;
    margin:24px 0 14px;
  }
  .products-detail-card p{
    font-size:13.5px;
    line-height:1.65;
    color:var(--muted);
    margin:0 0 12px;
  }
  .products-detail-card p:last-child{ margin-bottom:0; }

  @media (max-width:860px){
    .products-detail-grid{ grid-template-columns:1fr; }
  }

  .service{
    position:relative;
    padding:0 32px 100px;
    overflow:hidden;
  }
  .service-photo{
    position:absolute;
    inset:0;
    background:url('section-bg.jpg') center/cover no-repeat;
  }
  .service-photo::after{
    content:"";
    position:absolute;
    inset:0;
    background:linear-gradient(180deg,
      rgba(250,250,247,0.90) 0%,
      rgba(250,250,247,0.72) 50%,
      rgba(250,250,247,0.90) 100%);
  }
  .service-inner{
    position:relative;
    max-width:var(--max);
    margin:0 auto;
  }
  .service-panel{
    position:relative;
    max-width:720px;
    padding:52px 56px;
    border:1px solid var(--line);
    background:var(--paper);
  }
  .service-eyebrow{
    display:block;
    font-size:12px;
    font-weight:600;
    letter-spacing:0.1em;
    color:var(--frame-blue);
    margin-bottom:14px;
  }
  .service-panel h2{
    font-family:'Space Grotesk',sans-serif;
    font-weight:600;
    font-size:26px;
    margin:0 0 22px;
  }
  .service-panel p{
    font-size:15px;
    line-height:1.7;
    color:var(--muted);
    margin:0 0 18px;
    max-width:58ch;
  }
  .service-panel .btn{
    margin-top:12px;
  }
  @media (max-width:600px){
    .service-panel{ padding:36px 26px; }
  }

  /* ---------- Footer ---------- */
  footer{
    border-top:1px solid var(--line);
    padding:36px 32px;
  }

  .footer-inner{
    max-width:var(--max);
    margin:0 auto;
    display:flex;
    justify-content:space-between;
    align-items:center;
    flex-wrap:wrap;
    gap:16px;
  }

  .logo-img-footer{ height:32px; }

  footer .fine{
    font-size:12px;
    color:var(--muted);
  }

  /* ---------- Responsive ---------- */
  @media (max-width: 860px){
    .hero-frame{ padding:48px 24px; }
  }

  @media (max-width: 720px){
    nav{
      position:absolute;
      top:100%;
      left:0;
      right:0;
      background:var(--paper);
      border-bottom:1px solid var(--line);
      display:none;
      padding:20px 32px 28px;
    }
    nav.open{ display:block; }
    nav ul{ flex-direction:column; gap:20px; }
    .nav-toggle{ display:flex; }
    header{ position:relative; }
  }
</style>
</head>
<body>

<header>
  <div class="header-inner">
    <a href="#top" class="logo" aria-label="Clear Frame Studio home">
      <img src="clearframe-logo.png" alt="Clear Frame Studio" class="logo-img">
    </a>

    <button class="nav-toggle" id="navToggle" aria-label="Toggle menu" aria-expanded="false" aria-controls="mainNav">
      <span></span>
    </button>

    <nav id="mainNav">
      <ul>
        <li><a href="#top">HOME</a></li>
        <li><a href="discover.php">DISCOVER</a></li>
        <li><a href="faq.php">FAQ</a></li>
        <?php if ($isLoggedIn): ?>
          <li><a href="my-orders.php">MY ORDERS</a></li>
          <li><a href="my-requests.php">MY REQUESTS<?php if ($unreadCount > 0): ?><span style="display:inline-flex; align-items:center; justify-content:center; min-width:16px; height:16px; padding:0 4px; margin-left:6px; border-radius:999px; background:#9B3B3B; color:#fff; font-size:10px; font-weight:700; letter-spacing:0;"><?php echo $unreadCount; ?></span><?php endif; ?></a></li>
          <li><a href="logout.php" class="nav-login">Logout (<?php echo htmlspecialchars($userName); ?>)</a></li>
        <?php else: ?>
          <li><a href="login.php" class="nav-login">Login</a></li>
        <?php endif; ?>
      </ul>
    </nav>
  </div>
</header>

<section class="hero" id="top">
  <div class="hero-photo" aria-hidden="true"></div>
  <div class="hero-grid-bg" aria-hidden="true"></div>

  <div class="hero-frame">
    <svg class="corner tl" viewBox="0 0 34 34"><path d="M1 20 V1 H20"/></svg>
    <svg class="corner tr" viewBox="0 0 34 34"><path d="M1 20 V1 H20"/></svg>
    <svg class="corner bl" viewBox="0 0 34 34"><path d="M1 20 V1 H20"/></svg>
    <svg class="corner br" viewBox="0 0 34 34"><path d="M1 20 V1 H20"/></svg>

    <div class="eyebrow-line">
      <span class="dash"></span>
      <span>Digital design assets for creators &amp; teams</span>
    </div>

    <p class="tagline">&ldquo;Visual Assets &amp; Content, Crafted for Clarity.&rdquo;</p>

    <h1>Clear, Professional Visuals for Modern Content</h1>

    <p class="lede">
      Icons, infographics, PowerPoint templates &amp; content
      solutions — crafted for clarity.
    </p>

    <div class="cta-row">
      <a href="discover.php" class="btn btn-primary">Browse the shop</a>
      <a href="faq.php" class="btn btn-secondary">How it works</a>
    </div>
  </div>
</section>

<section class="products-detail">
  <div class="products-photo" aria-hidden="true"></div>
  <div class="products-detail-inner">
    <span class="products-detail-eyebrow">CLEARFRAME STUDIO PRODUCTS</span>

    <div class="products-detail-grid">
      <article class="products-detail-card">
        <span class="tick"></span>
        <h3>PowerPoint Templates</h3>
        <p>A versatile PowerPoint template designed for professionals who need clarity, structure, and visual consistency in every presentation.</p>
        <p>Built with practical layouts and flexible components, this template supports storytelling without overwhelming your content.</p>
      </article>

      <article class="products-detail-card">
        <span class="tick"></span>
        <h3>Icons &amp; Visual Assets</h3>
        <p>A clean, professional icon set designed to support clear communication across presentations, reports, and digital content.</p>
        <p>This collection focuses on simplicity and consistency, making it easy to enhance your slides without distracting from your message.</p>
      </article>

      <article class="products-detail-card">
        <span class="tick"></span>
        <h3>Infographics</h3>
        <p>A set of structured infographic slides designed to turn complex information into clear, visual stories.</p>
        <p>Each layout is built to guide attention, highlight key points, and support confident communication — whether you're presenting data, processes, or ideas.</p>
      </article>
    </div>
  </div>
</section>

<section class="service">
  <div class="service-photo" aria-hidden="true"></div>
  <div class="service-inner">
    <div class="service-panel">
      <svg class="corner tl" viewBox="0 0 34 34"><path d="M1 20 V1 H20"/></svg>
      <svg class="corner tr" viewBox="0 0 34 34"><path d="M1 20 V1 H20"/></svg>
      <svg class="corner bl" viewBox="0 0 34 34"><path d="M1 20 V1 H20"/></svg>
      <svg class="corner br" viewBox="0 0 34 34"><path d="M1 20 V1 H20"/></svg>

      <span class="service-eyebrow">CLEARFRAME STUDIO SERVICE</span>
      <h2>Content Development</h2>

      <p>Clear Frame Studio offers structured content development for presentations, learning materials, and professional documents.</p>
      <p>This service is designed for individuals and teams who need clear messaging, logical flow, and well-organised content — without starting from a blank page.</p>
      <p>Whether you're building a presentation, training deck, or report, we help shape your ideas into content that communicates with clarity and confidence.</p>
    </div>
  </div>
</section>

<footer>
  <div class="footer-inner">
    <a href="#top" class="logo" aria-label="Clear Frame Studio home">
      <img src="clearframe-logo.png" alt="Clear Frame Studio" class="logo-img logo-img-footer">
    </a>
    <p class="fine">&copy; 2026 Clear Frame Studio. All templates for personal and commercial use.</p>
  </div>
</footer>

<script>
  const toggle = document.getElementById('navToggle');
  const nav = document.getElementById('mainNav');
  toggle.addEventListener('click', () => {
    const isOpen = nav.classList.toggle('open');
    toggle.setAttribute('aria-expanded', String(isOpen));
  });
  nav.querySelectorAll('a').forEach(link => {
    link.addEventListener('click', () => {
      nav.classList.remove('open');
      toggle.setAttribute('aria-expanded', 'false');
    });
  });
</script>

</body>
</html>
