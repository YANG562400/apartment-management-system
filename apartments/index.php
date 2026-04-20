<?php
require '../config/auth.php';
require_login();
include '../config/db.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

$priceList = [
    'Studio' => 8000.00,
    '1 Bedroom' => 12000.00,
    '2 Bedroom' => 18000.00,
    'Penthouse' => 25000.00,
    'Default' => 10000.00
];

if (isset($_GET['archive_id'])) {
    $archiveId = (int) $_GET['archive_id'];
    $conn->query("UPDATE apartments SET is_archived = 1 WHERE id = $archiveId");
    $_SESSION['message'] = "<div class='alert alert-warning text-center'>Apartment archived.</div>";
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id']) && $_POST['edit_id'] !== '') {
    $id = (int) $_POST['edit_id'];
    $building_id = (int) $_POST['building_id'];
    $unit_number = trim($_POST['unit_number']);
    $type = trim($_POST['type']);
    $status = $_POST['status'] ?? 'vacant';

    $check = $conn->prepare("SELECT id FROM buildings WHERE id = ?");
    $check->bind_param("i", $building_id);
    $check->execute();
    if ($check->get_result()->num_rows === 0) {
        $_SESSION['message'] = "<div class='alert alert-danger text-center'>Error: Selected building does not exist.</div>";
        header("Location: index.php");
        exit;
    }

    $stmt = $conn->prepare("UPDATE apartments SET building_id = ?, unit_number = ?, type = ?, status = ? WHERE id = ?");
    $stmt->bind_param("isssi", $building_id, $unit_number, $type, $status, $id);
    if ($stmt->execute()) {
        $_SESSION['message'] = "<div class='alert alert-success text-center'>Apartment updated.</div>";
    } else {
        $_SESSION['message'] = "<div class='alert alert-danger text-center'>Error: {$stmt->error}</div>";
    }
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['edit_id']) || $_POST['edit_id'] === '')) {
    $building_id = (int) $_POST['building_id'];
    $unit_number = trim($_POST['unit_number']);
    $type = trim($_POST['type']);
    $status = $_POST['status'] ?? 'vacant';

    $check = $conn->prepare("SELECT id FROM buildings WHERE id = ?");
    $check->bind_param("i", $building_id);
    $check->execute();
    if ($check->get_result()->num_rows === 0) {
        $_SESSION['message'] = "<div class='alert alert-danger text-center'>Error: Selected building does not exist.</div>";
        header("Location: index.php");
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO apartments (building_id, unit_number, type, status) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $building_id, $unit_number, $type, $status);
    if ($stmt->execute()) {
        $_SESSION['message'] = "<div class='alert alert-success text-center'>Apartment added successfully.</div>";
    } else {
        $_SESSION['message'] = "<div class='alert alert-danger text-center'>Error: {$stmt->error}</div>";
    }
    header("Location: index.php");
    exit;
}

$message = $_SESSION['message'] ?? '';
unset($_SESSION['message']);

$typeFilter = $_GET['type'] ?? '';
$minPrice = isset($_GET['min_price']) ? (float) $_GET['min_price'] : 0;
$maxPrice = isset($_GET['max_price']) ? (float) $_GET['max_price'] : 999999;

$editData = ['id' => '', 'building_id' => '', 'unit_number' => '', 'type' => '', 'status' => 'vacant'];
if (isset($_GET['edit_id'])) {
    $eid = (int) $_GET['edit_id'];
    $stmt = $conn->prepare("SELECT id, building_id, unit_number, type, status FROM apartments WHERE id = ?");
    $stmt->bind_param("i", $eid);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows) {
        $editData = $res->fetch_assoc();
    }
}
$editPrice = $priceList[$editData['type']] ?? $priceList['Default'];

$totalActive = (int) ($conn->query("SELECT COUNT(*) AS count FROM apartments WHERE is_archived = 0")->fetch_assoc()['count'] ?? 0);
$totalVacant = (int) ($conn->query("SELECT COUNT(*) AS count FROM apartments WHERE status = 'vacant' AND is_archived = 0")->fetch_assoc()['count'] ?? 0);
$totalOccupied = (int) ($conn->query("SELECT COUNT(*) AS count FROM apartments WHERE status = 'occupied' AND is_archived = 0")->fetch_assoc()['count'] ?? 0);

$vacant = $conn->query("
    SELECT a.*, b.name AS building_name
    FROM apartments a
    LEFT JOIN buildings b ON a.building_id = b.id
    WHERE a.status = 'vacant' AND a.is_archived = 0
    ORDER BY a.id DESC
");

$occupied = $conn->query("
    SELECT a.*, b.name AS building_name,
           t.id AS tenant_id, t.name AS tenant_name, t.contact AS tenant_contact, t.move_in_date
    FROM apartments a
    LEFT JOIN buildings b ON a.building_id = b.id
    LEFT JOIN tenants t ON a.id = t.apartment_id
    WHERE a.status = 'occupied' AND a.is_archived = 0
    ORDER BY a.id DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Apartments</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="../assets/app-theme.css" rel="stylesheet">
  <style>
    .apartment-grid .row {
      --bs-gutter-x: 0.9rem;
      --bs-gutter-y: 0.9rem;
    }

    .apartment-card {
      padding: 14px;
      border-radius: 18px;
    }

    .apartment-card h4 {
      margin: 0 0 4px;
      font-size: 18px;
    }

    .apartment-card .unit-icon {
      font-size: 1.45rem;
    }

    .apartment-card .soft-badge,
    .apartment-card .status-pill {
      padding: 5px 10px;
      font-size: 12px;
    }

    .apartment-card .list-stack {
      gap: 8px;
    }

    .apartment-card .list-stack strong {
      font-size: 12px;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      color: #51657d;
    }

    .apartment-card .list-stack span {
      font-size: 14px;
      line-height: 1.35;
    }

    .apartment-card .d-flex.gap-2.mt-4 {
      margin-top: 14px !important;
    }

    .apartment-card .btn.btn-sm {
      padding: 0.35rem 0.75rem;
      font-size: 0.82rem;
    }
  </style>
</head>
<body>
<div class="page-shell">
  <section class="page-hero">
    <h1>Apartment Management</h1>
    <p>Manage available and occupied units, filter by type and price, and keep your apartment inventory organized from one consistent workspace.</p>
    <div class="hero-actions">
      <a href="../index.php" class="btn btn-light btn-sm"><i class="fa-solid fa-arrow-left"></i> Dashboard</a>
      <a href="archived_apartments.php" class="btn btn-outline-light btn-sm"><i class="fa-solid fa-box-archive"></i> Archived Apartments</a>
    </div>
  </section>

  <?php if ($message) echo $message; ?>

  <div class="metric-row">
    <div class="metric-card"><strong><?= $totalActive ?></strong><span>Active apartments</span></div>
    <div class="metric-card"><strong><?= $totalVacant ?></strong><span>Vacant apartments</span></div>
    <div class="metric-card"><strong><?= $totalOccupied ?></strong><span>Occupied apartments</span></div>
  </div>

  <section class="section-card mb-4">
    <div class="section-head">
      <h2><?= isset($_GET['edit_id']) ? 'Edit apartment' : 'Add apartment' ?></h2>
      <p><?= isset($_GET['edit_id']) ? 'Update the selected apartment details.' : 'Create a new apartment record.' ?></p>
    </div>
    <div class="section-body">
      <form method="post">
        <input type="hidden" name="edit_id" value="<?= htmlspecialchars($editData['id']) ?>">
        <div class="row g-3">
          <div class="col-md-2">
            <label class="form-label">Building ID</label>
            <input type="number" name="building_id" class="form-control" value="<?= htmlspecialchars($editData['building_id']) ?>" required>
          </div>
          <div class="col-md-2">
            <label class="form-label">Unit Number</label>
            <input type="text" name="unit_number" class="form-control" value="<?= htmlspecialchars($editData['unit_number']) ?>" required>
          </div>
          <div class="col-md-3">
            <label class="form-label">Type</label>
            <select name="type" id="typeSelect" class="form-select" required>
              <option value="">Select Type</option>
              <?php foreach ($priceList as $tname => $val): if ($tname === 'Default') continue; ?>
                <option value="<?= htmlspecialchars($tname) ?>" data-price="<?= $val ?>" <?= $editData['type'] === $tname ? 'selected' : '' ?>>
                  <?= htmlspecialchars($tname) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">Estimated Price</label>
            <input id="displayPrice" type="text" class="form-control" readonly value="PHP <?= number_format($editPrice, 2) ?>">
          </div>
          <div class="col-md-2">
            <label class="form-label">Status</label>
            <select name="status" class="form-select" required>
              <option value="vacant" <?= $editData['status'] === 'vacant' ? 'selected' : '' ?>>Available</option>
              <option value="occupied" <?= $editData['status'] === 'occupied' ? 'selected' : '' ?>>Occupied</option>
              <option value="maintenance" <?= $editData['status'] === 'maintenance' ? 'selected' : '' ?>>Maintenance</option>
            </select>
          </div>
          <div class="col-md-1 d-flex align-items-end justify-content-end">
            <div class="d-flex gap-2 justify-content-end w-100">
              <?php if (isset($_GET['edit_id'])): ?>
                <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
              <?php endif; ?>
              <button type="submit" class="btn btn-<?= isset($_GET['edit_id']) ? 'warning' : 'primary' ?>">
                <?= isset($_GET['edit_id']) ? 'Update' : 'Add' ?>
              </button>
            </div>
          </div>
        </div>
      </form>
    </div>
  </section>

  <div class="row g-4">
    <div class="col-12">
      <section class="section-card">
        <div class="section-head">
          <h2>Filter apartments</h2>
          <p>Narrow the apartment cards by type and price range.</p>
        </div>
        <div class="section-body">
          <form method="get" class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Type</label>
              <select name="type" class="form-select">
                <option value="">All Types</option>
                <?php foreach ($priceList as $tname => $val): if ($tname === 'Default') continue; ?>
                  <option value="<?= htmlspecialchars($tname) ?>" <?= $typeFilter === $tname ? 'selected' : '' ?>><?= htmlspecialchars($tname) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Min Price</label>
              <input type="number" name="min_price" class="form-control" step="100" value="<?= htmlspecialchars($minPrice) ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">Max Price</label>
              <input type="number" name="max_price" class="form-control" step="100" value="<?= htmlspecialchars($maxPrice) ?>">
            </div>
            <div class="col-md-2 d-flex align-items-end gap-2">
              <button type="submit" class="btn btn-primary w-100">Apply</button>
              <a href="index.php" class="btn btn-outline-secondary">Reset</a>
            </div>
          </form>
        </div>
      </section>

      <section class="section-card mt-4 apartment-grid">
        <div class="section-head">
          <h2>Available apartments</h2>
          <p>Units ready for new tenants.</p>
        </div>
        <div class="section-body">
          <div class="row g-4">
            <?php $foundVacant = false; ?>
            <?php if ($vacant && $vacant->num_rows > 0): ?>
              <?php while ($row = $vacant->fetch_assoc()): ?>
                <?php
                $price = $priceList[$row['type']] ?? $priceList['Default'];
                if (($typeFilter && $row['type'] !== $typeFilter) || $price < $minPrice || $price > $maxPrice) {
                    continue;
                }
                $foundVacant = true;
                ?>
                <div class="col-md-6 col-xl-4">
                  <div class="info-card apartment-card">
                    <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                      <div>
                        <h4>Unit <?= htmlspecialchars($row['unit_number']) ?></h4>
                        <span class="soft-badge status-resolved"><?= htmlspecialchars($row['type']) ?></span>
                      </div>
                      <i class="bi bi-door-open unit-icon" style="color:#198754;"></i>
                    </div>
                    <div class="list-stack">
                      <div><strong>Apartment ID</strong><span class="d-block"><?= (int) $row['id'] ?></span></div>
                      <div><strong>Building</strong><span class="d-block"><?= htmlspecialchars($row['building_name']) ?></span></div>
                      <div><strong>Price</strong><span class="d-block">PHP <?= number_format($price, 2) ?></span></div>
                      <div><strong>Status</strong><span class="status-pill status-resolved mt-1">Vacant</span></div>
                    </div>
                    <div class="d-flex gap-2 mt-4">
                      <a href="?edit_id=<?= (int) $row['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                      <a href="?archive_id=<?= (int) $row['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Archive this apartment?')">Archive</a>
                    </div>
                  </div>
                </div>
              <?php endwhile; ?>
            <?php endif; ?>
            <?php if (!$foundVacant): ?>
              <div class="col-12">
                <div class="empty-card">No available apartments found for the current filter.</div>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </section>

      <section class="section-card mt-4 apartment-grid">
        <div class="section-head">
          <h2>Occupied apartments</h2>
          <p>Units currently assigned to tenants.</p>
        </div>
        <div class="section-body">
          <div class="row g-4">
            <?php $foundOccupied = false; ?>
            <?php if ($occupied && $occupied->num_rows > 0): ?>
              <?php while ($row = $occupied->fetch_assoc()): ?>
                <?php
                $price = $priceList[$row['type']] ?? $priceList['Default'];
                if (($typeFilter && $row['type'] !== $typeFilter) || $price < $minPrice || $price > $maxPrice) {
                    continue;
                }
                $foundOccupied = true;
                ?>
                <div class="col-md-6 col-xl-4">
                  <div class="info-card apartment-card">
                    <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                      <div>
                        <h4>Unit <?= htmlspecialchars($row['unit_number']) ?></h4>
                        <span class="soft-badge status-pending"><?= htmlspecialchars($row['type']) ?></span>
                      </div>
                      <i class="bi bi-person-fill-lock unit-icon" style="color:#c44536;"></i>
                    </div>
                    <div class="list-stack">
                      <div><strong>Apartment ID</strong><span class="d-block"><?= (int) $row['id'] ?></span></div>
                      <div><strong>Building</strong><span class="d-block"><?= htmlspecialchars($row['building_name']) ?></span></div>
                      <div><strong>Price</strong><span class="d-block">PHP <?= number_format($price, 2) ?></span></div>
                      <?php if (!empty($row['tenant_name'])): ?>
                        <div><strong>Tenant</strong><span class="d-block"><?= htmlspecialchars($row['tenant_name']) ?></span></div>
                        <div><strong>Contact</strong><span class="d-block"><?= htmlspecialchars($row['tenant_contact']) ?></span></div>
                        <div><strong>Move-in Date</strong><span class="d-block"><?= htmlspecialchars($row['move_in_date']) ?></span></div>
                      <?php endif; ?>
                      <div><strong>Status</strong><span class="status-pill status-pending mt-1">Occupied</span></div>
                    </div>
                    <div class="d-flex gap-2 mt-4">
                      <a href="?edit_id=<?= (int) $row['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                      <a href="?archive_id=<?= (int) $row['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Archive this apartment?')">Archive</a>
                    </div>
                  </div>
                </div>
              <?php endwhile; ?>
            <?php endif; ?>
            <?php if (!$foundOccupied): ?>
              <div class="col-12">
                <div class="empty-card">No occupied apartments found for the current filter.</div>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </section>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
  const typeSelect = document.getElementById('typeSelect');
  const displayPrice = document.getElementById('displayPrice');
  if (!typeSelect || !displayPrice) return;

  function fmt(n) {
    return 'PHP ' + Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  typeSelect.addEventListener('change', function () {
    const opt = typeSelect.selectedOptions[0];
    const p = opt && opt.dataset ? opt.dataset.price : null;
    displayPrice.value = fmt(p || <?= json_encode($priceList['Default']) ?>);
  });
})();
</script>
</body>
</html>
