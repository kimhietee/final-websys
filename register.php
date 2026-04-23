<?php
/**
 * KIM INVENTORIES — register.php
 * User registration with INSERT into users table.
 */
session_start();
require_once 'db_connect.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    // Validation
    if (!$username || !$email || !$password || !$confirm) {
        $error = 'Please fill in all fields.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match. Please try again.';
    } else {
        // Check if email already exists (SELECT)
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->execute([strtolower($email)]);
        if ($stmt->fetch()) {
            $error = 'An account with this email already exists.';
        } else {
            // INSERT new user
            $hashedPw = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (email, username, password) VALUES (?, ?, ?)");
            $stmt->execute([strtolower($email), $username, $hashedPw]);
            $success = 'Account created! Redirecting to login…';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Register — Kim Inventories</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800;900&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="css/style.css" />
</head>
<body class="auth-page">

  <div class="auth-card">

    <!-- Brand Logo -->
    <div class="auth-logo">
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

    <h1 class="auth-title">Create Account</h1>
    <p class="auth-subtitle">Join Kim Inventories and manage your stock</p>

    <!-- Alerts -->
    <?php if ($error): ?>
      <div class="alert-msg alert-error" role="alert" style="display:block;"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="alert-msg alert-success" role="status" style="display:block;"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <!-- Register Form -->
    <form class="auth-form" id="registerForm" method="POST" action="register.php" novalidate autocomplete="on">
      <div class="form-group">
        <label for="regName">Full Name</label>
        <input type="text" id="regName" name="username"
               placeholder="Enter your full name" required autocomplete="name"
               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" />
      </div>
      <div class="form-group">
        <label for="regEmail">Email Address</label>
        <input type="email" id="regEmail" name="email"
               placeholder="you@example.com" required autocomplete="email"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" />
      </div>
      <div class="form-group">
        <label for="regPassword">Password</label>
        <input type="password" id="regPassword" name="password"
               placeholder="Minimum 6 characters" required autocomplete="new-password" />
      </div>
      <div class="form-group">
        <label for="regConfirm">Confirm Password</label>
        <input type="password" id="regConfirm" name="confirm_password"
               placeholder="Repeat your password" required autocomplete="new-password" />
      </div>
      <button type="submit" class="btn-auth">Create Account</button>
    </form>

    <div class="auth-link">
      Already have an account? <a href="login.php">Sign in</a>
    </div>

  </div><!-- /.auth-card -->

  <?php if ($success): ?>
  <script>
    setTimeout(function() { window.location.href = 'login.php'; }, 1800);
  </script>
  <?php endif; ?>

</body>
</html>
