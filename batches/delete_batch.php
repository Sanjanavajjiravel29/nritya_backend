<?php
// batches/delete_batch.php - Admin removes a batch

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth_middleware.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(false, "Only POST requests are allowed.", 405);
}

// Strictly restricted to authenticated Admin accounts only
$adminUser = requireRole('admin');

$data = getRequestData();

$batchId = !empty($data["id"]) ? (int)$data["id"] : (!empty($data["batch_id"]) ? (int)$data["batch_id"] : null);

if (!$batchId || $batchId < 1) {
    respond(false, "A valid batch ID is required.", 400);
}

// Remove relations
$conn->query("DELETE FROM batch_students WHERE batch_id = {$batchId}");
$conn->query("UPDATE users SET batch_id = NULL WHERE batch_id = {$batchId}");

$stmt = $conn->prepare("DELETE FROM batches WHERE id = ?");
$stmt->bind_param("i", $batchId);

if ($stmt->execute()) {
    $affected = $stmt->affected_rows;
    $stmt->close();
    if ($affected > 0) {
        respond(true, "Batch deleted successfully.", 200, ["deleted_batch_id" => $batchId]);
    } else {
        respond(false, "Batch not found or already deleted.", 404);
    }
}

$err = $stmt->error;
$stmt->close();
respond(false, "Could not delete batch: " . $err, 500);
?>

