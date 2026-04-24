<?php
/**
 * KIM INVENTORIES — profile.php
 * User profile view and inline edit with SELECT + UPDATE on users table.
 */
session_start();
require_once 'db_connect.php';

// Auth guard
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$toast  = '';

// ─── Handle POST updates ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $field = $_POST['field'] ?? '';
    $value = trim($_POST['value'] ?? '');

    $stmt = $conn->prepare("UPDATE users SET username = ? WHERE user_id = ?");
    $stmt->bind_param("si", $value, $userId);
    $stmt->execute();

    $check = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
    $check->bind_param("si", $value, $userId);
    $check->execute();
    $check->store_result();
    if ($check->num_rows > 0) {
        $toast = 'Email already in use.';
    } else {
        $stmt = $conn->prepare("UPDATE users SET email = ? WHERE user_id = ?");
        $stmt->bind_param("si", $value, $userId);
        $stmt->execute();
    }


    $stmt = $conn->prepare("SELECT user_id, email, username FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();


    $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM products WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $totalProducts = $stmt->get_result()->fetch_assoc()['cnt'];

    $stmt = $conn->prepare("SELECT COALESCE(SUM(quantity),0) AS total FROM products WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stockTotal = $stmt->get_result()->fetch_assoc()['total'];

    // if ($field === 'username' && $value) {
    //     $stmt = $pdo->prepare("UPDATE users SET username = ? WHERE user_id = ?");
    //     $stmt->execute([$value, $userId]);
    //     $_SESSION['username'] = $value;
    //     $toast = 'Name updated!';
    // }
    // elseif ($field === 'email' && $value) {
    //     // Check for duplicate email
    //     $check = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
    //     $check->execute([strtolower($value), $userId]);
    //     if ($check->fetch()) {
    //         $toast = 'Email already in use.';
    //     } else {
    //         $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE user_id = ?");
    //         $stmt->execute([strtolower($value), $userId]);
    //         $_SESSION['email'] = strtolower($value);
    //         $toast = 'Email updated!';
    //     }
    // }

    if ($toast) {
        $_SESSION['toast'] = $toast;
    }
    header('Location: profile.php');
    exit;
}

// Check for toast from redirect
$toast = '';
if (isset($_SESSION['toast'])) {
    $toast = $_SESSION['toast'];
    unset($_SESSION['toast']);
}

// ─── SELECT user data ────────────────────────────────────────────────────────
// $stmt = $pdo->prepare("SELECT user_id, email, username, created_at FROM users WHERE user_id = ?");
// $stmt->execute([$userId]);
// $user = $stmt->fetch();
$stmt = $conn->prepare("SELECT user_id, email, username, created_at FROM users WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$displayName  = $user['username'] ?? '—';
$displayEmail = $user['email'] ?? '—';
$createdAt    = $user['created_at'] ? date('F j, Y', strtotime($user['created_at'])) : '—';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Profile — Kim Inventories</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800;900&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="css/style.css" />
</head>
<body>

<div class="app-wrapper">

  <!-- ===== SIDEBAR ===== -->
  <aside class="sidebar">
    <div class="sidebar-logo">
      <div class="logo-emblem">
        <svg viewBox="0 0 70 70" xmlns="http://www.w3.org/2000/svg">
          <circle cx="35" cy="35" r="32" fill="none" stroke="#3d5a2d" stroke-width="1.5" stroke-dasharray="3 2.5"/>
          <circle cx="35" cy="35" r="26" fill="none" stroke="#3d5a2d" stroke-width="1.5"/>
          <circle cx="35" cy="5"  r="2.5" fill="#3d5a2d"/>
          <circle cx="35" cy="65" r="2.5" fill="#3d5a2d"/>
          <circle cx="5"  cy="35" r="2.5" fill="#3d5a2d"/>
          <circle cx="65" cy="35" r="2.5" fill="#3d5a2d"/>
          <text x="35" y="46" text-anchor="middle" font-size="26" font-weight="900"
                fill="#3d5a2d" font-family="Georgia,serif">K</text>
        </svg>
      </div>
      <div class="logo-text">KIM INVENTORIES</div>
    </div>
    <nav class="sidebar-nav">
      <a href="dashboard.php"     class="nav-item"><i class="bi bi-grid-fill"></i>            Dashboard</a>
      <a href="products.php"      class="nav-item"><i class="bi bi-box-seam"></i>             Products</a>
      <a href="stock-summary.php" class="nav-item"><i class="bi bi-bar-chart-fill"></i>       Stock Summary</a>
      <a href="profile.php"       class="nav-item active"><i class="bi bi-person-fill"></i>   Profile</a>
    </nav>
    <div class="sidebar-bottom">
      <button class="logout-btn" onclick="window.location.href='logout.php'">
        <i class="bi bi-box-arrow-right"></i> Log out
      </button>
    </div>
  </aside>

  <!-- ===== MAIN CONTENT ===== -->
  <main class="main-content">

    <h1 class="page-title">Profile</h1>

    <!-- Outer dashed wrapper card -->
    <div class="content-card">
      <div style="display:grid; grid-template-columns:1fr 1.2fr; gap:24px; align-items:start;">

        <!-- ── Left Card: Contact Info ── -->
        <div class="content-card-solid">

          <!-- Photo area -->
          <div class="profile-photo-area">
            <div class="photo-circle" id="photoCircle" onclick="document.getElementById('photoInput').click()" title="Click to change photo">
              <i class="bi bi-person-fill" id="photoIcon"></i>
              <img id="photoPreview" src="" alt="Profile Photo" style="display:none;" />
            </div>
            <button class="upload-photo-btn" onclick="document.getElementById('photoInput').click()">
              <i class="bi bi-upload"></i> Upload Photo
            </button>
            <input type="file" id="photoInput" accept="image/*" style="display:none;" onchange="handlePhotoUpload(event)" />
          </div>

          <!-- Name Field -->
          <div class="profile-field">
            <div class="profile-field-info" style="flex:1;">
              <div class="field-label">Your Name</div>
              <div class="field-value" id="nameVal"><?= htmlspecialchars($displayName) ?></div>
              <form method="POST" action="profile.php" style="display:none;" id="nameForm">
                <input type="hidden" name="field" value="username" />
                <input class="field-edit-input" type="text" id="nameInput" name="value" placeholder="Full name" value="<?= htmlspecialchars($displayName) ?>" />
                <div class="field-edit-actions" style="display:flex;">
                  <button type="submit" class="btn-save">Save</button>
                  <button type="button" class="btn-cancel-sm" onclick="cancelField('name')">Cancel</button>
                </div>
              </form>
            </div>
            <button class="btn-edit" id="nameEditBtn" onclick="editField('name')">Edit</button>
          </div>

          <!-- Email Field -->
          <div class="profile-field">
            <div class="profile-field-info" style="flex:1;">
              <div class="field-label">Email</div>
              <div class="field-value" id="emailVal"><?= htmlspecialchars($displayEmail) ?></div>
              <form method="POST" action="profile.php" style="display:none;" id="emailForm">
                <input type="hidden" name="field" value="email" />
                <input class="field-edit-input" type="email" id="emailInput" name="value" placeholder="Email address" value="<?= htmlspecialchars($displayEmail) ?>" />
                <div class="field-edit-actions" style="display:flex;">
                  <button type="submit" class="btn-save">Save</button>
                  <button type="button" class="btn-cancel-sm" onclick="cancelField('email')">Cancel</button>
                </div>
              </form>
            </div>
            <button class="btn-edit" id="emailEditBtn" onclick="editField('email')">Edit</button>
          </div>

          <!-- Member Since (read-only) -->
          <div class="profile-field">
            <div class="profile-field-info" style="flex:1;">
              <div class="field-label">Member Since</div>
              <div class="field-value"><?= htmlspecialchars($createdAt) ?></div>
            </div>
          </div>

        </div><!-- /.content-card-solid (left) -->

        <!-- ── Right Card: About ── -->
        <div class="content-card-solid">

          <div class="about-card-header">
            <div class="about-card-title">
              About <span><?= htmlspecialchars($displayName) ?></span>
            </div>
          </div>

          <p class="about-text">
            Welcome to Kim Inventories! This is the profile page for <strong><?= htmlspecialchars($displayName) ?></strong>.
            You can edit your name and email using the Edit buttons on the left.
          </p>

          <!-- Account Stats -->
          <?php
            // $prodCount = $pdo->prepare("SELECT COUNT(*) FROM products WHERE user_id = ?");
            // $prodCount->execute([$userId]);
            // $totalProducts = $prodCount->fetchColumn();

            // $totalStock = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM products WHERE user_id = ?");
            // $totalStock->execute([$userId]);
            // $stockTotal = $totalStock->fetchColumn();
            $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM products WHERE user_id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $totalProducts = $stmt->get_result()->fetch_assoc()['cnt'];

            $stmt = $conn->prepare("SELECT COALESCE(SUM(quantity), 0) AS total FROM products WHERE user_id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $stockTotal = $stmt->get_result()->fetch_assoc()['total'];
          ?>
          <div style="margin-top:20px; padding-top:16px; border-top:1px dashed var(--border-color);">
            <div class="field-label" style="margin-bottom:12px;">Account Summary</div>
            <div style="display:flex; gap:16px;">
              <div class="stat-card" style="flex:1; padding:14px;">
                <div class="stat-label">Products</div>
                <div class="stat-value" style="font-size:20px;"><?= $totalProducts ?></div>
              </div>
              <div class="stat-card" style="flex:1; padding:14px;">
                <div class="stat-label">Total Stock</div>
                <div class="stat-value" style="font-size:20px;"><?= number_format($stockTotal) ?></div>
              </div>
            </div>
          </div>

        </div><!-- /.content-card-solid (right) -->

      </div><!-- /.grid -->
    </div><!-- /.content-card -->

  </main>
</div><!-- /.app-wrapper -->

<!-- Toast -->
<div class="toast-notif" id="toastNotif"></div>

<!-- Scripts -->
<script src="js/auth.js"></script>
<script>
  // ─── Inline Field Edit ─────────────────────────────────────────────────────
  function editField(field) {
    document.getElementById(field + 'Val').style.display     = 'none';
    document.getElementById(field + 'EditBtn').style.display = 'none';
    document.getElementById(field + 'Form').style.display    = 'block';
    document.getElementById(field + 'Input').focus();
  }

  function cancelField(field) {
    document.getElementById(field + 'Form').style.display    = 'none';
    document.getElementById(field + 'Val').style.display     = 'block';
    document.getElementById(field + 'EditBtn').style.display = '';
  }

  // ─── Photo Upload (preview only) ──────────────────────────────────────────
  function handlePhotoUpload(event) {
    var file = event.target.files[0];
    if (!file) return;
    var reader = new FileReader();
    reader.onload = function(e) {
      var preview = document.getElementById('photoPreview');
      var icon    = document.getElementById('photoIcon');
      preview.src           = e.target.result;
      preview.style.display = 'block';
      icon.style.display    = 'none';
    };
    reader.readAsDataURL(file);
    showToast('Photo updated! (preview only)');
  }

  // ─── Show toast if redirected with message ─────────────────────────────────
  <?php if ($toast): ?>
    showToast('<?= addslashes($toast) ?>');
  <?php endif; ?>
</script>

</body>
</html>
