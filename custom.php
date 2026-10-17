<?php
session_start();
require 'db.php';
require 'csrf.php';

$errors = [];
$submitted = false;
$from = ($_GET['from'] ?? $_POST['from'] ?? '') === 'cart' ? 'cart' : '';
$values = [
    'name' => '',
    'email' => '',
    'product_type' => '',
    'request' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $values['name']         = trim($_POST['name'] ?? '');
    $values['email']        = trim($_POST['email'] ?? '');
    $values['product_type'] = trim($_POST['product_type'] ?? '');
    $values['request']      = trim($_POST['request'] ?? '');

    if ($values['name'] === '') {
        $errors[] = 'Please enter your name.';
    }
    if ($values['email'] === '' || !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if ($values['request'] === '') {
        $errors[] = 'Please describe your request.';
    }

    // Optional file upload
    $storedFileName = null;
    if (!empty($_FILES['reference_file']['name'])) {
        $tmpPath = $_FILES['reference_file']['tmp_name'];

        // Detect the file's ACTUAL type from its content (magic bytes),
        // not from the filename the browser sent — a renamed
        // "shell.php.jpg" or similar can't fake this the way it could
        // fake a pathinfo() extension check.
        //
        // pptx/docx are ZIP containers under the hood, so PHP's fileinfo
        // sometimes reports them as plain application/zip instead of the
        // specific Office MIME type — both are accepted for those two.
        $allowedTypes = [
            'pdf'  => ['application/pdf'],
            'png'  => ['image/png'],
            'jpg'  => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        ];

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMime = finfo_file($finfo, $tmpPath);
        finfo_close($finfo);

        // Match the DECLARED extension against what the content actually is.
        // (Taking "the first extension whose list contains the detected type"
        // would store a DOCX as .pptx whenever fileinfo reports plain
        // application/zip, since pptx is listed before docx.)
        $declaredExt = strtolower(pathinfo($_FILES['reference_file']['name'], PATHINFO_EXTENSION));
        $matchedExt = null;
        if (isset($allowedTypes[$declaredExt]) && in_array($detectedMime, $allowedTypes[$declaredExt], true)) {
            $matchedExt = $declaredExt;
        }

        if (!$matchedExt) {
            $errors[] = 'Reference file must be a genuine PDF, PNG/JPG image, PPTX, or DOCX file.';
        } elseif (empty($errors)) {
            $uploadDir = __DIR__ . '/uploads/custom-requests/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Defense in depth: even if a disguised file somehow passed
            // the MIME check above, this .htaccess stops Apache from
            // ever executing anything in this folder as PHP.
            $htaccessPath = $uploadDir . '.htaccess';
            if (!file_exists($htaccessPath)) {
                file_put_contents($htaccessPath,
                    "<IfModule mod_php.c>\n    php_flag engine off\n</IfModule>\n" .
                    "<IfModule mod_php8.c>\n    php_flag engine off\n</IfModule>\n" .
                    "<FilesMatch \"\\.(php|phtml|php3|php4|php5|phar)$\">\n" .
                    "    Require all denied\n" .
                    "</FilesMatch>\n"
                );
            }

            // The stored extension is one of a fixed whitelist AND must agree
            // with the file's detected content type; the rest of the stored
            // name is generated, never taken from the user's filename. That
            // combination is what closes the double-extension bypass.
            $storedFileName = uniqid('req_', true) . '.' . $matchedExt;
            if (!move_uploaded_file($tmpPath, $uploadDir . $storedFileName)) {
                $errors[] = 'Something went wrong uploading your file. Please try again.';
                $storedFileName = null;
            }
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'INSERT INTO custom_requests (user_id, name, email, product_type, request_text, reference_file)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $_SESSION['user_id'] ?? null, // null for guest submissions — they just won't show in a dashboard
            $values['name'],
            $values['email'],
            $values['product_type'],
            $values['request'],
            $storedFileName,
        ]);
        $submitted = true;
        $newRequestId = $pdo->lastInsertId();

        if ($from === 'cart') {
            // Part of the cart -> custom request -> checkout flow: hand
            // this request's id to checkout.php so the order it creates
            // can be linked back to it (see orders.custom_request_id).
            // Skip this page's own "thanks" panel and continue straight
            // on to Checkout now that the request is on file.
            $_SESSION['pending_custom_request_id'] = $newRequestId;
            header('Location: checkout.php');
            exit;
        }
    }
}

$isLoggedIn = !empty($_SESSION['is_user']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Custom Request — Clear Frame Studio</title>
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

  /* ---------- Form panel ---------- */
  .form-section{
    max-width:var(--max);
    margin:0 auto;
    padding:40px 32px 100px;
  }

  .form-panel{
    position:relative;
    border:1px solid var(--line);
    background:var(--paper);
    padding:44px;
    max-width:760px;
  }

  .corner{
    position:absolute;
    width:22px;
    height:22px;
    stroke:var(--frame-blue);
    stroke-width:1.5;
    fill:none;
  }
  .corner.tl{ top:-1px; left:-1px; }
  .corner.tr{ top:-1px; right:-1px; transform:scaleX(-1); }
  .corner.bl{ bottom:-1px; left:-1px; transform:scaleY(-1); }
  .corner.br{ bottom:-1px; right:-1px; transform:scale(-1,-1); }

  .field{
    margin-bottom:26px;
  }

  .field label{
    display:block;
    font-size:13px;
    font-weight:600;
    color:var(--ink);
    margin-bottom:8px;
  }

  .field .hint{
    font-size:12px;
    color:var(--muted);
    margin-top:6px;
  }

  .field input[type="text"],
  .field input[type="email"],
  .field select,
  .field textarea{
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

  .field input:focus,
  .field select:focus,
  .field textarea:focus{
    border-color:var(--frame-blue);
    background:var(--paper);
  }

  .field textarea{
    resize:vertical;
    min-height:220px;
    line-height:1.6;
  }

  .field-row{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:20px;
  }

  .file-drop{
    display:flex;
    align-items:center;
    gap:12px;
    padding:12px 14px;
    background:var(--line-soft);
    border:1px dashed var(--line);
    font-size:13px;
    color:var(--muted);
  }

  .file-drop input[type="file"]{
    font-size:13px;
    max-width:100%;
  }

  .btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    padding:14px 30px;
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
  .btn:focus-visible{ outline:2px solid var(--frame-blue); outline-offset:3px; }

  .error-list{
    background:#FBEAEA;
    border:1px solid #E2B4B4;
    color:#7A2020;
    padding:16px 20px;
    font-size:13px;
    margin-bottom:26px;
  }
  .error-list ul{ margin:6px 0 0; padding-left:18px; }

  .confirm{
    max-width:760px;
    padding:44px;
    border:1px solid var(--line);
    text-align:left;
  }
  .confirm .tag{
    font-size:12px;
    font-weight:600;
    letter-spacing:0.1em;
    color:var(--frame-blue);
    display:block;
    margin-bottom:10px;
  }
  .confirm h2{
    font-family:'Space Grotesk',sans-serif;
    font-size:24px;
    margin:0 0 12px;
  }
  .confirm p{
    color:var(--muted);
    font-size:14px;
    line-height:1.6;
    margin:0 0 24px;
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
  @media (max-width: 720px){
    .form-panel, .confirm{ padding:28px; }
    .field-row{ grid-template-columns:1fr; }
    .back-home span{ display:none; }
  }
</style>
</head>
<body>

<header>
  <div class="header-inner">
    <a href="index.php" class="logo" aria-label="Clear Frame Studio home">
      <img src="clearframe-logo.png" alt="Clear Frame Studio" class="logo-img">
    </a>
    <a href="<?php echo $from === 'cart' ? 'cart.php' : 'index.php'; ?>" class="back-home">
      <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M15 18l-6-6 6-6"/>
      </svg>
      <span>Back to <?php echo $from === 'cart' ? 'Cart' : 'Home'; ?></span>
    </a>
  </div>
</header>

<div class="page-head">
  <div class="eyebrow-line">
    <span class="dash"></span>
    <span><?php echo $from === 'cart' ? 'ONE MORE STEP BEFORE CHECKOUT' : 'Not seeing what you need?'; ?></span>
  </div>
  <?php if ($from === 'cart'): ?>
    <h1>Tell us about your order</h1>
    <p>Before you check out, let us know a bit about what this is for —
       it helps us prep the right files and reach you if anything about
       your order needs a follow-up.</p>
  <?php else: ?>
    <h1>Custom your Request</h1>
    <p>Tell us what you're trying to build — a deck, a Canva kit, a training
       module, or something in between — and we'll follow up with pricing
       and a timeline.</p>
  <?php endif; ?>
</div>

<div class="form-section">

  <?php if ($submitted): ?>

    <div class="confirm">
      <span class="tag">REQUEST RECEIVED</span>
      <h2>Thanks, <?php echo htmlspecialchars($values['name']); ?>.</h2>
      <p>We've got your request and will reply at
         <?php echo htmlspecialchars($values['email']); ?> within 1–2 business days
         with pricing and next steps.</p>
      <?php if ($isLoggedIn): ?>
        <p style="margin-top:-8px;">You can track its status any time from
           <a href="my-requests.php" style="font-weight:600; color:var(--frame-blue-deep);">My Requests</a>.</p>
      <?php else: ?>
        <p style="margin-top:-8px; font-size:13px; color:var(--muted);">
          Tip: <a href="login.php" style="font-weight:600; color:var(--frame-blue-deep);">log in</a>
          or <a href="register.php" style="font-weight:600; color:var(--frame-blue-deep);">create an account</a>
          next time to track requests like this one from a dashboard.
        </p>
      <?php endif; ?>
      <a href="index.php" class="btn">Back to Home</a>
    </div>

  <?php else: ?>

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

    <form class="form-panel" method="post" enctype="multipart/form-data" novalidate>
      <?php echo csrf_field(); ?>
      <input type="hidden" name="from" value="<?php echo htmlspecialchars($from); ?>">
      <svg class="corner tl" viewBox="0 0 22 22"><path d="M1 12V1H12"/></svg>
      <svg class="corner tr" viewBox="0 0 22 22"><path d="M1 12V1H12"/></svg>
      <svg class="corner bl" viewBox="0 0 22 22"><path d="M1 12V1H12"/></svg>
      <svg class="corner br" viewBox="0 0 22 22"><path d="M1 12V1H12"/></svg>

      <div class="field-row">
        <div class="field">
          <label for="name">Your name</label>
          <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($values['name']); ?>" required>
        </div>
        <div class="field">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($values['email']); ?>" required>
        </div>
      </div>

      <div class="field">
        <label for="product_type">What kind of product is this?</label>
        <select id="product_type" name="product_type">
          <option value="">Select one</option>
          <?php
            $options = ['PowerPoint Template', 'Canva Template', 'E-module', 'Icon Set', 'Something else'];
            foreach ($options as $opt):
              $selected = ($values['product_type'] === $opt) ? 'selected' : '';
          ?>
            <option value="<?php echo htmlspecialchars($opt); ?>" <?php echo $selected; ?>>
              <?php echo htmlspecialchars($opt); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field">
        <label for="request">Describe your request</label>
        <textarea id="request" name="request" placeholder="What's this for, how many slides or modules, any style references, and when do you need it by?" required><?php echo htmlspecialchars($values['request']); ?></textarea>
      </div>

      <div class="field">
        <label for="reference_file">Reference file (optional)</label>
        <div class="file-drop">
          <input type="file" id="reference_file" name="reference_file" accept=".pdf,.png,.jpg,.jpeg,.pptx,.docx">
        </div>
        <p class="hint">PDF, image, PPTX, or DOCX — up to your server's normal upload limit.</p>
      </div>

      <button type="submit" class="btn"><?php echo $from === 'cart' ? 'Submit & Continue to Checkout' : 'Submit request'; ?></button>
    </form>

  <?php endif; ?>

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
