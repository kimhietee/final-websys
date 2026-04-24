<?php
/**
 * KIM INVENTORIES — login.php
 * User login with SELECT from users table + password_verify.
 */
session_start();
require_once 'db_connect.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$username || !$password) {
        $error = 'Please fill in all fields.';
    } else {
        $stmt = $conn->prepare("SELECT user_id, username, password FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']  = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Invalid username or password. Please try again.';
        }
    }
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Login — Kim Inventories</title>
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

    <h1 class="auth-title">Welcome Back</h1>
    <p class="auth-subtitle">Sign in to your account to continue</p>

    <!-- Error Alert -->
    <?php if ($error): ?>
      <div class="alert-msg alert-error" role="alert" style="display:block;"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Login Form -->
    <form class="auth-form" id="loginForm" method="POST" action="login.php" novalidate autocomplete="on">
      <div class="form-group">
        <label for="loginUser">Username</label>
        <input type="email" id="loginUser" name="email"
               placeholder="Enter your username required autocomplete="username"
               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" />
      </div>
      <div class="form-group">
        <label for="loginPassword">Password</label>
        <input type="password" id="loginPassword" name="password"
               placeholder="Enter your password" required autocomplete="current-password" />
      </div>
      <button type="submit" class="btn-auth">Sign In</button>
    </form>

    <div class="auth-link">
      Don't have an account? <a href="register.php">Create one</a>
    </div>

    <!-- Test credentials hint -->
    <div class="test-hint">
      <strong>Test Credentials:</strong><br>
      admin@kiminventories.com &nbsp;/&nbsp; admin123
    </div>

  </div><!-- /.auth-card -->

</body>
</html>
