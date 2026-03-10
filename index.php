<?php
session_start();
require 'db.php';

// Session protection
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$uid     = $_SESSION['user_id'];
$msg     = '';
$edit    = null;
$cats    = ['Meeting','Clean-Up','Health','Sports','Cultural','Other'];

// ── CREATE ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    $title  = trim($_POST['title']);
    $desc   = trim($_POST['description']);
    $loc    = trim($_POST['location']);
    $date   = $_POST['event_date'];
    $time   = $_POST['event_time'];
    $cat    = $_POST['category'];
    $slots  = (int)$_POST['slots'];

    if (empty($title) || empty($loc) || empty($date) || empty($time)) {
        $msg = '<div class="alert alert-danger">Title, location, date, and time are required.</div>';
    } elseif ($slots < 0) {
        $msg = '<div class="alert alert-danger">Slots cannot be negative.</div>';
    } else {
        $pdo->prepare("INSERT INTO events (user_id,title,description,location,event_date,event_time,category,slots)
                       VALUES (?,?,?,?,?,?,?,?)")
            ->execute([$uid, $title, $desc, $loc, $date, $time, $cat, $slots]);
        $msg = '<div class="alert alert-success">✅ Event created successfully!</div>';
    }
}

// ── UPDATE ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    $id    = (int)$_POST['id'];
    $title = trim($_POST['title']);
    $desc  = trim($_POST['description']);
    $loc   = trim($_POST['location']);
    $date  = $_POST['event_date'];
    $time  = $_POST['event_time'];
    $cat   = $_POST['category'];
    $slots = (int)$_POST['slots'];

    if (empty($title) || empty($loc) || empty($date) || empty($time)) {
        $msg = '<div class="alert alert-danger">Title, location, date, and time are required.</div>';
    } elseif ($slots < 0) {
        $msg = '<div class="alert alert-danger">Slots cannot be negative.</div>';
    } else {
        $pdo->prepare("UPDATE events SET title=?,description=?,location=?,event_date=?,event_time=?,category=?,slots=?
                       WHERE id=? AND user_id=?")
            ->execute([$title, $desc, $loc, $date, $time, $cat, $slots, $id, $uid]);
        $msg = '<div class="alert alert-success">✅ Event updated successfully!</div>';
    }
}

// ── DELETE ────────────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM events WHERE id = ? AND user_id = ?")->execute([$id, $uid]);
    $msg = '<div class="alert alert-warning">🗑️ Event deleted.</div>';
}

// ── LOAD FOR EDIT ─────────────────────────────────────────────
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ? AND user_id = ?");
    $stmt->execute([(int)$_GET['edit'], $uid]);
    $edit = $stmt->fetch();
}

// ── READ (with search) ────────────────────────────────────────
$search = trim($_GET['search'] ?? '');
$filter = trim($_GET['filter'] ?? '');

$sql    = "SELECT e.*, (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id) AS reg_count
           FROM events e WHERE e.user_id = ?";
$params = [$uid];

if ($search !== '') {
    $sql    .= " AND (e.title LIKE ? OR e.location LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%";
}
if ($filter !== '') {
    $sql    .= " AND e.category = ?";
    $params[] = $filter;
}
$sql .= " ORDER BY e.event_date ASC";

$stmt   = $pdo->prepare($sql);
$stmt->execute($params);
$events = $stmt->fetchAll();

// Stats
$total     = count($events);
$upcoming  = array_filter($events, fn($e) => $e['event_date'] >= date('Y-m-d'));
$past      = array_filter($events, fn($e) => $e['event_date'] < date('Y-m-d'));
$total_reg = array_sum(array_column($events, 'reg_count'));
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Community Event System</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body { background: #f0f4f8; font-family: 'Segoe UI', sans-serif; }
    .navbar { background: #1e3a5f !important; }
    .card { border: none; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
    .stat-card { border-left: 4px solid; }
    .badge-cat { font-size: .72rem; padding: 4px 10px; border-radius: 20px; }
    .event-row:hover { background: #f8f9ff; }
    th { font-size: .83rem; background: #f8f9fa !important; }
    .form-label { font-size: .88rem; font-weight: 600; }
  </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-dark px-4 py-2">
  <span class="navbar-brand fw-bold fs-6">🏘️ Community Event System</span>
  <div class="d-flex align-items-center gap-3">
    <span class="text-white small opacity-75">
      <i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($_SESSION['username']) ?>
    </span>
    <a href="logout.php" class="btn btn-sm btn-outline-light">
      <i class="bi bi-box-arrow-right me-1"></i>Logout
    </a>
  </div>
</nav>

<div class="container-fluid px-4 py-4">

  <?= $msg ?>

  <!-- STATS -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card stat-card border-primary p-3">
        <div class="text-muted small">Total Events</div>
        <h4 class="fw-bold text-primary mb-0"><?= $total ?></h4>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card stat-card border-success p-3">
        <div class="text-muted small">Upcoming</div>
        <h4 class="fw-bold text-success mb-0"><?= count($upcoming) ?></h4>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card stat-card border-secondary p-3">
        <div class="text-muted small">Past Events</div>
        <h4 class="fw-bold text-secondary mb-0"><?= count($past) ?></h4>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card stat-card border-warning p-3">
        <div class="text-muted small">Total Registrations</div>
        <h4 class="fw-bold text-warning mb-0"><?= $total_reg ?></h4>
      </div>
    </div>
  </div>

  <div class="row g-4">

    <!-- ── FORM (Add / Edit) ──────────────────────────────── -->
    <div class="col-lg-4">
      <div class="card">
        <div class="card-header bg-<?= $edit ? 'warning' : 'primary' ?> text-white py-2">
          <i class="bi bi-<?= $edit ? 'pencil' : 'plus-circle' ?> me-2"></i>
          <?= $edit ? 'Edit Event' : 'Add New Event' ?>
        </div>
        <div class="card-body">
          <form method="POST">
            <input type="hidden" name="action" value="<?= $edit ? 'update' : 'create' ?>">
            <?php if ($edit): ?>
              <input type="hidden" name="id" value="<?= $edit['id'] ?>">
            <?php endif; ?>

            <div class="mb-2">
              <label class="form-label">Event Title *</label>
              <input type="text" name="title" class="form-control form-control-sm" required
                     value="<?= htmlspecialchars($edit['title'] ?? '') ?>">
            </div>
            <div class="mb-2">
              <label class="form-label">Description</label>
              <textarea name="description" class="form-control form-control-sm" rows="2"><?= htmlspecialchars($edit['description'] ?? '') ?></textarea>
            </div>
            <div class="mb-2">
              <label class="form-label">Location *</label>
              <input type="text" name="location" class="form-control form-control-sm" required
                     value="<?= htmlspecialchars($edit['location'] ?? '') ?>">
            </div>
            <div class="row g-2 mb-2">
              <div class="col">
                <label class="form-label">Date *</label>
                <input type="date" name="event_date" class="form-control form-control-sm" required
                       value="<?= $edit['event_date'] ?? '' ?>">
              </div>
              <div class="col">
                <label class="form-label">Time *</label>
                <input type="time" name="event_time" class="form-control form-control-sm" required
                       value="<?= $edit['event_time'] ?? '' ?>">
              </div>
            </div>
            <div class="row g-2 mb-3">
              <div class="col">
                <label class="form-label">Category</label>
                <select name="category" class="form-select form-select-sm">
                  <?php foreach ($cats as $c): ?>
                    <option value="<?= $c ?>" <?= ($edit['category'] ?? '') === $c ? 'selected' : '' ?>>
                      <?= $c ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col">
                <label class="form-label">Slots</label>
                <input type="number" name="slots" class="form-control form-control-sm"
                       min="0" value="<?= $edit['slots'] ?? 0 ?>">
              </div>
            </div>

            <div class="d-flex gap-2">
              <button class="btn btn-<?= $edit ? 'warning' : 'primary' ?> btn-sm flex-fill">
                <i class="bi bi-check-lg me-1"></i><?= $edit ? 'Update Event' : 'Save Event' ?>
              </button>
              <?php if ($edit): ?>
                <a href="index.php" class="btn btn-secondary btn-sm">Cancel</a>
              <?php endif; ?>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- ── TABLE (Read) ──────────────────────────────────── -->
    <div class="col-lg-8">
      <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-2">
          <span class="fw-semibold"><i class="bi bi-calendar-event me-2"></i>Events</span>
          <span class="badge bg-primary"><?= count($events) ?></span>
        </div>
        <div class="card-body pb-2">

          <!-- Search + Filter -->
          <form class="row g-2 mb-3" method="GET">
            <div class="col-sm-5">
              <input type="text" name="search" class="form-control form-control-sm"
                     placeholder="Search title or location..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-sm-4">
              <select name="filter" class="form-select form-select-sm">
                <option value="">All Categories</option>
                <?php foreach ($cats as $c): ?>
                  <option value="<?= $c ?>" <?= $filter === $c ? 'selected' : '' ?>><?= $c ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-sm-3 d-flex gap-1">
              <button class="btn btn-sm btn-primary flex-fill">Filter</button>
              <?php if ($search || $filter): ?>
                <a href="index.php" class="btn btn-sm btn-secondary">Clear</a>
              <?php endif; ?>
            </div>
          </form>

          <!-- Events Table -->
          <?php if (empty($events)): ?>
            <div class="text-center text-muted py-4">
              <i class="bi bi-calendar-x" style="font-size:2rem"></i>
              <p class="mt-2">No events found. Add your first event!</p>
            </div>
          <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle mb-0">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Title</th>
                  <th>Category</th>
                  <th>Date & Time</th>
                  <th>Location</th>
                  <th>Slots</th>
                  <th>Reg.</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($events as $i => $e):
                $is_past   = $e['event_date'] < date('Y-m-d');
                $is_full   = $e['slots'] > 0 && $e['reg_count'] >= $e['slots'];
                $cat_colors = [
                  'Meeting'  => 'primary', 'Clean-Up' => 'success',
                  'Health'   => 'info',    'Sports'   => 'warning',
                  'Cultural' => 'danger',  'Other'    => 'secondary',
                ];
                $c = $cat_colors[$e['category']] ?? 'secondary';
              ?>
                <tr class="event-row">
                  <td class="text-muted"><?= $i+1 ?></td>
                  <td>
                    <div class="fw-semibold"><?= htmlspecialchars($e['title']) ?></div>
                    <?php if ($e['description']): ?>
                      <small class="text-muted"><?= htmlspecialchars(mb_substr($e['description'],0,40)) ?>…</small>
                    <?php endif; ?>
                  </td>
                  <td><span class="badge bg-<?= $c ?> badge-cat"><?= $e['category'] ?></span></td>
                  <td class="small">
                    <?= date('M d, Y', strtotime($e['event_date'])) ?><br>
                    <span class="text-muted"><?= date('h:i A', strtotime($e['event_time'])) ?></span>
                  </td>
                  <td class="small"><?= htmlspecialchars($e['location']) ?></td>
                  <td class="text-center"><?= $e['slots'] == 0 ? '<span class="text-muted">—</span>' : $e['slots'] ?></td>
                  <td class="text-center">
                    <span class="badge bg-<?= $e['reg_count'] > 0 ? 'info text-dark' : 'light text-dark border' ?>">
                      <?= $e['reg_count'] ?>
                    </span>
                  </td>
                  <td>
                    <?php if ($is_past): ?>
                      <span class="badge bg-secondary">Past</span>
                    <?php elseif ($is_full): ?>
                      <span class="badge bg-danger">Full</span>
                    <?php else: ?>
                      <span class="badge bg-success">Open</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <a href="?edit=<?= $e['id'] ?>" class="btn btn-sm btn-warning py-0 px-2">
                      <i class="bi bi-pencil"></i>
                    </a>
                    <a href="attendees.php?event_id=<?= $e['id'] ?>"
                       class="btn btn-sm btn-info py-0 px-2" title="View Attendees">
                      <i class="bi bi-people"></i>
                    </a>
                    <a href="?delete=<?= $e['id'] ?>"
                       class="btn btn-sm btn-danger py-0 px-2"
                       onclick="return confirm('Delete this event and all its registrations?')">
                      <i class="bi bi-trash"></i>
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
