<?php
session_start();
require 'db.php';
require 'csrf.php';

if (empty($_SESSION['is_admin'])) {
    header('Location: admin-login.php');
    exit;
}
$adminName = $_SESSION['admin_name'] ?? 'Admin';
$deliverableError = '';

// Handle a status update from the detail view
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    csrf_verify();
    $id     = (int) $_POST['id'];
    $status = $_POST['status'];
    if (in_array($status, ['new', 'in_progress', 'completed'], true)) {
        $stmt = $pdo->prepare('UPDATE custom_requests SET status = ?, has_unread_update = 1 WHERE id = ?');
        $stmt->execute([$status, $id]);
    }
    header('Location: manage-requests.php?id=' . $id);
    exit;
}

// Handle sending/updating a quote from the detail view
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_quote'])) {
    csrf_verify();
    $id     = (int) $_POST['id'];
    $amount = trim($_POST['quote_amount'] ?? '');
    $notes  = trim($_POST['quote_notes'] ?? '');

    if (is_numeric($amount) && (float) $amount >= 0) {
        $stmt = $pdo->prepare(
            'UPDATE custom_requests SET quote_amount = ?, quote_notes = ?, quoted_at = NOW(), has_unread_update = 1 WHERE id = ?'
        );
        $stmt->execute([(float) $amount, $notes !== '' ? $notes : null, $id]);
    }
    header('Location: manage-requests.php?id=' . $id);
    exit;
}

// Handle uploading the finished deliverable file
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_deliverable'])) {
    csrf_verify();
    $id = (int) $_POST['id'];

    if (empty($_FILES['deliverable']['name'])) {
        $deliverableError = 'Please choose a file to upload.';
    } else {
        $tmpPath = $_FILES['deliverable']['tmp_name'];

        // Same content-based verification pattern used for the
        // customer's reference-file upload in custom.php — trust what
        // the file actually is, not its filename or claimed extension.
        $allowedTypes = [
            'pdf'  => ['application/pdf'],
            'png'  => ['image/png'],
            'jpg'  => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
            'zip'  => ['application/zip'],
        ];

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMime = finfo_file($finfo, $tmpPath);
        finfo_close($finfo);

        $declaredExt = strtolower(pathinfo($_FILES['deliverable']['name'], PATHINFO_EXTENSION));
        $matchedExt = null;
        if (isset($allowedTypes[$declaredExt]) && in_array($detectedMime, $allowedTypes[$declaredExt], true)) {
            $matchedExt = $declaredExt;
        }

        if (!$matchedExt) {
            $deliverableError = 'File must be a genuine PDF, PNG/JPG, PPTX, DOCX, or ZIP file.';
        } else {
            $uploadDir = __DIR__ . '/uploads/deliverables/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Defense in depth, same as every other upload folder in
            // this project: stop this directory from ever executing
            // anything as PHP.
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

            $storedFileName = uniqid('deliv_', true) . '.' . $matchedExt;
            if (move_uploaded_file($tmpPath, $uploadDir . $storedFileName)) {
                $pdo->prepare(
                    'UPDATE custom_requests SET deliverable_file = ?, deliverable_uploaded_at = NOW(), has_unread_update = 1 WHERE id = ?'
                )->execute([$storedFileName, $id]);
                header('Location: manage-requests.php?id=' . $id);
                exit;
            }
            $deliverableError = 'Something went wrong uploading the file. Please try again.';
        }
    }
}

$viewId = isset($_GET['id']) ? (int) $_GET['id'] : null;
$request = null;
$linkedOrder = null;

if ($viewId) {
    $stmt = $pdo->prepare('SELECT * FROM custom_requests WHERE id = ?');
    $stmt->execute([$viewId]);
    $request = $stmt->fetch();

    if ($request) {
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE custom_request_id = ?');
        $stmt->execute([$viewId]);
        $linkedOrder = $stmt->fetch();
    }
}

$requests = $pdo->query(
    'SELECT * FROM custom_requests ORDER BY created_at DESC'
)->fetchAll();

function statusBadge($status) {
    $map = [
        'new'         => ['Pending',    'badge-pending'],
        'in_progress' => ['In Review',  'badge-review'],
        'completed'   => ['Completed',  'badge-closed'],
    ];
    [$label, $class] = $map[$status] ?? [$status, 'badge-pending'];
    return "<span class=\"badge $class\">$label</span>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Requests — Clear Frame Studio</title>
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

  .section{ max-width:var(--max); margin:0 auto; padding:36px 32px 100px; }
  .panel{ border:1px solid var(--line); }

  table th, table td{ text-align:left; padding:14px 20px; font-size:13.5px; border-bottom:1px solid var(--line-soft); vertical-align:middle; }
  table th{ font-size:11.5px; letter-spacing:0.06em; color:var(--muted); font-weight:600; background:var(--line-soft); }
  table tr:last-child td{ border-bottom:none; }

  .badge{ display:inline-flex; padding:4px 12px; border-radius:999px; font-size:11px; font-weight:600; letter-spacing:0.02em; white-space:nowrap; }
  .badge-pending{ background:#FCEFD1; color:#8A5A00; }
  .badge-review{ background:#D9EEF2; color:#1B6E7D; }
  .badge-closed{ background:#DFF2E1; color:#276B33; }

  .view-btn{
    display:inline-flex; align-items:center; justify-content:center;
    padding:7px 16px; font-size:12.5px; font-weight:600;
    border:1px solid var(--frame-blue-deep); color:var(--frame-blue-deep); border-radius:2px;
  }
  .view-btn:hover{ background:var(--frame-blue-deep); color:var(--paper); }

  .empty-state{ padding:44px 24px; text-align:center; color:var(--muted); font-size:14px; }

  /* ---------- Detail view ---------- */
  .detail-panel{ border:1px solid var(--line); padding:40px; max-width:640px; }
  .detail-panel .field{ margin-bottom:22px; }
  .detail-panel .label{ font-size:11.5px; font-weight:600; letter-spacing:0.06em; color:var(--muted); margin-bottom:6px; }
  .detail-panel .value{ font-size:14.5px; line-height:1.6; }
  .detail-panel .value.request-text{ white-space:pre-wrap; }

  select.status-select{
    padding:10px 12px; font-size:14px; border:1px solid var(--line); border-radius:2px;
    background:var(--paper); color:var(--ink); font-family:'Inter',sans-serif;
  }
  .btn{
    display:inline-flex; align-items:center; justify-content:center;
    padding:12px 22px; font-size:13.5px; font-weight:600; border-radius:2px;
    border:1px solid transparent; cursor:pointer;
    background:var(--frame-blue-deep); color:var(--paper); transition:background 0.2s ease;
  }
  .btn:hover{ background:var(--frame-blue); }
  .error-list{ background:#FBEAEA; border:1px solid #E2B4B4; color:#7A2020; padding:12px 16px; font-size:13px; }

  @media (max-width:700px){
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
  <h1><?php echo $request ? 'Request Detail' : 'Manage Custom Requests'; ?></h1>
  <p><?php echo $request ? 'Review the request and update its status.' : 'All requests submitted from the Custom page.'; ?></p>
</div>

<div class="section">

  <?php if ($request): ?>

    <div class="detail-panel">
      <div class="field">
        <div class="label">NAME</div>
        <div class="value"><?php echo htmlspecialchars($request['name']); ?></div>
      </div>
      <div class="field">
        <div class="label">EMAIL</div>
        <div class="value"><?php echo htmlspecialchars($request['email']); ?></div>
      </div>
      <div class="field">
        <div class="label">PRODUCT TYPE</div>
        <div class="value"><?php echo htmlspecialchars($request['product_type'] ?: '—'); ?></div>
      </div>
      <div class="field">
        <div class="label">REQUEST</div>
        <div class="value request-text"><?php echo htmlspecialchars($request['request_text']); ?></div>
      </div>
      <?php if ($request['reference_file']): ?>
        <div class="field">
          <div class="label">REFERENCE FILE</div>
          <div class="value"><a href="uploads/custom-requests/<?php echo htmlspecialchars($request['reference_file']); ?>" target="_blank" style="color:var(--frame-blue-deep); font-weight:600;">Download file</a></div>
        </div>
      <?php endif; ?>
      <div class="field">
        <div class="label">SUBMITTED</div>
        <div class="value"><?php echo date('M j, Y \a\t g:ia', strtotime($request['created_at'])); ?></div>
      </div>

      <form method="post" style="display:flex; gap:12px; align-items:center; margin-top:30px;">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="id" value="<?php echo $request['id']; ?>">
        <select name="status" class="status-select">
          <option value="new" <?php echo $request['status'] === 'new' ? 'selected' : ''; ?>>Pending</option>
          <option value="in_progress" <?php echo $request['status'] === 'in_progress' ? 'selected' : ''; ?>>In Review</option>
          <option value="completed" <?php echo $request['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
        </select>
        <button type="submit" name="update_status" value="1" class="btn">Update status</button>
      </form>

      <hr style="border:none; border-top:1px solid var(--line); margin:30px 0;">

      <div class="field">
        <div class="label">QUOTE</div>
        <?php if ($request['quote_amount'] !== null): ?>
          <div class="value" style="font-size:20px; font-weight:600; font-family:'Space Grotesk',sans-serif;">
            RM <?php echo number_format((float) $request['quote_amount'], 2); ?>
          </div>
          <?php if ($request['quote_notes']): ?>
            <div class="value" style="color:var(--muted); margin-top:4px;"><?php echo htmlspecialchars($request['quote_notes']); ?></div>
          <?php endif; ?>
          <div class="value" style="color:var(--muted); font-size:12.5px; margin-top:4px;">
            Sent <?php echo date('M j, Y \a\t g:ia', strtotime($request['quoted_at'])); ?>
          </div>
        <?php else: ?>
          <div class="value" style="color:var(--muted);">No quote sent yet.</div>
        <?php endif; ?>
      </div>

      <form method="post" style="margin-top:16px;">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="id" value="<?php echo $request['id']; ?>">
        <div style="display:flex; gap:12px; align-items:flex-start; flex-wrap:wrap;">
          <div>
            <label style="display:block; font-size:12.5px; font-weight:600; margin-bottom:6px;">Amount (RM)</label>
            <input type="text" name="quote_amount"
                   value="<?php echo htmlspecialchars($request['quote_amount'] ?? ''); ?>"
                   placeholder="e.g. 45.00"
                   style="padding:10px 12px; border:1px solid var(--line); border-radius:2px; font-size:14px; width:140px;">
          </div>
          <div style="flex:1; min-width:220px;">
            <label style="display:block; font-size:12.5px; font-weight:600; margin-bottom:6px;">Notes (optional, shown to customer)</label>
            <input type="text" name="quote_notes"
                   value="<?php echo htmlspecialchars($request['quote_notes'] ?? ''); ?>"
                   placeholder="e.g. Covers 12 slides, 2 revision rounds"
                   style="padding:10px 12px; border:1px solid var(--line); border-radius:2px; font-size:14px; width:100%;">
          </div>
        </div>
        <button type="submit" name="send_quote" value="1" class="btn" style="margin-top:14px;">
          <?php echo $request['quote_amount'] !== null ? 'Update quote' : 'Send quote'; ?>
        </button>
      </form>

      <hr style="border:none; border-top:1px solid var(--line); margin:30px 0;">

      <div class="field">
        <div class="label">DELIVERABLE</div>
        <?php if ($request['deliverable_file']): ?>
          <div class="value">
            <a href="uploads/deliverables/<?php echo htmlspecialchars($request['deliverable_file']); ?>" target="_blank" style="color:var(--frame-blue-deep); font-weight:600;">
              <?php echo htmlspecialchars($request['deliverable_file']); ?>
            </a>
          </div>
          <div class="value" style="color:var(--muted); font-size:12.5px; margin-top:4px;">
            Uploaded <?php echo date('M j, Y \a\t g:ia', strtotime($request['deliverable_uploaded_at'])); ?>
          </div>
        <?php else: ?>
          <div class="value" style="color:var(--muted);">No deliverable uploaded yet.</div>
        <?php endif; ?>

        <?php if ($linkedOrder): ?>
          <div class="value" style="font-size:12.5px; margin-top:8px;">
            <?php if ($linkedOrder['status'] === 'paid'): ?>
              <span style="color:#276B33;">&#10003; Order #<?php echo $linkedOrder['id']; ?> is paid — the customer will see a download link as soon as a file is uploaded here.</span>
            <?php else: ?>
              <span style="color:#8A5A00;">Order #<?php echo $linkedOrder['id']; ?> isn't marked paid yet — uploading a file here won't unlock a download for the customer until payment is verified in <a href="verify-payments.php?id=<?php echo $linkedOrder['id']; ?>" style="color:var(--frame-blue-deep); font-weight:600;">Verify Payments</a>.</span>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <div class="value" style="color:var(--muted); font-size:12.5px; margin-top:8px;">
            This request isn't linked to an order — the download will be available to the customer as soon as a file is uploaded here.
          </div>
        <?php endif; ?>
      </div>

      <?php if ($deliverableError): ?>
        <div class="error-list" style="margin-bottom:16px;"><?php echo htmlspecialchars($deliverableError); ?></div>
      <?php endif; ?>

      <form method="post" enctype="multipart/form-data" style="margin-top:12px;">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="id" value="<?php echo $request['id']; ?>">
        <input type="file" name="deliverable" accept=".pdf,.png,.jpg,.jpeg,.pptx,.docx,.zip" required
               style="font-size:13px; margin-bottom:10px;">
        <br>
        <button type="submit" name="upload_deliverable" value="1" class="btn">
          <?php echo $request['deliverable_file'] ? 'Replace file' : 'Upload deliverable'; ?>
        </button>
      </form>
    </div>

  <?php else: ?>

    <div class="panel">
      <?php if (empty($requests)): ?>
        <div class="empty-state">No custom requests yet.</div>
      <?php else: ?>
        <table>
          <thead>
            <tr><th>Name</th><th>Product Type</th><th>Status</th><th>Quote</th><th>Submitted</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($requests as $req): ?>
              <tr>
                <td><?php echo htmlspecialchars($req['name']); ?></td>
                <td><?php echo htmlspecialchars($req['product_type'] ?: '—'); ?></td>
                <td><?php echo statusBadge($req['status']); ?></td>
                <td><?php echo $req['quote_amount'] !== null ? 'RM ' . number_format((float) $req['quote_amount'], 2) : '—'; ?></td>
                <td><?php echo date('d M Y', strtotime($req['created_at'])); ?></td>
                <td><a href="manage-requests.php?id=<?php echo $req['id']; ?>" class="view-btn">View</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

  <?php endif; ?>

</div>

</body>
</html>
