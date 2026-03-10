<?php
session_start();
require 'db.php';

// Session protection
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$message = '';
$edit_record = null;

// ── CREATE ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'create') {
        $name    = trim($_POST['name']);
        $subject = trim($_POST['subject']);
        $grade   = $_POST['grade'];

        if (empty($name) || empty($subject) || $grade === '') {
            $message = '<div class="alert alert-danger">All fields are required.</div>';
        } elseif ((float)$grade < 0 || (float)$grade > 100) {
            $message = '<div class="alert alert-danger">Grade must be between 0 and 100.</div>';
        } else {
            $stmt = $pdo->prepare("INSERT INTO students (name, subject, grade) VALUES (?, ?, ?)");
            $stmt->execute([$name, $subject, (float)$grade]);
            $message = '<div class="alert alert-success">Record added successfully!</div>';
        }
    }

    // ── UPDATE ────────────────────────────────────────────────
    elseif ($_POST['action'] === 'update') {
        $id      = (int)$_POST['id'];
        $name    = trim($_POST['name']);
        $subject = trim($_POST['subject']);
        $grade   = $_POST['grade'];

        if (empty($name) || empty($subject) || $grade === '') {
            $message = '<div class="alert alert-danger">All fields are required.</div>';
        } elseif ((float)$grade < 0 || (float)$grade > 100) {
            $message = '<div class="alert alert-danger">Grade must be between 0 and 100.</div>';
        } else {
            $stmt = $pdo->prepare("UPDATE students SET name=?, subject=?, grade=? WHERE id=?");
            $stmt->execute([$name, $subject, (float)$grade, $id]);
            $message = '<div class="alert alert-success">Record updated successfully!</div>';
        }
    }
}

// ── DELETE ────────────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $id   = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM students WHERE id = ?");
    $stmt->execute([$id]);
    $message = '<div class="alert alert-warning">Record deleted.</div>';
}

// ── LOAD EDIT ─────────────────────────────────────────────────
if (isset($_GET['edit'])) {
    $id   = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
    $stmt->execute([$id]);
    $edit_record = $stmt->fetch();
}

// ── READ (with search) ────────────────────────────────────────
$search = trim($_GET['search'] ?? '');
if ($search !== '') {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE name LIKE ? OR subject LIKE ? ORDER BY id DESC");
    $stmt->execute(["%$search%", "%$search%"]);
} else {
    $stmt = $pdo->query("SELECT * FROM students ORDER BY id DESC");
}
$students = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Student Grade CRUD</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<!-- NAVBAR -->
<nav class="navbar navbar-dark bg-primary px-4">
  <span class="navbar-brand fw-bold">Student Grade System</span>
  <div class="d-flex align-items-center gap-3">
    <span class="text-white small">Logged in as: <strong><?= htmlspecialchars($_SESSION['username']) ?></strong></span>
    <a href="logout.php" class="btn btn-sm btn-outline-light">Logout</a>
  </div>
</nav>

<div class="container mt-4">

  <?= $message ?>

  <div class="row g-4">

    <!-- FORM: Add / Edit -->
    <div class="col-md-4">
      <div class="card shadow-sm">
        <div class="card-header bg-<?= $edit_record ? 'warning' : 'success' ?> text-white">
          <?= $edit_record ? 'Edit Record' : 'Add New Record' ?>
        </div>
        <div class="card-body">
          <form method="POST">
            <input type="hidden" name="action" value="<?= $edit_record ? 'update' : 'create' ?>">
            <?php if ($edit_record): ?>
              <input type="hidden" name="id" value="<?= $edit_record['id'] ?>">
            <?php endif; ?>

            <div class="mb-3">
              <label class="form-label">Student Name</label>
              <input type="text" name="name" class="form-control" required
                     value="<?= htmlspecialchars($edit_record['name'] ?? '') ?>">
            </div>
            <div class="mb-3">
              <label class="form-label">Subject</label>
              <input type="text" name="subject" class="form-control" required
                     value="<?= htmlspecialchars($edit_record['subject'] ?? '') ?>">
            </div>
            <div class="mb-3">
              <label class="form-label">Grade (0–100)</label>
              <input type="number" name="grade" class="form-control" min="0" max="100"
                     step="0.01" required
                     value="<?= htmlspecialchars($edit_record['grade'] ?? '') ?>">
            </div>

            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-<?= $edit_record ? 'warning' : 'success' ?> w-100">
                <?= $edit_record ? 'Update' : 'Add Record' ?>
              </button>
              <?php if ($edit_record): ?>
                <a href="index.php" class="btn btn-secondary">Cancel</a>
              <?php endif; ?>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- TABLE: Read -->
    <div class="col-md-8">
      <div class="card shadow-sm">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
          <span>Student Records</span>
          <span class="badge bg-light text-dark"><?= count($students) ?> records</span>
        </div>
        <div class="card-body">

          <!-- Search -->
          <form class="d-flex gap-2 mb-3" method="GET">
            <input type="text" name="search" class="form-control form-control-sm"
                   placeholder="Search name or subject..." value="<?= htmlspecialchars($search) ?>">
            <button class="btn btn-sm btn-primary">Search</button>
            <?php if ($search): ?>
              <a href="index.php" class="btn btn-sm btn-secondary">Clear</a>
            <?php endif; ?>
          </form>

          <!-- Table -->
          <?php if (empty($students)): ?>
            <p class="text-muted text-center">No records found.</p>
          <?php else: ?>
          <table class="table table-bordered table-hover table-sm align-middle">
            <thead class="table-light">
              <tr>
                <th>#</th>
                <th>Name</th>
                <th>Subject</th>
                <th>Grade</th>
                <th>Remarks</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($students as $i => $s): ?>
              <tr>
                <td><?= $i + 1 ?></td>
                <td><?= htmlspecialchars($s['name']) ?></td>
                <td><?= htmlspecialchars($s['subject']) ?></td>
                <td><?= number_format($s['grade'], 2) ?></td>
                <td>
                  <?php if ($s['grade'] >= 75): ?>
                    <span class="badge bg-success">Passed</span>
                  <?php else: ?>
                    <span class="badge bg-danger">Failed</span>
                  <?php endif; ?>
                </td>
                <td>
                  <a href="?edit=<?= $s['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                  <a href="?delete=<?= $s['id'] ?>"
                     class="btn btn-sm btn-danger"
                     onclick="return confirm('Delete this record?')">Delete</a>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
          <?php endif; ?>
        </div>
      </div>
    </div>

  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
