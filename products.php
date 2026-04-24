<?php
/**
 * KIM INVENTORIES — products.php
 * Create (INSERT) and Read (SELECT) operations on products table.
 */
session_start();
require_once 'db_connect.php';
require_once 'constants.php';

// Auth guard
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];

// ─── Handle POST actions ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action == 'add') {
        $name       = trim($_POST['product_name'] ?? '');
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $price      = floatval($_POST['price'] ?? 0);
        $quantity   = (int)($_POST['quantity'] ?? 0);

        if ($name && $categoryId > 0 && $price >= 0 && $quantity >= 0) {
            $stmt = $conn->prepare("
                INSERT INTO products (user_id, category_id, product_name, price, quantity) 
                VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iisdi", $userId, $categoryId, $name, $price, $quantity);
            $stmt->execute();
            $_SESSION['toast'] = 'Product added successfully!';
        }
    }
    elseif ($action == 'edit') {
        $productId  = (int)($_POST['product_id'] ?? 0);
        $name       = trim($_POST['product_name'] ?? '');
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $price      = floatval($_POST['price'] ?? 0);
        $quantity   = (int)($_POST['quantity'] ?? 0);

        if ($productId > 0 && $name && $categoryId > 0) {
            $stmt = $conn->prepare("
                UPDATE products SET product_name = ?, category_id = ?, price = ?, quantity = ? 
                WHERE product_id = ? AND user_id = ?");
            $stmt->bind_param("ssdiii", $name, $categoryId, $price, $quantity, $productId, $userId);
            $stmt->execute();
            $_SESSION['toast'] = 'Product updated successfully!';
        }
    }
    elseif ($action == 'delete') {
        $productId = (int)($_POST['product_id'] ?? 0);
        if ($productId > 0) {
            $stmt = $conn->prepare("DELETE FROM products WHERE product_id = ? AND user_id = ?");
            $stmt->bind_param("ii", $productId, $userId);
            $stmt->execute();
            $_SESSION['toast'] = 'Product deleted.';
        }
    }
    elseif ($action == 'add_category') {
        $name = trim($_POST['category_name'] ?? '');

        if ($name) {

            // check if category already exists
            $check = $conn->prepare("SELECT category_id FROM category WHERE category_name = ?");
            $check->bind_param("s", $name);
            $check->execute();
            $result = $check->get_result();

            if ($result->num_rows > 0) {
                $_SESSION['toast'] = 'Category already exists!';
            } else {
                $stmt = $conn->prepare("INSERT INTO category (category_name) VALUES (?)");
                $stmt->bind_param("s", $name);
                $stmt->execute();

                $_SESSION['toast'] = 'Category added!';
            }
        }
    }

    header('Location: products.php');
    exit;
}

// Check for toast message from redirect
$toast = '';
if (isset($_SESSION['toast'])) {
    $toast = $_SESSION['toast'];
    unset($_SESSION['toast']);
}

// ─── SELECT all products with category names ─────────────────────────────────
$stmt = $conn->prepare("
    SELECT p.product_id, p.product_name, c.category_name, c.category_id, p.price, p.quantity
    FROM products p
    JOIN category c ON p.category_id = c.category_id
    WHERE p.user_id = ?
    ORDER BY p.product_id ASC
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ─── SELECT all categories for dropdown ──────────────────────────────────────
$result = $conn->query("SELECT category_id, category_name FROM category ORDER BY category_id");
$categories = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Products — Kim Inventories</title>
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
      <a href="dashboard.php"     class="nav-item"><i class="bi bi-grid-fill"></i>           Dashboard</a>
      <a href="products.php"      class="nav-item active"><i class="bi bi-box-seam"></i>     Products</a>
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

    <!-- Page Header  (add product and category-->
    <div class="page-header-row">
    <h1 class="page-title">Products</h1>

    <div style="display:flex; gap:10px;">
      <button class="btn-primary-green" onclick="openCategoryModal()" style="background:#6c8f4e;">
        <i class="bi bi-tags"></i> Add Category
      </button>

      <button class="btn-primary-green" onclick="openAddModal()">
        <i class="bi bi-plus-lg"></i> Add Product
      </button>
    </div>
  </div>

    <!-- Filter Bar -->
    <div class="filter-bar">
      <div class="search-wrapper">
        <i class="bi bi-search"></i>
        <input type="text" class="search-input" id="searchInput"
               placeholder="Search products…" oninput="filterTable()" />
      </div>
      <select class="filter-select" id="catFilter" onchange="filterTable()">
        <option value="">All Categories</option>
        <?php foreach ($categories as $cat): ?>
          <option value="<?= htmlspecialchars($cat['category_name']) ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
        <?php endforeach; ?>
      </select>
      <select class="filter-select" id="statusFilter" onchange="filterTable()">
        <option value="">All Status</option>
        <option value="in-stock">In Stock</option>
        <option value="low-stock">Low Stock</option>
        <option value="out-of-stock">Out of Stock</option>
      </select>
    </div>

    <!-- Products Table -->
    <div class="content-card" style="padding:0; overflow:hidden; border-radius:var(--radius-lg);">
      <table class="products-table" id="productsTable">
        <thead>
          <tr>
            <th>#</th>
            <th>Product Name</th>
            <th>Category</th>
            <th>Unit Price</th>
            <th>Quantity</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="productsTbody">
          <?php if (empty($products)): ?>
            <tr class="no-results"><td colspan="7">No products found.</td></tr>
          <?php else: ?>
            <?php foreach ($products as $i => $p): ?>
              <?php
                $qty = (int)$p['quantity'];
                if ($qty === 0)    { $status = 'out-of-stock'; $badgeCls = 'badge-out-stock'; $badgeTxt = 'Out of Stock'; }
                elseif ($qty < LOW_STOCK_THRESHOLD) { $status = 'low-stock';    $badgeCls = 'badge-low-stock'; $badgeTxt = 'Low Stock'; }
                else               { $status = 'in-stock';     $badgeCls = 'badge-in-stock';  $badgeTxt = 'In Stock'; }
              ?>
              <tr data-name="<?= htmlspecialchars(strtolower($p['product_name'])) ?>"
                  data-category="<?= htmlspecialchars($p['category_name']) ?>"
                  data-status="<?= $status ?>">
                <td style="color:var(--text-light);font-weight:700;"><?= $i + 1 ?></td>
                <td class="product-name"><?= htmlspecialchars($p['product_name']) ?></td>
                <td><?= htmlspecialchars($p['category_name']) ?></td>
                <td class="product-price">₱<?= number_format($p['price'], 0) ?></td>
                <td><?= $qty ?></td>
                <td><span class="badge-status <?= $badgeCls ?>"><?= $badgeTxt ?></span></td>
                <td>
                  <div class="td-actions">
                    <button class="btn-edit" onclick='openEditModal(<?= json_encode([
                        "id"          => $p["product_id"],
                        "name"        => $p["product_name"],
                        "category_id" => $p["category_id"],
                        "price"       => $p["price"],
                        "quantity"    => $p["quantity"]
                    ]) ?>)'>
                      <i class="bi bi-pencil"></i> Edit
                    </button>
                    <button class="btn-danger-sm" onclick="openDeleteModal(<?= $p['product_id'] ?>, '<?= htmlspecialchars(addslashes($p['product_name'])) ?>')">
                      <i class="bi bi-trash"></i>
                    </button>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Row count -->
    <div style="margin-top:12px; font-size:13px; color:var(--text-light); font-weight:600;">
      Showing <span id="rowCount"><?= count($products) ?></span> product(s)
    </div>

  </main>
</div><!-- /.app-wrapper -->

<!-- ===== ADD / EDIT MODAL ===== -->
<div class="modal-overlay" id="productModal">
  <div class="modal-box">
    <div class="modal-header">
      <div class="modal-title" id="modalTitle">Add Product</div>
      <button class="modal-close" onclick="closeModal()">&times;</button>
    </div>
    <form class="modal-form" id="productForm" method="POST" action="products.php" novalidate>
      <input type="hidden" name="action" id="formAction" value="add" />
      <input type="hidden" name="product_id" id="editId" value="" />
      <div class="form-group">
        <label for="prodName">Product Name</label>
        <input type="text" id="prodName" name="product_name" placeholder="e.g. Americano" required />
      </div>
      <div class="form-row">
        <div class="form-group">
          <label for="prodCategory">Category</label>
          <select id="prodCategory" name="category_id" required>
            <option value="">Select…</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?= $cat['category_id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label for="prodPrice">Unit Price (₱)</label>
          <input type="number" id="prodPrice" name="price" placeholder="0" min="0" step="0.01" required />
        </div>
        <div class="form-group">
          <label for="prodQty">Quantity</label>
          <input type="number" id="prodQty" name="quantity" placeholder="0" min="0" step="1" required />
        </div>
      </div>
      <!-- Alert inside modal -->
      <div class="alert-msg alert-error" id="modalError"></div>
      <div class="modal-actions">
        <button type="button" class="btn-cancel-sm" onclick="closeModal()">Cancel</button>
        <button type="submit" class="btn-primary-green" style="border-radius:var(--radius-sm);padding:9px 22px;">Save Product</button>
      </div>
    </form>
  </div>
</div>

<!-- ===== DELETE CONFIRM MODAL ===== -->
<div class="modal-overlay" id="deleteModal">
  <div class="modal-box" style="max-width:380px;">
    <div class="modal-header">
      <div class="modal-title" style="color:#c62828;">Delete Product</div>
      <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
    </div>
    <p style="font-size:14px;color:var(--text-medium);margin-bottom:20px;">
      Are you sure you want to delete <strong id="deleteProductName"></strong>?
      This action cannot be undone.
    </p>
    <form method="POST" action="products.php" id="deleteForm">
      <input type="hidden" name="action" value="delete" />
      <input type="hidden" name="product_id" id="deleteProductId" value="" />
      <div class="modal-actions">
        <button type="button" class="btn-cancel-sm" onclick="closeDeleteModal()">Cancel</button>
        <button type="submit" class="btn-danger-sm" style="padding:9px 20px;font-size:13.5px;">
          Yes, Delete
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ===== CATEGORY MODAL ===== -->
<div class="modal-overlay" id="categoryModal">
  <div class="modal-box" style="max-width:400px;">
    <div class="modal-header">
      <div class="modal-title">Add Category</div>
      <button class="modal-close" onclick="closeCategoryModal()">&times;</button>
    </div>

    <form method="POST" action="products.php">
      <input type="hidden" name="action" value="add_category">

      <div class="form-group">
        <label>Category Name</label>
        <input type="text" name="category_name" placeholder="e.g. Drinks" required>
      </div>

      <div class="modal-actions">
        <button type="button" class="btn-cancel-sm" onclick="closeCategoryModal()">Cancel</button>
        <button type="submit" class="btn-primary-green">Save</button>
      </div>
    </form>
  </div>
</div>

<!-- Toast -->
<div class="toast-notif" id="toastNotif"></div>

<!-- Scripts -->
<script src="js/auth.js"></script>
<script>
  // ─── Client-side filter (no page reload) ───────────────────────────────────
  function filterTable() {
    const search    = document.getElementById('searchInput').value.toLowerCase();
    const catFilter = document.getElementById('catFilter').value;
    const stFilter  = document.getElementById('statusFilter').value;
    const rows      = document.querySelectorAll('#productsTbody tr[data-name]');
    let visible = 0;

    rows.forEach(function(row) {
      const name     = row.getAttribute('data-name');
      const category = row.getAttribute('data-category');
      const status   = row.getAttribute('data-status');

      const matchSearch = name.includes(search) || category.toLowerCase().includes(search);
      const matchCat    = !catFilter  || category === catFilter;
      const matchStatus = !stFilter   || status === stFilter;

      if (matchSearch && matchCat && matchStatus) {
        row.style.display = '';
        visible++;
      } else {
        row.style.display = 'none';
      }
    });

    document.getElementById('rowCount').textContent = visible;
  }

  // ─── Add Modal ─────────────────────────────────────────────────────────────
  function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Add Product';
    document.getElementById('formAction').value       = 'add';
    document.getElementById('editId').value           = '';
    document.getElementById('productForm').reset();
    document.getElementById('modalError').style.display = 'none';
    document.getElementById('productModal').classList.add('active');
  }

  // ─── Edit Modal ────────────────────────────────────────────────────────────
  function openEditModal(product) {
    document.getElementById('modalTitle').textContent  = 'Edit Product';
    document.getElementById('formAction').value        = 'edit';
    document.getElementById('editId').value            = product.id;
    document.getElementById('prodName').value          = product.name;
    document.getElementById('prodCategory').value      = product.category_id;
    document.getElementById('prodPrice').value         = product.price;
    document.getElementById('prodQty').value           = product.quantity;
    document.getElementById('modalError').style.display = 'none';
    document.getElementById('productModal').classList.add('active');
  }

  function closeModal() {
    document.getElementById('productModal').classList.remove('active');
  }

  // ─── Client-side validation before form submit ─────────────────────────────
  document.getElementById('productForm').addEventListener('submit', function(e) {
    var name     = document.getElementById('prodName').value.trim();
    var category = document.getElementById('prodCategory').value;
    var price    = parseFloat(document.getElementById('prodPrice').value);
    var quantity = parseInt(document.getElementById('prodQty').value, 10);
    var errorEl  = document.getElementById('modalError');

    errorEl.style.display = 'none';

    if (!name || !category || isNaN(price) || isNaN(quantity) || price < 0 || quantity < 0) {
      e.preventDefault();
      errorEl.textContent = 'Please fill in all required fields with valid values.';
      errorEl.style.display = 'block';
      return;
    }
  });

  // ─── Delete Modal ──────────────────────────────────────────────────────────
  function openDeleteModal(id, name) {
    document.getElementById('deleteProductId').value        = id;
    document.getElementById('deleteProductName').textContent = name;
    document.getElementById('deleteModal').classList.add('active');
  }

  function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('active');
  }

  // ─── category modal ──────────────────────────────────────────────────────────
  function openCategoryModal() {
  document.getElementById('categoryModal').classList.add('active');
  }

  function closeCategoryModal() {
    document.getElementById('categoryModal').classList.remove('active');
  }

  // Close modal on overlay click
  document.getElementById('productModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
  });
  document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) closeDeleteModal();
  });

  // ─── Show toast if redirected with message ─────────────────────────────────
  <?php if ($toast): ?>
    showToast('<?= addslashes($toast) ?>');
  <?php endif; ?>
</script>

</body>
</html>