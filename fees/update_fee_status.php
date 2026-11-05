<?php
// fees/update_fee_status.php - Update payment/payout status of fee record

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth_middleware.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(false, "Only POST requests are allowed.", 405);
}

// Strictly restricted to authenticated Admin accounts only
$adminUser = requireRole('admin');

$data = getRequestData();

$feeId = !empty($data["id"]) ? (int)$data["id"] : (!empty($data["fee_id"]) ? (int)$data["fee_id"] : null);
$status = ucfirst(strtolower(trim($data["status"] ?? "Paid")));

if (!$feeId || $feeId < 1) {
    respond(false, "A valid fee ID is required.", 400);
}

$validStatuses = ["Paid", "Pending", "Overdue", "Calculated"];
if (!in_array($status, $validStatuses, true)) {
    respond(false, "Invalid status. Allowed values: Paid, Pending, Overdue, Calculated.", 400);
}

// Check if fee record exists
$chkStmt = $conn->prepare("SELECT id FROM fees_records WHERE id = ? LIMIT 1");
$chkStmt->bind_param("i", $feeId);
$chkStmt->execute();
$found = $chkStmt->get_result()->fetch_assoc();
$chkStmt->close();

if (!$found) {
    respond(false, "Fee record not found.", 404);
}

$stmt = $conn->prepare("UPDATE fees_records SET status = ? WHERE id = ?");
$stmt->bind_param("si", $status, $feeId);

if ($stmt->execute()) {
    $affected = $stmt->affected_rows;
    $stmt->close();
    respond(true, "Fee status updated to {$status}.", 200, [
        "fee_id" => $feeId,
        "status" => $status
    ]);
}

$err = $stmt->error;
$stmt->close();
respond(false, "Could not update fee status: " . $err, 500);
?>

