<?php
session_start();
require 'db.php';

// Session protection
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$uid      = $_SESSION['user_id'];
$event_id = (int)($_GET['event_id'] ?? 0);
$msg      = '';

// Load event (must belong to current user)
$stmt = $pdo->prepare("SELECT * FROM events WHERE id = ? AND user_id = ?");
$stmt->execute([$event_id, $uid]);
$event = $stmt->fetch();

if (!$event) {
    header('Location: index.php'); exit;
}

// ── ADD ATTENDEE ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'register') {
    $name    = trim($_POST['attendee_name']);
    $contact = trim($_POST['contact']);

    if (empty($name) || empty($contact)) {
        $msg = '<div class="alert alert-danger">Name and contact are required.</div>';
    } else {
        // Check if slots are full
        $cnt = $pdo->prepare("SELECT COUNT(*) FROM registrations WHERE event_id = ?");
        $cnt->execute([$event_id]);
        $count = $cnt->fetchColumn();

        if ($event['slots'] > 0 && $count >= $event['slots']) {
            $msg = '<div class="alert alert-danger">No slots available. Event is full.</div>';
        } else {
            $pdo->prepare("INSERT INTO registrations (event_id, attendee_name, contact) VALUES (?,?,?)")
                ->execute([$event_id, $name, $contact]);
            $msg = '<div class="alert alert-success">✅ Attendee registered successfully!</div>';
        }
    }
}

// ── DELETE ATTENDEE ───────────────────────────────────────────
if (isset($_GET['remove'])) {
    $rid = (int)$_GET['remove'];
    $pdo->prepare("DELETE FROM registrations WHERE id = ? AND event_id = ?")
        ->execute([$rid, $event_id]);
    $msg = '<div class="alert alert-warning">Attendee removed.</div>';
}

// ── READ attendees ────────────────────────────────────────────
$search = trim($_GET['search'] ?? '');
if ($search !== '') {
    $stmt = $pdo->prepare("SELECT * FROM registrations WHERE event_id = ? AND (attendee_name LIKE ? OR contact LIKE ?) ORDER BY registered_at DESC");
    $stmt->execute([$event_id, "%$search%", "%$search%"]);
} else {
    $stmt = $pdo->prepare("SELECT * FROM registrations WHERE event_id = ? ORDER BY registered_at DESC");
    $stmt->execute([$event_id]);
}
$attendees = $stmt->fetchAll();
$count     = count($attendees);
$is_full   = $event['slots'] > 0 && $count >= $event['slots'];
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Attendees — <?= htmlspecialchars($event['title']) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body { background: #f0f4f8; font-family: 'Segoe UI', sans-serif; }
    .navbar { background: #1e3a5f !important; }
    .card { border: none; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
    .form-label { font-size: .88rem; font-weight: 600; }
    th { font-size: .83rem; background: #f8f9fa !important; }
  </style>
</head>
<body>

<nav class="navbar navbar-dark px-4 py-2">
  <span class="navbar-brand fw-bold fs-6">🏘️ Community Event System</span>
  <div class="d-flex gap-2">
    <a href="index.php" class="btn btn-sm btn-outline-light">
      <i class="bi bi-arrow-left me-1"></i>Back to Events
    </a>
    <a href="logout.php" class="btn btn-sm btn-outline-light">Logout</a>
  </div>
</nav>

<div class="container-fluid px-4 py-4">

  <!-- Event Info Banner -->
  <div class="card mb-4 border-start border-primary border-4">
    <div class="card-body py-3">
      <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
          <h5 class="fw-bold mb-1"><?= htmlspecialchars($event['title']) ?></h5>
          <span class="text-muted small">
            <i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($event['location']) ?>
            &nbsp;·&nbsp;
            <i class="bi bi-calendar me-1"></i><?= date('F d, Y', strtotime($event['event_date'])) ?>
            &nbsp;·&nbsp;
            <i class="bi bi-clock me-1"></i><?= date('h:i A', strtotime($event['event_time'])) ?>
          </span>
        </div>
        <div class="text-end">
          <span class="badge bg-primary fs-6"><?= $count ?> / <?= $event['slots'] ?: '∞' ?> registered</span>
          <?php if ($is_full): ?>
            <div><span class="badge bg-danger mt-1">Slots Full</span></div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <?= $msg ?>

  <div class="row g-4">

    <!-- ADD ATTENDEE FORM -->
    <div class="col-lg-4">
      <div class="card">
        <div class="card-header bg-success text-white py-2">
          <i class="bi bi-person-plus me-2"></i>Register Attendee
        </div>
        <div class="card-body">
          <?php if ($is_full): ?>
            <div class="alert alert-danger py-2 text-center">
              <i class="bi bi-x-circle me-1"></i>No more slots available.
            </div>
          <?php else: ?>
          <form method="POST">
            <input type="hidden" name="action" value="register">
            <div class="mb-3">
              <label class="form-label">Full Name *</label>
              <input type="text" name="attendee_name" class="form-control form-control-sm" required
                     placeholder="e.g. Juan dela Cruz">
            </div>
            <div class="mb-3">
              <label class="form-label">Contact (phone or email) *</label>
              <input type="text" name="contact" class="form-control form-control-sm" required
                     placeholder="e.g. 09XX-XXX-XXXX">
            </div>
            <button class="btn btn-success btn-sm w-100">
              <i class="bi bi-person-check me-1"></i>Register
            </button>
          </form>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- ATTENDEES TABLE -->
    <div class="col-lg-8">
      <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-2">
          <span class="fw-semibold"><i class="bi bi-people me-2"></i>Registered Attendees</span>
          <span class="badge bg-success"><?= $count ?></span>
        </div>
        <div class="card-body pb-2">

          <!-- Search -->
          <form class="d-flex gap-2 mb-3" method="GET">
            <input type="hidden" name="event_id" value="<?= $event_id ?>">
            <input type="text" name="search" class="form-control form-control-sm"
                   placeholder="Search name or contact..." value="<?= htmlspecialchars($search) ?>">
            <button class="btn btn-sm btn-primary">Search</button>
            <?php if ($search): ?>
              <a href="attendees.php?event_id=<?= $event_id ?>" class="btn btn-sm btn-secondary">Clear</a>
            <?php endif; ?>
          </form>

          <?php if (empty($attendees)): ?>
            <div class="text-center text-muted py-4">
              <i class="bi bi-person-x" style="font-size:2rem"></i>
              <p class="mt-2">No attendees registered yet.</p>
            </div>
          <?php else: ?>
          <table class="table table-sm table-bordered align-middle mb-0">
            <thead>
              <tr>
                <th>#</th>
                <th>Name</th>
                <th>Contact</th>
                <th>Registered At</th>
                <th class="text-center">Action</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($attendees as $i => $a): ?>
              <tr>
                <td class="text-muted"><?= $i+1 ?></td>
                <td class="fw-semibold"><?= htmlspecialchars($a['attendee_name']) ?></td>
                <td><?= htmlspecialchars($a['contact']) ?></td>
                <td class="small text-muted"><?= date('M d, Y h:i A', strtotime($a['registered_at'])) ?></td>
                <td class="text-center">
                  <a href="?event_id=<?= $event_id ?>&remove=<?= $a['id'] ?>"
                     class="btn btn-sm btn-danger py-0 px-2"
                     onclick="return confirm('Remove this attendee?')">
                    <i class="bi bi-trash"></i>
                  </a>
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
