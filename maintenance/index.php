<?php
require '../config/auth.php';
require_login();
include '../config/db.php';

$success = false;
$updateSuccess = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_request'])) {
    $apartment_id = $_POST['apartment_id'];
    $description = $_POST['description'];

    $stmt = $conn->prepare("INSERT INTO maintenance (apartment_id, description, request_date) VALUES (?, ?, NOW())");
    $stmt->bind_param("is", $apartment_id, $description);
    $stmt->execute();
    $success = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $id = $_POST['maintenance_id'];
    $new_status = $_POST['status'];

    $stmt = $conn->prepare("UPDATE maintenance SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $id);
    $stmt->execute();
    $updateSuccess = true;
}

$apartments = $conn->query("SELECT id, unit_number FROM apartments WHERE is_archived = 0");

$status_filter = $_GET['status'] ?? '';
if ($status_filter) {
    $stmt = $conn->prepare("SELECT * FROM maintenance WHERE status = ? ORDER BY request_date DESC");
    $stmt->bind_param("s", $status_filter);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query("SELECT * FROM maintenance ORDER BY request_date DESC");
}

function timeAgo($datetime)
{
    $dt = new DateTime($datetime, new DateTimeZone('Asia/Manila'));
    $now = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $diff = $now->getTimestamp() - $dt->getTimestamp();

    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' mins ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hrs ago';
    return floor($diff / 86400) . ' days ago';
}

$pendingCount = (int) ($conn->query("SELECT COUNT(*) AS count FROM maintenance WHERE status = 'pending'")->fetch_assoc()['count'] ?? 0);
$progressCount = (int) ($conn->query("SELECT COUNT(*) AS count FROM maintenance WHERE status = 'in_progress'")->fetch_assoc()['count'] ?? 0);
$resolvedCount = (int) ($conn->query("SELECT COUNT(*) AS count FROM maintenance WHERE status = 'resolved'")->fetch_assoc()['count'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Maintenance Requests</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../assets/app-theme.css" rel="stylesheet">
</head>
<body>
<div class="page-shell">
    <section class="page-hero">
        <h1>Maintenance Requests</h1>
        <p>Monitor pending, in-progress, and resolved maintenance work with a simpler layout and quick status updates.</p>
        <div class="hero-actions">
            <a href="../index.php" class="btn btn-light btn-sm"><i class="fa-solid fa-arrow-left"></i> Dashboard</a>
            <a href="?status=pending" class="btn btn-outline-light btn-sm"><i class="fa-solid fa-filter"></i> Show Pending</a>
        </div>
    </section>

    <?php if ($success): ?>
        <div class="alert alert-success text-center">Maintenance request added successfully.</div>
    <?php endif; ?>
    <?php if ($updateSuccess): ?>
        <div class="alert alert-info text-center">Status updated successfully.</div>
    <?php endif; ?>

    <div class="metric-row">
        <div class="metric-card"><strong><?= $pendingCount ?></strong><span>Pending requests</span></div>
        <div class="metric-card"><strong><?= $progressCount ?></strong><span>In progress</span></div>
        <div class="metric-card"><strong><?= $resolvedCount ?></strong><span>Resolved requests</span></div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <section class="section-card h-100">
                <div class="section-head">
                    <h2>Add request</h2>
                    <p>Log a new maintenance issue for an active apartment.</p>
                </div>
                <div class="section-body">
                    <form method="post">
                        <input type="hidden" name="add_request" value="1">
                        <div class="mb-3">
                            <label class="form-label">Apartment Unit</label>
                            <select class="form-select" name="apartment_id" required>
                                <option value="">Select Apartment</option>
                                <?php while ($apt = $apartments->fetch_assoc()): ?>
                                    <option value="<?= $apt['id'] ?>">Unit <?= htmlspecialchars($apt['unit_number']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" required></textarea>
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">Submit Request</button>
                        </div>
                    </form>
                </div>
            </section>
        </div>

        <div class="col-lg-8">
            <section class="section-card h-100">
                <div class="section-head">
                    <h2>Request list</h2>
                    <p>Filter by status and update each request inline.</p>
                </div>
                <div class="section-body pt-3">
                    <form method="get" class="toolbar-row mb-3">
                        <div class="flex-grow-1">
                            <label class="form-label">Filter by Status</label>
                            <select name="status" class="form-select" onchange="this.form.submit()">
                                <option value="">All</option>
                                <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="in_progress" <?= $status_filter === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                <option value="resolved" <?= $status_filter === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="table-wrap pt-0">
                    <div class="table-responsive">
                        <table class="table table-modern align-middle">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Apartment</th>
                                    <th>Description</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <?php
                                    $statusClass = match ($row['status']) {
                                        'pending' => 'status-pending',
                                        'in_progress' => 'status-progress',
                                        'resolved' => 'status-resolved',
                                        default => ''
                                    };
                                    ?>
                                    <tr>
                                        <td>#<?= $row['id'] ?></td>
                                        <td>Apartment <?= htmlspecialchars($row['apartment_id']) ?></td>
                                        <td><?= htmlspecialchars($row['description']) ?></td>
                                        <td><?= htmlspecialchars(timeAgo($row['request_date'])) ?></td>
                                        <td>
                                            <form method="post" class="d-flex flex-wrap align-items-center gap-2">
                                                <input type="hidden" name="update_status" value="1">
                                                <input type="hidden" name="maintenance_id" value="<?= $row['id'] ?>">
                                                <select name="status" class="form-select form-select-sm" style="min-width: 170px;" onchange="this.form.submit()">
                                                    <option value="pending" <?= $row['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                                    <option value="in_progress" <?= $row['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                                    <option value="resolved" <?= $row['status'] === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                                                </select>
                                                <span class="status-pill <?= $statusClass ?>"><?= ucwords(str_replace('_', ' ', $row['status'])) ?></span>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4">No maintenance records found.</td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
