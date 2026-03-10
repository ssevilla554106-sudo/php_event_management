<?php
session_start();
require 'db.php';
$error = '';

if (isset($_SESSION['user_id'])) { header('Location: index.php'); exit; }

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
                setcookie('remember_user', $username, time() + (7 * 24 * 3600), '/');
            }
            // Cookie: Last login timestamp
            setcookie('last_login', date('M d, Y h:i A'), time() + (30 * 24 * 3600), '/');

            header('Location: index.php'); exit;
        } else {
            $error = 'Invalid username or password.';
        }
    }
}

$saved = $_COOKIE['remember_user'] ?? '';
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Login — Community Events</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background: #f0f4f8; }
    .card { border: none; border-radius: 12px; }
  </style>
</head>
<body>
<div class="container" style="max-width:420px; margin-top:70px;">
  <div class="card shadow-sm p-4">
    <div class="text-center mb-3">
      <div style="font-size:2.2rem">🏘️</div>
      <h5 class="fw-bold mt-1">Community Event System</h5>
      <p class="text-muted small mb-0">Sign in to manage events</p>
    </div>

    <?php if (!empty($_COOKIE['last_login'])): ?>
      <div class="alert alert-light border py-2 small text-center">
        🕐 Last login: <strong><?= htmlspecialchars($_COOKIE['last_login']) ?></strong>
      </div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="mb-3">
        <label class="form-label fw-semibold">Username</label>
        <input type="text" name="username" class="form-control"
               value="<?= htmlspecialchars($saved) ?>" required autofocus>
      </div>
      <div class="mb-3">
        <label class="form-label fw-semibold">Password</label>
        <input type="password" name="password" class="form-control" required>
      </div>
      <div class="mb-3 form-check">
        <input type="checkbox" name="remember" class="form-check-input" id="rem"
               <?= $saved ? 'checked' : '' ?>>
        <label class="form-check-label small" for="rem">Remember Me (7 days)</label>
      </div>
      <button class="btn btn-primary w-100">Login</button>
    </form>
    <p class="text-center mt-3 mb-0 small">No account? <a href="register.php">Register</a></p>
  </div>
</div>
</body>
</html>
