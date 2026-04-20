<?php
require '../config/auth.php';
require_login();
include '../config/db.php';

// Flash message handling
$message = $_SESSION['message'] ?? '';
unset($_SESSION['message']);

// Handle restore request
if (isset($_GET['restore'])) {
    $id = (int) $_GET['restore'];
    $stmt = $conn->prepare("UPDATE buildings SET is_archived = 0, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $_SESSION['message'] = "<div class='alert alert-success'>Building restored successfully.</div>";
    } else {
        $_SESSION['message'] = "<div class='alert alert-danger'>Restore failed: " . $stmt->error . "</div>";
    }
    header("Location: archived_buildings.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Archived Buildings</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet"> <!-- Link to external CSS -->
</head>

<body>
<div class="container mt-5">
    <h2 class="mb-4 text-center">Archived Buildings</h2>

    <?php if (!empty($message)) echo $message; ?>

    <table class="table table-bordered table-striped">
        <thead class="table-dark">
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Address</th>
                <th>Archived At</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $result = $conn->query("SELECT * FROM buildings WHERE is_archived = 1 ORDER BY updated_at DESC");

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                echo "<tr>
                        <td>{$row['id']}</td>
                        <td>" . htmlspecialchars($row['name']) . "</td>
                        <td>" . htmlspecialchars($row['address']) . "</td>
                        <td>" . ($row['updated_at'] ?? '-') . "</td>
                        <td>
                            <a class='btn btn-success btn-sm' href='archived_buildings.php?restore={$row['id']}' onclick='return confirm(\"Restore this building?\");'>Restore</a>
                        </td>
                      </tr>";
            }
        } else {
            echo "<tr><td colspan='5' class='text-center'>No archived buildings found.</td></tr>";
        }
        ?>
        </tbody>
    </table>

    <a href="index.php" class="btn btn-secondary mt-3">&larr; Back to Buildings</a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
