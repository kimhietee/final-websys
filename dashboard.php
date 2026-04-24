<?php
/**
 * KIM INVENTORIES — dashboard.php
 * Dashboard with stats, recent products, and category breakdown from MySQL.
 */
// $db_name = "kim_inventories";
// $conn = new mysqli("localhost", "root", "", $db_name);

// if ($conn->connect_error)
//     die("Connection failed: " . $conn->connect_error);

session_start();
require_once 'db_connect.php';

// Auth guard
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId   = $_SESSION['user_id'];
$username = $_SESSION['username'];

// ─── SELECT stats ────────────────────────────────────────────────────────────
// $stats = $pdo->prepare("
//     SELECT
//         COALESCE(SUM(quantity), 0) AS total_stock,
//         COALESCE(SUM(price * quantity), 0) AS total_value,
//         SUM(CASE WHEN quantity > 0 AND quantity < 10 THEN 1 ELSE 0 END) AS low_stock,
//         SUM(CASE WHEN quantity = 0 THEN 1 ELSE 0 END) AS out_of_stock
//     FROM products WHERE user_id = ?
// ");

$stmt = $conn->prepare("
                        SELECT 
                              COALESCE(SUM(quantity),0) AS total_stock, 
                              COALESCE(SUM(price*quantity),0) AS total_value, 
                              SUM(CASE WHEN quantity > 0 AND quantity < 10 THEN 1 ELSE 0 END) AS low_stock, 
                              SUM(CASE WHEN quantity = 0 THEN 1 ELSE 0 END) AS out_of_stock
                        FROM products WHERE user_id = ?
                        ");
$stmt->bind_param("i", $userId);
$stmt->execute();
$s = $stmt->get_result()->fetch_assoc();
$stats->execute([$userId]);
$s = $stats->fetch();

// ─── SELECT recent 6 products ────────────────────────────────────────────────
// $recent = $pdo->prepare("
//     SELECT p.product_name, c.category_name, p.quantity, p.price, p.unit
//     FROM products p
//     JOIN category c ON p.category_id = c.category_id
//     WHERE p.user_id = ?
//     ORDER BY p.product_id DESC
//     LIMIT 6
// ");
// $recent->execute([$userId]);
// $recentProducts = $recent->fetchAll();

$stmt = $conn->prepare("
  SELECT p.product_name, c.category_name, p.quantity, p.price 
  FROM products p 
  JOIN category c ON p.category_id = c.category_id 
  WHERE p.user_id = ? 
  ORDER BY p.product_id 
  DESC LIMIT 6
  ");
$stmt->bind_param("i", $userId);
$stmt->execute();
$recentProducts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
// ─── SELECT category totals ─────────────────────────────────────────────────
// $catTotals = $pdo->prepare("
//     SELECT c.category_name, COALESCE(SUM(p.quantity), 0) AS total_qty
//     FROM category c
//     LEFT JOIN products p ON c.category_id = p.category_id AND p.user_id = ?
//     GROUP BY c.category_id, c.category_name
//     HAVING total_qty > 0
//     ORDER BY total_qty DESC
// ");
// $catTotals->execute([$userId]);
// $categoryData = $catTotals->fetchAll();
$stmt = $conn->prepare("
  SELECT c.category_name, COALESCE(SUM(p.quantity),0) AS total_qty 
  FROM category c LEFT 
  JOIN products p ON c.category_id = p.category_id AND p.user_id = ? 
  GROUP BY c.category_id, c.category_name 
  HAVING total_qty > 0 
  ORDER BY total_qty DESC
  ");
$stmt->bind_param("i", $userId);
$stmt->execute();
$categoryData = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);


$maxQty = 1;
foreach ($categoryData as $cd) {
    if ($cd['total_qty'] > $maxQty) $maxQty = $cd['total_qty'];
}

// Greeting
$hour = (int)date('G');
if ($hour < 12) $greeting = 'Good morning';
elseif ($hour < 17) $greeting = 'Good afternoon';
else $greeting = 'Good evening';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Dashboard — Kim Inventories</title>
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
      <a href="dashboard.php"     class="nav-item active"><i class="bi bi-grid-fill"></i>    Dashboard</a>
      <a href="products.php"      class="nav-item"><i class="bi bi-box-seam"></i>            Products</a>
      <a href="stock-summary.php" class="nav-item"><i class="bi bi-bar-chart-fill"></i>      Stock Summary</a>
      <a href="profile.php"       class="nav-item"><i class="bi bi-person-fill"></i>         Profile</a>
    </nav>

    <div class="sidebar-bottom">
      <button class="logout-btn" onclick="window.location.href='logout.php'">
        <i class="bi bi-box-arrow-right"></i> Log out
      </button>
    </div>
  </aside>

  <!-- ===== MAIN CONTENT ===== -->
  <main class="main-content">

    <!-- Welcome Banner -->
    <div class="welcome-banner">
      <h2><?= htmlspecialchars("$greeting, $username!") ?> 👋</h2>
      <p>Here's a quick overview of your inventory today.</p>
    </div>

    <!-- Stat Cards -->
    <div class="stat-cards">
      <div class="stat-card">
        <div class="stat-label">Total Stock</div>
        <div class="stat-value"><?= number_format($s['total_stock']) ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Total Value</div>
        <div class="stat-value">₱<?= number_format($s['total_value'], 0) ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Low Stock Products</div>
        <div class="stat-value"><?= (int)$s['low_stock'] ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Out of Stock</div>
        <div class="stat-value"><?= (int)$s['out_of_stock'] ?></div>
      </div>
    </div>

    <!-- Dashboard Grid: Recent Products + Quick Category -->
    <div class="dashboard-grid">

      <!-- Recent Products -->
      <div class="content-card-solid">
        <div class="section-heading">Recent Products</div>
        <table class="recent-table">
          <thead>
            <tr>
              <th>Product</th>
              <th>Category</th>
              <th>Qty</th>
              <th>Price</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($recentProducts)): ?>
              <tr><td colspan="5" style="text-align:center;color:var(--text-light);">No products yet.</td></tr>
            <?php else: ?>
              <?php foreach ($recentProducts as $p): ?>
                <?php
                  $qty = (int)$p['quantity'];
                  if ($qty === 0) { $badgeCls = 'badge-out-stock'; $badgeTxt = 'Out of Stock'; }
                  elseif ($qty < 10) { $badgeCls = 'badge-low-stock'; $badgeTxt = 'Low Stock'; }
                  else { $badgeCls = 'badge-in-stock'; $badgeTxt = 'In Stock'; }
                ?>
                <tr>
                  <td class="product-name-cell"><?= htmlspecialchars($p['product_name']) ?></td>
                  <td><?= htmlspecialchars($p['category_name']) ?></td>
                  <td><?= $qty ?></td>
                  <td class="product-price">₱<?= number_format($p['price'], 0) ?></td>
                  <td><span class="badge-status <?= $badgeCls ?>"><?= $badgeTxt ?></span></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
        <div style="margin-top:14px; text-align:right;">
          <a href="products.php" class="btn-primary-green" style="font-size:13px; padding:7px 18px; border-radius:8px;">
            View All Products <i class="bi bi-arrow-right"></i>
          </a>
        </div>
      </div>

      <!-- Category Snapshot -->
      <div class="content-card-solid">
        <div class="section-heading">Stock by Category</div>
        <?php if (empty($categoryData)): ?>
          <p style="color:var(--text-light);font-size:13.5px;">No stock data yet.</p>
        <?php else: ?>
          <?php foreach ($categoryData as $cd): ?>
            <?php $pct = round(($cd['total_qty'] / $maxQty) * 100); ?>
            <div style="margin-bottom:16px;">
              <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:5px;">
                <span style="font-size:13.5px;font-weight:700;color:var(--text-dark);"><?= htmlspecialchars($cd['category_name']) ?></span>
                <span style="font-size:13px;font-weight:700;color:var(--primary-dark);"><?= $cd['total_qty'] ?> units</span>
              </div>
              <div style="background:var(--card-bg);border-radius:20px;height:8px;overflow:hidden;">
                <div style="background:var(--primary);width:<?= $pct ?>%;height:100%;border-radius:20px;transition:width 0.6s ease;"></div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

    </div><!-- /.dashboard-grid -->

  </main>

</div><!-- /.app-wrapper -->

<!-- Toast -->
<div class="toast-notif" id="toastNotif"></div>

<script src="js/auth.js"></script>

</body>
</html>
