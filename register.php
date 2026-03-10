<?php
require 'db.php';
$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm  = $_POST['confirm'];

    if (empty($username) || empty($password) || empty($confirm)) {
        $error = 'All fields are required.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $error = 'Username already taken.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)")
                ->execute([$username, $hash]);
            $success = 'Account created! <a href="login.php">Login here</a>.';
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Register — Community Events</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background: #f0f4f8; }
    .card { border: none; border-radius: 12px; }
    .brand-icon { font-size: 2.2rem; }
  </style>
</head>
<body>
<div class="container" style="max-width:420px; margin-top:70px;">
  <div class="card shadow-sm p-4">
    <div class="text-center mb-3">
      <div class="brand-icon">🏘️</div>
      <h5 class="fw-bold mt-1">Community Event System</h5>
      <p class="text-muted small mb-0">Create an account</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="alert alert-success py-2"><?= $success ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="mb-3">
        <label class="form-label fw-semibold">Username</label>
        <input type="text" name="username" class="form-control" required>
      </div>
      <div class="mb-3">
        <label class="form-label fw-semibold">Password</label>
        <input type="password" name="password" class="form-control" required>
      </div>
      <div class="mb-3">
        <label class="form-label fw-semibold">Confirm Password</label>
        <input type="password" name="confirm" class="form-control" required>
      </div>
      <button class="btn btn-primary w-100">Register</button>
    </form>
    <p class="text-center mt-3 mb-0 small">Already have an account? <a href="login.php">Login</a></p>
  </div>
</div>
</body>
</html>
