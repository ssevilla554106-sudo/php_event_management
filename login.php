<?php
session_start();
require 'db.php';
$error = '';

// Already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);

    if (empty($username) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];

            // Cookie: Remember Me (7 days)
            if ($remember) {
                setcookie('remembered_user', $username, time() + (7 * 24 * 3600), '/');
            }

            // Cookie: Last login time
            setcookie('last_login', date('Y-m-d H:i:s'), time() + (30 * 24 * 3600), '/');

            header('Location: index.php');
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    }
}

// Pre-fill username from remember cookie
$saved_user = $_COOKIE['remembered_user'] ?? '';
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Login</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container" style="max-width:400px; margin-top:80px;">
  <div class="card p-4 shadow-sm">
    <h4 class="mb-3">Login</h4>

    <?php if (!empty($_COOKIE['last_login'])): ?>
      <div class="alert alert-info py-2 small">
        Last login: <?= htmlspecialchars($_COOKIE['last_login']) ?>
      </div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="mb-3">
        <label class="form-label">Username</label>
        <input type="text" name="username" class="form-control"
               value="<?= htmlspecialchars($saved_user) ?>" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control" required>
      </div>
      <div class="mb-3 form-check">
        <input type="checkbox" name="remember" class="form-check-input" id="remember">
        <label class="form-check-label" for="remember">Remember Me</label>
      </div>
      <button type="submit" class="btn btn-primary w-100">Login</button>
    </form>
    <p class="text-center mt-3 mb-0">No account? <a href="register.php">Register</a></p>
  </div>
</div>
</body>
</html>
