<?php
require 'config/auth.php';
require_login();
include 'config/db.php';

// Get data from URL
$tenantId = $_GET['tenant_id'] ?? null;
$paymentDate = $_GET['date'] ?? null;
$remark = $_GET['remark'] ?? '';

if (!$tenantId || !$paymentDate) {
    die("Invalid receipt request.");
}

// Fetch tenant and payment info
$stmt = $conn->prepare("
    SELECT t.name, p.amount, p.payment_date, p.remarks
    FROM payments p
    JOIN tenants t ON p.tenant_id = t.id
    WHERE t.id = ? AND DATE(p.payment_date) = DATE(?)
");
$stmt->bind_param("ss", $tenantId, $paymentDate);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

if (!$data) {
    die("Receipt not found.");
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Payment Receipt</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #fff; color: #333; }
        .receipt { border: 1px solid #ccc; padding: 20px; max-width: 420px; margin: auto; border-radius: 10px; }
        h2 { text-align: center; color: #007bff; margin-bottom: 20px; }
        p { line-height: 1.6; margin: 8px 0; }
        .label { font-weight: bold; }
        .center { text-align: center; margin-top: 20px; }
        button { padding: 8px 16px; background: #007bff; color: #fff; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #0056b3; }
        hr { margin: 15px 0; }
    </style>
</head>
<body>
    <div class="receipt">
        <h2>Payment Receipt</h2>
        <p><span class="label">Tenant:</span> <?= htmlspecialchars($data['name']) ?></p>
        <p><span class="label">Payment Date:</span> <?= date('F j, Y', strtotime($data['payment_date'])) ?></p>
        <p><span class="label">Amount:</span> ₱<?= number_format($data['amount'], 2) ?></p>
        <p><span class="label">Remarks:</span> <?= htmlspecialchars($data['remarks']) ?></p>
        <hr>
        <div class="center">
            <p>Thank you for your payment!</p>
            <button onclick="window.print()">🖨️ Print Receipt</button>
        </div>
    </div>
</body>
</html>
