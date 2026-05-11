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
            $stmt->bind_param("sidiii", $name, $categoryId, $price, $quantity, $productId, $userId);
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
    elseif ($action == 'edit_category') {
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $name = trim($_POST['category_name'] ?? '');

        if ($categoryId > 0 && $name) {
            // Check if new name already exists (excluding current category)
            $check = $conn->prepare("SELECT category_id FROM category WHERE category_name = ? AND category_id != ?");
            $check->bind_param("si", $name, $categoryId);
            $check->execute();
            $result = $check->get_result();

            if ($result->num_rows > 0) {
                $_SESSION['toast'] = 'Category name already exists!';
            } else {
                $stmt = $conn->prepare("UPDATE category SET category_name = ? WHERE category_id = ?");
                $stmt->bind_param("si", $name, $categoryId);
                $stmt->execute();
                $_SESSION['toast'] = 'Category updated!';
            }
        }
    }
    elseif ($action == 'delete_category') {
        $categoryId = (int)($_POST['category_id'] ?? 0);

        if ($categoryId > 0) {
            // Check if *this user* has any products in this category
            $check = $conn->prepare("SELECT COUNT(*) AS count FROM products WHERE category_id = ? AND user_id = ?");
            $check->bind_param("ii", $categoryId, $userId);
            $check->execute();
            $result = $check->get_result()->fetch_assoc();

            if ((int)$result['count'] > 0) {
                $_SESSION['toast'] = 'Cannot delete: you still have products in this category!';
            } else {
                $stmt = $conn->prepare("DELETE FROM category WHERE category_id = ?");
                $stmt->bind_param("i", $categoryId);
                $stmt->execute();
                $_SESSION['toast'] = 'Category deleted!';
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

// ─── PAGINATION & FILTER SETUP ─────────────────────────────────────────────
$productsPerPage = 10;
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$currentPage = max(1, $currentPage);

// Read filters from query string (set by client-side code)
$filterCategory = trim($_GET['category'] ?? '');
$filterStatus   = trim($_GET['status']   ?? '');
$filterSearch   = trim($_GET['search']   ?? '');

// Build dynamic WHERE clause and bind params
$whereClauses = [];
$types = '';
$values = [];

// user id always required
$whereClauses[] = 'p.user_id = ?';
$types .= 'i';
$values[] = $userId;

if ($filterCategory !== '') {
  $whereClauses[] = 'c.category_name = ?';
  $types .= 's';
  $values[] = $filterCategory;
}

if ($filterStatus !== '') {
  if ($filterStatus === 'in-stock') {
    $whereClauses[] = 'p.quantity >= ?';
    $types .= 'i';
    $values[] = LOW_STOCK_THRESHOLD;
  } elseif ($filterStatus === 'low-stock') {
    $whereClauses[] = 'p.quantity > 0 AND p.quantity < ?';
    $types .= 'i';
    $values[] = LOW_STOCK_THRESHOLD;
  } elseif ($filterStatus === 'out-of-stock') {
    $whereClauses[] = 'p.quantity = 0';
    // no param
  }
}

if ($filterSearch !== '') {
  $whereClauses[] = '(p.product_name LIKE ? OR c.category_name LIKE ?)';
  $types .= 'ss';
  $like = '%' . $filterSearch . '%';
  $values[] = $like;
  $values[] = $like;
}

$whereSql = '';
if (!empty($whereClauses)) {
  $whereSql = 'WHERE ' . implode(' AND ', $whereClauses);
}

// Get total product count for this user with filters applied
$countSql = "SELECT COUNT(*) AS total FROM products p JOIN category c ON p.category_id = c.category_id " . $whereSql;
$countStmt = $conn->prepare($countSql);
if ($countStmt === false) {
  die('Prepare failed: ' . htmlspecialchars($conn->error));
}
if ($types !== '') {
  // bind params dynamically
  $bindParams = array_merge([$types], $values);
  $refs = [];
  foreach ($bindParams as $key => $val) $refs[$key] = &$bindParams[$key];
  call_user_func_array([$countStmt, 'bind_param'], $refs);
}
$countStmt->execute();
$countResult = $countStmt->get_result()->fetch_assoc();
$totalProducts = (int)$countResult['total'];
$totalPages = max(1, ceil($totalProducts / $productsPerPage));

// Validate and clamp current page
if ($currentPage > $totalPages) {
  $currentPage = $totalPages;
}

$offset = ($currentPage - 1) * $productsPerPage;

// ─── SELECT products with filters + pagination ─────────────────────────────
$selectSql = "SELECT p.product_id, p.product_name, c.category_name, c.category_id, p.price, p.quantity FROM products p JOIN category c ON p.category_id = c.category_id " . $whereSql . " ORDER BY p.product_id ASC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($selectSql);
if ($stmt === false) {
  die('Prepare failed: ' . htmlspecialchars($conn->error));
}

// bind params for select: same filter params plus limit and offset
$selectTypes = $types . 'ii';
$selectValues = $values;
$selectValues[] = $productsPerPage;
$selectValues[] = $offset;

$bindParams = array_merge([$selectTypes], $selectValues);
$refs = [];
foreach ($bindParams as $k => $v) $refs[$k] = &$bindParams[$k];
call_user_func_array([$stmt, 'bind_param'], $refs);
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

  <!-- Mobile Menu Overlay -->
  <div class="sidebar-overlay"></div>

  <!-- ===== MOBILE TOP BAR (mobile-only) ===== -->
  <header class="mobile-top-bar">
    <button class="mobile-menu-btn" title="Toggle menu" aria-controls="sidebar" aria-expanded="false">
      <i class="bi bi-list" aria-hidden="true"></i>
    </button>
    <div class="mobile-top-bar-logo">
      <div class="mobile-logo-emblem">
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
      <div class="mobile-logo-text">KIM INVENTORIES</div>
    </div>
  </header>

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

    <div style="display:flex; gap:10px; align-items:center;">
      <button class="btn-primary-green btn-category" onclick="openCategoryModal()">
        <i class="bi bi-tags"></i> Add Category
      </button>

      <!-- Category Options Modal Button -->
      <button class="btn-primary-green btn-category-opts" onclick="openCategoryOptionsModal()">
        <i class="bi bi-sliders"></i> Category Options
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
      <div class="products-table-wrapper">
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
      </div><!-- /.products-table-wrapper -->
    </div>

    <!-- Row count and Pagination -->
    <div style="margin-top:16px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
      <div style="font-size:13px; color:var(--text-light); font-weight:600;">
        Showing <span id="rowCount"><?= count($products) ?></span> product(s)
        <?php if ($totalPages > 1): ?>
          — Page <?= $currentPage ?> of <?= $totalPages ?>
        <?php endif; ?>
      </div>

      <?php if ($totalPages > 1): 
        // Build query string while preserving existing GET parameters
        $queryParams = $_GET;
        unset($queryParams['page']);
        $baseQuery = http_build_query($queryParams);
        $separator = !empty($baseQuery) ? '&' : '?';
      ?>
      <!-- Pagination Controls -->
      <nav>
        <ul class="pagination pagination-sm mb-0" style="gap:4px;">
          <!-- First Page Button -->
          <li class="page-item <?= $currentPage === 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= !empty($baseQuery) ? '?' . $baseQuery . '&page=1' : '?page=1' ?>" <?= $currentPage === 1 ? 'tabindex="-1" aria-disabled="true"' : '' ?> style="color:#587a42; border-color:#bfddaa;">
              <i class="bi bi-skip-start"></i> First
            </a>
          </li>

          <!-- Previous Page Button -->
          <li class="page-item <?= $currentPage === 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= !empty($baseQuery) ? '?' . $baseQuery . '&page=' . max(1, $currentPage - 1) : '?page=' . max(1, $currentPage - 1) ?>" <?= $currentPage === 1 ? 'tabindex="-1" aria-disabled="true"' : '' ?> style="color:#587a42; border-color:#bfddaa;">
              <i class="bi bi-chevron-left"></i> Prev
            </a>
          </li>

          <!-- Page Indicator -->
          <li class="page-item disabled">
            <span class="page-link" style="background:#587a42; color:white; border-color:#587a42; cursor:default;">
              <?= $currentPage ?> / <?= $totalPages ?>
            </span>
          </li>

          <!-- Next Page Button -->
          <li class="page-item <?= $currentPage === $totalPages ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= !empty($baseQuery) ? '?' . $baseQuery . '&page=' . min($totalPages, $currentPage + 1) : '?page=' . min($totalPages, $currentPage + 1) ?>" <?= $currentPage === $totalPages ? 'tabindex="-1" aria-disabled="true"' : '' ?> style="color:#587a42; border-color:#bfddaa;">
              Next <i class="bi bi-chevron-right"></i>
            </a>
          </li>

          <!-- Last Page Button -->
          <li class="page-item <?= $currentPage === $totalPages ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= !empty($baseQuery) ? '?' . $baseQuery . '&page=' . $totalPages : '?page=' . $totalPages ?>" <?= $currentPage === $totalPages ? 'tabindex="-1" aria-disabled="true"' : '' ?> style="color:#587a42; border-color:#bfddaa;">
              Last <i class="bi bi-skip-end"></i>
            </a>
          </li>
        </ul>
      </nav>
      <?php endif; ?>
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
  <div class="modal-box">
    <div class="modal-header">
      <div class="modal-title">Add Category</div>
      <button class="modal-close" onclick="closeCategoryModal()">&times;</button>
    </div>

    <form class="modal-form" method="POST" action="products.php" novalidate>
      <input type="hidden" name="action" value="add_category">

      <div class="form-group">
        <label for="catName">Category Name</label>
        <input type="text" id="catName" name="category_name" placeholder="e.g. Drinks" required>
      </div>

      <!-- (Optional but keeps design identical to product modal) -->
      <div class="alert-msg alert-error" id="categoryError"></div>

      <div class="modal-actions">
        <button type="button" class="btn-cancel-sm" onclick="closeCategoryModal()">Cancel</button>
        <button type="submit" class="btn-primary-green" style="border-radius:var(--radius-sm);padding:9px 22px;">
          Save Category
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ===== EDIT CATEGORY MODAL ===== -->
<div class="modal-overlay" id="editCategoryModal">
  <div class="modal-box">
    <div class="modal-header">
      <div class="modal-title">Edit Category</div>
      <button class="modal-close" onclick="closeEditCategoryModal()">&times;</button>
    </div>

    <form class="modal-form" method="POST" action="products.php" novalidate>
      <input type="hidden" name="action" value="edit_category">
      <input type="hidden" name="category_id" id="editCategoryId" value="">

      <div class="form-group">
        <label for="editCatName">Category Name</label>
        <input type="text" id="editCatName" name="category_name" placeholder="e.g. Drinks" required>
      </div>

      <div class="alert-msg alert-error" id="editCategoryError"></div>

      <div class="modal-actions">
        <button type="button" class="btn-cancel-sm" onclick="closeEditCategoryModal()">Cancel</button>
        <button type="submit" class="btn-primary-green" style="border-radius:var(--radius-sm);padding:9px 22px;">
          Update Category
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ===== DELETE CATEGORY MODAL ===== -->
<div class="modal-overlay" id="deleteCategoryModal">
  <div class="modal-box" style="max-width:380px;">
    <div class="modal-header">
      <div class="modal-title" style="color:#c62828;">Delete Category</div>
      <button class="modal-close" onclick="closeDeleteCategoryModal()">&times;</button>
    </div>
    <p style="font-size:14px;color:var(--text-medium);margin-bottom:20px;">
      Are you sure you want to delete <strong id="deleteCategoryName"></strong>?
      This action cannot be undone.
    </p>
    <form method="POST" action="products.php">
      <input type="hidden" name="action" value="delete_category" />
      <input type="hidden" name="category_id" id="deleteCategoryId" value="" />
      <div class="modal-actions">
        <button type="button" class="btn-cancel-sm" onclick="closeDeleteCategoryModal()">Cancel</button>
        <button type="submit" class="btn-danger-sm" style="padding:9px 20px;font-size:13.5px;">
          Yes, Delete
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ===== CATEGORY OPTIONS MODAL ===== -->
<div class="modal-overlay" id="categoryOptionsModal">
  <div class="modal-box">
    <div class="modal-header">
      <div class="modal-title">Category Options</div>
      <button class="modal-close" onclick="closeCategoryOptionsModal()">&times;</button>
    </div>
    <div id="categoryOptionsList" style="max-height: 400px; overflow-y: auto;">
      <!-- Populated by JavaScript -->
    </div>
  </div>
</div>

<!-- Toast -->
<div class="toast-notif" id="toastNotif"></div>

<!-- Scripts -->
<script src="js/auth.js"></script>
<script src="js/mobile-menu.js"></script>
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

    // Persist current filters to the URL (without reloading) and update pagination links
    updateQueryParamsInUrl({ category: catFilter, status: stFilter, search: search });
    updatePaginationLinks();
  }

  // Update the URL query string (no reload) to persist filters
  function updateQueryParamsInUrl(params) {
    const url = new URL(window.location);
    if (params.category) url.searchParams.set('category', params.category);
    else url.searchParams.delete('category');

    if (params.status) url.searchParams.set('status', params.status);
    else url.searchParams.delete('status');

    if (params.search) url.searchParams.set('search', params.search);
    else url.searchParams.delete('search');

    history.replaceState({}, '', url);
  }

  // Ensure pagination links include current filter params
  function updatePaginationLinks() {
    const cat = document.getElementById('catFilter').value;
    const st  = document.getElementById('statusFilter').value;
    const anchors = document.querySelectorAll('.pagination a.page-link');
    anchors.forEach(function(a) {
      try {
        const hrefUrl = new URL(a.href, window.location.origin);
        if (cat) hrefUrl.searchParams.set('category', cat); else hrefUrl.searchParams.delete('category');
        if (st)  hrefUrl.searchParams.set('status', st);  else hrefUrl.searchParams.delete('status');
        // Use relative href so server receives query params correctly
        a.href = hrefUrl.pathname + hrefUrl.search;
      } catch (e) {
        // ignore malformed hrefs
      }
    });
  }

  // When category or status changes, navigate to server with page=1 so pagination is recalculated
  function navigateWithFilters() {
    const params = new URLSearchParams(window.location.search);
    const cat = document.getElementById('catFilter').value;
    const st  = document.getElementById('statusFilter').value;
    const s   = document.getElementById('searchInput').value;

    if (cat) params.set('category', cat); else params.delete('category');
    if (st)  params.set('status', st);  else params.delete('status');
    if (s)   params.set('search', s);   else params.delete('search');

    params.set('page', '1');
    const url = window.location.pathname + '?' + params.toString();
    window.location.href = url;
  }

  document.getElementById('catFilter').addEventListener('change', function() {
    navigateWithFilters();
  });
  document.getElementById('statusFilter').addEventListener('change', function() {
    navigateWithFilters();
  });
  // Keep live client-side search for quick filtering; server search on enter isn't implemented
  document.getElementById('searchInput').addEventListener('input', function() {
    filterTable();
  });

  // Initialize filters from URL on load and ensure pagination links are correct
  document.addEventListener('DOMContentLoaded', function() {
    const params = new URLSearchParams(window.location.search);
    const cat = params.get('category') || '';
    const st  = params.get('status') || '';
    const s   = params.get('search') || '';

    if (cat) document.getElementById('catFilter').value = cat;
    if (st)  document.getElementById('statusFilter').value = st;
    if (s)   document.getElementById('searchInput').value = s;

    // Apply client-side filter and update links to include these params
    filterTable();
    updatePaginationLinks();
  });

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

  // category validation
  document.querySelector('#categoryModal form').addEventListener('submit', function(e) {
      var name = document.getElementById('catName').value.trim();
      var errorEl = document.getElementById('categoryError');

      errorEl.style.display = 'none';

      if (!name) {
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

  // ─── Edit Category Modal ──────────────────────────────────────────────────────
  function openEditCategoryModal(categoryId, categoryName) {
    // Close the Category Options modal first so only one modal shows
    document.getElementById('categoryOptionsModal').classList.remove('active');
    document.getElementById('editCategoryId').value = categoryId;
    document.getElementById('editCatName').value = categoryName;
    document.getElementById('editCategoryError').style.display = 'none';
    document.getElementById('editCategoryModal').classList.add('active');
  }

  function closeEditCategoryModal() {
    document.getElementById('editCategoryModal').classList.remove('active');
  }

  // ─── Delete Category Modal ───────────────────────────────────────────────────
  function openDeleteCategoryModal(categoryId, categoryName) {
    // Close the Category Options modal first so only one modal shows
    document.getElementById('categoryOptionsModal').classList.remove('active');
    document.getElementById('deleteCategoryId').value = categoryId;
    document.getElementById('deleteCategoryName').textContent = categoryName;
    document.getElementById('deleteCategoryModal').classList.add('active');
  }

  function closeDeleteCategoryModal() {
    document.getElementById('deleteCategoryModal').classList.remove('active');
  }

  // ─── Category Options Modal ───────────────────────────────────────────────
  var allCategories = <?= json_encode($categories) ?>;

  function openCategoryOptionsModal() {
    renderCategoryOptionsModal();
    document.getElementById('categoryOptionsModal').classList.add('active');
  }

  function closeCategoryOptionsModal() {
    document.getElementById('categoryOptionsModal').classList.remove('active');
  }

  function renderCategoryOptionsModal() {
    const listContainer = document.getElementById('categoryOptionsList');
    let html = '';

    if (allCategories.length === 0) {
      html = '<div style="padding:20px; text-align:center; color:var(--text-light);">No categories yet. Create one to get started!</div>';
    } else {
      html = '<div style="display:flex; flex-direction:column; gap:0;">';
      allCategories.forEach(function(cat, index) {
        html += '<div style="display:flex; align-items:center; justify-content:space-between; padding:14px 16px; border-bottom:1px solid var(--border-light); gap:12px;">' +
          '<span style="font-size:15px; font-weight:500; flex:1; color:var(--text-dark);">' + htmlEscape(cat.category_name) + '</span>' +
          '<div style="display:flex; gap:6px;">' +
          '<button type="button" class="btn-edit" onclick="openEditCategoryModal(' + cat.category_id + ', \'' + htmlEscape(cat.category_name).replace(/'/g, "\\'") + '\'); return false;" title="Edit">' +
          '<i class="bi bi-pencil"></i> Edit</button>' +
          '<button type="button" class="btn-danger-sm" onclick="openDeleteCategoryModal(' + cat.category_id + ', \'' + htmlEscape(cat.category_name).replace(/'/g, "\\'") + '\'); return false;" title="Delete">' +
          '<i class="bi bi-trash"></i> Delete</button>' +
          '</div>' +
          '</div>';
      });
      html += '</div>';
    }

    listContainer.innerHTML = html;
  }

  function htmlEscape(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  // Close modal on overlay click
  document.getElementById('productModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
  });
  document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) closeDeleteModal();
  });
  document.getElementById('categoryModal').addEventListener('click', function(e) {
    if (e.target === this) closeCategoryModal();
  });
  document.getElementById('editCategoryModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditCategoryModal();
  });
  document.getElementById('deleteCategoryModal').addEventListener('click', function(e) {
    if (e.target === this) closeDeleteCategoryModal();
  });
  document.getElementById('categoryOptionsModal').addEventListener('click', function(e) {
    if (e.target === this) closeCategoryOptionsModal();
  });

  // ─── Show toast if redirected with message ─────────────────────────────────
  <?php if ($toast): ?>
    showToast('<?= addslashes($toast) ?>');
  <?php endif; ?>
</script>

</body>
</html>