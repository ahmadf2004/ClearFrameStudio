<?php
session_start();
$isLoggedIn = !empty($_SESSION['is_user']);
$userName   = $_SESSION['user_name'] ?? '';

$faqs = [
    [
        'q' => 'What software do I need to use your products?',
        'a' => "All templates and visual assets are designed for ease of use. Our products are compatible with PowerPoint, and many assets also work with Google Slides and Canva (where stated in the product description). No advanced design skills are required.",
    ],
    [
        'q' => 'Are the files editable?',
        'a' => "Yes. All icons, slides, and infographic layouts are fully editable so you can customise colours, text, and layout to match your content and branding.",
    ],
    [
        'q' => 'Can I use these for client or commercial projects?',
        'a' => "Yes. All products come with personal and commercial use rights. You may use them in presentations, reports, training materials, and client work. Redistribution or resale of the files as-is is not permitted.",
    ],
    [
        'q' => "I'm not a designer. Will this still work for me?",
        'a' => "Absolutely. ClearFrame Studio products are created for clarity and practicality, not designers only. Layouts are structured, intuitive, and presentation-ready — just replace the content and go.",
    ],
    [
        'q' => 'Do you offer refunds?',
        'a' => "Due to the digital nature of our products, all sales are final. If you experience any issues accessing or using your files, feel free to reach out and we'll be happy to help.",
    ],
    [
        'q' => 'Will these work for corporate or professional presentations?',
        'a' => "Yes. Our designs are intentionally clean, minimal, and professional, making them suitable for corporate, educational, and business environments.",
    ],
    [
        'q' => 'How will I receive my files?',
        'a' => "Once your purchase is complete, your files will be available for instant download via Payhip. You'll also receive an email with your download link.",
    ],
    [
        'q' => 'Do you offer custom content or design services?',
        'a' => "Yes. We provide content development services, including structured presentation content and tailored materials. You can find service details in our store or contact us for custom requests.",
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>FAQ — Clear Frame Studio</title>
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
  body{
    margin:0;
    background:var(--paper);
    color:var(--ink);
    font-family:'Inter',sans-serif;
    -webkit-font-smoothing:antialiased;
  }
  a{color:inherit;text-decoration:none;}

  .wrap{ max-width:var(--max); margin:0 auto; padding:0 32px; }

  /* ---------- Header ---------- */
  header{
    border-bottom:1px solid var(--line);
    background:var(--paper);
    position:sticky;
    top:0;
    z-index:20;
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
  .logo-img{ height:44px; width:auto; display:block; }
  .logo-img-footer{ height:30px; }

  .back-home{
    display:flex;
    align-items:center;
    gap:6px;
    font-size:13px;
    font-weight:600;
    color:var(--frame-blue-deep);
    white-space:nowrap;
    padding-bottom:2px;
    border-bottom:1px solid transparent;
  }
  .back-home svg{ width:15px; height:15px; stroke:var(--frame-blue-deep); }
  .back-home:hover{ border-bottom-color:var(--amber); color:var(--frame-blue); }

  /* ---------- Page head ---------- */
  .page-head{
    max-width:var(--max);
    margin:0 auto;
    padding:64px 32px 8px;
  }
  .eyebrow-line{
    display:flex;
    align-items:center;
    gap:12px;
    margin-bottom:22px;
  }
  .eyebrow-line .dash{ width:28px; height:1px; background:var(--amber); }
  .eyebrow-line span{ font-size:13px; color:var(--muted); }

  .page-head h1{
    font-family:'Space Grotesk',sans-serif;
    font-weight:600;
    font-size:clamp(28px, 4vw, 40px);
    margin:0 0 14px;
  }
  .page-head p{
    font-size:15px;
    color:var(--muted);
    max-width:56ch;
    line-height:1.65;
    margin:0;
  }

  /* ---------- FAQ list ---------- */
  .faq-section{
    max-width:var(--max);
    margin:0 auto;
    padding:40px 32px 100px;
  }

  .faq-list{
    max-width:820px;
    border:1px solid var(--line);
  }

  details{
    border-bottom:1px solid var(--line);
    position:relative;
  }
  details:last-child{ border-bottom:none; }

  details summary{
    list-style:none;
    cursor:pointer;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:20px;
    padding:22px 26px;
    font-family:'Space Grotesk',sans-serif;
    font-weight:600;
    font-size:15.5px;
    color:var(--ink);
  }
  details summary::-webkit-details-marker{ display:none; }

  details summary .qnum{
    font-family:'Inter',sans-serif;
    font-weight:600;
    font-size:12px;
    color:var(--frame-blue);
    margin-right:14px;
  }

  details summary .chev{
    flex-shrink:0;
    width:18px;
    height:18px;
    stroke:var(--muted);
    transition:transform 0.2s ease;
  }
  details[open] summary .chev{ transform:rotate(180deg); stroke:var(--frame-blue); }

  details[open] summary{ border-bottom:1px solid var(--line-soft); }

  .faq-answer{
    padding:0 26px 24px 26px;
    font-size:14px;
    line-height:1.7;
    color:var(--muted);
    max-width:64ch;
  }
  .faq-answer p{ margin:0; padding-left:36px; }

  details summary:hover{ background:var(--line-soft); }
  details summary:focus-visible{
    outline:2px solid var(--frame-blue);
    outline-offset:-2px;
  }

  /* ---------- Footer ---------- */
  footer{ border-top:1px solid var(--line); padding:32px; }
  .footer-inner{
    max-width:var(--max);
    margin:0 auto;
    display:flex;
    justify-content:space-between;
    align-items:center;
    flex-wrap:wrap;
    gap:16px;
  }
  footer .fine{ font-size:12px; color:var(--muted); }

  /* ---------- Responsive ---------- */
  @media (max-width:600px){
    details summary{ padding:18px 18px; font-size:14.5px; }
    .faq-answer{ padding:0 18px 20px; }
    .faq-answer p{ padding-left:0; }
  }
</style>
</head>
<body>

<header>
  <div class="header-inner">
    <a href="index.php" class="logo" aria-label="Clear Frame Studio home">
      <img src="clearframe-logo.png" alt="Clear Frame Studio" class="logo-img">
    </a>
    <a href="index.php" class="back-home">
      <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M15 18l-6-6 6-6"/>
      </svg>
      <span>Back to Home</span>
    </a>
  </div>
</header>

<div class="page-head">
  <div class="eyebrow-line">
    <span class="dash"></span>
    <span>GOT QUESTIONS?</span>
  </div>
  <h1>Frequently Asked Questions</h1>
  <p>Everything you need to know before you buy — software compatibility, usage rights, delivery, and more.</p>
</div>

<div class="faq-section">
  <div class="faq-list">
    <?php foreach ($faqs as $i => $faq): ?>
      <details<?php echo $i === 0 ? ' open' : ''; ?>>
        <summary>
          <span><span class="qnum"><?php echo str_pad($i + 1, 2, '0', STR_PAD_LEFT); ?></span><?php echo htmlspecialchars($faq['q']); ?></span>
          <svg class="chev" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M6 9l6 6 6-6"/>
          </svg>
        </summary>
        <div class="faq-answer">
          <p><?php echo htmlspecialchars($faq['a']); ?></p>
        </div>
      </details>
    <?php endforeach; ?>
  </div>
</div>

<footer>
  <div class="footer-inner">
    <a href="index.php" class="logo" aria-label="Clear Frame Studio home">
      <img src="clearframe-logo.png" alt="Clear Frame Studio" class="logo-img logo-img-footer">
    </a>
    <p class="fine">&copy; 2026 Clear Frame Studio. All templates for personal and commercial use.</p>
  </div>
</footer>

</body>
</html>
