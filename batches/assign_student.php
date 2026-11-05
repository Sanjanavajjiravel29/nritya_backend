<?php
// batches/assign_student.php - Enroll a student into a batch

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

// Check batch exists
$bCheck = $conn->prepare("SELECT batch_name FROM batches WHERE id = ? LIMIT 1");
$bCheck->bind_param("i", $batchId);
$bCheck->execute();
$batch = $bCheck->get_result()->fetch_assoc();
$bCheck->close();

if (!$batch) {
    respond(false, "Batch not found.", 404);
}

// Check student exists
$sCheck = $conn->prepare("SELECT full_name, role FROM users WHERE id = ? LIMIT 1");
$sCheck->bind_param("i", $studentId);
$sCheck->execute();
$student = $sCheck->get_result()->fetch_assoc();
$sCheck->close();

if (!$student) {
    respond(false, "Student user not found.", 404);
}

if ($student["role"] !== "student") {
    respond(false, "Selected user is not a student.", 400);
}

// Prevent duplicate active assignment
$chk = $conn->prepare("SELECT id FROM batch_students WHERE batch_id = ? AND student_id = ? AND status = 'Active' LIMIT 1");
$chk->bind_param("ii", $batchId, $studentId);
$chk->execute();
if ($chk->get_result()->fetch_assoc()) {
    $chk->close();
    respond(false, "Student is already enrolled in {$batch['batch_name']}.", 409);
}
$chk->close();

// Insert or update into batch_students
$stmt = $conn->prepare("INSERT INTO batch_students (batch_id, student_id, status) VALUES (?, ?, 'Active') ON DUPLICATE KEY UPDATE status = 'Active'");
$stmt->bind_param("ii", $batchId, $studentId);

if ($stmt->execute()) {
    $stmt->close();

    // Also update student's primary batch_id if null or 0
    $uStmt = $conn->prepare("UPDATE users SET batch_id = ? WHERE id = ? AND (batch_id IS NULL OR batch_id = 0)");
    $uStmt->bind_param("ii", $batchId, $studentId);
    $uStmt->execute();
    $uStmt->close();

    respond(true, "Student successfully assigned to {$batch['batch_name']}.", 200, [
        "batch_id" => $batchId,
        "batch_name" => $batch["batch_name"],
        "student_id" => $studentId,
        "student_name" => $student["full_name"]
    ]);
}

$err = $stmt->error;
$stmt->close();
respond(false, "Could not assign student: " . $err, 500);
?>
