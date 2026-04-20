<?php
require '../config/auth.php';
require_login();
include '../config/db.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

$message = $_SESSION['message'] ?? '';
unset($_SESSION['message']);

if (isset($_GET['archive'])) {
    $id = (int) $_GET['archive'];
    $stmt = $conn->prepare("UPDATE buildings SET is_archived = 1, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $_SESSION['message'] = "<div class='alert alert-warning text-center'>Building archived successfully.</div>";
    } else {
        $_SESSION['message'] = "<div class='alert alert-danger text-center'>Archive failed: " . $stmt->error . "</div>";
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

if (isset($_POST['update'])) {
    $id = (int) $_POST['id'];
    $name = trim($_POST['name']);
    $address = trim($_POST['address']);

    if ($name && $address) {
        $stmt = $conn->prepare("UPDATE buildings SET name = ?, address = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("ssi", $name, $address, $id);
        if ($stmt->execute()) {
            $_SESSION['message'] = "<div class='alert alert-success text-center'>Building updated successfully.</div>";
        } else {
            $_SESSION['message'] = "<div class='alert alert-danger text-center'>Update failed: " . $stmt->error . "</div>";
        }
    } else {
        $_SESSION['message'] = "<div class='alert alert-warning text-center'>Both name and address are required.</div>";
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

if (isset($_POST['save'])) {
    $name = trim($_POST['name']);
    $address = trim($_POST['address']);

    if ($name && $address) {
        $stmt = $conn->prepare("INSERT INTO buildings (name, address, created_at) VALUES (?, ?, NOW())");
        $stmt->bind_param("ss", $name, $address);
        if ($stmt->execute()) {
            $_SESSION['message'] = "<div class='alert alert-success text-center'>Building added successfully.</div>";
        } else {
            $_SESSION['message'] = "<div class='alert alert-danger text-center'>Insert failed: " . $stmt->error . "</div>";
        }
    } else {
        $_SESSION['message'] = "<div class='alert alert-warning text-center'>Both name and address are required.</div>";
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

$search = trim($_GET['search'] ?? '');
$activeCount = (int) ($conn->query("SELECT COUNT(*) AS count FROM buildings WHERE is_archived = 0")->fetch_assoc()['count'] ?? 0);
$archivedCount = (int) ($conn->query("SELECT COUNT(*) AS count FROM buildings WHERE is_archived = 1")->fetch_assoc()['count'] ?? 0);

$edit = false;
$formData = ['id' => '', 'name' => '', 'address' => ''];
if (isset($_GET['edit'])) {
    $edit = true;
    $id = (int) $_GET['edit'];
    $res = $conn->query("SELECT * FROM buildings WHERE id = $id");
    if ($res && $res->num_rows > 0) {
        $formData = $res->fetch_assoc();
    }
}

if ($search !== '') {
    $like = "%$search%";
    $stmt = $conn->prepare("SELECT * FROM buildings WHERE (name LIKE ? OR address LIKE ?) AND is_archived = 0 ORDER BY id ASC");
    $stmt->bind_param("ss", $like, $like);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query("SELECT * FROM buildings WHERE is_archived = 0 ORDER BY id ASC");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Buildings Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../assets/app-theme.css" rel="stylesheet">
</head>
<body>
<div class="page-shell">
    <section class="page-hero">
        <h1>Buildings Management</h1>
        <p>Add, update, and organize your building records from one cleaner workspace. This view keeps active and archived properties easy to manage.</p>
        <div class="hero-actions">
            <a href="../index.php" class="btn btn-light btn-sm"><i class="fa-solid fa-arrow-left"></i> Dashboard</a>
            <a href="archived_buildings.php" class="btn btn-outline-light btn-sm"><i class="fa-solid fa-box-archive"></i> Archived Buildings</a>
        </div>
    </section>

    <?php if (!empty($message)) echo $message; ?>

    <div class="metric-row">
        <div class="metric-card"><strong><?= $activeCount ?></strong><span>Active buildings</span></div>
        <div class="metric-card"><strong><?= $archivedCount ?></strong><span>Archived buildings</span></div>
        <div class="metric-card"><strong><?= $search === '' ? 'All' : htmlspecialchars($search) ?></strong><span>Current search view</span></div>
    </div>

    <section class="section-card">
        <div class="section-head">
            <h2>Search buildings</h2>
            <p>Filter the list by building name or address.</p>
        </div>
        <div class="section-body">
            <form method="get" class="toolbar-row">
                <div class="flex-grow-1">
                    <input type="text" name="search" class="form-control" placeholder="Search by name or address" value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Search</button>
                    <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </section>

    <div class="row g-4 mt-1">
        <div class="col-lg-4">
            <section class="section-card h-100">
                <div class="section-head">
                    <h2><?= $edit ? 'Edit building' : 'Add building' ?></h2>
                    <p><?= $edit ? 'Update the selected building details.' : 'Create a new building record.' ?></p>
                </div>
                <div class="section-body">
                    <form method="post">
                        <input type="hidden" name="id" value="<?= htmlspecialchars($formData['id']) ?>">
                        <div class="mb-3">
                            <label class="form-label">Building Name</label>
                            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($formData['name']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <input type="text" name="address" class="form-control" value="<?= htmlspecialchars($formData['address']) ?>" required>
                        </div>
                        <div class="d-flex gap-2 justify-content-end">
                            <?php if ($edit): ?>
                                <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
                            <?php endif; ?>
                            <button type="submit" name="<?= $edit ? 'update' : 'save' ?>" class="btn btn-<?= $edit ? 'warning' : 'primary' ?>">
                                <?= $edit ? 'Update Building' : 'Save Building' ?>
                            </button>
                        </div>
                    </form>
                </div>
            </section>
        </div>

        <div class="col-lg-8">
            <section class="section-card h-100">
                <div class="section-head">
                    <h2>Active buildings</h2>
                    <p>Review all active building records and open quick actions.</p>
                </div>
                <div class="table-wrap">
                    <div class="table-responsive">
                        <table class="table table-modern align-middle">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Address</th>
                                    <th>Created</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td>#<?= (int) $row['id'] ?></td>
                                        <td><?= htmlspecialchars($row['name']) ?></td>
                                        <td><?= htmlspecialchars($row['address']) ?></td>
                                        <td><?= htmlspecialchars($row['created_at'] ?? '-') ?></td>
                                        <td class="text-center">
                                            <div class="d-flex flex-wrap justify-content-center gap-2">
                                                <a href="?edit=<?= (int) $row['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                                                <a href="?archive=<?= (int) $row['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Archive this building?');">Archive</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4">No buildings found.</td>
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
