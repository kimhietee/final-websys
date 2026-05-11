<?php
/**
 * KIM INVENTORIES — profile.php
 * User profile with editable name, email, bio, and photo upload.
 * Features: Database updates, localStorage persistence, client-side validation
 */
session_start();
require_once 'db_connect.php';

// Auth guard
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$apiResponse = ['success' => false, 'message' => '', 'data' => null];

// ─── Handle AJAX POST requests ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';

    // Update username
    if ($action === 'update_username') {
        $newUsername = trim($_POST['value'] ?? '');
        $errors = [];

        // Validation
        if (empty($newUsername)) {
            $errors[] = 'Username cannot be empty';
        } elseif (strlen($newUsername) < 3) {
            $errors[] = 'Username must be at least 3 characters';
        } elseif (strlen($newUsername) > 50) {
            $errors[] = 'Username cannot exceed 50 characters';
        }

        if (!empty($errors)) {
            echo json_encode(['success' => false, 'message' => implode('. ', $errors)]);
            exit;
        }

        $stmt = $conn->prepare("UPDATE users SET username = ? WHERE user_id = ?");
        $stmt->bind_param("si", $newUsername, $userId);
        if ($stmt->execute()) {
            $_SESSION['username'] = $newUsername;
            echo json_encode(['success' => true, 'message' => 'Username updated successfully!', 'value' => $newUsername]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update username']);
        }
        exit;
    }

    // Update email
    if ($action === 'update_email') {
        $newEmail = trim($_POST['value'] ?? '');
        $errors = [];

        // Validation
        if (empty($newEmail)) {
            $errors[] = 'Email cannot be empty';
        } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format';
        }

        if (!empty($errors)) {
            echo json_encode(['success' => false, 'message' => implode('. ', $errors)]);
            exit;
        }

        // Check for duplicate
        $check = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $check->bind_param("si", $newEmail, $userId);
        $check->execute();
        $check->store_result();
        if ($check->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'This email is already in use']);
            exit;
        }

        $stmt = $conn->prepare("UPDATE users SET email = ? WHERE user_id = ?");
        $stmt->bind_param("si", $newEmail, $userId);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Email updated successfully!', 'value' => $newEmail]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update email']);
        }
        exit;
    }
}

// ─── SELECT user data ────────────────────────────────────────────────────────
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
<body data-user-id="<?= $userId ?>">

<div class="app-wrapper">

  <!-- Mobile Menu Overlay -->
  <div class="sidebar-overlay"></div>

  <!-- ===== MOBILE TOP BAR (mobile-only) ===== -->
  <header class="mobile-top-bar">
    <button class="mobile-menu-btn" title="Toggle menu">
      <i class="bi bi-list"></i>
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
            <button class="upload-photo-btn" type="button" onclick="document.getElementById('photoInput').click()">
              <i class="bi bi-upload"></i> Upload Photo
            </button>
            <input type="file" id="photoInput" accept="image/*" style="display:none;" onchange="handlePhotoUpload()" />
          </div>

          <!-- Name Field -->
          <div class="profile-field">
            <div class="profile-field-info" style="flex:1;">
              <div class="field-label">Username</div>
              <div class="field-value" id="nameVal"><?= htmlspecialchars($displayName) ?></div>
              <div id="nameEditMode" style="display:none;">
                <input class="field-edit-input" type="text" id="nameInput" placeholder="Enter username" value="<?= htmlspecialchars($displayName) ?>" />
                <div class="field-error-msg" id="nameError"></div>
                <div class="field-success-msg" id="nameSuccess"></div>
                <div class="field-edit-actions">
                  <button type="button" class="btn-save" onclick="saveField('name')">Save</button>
                  <button type="button" class="btn-cancel-sm" onclick="cancelField('name')">Cancel</button>
                </div>
              </div>
            </div>
            <button class="btn-edit" id="nameEditBtn" onclick="editField('name')">Edit</button>
          </div>

          <!-- Email Field -->
          <div class="profile-field">
            <div class="profile-field-info" style="flex:1;">
              <div class="field-label">Email</div>
              <div class="field-value" id="emailVal"><?= htmlspecialchars($displayEmail) ?></div>
              <div id="emailEditMode" style="display:none;">
                <input class="field-edit-input" type="email" id="emailInput" placeholder="Email address" value="<?= htmlspecialchars($displayEmail) ?>" />
                <div class="field-error-msg" id="emailError"></div>
                <div class="field-success-msg" id="emailSuccess"></div>
                <div class="field-edit-actions">
                  <button type="button" class="btn-save" onclick="saveField('email')">Save</button>
                  <button type="button" class="btn-cancel-sm" onclick="cancelField('email')">Cancel</button>
                </div>
              </div>
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

          <!-- Bio Section -->
          <div class="bio-section">
            <div class="field-label">Personal Bio (max 500 characters)</div>
            <div id="bioDisplay" class="bio-display-text">Click to add your bio...</div>
            <div id="bioEditMode" style="display:none;">
              <textarea class="bio-textarea" id="bioInput" placeholder="Tell us about yourself..." maxlength="500"></textarea>
              <div class="bio-counter">
                <span id="bioCharCount">0</span> / 500
              </div>
              <div class="bio-actions">
                <button type="button" class="btn-save" onclick="saveBio()">Save Bio</button>
                <button type="button" class="btn-cancel-sm" onclick="cancelBio()">Cancel</button>
              </div>
            </div>
            <button class="btn-edit" id="bioEditBtn" onclick="editBio()" style="margin-top:8px;">Edit Bio</button>
          </div>

          <!-- Account Stats -->
          <?php
            $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM products WHERE user_id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $totalProducts = $stmt->get_result()->fetch_assoc()['cnt'];

            $stmt = $conn->prepare("SELECT COALESCE(SUM(quantity), 0) AS total FROM products WHERE user_id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $stockTotal = $stmt->get_result()->fetch_assoc()['total'];
          ?>
          <div style="margin-top:20px; padding-top:16px; border-top:1px dashed var(--card-border);">
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
<script src="js/mobile-menu.js"></script>
<script>
const MAX_PHOTO_SIZE = 5 * 1024 * 1024; // 5MB
const ALLOWED_FORMATS = ['image/jpeg', 'image/png', 'image/webp'];
const BIO_MAX_LENGTH = 500;

// ─── Initialize ────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
  loadPhotoFromStorage();
  loadBioFromStorage();
  setupPhotoUploadListeners();
  setupBioCharCounter();
});

// ─── Name/Email Field Management ───────────────────────────────────────────
function editField(field) {
  document.getElementById(field + 'Val').style.display = 'none';
  document.getElementById(field + 'EditBtn').style.display = 'none';
  document.getElementById(field + 'EditMode').style.display = 'block';
  document.getElementById(field + 'Input').focus();
  clearFieldMessages(field);
}

function cancelField(field) {
  document.getElementById(field + 'EditMode').style.display = 'none';
  document.getElementById(field + 'Val').style.display = 'block';
  document.getElementById(field + 'EditBtn').style.display = '';
  clearFieldMessages(field);
}

function clearFieldMessages(field) {
  const errorEl = document.getElementById(field + 'Error');
  const successEl = document.getElementById(field + 'Success');
  if (errorEl) {
    errorEl.textContent = '';
    errorEl.classList.remove('show');
  }
  if (successEl) {
    successEl.textContent = '';
    successEl.classList.remove('show');
  }
}

function saveField(field) {
  const input = document.getElementById(field + 'Input');
  const value = input.value.trim();
  const action = field === 'name' ? 'update_username' : 'update_email';
  
  // Disable button and show loading
  const btn = event.target;
  btn.disabled = true;
  btn.textContent = 'Saving...';
  
  const formData = new FormData();
  formData.append('action', action);
  formData.append('value', value);

  fetch('profile.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      // Update display
      document.getElementById(field + 'Val').textContent = data.value;
      
      // Show success message
      const successEl = document.getElementById(field + 'Success');
      if (successEl) {
        successEl.textContent = data.message;
        successEl.classList.add('show');
      }
      
      // Close edit mode after delay
      setTimeout(() => {
        cancelField(field);
      }, 1500);
      
      showToast(data.message, 'success');
    } else {
      // Show error message
      const errorEl = document.getElementById(field + 'Error');
      if (errorEl) {
        errorEl.textContent = data.message;
        errorEl.classList.add('show');
      }
      showToast(data.message, 'error');
    }
  })
  .catch(error => {
    console.error('Error:', error);
    const errorEl = document.getElementById(field + 'Error');
    if (errorEl) {
      errorEl.textContent = 'An unexpected error occurred';
      errorEl.classList.add('show');
    }
    showToast('An error occurred', 'error');
  })
  .finally(() => {
    btn.disabled = false;
    btn.textContent = 'Save';
  });
}

// ─── Bio Management ────────────────────────────────────────────────────────
function editBio() {
  document.getElementById('bioDisplay').style.display = 'none';
  document.getElementById('bioEditBtn').style.display = 'none';
  document.getElementById('bioEditMode').style.display = 'block';
  document.getElementById('bioInput').focus();
}

function cancelBio() {
  document.getElementById('bioEditMode').style.display = 'none';
  document.getElementById('bioDisplay').style.display = 'block';
  document.getElementById('bioEditBtn').style.display = '';
}

function saveBio() {
  const bioText = document.getElementById('bioInput').value.trim();
  localStorage.setItem(`bio_${getUserId()}`, bioText);
  
  // Update display
  const bioDisplay = document.getElementById('bioDisplay');
  if (bioText) {
    bioDisplay.textContent = bioText;
  } else {
    bioDisplay.textContent = 'Click to add your bio...';
  }
  
  showToast('Bio saved successfully!', 'success');
  cancelBio();
}

function loadBioFromStorage() {
  const userId = getUserId();
  const savedBio = localStorage.getItem(`bio_${userId}`);
  const bioInput = document.getElementById('bioInput');
  const bioDisplay = document.getElementById('bioDisplay');
  
  if (savedBio) {
    bioInput.value = savedBio;
    bioDisplay.textContent = savedBio;
  }
  
  updateBioCharCount();
}

function setupBioCharCounter() {
  const bioInput = document.getElementById('bioInput');
  bioInput.addEventListener('input', updateBioCharCount);
}

function updateBioCharCount() {
  const bioInput = document.getElementById('bioInput');
  const charCount = bioInput.value.length;
  const counter = document.getElementById('bioCharCount');
  const counterContainer = counter.parentElement;
  
  counter.textContent = charCount;
  
  // Update warning/error state
  counterContainer.classList.remove('warning', 'error');
  if (charCount > 450) {
    counterContainer.classList.add('warning');
  } else if (charCount >= BIO_MAX_LENGTH) {
    counterContainer.classList.add('error');
  }
}

// ─── Photo Upload Management ───────────────────────────────────────────────
function setupPhotoUploadListeners() {
  const photoInput = document.getElementById('photoInput');
  const photoCircle = document.getElementById('photoCircle');
  
  // Prevent default drag behaviors
  photoCircle.addEventListener('dragover', e => {
    e.preventDefault();
    e.stopPropagation();
    photoCircle.style.opacity = '0.7';
  });
  
  photoCircle.addEventListener('dragleave', e => {
    e.preventDefault();
    e.stopPropagation();
    photoCircle.style.opacity = '1';
  });
  
  photoCircle.addEventListener('drop', e => {
    e.preventDefault();
    e.stopPropagation();
    photoCircle.style.opacity = '1';
    
    const files = e.dataTransfer.files;
    if (files.length > 0) {
      photoInput.files = files;
      handlePhotoUpload();
    }
  });
}

function handlePhotoUpload() {
  const photoInput = document.getElementById('photoInput');
  const file = photoInput.files[0];
  
  if (!file) return;
  
  // Validate file format
  if (!ALLOWED_FORMATS.includes(file.type)) {
    showToast('Please upload JPG, PNG, or WebP format only', 'error');
    photoInput.value = '';
    return;
  }
  
  // Validate file size
  if (file.size > MAX_PHOTO_SIZE) {
    showToast('File size must be less than 5MB', 'error');
    photoInput.value = '';
    return;
  }
  
  // Show loading state
  const photoCircle = document.getElementById('photoCircle');
  const btn = document.querySelector('.upload-photo-btn');
  photoCircle.classList.add('loading');
  btn.classList.add('loading');
  
  // Read and convert to base64
  const reader = new FileReader();
  reader.onload = function(e) {
    const base64Data = e.target.result;
    
    // Store in localStorage
    try {
      localStorage.setItem(`photo_${getUserId()}`, base64Data);
      
      // Update preview
      const photoPreview = document.getElementById('photoPreview');
      const photoIcon = document.getElementById('photoIcon');
      photoPreview.src = base64Data;
      photoPreview.style.display = 'block';
      photoIcon.style.display = 'none';
      
      // Add delete button
      addDeletePhotoButton();
      
      showToast('Photo uploaded successfully!', 'success');
    } catch (e) {
      showToast('Photo is too large to store locally', 'error');
    }
    
    photoCircle.classList.remove('loading');
    btn.classList.remove('loading');
  };
  
  reader.onerror = function() {
    showToast('Error reading file', 'error');
    photoCircle.classList.remove('loading');
    btn.classList.remove('loading');
  };
  
  reader.readAsDataURL(file);
}

function loadPhotoFromStorage() {
  const userId = getUserId();
  const savedPhoto = localStorage.getItem(`photo_${userId}`);
  
  if (savedPhoto) {
    const photoPreview = document.getElementById('photoPreview');
    const photoIcon = document.getElementById('photoIcon');
    photoPreview.src = savedPhoto;
    photoPreview.style.display = 'block';
    photoIcon.style.display = 'none';
    
    addDeletePhotoButton();
  }
}

function addDeletePhotoButton() {
  const photoArea = document.querySelector('.profile-photo-area');
  let deleteBtn = photoArea.querySelector('.delete-photo-btn');
  
  if (!deleteBtn) {
    deleteBtn = document.createElement('button');
    deleteBtn.className = 'delete-photo-btn';
    deleteBtn.type = 'button';
    deleteBtn.innerHTML = '<i class="bi bi-trash"></i> Delete Photo';
    deleteBtn.onclick = deletePhoto;
    photoArea.appendChild(deleteBtn);
  }
}

function deletePhoto(e) {
  e.preventDefault();
  
  if (!confirm('Are you sure you want to delete your profile photo?')) {
    return;
  }
  
  const userId = getUserId();
  localStorage.removeItem(`photo_${userId}`);
  
  const photoPreview = document.getElementById('photoPreview');
  const photoIcon = document.getElementById('photoIcon');
  photoPreview.style.display = 'none';
  photoIcon.style.display = 'block';
  
  const deleteBtn = document.querySelector('.delete-photo-btn');
  if (deleteBtn) {
    deleteBtn.remove();
  }
  
  document.getElementById('photoInput').value = '';
  showToast('Photo deleted', 'success');
}

// ─── Utility Functions ─────────────────────────────────────────────────────
function getUserId() {
  return document.body.getAttribute('data-user-id');
}

function showToast(message, type = 'success') {
  const toast = document.getElementById('toastNotif');
  toast.textContent = message;
  toast.className = 'toast-notif show ' + type;
  
  setTimeout(() => {
    toast.classList.remove('show');
  }, 4000);
}
</script>

</body>
</html>
