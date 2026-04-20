<?php
require 'config/auth.php';
require_login();
include 'config/db.php';

$tenants = $conn->query("SELECT id, name FROM tenants ORDER BY name ASC");

$selectedTenant = $_GET['tenant_id'] ?? null;
$selectedYear = $_GET['year'] ?? date('Y');
$payments = [];
$tenant = null;

if ($selectedTenant) {
    $tenantQuery = $conn->prepare("SELECT name FROM tenants WHERE id = ?");
    $tenantQuery->bind_param("s", $selectedTenant);
    $tenantQuery->execute();
    $tenantResult = $tenantQuery->get_result();
    $tenant = $tenantResult->fetch_assoc();

    $paymentQuery = $conn->prepare("
        SELECT amount, payment_date, remarks
        FROM payments
        WHERE tenant_id = ? AND YEAR(payment_date) = ?
        ORDER BY payment_date ASC
    ");
    $paymentQuery->bind_param("si", $selectedTenant, $selectedYear);
    $paymentQuery->execute();
    $result = $paymentQuery->get_result();

    while ($row = $result->fetch_assoc()) {
        $month = (int) date('n', strtotime($row['payment_date']));
        if (!isset($payments[$month])) {
            $payments[$month] = [
                'amount' => 0,
                'payment_dates' => [],
                'remarks' => []
            ];
        }
        $payments[$month]['amount'] += $row['amount'];
        $payments[$month]['payment_dates'][] = date('Y-m-d', strtotime($row['payment_date']));
        $payments[$month]['remarks'][] = htmlspecialchars($row['remarks']);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Tenant Payment Information</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="assets/app-theme.css" rel="stylesheet">
</head>
<body>
<div class="page-shell">
    <section class="page-hero">
        <h1>Payment Information</h1>
        <p>Review a tenant’s year-by-year payment history, month totals, remarks, and receipt links from one cleaner report view.</p>
        <div class="hero-actions">
            <a href="index.php" class="btn btn-light btn-sm"><i class="fa-solid fa-arrow-left"></i> Dashboard</a>
            <a href="payments/index.php" class="btn btn-outline-light btn-sm"><i class="fa-solid fa-money-bill-wave"></i> Payments</a>
        </div>
    </section>

    <section class="section-card">
        <div class="section-head">
            <h2>View tenant payment history</h2>
            <p>Select a tenant and a year to generate the month-by-month payment summary.</p>
        </div>
        <div class="section-body">
            <form method="get" class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label class="form-label">Tenant</label>
                    <select name="tenant_id" class="form-select" required>
                        <option value="">Choose Tenant</option>
                        <?php while ($row = $tenants->fetch_assoc()): ?>
                            <option value="<?= htmlspecialchars($row['id']) ?>" <?= $selectedTenant == $row['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($row['name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Year</label>
                    <select name="year" class="form-select">
                        <?php for ($y = 2020; $y <= date('Y') + 2; $y++): ?>
                            <option value="<?= $y ?>" <?= (string) $selectedYear === (string) $y ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">View History</button>
                </div>
            </form>
        </div>
    </section>

    <?php if ($selectedTenant && $tenant): ?>
        <section class="section-card mt-4">
            <div class="section-head">
                <h2><?= htmlspecialchars($tenant['name']) ?> - <?= htmlspecialchars($selectedYear) ?> Summary</h2>
                <p>Monthly totals, payment dates, remarks, and receipt shortcuts.</p>
            </div>
            <div class="table-wrap">
                <div class="table-responsive">
                    <table class="table table-modern align-middle">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>Amount</th>
                                <th>Payment Dates</th>
                                <th>Remarks</th>
                                <th>Receipt</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <?php $record = $payments[$m] ?? null; ?>
                                <tr>
                                    <td><?= date('F', mktime(0, 0, 0, $m, 1)) ?></td>
                                    <td><?= $record ? 'PHP ' . number_format($record['amount'], 2) : '-' ?></td>
                                    <td>
                                        <?php if ($record): ?>
                                            <?php foreach ($record['payment_dates'] as $d): ?>
                                                <div><?= date('M j, Y', strtotime($d)) ?></div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($record): ?>
                                            <?php foreach ($record['remarks'] as $r): ?>
                                                <div><?= $r !== '' ? $r : 'No remark' ?></div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($record): ?>
                                            <?php foreach ($record['payment_dates'] as $index => $d): ?>
                                                <?php
                                                $encodedDate = urlencode($d);
                                                $encodedRemark = urlencode($record['remarks'][$index]);
                                                ?>
                                                <div>
                                                    <a href="receipt.php?tenant_id=<?= urlencode($selectedTenant) ?>&date=<?= $encodedDate ?>&remark=<?= $encodedRemark ?>" target="_blank" class="btn btn-sm btn-outline-primary mb-1">View Receipt</a>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>

                <div class="page-links">
                    <a href="receipt_all.php?tenant_id=<?= urlencode($selectedTenant) ?>&year=<?= urlencode($selectedYear) ?>" class="btn btn-outline-success" target="_blank">
                        <i class="fa-solid fa-file-lines"></i> Full Summary Receipt
                    </a>
                </div>
            </div>
        </section>
    <?php elseif ($selectedTenant): ?>
        <div class="alert alert-warning text-center mt-4">The selected tenant could not be found.</div>
    <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
