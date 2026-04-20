<?php
require '../config/auth.php';
require_login();
include '../config/db.php';
require '../config/mailer.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

$priceList = [
    'Studio' => 8000.00,
    '1 Bedroom' => 12000.00,
    '2 Bedroom' => 18000.00,
    'Penthouse' => 25000.00,
    'Default' => 10000.00
];

$success = false;
$error_message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tenant_id = $_POST['tenant_id'] ?? '';
    $amount = (float) ($_POST['amount'] ?? 0);
    $payment_date = $_POST['payment_date'] ?? date('Y-m-d');
    $remarks = trim($_POST['remarks'] ?? '');

    if ($tenant_id === '' || $amount <= 0) {
        $error_message = "Please select a tenant and enter a valid amount.";
    } else {
        $stmt = $conn->prepare("
            SELECT t.apartment_id, a.type, t.name
            FROM tenants t
            LEFT JOIN apartments a ON t.apartment_id = a.id
            WHERE t.id = ?
            LIMIT 1
        ");

        if (!$stmt) {
            $error_message = "Prepare failed: " . $conn->error;
        } else {
            $stmt->bind_param("s", $tenant_id);
            $stmt->execute();
            $res = $stmt->get_result();

            if (!$res || $res->num_rows === 0) {
                $error_message = "Tenant not found or tenant has no linked apartment.";
            } else {
                $row = $res->fetch_assoc();
                $apt_type = $row['type'] ?? null;
                $tenant_name = $row['name'];
                $monthly_rent = $priceList[$apt_type] ?? $priceList['Default'];
                $payments_to_insert = [];
                $payment_timestamp = strtotime($payment_date);

                if ($monthly_rent <= 0) {
                    $error_message = "Monthly rent for this apartment type is invalid.";
                } else {
                    if ($amount >= $monthly_rent) {
                        $full_months = (int) floor($amount / $monthly_rent);
                        $remainder = round($amount - ($full_months * $monthly_rent), 2);

                        for ($i = 0; $i < $full_months; $i++) {
                            $d = date('Y-m-d', strtotime("+{$i} month", $payment_timestamp));
                            $payments_to_insert[] = ['date' => $d, 'amount' => $monthly_rent];
                        }

                        if ($remainder > 0.0) {
                            $d = date('Y-m-d', strtotime("+{$full_months} month", $payment_timestamp));
                            $payments_to_insert[] = ['date' => $d, 'amount' => round($remainder, 2)];
                        }
                    } else {
                        $payments_to_insert[] = ['date' => date('Y-m-d', $payment_timestamp), 'amount' => round($amount, 2)];
                    }

                    $insStmt = $conn->prepare("INSERT INTO payments (tenant_id, amount, payment_date, remarks) VALUES (?, ?, ?, ?)");
                    if (!$insStmt) {
                        $error_message = "Prepare failed: " . $conn->error;
                    } else {
                        $conn->begin_transaction();
                        $ok = true;

                        foreach ($payments_to_insert as $p) {
                            $insStmt->bind_param("sdss", $tenant_id, $p['amount'], $p['date'], $remarks);
                            if (!$insStmt->execute()) {
                                $ok = false;
                                $error_message = "Insert failed: " . $insStmt->error;
                                break;
                            }
                        }

                        if ($ok) {
                            $conn->commit();
                            $success = true;
                            $body = "<p>Tenant: <strong>{$tenant_name}</strong></p>";
                            $body .= "<p>Amount: <strong>PHP " . number_format($amount, 2) . "</strong></p>";
                            $body .= "<p>Payment Date: <strong>{$payment_date}</strong></p>";
                            $body .= "<p>Remarks: <strong>" . htmlspecialchars($remarks) . "</strong></p>";
                            send_app_email('New Payment Received', $body);
                        } else {
                            $conn->rollback();
                        }
                    }
                }
            }
        }
    }

    if ($success) {
        header("Location: " . htmlspecialchars($_SERVER['PHP_SELF']) . "?success=1");
        exit;
    }
}

$tenants = $conn->query("
    SELECT t.id, t.name, a.unit_number
    FROM tenants t
    JOIN apartments a ON t.apartment_id = a.id
    ORDER BY t.name ASC
");

$paymentsResult = $conn->query("
    SELECT payments.*, tenants.name
    FROM payments
    LEFT JOIN tenants ON payments.tenant_id = tenants.id
    ORDER BY payments.id DESC
");

$today = date('Y-m-d');
$paymentCount = (int) ($conn->query("SELECT COUNT(*) AS count FROM payments")->fetch_assoc()['count'] ?? 0);
$monthlyTotal = (float) ($conn->query("SELECT COALESCE(SUM(amount), 0) AS total FROM payments WHERE MONTH(payment_date) = MONTH(CURDATE()) AND YEAR(payment_date) = YEAR(CURDATE())")->fetch_assoc()['total'] ?? 0);
$latestPayment = $conn->query("SELECT MAX(payment_date) AS latest_date FROM payments")->fetch_assoc()['latest_date'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Payment Management</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="../assets/app-theme.css" rel="stylesheet">
</head>
<body>
<div class="page-shell">
  <section class="page-hero">
    <h1>Payment Management</h1>
    <p>Track collections, split payments across months, and keep a cleaner payment history for every tenant.</p>
    <div class="hero-actions">
      <a href="../index.php" class="btn btn-light btn-sm"><i class="fa-solid fa-arrow-left"></i> Dashboard</a>
      <a href="../payment_info.php" class="btn btn-outline-light btn-sm"><i class="fa-solid fa-file-invoice-dollar"></i> Payment Info</a>
    </div>
  </section>

  <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success text-center">Payment record added successfully.</div>
  <?php endif; ?>

  <?php if (!empty($error_message)): ?>
    <div class="alert alert-danger text-center"><?= htmlspecialchars($error_message) ?></div>
  <?php endif; ?>

  <div class="metric-row">
    <div class="metric-card"><strong><?= $paymentCount ?></strong><span>Total payment entries</span></div>
    <div class="metric-card"><strong>PHP <?= number_format($monthlyTotal, 2) ?></strong><span>Collected this month</span></div>
    <div class="metric-card"><strong><?= $latestPayment ? htmlspecialchars($latestPayment) : 'No records' ?></strong><span>Latest payment date</span></div>
  </div>

  <section class="section-card">
    <div class="section-head">
      <h2>Add payment</h2>
      <p>Select the tenant, amount, date, and optional remarks.</p>
    </div>
    <div class="section-body">
      <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Tenant</label>
            <select class="form-select" name="tenant_id" required>
              <option value="">Select Tenant</option>
              <?php if ($tenants && $tenants->num_rows): ?>
                <?php while ($tenant = $tenants->fetch_assoc()): ?>
                  <option value="<?= htmlspecialchars($tenant['id']) ?>">
                    <?= htmlspecialchars($tenant['name']) ?> (Unit <?= htmlspecialchars($tenant['unit_number']) ?>)
                  </option>
                <?php endwhile; ?>
              <?php endif; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Amount</label>
            <input type="number" step="0.01" name="amount" class="form-control" required>
          </div>
          <div class="col-md-3">
            <label class="form-label">Payment Date</label>
            <input type="date" name="payment_date" class="form-control" value="<?= $today ?>" required>
          </div>
          <div class="col-md-2">
            <label class="form-label">Remarks</label>
            <input type="text" name="remarks" class="form-control" placeholder="Advance / deposit">
          </div>
          <div class="col-12 d-flex justify-content-end">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save Payment</button>
          </div>
        </div>
      </form>
    </div>
  </section>

  <section class="section-card mt-4">
    <div class="section-head">
      <h2>Payment records</h2>
      <p>Recent entries with tenant details, amounts, and remarks.</p>
    </div>
    <div class="table-wrap">
      <div class="table-responsive">
        <table class="table table-modern align-middle">
          <thead>
            <tr>
              <th>ID</th>
              <th>Tenant ID</th>
              <th>Name</th>
              <th class="text-end">Amount</th>
              <th>Date</th>
              <th>Remarks</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($paymentsResult && $paymentsResult->num_rows): ?>
              <?php while ($row = $paymentsResult->fetch_assoc()): ?>
                <tr>
                  <td>#<?= htmlspecialchars($row['id']) ?></td>
                  <td><?= htmlspecialchars($row['tenant_id']) ?></td>
                  <td><?= htmlspecialchars($row['name']) ?></td>
                  <td class="text-end">PHP <?= number_format((float) $row['amount'], 2) ?></td>
                  <td><?= htmlspecialchars($row['payment_date']) ?></td>
                  <td><?= nl2br(htmlspecialchars($row['remarks'])) ?></td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr>
                <td colspan="6" class="text-center py-4">No payments recorded yet.</td>
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
