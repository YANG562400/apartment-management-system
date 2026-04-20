<?php
require '../config/auth.php';
require_login();
include '../config/db.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

require '../config/mailer.php';

function sendNewTenantEmail($id, $name, $contact, $apartment, $move_in_date)
{
    $body = "
        <h2>New Tenant Added</h2>
        <ul>
            <li><strong>ID:</strong> $id</li>
            <li><strong>Name:</strong> $name</li>
            <li><strong>Contact:</strong> $contact</li>
            <li><strong>Apartment ID:</strong> $apartment</li>
            <li><strong>Move-in Date:</strong> $move_in_date</li>
        </ul>
    ";

    send_app_email("New Tenant Added (ID: $id)", $body);
}

if (isset($_GET['delete_id'])) {
    $id = trim($_GET['delete_id']);

    $apartmentQuery = $conn->prepare("SELECT apartment_id FROM tenants WHERE id = ?");
    $apartmentQuery->bind_param("s", $id);
    $apartmentQuery->execute();
    $apartmentResult = $apartmentQuery->get_result();

    if ($apartmentResult->num_rows > 0) {
        $apt = $apartmentResult->fetch_assoc();
        $apartmentId = (int) $apt['apartment_id'];

        $stmt = $conn->prepare("DELETE FROM tenants WHERE id = ?");
        $stmt->bind_param("s", $id);

        if ($stmt->execute()) {
            $conn->query("UPDATE apartments SET status = 'vacant' WHERE id = $apartmentId");
            $_SESSION['message'] = "<div class='alert alert-danger text-center'>Tenant removed.</div>";
        } else {
            $_SESSION['message'] = "<div class='alert alert-danger text-center'>Error: {$stmt->error}</div>";
        }
    } else {
        $_SESSION['message'] = "<div class='alert alert-warning text-center'>Tenant not found.</div>";
    }

    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    do {
        $id = (string) rand(1000, 9999);
        $check = $conn->prepare("SELECT id FROM tenants WHERE id = ?");
        $check->bind_param("s", $id);
        $check->execute();
        $exists = $check->get_result();
    } while ($exists->num_rows > 0);

    $name = trim($_POST['name']);
    $contact = trim($_POST['contact']);
    $apartment_id = (int) $_POST['apartment_id'];
    $move_in_date = $_POST['move_in_date'];

    $checkApt = $conn->prepare("SELECT status FROM apartments WHERE id = ? AND is_archived = 0");
    $checkApt->bind_param("i", $apartment_id);
    $checkApt->execute();
    $result = $checkApt->get_result();

    if ($result->num_rows === 0) {
        $_SESSION['message'] = "<div class='alert alert-danger text-center'>Apartment not found or archived.</div>";
    } else {
        $apt = $result->fetch_assoc();

        if ($apt['status'] === 'occupied') {
            $_SESSION['message'] = "<div class='alert alert-warning text-center'>Apartment is already occupied.</div>";
        } else {
            $stmt = $conn->prepare("INSERT INTO tenants (id, name, contact, apartment_id, move_in_date) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssis", $id, $name, $contact, $apartment_id, $move_in_date);

            if ($stmt->execute()) {
                $conn->query("UPDATE apartments SET status = 'occupied' WHERE id = $apartment_id");
                sendNewTenantEmail($id, $name, $contact, $apartment_id, $move_in_date);
                $_SESSION['message'] = "<div class='alert alert-success text-center'>Tenant added successfully. Generated ID: $id</div>";
            } else {
                $_SESSION['message'] = "<div class='alert alert-danger text-center'>Error: {$stmt->error}</div>";
            }
        }
    }

    header("Location: index.php");
    exit;
}

$message = $_SESSION['message'] ?? '';
unset($_SESSION['message']);

$tenantCount = (int) ($conn->query("SELECT COUNT(*) AS count FROM tenants")->fetch_assoc()['count'] ?? 0);
$occupiedCount = (int) ($conn->query("SELECT COUNT(*) AS count FROM apartments WHERE status = 'occupied' AND is_archived = 0")->fetch_assoc()['count'] ?? 0);
$vacantCount = (int) ($conn->query("SELECT COUNT(*) AS count FROM apartments WHERE status = 'vacant' AND is_archived = 0")->fetch_assoc()['count'] ?? 0);

$tenantData = $conn->query("
    SELECT t.*, a.unit_number, a.type
    FROM tenants t
    JOIN apartments a ON t.apartment_id = a.id
    ORDER BY t.move_in_date DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Tenant Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../assets/app-theme.css" rel="stylesheet">
</head>
<body>
<div class="page-shell">
    <section class="page-hero">
        <h1>Tenant Management</h1>
        <p>Add tenants, review occupancy, and remove records from a cleaner card-based layout that still keeps your current workflow intact.</p>
        <div class="hero-actions">
            <a href="../index.php" class="btn btn-light btn-sm"><i class="fa-solid fa-arrow-left"></i> Dashboard</a>
            <a href="../payments/index.php" class="btn btn-outline-light btn-sm"><i class="fa-solid fa-money-bill-wave"></i> Payments</a>
        </div>
    </section>

    <?php if ($message) echo $message; ?>

    <div class="metric-row">
        <div class="metric-card"><strong><?= $tenantCount ?></strong><span>Registered tenants</span></div>
        <div class="metric-card"><strong><?= $occupiedCount ?></strong><span>Occupied apartments</span></div>
        <div class="metric-card"><strong><?= $vacantCount ?></strong><span>Vacant apartments</span></div>
    </div>

    <section class="section-card">
        <div class="section-head">
            <h2>Add tenant</h2>
            <p>Create a tenant record and assign an apartment by ID.</p>
        </div>
        <div class="section-body">
            <form method="post">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Tenant Name</label>
                        <input type="text" name="name" class="form-control" placeholder="Enter full name" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Contact</label>
                        <input type="text" name="contact" class="form-control" placeholder="Phone or email" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Apartment ID</label>
                        <input type="number" name="apartment_id" class="form-control" placeholder="Unit ID" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Move-in Date</label>
                        <input type="date" name="move_in_date" class="form-control" required>
                    </div>
                    <div class="col-md-1 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Add</button>
                    </div>
                </div>
            </form>
        </div>
    </section>

    <section class="section-card mt-4">
        <div class="section-head">
            <h2>Tenant records</h2>
            <p>Current tenants grouped into clean profile cards.</p>
        </div>
        <div class="section-body">
            <?php if ($tenantData && $tenantData->num_rows > 0): ?>
                <div class="row g-4">
                    <?php while ($row = $tenantData->fetch_assoc()): ?>
                        <div class="col-md-6 col-xl-4">
                            <div class="info-card">
                                <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                                    <div>
                                        <h4><?= htmlspecialchars($row['name']) ?></h4>
                                        <span class="soft-badge status-progress">Tenant ID <?= htmlspecialchars($row['id']) ?></span>
                                    </div>
                                    <i class="fa-solid fa-user fa-2x" style="color:#0f4c81;"></i>
                                </div>
                                <div class="list-stack">
                                    <div><strong>Contact</strong><span class="d-block"><?= htmlspecialchars($row['contact']) ?></span></div>
                                    <div><strong>Unit</strong><span class="d-block">Unit <?= htmlspecialchars($row['unit_number']) ?> (<?= htmlspecialchars($row['type']) ?>)</span></div>
                                    <div><strong>Move-in Date</strong><span class="d-block"><?= htmlspecialchars($row['move_in_date']) ?></span></div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-4 gap-2">
                                    <span class="soft-badge status-resolved">Active</span>
                                    <a href="?delete_id=<?= urlencode($row['id']) ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Remove tenant?');">
                                        <i class="fa-solid fa-trash"></i> Remove
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-card">No tenants found yet.</div>
            <?php endif; ?>
        </div>
    </section>
</div>
</body>
</html>
