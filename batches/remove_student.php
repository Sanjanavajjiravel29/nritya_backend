<?php
// batches/remove_student.php - Remove/unenroll student from batch

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth_middleware.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(false, "Only POST requests are allowed.", 405);
}

// Strictly restricted to authenticated Admin accounts only
$adminUser = requireRole('admin');

$data = getRequestData();

$batchId = !empty($data["batch_id"]) ? (int)$data["batch_id"] : null;
$studentId = !empty($data["student_id"]) ? (int)$data["student_id"] : null;

if (!$batchId || !$studentId) {
    respond(false, "Both batch_id and student_id are required.", 400);
}

$stmt = $conn->prepare("DELETE FROM batch_students WHERE batch_id = ? AND student_id = ?");
$stmt->bind_param("ii", $batchId, $studentId);

if ($stmt->execute()) {
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected === 0) {
        respond(false, "Student is not enrolled in this batch.", 404);
    }
    
    // Clear user batch_id if it was their active primary batch
    $uStmt = $conn->prepare("UPDATE users SET batch_id = NULL WHERE id = ? AND batch_id = ?");
    $uStmt->bind_param("ii", $studentId, $batchId);
    $uStmt->execute();
    $uStmt->close();

    respond(true, "Student removed from batch successfully.", 200, [
        "batch_id" => $batchId,
        "student_id" => $studentId
    ]);
}

$err = $stmt->error;
$stmt->close();
respond(false, "Could not remove student: " . $err, 500);
?>
