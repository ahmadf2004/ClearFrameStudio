<?php
session_start();
require 'db.php';
require 'csrf.php';

if (empty($_SESSION['is_admin'])) {
    header('Location: admin-login.php');
    exit;
}
$adminName = $_SESSION['admin_name'] ?? 'Admin';

$errors = [];

// If a file is bigger than PHP's post_max_size, PHP throws away the ENTIRE
// request ($_POST and $_FILES both come through empty), so none of the
// handlers below would fire and the page would just reload as if nothing
// happened. Catch that here and say why.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    $errors[] = 'That upload was too large for the server (post_max_size is ' . ini_get('post_max_size') .
                '). Use a smaller file, or raise post_max_size and upload_max_filesize in php.ini and restart Apache.';
}

// Validates and stores a product's deliverable file (the thing a customer
// downloads after paying). Returns the stored filename, or null with $error
// set. The file's real content is checked, not just its name, and the stored
// extension must be on a fixed whitelist AND agree with that content.
function storeProductFile(array $file, ?string &$error): ?string
{
    $upErr = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($upErr === UPLOAD_ERR_INI_SIZE || $upErr === UPLOAD_ERR_FORM_SIZE) {
        $error = 'That file is larger than the server allows (upload_max_filesize is ' . ini_get('upload_max_filesize') .
                 '). Use a smaller file, or raise upload_max_filesize and post_max_size in php.ini and restart Apache.';
        return null;
    }
    if ($upErr !== UPLOAD_ERR_OK) {
        $error = 'The file could not be uploaded. Please try again.';
        return null;
    }

    // Extension => MIME types PHP's fileinfo may report for a genuine file of
    // that type. Office files (pptx/potx/docx) are ZIP containers, so fileinfo
    // sometimes reports them as plain application/zip instead of the specific
    // Office type. Matching the DECLARED extension against these lists (rather
    // than taking the first list that contains the detected type) is what keeps
    // a PPTX from being stored and downloaded as ".zip".
    $zipTypes = ['application/zip', 'application/x-zip-compressed'];
    $allowedTypes = [
        'pdf'  => ['application/pdf'],
        'zip'  => $zipTypes,
        'png'  => ['image/png'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'pptx' => array_merge(['application/vnd.openxmlformats-officedocument.presentationml.presentation'], $zipTypes),
        'potx' => array_merge(['application/vnd.openxmlformats-officedocument.presentationml.template'], $zipTypes),
        'docx' => array_merge(['application/vnd.openxmlformats-officedocument.wordprocessingml.document'], $zipTypes),
    ];

    $declaredExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $detectedMime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!isset($allowedTypes[$declaredExt]) || !in_array($detectedMime, $allowedTypes[$declaredExt], true)) {
        $error = 'Product file must be a genuine PDF, ZIP, PNG/JPG, PPTX, POTX, or DOCX file.';
        return null;
    }

    // This folder is never linked to directly by customers. The .htaccess
    // blocks all direct web access; only download.php ever reads from it,
    // after checking the customer actually paid for that product.
    $fileDir = __DIR__ . '/product-files/';
    if (!is_dir($fileDir)) {
        mkdir($fileDir, 0755, true);
    }
    $htaccessPath = $fileDir . '.htaccess';
    if (!file_exists($htaccessPath)) {
        file_put_contents($htaccessPath, "Require all denied\n");
    }

    $stored = uniqid('prod_', true) . '.' . $declaredExt;
    if (!move_uploaded_file($file['tmp_name'], $fileDir . $stored)) {
        $error = 'Something went wrong saving the product file. Please try again.';
        return null;
    }
    return $stored;
}

// Validates and stores a product's thumbnail image (shown on
// discover.php). Unlike product-files/, this folder is MEANT to be
// publicly viewable — these are the pictures customers browse by —
// so no .htaccess deny here on purpose.
function storeProductImage(array $file, ?string &$error): ?string
{
    $upErr = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($upErr === UPLOAD_ERR_INI_SIZE || $upErr === UPLOAD_ERR_FORM_SIZE) {
        $error = 'Image is larger than the server allows (upload_max_filesize is ' . ini_get('upload_max_filesize') .
                 '). Use a smaller image, or raise upload_max_filesize in php.ini and restart Apache.';
        return null;
    }
    if ($upErr !== UPLOAD_ERR_OK) {
        $error = 'The image could not be uploaded. Please try again.';
        return null;
    }

    $allowedTypes = [
        'png'  => ['image/png'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'webp' => ['image/webp'],
    ];

    $declaredExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $detectedMime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!isset($allowedTypes[$declaredExt]) || !in_array($detectedMime, $allowedTypes[$declaredExt], true)) {
        $error = 'Product image must be a genuine PNG, JPG, or WEBP file.';
        return null;
    }

    $imageDir = __DIR__ . '/product-images/';
    if (!is_dir($imageDir)) {
        mkdir($imageDir, 0755, true);
    }

    $stored = uniqid('img_', true) . '.' . $declaredExt;
    if (!move_uploaded_file($file['tmp_name'], $imageDir . $stored)) {
        $error = 'Something went wrong saving the image. Please try again.';
        return null;
    }
    return $stored;
}

// Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    csrf_verify();
    $stmt = $pdo->prepare('DELETE FROM products WHERE id = ?');
    $stmt->execute([(int) $_POST['delete_id']]);
    header('Location: manage-products.php');
    exit;
}

// Attach or replace the deliverable file on an EXISTING product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['attach_file'])) {
    csrf_verify();
    $productId = (int) ($_POST['product_id'] ?? 0);

    $stmt = $pdo->prepare('SELECT file_path FROM products WHERE id = ?');
    $stmt->execute([$productId]);
    $existing = $stmt->fetch();

    if (!$existing) {
        $errors[] = 'Product not found.';
    } elseif (empty($_FILES['product_file']['name'])) {
        $errors[] = 'Please choose a file to attach.';
    } else {
        $uploadError = null;
        $stored = storeProductFile($_FILES['product_file'], $uploadError);
        if ($stored === null) {
            $errors[] = $uploadError;
        } else {
            $pdo->prepare('UPDATE products SET file_path = ? WHERE id = ?')->execute([$stored, $productId]);
            // The old file was only referenced by this product, so remove it.
            if ($existing['file_path']) {
                $old = __DIR__ . '/product-files/' . basename($existing['file_path']);
                if (is_file($old)) {
                    unlink($old);
                }
            }
            header('Location: manage-products.php');
            exit;
        }
    }
}

// Edit an existing product's name/category/price/description, and
// optionally replace its thumbnail image.
$editId = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product'])) {
    csrf_verify();
    $productId = (int) ($_POST['product_id'] ?? 0);

    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([$productId]);
    $existingProduct = $stmt->fetch();

    if (!$existingProduct) {
        $errors[] = 'Product not found.';
    } else {
        $editValues = [
            'name'        => trim($_POST['name'] ?? ''),
            'category'    => $_POST['category'] ?? '',
            'price'       => trim($_POST['price'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
        ];
        $validCategories = ['powerpoint', 'infographic', 'icon'];

        if ($editValues['name'] === '') {
            $errors[] = 'Please enter a product name.';
        }
        if (!in_array($editValues['category'], $validCategories, true)) {
            $errors[] = 'Please choose a valid category.';
        }
        if (!is_numeric($editValues['price']) || (float) $editValues['price'] < 0) {
            $errors[] = 'Please enter a valid price.';
        }

        $newImagePath = null;
        if (empty($errors) && !empty($_FILES['product_image']['name'])) {
            $imgError = null;
            $newImagePath = storeProductImage($_FILES['product_image'], $imgError);
            if ($newImagePath === null) {
                $errors[] = $imgError;
            }
        }

        if (empty($errors)) {
            if ($newImagePath !== null) {
                $pdo->prepare(
                    'UPDATE products SET name = ?, category = ?, price = ?, description = ?, image_path = ? WHERE id = ?'
                )->execute([
                    $editValues['name'], $editValues['category'], (float) $editValues['price'],
                    $editValues['description'], $newImagePath, $productId,
                ]);
                // The old image was only referenced by this product.
                if ($existingProduct['image_path']) {
                    $old = __DIR__ . '/product-images/' . basename($existingProduct['image_path']);
                    if (is_file($old)) {
                        unlink($old);
                    }
                }
            } else {
                $pdo->prepare(
                    'UPDATE products SET name = ?, category = ?, price = ?, description = ? WHERE id = ?'
                )->execute([
                    $editValues['name'], $editValues['category'], (float) $editValues['price'],
                    $editValues['description'], $productId,
                ]);
            }
            header('Location: manage-products.php');
            exit;
        } else {
            // Validation failed — stay on the edit panel, pre-filled
            // with what was submitted, so nothing gets lost.
            $editId = $productId;
            $editProduct = $existingProduct;
        }
    }
}

// Add one or more preview/gallery photos to an existing product
// (separate from the single thumbnail image set above — a product
// can have any number of these).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_preview_images'])) {
    csrf_verify();
    $productId = (int) ($_POST['product_id'] ?? 0);

    $stmt = $pdo->prepare('SELECT id FROM products WHERE id = ?');
    $stmt->execute([$productId]);
    if (!$stmt->fetch()) {
        $errors[] = 'Product not found.';
    } elseif (empty($_FILES['preview_images']['name'][0])) {
        $errors[] = 'Please choose at least one image to upload.';
    } else {
        $stmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), -1) AS max_order FROM product_images WHERE product_id = ?');
        $stmt->execute([$productId]);
        $nextOrder = (int) $stmt->fetch()['max_order'] + 1;

        $fileCount = count($_FILES['preview_images']['name']);
        for ($i = 0; $i < $fileCount; $i++) {
            if (empty($_FILES['preview_images']['name'][$i])) {
                continue;
            }
            $singleFile = [
                'name'     => $_FILES['preview_images']['name'][$i],
                'type'     => $_FILES['preview_images']['type'][$i],
                'tmp_name' => $_FILES['preview_images']['tmp_name'][$i],
                'error'    => $_FILES['preview_images']['error'][$i],
                'size'     => $_FILES['preview_images']['size'][$i],
            ];
            $imgError = null;
            $stored = storeProductImage($singleFile, $imgError);
            if ($stored === null) {
                $errors[] = $singleFile['name'] . ': ' . $imgError;
                continue;
            }
            $pdo->prepare('INSERT INTO product_images (product_id, image_path, sort_order) VALUES (?, ?, ?)')
                ->execute([$productId, $stored, $nextOrder]);
            $nextOrder++;
        }

        if (empty($errors)) {
            header('Location: manage-products.php?edit=' . $productId);
            exit;
        }
        $editId = $productId;
        $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
        $stmt->execute([$productId]);
        $editProduct = $stmt->fetch();
        $values = [
            'name' => $editProduct['name'], 'category' => $editProduct['category'],
            'price' => $editProduct['price'], 'description' => $editProduct['description'],
        ];
    }
}

// Remove one preview/gallery photo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_preview_image'])) {
    csrf_verify();
    $imageId = (int) ($_POST['image_id'] ?? 0);
    $productId = (int) ($_POST['product_id'] ?? 0);

    $stmt = $pdo->prepare('SELECT * FROM product_images WHERE id = ? AND product_id = ?');
    $stmt->execute([$imageId, $productId]);
    $img = $stmt->fetch();

    if ($img) {
        $pdo->prepare('DELETE FROM product_images WHERE id = ?')->execute([$imageId]);
        $path = __DIR__ . '/product-images/' . basename($img['image_path']);
        if (is_file($path)) {
            unlink($path);
        }
    }
    header('Location: manage-products.php?edit=' . $productId);
    exit;
}

// Add
$values = ['name' => '', 'category' => 'powerpoint', 'price' => '', 'description' => ''];

// Opening the edit panel via the Edit button (a plain GET request —
// only runs when there's no update_product POST already in flight,
// since that path above handles its own pre-fill on validation failure).
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_GET['edit'])) {
    $editId = (int) $_GET['edit'];
    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([$editId]);
    $editProduct = $stmt->fetch();

    if ($editProduct) {
        $values = [
            'name'        => $editProduct['name'],
            'category'    => $editProduct['category'],
            'price'       => $editProduct['price'],
            'description' => $editProduct['description'],
        ];
    } else {
        $editId = null; // no such product — fall back to the Add panel
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    csrf_verify();
    $values['name']        = trim($_POST['name'] ?? '');
    $values['category']    = $_POST['category'] ?? '';
    $values['price']       = trim($_POST['price'] ?? '');
    $values['description'] = trim($_POST['description'] ?? '');

    $validCategories = ['powerpoint', 'infographic', 'icon'];

    if ($values['name'] === '') {
        $errors[] = 'Please enter a product name.';
    }
    if (!in_array($values['category'], $validCategories, true)) {
        $errors[] = 'Please choose a valid category.';
    }
    if (!is_numeric($values['price']) || (float) $values['price'] < 0) {
        $errors[] = 'Please enter a valid price.';
    }

    // Optional deliverable file and optional thumbnail image. Only
    // processed once the other fields are valid, so a typo in the
    // name can't leave an orphaned upload behind.
    $storedFilePath = null;
    if (empty($errors) && !empty($_FILES['product_file']['name'])) {
        $uploadError = null;
        $storedFilePath = storeProductFile($_FILES['product_file'], $uploadError);
        if ($storedFilePath === null) {
            $errors[] = $uploadError;
        }
    }

    $storedImagePath = null;
    if (empty($errors) && !empty($_FILES['product_image']['name'])) {
        $imgError = null;
        $storedImagePath = storeProductImage($_FILES['product_image'], $imgError);
        if ($storedImagePath === null) {
            $errors[] = $imgError;
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'INSERT INTO products (name, category, price, description, file_path, image_path) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $values['name'],
            $values['category'],
            (float) $values['price'],
            $values['description'],
            $storedFilePath,
            $storedImagePath,
        ]);
        header('Location: manage-products.php');
        exit;
    }
}

$galleryImages = [];
if ($editId) {
    $stmt = $pdo->prepare('SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order ASC, id ASC');
    $stmt->execute([$editId]);
    $galleryImages = $stmt->fetchAll();
}

$products = $pdo->query('SELECT * FROM products ORDER BY created_at DESC')->fetchAll();

$categoryLabels = [
    'powerpoint'  => 'PowerPoint',
    'infographic' => 'Infographic',
    'icon'        => 'Icon',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Products — Clear Frame Studio</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<style>
  :root{
    --paper:#FAFAF7; --ink:#1C2530; --muted:#6B7480;
    --frame-blue:#3E5C76; --frame-blue-deep:#26384A; --amber:#E8A13D; --alert:#9B3B3B;
    --line:#D8DBDE; --line-soft:#E7E9EB; --max:1160px;
  }
  *{box-sizing:border-box;}
  body{ margin:0; background:var(--paper); color:var(--ink); font-family:'Inter',sans-serif; -webkit-font-smoothing:antialiased; }
  a{ color:inherit; text-decoration:none; }
  table{ border-collapse:collapse; width:100%; }

  header{ border-bottom:1px solid var(--line); }
  .header-inner{ max-width:var(--max); margin:0 auto; padding:18px 32px; display:flex; align-items:center; justify-content:space-between; }
  .logo{ display:flex; align-items:center; }
  .logo-img{ height:40px; width:auto; display:block; }
  .header-right{ display:flex; align-items:center; gap:20px; }
  .admin-chip{ display:flex; align-items:center; gap:8px; font-size:13px; color:var(--muted); }
  .admin-chip .dot{ width:7px; height:7px; border-radius:50%; background:var(--alert); }
  .logout-link{ font-size:13px; font-weight:600; color:var(--frame-blue-deep); padding-bottom:2px; border-bottom:1px solid transparent; }
  .logout-link:hover{ border-bottom-color:var(--amber); color:var(--frame-blue); }

  .page-head{ max-width:var(--max); margin:0 auto; padding:48px 32px 8px; }
  .back-link{
    display:inline-flex; align-items:center; gap:6px; font-size:12.5px; font-weight:600;
    color:var(--muted); margin-bottom:20px; padding-bottom:2px; border-bottom:1px solid transparent;
  }
  .back-link svg{ width:14px; height:14px; stroke:var(--muted); }
  .back-link:hover{ color:var(--frame-blue-deep); border-bottom-color:var(--amber); }
  .back-link:hover svg{ stroke:var(--frame-blue-deep); }

  .page-head h1{ font-family:'Space Grotesk',sans-serif; font-weight:600; font-size:clamp(24px, 3vw, 32px); margin:0 0 8px; }
  .page-head p{ color:var(--muted); font-size:14px; margin:0; }

  .section{ max-width:var(--max); margin:0 auto; padding:20px 32px 100px; }
  .section-head{ margin-bottom:16px; }
  .section-head h2{ font-family:'Space Grotesk',sans-serif; font-size:17px; font-weight:600; margin:0; }

  /* ---------- Add product form ---------- */
  .add-panel{ border:1px solid var(--line); padding:32px; margin-bottom:48px; }
  .field-row{ display:grid; grid-template-columns:2fr 1fr 1fr; gap:16px; margin-bottom:18px; }
  .field label{ display:block; font-size:12.5px; font-weight:600; margin-bottom:6px; }
  .field input, .field select, .field textarea{
    width:100%; padding:11px 13px; background:var(--line-soft); border:1px solid transparent; border-radius:2px;
    font-family:'Inter',sans-serif; font-size:13.5px; color:var(--ink); outline:none;
    transition:border-color 0.2s ease, background 0.2s ease;
  }
  .field input:focus, .field select:focus, .field textarea:focus{ border-color:var(--frame-blue); background:var(--paper); }
  .field textarea{ min-height:70px; resize:vertical; margin-bottom:18px; }

  .btn{
    display:inline-flex; align-items:center; justify-content:center;
    padding:12px 24px; font-size:13.5px; font-weight:600; border-radius:2px;
    border:1px solid transparent; cursor:pointer;
    background:var(--frame-blue-deep); color:var(--paper); transition:background 0.2s ease;
  }
  .btn:hover{ background:var(--frame-blue); }

  .error-list{
    background:#FBEAEA; border:1px solid #E2B4B4; color:#7A2020;
    padding:14px 18px; font-size:13.5px; margin-bottom:22px;
  }
  .error-list ul{ margin:6px 0 0; padding-left:18px; }

  /* ---------- Table ---------- */
  .panel{ border:1px solid var(--line); }
  table th, table td{ text-align:left; padding:14px 20px; font-size:13.5px; border-bottom:1px solid var(--line-soft); vertical-align:middle; }
  table th{ font-size:11.5px; letter-spacing:0.06em; color:var(--muted); font-weight:600; background:var(--line-soft); }
  table tr:last-child td{ border-bottom:none; }
  .empty-state{ padding:44px 24px; text-align:center; color:var(--muted); font-size:14px; }

  .tag{ font-size:11.5px; font-weight:600; letter-spacing:0.04em; color:var(--frame-blue); }

  .del-btn{
    background:none; border:1px solid var(--alert); color:var(--alert);
    font-size:12.5px; font-weight:600; padding:7px 14px; border-radius:2px; cursor:pointer;
    font-family:'Inter',sans-serif;
  }
  .del-btn:hover{ background:var(--alert); color:var(--paper); }

  @media (max-width:700px){
    .field-row{ grid-template-columns:1fr; }
    table{ font-size:12.5px; }
    table th, table td{ padding:10px 12px; }
  }
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
    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
    Back to Dashboard
  </a>
  <h1>Manage Products</h1>
  <p>Add new listings or remove ones that are no longer for sale.</p>
</div>

<div class="section">

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

  <div class="add-panel">
    <?php if ($editId): ?>

      <div class="section-head" style="display:flex; align-items:center; justify-content:space-between;">
        <h2>Edit product</h2>
        <a href="manage-products.php" style="font-size:12.5px; font-weight:600; color:var(--muted);">Cancel</a>
      </div>
      <form method="post" enctype="multipart/form-data">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="product_id" value="<?php echo $editId; ?>">
        <div class="field-row">
          <div class="field">
            <label for="name">Product name</label>
            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($values['name']); ?>" required>
          </div>
          <div class="field">
            <label for="category">Category</label>
            <select id="category" name="category">
              <?php foreach ($categoryLabels as $key => $label): ?>
                <option value="<?php echo $key; ?>" <?php echo $values['category'] === $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="price">Price (RM)</label>
            <input type="text" id="price" name="price" value="<?php echo htmlspecialchars($values['price']); ?>" placeholder="e.g. 18.00" required>
          </div>
        </div>
        <div class="field">
          <label for="description">Description</label>
          <textarea id="description" name="description"><?php echo htmlspecialchars($values['description']); ?></textarea>
        </div>
        <div class="field" style="display:flex; align-items:center; gap:16px;">
          <?php if ($editProduct['image_path']): ?>
            <img src="product-images/<?php echo htmlspecialchars($editProduct['image_path']); ?>" alt="" style="width:64px; height:64px; object-fit:cover; border:1px solid var(--line); border-radius:2px;">
          <?php endif; ?>
          <div style="flex:1;">
            <label for="product_image">Thumbnail image <?php echo $editProduct['image_path'] ? '(optional — choose a file to replace it)' : '(optional)'; ?></label>
            <input type="file" id="product_image" name="product_image" accept=".png,.jpg,.jpeg,.webp">
          </div>
        </div>
        <button type="submit" name="update_product" value="1" class="btn" style="margin-top:18px;">Save changes</button>
      </form>

      <hr style="border:none; border-top:1px solid var(--line); margin:30px 0;">

      <div class="section-head"><h2>Preview photos</h2></div>
      <p style="font-size:12.5px; color:var(--muted); margin:-10px 0 16px;">
        Shown as a gallery on the product's page on Discover, alongside the main thumbnail above.
      </p>

      <?php if (!empty($galleryImages)): ?>
        <div style="display:flex; flex-wrap:wrap; gap:12px; margin-bottom:18px;">
          <?php foreach ($galleryImages as $img): ?>
            <div style="position:relative;">
              <img src="product-images/<?php echo htmlspecialchars($img['image_path']); ?>" alt=""
                   style="width:84px; height:84px; object-fit:cover; border:1px solid var(--line); border-radius:2px; display:block;">
              <form method="post" onsubmit="return confirm('Remove this preview photo?');" style="position:absolute; top:4px; right:4px;">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="product_id" value="<?php echo $editId; ?>">
                <input type="hidden" name="image_id" value="<?php echo $img['id']; ?>">
                <button type="submit" name="delete_preview_image" value="1"
                        style="width:20px; height:20px; border-radius:50%; border:none; background:rgba(28,37,48,0.75); color:#fff; font-size:12px; line-height:1; cursor:pointer;">&times;</button>
              </form>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p style="font-size:13px; color:var(--muted); margin-bottom:16px;">No preview photos added yet.</p>
      <?php endif; ?>

      <form method="post" enctype="multipart/form-data">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="product_id" value="<?php echo $editId; ?>">
        <input type="file" name="preview_images[]" accept=".png,.jpg,.jpeg,.webp" multiple style="font-size:13px; margin-bottom:10px;">
        <br>
        <button type="submit" name="upload_preview_images" value="1" class="btn" style="padding:10px 20px; font-size:13px;">Add photos</button>
      </form>

    <?php else: ?>

      <div class="section-head"><h2>Add a product</h2></div>
      <form method="post" enctype="multipart/form-data">
        <?php echo csrf_field(); ?>
        <div class="field-row">
          <div class="field">
            <label for="name">Product name</label>
            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($values['name']); ?>" required>
          </div>
          <div class="field">
            <label for="category">Category</label>
            <select id="category" name="category">
              <?php foreach ($categoryLabels as $key => $label): ?>
                <option value="<?php echo $key; ?>" <?php echo $values['category'] === $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="price">Price (RM)</label>
            <input type="text" id="price" name="price" value="<?php echo htmlspecialchars($values['price']); ?>" placeholder="e.g. 18.00" required>
          </div>
        </div>
        <div class="field">
          <label for="description">Description</label>
          <textarea id="description" name="description"><?php echo htmlspecialchars($values['description']); ?></textarea>
        </div>
        <div class="field">
          <label for="product_image">Thumbnail image (optional — shown on the Discover page)</label>
          <input type="file" id="product_image" name="product_image" accept=".png,.jpg,.jpeg,.webp">
        </div>
        <div class="field">
          <label for="product_file">Deliverable file (optional — what the customer downloads after paying)</label>
          <input type="file" id="product_file" name="product_file" accept=".pdf,.zip,.png,.jpg,.jpeg,.pptx,.potx,.docx">
        </div>
        <button type="submit" name="add_product" value="1" class="btn">Add product</button>
      </form>

    <?php endif; ?>
  </div>

  <div class="section-head"><h2>Current products (<?php echo count($products); ?>)</h2></div>
  <div class="panel">
    <?php if (empty($products)): ?>
      <div class="empty-state">No products yet — add one above.</div>
    <?php else: ?>
      <table>
        <thead>
          <tr><th>Image</th><th>Name</th><th>Category</th><th>Price</th><th>File</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($products as $p): ?>
            <tr>
              <td>
                <?php if ($p['image_path']): ?>
                  <img src="product-images/<?php echo htmlspecialchars($p['image_path']); ?>" alt="" style="width:44px; height:44px; object-fit:cover; border:1px solid var(--line); border-radius:2px; display:block;">
                <?php else: ?>
                  <div style="width:44px; height:44px; border:1px dashed var(--line); border-radius:2px; display:flex; align-items:center; justify-content:center; color:var(--muted); font-size:10px;">None</div>
                <?php endif; ?>
              </td>
              <td><?php echo htmlspecialchars($p['name']); ?></td>
              <td><span class="tag"><?php echo htmlspecialchars($categoryLabels[$p['category']] ?? $p['category']); ?></span></td>
              <td>RM <?php echo number_format((float) $p['price'], 2); ?></td>
              <td>
                <?php echo $p['file_path'] ? '<span style="color:#276B33;">Attached</span>' : '<span style="color:var(--muted);">None</span>'; ?>
                <form method="post" enctype="multipart/form-data" style="margin-top:8px; display:flex; gap:6px; align-items:center;">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="product_id" value="<?php echo $p['id']; ?>">
                  <input type="file" name="product_file" accept=".pdf,.zip,.png,.jpg,.jpeg,.pptx,.potx,.docx" style="font-size:12px; max-width:190px;" required>
                  <button type="submit" name="attach_file" value="1" class="btn" style="padding:6px 12px; font-size:12px;"><?php echo $p['file_path'] ? 'Replace' : 'Attach'; ?></button>
                </form>
              </td>
              <td style="white-space:nowrap;">
                <a href="manage-products.php?edit=<?php echo $p['id']; ?>" class="btn" style="padding:7px 14px; font-size:12.5px; margin-right:8px;">Edit</a>
                <form method="post" onsubmit="return confirm('Delete this product? This cannot be undone.');" style="display:inline;">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="delete_id" value="<?php echo $p['id']; ?>">
                  <button type="submit" class="del-btn">Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

</div>

</body>
</html>
