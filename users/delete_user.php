<?php
// users/delete_user.php - Delete or deactivate user account

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth_middleware.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(false, "Only POST requests are allowed.", 405);
}

// Strictly restricted to authenticated Admin accounts only
$adminUser = requireRole('admin');

$data = getRequestData();

$userId = !empty($data["id"]) ? (int)$data["id"] : (!empty($data["user_id"]) ? (int)$data["user_id"] : null);

if (!$userId || $userId < 1) {
    respond(false, "A valid user ID is required.", 400);
}

// Clean up associated relations
$conn->query("DELETE FROM batch_students WHERE student_id = {$userId}");
$conn->query("DELETE FROM attendance WHERE student_id = {$userId}");
$conn->query("DELETE FROM homework_submissions WHERE student_id = {$userId}");
$conn->query("DELETE FROM fees_records WHERE user_id = {$userId}");

$stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);

if ($stmt->execute()) {
    $affected = $stmt->affected_rows;
    $stmt->close();
    if ($affected > 0) {
        respond(true, "User deleted successfully.", 200, ["deleted_user_id" => $userId]);
    } else {
        respond(false, "User not found or already deleted.", 404);
    }
}

$err = $stmt->error;
$stmt->close();
respond(false, "Could not delete user: " . $err, 500);
?>

