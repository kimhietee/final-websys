<?php
/**
 * KIM INVENTORIES — stock-summary.php
 * Stock summary with charts, all data from MySQL SELECTs.
 */
session_start();
require_once 'db_connect.php';

// Auth guard
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];

// ─── SELECT stats ────────────────────────────────────────────────────────────
$stats = $pdo->prepare("
    SELECT
        COALESCE(SUM(quantity), 0) AS total_stock,
        COALESCE(SUM(price * quantity), 0) AS total_value,
        SUM(CASE WHEN quantity > 0 AND quantity < 10 THEN 1 ELSE 0 END) AS low_stock,
        SUM(CASE WHEN quantity = 0 THEN 1 ELSE 0 END) AS out_of_stock,
        SUM(CASE WHEN quantity >= 10 THEN 1 ELSE 0 END) AS in_stock,
        COUNT(*) AS total_products
    FROM products WHERE user_id = ?
");
$stats->execute([$userId]);
$s = $stats->fetch();

$totalProducts = (int)$s['total_products'];
$inStockCount  = (int)$s['in_stock'];
$lowStockCount = (int)$s['low_stock'];
$outStockCount = (int)$s['out_of_stock'];
$inStockPct    = $totalProducts > 0 ? round(($inStockCount / $totalProducts) * 100) : 0;

// ─── SELECT category totals for bar chart ────────────────────────────────────
$catTotals = $pdo->prepare("
    SELECT c.category_name, COALESCE(SUM(p.quantity), 0) AS total_qty
    FROM category c
    LEFT JOIN products p ON c.category_id = p.category_id AND p.user_id = ?
    GROUP BY c.category_id, c.category_name
    ORDER BY c.category_id
");
$catTotals->execute([$userId]);
$categoryData = $catTotals->fetchAll();

$catLabels = [];
$catValues = [];
foreach ($categoryData as $cd) {
    $catLabels[] = $cd['category_name'];
    $catValues[] = (int)$cd['total_qty'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Stock Summary — Kim Inventories</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800;900&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="css/style.css" />
  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
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
      <a href="dashboard.php"     class="nav-item"><i class="bi bi-grid-fill"></i>              Dashboard</a>
      <a href="products.php"      class="nav-item"><i class="bi bi-box-seam"></i>               Products</a>
      <a href="stock-summary.php" class="nav-item active"><i class="bi bi-bar-chart-fill"></i>  Stock Summary</a>
      <a href="profile.php"       class="nav-item"><i class="bi bi-person-fill"></i>            Profile</a>
    </nav>
    <div class="sidebar-bottom">
      <button class="logout-btn" onclick="window.location.href='logout.php'">
        <i class="bi bi-box-arrow-right"></i> Log out
      </button>
    </div>
  </aside>

  <!-- ===== MAIN CONTENT ===== -->
  <main class="main-content">

    <h1 class="page-title">Stock Summary</h1>

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
        <div class="stat-value"><?= $lowStockCount ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Out of Stock</div>
        <div class="stat-value"><?= $outStockCount ?></div>
      </div>
    </div>

    <!-- Charts Row -->
    <div class="content-card">
      <div class="chart-grid">

        <!-- Bar Chart: Stock Overview -->
        <div>
          <div class="section-heading">Stock Overview</div>
          <div class="chart-wrapper">
            <canvas id="barChart"></canvas>
          </div>
        </div>

        <!-- Donut Chart: Stock Status -->
        <div>
          <div class="section-heading">Stock Status</div>
          <div class="donut-wrapper">
            <canvas id="donutChart"></canvas>
          </div>
          <div class="donut-legend" id="donutLegend"></div>
        </div>

      </div>
    </div>

  </main>
</div><!-- /.app-wrapper -->

<!-- Toast -->
<div class="toast-notif" id="toastNotif"></div>

<!-- Scripts -->
<script src="js/auth.js"></script>
<script>
  // ─── Bar Chart: Stock by Category ─────────────────────────────────────────
  var catLabels = <?= json_encode($catLabels) ?>;
  var catValues = <?= json_encode($catValues) ?>;

  new Chart(document.getElementById('barChart'), {
    type: 'bar',
    data: {
      labels: catLabels,
      datasets: [{
        data: catValues,
        backgroundColor: 'rgba(88,122,66,0.25)',
        borderColor:     'rgba(88,122,66,0.8)',
        borderWidth: 2,
        borderRadius: 6,
        borderSkipped: false
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: function(ctx) { return ' ' + ctx.parsed.y + ' units'; }
          }
        }
      },
      scales: {
        x: {
          grid: { display: false },
          ticks: {
            font: { family: "'Nunito', sans-serif", size: 12, weight: '600' },
            color: '#4a5e40'
          }
        },
        y: {
          beginAtZero: true,
          grid: { color: 'rgba(180,210,150,0.25)' },
          ticks: {
            font: { family: "'Nunito', sans-serif", size: 11 },
            color: '#7a8e72',
            stepSize: 100
          }
        }
      }
    }
  });

  // ─── Donut Chart: Stock Status ─────────────────────────────────────────────
  var inStockCount  = <?= $inStockCount ?>;
  var lowStockCount = <?= $lowStockCount ?>;
  var outStockCount = <?= $outStockCount ?>;
  var inStockPct    = <?= $inStockPct ?>;

  var centerTextPlugin = {
    id: 'centerText',
    afterDraw: function(chart) {
      if (chart.config.type !== 'doughnut') return;
      var ctx = chart.ctx;
      var area = chart.chartArea;
      var cx = area.left + (area.right - area.left) / 2;
      var cy = area.top + (area.bottom - area.top) / 2;
      ctx.save();
      ctx.textAlign    = 'center';
      ctx.textBaseline = 'middle';
      ctx.font         = 'bold 22px Nunito, sans-serif';
      ctx.fillStyle    = '#222d1a';
      ctx.fillText(inStockPct + '%', cx, cy - 10);
      ctx.font         = '600 12px Nunito, sans-serif';
      ctx.fillStyle    = '#4a5e40';
      ctx.fillText('In Stock', cx, cy + 12);
      ctx.restore();
    }
  };

  new Chart(document.getElementById('donutChart'), {
    type: 'doughnut',
    plugins: [centerTextPlugin],
    data: {
      labels: ['In Stock', 'Low Stock', 'Out of Stock'],
      datasets: [{
        data: [inStockCount, lowStockCount, outStockCount],
        backgroundColor: ['#4caf50', '#f59e0b', '#ef4444'],
        borderColor: '#fff',
        borderWidth: 3,
        hoverOffset: 6
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: '68%',
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: function(ctx) { return ' ' + ctx.label + ': ' + ctx.parsed + ' product(s)'; }
          }
        }
      }
    }
  });

  // ─── Legend ────────────────────────────────────────────────────────────────
  var legendData = [
    { label: 'In Stock',     color: '#4caf50', count: inStockCount  },
    { label: 'Low Stock',    color: '#f59e0b', count: lowStockCount  },
    { label: 'Out of Stock', color: '#ef4444', count: outStockCount  }
  ];
  var legendEl = document.getElementById('donutLegend');
  legendData.forEach(function(item) {
    legendEl.innerHTML += '<div class="legend-item">' +
      '<span class="legend-dot" style="background:' + item.color + ';"></span>' +
      '<span>' + item.label + ':</span>' +
      '<span style="margin-left:auto;font-weight:800;color:var(--text-dark);">' + item.count + '</span>' +
      '</div>';
  });
</script>

</body>
</html>
