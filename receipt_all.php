<?php
require 'config/auth.php';
require_login();
include 'config/db.php';

$tenantId = $_GET['tenant_id'] ?? null;
$year = $_GET['year'] ?? date('Y');

if (!$tenantId) {
    die("Invalid tenant ID.");
}

// Fetch tenant name
$stmt = $conn->prepare("SELECT name FROM tenants WHERE id = ?");
$stmt->bind_param("s", $tenantId);
$stmt->execute();
$tenantResult = $stmt->get_result();
$tenant = $tenantResult->fetch_assoc();

if (!$tenant) {
    die("Tenant not found.");
}

// Fetch all payments for tenant and year
$query = $conn->prepare("
    SELECT amount, payment_date, remarks 
    FROM payments 
    WHERE tenant_id = ? AND YEAR(payment_date) = ?
    ORDER BY payment_date ASC
");
$query->bind_param("si", $tenantId, $year);
$query->execute();
$result = $query->get_result();

$total = 0;
$payments = [];
while ($row = $result->fetch_assoc()) {
    $payments[] = $row;
    $total += $row['amount'];
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Full Payment Summary - <?= htmlspecialchars($tenant['name']) ?></title>
    <style>
        body { font-family: Arial, sans-serif; background: #fff; margin: 40px; color: #333; }
        .receipt { border: 1px solid #ccc; border-radius: 10px; padding: 20px; max-width: 600px; margin: auto; }
        h2, h3 { text-align: center; color: #007bff; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ccc; padding: 10px; text-align: center; }
        th { background: #007bff; color: white; }
        .total { font-weight: bold; background: #d4edda; }
        .center { text-align: center; margin-top: 20px; }
        button { padding: 8px 16px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; }
        button:hover { background: #0056b3; }
    </style>
</head>
<body>
    <div class="receipt">
        <h2>Tenant Full Payment Summary</h2>
        <h3><?= htmlspecialchars($tenant['name']) ?> — <?= $year ?></h3>

        <?php if (count($payments) > 0): ?>
            <table>
                <tr>
                    <th>Date</th>
                    <th>Amount</th>
                    <th>Remarks</th>
                </tr>
                <?php foreach ($payments as $p): ?>
                    <tr>
                        <td><?= date('F j, Y', strtotime($p['payment_date'])) ?></td>
                        <td>₱<?= number_format($p['amount'], 2) ?></td>
                        <td><?= htmlspecialchars($p['remarks']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr class="total">
                    <td colspan="2">TOTAL PAID</td>
                    <td>₱<?= number_format($total, 2) ?></td>
                </tr>
            </table>

            <div class="center">
                <p>Thank you for your payments!</p>
                <button onclick="window.print()">🖨️ Print Summary</button>
            </div>
        <?php else: ?>
            <p class="center">No payments found for this year.</p>
        <?php endif; ?>
    </div>
</body>
</html>
