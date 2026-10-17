<?php
session_start();
require 'db.php';
require 'csrf.php';

$productId = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product) {
    http_response_code(404);
}

$gallery = [];
if ($product) {
    $stmt = $pdo->prepare('SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order ASC, id ASC');
    $stmt->execute([$productId]);
    $gallery = $stmt->fetchAll();
}

$categoryLabels = [
    'powerpoint'  => 'PowerPoint',
    'infographic' => 'Infographic',
    'icon'        => 'Icon',
];

$initialImage = $product ? ($product['image_path'] ?: ($gallery[0]['image_path'] ?? null)) : null;
$inCart = $product && in_array($product['id'], $_SESSION['cart'] ?? [], true);
$cartCount = count($_SESSION['cart'] ?? []);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $product ? htmlspecialchars($product['name']) . ' — ' : ''; ?>Clear Frame Studio</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<style>
  :root{
    --paper:#FAFAF7; --ink:#1C2530; --muted:#6B7480;
    --frame-blue:#3E5C76; --frame-blue-deep:#26384A; --amber:#E8A13D;
    --line:#D8DBDE; --line-soft:#E7E9EB; --max:1160px;
  }
  *{box-sizing:border-box;}
  body{ margin:0; background:var(--paper); color:var(--ink); font-family:'Inter',sans-serif; -webkit-font-smoothing:antialiased; }
  a{ color:inherit; text-decoration:none; }

  header{ border-bottom:1px solid var(--line); position:sticky; top:0; background:var(--paper); z-index:20; }
  .header-inner{ max-width:var(--max); margin:0 auto; padding:18px 32px; display:flex; align-items:center; justify-content:space-between; }
  .logo-img{ height:40px; width:auto; display:block; }
  .back-home{ display:flex; align-items:center; gap:6px; font-size:13px; font-weight:600; color:var(--frame-blue-deep); }
  .back-home svg{ width:15px; height:15px; stroke:var(--frame-blue-deep); }
  .cart-btn{
    display:flex; align-items:center; gap:8px; padding:10px 16px; border:1px solid var(--line); border-radius:2px;
    font-size:13px; font-weight:600; color:var(--frame-blue-deep); background:var(--paper);
  }
  .cart-btn:hover{ border-color:var(--frame-blue); }
  .cart-count{
    background:var(--amber); color:var(--frame-blue-deep); font-size:11px; font-weight:700;
    border-radius:50%; width:18px; height:18px; display:flex; align-items:center; justify-content:center;
  }

  .back-link{
    display:inline-flex; align-items:center; gap:6px; font-size:12.5px; font-weight:600;
    color:var(--muted); margin:28px 0 0 32px; padding-bottom:2px; border-bottom:1px solid transparent;
  }
  .back-link svg{ width:14px; height:14px; stroke:var(--muted); }
  .back-link:hover{ color:var(--frame-blue-deep); border-bottom-color:var(--amber); }

  .not-found{ max-width:var(--max); margin:0 auto; padding:100px 32px; text-align:center; }
  .not-found h1{ font-family:'Space Grotesk',sans-serif; font-size:28px; margin:0 0 12px; }
  .not-found p{ color:var(--muted); font-size:14px; margin:0 0 24px; }

  .section{
    max-width:var(--max); margin:0 auto; padding:24px 32px 100px;
    display:grid; grid-template-columns:1.1fr 1fr; gap:48px; align-items:start;
  }
  @media (max-width:860px){ .section{ grid-template-columns:1fr; gap:32px; } }

  /* ---------- Gallery ---------- */
  .main-image{
    aspect-ratio:4/3; background:
      linear-gradient(var(--line-soft) 1px, transparent 1px),
      linear-gradient(90deg, var(--line-soft) 1px, transparent 1px);
    background-size:14px 14px; background-color:#F1F0EA;
    border:1px solid var(--line); display:flex; align-items:center; justify-content:center;
    overflow:hidden; margin-bottom:14px;
  }
  .main-image img{ width:100%; height:100%; object-fit:cover; display:block; }
  .main-image .glyph{ width:64px; height:64px; stroke:var(--frame-blue-deep); stroke-width:1.2; fill:none; opacity:0.6; }

  .gallery-strip{ display:flex; gap:10px; flex-wrap:wrap; }
  .gallery-strip button{
    all:unset; width:72px; height:72px; border:1px solid var(--line); cursor:pointer;
    overflow:hidden; flex-shrink:0;
  }
  .gallery-strip button img{ width:100%; height:100%; object-fit:cover; display:block; }
  .gallery-strip button.active{ border-color:var(--frame-blue); }

  /* ---------- Info ---------- */
  .tag{ font-size:12px; font-weight:600; letter-spacing:0.1em; color:var(--frame-blue); display:block; margin-bottom:10px; }
  h1{ font-family:'Space Grotesk',sans-serif; font-weight:600; font-size:clamp(24px,3vw,32px); margin:0 0 12px; line-height:1.25; }
  .price{ font-family:'Space Grotesk',sans-serif; font-size:24px; font-weight:600; margin:0 0 26px; }
  .description{ font-size:14.5px; line-height:1.75; color:var(--ink); white-space:pre-wrap; margin:0 0 30px; }
  .description.empty{ color:var(--muted); font-style:italic; }

  .btn{
    display:inline-flex; align-items:center; justify-content:center;
    padding:14px 28px; font-size:14px; font-weight:600; border-radius:2px;
    border:1px solid transparent; cursor:pointer;
    background:var(--frame-blue-deep); color:var(--paper); transition:background 0.2s ease;
  }
  .btn:hover{ background:var(--frame-blue); }
  .btn-outline{ background:none; border-color:var(--frame-blue-deep); color:var(--frame-blue-deep); }
  .btn-outline:hover{ background:var(--line-soft); }

  footer{ border-top:1px solid var(--line); padding:32px; }
  .footer-inner{ max-width:var(--max); margin:0 auto; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px; }
  footer .fine{ font-size:12px; color:var(--muted); }
</style>
</head>
<body>

<header>
  <div class="header-inner">
    <a href="discover.php" class="logo" aria-label="Clear Frame Studio"><img src="clearframe-logo.png" alt="Clear Frame Studio" class="logo-img"></a>
    <div style="display:flex; align-items:center; gap:20px;">
      <a href="discover.php" class="back-home">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
        <span>Back to Discover</span>
      </a>
      <a href="cart.php" class="cart-btn">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.7 13.4a2 2 0 002 1.6h9.7a2 2 0 002-1.6L23 6H6"/></svg>
        Cart <span class="cart-count"><?php echo $cartCount; ?></span>
      </a>
    </div>
  </div>
</header>

<?php if (!$product): ?>

  <div class="not-found">
    <h1>Product not found</h1>
    <p>It may have been removed or is no longer for sale.</p>
    <a href="discover.php" class="btn">Back to Discover</a>
  </div>

<?php else: ?>

  <a href="discover.php" class="back-link">
    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
    Back to Discover
  </a>

  <div class="section">
    <div>
      <div class="main-image" id="main-image">
        <?php if ($initialImage): ?>
          <img src="product-images/<?php echo htmlspecialchars($initialImage); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" id="main-image-img">
        <?php else: ?>
          <svg class="glyph" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="12" rx="1"/><path d="M8 20h8M12 16v4"/></svg>
        <?php endif; ?>
      </div>

      <?php
        $allThumbs = [];
        if ($product['image_path']) {
            $allThumbs[] = $product['image_path'];
        }
        foreach ($gallery as $img) {
            $allThumbs[] = $img['image_path'];
        }
      ?>
      <?php if (count($allThumbs) > 1): ?>
        <div class="gallery-strip">
          <?php foreach ($allThumbs as $path): ?>
            <button type="button" class="<?php echo $path === $initialImage ? 'active' : ''; ?>" onclick="showImage(this, 'product-images/<?php echo htmlspecialchars(addslashes($path)); ?>')">
              <img src="product-images/<?php echo htmlspecialchars($path); ?>" alt="">
            </button>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div>
      <span class="tag"><?php echo htmlspecialchars($categoryLabels[$product['category']] ?? $product['category']); ?></span>
      <h1><?php echo htmlspecialchars($product['name']); ?></h1>
      <div class="price">RM <?php echo number_format((float) $product['price'], 2); ?></div>

      <?php if ($product['description']): ?>
        <p class="description"><?php echo htmlspecialchars($product['description']); ?></p>
      <?php else: ?>
        <p class="description empty">No description provided for this product yet.</p>
      <?php endif; ?>

      <?php if ($inCart): ?>
        <a href="cart.php" class="btn btn-outline">In cart — view cart &rarr;</a>
      <?php else: ?>
        <form method="post" action="cart.php">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="action" value="add">
          <input type="hidden" name="id" value="<?php echo $product['id']; ?>">
          <button type="submit" class="btn">Add to cart</button>
        </form>
      <?php endif; ?>
    </div>
  </div>

<?php endif; ?>

<footer>
  <div class="footer-inner">
    <a href="index.php" class="logo" aria-label="Clear Frame Studio home"><img src="clearframe-logo.png" alt="Clear Frame Studio" class="logo-img" style="height:30px;"></a>
    <p class="fine">&copy; 2026 Clear Frame Studio. All templates for personal and commercial use.</p>
  </div>
</footer>

<script>
  function showImage(btn, src) {
    document.getElementById('main-image-img')?.setAttribute('src', src);
    document.querySelectorAll('.gallery-strip button').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
  }
</script>

</body>
</html>
