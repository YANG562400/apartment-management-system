<?php
require '../config/auth.php';
require_login();
include '../config/db.php';

if (isset($_GET['restore_id'])) {
    $restoreId = (int) $_GET['restore_id'];
    $conn->query("UPDATE apartments SET is_archived = 0 WHERE id = $restoreId");
    $_SESSION['message'] = "<div class='alert alert-success text-center'>Apartment restored.</div>";
    header("Location: archived_apartments.php");
    exit;
}

$message = $_SESSION['message'] ?? '';
unset($_SESSION['message']);

$result = $conn->query("
    SELECT a.*, b.name AS building_name
    FROM apartments a
    LEFT JOIN buildings b ON a.building_id = b.id
    WHERE a.is_archived = 1
    ORDER BY a.updated_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Archived Apartments</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../assets/app-theme.css" rel="stylesheet">
</head>
<body>
<div class="page-shell">
    <section class="page-hero">
        <h1>Archived Apartments</h1>
        <p>Review archived apartment records and restore them back into the active inventory when needed.</p>
        <div class="hero-actions">
            <a href="index.php" class="btn btn-light btn-sm"><i class="fa-solid fa-arrow-left"></i> Apartments</a>
            <a href="../index.php" class="btn btn-outline-light btn-sm"><i class="fa-solid fa-house"></i> Dashboard</a>
        </div>
    </section>

    <?php if ($message) echo $message; ?>

    <section class="section-card">
        <div class="section-head">
            <h2>Archived apartment list</h2>
            <p>Restore any archived unit with one click.</p>
        </div>
        <div class="table-wrap">
            <div class="table-responsive">
                <table class="table table-modern align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Building</th>
                            <th>Unit Number</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Archived At</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td>#<?= (int) $row['id'] ?></td>
                                <td><?= htmlspecialchars($row['building_name'] ?? 'Unknown building') ?></td>
                                <td><?= htmlspecialchars($row['unit_number']) ?></td>
                                <td><?= htmlspecialchars($row['type']) ?></td>
                                <td><?= htmlspecialchars($row['status']) ?></td>
                                <td><?= htmlspecialchars($row['updated_at'] ?? '-') ?></td>
                                <td class="text-center">
                                    <a href="?restore_id=<?= (int) $row['id'] ?>" class="btn btn-sm btn-success" onclick="return confirm('Restore this apartment?')">Restore</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-4">No archived apartments.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>
</body>
</html>
