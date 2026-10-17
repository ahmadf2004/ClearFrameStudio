<?php
session_start();
require 'db.php';
require 'csrf.php';

$cartCount = count($_SESSION['cart'] ?? []);

// Category metadata (tag label, heading, anchor id, thumbnail glyph, CTA
// text) stays here in code since the products table only stores name,
// category, price, and description — not per-product artwork.
$categoryMeta = [
    'powerpoint' => [
        'tag' => 'SLIDES', 'heading' => 'Powerpoint Template', 'anchor' => 'ppt',
        'glyph' => '<rect x="3" y="4" width="18" height="12" rx="1"/><path d="M8 20h8M12 16v4"/>',
        'cta' => 'View template', 'icons' => false,
    ],
    'infographic' => [
        'tag' => 'VISUALS', 'heading' => 'Infographic', 'anchor' => 'infographic',
        'glyph' => '<rect x="4" y="3" width="16" height="18" rx="1"/><path d="M8 17V11M12 17V7M16 17v-4"/>',
        'cta' => 'View template', 'icons' => false,
    ],
    'icon' => [
        'tag' => 'GRAPHICS', 'heading' => 'Icon', 'anchor' => 'icon',
        'glyph' => '<circle cx="12" cy="12" r="8"/>',
        'cta' => 'Add to cart', 'icons' => true,
    ],
];

// Group real products by category. Falls back to an empty array per
// category if the table has no rows yet, so the page still renders.
$productsByCategory = array_fill_keys(array_keys($categoryMeta), []);
$stmt = $pdo->query('SELECT * FROM products ORDER BY created_at DESC');
foreach ($stmt->fetchAll() as $row) {
    if (isset($productsByCategory[$row['category']])) {
        $productsByCategory[$row['category']][] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Discover — Clear Frame Studio</title>
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
  ul{list-style:none;margin:0;padding:0;}
  button{font-family:inherit;}

  .wrap{ max-width:var(--max); margin:0 auto; padding:0 32px; }

  /* ---------- Top bar ---------- */
  header{
    border-bottom:1px solid var(--line);
    background:var(--paper);
    position:sticky;
    top:0;
    z-index:20;
  }

  .topbar{
    max-width:var(--max);
    margin:0 auto;
    padding:18px 32px;
    display:grid;
    grid-template-columns:auto 1fr auto auto;
    align-items:center;
    gap:20px;
  }

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

  .logo{ display:flex; align-items:center; }
  .logo-img{ height:40px; width:auto; display:block; }
  .logo-img-footer{ height:30px; }

  .search{
    position:relative;
    max-width:520px;
    width:100%;
    justify-self:center;
  }
  .search input{
    width:100%;
    padding:11px 16px 11px 40px;
    background:var(--line-soft);
    border:1px solid transparent;
    border-radius:2px;
    font-size:14px;
    color:var(--ink);
    outline:none;
    transition:border-color 0.2s ease, background 0.2s ease;
  }
  .search input::placeholder{ color:var(--muted); }
  .search input:focus{
    border-color:var(--frame-blue);
    background:var(--paper);
  }
  .search svg{
    position:absolute;
    left:13px;
    top:50%;
    transform:translateY(-50%);
    width:16px;
    height:16px;
    stroke:var(--muted);
  }

  .cart-btn{
    display:flex;
    align-items:center;
    gap:8px;
    padding:10px 16px;
    border:1px solid var(--line);
    border-radius:2px;
    font-size:13px;
    font-weight:600;
    color:var(--frame-blue-deep);
    cursor:pointer;
    background:var(--paper);
    white-space:nowrap;
  }
  .cart-btn:hover{ border-color:var(--frame-blue); }
  .cart-btn svg{ width:17px; height:17px; stroke:var(--frame-blue-deep); }
  .cart-count{
    background:var(--amber);
    color:var(--frame-blue-deep);
    font-size:11px;
    font-weight:700;
    border-radius:50%;
    width:18px;
    height:18px;
    display:flex;
    align-items:center;
    justify-content:center;
  }

  /* ---------- Category tabs ---------- */
  .cat-tabs{
    border-bottom:1px solid var(--line);
    overflow-x:auto;
  }
  .cat-tabs ul{
    max-width:var(--max);
    margin:0 auto;
    padding:0 32px;
    display:flex;
    gap:36px;
  }
  .cat-tabs a{
    display:inline-block;
    padding:16px 0 14px;
    font-size:13px;
    font-weight:600;
    letter-spacing:0.02em;
    color:var(--muted);
    border-bottom:2px solid transparent;
    white-space:nowrap;
  }
  .cat-tabs a.active,
  .cat-tabs a:hover{
    color:var(--ink);
    border-bottom-color:var(--amber);
  }

  /* ---------- Category sections ---------- */
  .category{
    padding:64px 32px 8px;
    border-bottom:1px solid var(--line-soft);
  }
  .category:last-of-type{ border-bottom:none; }

  .category-inner{ max-width:var(--max); margin:0 auto; }

  .category-head{
    display:flex;
    align-items:baseline;
    justify-content:space-between;
    gap:16px;
    margin-bottom:32px;
    flex-wrap:wrap;
  }

  .category-head h2{
    font-family:'Space Grotesk',sans-serif;
    font-size:26px;
    font-weight:600;
    margin:0;
  }

  .category-head .tag{
    font-size:12px;
    font-weight:600;
    letter-spacing:0.1em;
    color:var(--frame-blue);
    display:block;
    margin-bottom:8px;
  }

  .see-all{
    font-size:13px;
    font-weight:600;
    color:var(--frame-blue-deep);
    border-bottom:1px solid var(--frame-blue-deep);
    padding-bottom:2px;
  }
  .see-all:hover{ color:var(--frame-blue); border-color:var(--frame-blue); }

  .card-grid{
    display:grid;
    grid-template-columns:repeat(4, 1fr);
    gap:24px;
    padding-bottom:56px;
  }

  .card{
    position:relative;
    display:flex;
    flex-direction:column;
  }

  .thumb{
    position:relative;
    aspect-ratio:4/3;
    background:
      linear-gradient(var(--line-soft) 1px, transparent 1px),
      linear-gradient(90deg, var(--line-soft) 1px, transparent 1px);
    background-size:14px 14px;
    background-color:#F1F0EA;
    border:1px solid var(--line);
    display:flex;
    align-items:center;
    justify-content:center;
    overflow:hidden;
  }

  .thumb .corner{
    position:absolute;
    width:18px;
    height:18px;
    stroke:var(--frame-blue);
    stroke-width:1.4;
    fill:none;
    opacity:0.6;
  }
  .thumb .corner.tl{ top:8px; left:8px; }
  .thumb .corner.br{ bottom:8px; right:8px; transform:scale(-1,-1); }

  .thumb .glyph{
    width:34px;
    height:34px;
    stroke:var(--frame-blue-deep);
    stroke-width:1.4;
    fill:none;
    opacity:0.75;
  }

  .card h3{
    font-size:14px;
    font-weight:600;
    margin:14px 0 4px;
    line-height:1.4;
  }

  .card .price{
    font-size:13px;
    color:var(--muted);
    margin:0 0 12px;
  }

  .card .buy{
    align-self:flex-start;
    font-size:12px;
    font-weight:600;
    letter-spacing:0.04em;
    color:var(--frame-blue-deep);
    padding-bottom:2px;
    border-bottom:1px solid var(--line);
  }
  .card:hover .buy{ border-bottom-color:var(--amber); color:var(--frame-blue); }

  /* icons get a denser, smaller grid */
  .card-grid.icons{
    grid-template-columns:repeat(8, 1fr);
    gap:18px;
  }
  .card-grid.icons .thumb{ aspect-ratio:1/1; background-size:10px 10px; }
  .card-grid.icons .glyph{ width:22px; height:22px; }
  .card-grid.icons h3{ font-size:12px; margin:10px 0 2px; }
  .card-grid.icons .price{ font-size:11px; margin-bottom:4px; }
  .card-grid.icons .buy{ font-size:10.5px; }

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
  @media (max-width: 980px){
    .card-grid{ grid-template-columns:repeat(2, 1fr); }
    .card-grid.icons{ grid-template-columns:repeat(4, 1fr); }
  }
  @media (max-width: 720px){
    .topbar{ grid-template-columns:auto auto auto; row-gap:14px; }
    .search{ grid-column:1 / -1; order:4; }
    .back-home span{ display:none; }
    .card-grid{ grid-template-columns:repeat(2, 1fr); }
    .card-grid.icons{ grid-template-columns:repeat(3, 1fr); }
  }
</style>
</head>
<body>

<header>
  <div class="topbar">
    <a href="index.php" class="logo" aria-label="Clear Frame Studio home">
      <img src="clearframe-logo.png" alt="Clear Frame Studio" class="logo-img">
    </a>

    <div class="search">
      <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>
      </svg>
      <input type="search" placeholder="Search products" aria-label="Search products">
    </div>

    <a href="index.php" class="back-home">
      <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M15 18l-6-6 6-6"/>
      </svg>
      <span>Back to Home</span>
    </a>

    <a href="cart.php" class="cart-btn" aria-label="View cart">
      <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
        <path d="M1 1h4l2.7 13.4a2 2 0 002 1.6h9.7a2 2 0 002-1.6L23 6H6"/>
      </svg>
      Cart
      <span class="cart-count"><?php echo $cartCount; ?></span>
    </a>
  </div>

  <nav class="cat-tabs">
    <ul>
      <?php foreach ($categoryMeta as $catKey => $meta): ?>
        <li><a href="#<?php echo $meta['anchor']; ?>"<?php echo $catKey === array_key_first($categoryMeta) ? ' class="active"' : ''; ?>><?php echo htmlspecialchars($meta['heading']); ?></a></li>
      <?php endforeach; ?>
    </ul>
  </nav>
</header>

<main>

  <?php foreach ($categoryMeta as $catKey => $meta): ?>
    <section class="category" id="<?php echo $meta['anchor']; ?>">
      <div class="category-inner">
        <div class="category-head">
          <div>
            <span class="tag"><?php echo htmlspecialchars($meta['tag']); ?></span>
            <h2><?php echo htmlspecialchars($meta['heading']); ?></h2>
          </div>
          <a href="#<?php echo $meta['anchor']; ?>" class="see-all">See all</a>
        </div>

        <?php if (empty($productsByCategory[$catKey])): ?>
          <p style="color:var(--muted); font-size:14px; padding-bottom:56px;">
            No products in this category yet — check back soon.
          </p>
        <?php else: ?>
          <div class="card-grid<?php echo $meta['icons'] ? ' icons' : ''; ?>">
            <?php foreach ($productsByCategory[$catKey] as $product): ?>
              <article class="card">
                <a href="product-detail.php?id=<?php echo $product['id']; ?>" style="display:block; color:inherit;">
                  <div class="thumb">
                    <?php if ($product['image_path']): ?>
                      <img src="product-images/<?php echo htmlspecialchars($product['image_path']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" style="width:100%; height:100%; object-fit:cover;">
                    <?php else: ?>
                      <?php if (!$meta['icons']): ?>
                        <svg class="corner tl" viewBox="0 0 18 18"><path d="M1 10V1H10"/></svg>
                        <svg class="corner br" viewBox="0 0 18 18"><path d="M1 10V1H10"/></svg>
                      <?php endif; ?>
                      <svg class="glyph" viewBox="0 0 24 24"><?php echo $meta['glyph']; ?></svg>
                    <?php endif; ?>
                  </div>
                  <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                </a>
                <p class="price">RM <?php echo number_format((float) $product['price'], 2); ?></p>
                <?php if ($meta['cta']): ?>
                  <?php if (in_array($product['id'], $_SESSION['cart'] ?? [], true)): ?>
                    <a href="cart.php" class="buy">In cart &rarr;</a>
                  <?php else: ?>
                    <form method="post" action="cart.php" style="margin:0;">
                      <?php echo csrf_field(); ?>
                      <input type="hidden" name="action" value="add">
                      <input type="hidden" name="id" value="<?php echo $product['id']; ?>">
                      <button type="submit" class="buy" style="background:none; border:none; padding:0; cursor:pointer; font:inherit;">Add to cart</button>
                    </form>
                  <?php endif; ?>
                <?php endif; ?>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </section>
  <?php endforeach; ?>

</main>

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
